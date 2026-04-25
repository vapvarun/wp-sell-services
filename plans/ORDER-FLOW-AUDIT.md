# Order Flow Audit — 2026-04-25

> **Purpose:** Track the 22 friction points found during the customer + vendor order-journey audit and the implementation plan to address them in `1.1.0-rc3`.
>
> Companion to `docs/audits/baseline-2026-04-25.md` (vendor-flow audit). Same methodology: read-only browser walkthrough + code exploration → friction list → simplification plan → step-by-step implementation with browser verification per commit.

## Summary

22 friction points across two journeys:

- **8 customer (buyer) friction points** — browse → checkout → fulfilment → review
- **11 vendor (seller) friction points** — new order → work → delivery → earnings
- **5 shared-theme overlaps** — fixing each fixes both sides at once

Branch: `feat/order-flow-stable` (off `1.1.0`)
Target release: `1.1.0-rc3` (single ZIP, all 22 fixed before Shashank QA)

## Customer (buyer) friction

| ID | Severity | Issue | File |
|---|---|---|---|
| **CB1** | 🔴 | No cart-review step — buyer commits to payment without confirming totals + add-ons + delivery side-by-side | `StandaloneCheckoutProvider.php:166-200` |
| **CB2** | 🔴 | Requirements form has no progress indicator (Q2 of 5) | `templates/order/order-view.php:528-617` |
| CB3 | 🟠 | Revision limit invisible — buyer doesn't know remaining count | `order-view.php` (no revision counter) |
| CB4 | 🟠 | No post-review confirmation email or thank-you page | `API/ReviewsController.php:~130` |
| CB5 | 🟠 | Cancellation window (24h) not labelled with countdown | `order-view.php:~380` |
| CB6 | 🟠 | Sub-orders (tip/extension/milestone) have no breadcrumb to parent | `templates/order/tip-view.php:1-30` |
| CB7 | 🟡 | Delivery timeline + messaging hard to scan in long threads | `order-view.php:750+` |
| CB8 | 🟡 | Empty orders state lacks "Browse Similar Services" CTA | `dashboard/sections/orders.php:~105` |

## Vendor (seller) friction

| ID | Severity | Issue | File |
|---|---|---|---|
| **VS1** | 🔴 | 14-day earnings clearance — vendor cash locked 2 weeks post-completion, no early-unlock | `EarningsService::get_summary():68-78` |
| **VS2** | 🔴 | Sub-order type (milestone/extension) opaque — no badge on order detail explaining payment structure | `templates/order/order-view.php` |
| **VS3** | 🔴 | Revisions unbounded — buyer can demand infinite revisions, no max-limit | `Frontend/AjaxHandlers.php` (no revision_count field) |
| VS4 | 🟠 | Requirements form silently blank if service post meta corrupted | `RequirementsService::get_service_fields()` |
| VS5 | 🟠 | Extension approval doesn't email vendor with new deadline | `ExtensionOrderService::create_extension()` |
| VS6 | 🟠 | Dispute escalation criteria unclear in vendor UI | `DisputesController` |
| VS7 | 🟡 | Cancellation request hangs forever if buyer doesn't respond | `OrderWorkflowManager` (no timeout job) |
| VS8 | 🟡 | Rate-limited emails dropped silently — vendor doesn't know buyer messaged | `EmailService::send_new_message()` |
| VS9 | 🟡 | Sub-vendor split earnings not shown on dashboard | (not implemented in 1.1.0) |
| VS10 | 🟢 | Sales dashboard hard-capped at 20 orders, no date filter | `dashboard/sections/sales.php:75` |
| VS11 | 🟢 | No granular email preferences (digest mode, quiet hours) | `EmailService::get_email_settings():125` |

## Shared themes (5 high-leverage overlaps)

| Theme | Buyer friction | Vendor friction | Combined fix |
|---|---|---|---|
| Revision count | CB3 (invisible to buyer) | VS3 (unbounded for vendor) | `revision_count` field, max 3 default, "X revisions left" badge to both sides |
| Cancellation lifecycle | CB5 (no countdown) | VS7 (hangs forever) | Visible deadline + 72h auto-resolve cron |
| Sub-order context | CB6 (no parent link from tip) | VS2 (opaque platform field) | Persistent badge on order detail + breadcrumb to parent |
| Extension confirmation | (none) | VS5 (silent deadline push) | Email both sides on extension acceptance with old + new deadline visualised |
| Requirements UX | CB2 (no progress indicator) | VS4 (silent blank form) | Progress bar + fallback "field config missing" message |

