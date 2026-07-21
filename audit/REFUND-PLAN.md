# Refund: one flow, free + pro, all three surfaces

**Status:** PLAN — not implemented. Written 2026-07-21 so the work is done once.
**Scope:** free + pro together; admin, vendor and buyer surfaces in the same pass.

---

## 1. What is actually broken (reproduced, not inferred)

Reproduced on the local DB as an **admin** (the normal actor — refunds are
admin-mediated). A completed $100 order, vendor share $90:

| Admin sets status | Vendor ledger | Correct? |
|---|---|---|
| `cancelled` | 210 → 120 (**−90**) | yes |
| `refunded` | 210 → 210 (**0**) | **no** |
| `partially_refunded` | 300 → 300 (**0**) | **no** |

**The buyer side is fine.** `attempt_payment_refund()` skips when
`payment_status` is already `refunded`, and Stripe/PayPal reject a second refund
on the same transaction id. The buyer cannot be refunded twice. That protection
is real — it just guards a different axis than the one that leaks.

**The leak is the vendor side.** On a refund the buyer gets their money back and
the vendor keeps the wallet credit, withdrawable once clearance passes. The
platform pays out on both sides of an order it collected nothing for. Gateway
idempotency cannot catch it: one payout is a Stripe refund, the other is a wallet
withdrawal weeks later, and nothing links them.

### Root cause — one omission, not eight implementations

`Plugin.php` wires both handlers:

```
'wpss_order_status_cancelled' => handle_order_cancelled   // HAS the reversal block
'wpss_order_status_refunded'  => handle_order_refunded    // DOES NOT
```

`handle_order_refunded()` calls `attempt_payment_refund()`, fires
`wpss_order_refunded`, and stops. That is the whole bug.

`partially_refunded` is worse: the status exists
(`ServiceOrder::STATUS_PARTIALLY_REFUNDED`, set by dispute resolution) but is
**wired to no handler at all** — no gateway refund *and* no reversal. The buyer
is told "partially refunded" and nothing happens.

### Corrections to `DUPLICATE-FLOWS-money.md` item 2

- It claimed 8 divergent refund implementations. Five are correct single-purpose
  gateway adapters implementing `PaymentGatewayInterface::process_refund`; one
  cited file (`AjaxHandlers.php:3068` for disputes) is not the dispute entry
  point. The real defect is the single missing block above.
- It never noticed `can_transition()`. A non-admin cannot move a completed order
  to refunded at all — the first reproduction attempt was refused outright. The
  bug is only reachable by an admin or a `wpss_manage_orders` holder.

---

## 2. Decisions (locked by owner 2026-07-21)

**D1 — A refund always reverses the vendor's earnings, and the balance MAY GO
NEGATIVE.** A negative wallet balance is the record of a debt: the vendor was
paid for work that was refunded, and already withdrew it. Because the balance is
a `SUM` over the ledger, future earnings pay the debt down automatically with no
extra machinery. This replaces the current `max(0, …)` clamp, which hid the debt
and let the vendor start earning again as if nothing happened.

**D2 — Partial refunds reverse the vendor's proportional share.** The amount
already exists: `wpss_disputes.refund_amount` is stored, and
`DisputeService::handle_resolution()` already receives `$refund_amount` — it just
drops it on the floor. Plumb it through instead of inventing a new mechanism.

**D3 — Stripe Connect orders still skip the ledger reversal.** Already
implemented (`7c6b1c4`). The vendor's net wallet position on a Connect order is
zero (credit + `connect_transfer` offset), so reversing would create a spurious
debt. The real clawback is `reverse_transfer` on the Stripe refund, which
`build_refund_args()` already sets. **This is the one case where D1 does not
apply**, and it is deliberate.

**D4 — `refunded` means refunded on every platform.** Pro's Woo provider
currently maps Woo `refunded` → wpss `cancelled`
(`WCOrderProvider.php:487`). That is why Woo refunds accidentally reverse
earnings today — via the cancelled handler, through a raw `$wpdb->update` that
bypasses the transition authority. Once `handle_order_refunded` is fixed, the
mapping must be retired so a Woo buyer and a standalone buyer see the same
status for the same event.

