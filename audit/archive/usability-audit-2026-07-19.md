# WP Sell Services — Usability + Template-Flow Audit — 2026-07-19

## Executive summary

Overall UX/template health is **fair with two revenue-critical holes in Pro**. Across 8 audit directions, static source tracing surfaced **17 CONFIRMED defects that reach the user** (2 P1, 13 P2, 2 CONFIRMED-but-downgraded-to-P3) plus 8 P3/unverified items. Nothing crashes or corrupts data, but the free plugin leaks buyer content silently and the Pro subscription flow leaks money.

**Confirmed P1: 2. Confirmed P2: 13.**

Dominant themes, in order of weight:

1. **Billing integrity (Pro) — the only P1s.** Vendors get "Active" paid subscriptions with **no card ever captured** (Stripe sub created `incomplete`, auto-expires ~24h, free access meanwhile), a **paid plan missing a Stripe Price ID silently routes to the free/manual path**, and plan **switching double-bills** (new Stripe sub created without cancelling the old). Three defects, one root cause: the subscribe path never reconciles local "active" state with real Stripe payment state.
2. **Template-contract drift** — readers keyed on strings/meta the writers never produce: `_wpss_budget`/`_wpss_deadline` vs `_wpss_budget_min/max/_wpss_expires_at` (requests cards blank), `case 'delivered'` vs the `pending_approval` status actually set, the `'create' !== section` header guard that's always false ("Create Service" while editing), and `dispute_reason` never passed to the dispute email.
3. **Missing pagination / list-cap states** — buyer Orders, vendor Services, and buyer Requests all hard-cap at 20 rows while printing the true uncapped total, with **no pager**, on a dashboard whose sibling Sales screen *does* paginate (internal inconsistency).
4. **Dead / misleading affordances** — two admin order buttons that only toast "not implemented", public portfolio cards that fake clickability but are inert and keyboard-unreachable, and a Notifications feature the Profile page promises but the dashboard never surfaces.
5. **Messaging** — a composer locked on 7 legitimate active statuses (late, disputes, cancellation, pending-approval), and an unindexed `JSON_CONTAINS` + whole-table `GROUP BY` that degrades the Messages tab at scale.

Two clusters (**disputes-templates-flow**, **admin-screens-usability**) came back with placeholder `"test"` coverage notes — effectively **not audited**. No cluster received browser/runtime verification; all findings are source-level cross-reads with file:line evidence.

## Confirmed P1/P2

