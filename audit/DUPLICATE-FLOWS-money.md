# Duplicate / divergent MONEY + ORDER flows

Audit 2026-07-20. Same bug class that broke checkout and split the commission
math: **one customer flow, many implementations, copies drift apart.**
See the "ONE FLOW, ONE IMPLEMENTATION" rule in `CLAUDE.md`.

**9 duplicated flow families found.** Counts: order creation ×15, payment
verification / mark-as-paid ×18, wallet credit ×9, refund ×8, status transition
×6, wallet balance formula ×2, withdrawal request ×2, offline-order REST ×2,
hand-rolled fee math ×2 remaining.

---

## Re-run 2026-08-26 (1.7.0 sweep)

Four more families of the same bug class, all found and fixed this cycle. Logged
here because this file is the standing record and CLAUDE.md requires the sweep
before a release.

| Flow | Copies found | What it cost | Resolution |
|---|---|---|---|
| "Is this caller a vendor?" | **8** — one in free's EarningsController and PortfolioController, four in Pro, plus the canonical helper and an ownership check wearing the same name | Three different denial codes for one condition, reads login-only while writes were gated, and `wpss_rest_require_vendor()` never called `wpss_vendor_status_block()` — so a **suspended vendor kept full access to every money route** | One `RestController::check_vendor_permissions()` delegating to the canonical helper. Six copies deleted. `tests/test-money-route-gates.php` fails if the surface drifts again |
| Switchable notification types | **2** — the same 23-entry literal written once to render and once to sanitize | The copies drifted: `notify_moderation` was gated on by EmailService and written by neither, so moderation emails could not be switched off | One `Settings::get_notification_types()`. `tests/test-notification-toggle-contract.php` asserts both directions |
| `PaymentGatewayInterface` | **2** — free's and an identical one in Pro | Razorpay implemented one contract while the other four gateways implemented the other, all registering into the same filter. Both auth and payload shape depended on load order | Pro's deleted; Razorpay uses free's |
| `GET /payments/methods` | **2 registrations** — free's authenticated one and a Pro duplicate with `__return_true` | Two permission callbacks and two handlers returning different shapes on one route | Pro's registration and its orphaned handler removed |

**Still open, logged not fixed:** `TeraWalletProvider` and `WooWalletProvider`
both detect and call the same `woo_wallet()` runtime, so a site with TeraWallet
installed sees two dropdown entries that do the same thing. Carded as
#10239808430 — collapsing them changes a stored option value and needs a
read-time alias.

---

## 1. CRITICAL — Vendor wallet balance: two incompatible formulas

9 credit implementations, and **two different definitions of "balance"**:

| Side | Formula | File |
|---|---|---|
| Free | reads the **last row's** `balance_after` | `CommissionService.php:325` |
| Pro | `SUM(CASE type IN withdrawal/debit/dispute_refund …)` | `wp-sell-services-pro/src/Integrations/Wallets/InternalWalletProvider.php:141` |

Other credit sites: `TippingService.php:383`, `ExtensionOrderService.php:357`,
`MilestoneService.php:300`, `OrderWorkflowManager.php:829`.

**Customer impact:** the two disagree the moment a withdrawal or reversal lands.
Displayed balance, withdrawable amount and the ledger permanently diverge →
vendor is over- or under-paid.

**Fix:** one `WalletService` owning balance + credit/debit. Every caller goes
through it. Decide the canonical formula (recommend derived SUM, with
`balance_after` kept only as a denormalised cache written by that one service).

---

## 2. CRITICAL — Refund: 8 implementations that disagree on what they write

| Site | What it does |
|---|---|
| `OrderWorkflowManager.php:917` | sets `payment_status` only — **no earnings reversal** |
| `StandaloneOrderProvider.php:286` | calls the gateway, writes nothing |
| pro `WooCommerce/WCOrderProvider.php:295` | maps refund → status **`cancelled`** |
| pro `SureCart/SureCartOrderProvider.php:227` | maps refund → status **`refunded`** |
| `AjaxHandlers.php:3068`, `DisputeService.php:516` | further variants |
| `StripeGateway.php:1152`, `PayPalGateway.php:1087`, `RazorpayGateway.php:866` | fire actions **nobody listens to** |

**Customer impact:** refunding leaves the vendor **still credited** (only the
`cancelled` path calls `reverse_order_earnings`) → buyer refunded AND vendor
paid, platform pays twice. A Woo refund shows "Cancelled" while a SureCart
refund shows "Refunded" for the same customer action.

**Fix:** one `RefundService::refund(order, amount, reason)` that reverses
earnings, sets one canonical status, and is the only thing gateways/webhooks call.

