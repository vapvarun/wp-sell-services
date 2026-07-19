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
- [ ] (P1) Setup wizard saves PayPal creds under keys the gateway never reads · `SetupWizardPage.php:178` — DEFERRED to PayPal/Stripe session (verify write-key == gateway read-key end-to-end there)
- [x] (P1) Buyer proposal $0/0 — response preparer read non-existent `price`/`delivery_days`; now reads `proposed_price`/`proposed_days` (+contract_type/milestones) · `BuyerRequestsController.php` · `ad5bfa1`
- [x] (P2) Proposal admin metabox 0.00/blank — same key fix (`proposed_price`/`proposed_days`) · `BuyerRequestMetabox.php` · `ad5bfa1`
- [x] (P2) REST proposal submit always 400 — controller sent `cover_letter`, `submit()` requires `description`; now mapped + forwards contract_type/milestones/attachments · `BuyerRequestsController.php` · `ad5bfa1`
- [x] (P2) Audit-log cleanup cron never bound — event scheduled but no handler; added `AuditLogService::init()` binding `wpss_audit_log_cleanup`, wired in bootstrap · `AuditLogService.php` + `Plugin.php` · `ad5bfa1`
- [x] (P2) Cart destroyed on failed/absent checkout — `wpss_cart_checkout` has no handler yet cart was deleted + "Order created" faked; now clears cart ONLY on a real order, returns 501 otherwise (seam kept) · `CartController.php` · `ad5bfa1`
- Verified (`wp eval-file`): submit no longer 400s + stores 250/5; response surfaces 250/5 (was $0/0); audit cron prunes a 60-day row at retention 30; no-handler checkout returns WP_Error + PRESERVES cart, real order clears it. PHPCS net-zero (5 files).

## E. Data-fidelity & moderation trust
- [x] (P1/P2) Tips list/total/badge empty + tipper/message · `TippingController.php` · `dc367b7`
- [x] (P2) Review moderation toggle + rating recompute · `ReviewsController.php` + `ReviewModerationPage.php` · `042b98a`
- [x] (P2) SellerCard rating omits `status='approved'` — now matches canonical `status='approved' AND review_type='customer_to_vendor'` · `SellerCard.php` · `2fecf09`
- [x] (P1) Search/Category blocks posted `wpss_search`/`wpss_category`; archive reads `search`/`category` — blocks realigned to `search`/`category` · `ServiceSearch.php` + `ServiceCategories.php` · `2fecf09`
- [x] (P2) Featured shortcode orphan key `_wpss_is_featured`→`_wpss_featured`; block zero-review INNER JOIN → OR EXISTS/NOT EXISTS (LEFT JOIN) · `FeaturedServices.php` + `Shortcodes.php` · `2fecf09`
- [x] (P2) SellerCard "View Profile" → now `wpss_get_vendor_url()` storefront (was theme author archive) · `SellerCard.php` · `2fecf09` — [browser-owed: visual]
- [x] (P2) Guest "Continue to Checkout" — JS now redirects to the returned `login_url` instead of dead-ending · `assets/js/single-service.js` · `2fecf09` — [browser-owed]
- [x] (P2) Bulk Reject now sets `post_status=draft` (was meta-only, stayed live) · `ServiceModerationPage.php` · `2fecf09` — [browser-owed: admin UI]
- [x] (P2) Dispute timeline read array evidence as objects → "System"/blank; now array access · `DisputeWorkflowManager.php` · `2fecf09`
- [x] (P2) Dispute-opened email omitted buyer reason (incl. admin copy); `send_dispute_opened()` now loads the dispute + passes `dispute_reason` · `EmailService.php` · `2fecf09`
- Verified (`wp eval-file`): featured query includes an UNRATED featured service (control proves old orderby excluded it); SellerCard rating=5.0 counting only approved c2v; dispute evidence timeline shows real user_id + content. PHPCS net-zero (8 files). `.min.js` not enqueued (source authoritative; regenerated at release build).

