=== BRS Block Fake Orders ===
Contributors: bigredseo
Tags: woocommerce, checkout, paypal, security, spam, fraud
Requires at least: 5.5
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Blocks suspicious WooCommerce checkout/order creation (Store API + PayPal Payments AJAX + classic checkout) using layered checks and an optional short-lived client token header.

== Description ==
This plugin stops common fake orders that hit WooCommerce endpoints directly:

* Intercepts **Store API** routes (e.g., `/wc/store/*` for cart/checkout)
* Intercepts **WooCommerce REST v3** order creation (`/wc/v3/orders` POST)
* Intercepts **PayPal Payments** AJAX actions (`ppc-create-order`, `ppc-approve-order`)
* Hooks **classic checkout** as a safety net

**What gets blocked?**  
Requests missing a User-Agent, missing Origin/Referrer, or coming from a different host (unless allowed) are blocked. Payloads without line items or with non-positive totals are blocked. Bot-like UAs (curl/python/php/httpclient…) are blocked. By default, a short-lived frontend token header `X-BRS-TOKEN` (WP nonce) is required — this is the strongest protection.

**Logging**  
Blocked attempts are logged to `/wp-content/brs-fake-orders.log` for review.

**Filters**  
- `brs_require_frontend_token` (bool, default `true`) — turn off if needed.  
- `brs_allow_cross_origin_checkout` (bool, default `false`) — allow trusted cross-origin flows.  
- `brs_bad_user_agent_patterns` (array) — extend UA patterns.  
- `brs_block_fake_orders_log` (action) — observe block events.

== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/brs-block-fake-orders/` and activate, or drop into `/wp-content/mu-plugins/`.
2. No settings page — it works out of the box.
3. The plugin automatically injects a small JS helper that adds `X-BRS-TOKEN` to outgoing requests on cart/checkout/product pages. If your theme prevents enqueues on these pages, enqueue manually.

== FAQ ==
= Will this break PayPal? =  
No. Legitimate on-site flows send the token and same-origin headers automatically via the provided JS helper.

= My checkout is embedded in an external host/iframe. =  
Add `add_filter('brs_allow_cross_origin_checkout', '__return_true');` in a small custom plugin to allow cross-origin.

= I’m getting false positives. =  
Temporarily disable the token requirement:  
`add_filter('brs_require_frontend_token', '__return_false');`

== Changelog ==
= 0.1.2 =
* Fix: Correct `rest_pre_dispatch` parameter order and add type guards to prevent admin 500s.

= 0.1.1 =
* Fix: Prevent fatal on Plugins screen by removing WP_REST_Request type hint and loading class defensively.

= 0.1.0 =
* Initial release: REST/Store API/PayPal AJAX interception, layered checks, frontend token helper, and logging.

== Upgrade Notice ==
0.1.2 – Fixes plugin screen 500 error.