| Severity | Area | What the user sees | Category | file:line | Fix |
|---|---|---|---|---|---|
| **P1** | Pro / vendor subscription | Clicks "Subscribe" on $50/mo, gets green **Active** badge + Next-Billing date, but no card requested and nothing charged; Stripe sub is `incomplete`, auto-expires ~24h, vendor keeps paid access free | missing-cta | `src/VendorSubscriptions/SubscriptionBillingHandler.php:444` | Create a Stripe Checkout Session (`mode=subscription`) / SetupIntent + Elements, redirect to it; only persist local row `active` after Stripe reports active/trialing — stop hard-coding `'active'` in `VendorSubscriptionService::subscribe()` |
| **P1** | Pro / plan config | Paid plan (price>0) with a blank Stripe Price ID still shows "$50/month" + Subscribe; vendor clicks, marked Active, pays nothing, no warning | data-mismatch | `src/API/SubscriptionPlanController.php:434` | Reject the free/manual branch when `price>0 && empty(stripe_price_id)` with a clear WP_Error; block saving a paid plan without a Price ID in the settings renderer |
| **P2** | Pro / plan switch | "Upgrade"/"Downgrade" creates a new Stripe sub without cancelling the old one → billed for both plans each cycle | missing-state | `src/VendorSubscriptions/SubscriptionBillingHandler.php:154` | Before creating, detect existing active `stripe_subscription_id` and swap the price item on the SAME sub (or cancel old first); never overwrite the id without cancelling the replaced value |
| **P2** | Buyer / requirements | Drag-dropped reference file shows in the list but is never submitted; required field blocks with a "required" error over a visible file, optional field silently ships without the file | js-not-wired | `assets/js/requirements-form.js:58` | Back the upload with `DataTransfer`, write it to the `<input type=file>` on drop/change/remove so FormData + required-validation both see dropped files |
| **P2** | Buyer / dashboard | "Total Orders 45" but only 20 cards, no next/older control; orders 21+ unreachable. Same wall on vendor "My Services" and buyer "Requests" while sibling Sales screen paginates | missing-state | `templates/dashboard/sections/orders.php:75` (also `services.php:32`, `requests.php:29`) | Add limit+offset paging + `wpss-pagination` nav mirroring `sales.php:379-411`; compute total pages from the existing count |
| **P2** | Buyer / requests | Budget range and deadline entered on a request never render on the "Buyer Requests" card — always blank | data-mismatch | `templates/dashboard/sections/requests.php:76` | Read `_wpss_budget_min`/`_wpss_budget_max` (min-max range) + `_wpss_expires_at`; mirror `edit-request.php` legacy fallback |
| **P2** | Vendor / edit service | Editing a service (`?section=create&id=123`) shows page H1 "Create Service" — no sign they're editing | data-mismatch | `src/Frontend/UnifiedDashboard.php:497` | Flip guard to `if ( $id && 'create' === $this->current_section )` → "Update Service" |
| **P2** | All users / notifications | Profile says "In-app notifications still appear regardless of these settings," but there is no Notifications tab/bell/list anywhere; `?section=notifications` dead-ends on "Section Not Available" | missing-nav | `src/Frontend/UnifiedDashboard.php:355` | Add a `notifications` section + template listing `wpss_get_user_notifications()` with a `[data-wpss-notification-count]` badge target; or remove the misleading clause at `profile.php:238` if out of Free scope |
| **P2** | Buyer / vendor profile | Portfolio thumbnails show pointer cursor + are Tab-focusable but do nothing on click/Enter; the only real "View Project" link is `tabindex=-1` (keyboard/touch unreachable) | dead-template | `templates/partials/vendor-portfolio.php:207` | Wire the already-built `$lightbox_items` lightbox (mirror dashboard portfolio) OR drop the false affordance: wrap card in `<a>` when a URL exists, remove `cursor:pointer`/`tabindex=0`, and remove `tabindex=-1` from the link |
| **P2** | Buyer / vendor profile | "View all reviews" loads starting at #11, silently skipping reviews #6–#10; a vendor with exactly 6 reviews has #6 permanently unreachable | broken-nav | `templates/vendor/profile.php:448` | Align initial `LIMIT 5` with load-more `per_page=10` (make initial 10), or set explicit `data-page`/`data-per-page` and pass matching `per_page=5` |
| **P2** | Buyer + seller / messaging | Composer replaced by a lock + "Messaging is not available for this order status" on Accepted, Requirements Submitted, Pending Approval, On Hold, Late, Cancellation Requested, Disputed — exactly when they most need to talk | missing-cta | `templates/order/conversation.php:114` | Widen to `! in_array($order->status, ['completed','cancelled','refunded','rejected'], true)`, or expose `apply_filters('wpss_conversation_can_message', …)` |
| **P2** | Buyer + vendor / messages perf | Messages tab + unread badge progressively slow / can time out on a busy marketplace | usability | `src/Database/Repositories/ConversationRepository.php:169` | Replace `JSON_CONTAINS(participants,…)` on an unindexed longtext with an indexed participants join table / stored owner_id/vendor_id; constrain the last-message subquery to the user's conversation ids instead of grouping the whole messages table; add `KEY idx_last_message` |
| **P2** | Admin / order metabox | "Extend Deadline" (only quick action on in-progress orders) and "Resend Notifications" (every order) just flash "not yet implemented" | missing-cta | `src/Admin/Metaboxes/OrderMetabox.php:829` | Implement the AJAX handlers (bump `delivery_deadline`; re-fire order emails), or stop rendering the buttons (drop `'extend'` from `get_available_actions()`; remove the Resend button) |
| **P2** | Vendor/admin/site-owner / email | "A dispute has been opened" email (buyer, vendor, **admin**) always omits the buyer's reason — the mediating admin gets zero context | data-mismatch | `templates/emails/dispute-opened.php:113` | In `EmailService::send_dispute_opened()`, resolve the latest dispute by order_id and add `'dispute_reason'` to `$base_vars` for all three sends |