## F. Ship or gate half-built surfaces
- [x] (P1) Disputes on-site surface — new "Disputes" dashboard section + self-contained `templates/dashboard/sections/disputes.php` (list + party-gated detail via `get_timeline`) · `UnifiedDashboard.php` · `16daaad` — [browser-owed: visual]. (Orphaned `dispute-view.php` left for a future richer view.)
- [ ] (P2) Standalone notifications no mark-read; good template never renders · `StandaloneAccountProvider.php:547` — DEFERRED to browser batch (needs mark-read JS)
- [ ] (P2) Realtime JS dispatches events nothing consumes · `assets/js/wpss-realtime.js:86` — DEFERRED to browser batch (needs Pusher + JS consumers; events are a public API)
- [x] (P2) Analytics export → 403 — now served via authenticated `wpss_analytics_download_export` streamer (nonce + manage_options + traversal-safe filename) · pro `AnalyticsManager.php` · `d2c8d0e`
- [x] (P2) /upload ignored `order_id` → participants 403 — now persists `_wpss_order_id` (+ participant check) so downloads authorize · pro `StorageController.php` · `d2c8d0e`
- [x] (P2) Two dead admin order buttons — "Resend Notifications" + "Extend Deadline" removed (no backend; gated) · `OrderMetabox.php` · `474d436` — [browser-owed]
- [x] (P2) Admin bulk payout/vendor per-row report now survives reload via a per-user transient notice · `WithdrawalsPage.php` + `VendorsPage.php` · `474d436` — [browser-owed]
- [ ] (P2) Manual Order page eager-loads ALL services + ALL users (twice) → hangs at scale · `ManualOrderPage.php:124` — DEFERRED to browser batch (needs AJAX/select2 search)

## G. Commission architecture (CONSOLIDATION DONE)
- [x] Phase 1 — `wpss_commission_fee` amount seam + parity · `CommissionService.php` · `4e45059`
- [x] Phase 2 — extract `compute_breakdown()` authority; `calculate()` delegates · free `CommissionService.php` · `1a68189`
- [x] Phase 2 — repoint creation-time fee sites (Standalone, WC, Seeder) so the PERSISTED fee already reflects tiered/override/flat · `1a68189` (free) + `24093f8` (pro WC)
- [x] Phase 2/4 — Stripe Connect split reads the persisted `platform_fee` (zero-decimal-aware cents); legacy %-of-total kept only as order_id-0 fallback; divergent math killed · pro `ConnectPaymentProcessor.php` · `24093f8`
- [x] Phase 3 — flat tiered rules onto `wpss_commission_fee` (amount); subscription override re-asserts on the same seam at higher priority so override > flat · pro `TieredCommissionManager.php` + `SubscriptionManager.php` · `24093f8`
- [x] PayPal payout — CONFIRMED already sums the persisted ledger `vendor_earnings` (`get_pending_payouts`); no change needed
- [~] ManualOrderPage — intentionally left on its admin-supplied per-order rate (admin authority applied to `$total`, not engine-resolved rate on pre-tax base); documented in `COMMISSION-ARCHITECTURE.md`
- Verified (`wp eval-file`, 11/11): parity byte-identical for pure-% global orders; flat rule → flat fee (not flat%); override 25% beats flat $10; Connect reads persisted $50 → 5000 cents (not recomputed). PHPCS net-zero. PHPStan project run blocked by phpstan-wordpress extension autoload gap in this checkout (needs `composer install`); standalone level-5 shows changed logic type-clean.

## H. Buyer/vendor UX (3 pure-logic done · 5 deferred to browser batch)
- [x] (P2) Buyer request card budget/deadline blank — read real keys `_wpss_budget_min/_max` + `_wpss_delivery_days` (were `_wpss_budget`/`_wpss_deadline`, never written) · `dashboard/sections/requests.php` · `26ff392`
- [x] (P2) Editing a service showed H1 "Create Service" — inverted guard (`!== 'create'` → `=== 'create'`); added `edit-request` title · `UnifiedDashboard.php` · `26ff392`
- [x] (P2) Messaging composer locked on 7 legit active statuses — expanded allow-list (accepted, requirements_submitted, pending_approval, on_hold, late, cancellation_requested, disputed) · `templates/order/conversation.php` · `26ff392`
- [ ] (P2) Drag-dropped requirement files never submitted (DataTransfer) · `assets/js/requirements-form.js:149` — DEFERRED browser/JS (fix: sync dropped files into `input.files` via DataTransfer)
- [ ] (P2) Orders/Services/Requests hard-cap at 20, no pager · `dashboard/sections/{orders,services,requests}.php` — DEFERRED browser (mirror `sales.php:379`; Orders also needs a new `OrderRepository::count_by_customer()`)
- [ ] (P2) Notifications promised on Profile but no surface exists · `UnifiedDashboard.php:355` — DEFERRED browser (needs a `notifications` section + template; mark-read backends already exist)
- [ ] (P2) Portfolio cards fake-clickable + keyboard-trapped · `templates/partials/vendor-portfolio.php:239` — DEFERRED browser/a11y (card is a `role=article` div with cursor:pointer but no handler; the real link is `tabindex=-1` in an `aria-hidden` overlay)
- [ ] (P2) "View all reviews" skips reviews #6-10 · `templates/vendor/profile.php:446` + `assets/js/frontend.js:394` — DEFERRED browser/JS (initial LIMIT 5 but load-more sends page=2&per_page=10 → offset 10, skipping rows 5-9)

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
