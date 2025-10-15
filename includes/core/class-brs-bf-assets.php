<?php
if (!defined('ABSPATH')) exit;

class BRS_BFO_Assets {
    public function register() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_helper_js']);
    }

    public function enqueue_helper_js() {
        // same behavior as original
        wp_enqueue_script(
            'brs-bfo-helper',
            BRS_BFO_URL . 'assets/js/brs-checkout-helper.js',
            ['wp-hooks'],
            BRS_BFO_VERSION,
            true
        );
        // Localize/nonce if needed
        wp_add_inline_script('brs-bfo-helper', 'window.BRS_BFO = { nonce: "'. esc_js( wp_create_nonce('brs_checkout_token') ) .'" };', 'before');
    }
}
