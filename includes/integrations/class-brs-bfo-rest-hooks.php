<?php
if (!defined('ABSPATH')) exit;

class BRS_BFO_Rest_Hooks {
    private $validator;
    private $logger;

    public function __construct(BRS_BFO_Validator $validator, BRS_BFO_Logger $logger) {
        $this->validator = $validator;
        $this->logger = $logger;
    }

    public function register() {
        add_filter('rest_pre_dispatch', [$this, 'maybe_block_rest_request'], 10, 3);
    }

    public function maybe_block_rest_request($result, $server, $request) {
        if (! (isset($request) && class_exists('WP_REST_Request') && $request instanceof WP_REST_Request)) {
            return $result;
        }
        $route = method_exists($request, 'get_route') ? $request->get_route() : '';
        $is_store_checkout   = ( is_string($route) && (strpos($route, '/store/checkout') !== false || strpos($route, '/cart') !== false) );
        $is_wc_orders_create = ( preg_match('#/wc/v\d+/orders#', $route) && $request->get_method() === 'POST' );

        if ( $is_store_checkout || $is_wc_orders_create ) {
            $block = $this->validator->evaluate($request, ['route' => $route, 'integration'=>'rest']);
            if (is_wp_error($block)) {
                return $block;
            }
        }
        return $result;
    }
}
