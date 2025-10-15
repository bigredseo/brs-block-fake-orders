<?php
if (!defined('ABSPATH')) exit;

class BRS_BFO_PayPal_Hooks {
    private $validator;
    private $logger;

    public function __construct(BRS_BFO_Validator $validator, BRS_BFO_Logger $logger) {
        $this->validator = $validator;
        $this->logger = $logger;
    }

    public function register() {
        add_action('wp_ajax_nopriv_ppc-create-order',  [$this, 'guard']);
        add_action('wp_ajax_ppc-create-order',         [$this, 'guard']);
        add_action('wp_ajax_nopriv_ppc-approve-order', [$this, 'guard']);
        add_action('wp_ajax_ppc-approve-order',        [$this, 'guard']);
    }

    public function guard() {
        $block = $this->validator->evaluate(null, ['route'=>'ajax:paypal']);
        if (is_wp_error($block)) {
            wp_send_json_error(['message'=> $block->get_error_message()], $block->get_error_data()['status'] ?? 403);
        }
        // allow normal handler to continue
    }
}
