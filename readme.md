# BRS Block Fake Orders

**Version:** 0.1.7.1 
**Author:** Big Red SEO  
**Requires WordPress:** 6.3+  
**Tested up to:** 6.7  
**Requires PHP:** 7.4+  
**License:** GPLv3  
**Tags:** WooCommerce, checkout, fraud prevention, fake orders, security

Blocks suspicious WooCommerce checkout and order creation (Store API, PayPal Payments AJAX, and classic checkout) using layered validation and an optional short-lived client token header.

---

## Description

### What it does
This plugin prevents fake or automated WooCommerce orders that hit checkout endpoints directly — bypassing the normal front-end flow.

It adds layered validation to these systems:

- **WooCommerce Store API** routes (e.g. `/wc/store/*` for cart/checkout)
- **WooCommerce REST API v3** order creation (`/wc/v3/orders` POST)
- **PayPal Payments AJAX** actions (`ppc-create-order`, `ppc-approve-order`)
- **Classic Checkout** hook (`woocommerce_checkout_process`) as a safety net

---

## How requests are validated

Each request is evaluated in order:

### Token Validation (first)
If `brs_require_frontend_token` is `true` (default), a valid **`X-BRS-TOKEN`** header (WordPress nonce) must be present.

- If the token is valid, origin/referrer checks are skipped (default).
- If the token is missing or invalid, the request is blocked immediately.

### Origin / Referrer Checks  
Used only when the token is missing or invalid.

- Missing **Origin/Referer**, or  
- Cross-origin host mismatch (unless allowed by filter)  
→ Request is blocked.

> **Cloudflare note:** Legitimate Store API requests still pass even if Cloudflare strips `Origin/Referer`, because the token validates first.

### Payload Sanity
For checkout and order routes:
- Missing `line_items` or `cart`
- `total` less than or equal to `0`  
→ Request is blocked.

### User-Agent Checks
Blocks requests with:
- Empty or missing `User-Agent`
- Known bad UA patterns:
  - `curl/`
  - `python-requests`
  - `php/`
  - `httpclient`
  - `nikto`
  - `scanner`
  - etc.

---

## Logging

All blocked attempts are logged to the database table: `{prefix}_brs_fo_log`

View logs in the admin UI:  
**WooCommerce → Fake Order Log**

Optional: enable file logging (for debugging) by adding a filter:
`add_filter('brs_bfo_enable_file_log', '__return_true');`

This writes to:
/wp-content/brs-fake-orders.log

## Filters & Options

| Filter | Type | Default | Description |
|---------|------|----------|-------------|
| `brs_require_frontend_token` | bool | `true` | Require a valid token header. |
| `brs_skip_origin_checks_when_token_valid` | bool | `true` | Skip origin/referer checks if token passes. |
| `brs_require_origin_or_referer` | bool | `true` | Require Origin or Referer when token is missing. |
| `brs_allow_cross_origin_checkout` | bool | `false` | Allow cross-origin requests for checkout. |
| `brs_bad_user_agent_patterns` | array | `['python-requests', 'curl/', 'wget/', 'php/', 'httpclient', 'nikto', 'fuzzer', 'scanner']` | Additional UA block patterns. |
| `brs_block_fake_orders_log` | action | — | Fires when a request is logged. |
| `brs_bfo_enable_file_log` | bool | `false` | Enable flat file logging to `/wp-content/brs-fake-orders.log`. |

## Installation

1. Upload the folder `brs-block-fake-orders` to `/wp-content/plugins/`.
2. Activate the plugin in **Plugins → Installed Plugins**.
3. The system runs automatically — no setup required.
4. View logs under **WooCommerce → Fake Order Log**.

## Uninstall

By default, uninstalling the plugin does not remove stored logs.

To opt in to full cleanup:

1. Go to **WooCommerce → BRS Fake Orders Settings**.
2. Enable “Delete data on uninstall.”
3. Delete the plugin from **Plugins → Delete**.

This will remove:
- Database table `{prefix}_brs_fo_log`
- Related plugin options
- Optional file log (`brs-fake-orders.log`)

## Developer Notes

- Admins and shop managers are exempt from blocking checks.
- Activation automatically creates or updates the `brs_fo_log` table.
- All filters and hooks from prior versions remain backward compatible.
- Frontend JS (`brs-checkout-helper.js`) automatically attaches the `X-BRS-TOKEN` header to requests.
