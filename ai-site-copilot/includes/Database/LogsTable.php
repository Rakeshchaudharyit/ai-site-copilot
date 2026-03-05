<?php
namespace AISC\Database;

if (!defined('ABSPATH')) exit;

class LogsTable {

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'aisc_logs';
    }

    public static function install(): void {
        global $wpdb;

        $table = self::table_name();
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            action VARCHAR(100) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            tokens_used INT UNSIGNED NOT NULL DEFAULT 0,
            cost_est DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
            message TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY created_at (created_at)
        ) {$charset};";

        dbDelta($sql);
    }
}