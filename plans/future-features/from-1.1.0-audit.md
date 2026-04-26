# Future Feature Ideas — parked from the 1.1.0 completeness audit

> **Date:** 2026-04-25
> **Source:** `plans/1.1.0-COMPLETENESS-AUDIT.md` walk (25 features × 5 personas)
>
> No 🟢 (entirely-missing-feature) cells were found in the matrix — every feature concept described
> in the audit exists in code to some degree. This file captures **UX improvements and new capabilities**
> that surfaced during the walk but are out of scope for 1.1.0.

---

## Wishlist items by persona

### Admin

| ID | Idea | Source observation |
|---|---|---|
| A1 | **Dashboard: "Action Items" strip** — split into Daily (Open Disputes count, Pending Withdrawals count + sum, Pending Vendor Approvals count) vs Marketplace Health (rolling totals). Tiles dim at 0. | Dashboard currently shows 4 health KPIs only; already captured in `1.1.0-ADMIN-OVERWHELM-AUDIT.md` finding #4 (fix for 1.1.0). |
| A2 | **Withdrawals bulk actions** — checkbox column + Approve / Reject / Mark as paid bulk dropdown. | Already in `1.1.0-ADMIN-OVERWHELM-AUDIT.md` finding #2 (fix for 1.1.0). |
| A3 | **Vendors bulk actions** — Approve / Suspend / Reactivate in batch. | Already in `1.1.0-ADMIN-OVERWHELM-AUDIT.md` finding #3 (fix for 1.1.0). |
| A4 | **Orders: missing lifecycle status tabs** — `pending_requirements`, `requirements_submitted`, `pending_approval`, `disputed`. | Already in `1.1.0-ADMIN-OVERWHELM-AUDIT.md` finding #7 (polish, may slip to 1.1.x). |
| A5 | **Withdrawals: date-range filter + amount sort** — high-value-first sorting on a busy payout day. | Already in `1.1.0-ADMIN-OVERWHELM-AUDIT.md` finding #5 (polish). |
| A6 | **Vendors: joined-date filter** — filter vendors by signup cohort for approval-queue management. | Already in `1.1.0-ADMIN-OVERWHELM-AUDIT.md` finding #6 (polish). |

### Vendor

| ID | Idea | Source observation |
|---|---|---|
| V1 | **Earnings: per-service revenue breakdown** — chart or table showing which service earns most. Currently rolled up to total wallet. | Analytics tab exists but chart granularity is limited. |
| V2 | **Portfolio: drag-to-reorder on mobile** — drag handle is touch-unfriendly at narrow viewport. | Observed on 390px emulation during walk. |

### Buyer

| ID | Idea | Source observation |
|---|---|---|
| B1 | **Saved searches / saved filters** — buyer refines category + price range frequently, no way to persist. | Marketplace listing has no "Save this filter" affordance. |
| B2 | **Order dashboard: empty-state onboarding** — fresh buyer with no orders sees a blank table; could show a "Browse services" CTA with featured service cards. | `buyer (fresh)` persona walk. Already noted as polish in admin-overwhelm audit; same gap exists on buyer side. |

---

## Already being fixed in 1.1.0 (not parked)

The following were found in this audit but are **actively on the `feat/1.1.0-completeness` fix list**, not parked:

| Finding | Fix |
|---|---|
| `$is_buyer` undefined in `order-view.php` milestone block (F23 🔴) | Replace 7 occurrences with `$is_customer` |
| Package DB/meta split → $0.00 checkout (F7/F13 🔴) | Sync `ServiceManager::save_packages()` to also write `_wpss_packages` post meta |
| Add-on meta key mismatch `_wpss_addons` vs `_wpss_extras` (F8 🟠) | Re-seed fixture data or add fallback read in `SingleServiceView.php` |
| Wallet block visible to unauthenticated visitors on vendor profile (F3 🟠) | Wrap wallet section with `is_user_logged_in()` check in `templates/vendor/profile.php` |
