# Money flow

How money moves through this plugin, and the rules that keep it correct. Read
this before touching anything that charges, credits, refunds, or pays out.

Live plan and open work: `audit/archive/MONEY-FLOW-PLAN.md`. This document describes the
rules; that one tracks the tasks. If they disagree, the plan is newer.

---

## 1. The shape

```
buyer pays FULL amount to the platform
  └─ order completion → vendor credited in the wallet ledger
       └─ clearance window (OPTIONAL, default 0 = no hold)
            └─ scheduled run (weekly / bi-weekly / monthly, above a threshold)
                 └─ payout batch
                      ├─ manual  → CSV → admin pays offline → MARK PAID
                      ├─ stripe  → transfers → MARK PAID on success
                      └─ paypal  → batch payout → MARK PAID on success
                           └─ MARK PAID → ledger debit
```

The platform holds the money until it is paid out. **The clearance window is
the owner's choice, not ours** (owner decision 2026-07-23): it defaults to
**0 — pay out as soon as an order completes** — and an owner who wants a refund
window sets 7 / 14 / 30 in Settings → Payouts → Withdrawal Settings. How long to
sit on a vendor's money is business policy, and many marketplaces pay
immediately.

A hold, when the owner sets one, means a refund arriving inside the window
finds the money still unpaid, so nothing has to be clawed back. **With no hold
we do not eat the loss either**, because the ledger records it honestly: a
refund on already-withdrawn money drives the vendor's balance NEGATIVE and
future earnings pay it down automatically (see 2.1 — the balance is deliberately
not clamped at zero). Clearance prevents that conversation; the ledger survives
it. Both are correct; the owner picks.

`EarningsService::get_clearance_days()` is the single accessor — read the hold
through it, never the raw option, so the settings field, the activator and
runtime can never disagree.

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

### 2.7 Refund sizing has one formula, and refunds accumulate

`wpss_get_refund_vendor_share()`. A refund returns some proportion of what the
buyer still had paid; the vendor returns the same proportion of what they still
hold. A full refund is just the case where that proportion is 1 — there is no
separate partial path to drift.

Refunds accumulate: `refunded_amount` is a running total and every call to
`apply_refund_status()` adds one event to it. A partial keeps `payment_status`
`paid`; only reaching the total closes the payment. Each event writes its own
reversal row (`order_reversal`, reference_id = order id; the first keeps
`reference_type` `order`, later ones `order_refund_2`, `order_refund_3`) sized
against what was left before it. A partial larger than the remainder is
clamped to it. Otherwise the reversal claws back more than the vendor holds.

### 2.7a Commission is locked at payment

`commission_rate`, `platform_fee` and `vendor_earnings` are written when the
order is created and never re-resolved. `CommissionService::calculate()` reads
them back at completion and only scales them by the share the buyer kept after
refunds; a rate change, a new tiered rule or a vendor override between payment
and completion touches new orders only. Rows from before 1.2.2 carry no rate and
are computed then.

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

Enforced by the database: the ledger is UNIQUE on
`(reference_type, reference_id, type)` and reviews on `(order_id, review_type)`.
Every Free ledger write goes through `wpss_insert_ledger_row()`, which reports a
duplicate-key refusal as success (the row exists) and recomputes the vendor
profile's cached `total_earnings` / `net_earnings` from the ledger, so the
profile row, the Earnings page and the ledger say one number. `balance_after`
is still written but displayed nowhere. At the gateway, an Idempotency-Key. A
retried cron, a double-clicked button and a replayed webhook must each produce
exactly one row and one transfer.

### 2.11 Rail-side refunds enter through one seam

A refund that happened AT the gateway (Stripe / PayPal / Razorpay dashboard,
a Woo refund) reaches us as a webhook. Every webhook fires
`wpss_gateway_refund_received( $gateway, $transaction_id, $amount, $context )`
and nothing else; `OrderWorkflowManager::handle_gateway_refund()` is the only
listener. It resolves every order paid with that transaction (a cart stamps
one id on several orders), splits an unnamed amount by order total, skips a
refund id it has already seen, and records each share through
`apply_refund_status( …, settled_at_rail = true )` so the vendor share is
reversed and the gateway is never called back. No gateway applies a refund
inline.

