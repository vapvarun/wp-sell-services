# WP Sell Services (free+pro) — Journey Audit — 2026-07-19

## Executive summary

This is a large, mature codebase with a correspondingly large defect surface: the audit confirmed **10 P1 and 26 P2 findings** (36 high-severity, source-verified). The plugin is not in good shape on the money-and-membership paths — every monetization surface has at least one confirmed break: payment confirmation skips amount/ownership checks (underpayment bypass), PayPal batch payouts re-pay the same earnings, admin withdrawal rejection double-pays, paid vendor-plan commission overrides are inert, and recurring Stripe subscriptions never charge. Four gap themes dominate: **(1) contract/key drift** — code reads meta/option/param/column names that no writer ever populates (`_wpss_featured`, `_wpss_is_featured`, `wpss_search`/`wpss_category`, `reference_type='tip'`, wizard PayPal keys, proposal `price`/`delivery_days`, tip `meta` column); **(2) meta-vs-role vendor identity** — five REST surfaces gate on the legacy `_wpss_is_vendor` meta that role-based/demo-seeded vendors never carry, silently excluding the exact accounts an evaluator uses; **(3) broken wiring** — filters/hooks/cron declared but never bound or consumed (`wpss_cart_checkout`, `wpss_audit_log_cleanup`, realtime CustomEvents, `wpss_review_moderated`); **(4) money-flow correctness & concurrency** — missing idempotency/state guards on paid/payout transitions. Roughly a third of these bugs share one root cause — duplicated logic across two entry points where only one path was ever fixed — so consolidation, not one-off patches, is the right fix strategy.

## Top findings

