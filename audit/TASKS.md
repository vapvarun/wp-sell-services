# WP Sell Services — Master Task List (audit remediation)

Single source of truth for the 1.2.2 audit-fix sprint. Everything below traces to
`audit/REMEDIATION-PLAN.md` (findings + file:line), `audit/COMMISSION-ARCHITECTURE.md`
(commission refactor), and the three audit reports. QA card: Basecamp WP Sell Services /
Bugs **#10109128594**. Branch: **1.2.2** (both repos).

Legend: `[x]` shipped + verified · `[~]` in progress · `[ ]` todo · (P1/P2/P3) severity.

---

## J. CHECKOUT — WAS BROKEN, NOW WORKS END-TO-END (P0 chain, 2026-07-19/20)

Found only by clicking through a real checkout in the browser; every prior
verification was `wp eval-file`/unit-level and missed it entirely. **Stripe
checkout now completes: order 41 paid, real PaymentIntent, correct commission.**

**⚠️ BUILD GOTCHA (bit me mid-fix):** `Assets.php` rewrites asset URLs to `.min`
when the file exists, so the site loads `stripe.min.js` and **edits to
`assets/js/*.js` are inert until the min is rebuilt**. `npm run build:min` fails
(no node_modules); use `npx terser assets/js/FILE.js -c -m -o assets/js/FILE.min.js`.
This also applies to the deferred H#8 drag-drop fix.

