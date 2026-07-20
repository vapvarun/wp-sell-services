# Duplicate / divergent MONEY + ORDER flows

Audit 2026-07-20. Same bug class that broke checkout and split the commission
math: **one customer flow, many implementations, copies drift apart.**
See the "ONE FLOW, ONE IMPLEMENTATION" rule in `CLAUDE.md`.

**9 duplicated flow families found.** Counts: order creation ×15, payment
verification / mark-as-paid ×18, wallet credit ×9, refund ×8, status transition
×6, wallet balance formula ×2, withdrawal request ×2, offline-order REST ×2,
hand-rolled fee math ×2 remaining.

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

## 4. HIGH — 4 order-creation paths persist NO commission fields

15 distinct inserts into `wpss_orders`. Correct (delegate to
`CommissionService::compute_breakdown()`):
`StandaloneOrderProvider.php:117` ✅, pro `WCOrderProvider.php:409` ✅.

Missing commission entirely:

| Path | Problem |
|---|---|
| pro `EDD/EDDOrderProvider.php:580` | no `platform_fee` / `vendor_earnings` |
| pro `RecurringServices/RecurringOrderFactory.php:95` | none, **and** writes `payment_status=paid` unverified |
| pro `API/PaymentController.php:341` | none |
| `Admin/Pages/ManualOrderPage.php:711` | hand-rolled fee math at `:609` |

**Customer impact:** EDD purchases, subscription renewals and Pro offline orders
record `platform_fee`/`vendor_earnings` as NULL → **the vendor is never paid for
those orders** and platform revenue under-counts silently.

**Fix:** route all 4 through `compute_breakdown()`. (ManualOrderPage keeps its
admin-chosen rate but should still persist via the shared helper.)

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