| Severity | Area | Gap | Entry point | file:line | Fix |
|---|---|---|---|---|---|
| P1 | Payments | `pay_order` confirm never checks amount/ownership/status — pay a $5 capture, mark a $500 order paid | api | src/API/PaymentController.php:467 | Load order first; reject on non-owner, non-`pending_payment`, or captured amount ≠ total |
| P1 | Payouts (Pro) | PayPal batches never set `paid_out`, so every run re-pays the same earnings | admin | src/PayPalPayouts/PayoutsBatchService.php:403 | Atomically mark settled orders `paid_out` in create_batch; reconcile on failure |
| P1 | Withdrawals | `process_withdrawal` has no terminal-state guard — rejecting a completed payout re-inflates balance (double cash-out) | admin | src/Services/EarningsService.php:468 | Add the REST twin's terminal-state guard; skip terminal IDs in bulk loop |
| P1 | Subscriptions (Pro) | Plan `commission_override` is stored/shown but never applied — paid plans charge global rate | cross | src/VendorSubscriptions/SubscriptionSettingsRenderer.php:469 | Add `wpss_commission_rate` filter reading active plan's override |
| P1 | Recurring (Pro) | Stripe subscription created against a customer with no payment method — never charges, shown "Active" | api | src/RecurringServices/StripeRecurringBilling.php:547 | Attach card/`setup_future_usage` + `default_payment_method`; derive status from Stripe |
| P1 | Tipping | Tip reads filter `reference_type='tip'` but writer stores `'order'`/`type='tip'` — tips list/total/badge always empty | api | src/API/TippingController.php:201 | Filter by `type='tip'` (mirror TippingService) |
| P1 | Proposals | Buyer's decision endpoint reads `price`/`delivery_days` (no such columns) — every bid shows $0 / 0 days | api | src/API/BuyerRequestsController.php:770 | Read `proposed_price`/`proposed_days`; share one formatter |
| P1 | Portfolio | `check_vendor_permissions` gates on raw `_wpss_is_vendor` meta — 403 on every add/edit for role/demo vendors | api | src/API/PortfolioController.php:404 | Use `wpss_is_vendor()` (as VendorsController already does) |
| P1 | Auth/Signup | API `register(role=vendor)` writes ad-hoc meta only — corrupt, capability-less, permanently-pending vendor; bypasses registration mode | api | src/API/AuthController.php:338 | Delegate to `VendorService::register()` |
| P1 | Setup wizard | PayPal creds saved under keys the gateway never reads — PayPal stays Disabled after onboarding | admin | src/Admin/Pages/SetupWizardPage.php:178 | Write `sandbox_mode`/`sandbox_client_id`/`live_client_id`… keys |
| P2 | Services catalog | Search/Category blocks post `wpss_search`/`wpss_category`; archive reads `search`/`category` — filtering silently no-ops | frontend | src/Blocks/ServiceSearch.php:140 | Unify on one param name everywhere |
| P2 | Services catalog | SellerCard rating/count omit `status='approved'` — counts pending/rejected reviews | frontend | src/Blocks/SellerCard.php:259 | Add `AND status='approved'` (+ review_type guard) |
| P2 | Services catalog | Featured block/grid key on `_wpss_featured`; shortcode on `_wpss_is_featured` — no admin UI writes either | cross | src/Blocks/FeaturedServices.php:154 | Add a Featured toggle writing one canonical key |
| P2 | Moderation | Bulk Reject sets meta but not `draft` (single reject does) — rejected service stays live, resubmit CTA never shows | admin | src/Admin/Pages/ServiceModerationPage.php:921 | Mirror single reject: set `post_status=draft` |
| P2 | Cart | `checkout()` deletes cart before order confirmed, and `wpss_cart_checkout` has no handler — cart lost, no order | api | src/API/CartController.php:344 | Delete only after successful order; register a handler |
| P2 | Proposals | Admin metabox reads `price`/`delivery_days` — shows 0.00 / blank for every bid | admin | src/Admin/Metaboxes/BuyerRequestMetabox.php:196 | Use `proposed_price`/`proposed_days` |
| P2 | Proposals | Nested submit maps `cover_letter` but service checks `description` — route always 400s with misleading error | api | src/API/BuyerRequestsController.php:636 | Map `description` => cover_letter |
| P2 | Reviews | Approve/reject never recomputes cached service/vendor rating (`wpss_review_moderated` has no listener) | admin | src/Admin/Pages/ReviewModerationPage.php:696 | Recompute ratings on status change |
| P2 | Reviews | REST create auto-approves regardless of admin "Moderate reviews" toggle | api | src/API/ReviewsController.php:390 | Default from `wpss_vendor['moderate_reviews']` |
| P2 | Disputes | Member dispute UI is orphaned — can open a dispute but never view/respond/add evidence on-site | frontend | templates/order/order-view.php:1003 | Wire dispute-view into dashboard + order link + email URL |
| P2 | Disputes | Timeline reads evidence arrays with object syntax — every entry shows "System", blank body, null date | api | src/Services/DisputeWorkflowManager.php:1024 | Access `$item['...']` array keys |
| P2 | Vendor directory | GET /vendors meta_query requires `_wpss_is_vendor` — role/demo vendors invisible though in admin list | api | src/API/VendorsController.php:237 | List from `wpss_vendor_profiles` or add role clause |
| P2 | Vendor self | GET /vendors/me + /me/vacation use raw meta check that PUT /vendors/me does not — 404 for role vendors | api | src/API/VendorsController.php:345 | Use `wpss_is_vendor()` |
| P2 | Seller levels | GET /vendors/me/level rejects role/seeded vendors (403) | api | src/API/SellerLevelsController.php:282 | Use `wpss_is_vendor()` |
| P2 | Realtime | Realtime JS dispatches events/badge nothing consumes — Pusher config produces zero visible effect | frontend | assets/js/wpss-realtime.js:86 | Ship consuming UI or gate the settings |
| P2 | Notifications | Standalone (default) notifications page has no mark-read; the template that has one is never rendered | frontend | src/Integrations/Standalone/StandaloneAccountProvider.php:547 | Render myaccount/notifications.php + wire mark-all AJAX |
| P2 | Tipping | Tip rows read a `meta` column that doesn't exist — tipper name/avatar/message always empty | api | src/API/TippingController.php:212 | Persist + read tipper_id/note from real source |
| P2 | Withdrawals | REST withdrawal ignores clearance-hold window — vendors cash out un-cleared earnings | api | src/API/EarningsController.php:457 | Subtract `in_clearance` (match get_summary) |
| P2 | Audit log | `cleanup_expired()` never bound to its scheduled cron — retention setting is a no-op, table grows unbounded | admin | src/Services/AuditLogService.php:266 | Add init() binding `add_action(CLEANUP_HOOK,…)` |
| P2 | Analytics (Pro) | Export writes to a `Deny from all` dir then hands admin a direct URL — 403 on Apache, download fails | admin | src/Analytics/DataExporter.php:70 | Stream via authenticated handler, not public URL |
| P2 | Storage (Pro) | /upload ignores documented `order_id`, never writes `_wpss_order_id` — order participants get 403 on the file | api | src/API/StorageController.php:353 | Read order_id, verify participation, write meta |
| P2 | Payouts (Pro) | `get_vendors_with_paypal` capped at 500, no paging — vendors past 500 unpayable; ~500 SUM N+1 per load | cross | src/PayPalPayouts/VendorPayoutProfileService.php:97 | Paginate + single GROUP BY aggregate |
| P2 | Payouts (Pro) | No lock/idempotency on batch creation — double-click / two admins pays vendor twice | admin | src/PayPalPayouts/PayoutsBatchService.php:77 | Claim-and-mark orders in a transaction before API call |
| P2 | Commission (Pro) | `flat` rate type offered/saved but applied as a percentage — flat $10 becomes 10% of order | admin | src/TieredCommission/Rules/AbstractCommissionRule.php:184 | Honor rate_type or remove the option |
| P2 | Commission (Pro) | Rules table always prints "Active" (reads is_active from wrong source + precedence bug) | admin | src/TieredCommission/CommissionSettingsRenderer.php:148 | Hydrate/read `is_active` column; fix precedence |
| P2 | Recurring (Pro) | pause/resume documented admin-only but any customer can call; no status guard resurrects cancelled subs | api | src/API/RecurringServiceController.php:233 | Gate to admin; add current-status guards |

