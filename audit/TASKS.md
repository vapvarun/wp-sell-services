# WP Sell Services — Master Task List (audit remediation)

Single source of truth for the 1.2.2 audit-fix sprint. Everything below traces to
`audit/REMEDIATION-PLAN.md` (findings + file:line), `audit/COMMISSION-ARCHITECTURE.md`
(commission refactor), and the three audit reports. QA card: Basecamp WP Sell Services /
Bugs **#10109128594**. Branch: **1.2.2** (both repos).

Legend: `[x]` shipped + verified · `[~]` in progress · `[ ]` todo · (P1/P2/P3) severity.

---

## A. Money-loss guards
- [x] (P1) Withdrawal double cash-out — terminal-state guard · `EarningsService.php` · `774643f`
- [x] (P2) Withdrawal ignores clearance hold · `EarningsController.php` · `07c36c5`
- [x] (P1) Stripe pay_order underpayment/ownership/status bypass · `PaymentController.php` · `c42a6a8`
- [ ] (P1) PayPal payout batch never marks `paid_out` → re-pays · pro `PayoutsBatchService.php:403` · DB-testable
- [ ] (P2) PayPal payout no idempotency lock → double pay on double-click/2 admins · pro `PayoutsBatchService.php:77`
- [ ] (P2) PayPal payout 500-vendor cap + N+1 · pro `VendorPayoutProfileService.php:97`

