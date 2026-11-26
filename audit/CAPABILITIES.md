# WP Sell Services — Capabilities & Roles

**Version**: 1.9.4 · **Enumerated**: 2026-11-26 (branch `1.9.4`, from `Core\Activator::create_roles()`)

Enumerated from source, not generated. Every entry below was verified against an
actual `current_user_can()` / `add_cap()` call — a capability that exists only in
a plan document is not listed.

---

## 1. Roles

| Role | Slug | Created by | Notes |
|---|---|---|---|
| Vendor | `wpss_vendor` | `Core\Activator` on activation | The only role the plugin creates. Buyers are ordinary WordPress users (usually `subscriber`) — the plugin never creates a "buyer" role. |

**Do not test vendor status by role or by raw meta.** Use `wpss_is_vendor( $user_id )`.
A site owner can grant the role manually, and a user can hold `wpss_vendor`
alongside other roles; the helper is the only check that accounts for both.

---

## 2. Plugin capabilities

| Capability | Granted to | Enforced at |
|---|---|---|
| `wpss_vendor` | administrator, shop_manager, vendor | Vendor marker; check vendor status with `wpss_is_vendor()` |
| `wpss_manage_services` | administrator, shop_manager, vendor | Service create REST, `wpss/create-service`, earnings and withdrawal abilities |
| `wpss_vendor_orders` | administrator, shop_manager, vendor | Vendor-side order work (`can_manage_orders` in `/me`) |
| `wpss_view_analytics` | administrator, shop_manager, vendor | Granted only; admin analytics use `manage_options` |
| `wpss_respond_to_requests` | administrator, shop_manager, vendor | `wpss/submit-proposal` |
| `wpss_manage_orders` | administrator, shop_manager | Admin Orders screen; forcing any status (`OrderService`) |
| `wpss_manage_disputes` | administrator | Admin Disputes screen (resolving needs `manage_options`) |
| `wpss_manage_settings` | administrator | Admin Settings screen |
| `wpss_manage_vendors` | administrator | Granted only; nothing checks it. Vendors screen uses `manage_options` |

Authors hold no WPSS capability (removed on activation since 1.7.1). Moderation
of services, reviews and vendors is admin-only (`manage_options`).

## 3. WordPress capabilities relied on

| Capability | Why |
|---|---|
| `manage_options` | The general admin gate, used by most admin screens and settings |
| `upload_files` | Media/attachment handling in orders and portfolios |
| `edit_post` | Per-post checks on service edit |
| `manage_categories` | Service category management |

---

## 4. What is NOT capability-gated

Ownership is not a capability. "Is this my order / service / conversation?" is
checked against the record's owner, and returns REST code `wpss_not_owner`
(403) — never a capability failure. See `docs/website/developer-guide/rest-api-overview.md`
for the full permission-code table.

Role failures are distinct: `wpss_not_vendor` (not a vendor),
`wpss_vendor_pending` (vendor awaiting approval), `wpss_not_admin`.

---

## 5. Refreshing this file

By hand. `write-manifest.mjs` must not be run against this plugin — see the
`manifest_refresh: agent-enumeration-only` note in `CLAUDE.md`.