- [x] (P0) **Pay button posted to an unregistered AJAX action → admin-ajax returned bare `0`.** Checkout JS builds `wpss_{gateway}_process_payment` (`StandaloneCheckoutProvider.php:1193,1719`) but ONLY Offline + Test register that name. Registered the contract on Stripe's existing confirm handler + accepted the checkout nonce and `stripe_payment_intent_id`. · `StripeGateway.php` · `ab93f8a` — verified: response went `400`+`0` → `200`+real JSON.
- [x] (P0) **Checkout never charged the card — FIXED** · `42a0def`. Root cause: TWO submit handlers on the same form; stripe.js had the correct flow but the generic inline handler raced it and posted the unconfirmed intent. Gateways now declare `data-wpss-own-submit` and the generic handler stands down; stripe.js also binds the cart form + sends `is_multi_checkout`; PaymentIntents now carry a `description` (required by Stripe for India exports). Verified by the error advancing at each step: `0` → "requires additional action" → "requires a description" → "requires customer name and address".
- [x] (P0) **India-export compliance: customer name + address — FIXED** · `818ecca`. Added the Stripe Address Element, passed to `confirmPayment()` as `billing_details` + `shipping`, with a field-level prompt when incomplete. Affects every India-registered merchant selling cross-border.
- [x] **✅ VERIFIED END-TO-END** (BuddyX, Stripe test): buyer "Real Customer" bought a $50 service → `/dashboard/?order_id=41&action=requirements`, no error. Order 41: `payment_status=paid`, `payment_method=stripe`, `transaction_id=pi_3TvBOkSY8ch105Oa1irGYMdv`, **commission_rate 10.00 / platform_fee 5.00 / vendor_earnings 45.00** — first real payment through cluster G's `compute_breakdown()`, split correct.
- [ ] (P1) **PayPal + Razorpay still hit the original unregistered-action wall** (`wpss_{gateway}_process_payment` doesn't exist for them). They now have a clean seam to use: declare `data-wpss-own-submit` on their payment-fields container and own their submit, exactly like Stripe. PayPal also still has EMPTY credentials (`wpss_paypal_settings`) — needs client_id, client_secret, webhook_id.
- [ ] (P2) Verify the wallet ledger credit + vendor earnings roll-up once order 41 is completed (commission is persisted at creation; `CommissionService::record()` fires on completion). The checkout page's INLINE JS mounts the Stripe Payment Element, collects the card, then posts the UNCONFIRMED intent to the server — it never calls `stripe.confirmPayment()`. Result: `{"success":false,"message":"Payment requires additional action."}` even with a valid 4242 test card. `assets/js/stripe.js:176-192` already implements the correct flow (`confirmPayment()` → `confirmPaymentAndCreateOrder()`), so there are TWO parallel Stripe implementations and the checkout page runs the broken duplicate. **Right fix = delete the inline duplicate and drive checkout through `assets/js/stripe.js`** (matches the no-duplicate-code rule); do NOT bolt a second confirm call into the inline copy.
- [ ] (P0) **Same contract break for PayPal + Razorpay** — they register `create_order`/`capture` and `create_order`/`verify_payment`, so they hit the identical unregistered-action wall. Apply the same mapping once the confirm-flow above is settled.
- [ ] (P2) Pay button stays stuck on "Processing..." after a failed payment (never re-enabled) · `StandaloneCheckoutProvider.php` submit handler.
- [ ] Re-run the full browser checkout after the confirm fix and verify: order row created, `platform_fee`/`vendor_earnings` persisted by `compute_breakdown()`, wallet ledger credited. **The commission work (cluster G) has never had a real payment run through it.**

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
- [x] (P1) Disputes on-site surface — new "Disputes" dashboard section + self-contained `templates/dashboard/sections/disputes.php` (list + party-gated detail via `get_timeline`) · `UnifiedDashboard.php` · `16daaad` — ✅ BROWSER-VERIFIED on **BuddyX** (desktop + 390px mobile): nav item renders, list table stacks responsively, detail shows status/reason and the Activity timeline with the REAL user name (confirms the E6 evidence array-access fix end-to-end, not "System"). (Orphaned `dispute-view.php` left for a future richer view.)
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

## H. Buyer/vendor UX (7 done · 1 deferred JS)
- [x] (P2) Buyer request card budget/deadline blank — read real keys `_wpss_budget_min/_max` + `_wpss_delivery_days` (were `_wpss_budget`/`_wpss_deadline`, never written) · `dashboard/sections/requests.php` · `26ff392`
- [x] (P2) Editing a service showed H1 "Create Service" — inverted guard (`!== 'create'` → `=== 'create'`); added `edit-request` title · `UnifiedDashboard.php` · `26ff392`
- [x] (P2) Messaging composer locked on 7 legit active statuses — expanded allow-list (accepted, requirements_submitted, pending_approval, on_hold, late, cancellation_requested, disputed) · `templates/order/conversation.php` · `26ff392`
- [ ] (P2) Drag-dropped requirement files never submitted (DataTransfer) · `assets/js/requirements-form.js:149` — DEFERRED browser/JS (fix: sync dropped files into `input.files` via DataTransfer)
- [x] (P2) Orders/Services/Requests hard-cap at 20 — added pagination (Orders via new `OrderRepository::count_by_customer()`+offset; Services/Requests via WP_Query `paged`+`max_num_pages`), pager links relative to the current section URL · `dashboard/sections/{orders,services,requests}.php` · `22e8efa` — ✅ BROWSER-VERIFIED on BuddyX (seeded 22 orders → Page 1/2 → Next → Page 2/2 → Previous)
- [x] (P2) Portfolio cards fake-clickable + keyboard-trapped — card now non-focusable `role=figure`, overlay exposed + revealed on `:focus-within`, "View Project" link keyboard-reachable (was `tabindex=-1` in aria-hidden overlay) · `templates/partials/vendor-portfolio.php` · `22e8efa`
- [x] (P2) "View all reviews" skipped reviews #6-10 — initial render aligned to 10 (button threshold >10) to match the load-more JS's per_page=10/offset-10 model; no JS change, no gap · `templates/vendor/profile.php` · `966b2ec`
- [x] (P2) Notifications promised on Profile but no surface — added a "Notifications" dashboard section + `templates/dashboard/sections/notifications.php` (list + working mark-all/one-read via existing AJAX) · `UnifiedDashboard.php` · `a4cad8f` — ✅ BROWSER-VERIFIED on BuddyX (8 notifs render, unread highlighted, "Mark all as read" clears DB 8→0 + UI). NOTE: used `wpss-notif-*` classes — `wpss-notification` is the fixed-position toast component (reusing it hid the list off-screen).
- [ ] (P2) Drag-dropped requirement files never submitted · `assets/js/requirements-form.js` — DEFERRED JS. Exact fix: in `initFileUpload()`, add `function syncInput(){ var dt=new DataTransfer(); files.forEach(f=>dt.items.add(f)); $input[0].files=dt.files; }` and call it at the end of `handleFiles()` and after the `files.splice()` in the remove handler — so the native `<input type=file>` holds the JS `files` array and `FormData($form)` actually submits dropped files. NOTE: verify whether `requirements-form.min.js` is the served asset (Assets.php min-swap) — if so, regenerate the min (no in-repo build tool; may need manual minify or a build step).

## I. Big-site / P3 backlog (verify each in source before fixing) — NOT STARTED
- [ ] Messaging unread badge unindexed `JSON_CONTAINS` full scan · `ConversationRepository.php:169` — CONFIRMED real. NOT a quick fix: `JSON_CONTAINS(participants, …)` can't use an index → full table scan per call, fetches all rows + sums in PHP. Proper fix = schema migration (participants join table w/ indexed participant_id, OR indexed generated column) + write-path updates + a per-user unread cache with write-time invalidation. Own focused effort with seeded 1000+ conversation scale test; no band-aid.
- [ ] Dead template routes + orphaned `requirements-form.php` · `TemplateLoader.php:389` — entangled with browser-batch F#2 (the "good" `templates/myaccount/notifications.php` should be WIRED, not deleted) and the intentionally-retained `templates/disputes/dispute-view.php`. Resolve alongside those, don't bulk-delete.
- [ ] Dead `templates/myaccount/*` + false Preflight pass; dead `templates/emails/plain/*` (14 files) — decide multipart-vs-delete AFTER F#2 + the dispute email plain-sibling reason fix.
- [ ] ~30 UNVERIFIED P3s across both audit reports — each needs source verification first (favorites total, notifications pagination, deleted-user name fallbacks, wizard save-on-error, offline-instructions key, ledger balance column, order-cancelled email reason, email heading bands, etc.). Treat as leads, not specs.

---

## Coverage still owed (before calling the audit fully closed)

### Browser batch (one focused session — all `[browser-owed]` items + JS/template fixes)
- [ ] F#2 Standalone notifications (WC/EDD myaccount surface only — the UNIFIED dashboard now has a working notifications center from H#6/`a4cad8f`): render the good `templates/myaccount/notifications.php` + add mark-read JS for the standalone/myaccount provider path (backends exist)
- [ ] F#3 Realtime JS: add consumers for `wpss:realtime:notification`/`wpss:realtime:message` (or document as a public extension API) · `assets/js/wpss-realtime.js`
- [ ] F#8 Manual Order page: convert all-users/all-services `<select>`s to AJAX search (select2) — currently unbounded, hangs at scale · `ManualOrderPage.php:124`
- [ ] H#2 "View all reviews" load-more offset (JS+REST) · `frontend.js:394` + `vendor/profile.php:446`
- [ ] H#5 Orders/Services/Requests pagination (mirror `sales.php:379`; add `OrderRepository::count_by_customer()`)
- [ ] H#6 Profile notifications surface (new `notifications` dashboard section + template)
- [ ] H#7 Portfolio card a11y (real anchor/button, not a `role=article` div; fix the `tabindex=-1`/`aria-hidden` overlay trap)
- [ ] H#8 Drag-drop requirement files: sync dropped files into `input.files` via DataTransfer · `requirements-form.js:149`
- [ ] Visual/responsive (≤480px) + dark-mode sign-off on every `[browser-owed]` fix already shipped (E: SellerCard View-Profile, guest-checkout redirect, bulk-reject; F: disputes surface, dead-button removal, bulk-report notice)

### PayPal + Stripe live session (needs real gateway / browser 3DS)
- [ ] Cluster A (PayPal payout: mark paid_out, idempotency, N+1/cap)
- [ ] Cluster B (Stripe billing 3DS trio + #8 rules-table "Active" display)
- [ ] Commission Phase 2/4 END-TO-END: real Connect account, verify flat + override reach `application_fee_amount` in a live 3DS payment (unit/DB path already verified)
- [ ] D setup-wizard PayPal cred keys (confirm write-key == gateway read-key end-to-end)

### Then
- [ ] Confirm Pro `wpss_dashboard_sections` doesn't already register/mask a `disputes` section on Pro sites (my free-side add)
- [ ] Cluster I (P3 backlog) — JSON_CONTAINS migration + verify-then-fix the ~30 P3s
- [ ] Release: version bump + changelog + MANDATORY Docker install test (Reign + free/pro) before tagging 1.2.2

## Environment state (Local, for the next session)
- Stripe TEST keys + webhook secret set; webhook live via cloudflare tunnel (ephemeral — restart if the tunnel died).
- Pro active; `wpss_pro_license_status='valid'` set for local testing (revert with `wp option delete wpss_pro_license_status`).
- **Theme: BuddyX 5.1.4 active** (GitHub `master` from github.com/wbcomdesigns/buddyx, installed into `wp-content/themes/buddyx` per owner request; ships built `.min` assets, no npm build needed. Was twentytwentyfive → wp.org 5.1.3 → master 5.1.4). BuddyX Pro / Reign not installed (no local zips) — drop a premium zip to test those.
- **Browser testing: use the HOST Playwright MCP** (`mcp__plugin_playwright_playwright__*`), NOT the Docker one — the Docker browser can't resolve `wp-sell-services.local`. Site: `http://wp-sell-services.local`, dashboard `/dashboard/` (sections are pretty-permalinked, e.g. `/dashboard/disputes/`). Auto-login via `?autologin=1`. Tour modal suppressed via `wp user meta update 1 wpss_tour_completed 1`.
- Demo dispute seeded for browser test: order #18 / dispute #4 (user 1 as customer). Delete when done.
- Verification pattern used: seed real rows via `wp eval-file`, assert before/after with positive+negative controls.