Store-side refunds run the other way: `attempt_payment_refund()` first offers
`wpss_pre_process_gateway_refund` (a rail that owns the money settles it and
returns the seam-3 array), then calls the order's gateway. Every
`process_refund()` returns `{success, transaction_id, message, manual}`. A
gateway that cannot move money (offline, test) returns `manual = true`: the
order gets `_wpss_refund_pending` = amount, the admin sees a notice and a
"Mark refund sent" button on the order, and nothing is ever logged as a
successful refund. `OrderWorkflowManager::get_last_refund_result()` hands the
real gateway outcome back to whoever moved the status.

---

## 3. Where credit happens (a common misreading)

**There are two answers, and which one applies depends on the row type.** This
section used to give only the first, which is why it contradicted the milestone
documentation. Both are correct; they are different code paths.

### 3.1 Real orders — credit at COMPLETION

For an order a buyer placed against a service (`platform` = `standalone`,
`woocommerce`, `request`, …):

Payment does **not** credit the vendor. `vendor_earnings` is written on the
order at payment, but the ledger row is created at **completion**. A paid
in-flight order therefore has `vendor_earnings = 45.00` and **zero ledger rows**.

Any code that reverses earnings must not assume a credit exists just because
`vendor_earnings` is set, or it will debit a vendor who was never paid.

### 3.2 Sub-orders — credit at PAYMENT

**Tips, milestone phases and paid extensions credit the vendor the moment they
are paid.** There is no completion step to wait for and no escrow.

- `MilestoneService::credit_milestone_on_payment_complete()`,
  `TippingService` and `ExtensionOrderService` all bind `wpss_order_paid` and
  write the wallet ledger row inside that handler, in one transaction with the
  status flip to `in_progress`.
- Approving a phase moves **no money**. Approval is a delivery sign-off; the
  payment already settled. The ledger entry type is `milestone` / `tip` /
  `extension`, `reference_type = 'order'`, `reference_id` = the sub-order id.
- The credit is idempotent on `(reference_type, reference_id)` — a replayed
  webhook or double-clicked button credits once.

**Why the split is deliberate:** a sub-order has no independent lifecycle. It is
paid, worked, and signed off inside its parent. Deferring its credit to
"completion" would mean deferring it to the parent's completion, which can be
weeks later and may never come — a tip would sit uncredited until the whole
project finished.

**The consequence to be honest about: sub-orders are not escrowed.** Money paid
on a phase is in the vendor's wallet before the work is delivered. A refund
after the fact is a *reversal* against the ledger (see §2.7), which can drive
the balance negative — it is not a release of money the platform was still
holding. Do not describe milestones as escrow, in docs or in marketing.

### 3.3 The rule for anyone touching this

Before writing or reversing a credit, branch on `platform`:

| `platform` | Credited at | Reversal target |
|---|---|---|
| `tip`, `milestone`, `extension` | **Payment** | A ledger row that already exists |
| everything else | **Completion** | A ledger row that may not exist yet |

Pro's `WalletManager` encodes exactly this: it early-returns for the three
sub-order platforms so completion cannot credit them a second time.

Cross-references that must agree with this section:
[Milestone Contracts](../website/order-management/milestones-wpss.md),
[Sub-Order Pattern](SUB_ORDER_PATTERN.md).

---

## 4. Stripe Connect

Connect is currently wired as a **charge-time split** (`transfer_data` on the
PaymentIntent), which pays the vendor at charge and bypasses clearance entirely.
That contradicts §1 and is scheduled to move to scheduled transfers — see
`audit/archive/MONEY-FLOW-PLAN.md` T7/T8, including the migration hazard for orders that
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
