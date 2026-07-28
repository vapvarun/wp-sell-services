# WP Sell Services — Master Remediation Plan (Journey Audit 2026-07-19)

Consolidates three multi-agent audit passes, every P1/P2 adversarially source-verified:
- Wave 1 — user+admin journeys / API+data contract: **10 P1 + 27 P2** — `audit/journey-audit-2026-07-19.md`
- Wave 2 — usability + template flow: **2 P1 + 13 P2** — `audit/usability-audit-2026-07-19.md`
- Wave 3 — gap-fill (disputes, admin screens, discovery): **2 P1 + 9 P2** — `audit/gapfill-audit-2026-07-19.md`

**Combined confirmed: 14 P1 + 49 P2** (+ ~40 P3/unverified backlog).

> **Static-only.** All findings are source-traced; NONE runtime-repro'd. Still owed: one
> browser + responsive (≤480px) + dark-mode pass, and Pro-repo check of whether the
> `wpss_dashboard_sections` filter masks the disputes P1 on Pro sites.

---

## How to implement (methodology — every task)

1. **Replicate first.** Findings are source-traced *leads*, never runtime-repro'd. Reproduce
   on `wp-sell-services.local` with real seeded data (role/demo vendors, real orders,
   Stripe/PayPal test mode) BEFORE coding. A finding that won't replicate is a result — record it.
2. **Fix onto a shared seam, not per call-site.** ~⅓ are duplicated-path bugs (one entry
   point fixed, its twin not). Consolidate each cluster onto ONE service/helper.
3. **Verify per finding** — browser (frontend + wp-admin + 390px), REST/curl, DB — same
   conditions as the repro. WPCS 0-errors + PHPStan-clean are necessary, not sufficient.
4. **Release grouping:** **1.2.2** (reviewer-name, ready now, already pushed). **1.3.0** =
   WP-A…WP-H, Free+Pro lockstep, Docker-gated. WP-I as a follow-up hardening pass.
5. **Order:** WP-A → WP-B → WP-C (parallel) → WP-D → WP-E → WP-F → WP-G → WP-H → WP-I.

---

## WP-A — Stop financial loss (P1) — money-movement guards + idempotency

| Finding | file:line |
|---|---|
| `pay_order` confirm skips amount/ownership/status (underpayment) | `src/API/PaymentController.php:467` |
| PayPal batch never sets `paid_out` → re-pays each run | `src/PayPalPayouts/PayoutsBatchService.php:403` |
| No lock/idempotency on batch creation → double pay (P2) | `src/PayPalPayouts/PayoutsBatchService.php:77` |
| Withdrawal reject no terminal guard → double cash-out | `src/Services/EarningsService.php:468` |
| REST withdrawal ignores clearance hold (P2) | `src/API/EarningsController.php:457` |

Shared seam: order/payout state-guard + idempotent claim-and-mark.

## WP-B — Pro billing integrity (P1) — reconcile local state with real Stripe state

*Root cause across all of these: local `active` persisted without confirming Stripe payment.*

| Finding | file:line |
|---|---|
| Subscribe marks plan **Active with no card captured** (Stripe `incomplete`, free access) | `src/VendorSubscriptions/SubscriptionBillingHandler.php:444` |
| Paid plan with blank Stripe Price ID silently routes free/manual | `src/API/SubscriptionPlanController.php:434` |
| Plan switch creates new Stripe sub without cancelling old → double-bill (P2) | `src/VendorSubscriptions/SubscriptionBillingHandler.php:154` |
| Plan `commission_override` stored/shown but never applied | `src/VendorSubscriptions/SubscriptionSettingsRenderer.php:469` |
| Recurring Stripe sub has no payment method → never charges | `src/RecurringServices/StripeRecurringBilling.php:547` |
| Commission `flat` applied as % (P2) | `src/TieredCommission/Rules/AbstractCommissionRule.php:184` |
| Commission rules table always "Active" (P2) | `src/TieredCommission/CommissionSettingsRenderer.php:148` |

Shared seams: (1) Checkout-Session/SetupIntent card capture + persist `active` only on Stripe
active/trialing; (2) single `wpss_commission_rate` resolver. Verify with live Stripe test-mode.

## WP-C — Vendor-identity class sweep (1 P1 + 4 P2) — one helper, five sites

Replace raw `_wpss_is_vendor` meta with `wpss_is_vendor()`; route API register through `VendorService`.

