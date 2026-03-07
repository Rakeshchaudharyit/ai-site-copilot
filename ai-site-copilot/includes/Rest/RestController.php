<?php
namespace AISC\Rest;

use WP_REST_Request;
use WP_REST_Response;

use AISC\Security\Permissions;
use AISC\Database\LogsRepository;
use AISC\Admin\SettingsPage;

use AISC\Services\PostScanner;
use AISC\AI\AIClient;
use AISC\AI\PromptManager;
use AISC\AI\CostCalculator;
if (!defined('ABSPATH'))
    exit;

class RestController
{
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'routes']);
    }

    public function routes(): void
    {
        register_rest_route('aisc/v1', '/test', [
            'methods' => 'POST',
            'callback' => [$this, 'test_api'],
            'permission_callback' => [$this, 'perm_manage'],
        ]);

        register_rest_route('aisc/v1', '/scan', [
            'methods' => 'POST',
            'callback' => [$this, 'scan_site'],
            'permission_callback' => [$this, 'perm_manage'],
        ]);

        register_rest_route('aisc/v1', '/posts', [
            'methods' => 'GET',
            'callback' => [$this, 'get_posts'],
            'permission_callback' => function () {
                return Permissions::can_manage();
            }
        ]);

        register_rest_route('aisc/v1', '/seo-fix', [
            'methods' => 'POST',
            'callback' => [$this, 'seo_fix'],
            'permission_callback' => [$this, 'perm_manage'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ]
            ]
        ]);

        //  Quick Fix (SEO + Excerpt)
        register_rest_route('aisc/v1', '/quick-fix', [
            'methods' => 'POST',
            'callback' => [$this, 'quick_fix'],
            'permission_callback' => [$this, 'perm_manage'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ]
            ]
        ]);

        // Internal link suggestions (AI)
        register_rest_route('aisc/v1', '/internal-links', [
            'methods' => 'POST',
            'callback' => [$this, 'internal_links'],
            'permission_callback' => [$this, 'perm_manage'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ]
            ]
        ]);

        // Insert selected links into content
        register_rest_route('aisc/v1', '/insert-links', [
            'methods' => 'POST',
            'callback' => [$this, 'insert_links'],
            'permission_callback' => [$this, 'perm_manage'],
            'args' => [
                'post_id' => [
                    'required' => true,
                    'sanitize_callback' => 'absint',
                ],
                'suggestions' => [
                    'required' => true,
                ]
            ]
        ]);
    }

    public function perm_manage()
    {
        if (!is_user_logged_in()) {
            return new \WP_Error('aisc_not_logged_in', 'Not logged in', ['status' => 401]);
        }
        if (!current_user_can('manage_options')) {
            return new \WP_Error('aisc_forbidden', 'Forbidden', ['status' => 403]);
        }
        return true;
    }

    public function test_api(WP_REST_Request $req): WP_REST_Response
    {
        $key = (string) get_option(SettingsPage::OPT_API_KEY, '');
        $model = (string) get_option(SettingsPage::OPT_MODEL, 'gpt-4.1-mini');
        $ok = (strlen($key) > 20);

        (new LogsRepository())->insert([
            'action' => 'test_api',
            'status' => $ok ? 'success' : 'error',
            'tokens_used' => 0,
            'cost_est' => 0,
            'message' => $ok
                ? 'API key present. Ready for real OpenAI call.'
                : 'API key missing/invalid. Please save in Settings.',
        ]);

        return new WP_REST_Response([
            'ok' => $ok,
            'model' => $model,
            'message' => $ok
                ? '✅ Key saved. Ready for real AI actions.'
                : '❌ Key not saved yet. Save API key in Settings.'
        ], $ok ? 200 : 400);
    }

    public function scan_site(WP_REST_Request $req): WP_REST_Response
    {
        $scanner = new PostScanner();
        $result = $scanner->scan([
            'limit' => 200,
            'thin_words' => 300,
            'short_title_chars' => 30,
        ]);

        (new LogsRepository())->insert([
            'action' => 'scan_site',
            'status' => 'success',
            'tokens_used' => 0,
            'cost_est' => 0,
            'message' => 'Site scan completed (posts/pages checked).'
        ]);

        return new WP_REST_Response([
            'ok' => true,
            'summary' => $result['summary'],
            'top_issues' => $result['top_issues'],
            'message' => '✅ Scan complete.'
        ], 200);
    }

    public function get_posts(WP_REST_Request $req): WP_REST_Response
    {
        $limit = max(5, min(50, (int) $req->get_param('limit')));
        $type = $req->get_param('type') ? sanitize_text_field($req->get_param('type')) : 'any';

        $qArgs = [
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ];
        if ($type !== 'any')
            $qArgs['post_type'] = [$type];
        else
            $qArgs['post_type'] = ['post', 'page'];

        $q = new \WP_Query($qArgs);

        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'id' => (int) $p->ID,
                'type' => $p->post_type,
                'title' => get_the_title($p->ID),
            ];
        }

        return new WP_REST_Response(['ok' => true, 'items' => $items], 200);
    }

    public function seo_fix(WP_REST_Request $req): WP_REST_Response
    {
        $post_id = absint($req->get_param('post_id'));
        $post = get_post($post_id);

        if (!$post || $post->post_status !== 'publish') {
            return new WP_REST_Response(['ok' => false, 'message' => 'Invalid post/page.'], 400);
        }

        $apiKey = (string) get_option(SettingsPage::OPT_API_KEY, '');
        $model = (string) get_option(SettingsPage::OPT_MODEL, 'gpt-4.1-mini');

        $client = new AIClient($apiKey, $model);
        if (!$client->is_ready()) {
            (new LogsRepository())->insert([
                'action' => 'seo_fix',
                'status' => 'error',
                'message' => 'SEO Fix failed: API key not set.',
            ]);
            return new WP_REST_Response(['ok' => false, 'message' => 'API key not set.'], 400);
        }

        $pm = new PromptManager();
        $prompt = $pm->seo_fix_prompt([
            'title' => get_the_title($post_id),
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
        ]);

        $ai = $client->respond($prompt, [
            'retries' => 2,
            'timeout' => 30,
            'max_output_tokens' => 250,
        ]);

        if (!$ai['ok']) {
            (new LogsRepository())->insert([
                'action' => 'seo_fix',
                'status' => 'error',
                'tokens_used' => 0,
                'cost_est' => 0,
                'message' => 'OpenAI error: ' . esc_html($ai['error']),
            ]);
            return new WP_REST_Response(['ok' => false, 'message' => 'AI error: ' . $ai['error']], 500);
        }

        $parsed = $pm->parse_json((string) $ai['text']);
        $seoTitle = sanitize_text_field($parsed['seo_title'] ?? '');
        $metaDesc = sanitize_text_field($parsed['meta_description'] ?? '');
        $focusKw = sanitize_text_field($parsed['focus_keyphrase'] ?? '');

        if (!$seoTitle || !$metaDesc) {
            $tokensUsed = (int) ($ai['tokens'] ?? 0);
            $cost = CostCalculator::estimate($model, $tokensUsed);

            (new LogsRepository())->insert([
                'action' => 'seo_fix',
                'status' => 'error',
                'tokens_used' => $tokensUsed,
                'cost_est' => $cost,
                'message' => 'OpenAI error: ' . esc_html($ai['error']),
            ]);
            return new WP_REST_Response(['ok' => false, 'message' => 'AI output invalid. Try again.'], 500);
        }

        update_post_meta($post_id, '_yoast_wpseo_title', $seoTitle);
        update_post_meta($post_id, '_yoast_wpseo_metadesc', $metaDesc);
        if (!empty($focusKw)) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $focusKw);
        }

        $tokensUsed = (int) ($ai['tokens'] ?? 0);

        // If your AIClient later returns split tokens, use them:
        $inputTokens = (int) ($ai['input_tokens'] ?? 0);
        $outputTokens = (int) ($ai['output_tokens'] ?? 0);

        $cost = CostCalculator::estimate($model, $tokensUsed, $inputTokens, $outputTokens);

        (new LogsRepository())->insert([
            'action' => 'seo_fix',
            'status' => 'success',
            'tokens_used' => $tokensUsed,
            'cost_est' => $cost,
            'message' => 'SEO meta generated for post_id=' . $post_id,
        ]);

        return new WP_REST_Response([
            'ok' => true,
            'post_id' => $post_id,
            'seo_title' => $seoTitle,
            'meta_description' => $metaDesc,
            'focus_keyphrase' => $focusKw,
            'message' => '✅ SEO generated and saved in Yoast fields.'
        ], 200);
    }

    public function quick_fix(WP_REST_Request $req): WP_REST_Response
    {
        $post_id = absint($req->get_param('post_id'));
        $post = get_post($post_id);

        if (!$post || $post->post_status !== 'publish') {
            return new WP_REST_Response(['ok' => false, 'message' => 'Invalid post/page.'], 400);
        }

        // Safety: block system pages
        $slug = (string) $post->post_name;
        $blocked = ['checkout', 'cart', 'my-account', 'thank-you', 'order-received'];
        foreach ($blocked as $bs) {
            if ($slug === $bs || (function_exists('str_contains') && str_contains($slug, $bs))) {
                return new WP_REST_Response([
                    'ok' => false,
                    'message' => 'Quick Fix disabled for system/checkout pages.',
                ], 422);
            }
        }

        $changed = [
            'seo' => false,
            'excerpt' => false,
        ];

        // 1) SEO Fix (reuse method)
        $seo_req = new WP_REST_Request('POST', '/aisc/v1/seo-fix');
        $seo_req->set_param('post_id', $post_id);
        $seo_res = $this->seo_fix($seo_req);
        $seo_data = $seo_res->get_data();

        if (!empty($seo_data['ok'])) {
            $changed['seo'] = true;
        }

        // 2) Excerpt fix (if empty)
        $has_excerpt = !empty(trim((string) $post->post_excerpt));
        if (!$has_excerpt) {
            $apiKey = (string) get_option(SettingsPage::OPT_API_KEY, '');
            $model = (string) get_option(SettingsPage::OPT_MODEL, 'gpt-4.1-mini');
            $client = new AIClient($apiKey, $model);

            $excerpt_text = '';

            if ($client->is_ready()) {
                $pm = new PromptManager();

                $plain = wp_strip_all_tags((string) $post->post_content);
                $plain = mb_substr($plain, 0, 1800);

                $prompt =
                    "You're an SEO assistant.
Write a WordPress excerpt for this content.

Rules:
- 140-160 characters
- plain text only
- no quotes
- no emojis
Return STRICT JSON only.

Output:
{ \"excerpt\": \"...\" }

Title: " . get_the_title($post_id) . "
Content: " . $plain;

                $ai = $client->respond($prompt, [
                    'retries' => 2,
                    'timeout' => 30,
                    'max_output_tokens' => 120,
                ]);

                if (!empty($ai['ok'])) {
                    $parsed = $pm->parse_json((string) $ai['text']);
                    $excerpt_text = sanitize_text_field($parsed['excerpt'] ?? '');
                }
            }

            // fallback
            if (!$excerpt_text) {
                $plain = wp_strip_all_tags((string) $post->post_content);
                $plain = preg_replace('/\s+/', ' ', trim($plain));
                $excerpt_text = mb_substr($plain, 0, 160);
            }

            if ($excerpt_text) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_excerpt' => $excerpt_text,
                ]);
                $changed['excerpt'] = true;
            }
        }

        (new LogsRepository())->insert([
            'action' => 'quick_fix',
            'status' => 'success',
            'tokens_used' => 0,
            'cost_est' => 0,
            'message' => 'Quick Fix applied for post_id=' . $post_id .
                ' (seo=' . ($changed['seo'] ? 'yes' : 'no') .
                ', excerpt=' . ($changed['excerpt'] ? 'yes' : 'no') . ')',
        ]);

        return new WP_REST_Response([
            'ok' => true,
            'post_id' => $post_id,
            'changed' => $changed,
            'seo' => $seo_data,
            'message' => '✅ Quick Fix completed.',
        ], 200);
    }

    public function internal_links(WP_REST_Request $req): WP_REST_Response
    {
        $post_id = absint($req->get_param('post_id'));
        $post = get_post($post_id);

        if (!$post || $post->post_status !== 'publish') {
            return new WP_REST_Response(['ok' => false, 'message' => 'Invalid post/page.'], 400);
        }

        $apiKey = (string) get_option(SettingsPage::OPT_API_KEY, '');
        $model = (string) get_option(SettingsPage::OPT_MODEL, 'gpt-4.1-mini');
        $client = new AIClient($apiKey, $model);

        if (!$client->is_ready()) {
            (new LogsRepository())->insert([
                'action' => 'internal_links',
                'status' => 'error',
                'message' => 'Internal Links failed: API key not set.',
            ]);
            return new WP_REST_Response(['ok' => false, 'message' => 'API key not set.'], 400);
        }

        $candidates = [];
        $q = new \WP_Query([
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'orderby' => 'date',
            'order' => 'DESC',
            'post__not_in' => [$post_id],
            'no_found_rows' => true,
        ]);

        foreach ($q->posts as $p) {
            $candidates[] = [
                'id' => (int) $p->ID,
                'title' => get_the_title($p->ID),
                'url' => get_permalink($p->ID),
            ];
        }

        $pm = new PromptManager();
        $prompt = $pm->internal_links_prompt([
            'title' => get_the_title($post_id),
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'candidates' => $candidates,
        ]);

        $ai = $client->respond($prompt, [
            'retries' => 2,
            'timeout' => 40,
            'max_output_tokens' => 500,
        ]);

        if (!$ai['ok']) {
            (new LogsRepository())->insert([
                'action' => 'internal_links',
                'status' => 'error',
                'tokens_used' => 0,
                'cost_est' => 0,
                'message' => 'OpenAI error: ' . esc_html($ai['error']),
            ]);
            return new WP_REST_Response(['ok' => false, 'message' => 'AI error: ' . $ai['error']], 500);
        }

        $parsed = $pm->parse_json((string) $ai['text']);
        $suggestions = is_array($parsed['suggestions'] ?? null) ? $parsed['suggestions'] : [];

        $clean = [];
        $seen = [];

        foreach ($suggestions as $s) {
            $anchor = sanitize_text_field($s['anchor'] ?? '');
            $url = esc_url_raw($s['url'] ?? '');
            $title = sanitize_text_field($s['title'] ?? '');
            $reason = sanitize_text_field($s['reason'] ?? '');

            if (!$anchor || !$url)
                continue;

            $key = strtolower($url);
            if (isset($seen[$key]))
                continue;
            $seen[$key] = true;

            $plain = wp_strip_all_tags((string) $post->post_content);
            if ($plain && mb_stripos($plain, $anchor) === false)
                continue;

            $clean[] = [
                'anchor' => $anchor,
                'url' => $url,
                'title' => $title,
                'reason' => $reason,
            ];

            if (count($clean) >= 5)
                break;
        }

        if (count($clean) < 3) {
            return new WP_REST_Response([
                'ok' => false,
                'message' => 'AI returned duplicate/invalid suggestions. Try again (or add more internal posts).'
            ], 422);
        }

        (new LogsRepository())->insert([
            'action' => 'internal_links',
            'status' => 'success',
            'tokens_used' => (int) $ai['tokens'],
            'cost_est' => 0,
            'message' => 'Internal link suggestions generated for post_id=' . $post_id,
        ]);

        return new WP_REST_Response([
            'ok' => true,
            'post_id' => $post_id,
            'suggestions' => $clean,
            'message' => '✅ Suggestions generated.'
        ], 200);
    }

    public function insert_links(WP_REST_Request $req): WP_REST_Response
    {
        $post_id = absint($req->get_param('post_id'));
        $post = get_post($post_id);

        if (!$post || $post->post_status !== 'publish') {
            return new WP_REST_Response(['ok' => false, 'message' => 'Invalid post/page.'], 400);
        }

        $suggestions = $req->get_param('suggestions');
        if (!is_array($suggestions) || empty($suggestions)) {
            return new WP_REST_Response(['ok' => false, 'message' => 'No suggestions provided.'], 400);
        }

        $content = (string) $post->post_content;

        $inserted = 0;
        $skipped = 0;

        foreach ($suggestions as $s) {
            $anchor = sanitize_text_field($s['anchor'] ?? '');
            $url = esc_url_raw($s['url'] ?? '');

            if (!$anchor || !$url) {
                $skipped++;
                continue;
            }

            if (stripos($content, $url) !== false) {
                $skipped++;
                continue;
            }

            $plain = wp_strip_all_tags($content);
            if (!preg_match('/\b' . preg_quote($anchor, '/') . '\b/u', $plain)) {
                $skipped++;
                continue;
            }

            $link = '<a href="' . esc_url($url) . '">' . esc_html($anchor) . '</a>';
            $new = preg_replace('/\b' . preg_quote($anchor, '/') . '\b/u', $link, $content, 1, $count);

            if ($count > 0 && $new && $new !== $content) {
                $content = $new;
                $inserted++;
            } else {
                $skipped++;
            }
        }

        wp_update_post([
            'ID' => $post_id,
            'post_content' => $content,
        ]);

        (new LogsRepository())->insert([
            'action' => 'insert_links',
            'status' => 'success',
            'tokens_used' => 0,
            'cost_est' => 0,
            'message' => 'Inserted ' . $inserted . ' links for post_id=' . $post_id . ' (skipped ' . $skipped . ').',
        ]);

        return new WP_REST_Response([
            'ok' => true,
            'post_id' => $post_id,
            'inserted' => $inserted,
            'skipped' => $skipped,
            'message' => '✅ Links inserted.'
        ], 200);
    }
}