---

## 3. Free plugin changes

### 3.1 Extract the reversal so both handlers share it
`handle_order_cancelled()` and `handle_order_refunded()` call one private
`reverse_earnings_for_terminal_status( $order_id, $order, $amount )`.
`reverse_order_earnings()` already exists and is already idempotent on
`(reference_type='order', reference_id)` and already Connect-aware — extend it to
take an optional partial amount rather than writing a second reversal path.

**Do not** add `order_reversal` to `wpss_get_ledger_debit_types()`. That type
stores a **negative** `amount` (`OrderWorkflowManager` writes
`'amount' => -$vendor_earnings`), unlike every other type. Adding it to the debit
list would negate it twice and turn every historical reversal into a credit.
Documented in the manifest; leave the convention alone, no migration.

### 3.2 Wire `partially_refunded`
Add to the `$status_hooks` map in `Plugin.php`:
```
'wpss_order_status_partially_refunded' => array( 'handle_order_partially_refunded', 10, 2 ),
```
Handler: gateway-refund the partial amount, reverse the vendor's proportional
share, fire `wpss_order_partially_refunded`.

### 3.3 Persist what was refunded
New column `wpss_orders.refunded_amount decimal(10,2) DEFAULT NULL`
(`SchemaManager` CREATE TABLE + a `run_column_migrations()` entry + `DB_VERSION`
bump; `ServiceOrder` must map it — the Connect work proved an unmapped column is
silently dropped and the feature no-ops).

Needed because: the refund amount is currently knowable only inside the dispute,
so an order refunded from the admin screen has no record of how much went back,
and a partial reversal cannot be computed at all.

### 3.4 Proportional share
`vendor_share_of_refund = refunded_amount × ( vendor_earnings ÷ order_total )`.
Full refund is the case where `refunded_amount === total`, so one formula covers
both. The platform fee is reversed proportionally too, so platform revenue
reporting stays honest.

### 3.5 Plumb the dispute amount
`handle_resolution()` passes `$refund_amount` into `update_status()`'s path
(persist `refunded_amount` on the order before the status change so the handler
can read it). `RESOLUTION_REFUND` / `FAVOR_BUYER` set it to the full total;
`RESOLUTION_PARTIAL_REFUND` sets it to the dispute's stored amount.

### 3.6 Un-clamp the balance (D1)
`EarningsService::get_summary()` — `'available_balance' => $available` instead of
`max( 0, $available )`. Check every consumer of `available_balance` for an
assumption that it is non-negative:
- the withdrawal gate (`$amount > $available`) — works unchanged, a negative
  balance simply blocks every withdrawal, which is correct
- `get_eligible_for_auto_payout()` (`>= $threshold`) — works unchanged
- the earnings template — needs the negative-state UI in §5.2

---

## 4. Pro plugin changes

| File | Change |
|---|---|
| `Integrations/WooCommerce/WCOrderProvider.php:487` | Retire the `refunded → cancelled` mapping (D4). Route the status write through `OrderService::update_status()` instead of the raw `$wpdb->update` at `:513`, so the transition is validated and the timeline logged. Overlaps money-doc item 5. |
| `Integrations/SureCart/SureCartOrderProvider.php:227` | `handle_order_refunded()` already sets `refunded`; it will now reverse correctly via the free fix. Persist `refunded_amount` from the webhook payload. |
| `Integrations/FluentCart/FluentCartOrderProvider.php` | Same shape as SureCart — confirm which status its caller maps to and persist the amount. |
| `Integrations/EDD/EDDOrderProvider.php:477` | Maps `refunded → refunded`; inherits the fix. Persist `refunded_amount`. |
| `StripeConnect/` | No change. D3 already handled in `7c6b1c4`. Add a regression test so a future refactor does not "fix" the skip. |
| `Services/LedgerExporter.php` | Statement CSV must render partial reversals sensibly (they are a new amount shape, not a new type). |

