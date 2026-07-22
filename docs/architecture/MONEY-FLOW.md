# Money flow

How money moves through this plugin, and the rules that keep it correct. Read
this before touching anything that charges, credits, refunds, or pays out.

Live plan and open work: `audit/MONEY-FLOW-PLAN.md`. This document describes the
rules; that one tracks the tasks. If they disagree, the plan is newer.

---

## 1. The shape

```
buyer pays FULL amount to the platform
  └─ order completion → vendor credited in the wallet ledger
       └─ clearance window (default 14 days)
            └─ scheduled run (weekly / bi-weekly / monthly, above a threshold)
                 └─ payout batch
                      ├─ manual  → CSV → admin pays offline → MARK PAID
                      ├─ stripe  → transfers → MARK PAID on success
                      └─ paypal  → batch payout → MARK PAID on success
                           └─ MARK PAID → ledger debit
```

The platform holds the money until it matures. That is the whole point: a
refund inside the clearance window finds the money still unpaid, so **nothing
is ever clawed back from a vendor**. Clawing money back out of someone's bank
account is the failure mode this design exists to avoid — it fails often, and
when it fails the platform eats the loss.

---

## 2. Non-negotiable rules

### 2.1 One balance authority

`wpss_get_ledger_balance()` is the ONLY wallet-balance formula: a `SUM` over
`wpss_wallet_transactions`. Never read a cached balance column, never re-derive
it inline. Withdrawable balance comes from
`EarningsService::get_summary()['available_balance']`
(= ledger − pending withdrawals − in-clearance), and the withdrawal gate takes a
row lock before checking it.

### 2.2 Never force a payment rail on the site owner

We do not know what a site owner has. Stripe Connect is not available in every
country; many owners pay by bank transfer. **Manual/CSV is the default rail and
is first-class** — a site with no gateway configured at all must have a complete
payout flow. Stripe and PayPal are options layered on top, never prerequisites.

A feature that only works on our own preferred stack is broken for most of the
install base.

### 2.3 Payouts end in exactly one terminal step

Every payout finishes at **mark paid** — written by a rail on success, or by the
admin by hand. That single step writes the ledger debit. No rail keeps its own
bookkeeping, or the two drift and the ledger stops being the truth.

Mark-paid must be **idempotent**: marking twice debits once.

### 2.4 Export is not payment

Exporting a payout CSV must never change status. An export that auto-marked
would lie the moment a bank transfer failed. Export and mark-paid are separate
deliberate acts.

### 2.5 Gateways are rails, not authorities

A gateway reports one thing: whether money moved. Every amount, split,
commission and log is computed and stored by this plugin. Never read a price,
total or currency back out of a gateway or an e-commerce platform — read our own
package price and the site's BASE currency. Multi-currency plugins are expected
to hand us base-currency values, exactly as they do for WooCommerce.

### 2.6 Amounts are stored once, in base currency

One value, one currency, on the order. Display conversion is a display concern.

### 2.7 Refund sizing has one formula

`wpss_get_refund_vendor_share()`. A refund returns some proportion of what the
buyer paid; the vendor returns the same proportion of what they earned. A full
refund is just the case where that proportion is 1 — there is no separate
partial path to drift.

A partial larger than the order total is clamped to the total. Otherwise the
reversal claws back more than the vendor ever earned.

### 2.8 refunded_amount is a sentinel — resolve it, never read it raw

`wpss_orders.refunded_amount` NULL means "fully refunded"; a number means "this
much". The money layer depends on that. **Display code must call
`wpss_get_order_refunded_amount()`**, never the column — reading it raw and
testing `> 0` silently drops every full refund, which is how buyers came to see
"Refunded" with no amount next to it.

### 2.9 Write the amount, then transition — and undo it if refused

The refund handlers read `refunded_amount` off the order *during* the status
hook, so it must be written first. If the transition is then refused, the write
must be rolled back, or the order claims a refund that never happened.
`OrderService::apply_refund_status()` owns this ordering. Nothing else may write
the column and call `update_status()` itself.

### 2.10 Idempotency everywhere money moves

Keyed on `(reference_type, reference_id)` in the ledger, and on an
Idempotency-Key at the gateway. A retried cron, a double-clicked button and a
replayed webhook must each produce exactly one row and one transfer.

---

## 3. Where credit happens (a common misreading)

Payment does **not** credit the vendor. `vendor_earnings` is written on the
order at payment, but the ledger row is created at **completion**. A paid
in-flight order therefore has `vendor_earnings = 45.00` and **zero ledger rows**.

Any code that reverses earnings must not assume a credit exists just because
`vendor_earnings` is set, or it will debit a vendor who was never paid.

---

## 4. Stripe Connect

Connect is currently wired as a **charge-time split** (`transfer_data` on the
PaymentIntent), which pays the vendor at charge and bypasses clearance entirely.
That contradicts §1 and is scheduled to move to scheduled transfers — see
`audit/MONEY-FLOW-PLAN.md` T7/T8, including the migration hazard for orders that
were already split.

---

## 5. Testing money

Reading the code is not verification. Every money defect found in this plugin so
far was found by running the flow, not by reading it — including buyers never
being refunded, and every admin confirm dialog being unclickable while 16/16
automated checks passed.

Verify against the rail's own API, not just our database: for order 112 that
meant confirming `pi_…` and `re_…` at Stripe, not trusting our `transaction_id`
column.
