# BRS Block Fake Orders

**Contributors:** [bigredseo](https://github.com/bigredseo)  
**Tags:** woocommerce, checkout, paypal, security, spam, fraud  
**Requires at least:** WordPress 5.5  
**Tested up to:** WordPress 6.7  
**Requires PHP:** 7.4  
**Stable tag:** 0.1.5 
**License:** GPLv3  
**License URI:** [https://www.gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)

Blocks suspicious WooCommerce checkout/order creation (Store API + PayPal Payments AJAX + classic checkout) using layered checks and an optional short-lived client token header.

---

## Description

This plugin stops common fake orders that hit WooCommerce endpoints directly:

- Intercepts **Store API** routes (e.g., `/wc/store/*` for cart/checkout)
- Intercepts **WooCommerce REST v3** order creation (`/wc/v3/orders` POST)
- Intercepts **PayPal Payments** AJAX actions (`ppc-create-order`, `ppc-approve-order`)
- Hooks **classic checkout** as a safety net

### What gets blocked
The plugin evaluates requests in this order:

1) **Token validation (first)**
- If `brs_require_frontend_token` is `true` (default), a valid `X-BRS-TOKEN` (WP nonce) **must** be present.
- **If the token is valid:** origin/referrer checks are **skipped** (default; see filters below).

2) **Origin / Referrer (only when token is invalid or not required)**
- Missing **Origin/Referrer**, or
- Cross-origin **host mismatch** (unless allowed via `brs_allow_cross_origin_checkout`)
→ will be blocked.

3) **Payload sanity**
- No `line_items`/`cart`, or
- `total` ≤ 0
→ will be blocked.

4) **User-Agent checks**
- Missing **User-Agent**, or
- Known bot UAs (`curl`, `python`, `php`, `httpclient`, `nikto`, etc.)
→ will be blocked.

> **Cloudflare note:** Because the token is validated first, legitimate requests still pass even if Cloudflare strips `Origin/Referer`.

### Logging
All blocked attempts are logged to:
/wp-content/brs-fake-orders.log

### Relevant filters
- `brs_require_frontend_token` (bool, default `true`)
- `brs_skip_origin_checks_when_token_valid` (bool, default `true`)
- `brs_require_origin_or_referer` (bool, default `true` when token is invalid/not required)
- `brs_allow_cross_origin_checkout` (bool, default `false`)