<?php
namespace AISC\Admin;

use AISC\Security\Permissions;

if (!defined('ABSPATH')) exit;

class SettingsPage {
    const OPT_API_KEY = 'aisc_openai_api_key';
    const OPT_MODEL   = 'aisc_openai_model';

    public function register(): void {
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_settings(): void {
        register_setting('aisc_settings_group', self::OPT_API_KEY, [
            'type' => 'string',
            'sanitize_callback' => [$this, 'sanitize_key'],
            'default' => '',
        ]);

        register_setting('aisc_settings_group', self::OPT_MODEL, [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => 'gpt-4.1-mini',
        ]);
    }

    public function sanitize_key($value): string {
        $value = is_string($value) ? trim($value) : '';
        $value = preg_replace('/\s+/', '', wp_strip_all_tags($value));
        return $value;
    }

    public function render(): void {
        if (!Permissions::can_manage()) wp_die('No permission.');

        $key = (string) get_option(self::OPT_API_KEY, '');
        $model = (string) get_option(self::OPT_MODEL, 'gpt-4.1-mini');
        $masked = $key ? str_repeat('•', max(0, strlen($key) - 6)) . substr($key, -6) : '';
        ?>
        <div class="wrap aisc-wrap">
            <h1>AI Site Copilot Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('aisc_settings_group'); ?>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="aisc_openai_api_key">OpenAI API Key</label></th>
                        <td>
                            <input type="password" id="aisc_openai_api_key" name="<?php echo esc_attr(self::OPT_API_KEY); ?>"
                                   value="<?php echo esc_attr($key); ?>" class="regular-text" />
                            <p class="description">Saved key ends with: <code><?php echo esc_html($masked); ?></code></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label for="aisc_openai_model">Model</label></th>
                        <td>
                            <select id="aisc_openai_model" name="<?php echo esc_attr(self::OPT_MODEL); ?>">
                                <?php foreach (['gpt-4.1-mini','gpt-4.1','gpt-4o-mini'] as $m) : ?>
                                    <option value="<?php echo esc_attr($m); ?>" <?php selected($model, $m); ?>>
                                        <?php echo esc_html($m); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">You can change this later per feature (SEO, rewrite, etc.).</p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
}