## Secondary / unverified (P3 + unverified)

- **Admin / order metabox (CONFIRMED, downgraded P3)** — delivered-awaiting-buyer orders show only "Cancel" quick action; Force Complete / Request Revision missing because actions key on `'delivered'` but `DeliveryService::submit()` sets `pending_approval` (Change-Status dropdown is a working fallback) — `src/Admin/Metaboxes/OrderMetabox.php:953`.
- **Vendor / public profile (CONFIRMED, downgraded P3)** — non-featured portfolio items show in the dashboard but the entire public Portfolio section vanishes when nothing is featured; no hint that "Feature" is what publishes — `templates/vendor/profile.php:363`.
- **Buyer / requirements (unverified)** — standalone requirements page submit button has no spinner and never disables (double-submit risk); inline order-view form does; prefill also reads by numeric index vs question-text keying — `templates/order/order-requirements.php:281`.
- **Dev-facing (unverified)** — `TemplateLoader` switches to non-existent `order-delivery.php`/`order-review.php` (fall back to order-view) and `templates/order/requirements-form.php` (540 lines) is included by nothing — `src/Frontend/TemplateLoader.php:389`.
- **Site owner / overrides (unverified)** — `templates/myaccount/*` are never rendered, so child-theme overrides do nothing, yet PreflightCommand reports one as the live "Dashboard" template — `templates/myaccount/vendor-dashboard.php:1`.
- **Buyer / edit request (unverified)** — page H1 reads generic "Dashboard" (missing from the title map) while the card below says "Edit Request" — `src/Frontend/UnifiedDashboard.php:582`.
- **Buyer + vendor / messaging (unverified)** — dashboard thread has no live polling, full-page reloads on send (loses scroll), and no attachments, unlike the order-page thread — `templates/dashboard/sections/messages.php:162`.
- **Both parties / email (unverified)** — order-cancelled email's Reason block is always empty (auto-cancel/admin-forced give no explanation) — `templates/emails/order-cancelled.php:71`.
- **Text-mail recipients (unverified)** — emails ship HTML-only, no plain-text part; `templates/emails/plain/` (14 files, drifted, referenced by nothing) is dead — `templates/emails/plain/new-order.php:1`.
- **Vendor/admin / email (unverified)** — moderation + dispute-escalated emails render with no heading band (open straight into "Hi {name},") unlike every order email — `templates/emails/email-header.php:137`.

## Coverage matrix

| Cluster | Status | Gaps reported |
|---|---|---|
| buyer-order-lifecycle-templates | Traced end-to-end (confirmation→requirements→delivery→accept/revision→review) + admin metabox | No browser repro; `conversation.php` empty/error states and milestone/extension/tip sub-order flows only skimmed; Pro order hooks + gateway "Pay Now" return path not read; responsive/dark-mode not visually checked |
| account-dashboard-templates | Live flow covered (shell, nav, orders/services/requests/favorites/messages/create/edit-request, JS↔markup contracts) | `earnings.php`/`sales.php` internals, `profile.php` avatar/cover/portfolio upload, `portfolio.php`/`create-request.php` field contracts not deep-verified; no browser run |
| vendor-onboarding-profile-templates | Become-vendor, create/edit wizard (all 6 steps), profile + portfolio sections, public profile + portfolio partial | `service-edit.js` (wp-admin metabox), `vendor-card.php`, and all responsive/CSS not audited; code-traced only |
| messaging-flow | ConversationsController (7 routes), service/repo, MediaController, both rendered surfaces | No browser run; 10s poll + read-receipt not visually verified; two dead-code latents noted but not reported (`create_conversation()` object-format participants, `close()` has zero callers) |
| email-templates-flow | All 37 HTML templates + header/footer + every sender; verified 9 trigger hooks fire and deep-links resolve | No live mail-client render / DB-seeded send; NotificationService in-app payloads not deep-audited |
| pro-frontend-usability | Subscription picker, recurring list, Stripe Connect rail, analytics, white-label — all template+data-source+JS+REST | Free-repo base RestController + section-include mechanism taken on faith from docblocks; recurring-service INITIAL purchase (card capture in free checkout) not traced; PayPal Payouts + admin settings out of scope; no browser run |
| **disputes-templates-flow** | **NOT AUDITED** — coverage note is placeholder `"test"` | Entire cluster uncovered |
| **admin-screens-usability** | **NOT AUDITED** — coverage note is placeholder `"test"` | Entire cluster uncovered (admin order defects here surfaced only via the buyer-lifecycle pass) |