---

## 5. The three surfaces — all in this pass

### 5.1 Admin
- **Trigger:** `OrderMetabox` refund action (`confirmRefund` string at `:219`).
  Add a **partial refund amount** input; blank/full = full refund.
- **Feedback:** after refunding, the metabox must state what happened to the
  vendor — "Vendor debited $90" or "Vendor balance is now −$40 (owed)". Today the
  admin gets no signal at all that the vendor side moved (or didn't).
- **Orders list:** show `refunded_amount` on partially-refunded rows so the two
  refund states are distinguishable at a glance.
- **Vendors screen:** surface negative balances — that is money owed to the
  platform and the admin needs to see it without opening each vendor.

### 5.2 Vendor
- **Earnings dashboard:** show a negative balance honestly rather than $0.00.
  Needs its own state: "You owe $40.00. This will be deducted from future
  earnings." — with the token-driven danger styling, not raw red.
- **Withdrawal form:** already blocked by the gate; must explain *why*, not just
  render a disabled form with `Max: $-40`.
- **Wallet transactions:** the reversal row must read clearly, e.g. "Refund for
  order #123 (partial: $50 of $100)".
- **Notification:** the vendor must be told their earnings were reversed. They
  will otherwise discover it as a silently shrinking balance.

### 5.3 Buyer
- **Order view / conversation:** `refunded` is handled
  (`conversation.php:321`); `partially_refunded` is in the terminal list but has
  **no message branch** — add "This order was partially refunded ($50.00)."
- **Orders list:** show the refunded amount, not just the status word.
- **Email:** confirm a refund email exists for both states.

---

## 6. Verification matrix — none of this counts until it runs

Unit-level `wp eval` proved the checkout flow "worked" for months while it was
100% broken. Every row below is a browser check at the real URL, as the real
role.

| # | Case | Expected |
|---|---|---|
| 1 | Full refund, standalone | buyer refunded once; vendor −90; order `refunded` |
| 2 | Partial refund $50, standalone | buyer refunded $50; vendor −45; order `partially_refunded` |
| 3 | Refund a vendor who already withdrew | balance goes **negative**; withdrawal blocked with an explanation |
| 4 | Negative vendor earns again | new earning pays the debt down; balance climbs toward 0 |
| 5 | Refund twice (double-click / replay) | gateway called once; **one** reversal row |
| 6 | Connect order refunded | ledger **unchanged**; `reverse_transfer` at Stripe (D3) |
| 7 | Dispute resolved for buyer (full) | same as #1, via the dispute UI |
| 8 | Dispute resolved partial | same as #2, using the dispute's stored `refund_amount` |
| 9 | Woo refund | status `refunded` (not `cancelled`); vendor reversed once, not twice |
| 10 | Admin view | shows the vendor debit / negative balance |
| 11 | Vendor view | negative state + explanation, at 390px |
| 12 | Buyer view | partial-refund message with amount |

Roles: admin, vendor, buyer. Viewports: desktop + 390px. Theme: BuddyX.

---

## 7. Explicitly out of scope

- Retiring `balance_after` (a separate call; `LedgerExporter` still reads it).
- The 5 status writers that bypass `update_status()` — money-doc item 5, except
  the Woo one, which D4 forces into this pass.
- Vendor-initiated refunds. Refunds stay admin-mediated.
- Automatic debt collection beyond netting against future earnings.

---

## 8. Suggested order

1. Schema (`refunded_amount`) + `ServiceOrder` mapping + DB_VERSION
2. Shared reversal helper; `handle_order_refunded` uses it (case 1)
3. Proportional amount + `partially_refunded` handler + wiring (case 2)
4. Un-clamp the balance + vendor negative-state UI (cases 3, 4, 11)
5. Dispute plumbing (cases 7, 8)
6. Pro: Woo mapping retirement + amount persistence in the 4 providers (case 9)
7. Admin + buyer surfaces (cases 10, 12)
8. Full matrix in the browser

Steps 1–3 are the money fix and could ship alone if the pass has to be split.
