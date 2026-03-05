<?php
/**
 * Plugin Name: AI Site Copilot
 * Description: AI-powered WordPress admin assistant: site analysis, SEO fixes, internal linking, logs & usage dashboard.
 * Version: 0.1.0
 * Author: Rakesh Chaudhary
 * Text Domain: ai-site-copilot
 */

if (!defined('ABSPATH')) exit;

define('AISC_VERSION', '0.1.0');
define('AISC_PATH', plugin_dir_path(__FILE__));
define('AISC_URL', plugin_dir_url(__FILE__));

require_once AISC_PATH . 'includes/Core/Loader.php';

register_activation_hook(__FILE__, function () {
    \AISC\Database\LogsTable::install();
});

add_action('plugins_loaded', function () {
    $plugin = new \AISC\Core\Plugin();
    $plugin->init();
});