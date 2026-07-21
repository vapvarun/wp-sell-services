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

**D4 — `refunded` means refunded on every platform. RESOLVED: Woo is a payment
gateway, not a platform.**

Owner, 2026-07-21: *"Woo is just a payment gateway for us like Stripe or PayPal."*

That settles the mapping question and simplifies the model. Woo/EDD/SureCart/
FluentCart are **payment rails**, exactly like Stripe and PayPal. They report one
fact — was the money taken, or given back — and we own the order semantics on our
side. They do not get their own status vocabulary that we translate.

So a status **map** is the wrong shape entirely. Stripe does not tell us to call
an order `cancelled`; it tells us a refund succeeded, and *we* decide the order is
`refunded`. Woo gets the same treatment:

- Woo refund → our order `refunded` (or `partially_refunded` when
  `get_total_refunded()` < total). Never `cancelled`.
- Retire the `'refunded' => 'cancelled'` entry at `WCOrderProvider.php:487`.
- Route through `OrderService::update_status()` instead of the raw
  `$wpdb->update` at `:513`, so the transition is validated and the timeline
  logged like every other rail.

**Why the mapping existed and why removing it is safe now:** it was load-bearing
by accident. Mapping refund→cancelled routed Woo refunds into
`handle_order_cancelled`, which *does* reverse earnings — so Woo refunds worked
while standalone refunds silently did not. Once `handle_order_refunded` reverses
correctly (§3.1), the mapping stops being a workaround and becomes a lie about
what happened. Removing it makes a Woo buyer and a standalone buyer see the same
status for the same event.

**Regression risk to watch:** existing Woo sites will start seeing `refunded`
where they saw `cancelled`. Verification case 9 must confirm the vendor is
reversed exactly once — not twice via both handlers — during the transition.

**D4a — THE RAIL NEVER CHANGES THE NUMBERS.** Owner, 2026-07-21: the gateway
"should not impact how we are calculating earning and refunds."

The rail decides only *whether* money moved and *how much the buyer got back*.
It never influences the arithmetic:

| Quantity | Source | Identical across rails? |
|---|---|---|
| order `total` | our service/package price | yes — never the rail's line total |
| `platform_fee` / `vendor_earnings` | `CommissionService::compute_breakdown()` | yes |
| vendor share of a refund | `wpss_get_refund_vendor_share()` | yes |
| ledger rows written | `reverse_order_earnings()` | yes |
| currency of stored amounts | store base currency | yes |

A $49 service refunded in full reverses the same $44.10 vendor share whether the
buyer paid via Woo, Stripe, PayPal, EDD or SureCart. If swapping the rail changes
any persisted number, that is the bug — not a configuration difference.

This is the refund-side restatement of `ecommerce_adapter_boundary` in the
manifest, and it is why §13 forbids Pro from owning any `reverse_*` or fee math:
per-rail money logic is precisely how the numbers would drift apart.

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

---

# PART II — Data-level and function-level plan

Added 2026-07-21 after inventorying the real files, so implementation reuses
what exists instead of growing twins. Every signature below was read from
source, not recalled.

## 9. Runtime facts verified before planning

```
wpss_dispute_resolved                    listeners=2   <- DOUBLE-WIRED (bug, §12)
wpss_order_status_refunded               listeners=1
wpss_order_status_partially_refunded     listeners=0   <- no handler (confirms §2)
wpss_order_status_cancelled              listeners=1
```

## 10. DATA LEVEL

### 10.1 Already exists — do NOT re-invent

| Store | Column | Note |
|---|---|---|
| `wpss_disputes` | `refund_amount decimal(10,2) DEFAULT NULL` | **The partial amount already exists.** `DisputeService::resolve()` accepts it, `handle_resolution()` receives it — and drops it. Plumb, don't add. |
| `wpss_orders` | `total`, `vendor_earnings`, `platform_fee`, `currency` | Everything needed to compute a proportional share. |
| `wpss_orders` | `payment_status` | Already the double-refund guard (`refunded`/`pending` short-circuit). |
| `wpss_orders` | `connect_transfer_id` | Added `7c6b1c4`. Drives the D3 skip. |
| `wpss_wallet_transactions` | `reference_type` + `reference_id` | The idempotency key used by every write path. Reuse for reversals. |