## Secondary / unverified

- Services catalog — Favorites list total counts unpublished/deleted favorites, overstating paginated total — src/API/FavoritesController.php:121 *(UNVERIFIED, P3)*
- Orders — `mark_as_paid` not idempotent; re-confirm re-fires `wpss_order_paid` → duplicate order emails (wallet credits are guarded, so downgraded) — src/Integrations/Standalone/StandaloneOrderProvider.php:337 *(CONFIRMED, corrected P3)*
- Orders — vendor_name/customer_name resolve to `''` when the user account is deleted — src/API/OrdersController.php:1503 *(UNVERIFIED, P3)*
- Orders — Manual Order page loads all services + all users unbounded into two selects — src/Admin/Pages/ManualOrderPage.php:124 *(UNVERIFIED, P3)*
- Reviews — REST reviews update user-meta not `wpss_vendor_profiles`, and drop sub-ratings — src/API/ReviewsController.php:860 *(UNVERIFIED, P3)*
- Disputes — refund amount written into evidence JSON, never to `refund_amount` column; no admin surface shows it — src/Services/DisputeService.php:463 *(UNVERIFIED, P3)*
- Vendor levels — seller-level stats hardcode `total_earnings = 0` — src/API/SellerLevelsController.php:226 *(UNVERIFIED, P3)*
- Notifications — frontend list hard-caps at 20, no pagination — src/Integrations/Standalone/StandaloneAccountProvider.php:547 *(UNVERIFIED, P3)*
- Notifications — AJAX mark-read bypasses unread-count cache invalidation → stale admin count up to 1h — src/Frontend/AjaxHandlers.php:2611 *(UNVERIFIED, P3)*
- Notifications — WC notifications endpoint references a non-existent template and is unhooked — wp-sell-services-pro/src/Integrations/WooCommerce/WCAccountProvider.php:152 *(UNVERIFIED, P3)*
- Wallet — ledger "Balance" column never reflects withdrawals; contradicts available balance on same screen — src/API/EarningsController.php:393 *(UNVERIFIED, P3)*
- Auth — `_wpss_vendor_status` is a write-only orphan key; 4 readers, canonical status lives in profiles table — src/API/AuthController.php:340 *(UNVERIFIED, P3)*
- Favorites — dashboard favorites loads unbounded (`posts_per_page=-1`) and int-casts decimal prices — templates/dashboard/sections/favorites.php:37 *(UNVERIFIED, P3)*
- Wizard — Save & Continue advances even when the AJAX save returns error — src/Admin/Pages/SetupWizardPage.php:928 *(UNVERIFIED, P3)*
- Wizard — Offline "Instructions" textarea saves to gateway `description`, not buyer-facing `instructions` — src/Admin/Pages/SetupWizardPage.php:192 *(UNVERIFIED, P3)*
- Payouts (Pro) — admin account list shows blank vendor name for deleted users (REST vs renderer disagree) — src/API/StripeConnectController.php:433 *(UNVERIFIED, P3)*
- Subscriptions (Pro) — service-limit upgrade link uses legacy `wpss_dashboard_page` option; link vanishes on modern installs — src/VendorSubscriptions/SubscriptionManager.php:208 *(UNVERIFIED, P3)*
- Messaging — dashboard unread badge runs unindexable `JSON_CONTAINS` full-table scan, no LIMIT, on every load — src/Database/Repositories/ConversationRepository.php:172 *(UNVERIFIED, P3)*

## Coverage matrix

