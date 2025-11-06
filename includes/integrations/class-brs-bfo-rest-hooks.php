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

        // --- New protection for WooCommerce REST endpoints ---
        if (strpos($route, '/wc/v') === 0) {
            // Allow admins
            if (is_user_logged_in() && current_user_can('manage_options')) {
                return $result;
            }

            $allowed_domain = get_option('brs_bfo_allowed_domain', parse_url(get_site_url(), PHP_URL_HOST));

            // Session validation
            if (get_option('brs_bfo_check_session', true) && !isset($_COOKIE['woocommerce_cart_hash'])) {
                return new WP_Error('brs_block', __('Session required for checkout.', 'brs-block-fake-orders'), ['status' => 403]);
            }

            // Origin validation
            if (get_option('brs_bfo_check_origin', true) && isset($_SERVER['HTTP_ORIGIN'])) {
                $origin = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
                if ($origin !== $allowed_domain) {
                    return new WP_Error('brs_block', __('Invalid Origin.', 'brs-block-fake-orders'), ['status' => 403]);
                }
            }

            // Referer validation
            if (get_option('brs_bfo_check_referer', true) && isset($_SERVER['HTTP_REFERER'])) {
                $referer = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
                if ($referer !== $allowed_domain) {
                    return new WP_Error('brs_block', __('Invalid Referer.', 'brs-block-fake-orders'), ['status' => 403]);
                }
            }

            // Optional: basic browser UA check
            $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
            if (!preg_match('/Mozilla|Chrome|Safari|Edge/i', $ua)) {
                return new WP_Error('brs_block', __('Non-browser client blocked.', 'brs-block-fake-orders'), ['status' => 403]);
            }
        }
                
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