### 10.2 New — exactly one column

| Table | Column | Why |
|---|---|---|
| `wpss_orders` | `refunded_amount decimal(10,2) DEFAULT NULL` | How much actually went back to the buyer. Without it a partial reversal cannot be computed, and an admin-screen refund leaves no record of the amount. NULL = never refunded. |

Migration: `SchemaManager` CREATE TABLE + `run_column_migrations()` entry
(`after` => `connect_transfer_id`) + `DB_VERSION` 1.4.8 → 1.4.9.
**`ServiceOrder::from_db()` must map it** — the Connect work proved an unmapped
column is silently dropped and the feature no-ops with no error.

### 10.3 Ledger sign convention — a trap

`order_reversal` rows store a **NEGATIVE** `amount`
(`OrderWorkflowManager` writes `'amount' => -$vendor_earnings`), unlike every
other type, which stores positive and has the sign applied on read.

- **Do NOT** add `order_reversal` to `wpss_get_ledger_debit_types()` — it would
  negate twice and flip every historical reversal into a credit.
- New partial reversals **follow the existing negative convention** for this
  type, so no data migration and no mixed semantics within one type.
- The manifest's "amounts are always stored POSITIVE" claim is wrong for this
  type and must be corrected.

### 10.4 Data flow

```
admin refund (amount) ─┐
dispute resolve ───────┼─> wpss_orders.refunded_amount  (persist FIRST)
gateway webhook ───────┘            │
                                    v
                       update_status( refunded | partially_refunded )
                                    │
                                    v
                    handle_order_(partially_)refunded
                          │                    │
                          v                    v
              attempt_payment_refund    reverse_order_earnings
                  (buyer money)          (vendor ledger, may go negative)
```
`refunded_amount` is persisted **before** the status change so the handler can
read it off the order rather than threading it through hook args (which would
break the 2-arg `$status_hooks` signature every other handler shares).

## 11. FUNCTION LEVEL

### 11.1 REUSE — extend, do not duplicate

| Function | Current signature | Change |
|---|---|---|
| `OrderWorkflowManager::reverse_order_earnings()` | `(int $order_id, ServiceOrder $order): bool` — private | Add `?float $refund_amount = null`. Null = full. Already idempotent on `(order, order_reversal\|refund)`, already Connect-aware, already transactional. **This is the single reversal authority.** |
| `OrderWorkflowManager::attempt_payment_refund()` | `(ServiceOrder $order): void` — private, hardcodes `(float) $order->total` at `:945` | Add `?float $amount = null`. Already guards `payment_status IN (refunded, pending)`. |
| `PaymentGatewayInterface::process_refund()` | `(string $transaction_id, ?float $amount = null, string $reason = ''): array` | **No change — already supports partial.** All 5 implementations (Stripe, PayPal, Offline, Test, pro Razorpay) already honour the amount. |
| `DisputeService::handle_resolution()` | `(object $dispute, string $resolution, float $refund_amount): void` | Stop dropping `$refund_amount` — persist it to `wpss_orders.refunded_amount` before `update_status()`. |
| `CommissionService::compute_breakdown()` | static | Reuse for the proportional platform-fee reversal. Do not hand-roll. |
| `wpss_get_ledger_balance()` | `(int, bool): float` | No change. Already returns negatives correctly — only the display clamp hides them. |

### 11.2 NEW — three functions, no more

| Function | Signature | Home |
|---|---|---|
| `handle_order_partially_refunded()` | `(int $order_id, string $old_status): void` | `OrderWorkflowManager`. Mirrors `handle_order_refunded`; both delegate to the shared reversal. |
| `wpss_get_refund_vendor_share()` | `(ServiceOrder $order, float $refunded_amount): float` | `src/functions.php`. `refunded × (vendor_earnings ÷ total)`. One formula; full refund is the case where the amounts are equal. Guards total = 0. |
| `EarningsService::get_balance_state()` | `(int $vendor_id): string` — `positive\|zero\|negative` | Backs the vendor negative-state UI so three templates don't each re-derive "is it negative". |

### 11.3 DELETE — confirmed dead duplicate

`StandaloneOrderProvider::process_refund( ServiceOrder $order, float $amount, string $reason = '' ): bool` (`:286`)