| Cluster | Audited | Notable coverage gaps |
|---|---|---|
| services-catalog | Yes | Static trace only; no confirmation a shipped JS/mobile client actually calls the cart/checkout REST endpoint (affects real-world blast radius of the cart-wipe P2); no 2000-row big-site query-plan run |
| orders-checkout | Yes | New-order confirm path's gateway-side amount re-validation (StripeGateway/PayPalGateway out of scope) not verified; StandaloneCheckoutProvider web pay path not read end-to-end; no runtime repro |
| deliveries-extensions-proposals | Yes | Did not browser-confirm whether the web buyer dashboard uses the broken REST reader or a separate AJAX path (blast radius of proposals P1); wpss_deliveries surface not traced; latent no-pagination on >50 proposals not filed |
| reviews | Yes | No runtime repro; Pro repo has no review-hook consumers; vote-cleanup cron and RateLimiter internals not deeply audited |
| disputes | Yes | PHP-8 array-as-object behavior reasoned, not observed at runtime; orphaned dispute-view.php has its own latent model-shape/resolution-key drift to reconcile before wiring |
| vendor-profile-portfolio-levels | Yes | No live run; PortfolioController create_item media/service_id ownership not confirmed exploitable; wpss_vendor_profiles / wpss_portfolio_items index coverage unverified |
| notifications-realtime | Yes | WCAccountProvider reachability in live WC My Account unconfirmed (likely dead code → F5 P3); UnifiedDashboard shortcode notifications path (out of scope) not checked; ~660 lines of NotificationService not read |
| wallet-earnings-withdrawals-tipping | Yes | Static only; whether a Pro WalletManager overrides the free ledger (could mask ledger-balance P3) not checked; CommissionService math trusted |
| auth-signup-favorites-account | Yes | No live install; API vendor dead-end + Pro subscription-skip inferred from source; myaccount WC-endpoint templates and profile-save AJAX not deep-audited |
| admin-settings-wizard-auditlog | Yes | No runtime; not every Settings.php sanitize callback audited; Pro settings renderers not covered; minor wizard commission clamp inconsistency (0–100 vs field max 50) noted |
| pro-license-whitelabel-analytics-storage | Yes | Export breakage inferred (Apache high-confidence, nginx ignores .htaccess); S3/GCS/DO driver credential internals and analytics JS empty/error states not deep-audited |
| pro-payments-stripe-paypal-payouts | Yes | Live PayPal/Stripe round-trips and sync_batch_status multi-item matching not exercised; payout admin JS empty/error/loading states not read |
| pro-subscriptions-recurring-commission | Yes | Recurring "no payment method" P1 reasoned, not live Stripe-tested (0.6 conf); initial recurring-order possible double-charge not traced; RecurringWebhookHandler internals out of scope |
| rest-ajax-contract-sweep | Yes | Full per-endpoint LIMIT/COUNT(*) audit of all 139 REST callbacks not completed; no runtime; Pro payment-webhook signature-verification depth not audited |

Cross-cutting note: **no cluster performed live/browser/DB reproduction** — every finding is source-trace verified. The two highest-value confirmations to promote to a live repro before shipping fixes are the recurring-Stripe P1 (test-mode purchase) and the PayPal-payout P1 (multi-run batch), since both are money-movement and both carry residual runtime uncertainty.

## Recommended fix order

1. **Stop the bleeding — money-loss P1s first (this week):** PayPal batch never marks `paid_out` (repeated payouts), withdrawal-reject double-pay, and payment-confirm amount/ownership bypass. These are active financial-loss/fraud paths. Add the batch idempotency P2 and no-lock P2 in the same pass since they share the payout code.
2. **Restore broken revenue features (P1):** recurring-Stripe payment-method attachment, plan `commission_override` application, and the commission `flat`-as-percentage P2 — advertised monetization that currently collects the wrong amount or nothing. Verify the recurring fix with a live Stripe test-mode purchase.
3. **Fix the vendor-identity class in one sweep:** replace every raw `_wpss_is_vendor` meta check with `wpss_is_vendor()` — Portfolio (P1), directory list, /me, /me/vacation, seller-level (all P2). One helper, five call sites; also retire the `_wpss_vendor_status` orphan and route API vendor registration through `VendorService` (auth P1).
4. **Onboarding & broken-wiring correctness:** wizard PayPal keys (P1), audit-log cron binding, cart-checkout delete-after-confirm, proposal field-name drift (P1 + admin P2 + nested-route P2) — consolidate proposals through one shared formatter/service.
5. **Data-fidelity & moderation trust:** tips `reference_type`/`meta` fixes, review-moderation rating recompute + REST moderation-toggle honoring, SellerCard/Featured key fixes, bulk-reject state, dispute timeline array access.
6. **Ship or gate half-built surfaces:** wire the orphaned dispute UI and standalone mark-read template, and either build realtime consumers or hide the realtime settings; analytics-export authenticated streaming.
7. **Sweep the P3/UNVERIFIED backlog** (big-site pagination, cache invalidation, deleted-user name fallbacks, JSON_CONTAINS badge scan) as a scheduled hardening pass — none are urgent, but the >500-vendor payout cap and the messaging-badge scan should be re-checked against a seeded 2000+ dataset before the next release is called big-site-ready.

Fix strategy across all of the above: these are overwhelmingly **duplicated-path** bugs where one entry point was fixed and its twin was not. Prefer consolidating each pair onto a single shared service (proposals formatter, review create/rating, vendor-identity helper, withdrawable-balance calc, commission-rate resolution) over patching each call site — otherwise the same drift recurs at the next entry point added.