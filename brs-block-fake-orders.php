<?php
/*
Plugin Name: BRS Block Fake Orders
Description: Blocks suspicious checkout/order creation requests (Store API, WooCommerce REST, and PayPal Payments AJAX) with layered checks + optional required client token header.
Version: 0.1.2
Author: Big Red SEO (Conor Treacy)
License: GPLv3
Text Domain: brs-block-fake-orders
*/

defined('ABSPATH') || exit;

if ( ! class_exists('BRS_Block_Fake_Orders') ) :

final class BRS_Block_Fake_Orders {
    private $log_file;

    public function __construct() {
        $this->log_file = trailingslashit( WP_CONTENT_DIR ) . 'brs-fake-orders.log';

        // REST interception (Store API and Woo REST v3 Orders POST)
        add_filter( 'rest_pre_dispatch', [ $this, 'maybe_block_rest_request' ], 10, 3 );

        // PayPal Payments AJAX routes (WooCommerce PayPal Payments)
        add_action( 'wp_ajax_nopriv_ppc-create-order', [ $this, 'block_ajax_paypal' ] );
        add_action( 'wp_ajax_ppc-create-order', [ $this, 'block_ajax_paypal' ] );
        add_action( 'wp_ajax_nopriv_ppc-approve-order', [ $this, 'block_ajax_paypal' ] );
        add_action( 'wp_ajax_ppc-approve-order', [ $this, 'block_ajax_paypal' ] );

        // Classic checkout fallback
        add_action( 'woocommerce_checkout_process', [ $this, 'maybe_block_classic_checkout' ] );

        // Frontend helper to inject token
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_helper_js' ] );
    }

    private function log( $msg, $context = [] ) {
        $time = date( 'Y-m-d H:i:s' );
        $entry = sprintf( "[%s] %s %s\n", $time, $msg, ! empty( $context ) ? wp_json_encode( $context ) : '' );
        error_log( $entry, 3, $this->log_file );
        do_action( 'brs_block_fake_orders_log', $msg, $context );
    }

    private function fail_if_suspicious( $request = null, $extra = [] ) {
        // Allow admin-cap users
        if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
            return null;
        }

        $ua     = isset($_SERVER['HTTP_USER_AGENT']) ? trim($_SERVER['HTTP_USER_AGENT']) : '';
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : ( isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '' );

        // 1) UA required
        if ( empty( $ua ) ) {
            $this->log( 'Blocked: missing UA', $extra + compact('ua','origin') );
            return new WP_Error( 'brs_block', __( 'Request blocked.', 'brs-block-fake-orders' ), [ 'status' => 403 ] );
        }

        // 2) Require origin/referrer
        if ( empty( $origin ) ) {
            $this->log( 'Blocked: missing origin/referrer', $extra + compact('ua') );
            return new WP_Error( 'brs_block', __( 'Request blocked.', 'brs-block-fake-orders' ), [ 'status' => 403 ] );
        }

        // 3) Same-host origin unless allowed
        $site_host   = parse_url( home_url(), PHP_URL_HOST );
        $origin_host = parse_url( $origin, PHP_URL_HOST );
        $allow_cross = apply_filters( 'brs_allow_cross_origin_checkout', false, $origin_host, $site_host );
        if ( $origin_host && strcasecmp( $origin_host, $site_host ) !== 0 && ! $allow_cross ) {
            $this->log( 'Blocked: origin mismatch', $extra + compact('origin_host','site_host') );
            return new WP_Error( 'brs_block', __( 'Request blocked.', 'brs-block-fake-orders' ), [ 'status' => 403 ] );
        }

        // 4) Require a short-lived frontend token by default
        $require_token = apply_filters( 'brs_require_frontend_token', true );
        if ( $require_token ) {
            $header_token = isset($_SERVER['HTTP_X_BRS_TOKEN']) ? sanitize_text_field($_SERVER['HTTP_X_BRS_TOKEN']) : '';
            if ( empty( $header_token ) || ! wp_verify_nonce( $header_token, 'brs_checkout_token' ) ) {
                $this->log( 'Blocked: invalid/missing token', $extra + [ 'token_prefix' => substr($header_token, 0, 10) ] );
                return new WP_Error( 'brs_block', __( 'Request blocked (invalid token).', 'brs-block-fake-orders' ), [ 'status' => 403 ] );
            }
        }