## Implementation plan — 3 sub-phases on `feat/order-flow-stable`

Per-commit cadence: read code → plan → implement → browser-verify → commit. Each commit ships ONE friction item (or one tightly-scoped group). UX + flow check is mandatory before each commit lands.

### Sub-phase 1 — UI / display polish (lowest risk, no schema or money changes)

| # | Item | Touched files |
|---|---|---|
| 1.1 | VS2 — sub-order type badge on order detail | `templates/order/order-view.php` + CSS |
| 1.2 | CB6 — sub-order parent breadcrumb (Tip → Order #1234) | `templates/order/tip-view.php` + CSS |
| 1.3 | CB8 — "Browse Similar Services" empty-state CTA | `dashboard/sections/orders.php` |
| 1.4 | VS5 — extension approval email with new deadline | `EmailService.php` + new template |
| 1.5 | VS10 — sales dashboard pagination + date filter | `dashboard/sections/sales.php` + Repository |
| 1.6 | VS11 — basic per-vendor email preferences | new admin settings page + `EmailService.php` |
| 1.7 | CB7 — conversation timeline grouping by date | `order-view.php` messaging panel |
| 1.8 | VS6 — dispute escalation explainer | `templates/dispute/*.php` |

### Sub-phase 2 — State machine + lifecycle (medium risk, additive schema)

| # | Item | Touched files |
|---|---|---|
| 2.1 | CB3+VS3 — `revision_count` column + max 3 default + visible badge both sides | DB migration + `OrderService` + `order-view.php` |
| 2.2 | CB5+VS7 — cancellation deadline countdown + 72h auto-resolve cron | `OrderWorkflowManager` + `order-view.php` + Action Scheduler |
| 2.3 | CB2+VS4 — requirements progress + "field config missing" fallback | `order-view.php:528-617` + `RequirementsService` |
| 2.4 | CB4 — post-review confirmation email + thank-you redirect | `API/ReviewsController.php` + new email template |
| 2.5 | VS8 — in-app notification when an email is rate-limit-dropped | `EmailService.php` + `NotificationService` |

### Sub-phase 3 — Money + checkout (highest risk, settings-flagged)

Each item ships with an admin setting that defaults to current behaviour so existing sites are unaffected.

| # | Item | Touched files |
|---|---|---|
| 3.1 | CB1 — order-review step before payment commit | `StandaloneCheckoutProvider.php` + new template |
| 3.2 | VS1 — earnings clearance days configurable (default 14, sites can lower) | `EarningsService.php` + admin settings |
| 3.3 | VS9 — sub-vendor split visibility on earnings dashboard (display only) | `dashboard/sections/earnings.php` + helper |

### After sub-phase 3

- Update `docs/qa/1.1.0-qa-checklist.md` with new §20 covering order flow simplification (counterpart to §19 from the vendor refactor)
- Re-run §13 SQL money-integrity queries — must all return healthy
- Manual buyer→vendor end-to-end test: signup → order → requirements → delivery → review → tip
- Rebuild ZIPs as `1.1.0-rc3`
- Notify Shashank in same Slack thread

## Compatibility guarantees (the "no break" contract)

| Surface | Promise |
|---|---|
| Order state machine | NEW statuses may be added; none renamed or removed |
| `wpss_orders` schema | New columns nullable + defaults; no column drops |
| REST API | All `/wpss/v1/*` endpoint signatures + payload shapes preserved |
| AJAX endpoints | All `wpss_*` action names + nonces preserved |
| Hook signatures | All `do_action`/`apply_filters` calls keep their args |
| Templates | No template paths renamed (theme overrides keep working) |
| Existing orders | Render normally; new fields display as N/A or default |
| Pro plugin | All hooks Pro depends on remain unchanged |
| i18n | No removed strings; new strings added with text-domain |
| Money flow | All clearance + commission + payout logic preserved by default; changes opt-in via admin settings |

## Out of scope for `feat/order-flow-stable`

- F6 — onboarding tour timing (kept for 1.1.1 if vendors complain)
- 1.2.0 commercial features (per `plans/1.2.0-COMMERCIAL-AUDIT.md`)
- 1.2.0 service types (per `plans/1.2.0-SERVICE-TYPES.md`)
- Sub-vendor architecture rewrite (VS9 only adds DISPLAY visibility, not split logic)

These ship in subsequent releases under their own branches.
