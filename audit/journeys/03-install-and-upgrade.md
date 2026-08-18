# Journey 03 — Install, upgrade and first run

**Role:** admin
**Status:** passing as of commit `c341923`

Guards cards 10208003415, 10208047602, and the activation fatal found the
same day.

## Why this one matters

Run it **with WooCommerce active**, because that is when the page slugs
collide. `/cart/` and `/checkout/` already belong to Woo, and this is exactly
the configuration that produced the `/cart-2/` … `/cart-16/` trail.

## A. Fresh activation

### 1. Activate the plugin
On a site with WooCommerce active and no WPSS pages.

**Expect**
- Activation **succeeds**. If the plugin lands back on "inactive" with no error
  in the UI, check the PHP log for a fatal — the activation hook loads the
  Composer autoloader but **not** `src/functions.php`, so any global helper
  called from `Activator` dies there. This exact fatal shipped and was silent.
- Zero fatals in the log.

### 2. Pages created
Pages → All Pages.

**Expect** six WPSS pages, each carrying its shortcode:

| Key | Title | Slug |
|---|---|---|
| `services_page` | Services | `services` |
| `vendors_page` | Vendors | `vendors` |
| `dashboard` | Dashboard | `dashboard` |
| `become_vendor` | Become a Vendor | `become-vendor` |
| `cart` | Service Cart | `service-cart` |
| `checkout` | Service Checkout | `service-checkout` |

**Expect** cart is `service-cart`, **not** `cart-N`. Woo keeps `/cart/`.

### 3. Nothing duplicated
Re-activate two or three more times.

**Expect** no new pages. A published page already carrying the shortcode is
adopted, never duplicated.

### 4. Settings → Pages
**Expect**
- All six mappings populated, no empty dropdown.
- The **Create** button's confirm dialog names the page you will actually get
  (registry title), not the field label.
- Hit **Save** — every key survives. A key silently disappearing means it is
  missing from `sanitize_pages_settings()`, which has bitten twice.

### 5. Setup wizard
**Expect** labels read "Service Checkout" and "Become a Vendor" — matching the
installer. "Checkout" / "Vendor Registration" means a label list has drifted
again.

### 6. Preflight
```
wp wpss preflight
```
**Expect** all six pages PASS, including `vendors_page`.
(Ignore the debug.log entry count on a shared sandbox — it is dominated by
third-party plugin deprecations.)

### 7. Cart link
**Expect** with the **Site cart link** setting off (default), the theme header
cart still points at Woo's `/cart/`. Turning it on points it at
`/service-cart/`. Off is correct for a site that genuinely sells Woo products.

## B. Upgrade path

Simulate a site coming from an older version:

```
wp option update wpss_version 1.5.0
# then load any front-end page
```

**Expect**
- Missing pages are created on the upgrade path too, not only on activation —
  `Activator::create_pages()` runs from `Plugin` on `init:5`.
- `wpss_version` ends at the current version.
- Schema migrates: `DB_VERSION` is **1.6.1**. If new columns do not appear,
  `install()` short-circuited on `needs_update()` — the schema version must be
  bumped for any schema change, even inside an already-numbered release.
- `wpss_dispute_messages` has 9 columns (`message_type`, `description` added).

## C. Uninstall

**Known gap, not yet verified:** uninstall may only clear WP-Cron entries and
leave Action Scheduler actions behind. Confirm before release.