**Global gap:** zero browser/runtime verification on any cluster; no responsive (≤480px) or dark-mode visual pass. All findings are static source cross-reads.

## Recommended fix order

**1. Billing integrity (Pro) — ship first, do together.** These three share one root cause (local `active` state never reconciled with Stripe payment state) and should be one coordinated fix in the subscribe path:
- `SubscriptionPlanController.php:434` — reject paid-plan-without-Price-ID before the free/manual branch (cheapest, closes the "free access" hole immediately).
- `SubscriptionBillingHandler.php:444` — real card capture via Checkout Session/SetupIntent; persist `active` only on Stripe active/trialing.
- `SubscriptionBillingHandler.php:154` — cancel/swap the existing Stripe sub on plan switch.
Also block saving a paid plan with a blank Stripe Price ID in the settings renderer so the config mistake can't recur.

**2. Silent buyer/vendor data loss (Free) — high user-visible impact, low effort.**
- `requirements-form.js:58` — DataTransfer fix (buyer's very first post-payment step loses files).
- Pagination: **one shared fix** across `orders.php:75`, `services.php:32`, `requests.php:29` — lift the `wpss-pagination` pattern already working in `sales.php:379-411` into a shared helper and apply to all three (also bound `favorites.php` `-1` and `portfolio.php` 50/100 noted in the finding).
- `requests.php:76` — read the correct min/max/expires_at meta keys.

**3. Wrong-string / wrong-key contract drift — grep-sweep the class, not just the line.**
- `UnifiedDashboard.php:497` (edit header) — while there, add the missing `edit-request` title (P3 finding at :582) since it's the same title-map file.
- `OrderMetabox.php:953` (delivered vs `pending_approval`) — audit every admin surface that branches on `'delivered'` for the same drift.
- `dispute-opened.php:113` + `order-cancelled.php:71` — both email senders drop a stored reason; fix in `EmailService` together.

**4. Dead / misleading affordances.**
- `OrderMetabox.php:829` — implement or remove the two dead admin buttons.
- `vendor-portfolio.php:207` — either wire the built-but-unused lightbox or drop the false clickability + fix the `tabindex=-1` keyboard trap (WCAG 2.1.1).
- `UnifiedDashboard.php:355` — add a Notifications surface or remove the Profile promise; this also revives the dead `[data-wpss-notification-count]` realtime badge.

**5. Messaging.**
- `conversation.php:114` — widen the can-message whitelist (quick, high-value).
- `ConversationRepository.php:169` — the schema/index fix (participants join table); larger, schedule deliberately, but it's the only P2 that worsens with growth.

**6. P3 cleanup / tech-debt — batch when touching adjacent code:** review-pagination alignment (`profile.php:448`), dead template routes + orphaned `requirements-form.php` (`TemplateLoader.php:389`), dead `myaccount/*` + its false Preflight pass, dashboard-vs-order message UI unification, email heading bands, and the plain-text email decision (wire multipart or delete the 14 dead files).

**Before sign-off:** the **disputes-templates-flow** and **admin-screens-usability** clusters were never actually audited (placeholder coverage) — re-run those two directions, and add at least one browser/responsive pass, before treating this audit as complete.