| Finding | file:line |
|---|---|
| Portfolio add/edit 403 for role/demo vendors (P1) | `src/API/PortfolioController.php:404` |
| API vendor register writes ad-hoc meta → corrupt vendor (P1) | `src/API/AuthController.php:338` |
| GET /vendors excludes role/demo vendors (P2) | `src/API/VendorsController.php:237` |
| GET /vendors/me + /me/vacation 404 (P2) | `src/API/VendorsController.php:345` |
| GET /vendors/me/level 403 (P2) | `src/API/SellerLevelsController.php:282` |

## WP-D — Onboarding & broken wiring (P1 + P2)

| Finding | file:line |
|---|---|
| Wizard saves PayPal creds under keys gateway never reads → stays Disabled (P1) | `src/Admin/Pages/SetupWizardPage.php:178` |
| Buyer proposal decision reads `price`/`delivery_days` (no columns) → $0/0 (P1) | `src/API/BuyerRequestsController.php:770` |
| Proposal admin metabox shows 0.00/blank (P2) | `src/Admin/Metaboxes/BuyerRequestMetabox.php:196` |
| Nested proposal submit field mismatch → always 400 (P2) | `src/API/BuyerRequestsController.php:636` |
| Audit-log cleanup cron never bound → retention no-op (P2) | `src/Services/AuditLogService.php:266` |
| Cart deleted before order confirmed + no `wpss_cart_checkout` handler (P2) | `src/API/CartController.php:344` |

Shared seam: one proposals formatter/service.

## WP-E — Data-fidelity & moderation trust (P2)

| Finding | file:line |
|---|---|
| Tips read `reference_type='tip'`; writer stores `type='tip'` → always empty | `src/API/TippingController.php:201` |
| Tip rows read non-existent `meta` column → tipper blank | `src/API/TippingController.php:212` |
| Review moderation never recomputes cached rating | `src/Admin/Pages/ReviewModerationPage.php:696` |
| REST review create ignores "Moderate reviews" toggle | `src/API/ReviewsController.php:390` |
| SellerCard rating/count omits `status='approved'` | `src/Blocks/SellerCard.php:259` |
| Featured block/grid vs shortcode key drift | `src/Blocks/FeaturedServices.php:154` |
| Featured block INNER-JOINs `_wpss_rating_average` → hides every zero-review featured service (P2, wave-3) | `src/Blocks/FeaturedServices.php:160` |
| Bulk Reject doesn't set `draft` → stays live | `src/Admin/Pages/ServiceModerationPage.php:921` |
| Search/Category blocks post `wpss_search`/`wpss_category`; archive reads `search`/`category` → filter silently no-ops (P1, wave-3) | `src/Blocks/ServiceSearch.php:140`, `src/Blocks/ServiceCategories.php:197` |
| SellerCard "View Profile" → theme author archive, not vendor storefront; dead `?contact=` (P2, wave-3) | `src/Blocks/SellerCard.php:219` |
| Guest "Continue to Checkout" dead-ends (5s error, no login link) though `login_url`/`isLoggedIn` are localized (P2, wave-3) | `assets/js/single-service.js:642` |
| Dispute timeline array-as-object access → "System"/blank | `src/Services/DisputeWorkflowManager.php:1024` |
| Dispute-opened email omits buyer reason (incl. admin copy) | `templates/emails/dispute-opened.php:113` |
| Order-cancelled email reason always blank (P3) | `templates/emails/order-cancelled.php:71` |

## WP-F — Ship or gate half-built surfaces (P1 + P2)

**Disputes on-site surface (P1, wave-3) — build it as a unit:** a member can OPEN a dispute
but has nowhere on the site to view/respond/add-evidence/see resolution; both dispute
templates are orphaned. Wire it AND fix the 3 latent template/data bugs so they don't bite
the moment it renders:
| Finding | file:line |
|---|---|
| No on-site dispute surface — add `disputes` dashboard section + render `dispute-view.php` | `src/Frontend/UnifiedDashboard.php:311` |
| Order page shows only "Disputed" dot, no path to the dispute | `templates/order/order-view.php:1003` |
| Evidence file stored as `absint(url)=0` → attachment lost on reload | `src/Services/DisputeService.php:314` |
| dispute-view reads arrays as objects → evidence blank (P3) | `templates/disputes/dispute-view.php:240` |
| dispute-view reads `initiator_id`; row is `initiated_by` → "Opened by Unknown" (P3) | `templates/disputes/dispute-view.php:54` |

Check Pro first: `wpss_dashboard_sections` filter may already add this on Pro sites (masks the P1 there); free installs still have nothing.

