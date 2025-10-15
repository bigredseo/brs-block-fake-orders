<?php
// uninstall.php
if ( ! defined('WP_UNINSTALL_PLUGIN') ) {
    exit;
}

// We can’t rely on plugin constants here, so replicate the table name logic.
global $wpdb;
$table = $wpdb->prefix . 'brs_fo_log';

// Read the opt-in flag (don’t create defaults here).
$delete = get_option('brs_bfo_delete_data_on_uninstall', false);

if ( $delete ) {
    // Drop table if it exists
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );

    // Clean our options (including the flag itself)
    delete_option('brs_bfo_delete_data_on_uninstall');

    // Optional: remove old file logs if the site had the file logger enabled
    $file = trailingslashit(WP_CONTENT_DIR) . 'brs-fake-orders.log';
    if ( file_exists( $file ) ) {
        @unlink( $file );
    }
}