Grep proves **zero callers**. It duplicates `attempt_payment_refund()` — resolves
the gateway from `wpss_payment_gateways` and calls `process_refund()`, but
without the `payment_status` double-refund guard, without the audit-log write and
without updating the order. Deleting it removes a trap where someone "reuses" the
unguarded twin later. This is the audit's 4th "refund implementation".

### 11.4 MODIFY — clamp removal

`EarningsService::get_summary()` — `'available_balance' => max( 0, $available )`
becomes `$available` (D1). Verified consumers that already tolerate negatives:
withdrawal gate (`$amount > $available`), auto-payout (`>= $threshold`),
REST `get_summary`. Only the earnings template needs the new state.

## 12. Bug found while inventorying — fix in this pass

**`wpss_dispute_resolved` fires every listener TWICE.** Verified at runtime
(listeners=2).

`DisputeWorkflowManager::__construct()` registers
`add_action( 'wpss_dispute_resolved', [$this, 'on_dispute_resolved'], 10, 4 )`
(`:81`), **and** `Plugin.php:1904` wires the same method through a static
closure. WordPress cannot dedupe a closure against an object-array callback, so
both run. `DisputesController.php:56` does `new DisputeWorkflowManager()`, so the
constructor registration is live.

Effect: on every dispute resolution both parties get **two** notifications, and
any side effect added to `on_dispute_resolved` (such as a refund reversal) would
run twice. Since this plan puts refund logic in that path, it must be fixed
first or the reversal double-fires — the idempotency guard would mask it, which
is worse because it hides the duplication.

Fix: drop the constructor registrations (same precedent as the comment already at
`Plugin.php:1799`, where a duplicate `log_status_change` listener caused
duplicate audit rows). `Plugin.php` is the single wiring point.

## 13. Free ↔ Pro contract

Pro adds **no** refund logic. It persists `refunded_amount` and sets a status;
free owns money movement.

| Pro file | Change |
|---|---|
| `WooCommerce/WCOrderProvider.php:487` | Retire `refunded → cancelled` (D4). Route via `OrderService::update_status()` instead of the raw `$wpdb->update` at `:513`. Persist `refunded_amount` from `$order->get_total_refunded()`. |
| `SureCart/SureCartOrderProvider.php:227` | Persist `refunded_amount` from the webhook before `update_order_status()`. |
| `FluentCart/FluentCartOrderProvider.php` | Same shape; confirm the mapped status. |
| `EDD/EDDOrderProvider.php:477` | Persist `refunded_amount`; mapping already correct. |
| `StripeConnect/*` | No change (D3). Add a regression test so the skip is not "fixed" later. |
| `Services/LedgerExporter.php` | No change — partial reversals are the same type, different amount. |

**Pro must not gain a `reverse_*` or `process_refund` of its own.** Any new one
is a contract violation.

## 14. Anti-duplication checklist

- [ ] One reversal authority: `reverse_order_earnings()`. No second reversal.
- [ ] One buyer-refund path: `attempt_payment_refund()`. Dead twin deleted.
- [ ] One proportional formula: `wpss_get_refund_vendor_share()`.
- [ ] One debit-types list: `wpss_get_ledger_debit_types()` — unchanged, and
      `order_reversal` stays OUT of it.
- [ ] One commission authority: `CommissionService::compute_breakdown()`.
- [ ] One hook wiring point: `Plugin.php`. Constructor registrations removed.
- [ ] Pro adds no money logic.

---

# PART III — Stripe Connect: what exists, and the refund gaps

Added 2026-07-21. **Connect is already implemented** — this is not a greenfield
build. Surveyed from source before planning.

## 15. What already exists (do not rebuild)

| Layer | Component | State |
|---|---|---|
| Storage | `wpss_pro_connect_accounts` table | built |
| Backend | `ConnectAccountService`, `ConnectOnboardingHandler`, `ConnectPaymentProcessor`, `ConnectWebhookHandler`, `StripeConnectManager` | built |
| REST | 6 routes — `/onboard`, `/status`, `/disconnect`, `/accounts`, `/accounts/{vendor_id}`, `/settings` | built |
| Webhooks | `account.updated`, `payout.paid`, `payout.failed`, `transfer.created` | built |
| Admin | `ConnectSettingsRenderer` (Gateways tab, `wpss_pro_stripe_connect_enabled`) | built |
| Vendor frontend | `PayoutMethodsCoordinator` — renders not-connected / active / pending-restricted in Dashboard → Earnings & Payouts | built |
| Split payment | `transfer_data` + `application_fee_amount` on the PaymentIntent | built |
| Double-pay fix | `connect_transfer_id` + offsetting ledger row | shipped `7c6b1c4` / `85cd4de` |

