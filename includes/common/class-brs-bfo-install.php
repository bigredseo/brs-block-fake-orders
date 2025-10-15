<?php
if (!defined('ABSPATH')) exit;

class BRS_BFO_Install {
    public function run() {
        global $wpdb;
        $table = brs_bfo_log_table();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            msg TEXT NOT NULL,
            context LONGTEXT NULL,
            route VARCHAR(255) NULL,
            ip VARCHAR(64) NULL,
            ua TEXT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY level (level),
            KEY route (route)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
