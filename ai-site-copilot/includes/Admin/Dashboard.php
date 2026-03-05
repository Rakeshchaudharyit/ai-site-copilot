<?php
namespace AISC\Admin;

use AISC\Security\Permissions;
use AISC\Security\NonceManager;
use AISC\Database\LogsRepository;

if (!defined('ABSPATH'))
    exit;

class Dashboard
{
    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);

        // ✅ AJAX handler for post selector dropdown
        add_action('wp_ajax_aisc_get_posts', [$this, 'ajax_get_posts']);
    }

    public function ajax_get_posts(): void
    {
        if (!Permissions::can_manage()) {
            wp_send_json_error(['message' => 'Forbidden'], 403);
        }

        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!NonceManager::verify($nonce)) {
            wp_send_json_error(['message' => 'Invalid nonce'], 401);
        }

        $limit = isset($_POST['limit']) ? absint($_POST['limit']) : 30;
        $limit = max(5, min(50, $limit));

        $type = sanitize_text_field($_POST['type'] ?? 'any');
        $post_types = ($type === 'any') ? ['post', 'page'] : [$type];

        $q = new \WP_Query([
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ]);

        $items = [];
        foreach ($q->posts as $p) {
            $items[] = [
                'id' => (int) $p->ID,
                'type' => $p->post_type,
                'title' => get_the_title($p->ID),
            ];
        }

        wp_send_json_success(['items' => $items]);
    }

    public function enqueue(string $hook): void
    {
        // Load only on our pages
        if (strpos($hook, 'aisc') === false)
            return;

        // ✅ Cache-buster versions (forces latest JS/CSS)
        $css_ver = file_exists(AISC_PATH . 'assets/admin.css') ? filemtime(AISC_PATH . 'assets/admin.css') : AISC_VERSION;
        $js_ver = file_exists(AISC_PATH . 'assets/admin.js') ? filemtime(AISC_PATH . 'assets/admin.js') : AISC_VERSION;

        wp_enqueue_style('aisc-admin', AISC_URL . 'assets/admin.css', [], $css_ver);
        wp_enqueue_script('aisc-admin', AISC_URL . 'assets/admin.js', ['jquery'], $js_ver, true);

        wp_localize_script('aisc-admin', 'AISC', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => untrailingslashit(rest_url('aisc/v1')),
            'nonce' => NonceManager::create(),
            'restNonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public function render(): void
    {
        if (!Permissions::can_manage()) {
            wp_die(__('You do not have permission to access this page.', 'ai-site-copilot'));
        }

        $logs = (new LogsRepository())->latest(8);
        $apiKey = (string) get_option(SettingsPage::OPT_API_KEY, '');
        $model = (string) get_option(SettingsPage::OPT_MODEL, 'gpt-4.1-mini');
        ?>
        <div class="wrap aisc-wrap">
            <div class="aisc-header">
                <div>
                    <h1>AI Site Copilot</h1>
                    <p class="aisc-sub">Analyze your site, improve SEO, and automate improvements — with secure logs & controls.
                    </p>
                </div>
                <div class="aisc-pill">
                    <span class="aisc-dot <?php echo $apiKey ? 'ok' : 'bad'; ?>"></span>
                    <strong><?php echo $apiKey ? 'Connected' : 'Not Connected'; ?></strong>
                    <span class="aisc-muted">Model: <?php echo esc_html($model); ?></span>
                </div>
            </div>

            <div class="aisc-grid">

                <!-- Quick Actions -->
                <div class="aisc-card">
                    <h3>Quick Actions</h3>
                    <p class="aisc-muted">Run safe checks and see results instantly.</p>

                    <div class="aisc-actions">
                        <button class="button button-primary aisc-btn" data-action="test_api">Test API Connection</button>
                        <button class="button aisc-btn" data-action="scan_site">Scan Site</button>
                        <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=aisc-settings')); ?>">Settings</a>
                    </div>

                    <div class="aisc-result" id="aisc-result" style="display:none;"></div>
                </div>


                <!-- KEEP THIS CARD -->
                <div class="aisc-card">
                    <h3>What this plugin will do</h3>

                    <ul class="aisc-list">
                        <li><strong>Site Analyzer</strong> → missing meta, weak titles, thin content</li>
                        <li><strong>SEO Fixer</strong> → generate SEO title + meta, readability improvements</li>
                        <li><strong>Internal Link Engine</strong> → suggest/insert internal links</li>
                        <li><strong>Audit Logs</strong> → track tokens/cost/errors per action</li>
                    </ul>
                </div>


                <!-- SEO Fixer -->
                <div class="aisc-card">
                    <h3>SEO Fixer (AI)</h3>
                    <p class="aisc-muted">Pick a post/page and generate SEO title + meta description.</p>

                    <div class="aisc-field">
                        <label class="aisc-label">Select Post/Page</label>

                        <select id="aisc-post-select" style="width:100%; max-width:520px;">
                            <option value="">Loading...</option>
                        </select>
                    </div>

                    <div class="aisc-actions" style="margin-top:10px;">
                        <button class="button button-primary" id="aisc-seo-fix-btn">
                            Generate SEO
                        </button>
                    </div>

                    <div class="aisc-result" id="aisc-seo-result" style="display:none;"></div>
                </div>
                <!-- Internal Link Engine -->
                <div class="aisc-card">
                    <h3>Internal Link Engine (AI)</h3>
                    <p class="aisc-muted">Suggest 5 internal links for a post/page and optionally insert them automatically.</p>

                    <div class="aisc-field">
                        <label class="aisc-label">Select Post/Page</label>
                        <select id="aisc-il-post-select" style="width:100%; max-width:520px;">
                            <option value="">Loading...</option>
                        </select>
                    </div>

                    <div class="aisc-actions" style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap;">
                        <button class="button button-primary" id="aisc-il-suggest-btn">Suggest Links</button>
                        <button class="button" id="aisc-il-insert-btn" disabled>Insert Selected</button>
                    </div>

                    <div class="aisc-result" id="aisc-il-result" style="display:none; margin-top:10px;"></div>
                </div>

                <!-- Recent Logs -->
                <div class="aisc-card aisc-card-wide">

                    <div class="aisc-row">
                        <h3>Recent Logs</h3>
                        <span class="aisc-muted">Latest actions & system events</span>
                    </div>

                    <table class="widefat striped">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>Tokens</th>
                                <th>Cost</th>
                                <th>Message</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6">No logs yet. Run “Test API Connection”.</td>
                                </tr>
                            <?php else:
                                foreach ($logs as $row): ?>
                                    <tr>
                                        <td><?php echo esc_html($row['created_at']); ?></td>
                                        <td><code><?php echo esc_html($row['action']); ?></code></td>

                                        <td>
                                            <span class="aisc-badge <?php echo $row['status'] === 'success' ? 'ok' : 'bad'; ?>">
                                                <?php echo esc_html($row['status']); ?>
                                            </span>
                                        </td>

                                        <td><?php echo (int) $row['tokens_used']; ?></td>
                                        <td><?php echo esc_html(number_format((float) $row['cost_est'], 4)); ?></td>
                                        <td class="aisc-msg"><?php echo wp_kses_post($row['message']); ?></td>
                                    </tr>
                                <?php endforeach; endif; ?>
                        </tbody>

                    </table>
                </div>

            </div>
        </div>
        <?php
    }
}