No dedicated Connect JS — the vendor UI is server-rendered and posts to the REST
routes. That is fine; do not introduce a JS layer for this.

## 16. GAP 1 — nothing verifies the Connect clawback succeeded

**This undermines an assumption in D3.**

D3 says a refunded Connect order skips the ledger reversal because "the clawback
happens at Stripe" via `reverse_transfer` (set in
`StripeGateway::build_refund_args()`). That is true only when the reversal
*succeeds*.

`reverse_transfer` fails routinely in production: it pulls funds back out of the
connected account, and if the vendor has already paid out to their bank the
balance is insufficient. Stripe then returns an error or creates a negative
balance on the connected account.

**Grep shows no Connect webhook handles this.** `ConnectWebhookHandler` covers
`account.updated`, `payout.paid`, `payout.failed`, `transfer.created` — there is
**no** `charge.refunded`, no `transfer.reversed`, no failure branch.

Failure mode: buyer refunded, `reverse_transfer` fails silently, ledger reversal
skipped by D3, vendor keeps the money, platform absorbs the loss with **no record
anywhere**. Strictly worse than the standalone bug this plan fixes, because at
least that one leaves a wallet balance an admin could spot.

**Fix:**
- Handle `transfer.reversed` — confirm the clawback landed.
- On refund, inspect the Stripe refund response for reversal failure; when the
  transfer could not be reversed, **fall back to the ledger reversal** (D1) so the
  debt is recorded on our side and nets off future earnings.
- Surface it to the admin: "Stripe could not reclaim $X from the vendor's
  connected account; recorded as a wallet debt instead."

This makes D3 conditional, which is what it should always have been: *skip the
ledger reversal only when Stripe actually took the money back.*

## 17. GAP 2 — disconnect has no balance guard

`StripeConnectController::disconnect()` checks only `is_connected()`. A vendor can
disconnect at any time.

Under D1 (negative balances allowed) a vendor could carry a wallet debt,
disconnect, and remove the rail the platform would have reclaimed through. Low
severity today because Connect orders net to zero in the wallet — but GAP 1's
fallback creates exactly this state, so the two must land together.

**Fix:** refuse disconnect while `wpss_get_ledger_balance() < 0`, with a message
explaining the outstanding amount. Admin override stays available.

## 18. Connect work folded into the refund pass

| # | Change | File |
|---|---|---|
| C1 | Make D3 conditional — reverse the ledger when `reverse_transfer` failed | free `OrderWorkflowManager::reverse_order_earnings()` + `StripeGateway::process_refund()` return shape |
| C2 | Handle `transfer.reversed` | pro `ConnectWebhookHandler` |
| C3 | Admin notice when a clawback failed | pro + free admin order screen |
| C4 | Block disconnect while balance is negative | pro `StripeConnectController::disconnect()` |
| C5 | Regression test that the D3 skip is not "fixed" away | pro |

**Still no Pro money logic:** C1 lives in free. Pro reports the Stripe outcome;
free decides what the ledger does. Consistent with D4a — the rail never changes
the numbers, it only reports whether money moved.

## 19. Verification additions

| # | Case | Expected |
|---|---|---|
| 13 | Connect refund, `reverse_transfer` **succeeds** | ledger unchanged (D3 holds) |
| 14 | Connect refund, `reverse_transfer` **fails** | ledger reversed, balance may go negative, admin notified |
| 15 | Vendor with negative balance tries to disconnect | refused with the amount explained |
| 16 | Connect onboarding end-to-end | test-mode account, real `transfer_data`, verified at Stripe |

**Case 16 is the one still genuinely unverified.** Everything shipped in
`7c6b1c4` was proven at the ledger level with a synthetic `connect_transfer_id`;
the live Stripe leg — onboarding, `transfer_data` landing, the intent read-back —
has never been exercised. **This needs a Stripe test-mode Connect account.**