## B. Pro billing integrity
- [x] (P1) Paid plan without Stripe Price ID grants free access · pro `SubscriptionPlanController.php` · `f8463f8`
- [x] (P1) Plan `commission_override` never applied (wallet path) · pro `SubscriptionManager.php` · `23e5c01`
- [ ] (P1) Subscribe marks Active with NO card captured · pro `SubscriptionBillingHandler.php:444` · needs browser 3DS
- [ ] (P2) Plan switch double-bills (new Stripe sub, old not cancelled) · pro `SubscriptionBillingHandler.php:154` · browser 3DS
- [ ] (P1) Recurring Stripe sub has no payment method → never charges · pro `StripeRecurringBilling.php:547` · browser 3DS
- [x] (P2) Tiered commission `flat` applied as a percentage — now resolves on the `wpss_commission_fee` amount seam · pro `TieredCommissionManager.php` · `24093f8`
- [ ] (P2) Commission rules table always prints "Active" · pro `CommissionSettingsRenderer.php:148` (display-only #8; still open)

## C. Vendor identity (COMPLETE)
- [x] (P1) Portfolio add/edit 403 for role vendors · `PortfolioController.php` · `d325cc0`
- [x] (P2) GET /vendors/me + /me/vacation 404 · `VendorsController.php` · `d325cc0`
- [x] (P2) GET /vendors/me/level 403 · `SellerLevelsController.php` · `d325cc0`
- [x] (P2) Vendor directory hides role vendors · `VendorsController.php` · `1e93799`
- [x] (P1) API register creates corrupt vendor · `AuthController.php` · `e2a1be2`

## D. Onboarding & broken wiring
- [ ] (P1) Setup wizard saves PayPal creds under keys the gateway never reads · `SetupWizardPage.php:178`
- [ ] (P1) Buyer proposal decision reads non-existent `price`/`delivery_days` → $0/0 · `BuyerRequestsController.php:770`
- [ ] (P2) Proposal admin metabox shows 0.00/blank · `BuyerRequestMetabox.php:196` (shared formatter with above)
- [ ] (P2) Nested proposal submit field mismatch → always 400 · `BuyerRequestsController.php:636`
- [ ] (P2) Audit-log cleanup cron never bound → retention no-op · `AuditLogService.php:266`
- [ ] (P2) Cart deleted before order confirmed + `wpss_cart_checkout` no handler · `CartController.php:344`

## E. Data-fidelity & moderation trust
- [x] (P1/P2) Tips list/total/badge empty + tipper/message · `TippingController.php` · `dc367b7`
- [x] (P2) Review moderation toggle + rating recompute · `ReviewsController.php` + `ReviewModerationPage.php` · `042b98a`
- [ ] (P2) SellerCard rating omits `status='approved'` · `SellerCard.php:259`
- [ ] (P1) Search/Category blocks post `wpss_search`/`wpss_category`; archive reads `search`/`category` · `ServiceSearch.php:140` + `ServiceCategories.php:197`
- [ ] (P2) Featured block/grid vs shortcode key drift + zero-review INNER JOIN hides featured · `FeaturedServices.php:154,160`
- [ ] (P2) SellerCard "View Profile" → theme author archive, not vendor storefront · `SellerCard.php:219`
- [ ] (P2) Guest "Continue to Checkout" dead-ends (login_url localized but unused) · `assets/js/single-service.js:642`
- [ ] (P2) Bulk Reject service doesn't set draft → stays live · `ServiceModerationPage.php:921`
- [ ] (P2) Dispute timeline reads arrays as objects → "System"/blank · `DisputeWorkflowManager.php:1024`
- [ ] (P2) Dispute-opened email omits buyer reason (incl. admin copy) · `templates/emails/dispute-opened.php:113`

## F. Ship or gate half-built surfaces
- [ ] (P1) Disputes have NO on-site surface — add dashboard section + wire `dispute-view.php` · `UnifiedDashboard.php:311` (+ order-view link, evidence-absint, initiated_by/array-access latents)
- [ ] (P2) Standalone notifications no mark-read; good template never renders · `StandaloneAccountProvider.php:547`
- [ ] (P2) Realtime JS dispatches events nothing consumes · `assets/js/wpss-realtime.js:86`
- [ ] (P2) Analytics export → 403 (Deny-from-all dir + direct URL) · pro `DataExporter.php:70`
- [ ] (P2) /upload ignores `order_id` → participants 403 · `StorageController.php:353`
- [ ] (P2) Two dead admin order buttons ("not implemented") · `OrderMetabox.php:829`
- [ ] (P2) Admin bulk payout/vendor actions discard per-row failure report on reload · `WithdrawalsPage.php:709` + `VendorsPage.php:867`
- [ ] (P2) Manual Order page eager-loads ALL services + ALL users (twice) → hangs at scale · `ManualOrderPage.php:124`

## G. Commission architecture (CONSOLIDATION DONE)
- [x] Phase 1 — `wpss_commission_fee` amount seam + parity · `CommissionService.php` · `4e45059`
- [x] Phase 2 — extract `compute_breakdown()` authority; `calculate()` delegates · free `CommissionService.php` · `1a68189`
- [x] Phase 2 — repoint creation-time fee sites (Standalone, WC, Seeder) so the PERSISTED fee already reflects tiered/override/flat · `1a68189` (free) + `24093f8` (pro WC)
- [x] Phase 2/4 — Stripe Connect split reads the persisted `platform_fee` (zero-decimal-aware cents); legacy %-of-total kept only as order_id-0 fallback; divergent math killed · pro `ConnectPaymentProcessor.php` · `24093f8`
- [x] Phase 3 — flat tiered rules onto `wpss_commission_fee` (amount); subscription override re-asserts on the same seam at higher priority so override > flat · pro `TieredCommissionManager.php` + `SubscriptionManager.php` · `24093f8`
- [x] PayPal payout — CONFIRMED already sums the persisted ledger `vendor_earnings` (`get_pending_payouts`); no change needed
- [~] ManualOrderPage — intentionally left on its admin-supplied per-order rate (admin authority applied to `$total`, not engine-resolved rate on pre-tax base); documented in `COMMISSION-ARCHITECTURE.md`
- Verified (`wp eval-file`, 11/11): parity byte-identical for pure-% global orders; flat rule → flat fee (not flat%); override 25% beats flat $10; Connect reads persisted $50 → 5000 cents (not recomputed). PHPCS net-zero. PHPStan project run blocked by phpstan-wordpress extension autoload gap in this checkout (needs `composer install`); standalone level-5 shows changed logic type-clean.

## H. Buyer/vendor UX (mostly template layer)
- [ ] (P2) Drag-dropped requirement files never submitted (DataTransfer) · `assets/js/requirements-form.js:58`
- [ ] (P2) Orders/Services/Requests hard-cap at 20, no pager (shared fix, mirror `sales.php:379`) · `dashboard/sections/{orders,services,requests}.php`
- [ ] (P2) Buyer request card budget/deadline blank (wrong meta keys) · `dashboard/sections/requests.php:76`
- [ ] (P2) Editing a service shows H1 "Create Service" · `UnifiedDashboard.php:497` (+ edit-request title `:582`)
- [ ] (P2) Notifications promised on Profile but no surface exists · `UnifiedDashboard.php:355`
- [ ] (P2) Portfolio cards fake-clickable + keyboard-trapped · `templates/partials/vendor-portfolio.php:207`
- [ ] (P2) "View all reviews" skips reviews #6-10 · `templates/vendor/profile.php:448`
- [ ] (P2) Messaging composer locked on 7 legit active statuses · `templates/order/conversation.php:114`

## I. Big-site / P3 backlog (verify each in source before fixing)
- [ ] Messaging unread badge unindexed `JSON_CONTAINS` full scan · `ConversationRepository.php:169`
- [ ] Dead template routes + orphaned `requirements-form.php` · `TemplateLoader.php:389`
- [ ] Dead `templates/myaccount/*` + false Preflight pass; dead `templates/emails/plain/*` (14 files) — multipart or delete
- [ ] ~30 UNVERIFIED P3s across both audit reports (favorites total, notifications pagination, deleted-user name fallbacks, wizard save-on-error, offline-instructions key, ledger balance column, order-cancelled email reason, email heading bands, etc.)

---

## Coverage still owed (before calling the audit fully closed)
- [ ] One BROWSER + responsive (≤480px) + dark-mode pass — nothing has had runtime UI verification yet
- [ ] Confirm Pro `wpss_dashboard_sections` doesn't already mask the disputes P1 on Pro sites
- [ ] Re-verify the ~30 P3/UNVERIFIED items in source
- [ ] Release: version bump + changelog + MANDATORY Docker install test (Reign + free/pro) before tagging 1.2.2

## Environment state (Local, for the next session)
- Stripe TEST keys + webhook secret set; webhook live via cloudflare tunnel (ephemeral — restart if the tunnel died).
- Pro active; `wpss_pro_license_status='valid'` set for local testing (revert with `wp option delete wpss_pro_license_status`).
- Verification pattern used: seed real rows via `wp eval-file`, assert before/after with positive+negative controls.
