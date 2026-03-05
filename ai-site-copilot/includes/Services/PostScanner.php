<?php
namespace AISC\Services;

if (!defined('ABSPATH')) exit;

class PostScanner
{
    public function scan(array $args = []): array
    {
        $defaults = [
            'post_types' => ['post', 'page'],
            'limit' => 200,
            'thin_words' => 300,
            'short_title_chars' => 30,
        ];
        $args = array_merge($defaults, $args);

        $q = new \WP_Query([
            'post_type' => $args['post_types'],
            'post_status' => 'publish',
            'posts_per_page' => (int) $args['limit'],
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
            'fields' => 'ids',
        ]);

        $issues = [];
        $summary = [
            'total_checked' => 0,
            'thin_content' => 0,
            'missing_excerpt' => 0,
            'missing_featured' => 0,
            'short_title' => 0,
            'no_internal_links' => 0,
        ];

        $siteHost = parse_url(home_url(), PHP_URL_HOST);

        foreach ($q->posts as $post_id) {
            $summary['total_checked']++;

            $post = get_post($post_id);
            if (!$post) continue;

            $title = (string) get_the_title($post_id);
            $content = (string) $post->post_content;
            $excerpt = (string) $post->post_excerpt;

            $plain = trim(wp_strip_all_tags($content));
            $wordCount = str_word_count($plain);

            $hasFeatured = has_post_thumbnail($post_id);
            $titleChars = mb_strlen(trim($title));
            $internalLinks = $this->count_internal_links($content, (string) $siteHost);

            $postIssues = [];

            if ($wordCount > 0 && $wordCount < (int) $args['thin_words']) {
                $summary['thin_content']++;
                $postIssues[] = 'thin_content';
            }
            if (!$excerpt) {
                $summary['missing_excerpt']++;
                $postIssues[] = 'missing_excerpt';
            }
            if (!$hasFeatured) {
                $summary['missing_featured']++;
                $postIssues[] = 'missing_featured_image';
            }
            if ($titleChars > 0 && $titleChars < (int) $args['short_title_chars']) {
                $summary['short_title']++;
                $postIssues[] = 'short_title';
            }
            if ($internalLinks === 0) {
                $summary['no_internal_links']++;
                $postIssues[] = 'no_internal_links';
            }

            if (!empty($postIssues)) {
                $slug = (string) $post->post_name;

                // disable quick fix for system pages
                $blocked_slugs = ['checkout', 'cart', 'my-account', 'thank-you', 'order-received'];
                $can_quick_fix = true;
                foreach ($blocked_slugs as $bs) {
                    if ($slug === $bs || (function_exists('str_contains') && str_contains($slug, $bs))) {
                        $can_quick_fix = false;
                        break;
                    }
                }

                $issues[] = [
                    'post_id' => (int) $post_id,
                    'type' => $post->post_type,
                    'title' => $title,
                    'slug' => $slug,
                    'can_quick_fix' => $can_quick_fix,

                    'edit_link' => get_edit_post_link($post_id, 'raw'),
                    'permalink' => get_permalink($post_id),
                    'word_count' => $wordCount,
                    'internal_links' => $internalLinks,
                    'issues' => $postIssues,
                ];
            }
        }

        usort($issues, function ($a, $b) {
            return count($b['issues']) <=> count($a['issues']);
        });

        return [
            'summary' => $summary,
            'top_issues' => array_slice($issues, 0, 15),
        ];
    }

    private function count_internal_links(string $html, string $siteHost): int
    {
        if (!$siteHost) return 0;

        $count = 0;

        if (preg_match_all('/<a\s[^>]*href=("|\')(.*?)\1/i', $html, $m)) {
            foreach ($m[2] as $href) {
                $href = trim((string) $href);

                if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                    continue;
                }

                if (str_starts_with($href, '/')) {
                    $count++;
                    continue;
                }

                $host = parse_url($href, PHP_URL_HOST);
                if ($host && $host === $siteHost) $count++;
            }
        }

        return $count;
    }
}