---

## 3. HIGH — PayPal `pay_order` is missing the guards Stripe already has

Identical twin of the P1 already fixed on Stripe this sprint (`c42a6a8`).

| Path | Ownership check | Idempotency | Amount match |
|---|---|---|---|
| `API/PaymentController.php:493-518` (Stripe) | ✅ | ✅ | ✅ |
| `API/PaymentController.php:558-577` (PayPal) | ❌ | ❌ | ❌ |

Plus 18 total verification call sites across `StripeGateway` (789/963/1033/1066/1095),
`PayPalGateway` (661/924/955/1015/1067), `RazorpayGateway` (675 REST + 805 AJAX duplicate).

**Customer impact:** a buyer can capture a **small PayPal payment against someone
else's larger order** and have it marked paid. This is the exact hole we already
closed on the Stripe twin — we fixed the instance, not the class.

**Fix:** extract the three guards into one shared verifier both paths call.
**Do this one first — it is a live money/security hole with a known-good template.**

---

## 4. ~~HIGH~~ → MOSTLY REFUTED — insert-time NULL commission is NOT "never paid"

**Correction 2026-07-21 (verified empirically).** The headline claim — that a
NULL `platform_fee`/`vendor_earnings` at insert means the vendor is never paid —
is FALSE for any order that reaches `completed` through the normal workflow.

`CommissionService::record()` (hooked to `handle_order_completed`) calls
`calculate()`, which computes the breakdown FRESH from `subtotal + addons_total`
at completion time — it does not read a persisted fee. So insert-time NULL is
irrelevant as long as the order transitions to `completed`.

**Pro `API/PaymentController.php:341` (`offline_submit`) — REFUTED.** Reproduced
on the local DB: an order inserted exactly as `offline_submit` writes it (subtotal
set, fee + earnings NULL, no `platform` column) is credited correctly the moment
it completes (fee 10, earnings 90, wallet 120→210). Offline orders start
`pending_payment` and only complete after an admin marks them paid — an
admin-mediated, low-stakes flow (bank transfer, invoice, or assigning a task to a
vendor/self). Not a bug. No fix applied.

**The real question for the remaining paths is NOT insert-time NULL** — it is
whether the path reaches `handle_order_completed` at all, or short-circuits into a
paid/completed state by another route (in which case `record()` never runs and the
credit is genuinely missed):

| Path | Actual concern to verify |
|---|---|
| pro `EDD/EDDOrderProvider.php:580` | Fixed in pro 503e76f. Re-verify it was a real gap (purchase reaching completion without the hook), not redundant. |
| pro `RecurringServices/RecurringOrderFactory.php:95` | Fixed in pro 503e76f. Genuine risk is `payment_status=paid` unverified + whether a renewal fires the completion hook. |
| pro `API/PaymentController.php:341` (offline) | **REFUTED — not broken.** |
| `Admin/Pages/ManualOrderPage.php:711` | Fixed in de43c1b (uses the commission authority). |

**Lesson:** this item was written from the insert site alone, without tracing the
completion path. The insert-time NULL was real but harmless; the sweep inferred
"never paid" without reproducing. Any remaining work is per-path verification of
the completion trigger, not a blanket "route all 4 through compute_breakdown()."

---

## 5. HIGH — 6 status writers bypass the transition authority

Authority: `OrderService::update_status()` (`OrderService.php:157`) — validates
`can_transition`, fires the pre-change filter, logs a system message, fires hooks.

Bypassers writing status directly: pro `WCOrderProvider.php:513` (raw
`$wpdb->update`), pro `SureCartOrderProvider.php:142`, pro
`FluentCartOrderProvider.php:128`, `OrderRepository.php:350`,
`ManualOrderPage.php:745` (fires hooks manually).

**Customer impact:** Woo/SureCart/FluentCart buyers get illegal transitions with
no validation, **no order-timeline entry** and inconsistent timestamps — their
order history is missing steps that standalone buyers do see.

**Fix:** make `update_status()` the only writer; repositories expose no public
status setter.

---

## Suggested order of work

1. **#3 PayPal guards** — live money/security hole, small, template already exists.
2. **#2 Refund service** — platform currently pays twice on refunds.
3. **#4 commission on the 4 order paths** — vendors unpaid on EDD/renewals.
4. **#1 wallet balance authority** — needs a decision on the canonical formula.
5. **#5 status authority** — largest blast radius, do last with a regression pass.

Remaining hand-rolled fee math to retire: `ManualOrderPage.php:609`,
`TippingService.php:376`.
