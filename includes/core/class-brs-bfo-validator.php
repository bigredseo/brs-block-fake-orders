<?php
if (!defined('ABSPATH')) exit;

class BRS_BFO_Validator {
    private $logger;

    public function __construct(BRS_BFO_Logger $logger) {
        $this->logger = $logger;
    }

    /**
     * Returns null when allowed, or WP_Error to block.
     * Mirrors the original fail_if_suspicious() logic (moved only).
     */
    public function evaluate($request = null, array $extra = []) {
        if ( is_user_logged_in() && current_user_can('manage_options') ) {
            return null;
        }

        $require_token = apply_filters('brs_require_frontend_token', true);
        $skip_origin_when_token_valid = apply_filters('brs_skip_origin_checks_when_token_valid', true);
        $require_origin_or_referer = apply_filters('brs_require_origin_or_referer', true);

        // Token first
        $token_ok = false;
        if ($require_token) {
            $header_token = isset($_SERVER['HTTP_X_BRS_TOKEN']) ? sanitize_text_field($_SERVER['HTTP_X_BRS_TOKEN']) : '';
            $token_ok = (!empty($header_token) && wp_verify_nonce($header_token, 'brs_checkout_token'));
            if (!$token_ok) {
                $this->logger->log('Blocked: invalid/missing token', $extra + ['token_prefix' => substr($header_token, 0, 10)]);
                return new WP_Error('brs_block', __('Request blocked. (Invalid token).', 'brs-block-fake-orders'), ['status' => 403]);
            }
        }

        // Headers
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? trim($_SERVER['HTTP_USER_AGENT']) : '';
        $origin  = isset($_SERVER['HTTP_ORIGIN']) ? trim($_SERVER['HTTP_ORIGIN']) : '';
        $referer = isset($_SERVER['HTTP_REFERER']) ? trim($_SERVER['HTTP_REFERER']) : '';

        // Origin/Referer checks (skippable if token OK)
        if ($require_origin_or_referer && ! ($skip_origin_when_token_valid && $token_ok)) {
            if (empty($origin) && empty($referer)) {
                $this->logger->log('Blocked: no Origin/Referer', $extra + compact('origin','referer'));
                return new WP_Error('brs_block', __('Request blocked.', 'brs-block-fake-orders'), ['status' => 403]);
            }
        }

        // Body basics for known endpoints
        if ($request instanceof WP_REST_Request) {
            $route = method_exists($request,'get_route') ? $request->get_route() : '';
            $method = $request->get_method();
            $body = (array) $request->get_json_params();

            // basic sanity checks used in the original
            if (strpos($route, '/store/checkout') !== false || preg_match('#/wc/v\d+/orders#', $route)) {
                $total = isset($body['total']) ? floatval($body['total']) : 0;
                if ($total <= 0) {
                    $this->logger->log('Blocked: invalid total', $extra + ['total' => $total, 'route'=>$route, 'method'=>$method]);
                    return new WP_Error('brs_block', __('Request blocked. (Invalid total).', 'brs-block-fake-orders'), ['status' => 403]);
                }
            }
        }

        // UA patterns
        $bad_ua_patterns = apply_filters('brs_bad_user_agent_patterns', ['python-requests','curl/','wget/','java/','php/','httpclient','nikto','fuzzer','scanner']);
        $lc_ua = strtolower($ua);
        foreach ($bad_ua_patterns as $pat) {
            if (strpos($lc_ua, $pat) !== false) {
                $this->logger->log('Blocked: bad UA pattern', $extra + compact('ua'));
                return new WP_Error('brs_block', __('Request blocked.', 'brs-block-fake-orders'), ['status' => 403]);
            }
        }

        return null;
    }
}
