<?php
if (!defined('ABSPATH')) exit;

class BRS_BFO_Classic_Checkout {
    private $validator;
    private $logger;

    public function __construct(BRS_BFO_Validator $validator, BRS_BFO_Logger $logger) {
        $this->validator = $validator;
        $this->logger = $logger;
    }

    public function register() {
        add_action('woocommerce_checkout_process', [$this, 'maybe_block']);
    }

    public function maybe_block() {
        $block = $this->validator->evaluate(null, ['route'=>'wc:checkout']);
        if (is_wp_error($block)) {
            wc_add_notice(__('We could not process your request.', 'brs-block-fake-orders'), 'error');
            // Also die early to be safe
            wp_die( esc_html__('Request blocked.', 'brs-block-fake-orders'), 403 );
        }
    }
}