**Other half-built surfaces (P2):**
| Finding | file:line |
|---|---|
| Standalone notifications no mark-read; the good template never renders | `src/Integrations/Standalone/StandaloneAccountProvider.php:547` |
| Realtime JS dispatches events nothing consumes | `assets/js/wpss-realtime.js:86` |
| Analytics export → 403 (Deny-from-all dir + direct URL) | `src/Analytics/DataExporter.php:70` |
| /upload ignores `order_id` → participants 403 | `src/API/StorageController.php:353` |
| Two dead admin order buttons ("not yet implemented") | `src/Admin/Metaboxes/OrderMetabox.php:829` |
| Delivered-order admin actions key on `'delivered'` not `pending_approval` (P3) | `src/Admin/Metaboxes/OrderMetabox.php:953` |
| Bulk payout/vendor actions discard per-row failure report on `location.reload()` → silent partial success (P2, wave-3) | `src/Admin/Pages/WithdrawalsPage.php:709`, `src/Admin/Pages/VendorsPage.php:867` |
| Manual Order page eager-loads ALL services + ALL users (twice) → hangs at scale (P2, wave-3) | `src/Admin/Pages/ManualOrderPage.php:124` |
| Setup wizard "Save & Continue" advances even on save error → step config lost (P3, wave-3) | `src/Admin/Pages/SetupWizardPage.php:928` |
| ServiceModeration uses native `confirm()`/`prompt()` (blocked/mobile-broken) + missing aria-labels (P3, wave-3) | `src/Admin/Pages/ServiceModerationPage.php:572` |

## WP-H — Buyer/vendor free UX (P2) — usability, mostly template-layer

| Finding | file:line | Shared fix |
|---|---|---|
| Drag-dropped requirement files never submitted (DataTransfer bug) | `assets/js/requirements-form.js:58` | — |
| Orders / Services / Requests hard-cap at 20, no pager (Sales paginates) | `templates/dashboard/sections/orders.php:75`, `services.php:32`, `requests.php:29` | Lift `sales.php:379-411` pager into a shared helper |
| Buyer request card: budget/deadline always blank (wrong meta keys) | `templates/dashboard/sections/requests.php:76` | Read `_wpss_budget_min/max` + `_wpss_expires_at` |
| Editing a service shows H1 "Create Service" | `src/Frontend/UnifiedDashboard.php:497` (+ `:582` edit-request title P3) | Fix title map |
| Notifications promised on Profile but no surface exists anywhere | `src/Frontend/UnifiedDashboard.php:355` | Add notifications section (revives realtime badge target) |
| Portfolio cards fake-clickable + keyboard-trapped (`tabindex=-1`) | `templates/partials/vendor-portfolio.php:207` | Wire lightbox or drop false affordance |
| "View all reviews" skips reviews #6–10 (LIMIT 5 vs per_page 10) | `templates/vendor/profile.php:448` | Align initial/load-more page size |
| Messaging composer locked on 7 legit active statuses | `templates/order/conversation.php:114` | Widen whitelist / add `wpss_conversation_can_message` filter |

## WP-I — Big-site / P3 / tech-debt (backlog; re-check on 2000+ seeded data)

- Messaging unread badge: unindexed `JSON_CONTAINS` + whole-table GROUP BY — `src/Database/Repositories/ConversationRepository.php:169` (participants join table + index)
- PayPal payout 500-vendor cap + ~500 SUM N+1 — `src/PayPalPayouts/VendorPayoutProfileService.php:97`
- Dead template routes + orphaned `requirements-form.php` — `src/Frontend/TemplateLoader.php:389`
- Dead `templates/myaccount/*` (never rendered, false Preflight pass); dead `templates/emails/plain/*` (14 files) — decide multipart vs delete
- ~30 UNVERIFIED P3s from both waves — verify each before fixing.

---

## Coverage status
- ✅ All 3 previously-uncovered clusters (disputes, admin-screens, discovery) re-audited (wave 3).
- ⬜ One **browser + responsive (≤480px) + dark-mode** pass — zero runtime verification so far (findings are static-source only; each WP's replicate step covers its own).
- ⬜ Pro-repo check: does `wpss_dashboard_sections` add a disputes surface on Pro (masking the WP-F P1 there)?
- ⬜ Verify the ~40 P3/unverified items in source before scheduling.
- Not deep-audited: `Settings.php` (3067 lines) + `admin-settings*.js`, `UpgradePage.php`, Vendors single-vendor detail sub-tabs, Pro payment-webhook signature depth.
