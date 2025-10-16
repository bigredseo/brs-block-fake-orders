# Changelog
All notable changes to **BRS Block Fake Orders** will be documented in this file.
---

## [0.1.6] - 2025-10-16
### Changed
- Merged `brs_bfo_log_table()` into the main plugin file so it’s available earlier (e.g., during install/logging).
- Reworded autoloader comment to: `Minimal classmap autoloader (not PSR-4)` for accuracy.
- Stopped defining any separate runtime version value; the plugin header remains the single source of truth.
- Updated license.txt with GPL v2 compliance information and removed v3 details.

### Removed
- Deleted `includes/common/helpers.php` and the corresponding `require_once` in the main file.

---

## [0.1.5] - 2025-10-15
### Changed
- Major internal refactor: split plugin logic into modular include files for maintainability and clarity.
  - Core classes separated for install, logging, validation, integrations, and admin functionality.
  - Main plugin file now acts as a clean bootstrap/loader only.

### Added
- New **opt-in uninstall cleanup**:
  - Added `uninstall.php` to safely remove data when enabled.
  - New **Settings** page under WooCommerce → BRS Fake Orders Settings with a checkbox to delete data on uninstall.
- Added `BRS_BFO_Settings` class to manage admin options.
- Light autoloader implemented for `includes/` classes.

### Notes
- No functional behavior changes for checkout validation or logging.
- Existing files `assets/js/brs-checkout-helper.js` and `includes/admin/log-viewer.php` remain untouched.

---

## [0.1.4] - 2025-10-14
### Added
- **Admin Log Viewer (WooCommerce → Fake Order Log):** View, search, filter, paginate, clear, and export logs to CSV directly in wp-admin.
- **Database-backed logging:** Introduced custom table `wp_brs_fo_log` (created on activation) to store log entries with fields:
  `id, created_at, level, msg, context (JSON), route, ip, ua`.
- **Security & permissions:** Admin screen gated by `manage_woocommerce` (fallback `manage_options`). All actions protected with nonces.
- **Quality-of-life:** 
  - Filters for **level**, **route**, and a general **search** across message/context/UA.
  - **Pagination** controls and **CSV export** of all entries.
  - **Clear Log** button to truncate the table.

### Changed
- **Logging pipeline:** Replaced file-based logging (`/wp-content/brs-fake-orders.log`) with database inserts via a new `log()` implementation.
- **Internal structure:** Added `/includes/admin/log-viewer.php` and submenu registration to load the admin UI.

### Notes
- Existing `.log` file is no longer used.
- Table includes indexes on `created_at`, `level`, and `route` for snappy admin queries.

---

## [0.1.3] - 2025-10-13
### Added
- **Future-proofed REST API protection:**  
  Updated detection logic to cover **all WooCommerce REST order endpoints** — not just `/wc/v3/orders`.  
  The plugin now automatically protects any versioned route matching `/wc/v1/orders`, `/wc/v2/orders`, `/wc/v3/orders`, `/wc/v4/orders`, etc.
- **Cloudflare-safe origin handling:**  
  Added a new token-first validation flow that allows requests to bypass missing `Origin`/`Referer` headers when a valid `X-BRS-TOKEN` (nonce) is present.  
  This prevents false positives when CDNs like **Cloudflare** strip these headers.  

### Changed
- **Validation order updated:** Token validation now runs **before** origin/referrer checks.  
  Legitimate checkout requests with a valid token will pass even if the origin or referer is missing.
- Replaced strict `strpos()` match with a more flexible regex `preg_match( '#/wc/v\d+/orders#', $route )`
- Updated internal helper script version to match plugin header (0.1.3).

### Filters Added
- `brs_skip_origin_checks_when_token_valid` — (bool, default true) Skip origin/referrer validation when token passes.
- `brs_require_origin_or_referer` — (bool, default true when token invalid/not required) Control strictness when no token is provided.

---

## [0.1.2] - 2025-10-13
### Fixed
- Corrected `rest_pre_dispatch` hook parameter order to `($result, $server, $request)` for full WordPress compatibility.  
- Added strict `instanceof WP_REST_Request` type checks to prevent fatal errors when WooCommerce Analytics or other background REST calls run in admin.  
- Prevented **500 Internal Server Errors** on the Plugins page and WooCommerce Admin analytics screens.  

### Improved
- Enhanced compatibility with `rest_do_request()` and WooCommerce background REST preloads.  
- Verified stability with **WooCommerce 9.9+ (HPOS)** and **WordPress 6.7**.  
- Maintained all previous security and token-verification logic.

---

## [0.1.1] - 2025-10-13
### Fixed
- Removed `WP_REST_Request` type hint that caused fatal errors when the REST class was unavailable during plugin screen rendering.  
- Added defensive `require_once` calls to ensure REST classes are loaded safely when needed.  
- Resolved critical error preventing access to the **Plugins** page and activation.

---

## [0.1.0] - 2025-10-13
### Added
- **Initial release** of the plugin.  
- Blocks suspicious WooCommerce order creation attempts across:
  - **Store API** (`/wc/store/checkout`, `/wc/store/cart`)
  - **WooCommerce REST v3** (`/wc/v3/orders` POST)
  - **PayPal Payments AJAX** (`ppc-create-order`, `ppc-approve-order`)
  - **Classic checkout process**

### Security & Validation
- Detects and blocks:
  - Requests missing a **User-Agent**.
  - Requests missing or mismatched **Origin/Referrer** headers.
  - Requests with **empty carts** or **invalid totals**.
  - Known **bot-like user agents** (curl, python, httpclient, nikto, etc.).
- Optional short-lived **frontend token header** (`X-BRS-TOKEN`, WP nonce) for maximum protection.

### Logging
- Logs all blocked attempts to `/wp-content/brs-fake-orders.log`.

### Filters
- `brs_require_frontend_token` – Enable/disable token requirement (default: `true`).  
- `brs_allow_cross_origin_checkout` – Allow trusted cross-origin flows (default: `false`).  
- `brs_bad_user_agent_patterns` – Extend/override blocked user agent list.  
- `brs_block_fake_orders_log` – Hook into block events for monitoring or custom logging.

### Frontend Helper
- Automatically injects lightweight JS (`brs-checkout-helper.js`) on Cart, Checkout, and Product pages.  
- Adds `X-BRS-TOKEN` header to all Store API, AJAX, and PayPal requests.
