# WP Sell Services — Capabilities & Roles

**Version**: 1.3.1 · **Enumerated**: 2026-08-01 (branch `1.3.1`)

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
| `wpss_manage_services` | administrator, vendor | Service create/edit REST + admin |
| `wpss_manage_orders` | administrator | Order admin screens and status changes |
| `wpss_manage_vendors` | administrator | Vendor approval / management |
| `wpss_manage_disputes` | administrator | Dispute resolution |
| `wpss_manage_settings` | administrator | Plugin settings |
| `wpss_respond_to_requests` | vendor | Submitting proposals to buyer requests |

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
