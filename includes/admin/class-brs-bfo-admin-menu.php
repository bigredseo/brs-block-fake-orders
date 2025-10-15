<?php
if (!defined('ABSPATH')) exit;

class BRS_BFO_Admin_Menu {
    public function register() {
        add_action('admin_menu', [$this, 'menu']);
    }

    public function menu() {
        add_submenu_page(
            'woocommerce',
            __('Fake Order Log','brs-block-fake-orders'),
            __('Fake Order Log','brs-block-fake-orders'),
            'manage_woocommerce',
            'brs-fake-orders-log',
            function () {
                require_once BRS_BFO_DIR . 'includes/admin/log-viewer.php';
                brs_bfo_render_log_page();
            }
        );
    }
}
