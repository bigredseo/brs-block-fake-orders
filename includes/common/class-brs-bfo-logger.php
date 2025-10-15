<?php
if (!defined('ABSPATH')) exit;

class BRS_BFO_Logger {
    private $file_path;

    public function __construct() {
        // Keep optional log file behind a filter (default: disabled)
        $this->file_path = trailingslashit(WP_CONTENT_DIR).'brs-fake-orders.log';
    }

    public function log($msg, array $context = [], $level = 'info') {
        global $wpdb;
        $table = brs_bfo_log_table();

        $route = $_SERVER['REQUEST_URI'] ?? '';
        $ip    = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua    = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $wpdb->insert(
            $table,
            [
                'created_at' => current_time('mysql'),
                'level'      => $level,
                'msg'        => (string) $msg,
                'context'    => !empty($context) ? wp_json_encode($context) : null,
                'route'      => substr((string)$route, 0, 255),
                'ip'         => substr((string)$ip, 0, 64),
                'ua'         => (string)$ua,
            ],
            ['%s','%s','%s','%s','%s','%s','%s']
        );

        // Optional file logging for debug scenarios
        if ( apply_filters('brs_bfo_enable_file_log', false) ) {
            @file_put_contents(
                $this->file_path,
                sprintf("[%s] %s: %s %s\n",
                    date('c'),
                    strtoupper($level),
                    $msg,
                    $context ? wp_json_encode($context) : ''
                ),
                FILE_APPEND
            );
        }

        /**
         * Allow external listeners to consume log events.
         */
        do_action('brs_block_fake_orders_log', $msg, $context);
    }
}
