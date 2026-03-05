<?php
namespace AISC\Admin;

if (!defined('ABSPATH')) exit;

class Menu {
    const SLUG = 'aisc-dashboard';

    public function register(): void {
        add_action('admin_menu', [$this, 'add_menu']);
    }

    public function add_menu(): void {
        add_menu_page(
            __('AI Site Copilot', 'ai-site-copilot'),
            __('AI Copilot', 'ai-site-copilot'),
            'manage_options',
            self::SLUG,
            [$this, 'render_dashboard'],
            'dashicons-shield-alt',
            58
        );

        add_submenu_page(
            self::SLUG,
            __('Dashboard', 'ai-site-copilot'),
            __('Dashboard', 'ai-site-copilot'),
            'manage_options',
            self::SLUG,
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            self::SLUG,
            __('Settings', 'ai-site-copilot'),
            __('Settings', 'ai-site-copilot'),
            'manage_options',
            'aisc-settings',
            [new SettingsPage(), 'render']
        );
    }

    public function render_dashboard(): void {
        (new Dashboard())->render();
    }
}