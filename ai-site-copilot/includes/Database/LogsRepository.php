<?php
namespace AISC\Database;

if (!defined('ABSPATH')) exit;

class LogsRepository {

    public function insert(array $row): int {
        global $wpdb;
        $table = LogsTable::table_name();

        $defaults = [
            'user_id' => get_current_user_id(),
            'action' => 'unknown',
            'status' => 'success',
            'tokens_used' => 0,
            'cost_est' => 0,
            'message' => '',
        ];
        $data = array_merge($defaults, $row);

        $wpdb->insert($table, [
            'user_id' => (int) $data['user_id'],
            'action' => sanitize_text_field($data['action']),
            'status' => sanitize_text_field($data['status']),
            'tokens_used' => (int) $data['tokens_used'],
            'cost_est' => (float) $data['cost_est'],
            'message' => wp_kses_post($data['message']),
        ]);

        return (int) $wpdb->insert_id;
    }

    public function latest(int $limit = 10): array {
        global $wpdb;
        $table = LogsTable::table_name();
        $limit = max(1, min(50, $limit));
        return (array) $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}", ARRAY_A);
    }
}