        // 5) Payload sanity: require line items & positive total where possible
        if ( $request && class_exists('WP_REST_Request') && $request instanceof WP_REST_Request ) {
            $body = $request->get_body_params();
            $items = isset( $body['line_items'] ) ? $body['line_items'] : ( isset( $body['cart'] ) ? $body['cart'] : null );
            if ( empty( $items ) ) {
                $this->log( 'Blocked: empty items', $extra + [ 'keys' => array_keys( (array) $body ) ] );
                return new WP_Error( 'brs_block', __( 'Request blocked (empty cart).', 'brs-block-fake-orders' ), [ 'status' => 403 ] );
            }
            $total = isset( $body['total'] ) ? floatval( $body['total'] ) : 0;
            if ( $total <= 0 ) {
                $this->log( 'Blocked: invalid total', $extra + [ 'total' => $total ] );
                return new WP_Error( 'brs_block', __( 'Request blocked (invalid total).', 'brs-block-fake-orders' ), [ 'status' => 403 ] );
            }
        }

        // 6) Simple bad UA patterns
        $bad_ua_patterns = apply_filters( 'brs_bad_user_agent_patterns', [ 'curl', 'python-requests', 'php/', 'httpclient', 'nikto', 'fuzzer', 'scanner' ] );
        $lc_ua = strtolower( $ua );
        foreach ( $bad_ua_patterns as $pat ) {
            if ( strpos( $lc_ua, $pat ) !== false ) {
                $this->log( 'Blocked: bad UA pattern', $extra + compact('ua') );
                return new WP_Error( 'brs_block', __( 'Request blocked.', 'brs-block-fake-orders' ), [ 'status' => 403 ] );
            }
        }

        return null; // allow
    }

    public function maybe_block_rest_request( $result, $server, $request ) {
        // Ensure proper types; WordPress calls with ($result, WP_REST_Server $server, WP_REST_Request $request)
        if ( ! ( isset($request) && class_exists('WP_REST_Request') && ( $request instanceof WP_REST_Request ) ) ) {
            return $result;
        }
        $route = method_exists($request, 'get_route') ? $request->get_route() : '';
        $is_store_checkout   = ( is_string($route) && strpos( $route, '/wc/store' ) !== false && ( strpos( $route, '/checkout' ) !== false || strpos( $route, '/cart' ) !== false ) );
        $is_wc_orders_create = ( is_string($route) && strpos( $route, '/wc/v3/orders' ) !== false && $request->get_method() === 'POST' );

        if ( $is_store_checkout || $is_wc_orders_create ) {
            $blocked = $this->fail_if_suspicious( $request, [ 'route' => $route, 'method' => $request->get_method() ] );
            if ( is_wp_error( $blocked ) ) {
                return rest_ensure_response( $blocked );
            }
        }
        return $result;
    }

    public function block_ajax_paypal() {
        // Defensive: ensure WP_REST_Request is loadable
        if ( ! class_exists('WP_REST_Request') ) { @require_once ABSPATH . 'wp-includes/rest-api/class-wp-rest-request.php'; }
        $request = class_exists('WP_REST_Request') ? new WP_REST_Request( $_SERVER['REQUEST_METHOD'] ) : null;
        if ( $request ) {
            foreach ( $_REQUEST as $k => $v ) {
                $request->set_param( $k, $v );
            }
        }
        $blocked = $this->fail_if_suspicious( $request, [ 'ajax' => current_action() ] );
        if ( is_wp_error( $blocked ) ) {
            wp_send_json_error( [ 'message' => $blocked->get_error_message() ], 403 );
        }
        // otherwise let PayPal plugin continue
    }

    public function maybe_block_classic_checkout() {
        // Defensive: ensure WP_REST_Request is loadable
        if ( ! class_exists('WP_REST_Request') ) { @require_once ABSPATH . 'wp-includes/rest-api/class-wp-rest-request.php'; }
        $req = class_exists('WP_REST_Request') ? new WP_REST_Request( 'POST' ) : null;
        if ( $req ) {
            $req->set_body_params( $_POST );
        }
        $blocked = $this->fail_if_suspicious( $req, [ 'hook' => 'woocommerce_checkout_process' ] );
        if ( is_wp_error( $blocked ) ) {
            wc_add_notice( $blocked->get_error_message(), 'error' );
        }
    }

    public function enqueue_helper_js() {
        if ( ! ( is_checkout() || is_cart() || is_product() ) ) return;
        $ver = '0.1.2';
        wp_enqueue_script(
            'brs-checkout-helper',
            plugins_url( 'assets/js/brs-checkout-helper.js', __FILE__ ),
            array(),
            $ver,
            true
        );
        wp_localize_script( 'brs-checkout-helper', 'BRSCheckout', array(
            'token' => wp_create_nonce( 'brs_checkout_token' ),
        ) );
    }
}

// Bootstrap
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'WooCommerce' ) ) {
        new BRS_Block_Fake_Orders();
    }
} );

endif; // class guard
