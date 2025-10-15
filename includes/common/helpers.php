<?php
if (!defined('ABSPATH')) exit;

// Version constant if you want to read it elsewhere.
if (!defined('BRS_BFO_VERSION')) define('BRS_BFO_VERSION', '0.1.4');

// Table name helper (used across classes)
function brs_bfo_log_table() {
    global $wpdb;
    return $wpdb->prefix . 'brs_fo_log';
}
