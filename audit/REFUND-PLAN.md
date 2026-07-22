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

---

# PART IV — Code-level touch list + QA gate

Added 2026-07-21. Every line number below was re-verified against current
source. **This is the anti-duplication contract: if a change is not in this
table, it is not in scope — and if you find yourself writing a second reversal,
a second refund call or a second proportional formula, stop.**

## 20. Build order — exact edits

### Step 0 — kill the double-wiring FIRST (blocking)

| File:line | Edit |
|---|---|
| free `Services/DisputeWorkflowManager.php:78-81` | **Delete** the four `add_action()` calls from `__construct()`. `Plugin.php:1901-1904` already wires all four via the dispute-hook map. Constructor registration double-fires because `DisputesController.php:56` does `new DisputeWorkflowManager()` and WP cannot dedupe a closure against an object-array callback. |

Verify with the runtime counter (§9): `wpss_dispute_resolved` must drop 2 → 1.
**Do this before anything else** — otherwise every reversal added below fires
twice and the idempotency guard hides it.

### Step 1 — data

| File | Edit |
|---|---|
| free `Database/SchemaManager.php` | Add `refunded_amount decimal(10,2) DEFAULT NULL` to the orders CREATE TABLE (after `connect_transfer_id`); add a `run_column_migrations()` entry `after => 'connect_transfer_id'`; bump `DB_VERSION` 1.4.8 → 1.4.9. |
| free `Models/ServiceOrder.php` | Declare `public ?string $refunded_amount = null;` **and** map it in `from_db()` with `?? null`. Non-negotiable: the Connect work proved an unmapped column is dropped silently and the feature no-ops. |

### Step 2 — the shared reversal (closes the leak)

| File:line | Edit |
|---|---|
| free `Services/OrderWorkflowManager.php:764` | `reverse_order_earnings( int, ServiceOrder )` → add `?float $refund_amount = null`. Null = full. Keep the existing idempotency key, the Connect skip and the transaction. **This stays the only reversal.** |
| free `Services/OrderWorkflowManager.php:728` | `handle_order_refunded()` — add the reversal call, guarded exactly as `handle_order_cancelled():674` guards it (`null !== $order->vendor_earnings`). |
| free `functions.php` | New `wpss_get_refund_vendor_share( ServiceOrder $order, float $refunded ): float` — `refunded × (vendor_earnings ÷ total)`, guard `total <= 0`. **The only proportional formula.** |
| free `Integrations/Standalone/StandaloneOrderProvider.php:286` | **DELETE** `process_refund()`. Zero callers; unguarded duplicate of `attempt_payment_refund()`. |

### Step 3 — partial refunds

| File:line | Edit |
|---|---|
| free `Services/OrderWorkflowManager.php:927` | `attempt_payment_refund( ServiceOrder )` → add `?float $amount = null`; pass to `process_refund()` at `:945` instead of hardcoded `$order->total`. Gateway interface already accepts it — **no gateway edits at all**. |
| free `Services/OrderWorkflowManager.php` | New `handle_order_partially_refunded( int, string )`. Mirrors `handle_order_refunded`; reads `$order->refunded_amount`; delegates to the same two helpers. |
| free `Core/Plugin.php:1783` | Add `'wpss_order_status_partially_refunded' => array( 'handle_order_partially_refunded', 10, 2 )` to `$status_hooks`. |

### Step 4 — dispute plumbing

| File:line | Edit |
|---|---|
| free `Services/DisputeService.php:516` | `handle_resolution()` — persist `$refund_amount` to `wpss_orders.refunded_amount` **before** `update_status()`. Full refund / favour-buyer = order total; partial = the dispute's stored amount. Stop dropping the parameter. |

### Step 5 — un-clamp + vendor UI

| File:line | Edit |
|---|---|
| free `Services/EarningsService.php:173` | `max( 0, $available )` → `$available`. |
| free `Services/EarningsService.php` | New `get_balance_state( int ): string` — `positive\|zero\|negative`. One derivation, consumed by every template. |
| free `templates/dashboard/sections/earnings.php` | Negative state: amount, "deducted from future earnings" explanation, withdrawal form replaced by the reason. Tokens only, no raw hex. |

### Step 6 — Connect (Part III)

| File:line | Edit |
|---|---|
| free `Integrations/Stripe/StripeGateway.php` | `process_refund()` return shape carries whether the transfer reversal succeeded. |
| free `Services/OrderWorkflowManager.php:764` | Make the D3 skip **conditional** — reverse the ledger when the clawback failed (C1). |
| pro `StripeConnect/ConnectWebhookHandler.php:79` | Add `transfer.reversed` alongside `transfer.created` (C2). |
| pro `API/StripeConnectController.php:286` | `disconnect()` — refuse while `wpss_get_ledger_balance() < 0` (C4). |

### Step 7 — Pro rails (D4/D4a)

| File:line | Edit |
|---|---|
| pro `Integrations/WooCommerce/WCOrderProvider.php:487` | Delete `'refunded' => 'cancelled'`. Map `refunded → refunded`, and `partially_refunded` when `get_total_refunded() < total`. |
| pro `Integrations/WooCommerce/WCOrderProvider.php:295` | `handle_order_refunded()` — persist `refunded_amount` from `get_total_refunded()`; route the status write through `OrderService::update_status()` (retires the raw `$wpdb->update` at `:513`). |
| pro `Integrations/SureCart/SureCartOrderProvider.php:227` | Persist `refunded_amount` from the webhook before the status write. |
| pro `Integrations/FluentCart/FluentCartOrderProvider.php` | Same shape; confirm the mapped status. |
| pro `Integrations/EDD/EDDOrderProvider.php:477` | Persist `refunded_amount`; mapping already correct. |

### Step 8 — admin + buyer surfaces

| File | Edit |
|---|---|
| free `Admin/Metaboxes/OrderMetabox.php:219` | Partial-amount input; post-refund confirmation naming the vendor impact. |
| free `Admin/Pages/OrdersPage.php` | Show `refunded_amount` on partially-refunded rows. |
| free `Admin/Pages/VendorsPage.php` | Surface negative balances in the list. |
| free `templates/order/conversation.php:321` | Add the `partially_refunded` branch with the amount (the status is already terminal at `:116` but has no message). |
| free `templates/dashboard/sections/orders.php` | Show refunded amount, not just the status word. |

## 21. QA gate — nothing ships until every row passes

Per the team standard: code-quality passing is **not** verification, and a plan
item is done when its browser check passes, not when the code is written.

### 21.1 Functional matrix (cases 1-16 from §6 and §19)
Run each in the browser at the real URL as the real role — not `wp eval`.
Unit-level checks passed for months while checkout was 100% broken.

### 21.2 Per-surface browser checks

| Surface | Role | Must verify |
|---|---|---|
| Earnings dashboard | vendor | positive / zero / **negative** states; withdrawal form blocked with a reason, not a bare disabled control; wallet rows readable |
| Order metabox | admin | partial input validates (> 0, ≤ total); confirmation states the vendor impact |
| Orders list | admin | refunded vs partially-refunded distinguishable at a glance |
| Vendors list | admin | negative balances visible without opening each vendor |
| Order conversation | buyer | refunded **and** partially-refunded messages, with amounts |
| Payout methods | vendor | Connect states unchanged by this work (regression) |

### 21.3 Cross-cutting (every changed surface)
- **390px** — required, not optional
- **Hover / focus / visited** on every `<a>` styled as a button (themes override these)
- **Dark mode** — token-driven colours only, no raw hex
- **RTL** — `margin-inline-*`, not `margin-left/right`
- **Empty / error / loading** states on every async surface
- **A11y** — semantic markup, ARIA labels on icon-only buttons, keyboard reachable
- **Theme matrix** — BuddyX **and** a generic theme (most installs are not ours)

### 21.4 Big-site readiness
Admin Orders and Vendors lists gain a new column each: confirm pagination,
`COUNT(*)` totals and no N+1 at 2000+ orders. `refunded_amount` needs no index
(never filtered on) — state that explicitly rather than adding one by reflex.

### 21.5 Money integrity (the ones that actually matter)
- Ledger never double-reverses — replay every refund path twice
- Balance arithmetic identical across all five rails (**D4a**)
- Connect: ledger untouched on success, reversed on clawback failure
- No negative balance created where the vendor was never wallet-credited
- `wpss_dispute_resolved` listeners = 1 (step 0 held)

### 21.6 Regression
- Existing Woo sites: `cancelled` → `refunded` transition reverses **once**
- Historical `order_reversal` rows still sum correctly (negative-amount convention)
- Statement CSV renders partial reversals correctly
- Withdrawal, auto-payout and Connect onboarding all unaffected

---

# PART V — ARCHITECTURE CORRECTION: Connect is Pro-only

Added 2026-07-21. **This supersedes how D3 was implemented in `7c6b1c4`.**

## 22. The rule

Owner: *"stripe connect should be part of pro"* / *"without stripe connect they
will pay manually in free standalone."*

| Plugin | Payout model |
|---|---|
| **Free** | **Manual only.** Vendor earns → wallet credit → withdrawal request → admin marks paid → ledger debit. |
| **Pro** | Adds Stripe Connect: automatic split at charge time, vendor paid directly. |

Free must have **zero** knowledge of Connect. A free-only site has no split
payments, so the offset and the reversal-skip are meaningless there.

## 23. What I got wrong in 7c6b1c4

Eleven Connect-specific references were put into free:

| File:line | Leak |
|---|---|
| `Services/CommissionService.php:208,403-460` | `create_connect_offset_transaction()` — a whole Connect method in free |
| `functions.php:142` | `'connect_transfer'` hardcoded in the default debit types |
| `Database/SchemaManager.php:219,428` | `connect_transfer_id` column in free's schema |
| `Models/ServiceOrder.php:243,553` | property + `from_db()` mapping |
| `Services/OrderWorkflowManager.php:787-795` | reversal skip keyed on `connect_transfer_id` |

The behaviour was right; the placement was not. Corrected **before** step 2
builds on it — otherwise the shared reversal inherits the leak and the cost of
undoing it triples.

## 24. The seams already exist

Only **one** new hook is needed. The other two shipped earlier today:

| Seam | Status | Pro uses it to |
|---|---|---|
| `wpss_commission_recorded` (action) | exists, `CommissionService:216` | write its own offset ledger row after free credits the vendor |
| `wpss_ledger_debit_types` (filter) | exists, `functions.php:133` | register `connect_transfer` as a debit type |
| `wpss_should_reverse_vendor_earnings( bool, ServiceOrder ): bool` | **NEW** | return false for Connect-settled orders |

That third filter is the generic form of the D3 decision. Free asks "should I
reverse this?"; Pro answers. Free never learns why.

## 25. Ownership after the correction

**Free keeps** (generic, not Connect):
- `StripeGateway::get_payment_intent()` — generic Stripe read, Pro consumes it
- `StripeGateway::build_refund_args()` `reverse_transfer` flags — derived from the
  intent itself, stores nothing
- the ledger, the reversal authority, every calculation (D4a unchanged)

**Pro takes**:
- `connect_transfer_id` storage — via a **new** `maybe_add_column()` helper on
  `ProSchemaManager`, which today only creates Pro tables. Pro owns the column on
  `wpss_orders` through its own migration.
- the offset ledger row, written with the existing `InternalWalletProvider`
  pattern
- the reversal-skip decision, via the new filter
- `transfer.reversed` handling and the disconnect guard (Part III)

## 26. Effect on Part IV

- **Step 0b** inserted after step 0, before step 2. Both are blocking.
- **Step 6** shrinks: the conditional-D3 logic becomes a Pro filter return value
  rather than a free code path. Free only gains the filter.
- Free file count drops by 1 (no `connect_transfer_id` in `SchemaManager` /
  `ServiceOrder` for Connect — `refunded_amount` from step 1 still stands).

## 27. Acceptance

`grep -rn "connect" free/src` returns **zero code hits** — comments and generic
Stripe only. Then:
- free-only site: refund reverses normally, no Connect concepts anywhere
- Pro active: Connect order still nets to zero, still skips the reversal
- both proven by the same case 6 and 13/14 tests

---

# PART VI — QA SWEEP RESULTS (2026-07-21)

Environment: BuddyX, PHP 8.2, Stripe test mode. Woo and a generic theme were
activated for their cases and restored afterwards. All test data removed; the
DB is back to its pre-sweep baseline.

## 28. Functional + money integrity — 16/16 PASS

| # | Case | Result |
|---|---|---|
| 1 | Full refund reverses | −90.00 |
| 2 | Partial $50 reverses proportionally | −45.00, order keeps 45.00 |
| 5 | Replay (double-click) | no movement, exactly 1 reversal row |
| 6 | Connect order nets to zero on earn | 0.00 |
| 13 | Connect refund, clawback SUCCEEDS | ledger untouched |
| 14 | Connect refund, clawback FAILS | −90.00 debt recorded |
| 3 | Refund after withdrawal | balance goes negative, state `negative` |
| 4 | Negative vendor earns again | debt pays down automatically |
| 7 | Dispute resolved full | −90.00, order `refunded`, amount 100.00 |
| 8 | Dispute resolved partial $25 | −22.50, amount on dispute AND order |
| 9 | **Woo refund (real `wc_create_refund`)** | status `refunded` (not `cancelled`), amount 100.00, **exactly 1** reversal row, −90.00 |
| D4a | Rail parity | standalone / woocommerce / surecart / edd all −90.00 |

Hook wiring verified at runtime: `wpss_dispute_resolved` = 1,
`wpss_order_status_refunded` = 1, `wpss_order_status_partially_refunded` = 1
(was 0), `wpss_order_status_cancelled` = 1.

## 29. Cross-cutting — PASS

| Check | Result |
|---|---|
| 390px | no horizontal scroll, banner fits, CTA 44px |
| Desktop | no horizontal scroll |
| Tap targets | 0 buttons under 40px |
| A11y | banner `role="status"`, 0 icon-only buttons missing a label |
| RTL | no overflow, no horizontal scroll |
| Dark mode | danger tokens resolve; banner keeps its light surface, **consistent with the existing `--warning` banner** (verified by comparison, not assumed) |
| Generic theme (Twenty Twenty-Four) | banner renders correctly, tokens apply, CTA 58px |

## 30. Findings NOT caused by this work — for follow-up

Both reproduce independently of the refund changes and affect shared code.

**F1 — `.wpss-btn--primary` hover does nothing.** The rule exists in
`design-system.css`, and the tokens differ (`--wpss-primary` `#4f46e5` vs
`--wpss-primary-hover` `#4338ca`), yet a real mouse-move produces no change in
computed background. Reproduces on **BuddyX and Twenty Twenty-Four**, so it is
not a theme override. Focus is fine (2px solid outline). This affects every
primary button in the plugin, not just the refund banner. Worth a dedicated
look — the team standard explicitly calls out hover/focus on `<a>` buttons.

**F2 — dashboard shell overflows horizontally on a generic theme.**
`wpss-fullwidth-page` measures 1312px inside a 1280px viewport on Twenty
Twenty-Four (BuddyX is clean). The refund banner is **not** among the
offenders — the overflow is the page shell itself. Given most installs do not
run our themes, this is worth its own card.

## 31. Not verified — stated plainly

- **Case 16, live Stripe Connect onboarding.** Still needs a test-mode Connect
  account. Every Connect result above was produced by injecting the clawback
  outcome, not by Stripe. The ledger logic is proven; the live leg is not.
- **Admin partial-refund UI.** The server accepts an amount
  (`order_action` → `refund_amount`) and the AJAX path was exercised, but the
  metabox still offers only a full refund. Server side done, UI not built.
- **Big-site (2000+ rows).** Not run. The two admin lists gained a label
  change, not a new query or column, so the risk is low —
  `refunded_amount` is never filtered or sorted on and needs no index.

## 32. Release note

Free and Pro **must ship together**. Free's reversal fix and Pro's Woo status
change are two halves of one behaviour: free alone makes Woo reverse twice
(the map still routes to `cancelled`), Pro alone stops Woo reversing at all.

---

# PART VII — REAL CHECKOUT RUN (2026-07-22)

Ran the buyer flow in the browser as `realcustomer`, because every prior test
entered mid-flow with a hand-inserted order. Two P0s were found that no amount
of `wp eval` would have surfaced.

## 33. Flow verified up to the payment gate

service page → "Continue ($50.00)" → Order Options modal → added to cart →
`/service-checkout/9/` → gateway radio → **Stripe Payment Element mounts** →
card `4242…` accepted → Address Element renders.

Blocked at the plugin's own pre-flight guard: *"Please complete your billing
name and address."* That guard is **correct behaviour** — `stripe.js` calls
`addressElement.getValue()` and refuses to charge unless `.complete` is true,
so the buyer gets a field-level prompt instead of a raw Stripe API error.

Could not satisfy it through automation: the Address Element is a
React-controlled Stripe iframe, so setting `.value` + dispatching `change` does
not update Stripe's internal state, and `selectOption()` on its `<select>`
times out. **A harness limitation, not a product defect.** The Element also
defaults to the account country (India), so the state list is Indian until
country is changed.

## 34. P0 — the auto-refund never found the gateway (FIXED, e6b77a5)

`attempt_payment_refund()` resolved gateways via
`apply_filters( 'wpss_payment_gateways', [] )`. Free registers stripe, paypal
and offline into `Plugin::$payment_gateways` and applies the filter to THAT
array once at init — re-running it from an empty array returns only what Pro
adds (razorpay here). So the lookup returned null for every Stripe/PayPal/
offline order, logged a warning, and returned. **The buyer's money never went
back**, for as long as the method has existed.

Today's work made it worse, not better: the vendor reversal now fires
correctly, so the platform was debiting the vendor while never refunding the
buyer. Fixed by using `wpss()->get_payment_gateways()`, which
`PaymentController` already used — this was the odd one out. Verified:
`payment_status` now flips to `refunded`, which only happens when the gateway
call actually succeeds.

## 35. GAP — billing address is collected but never stored (NOT fixed)

Owner: *"lots of people need that for tax and invoices."*

`stripe.js` collects name + address and sends them to Stripe as
`billing_details` and `shipping`. **Nothing persists them locally** — grep over
`src/` finds no billing field, and `wpss_orders` has no billing column among
its 33.

Consequences:
- The plugin cannot render an invoice showing the billing address.
- There is no local record for tax reporting; it exists only in the Stripe
  dashboard, and not at all for PayPal/offline orders.
- A buyer's own order view cannot show what address the purchase was billed to.

Shape of the fix (not attempted here — it is a feature, not a refund bug):
persist the billing block on the order at payment time, from the same
`address.value` the gateway already receives. Needs a column or a JSON blob on
`wpss_orders`, plus display on the buyer order view, the admin order screen and
any invoice/export surface. Worth its own card.

## 36. Still not verified

A completed real payment. The flow is proven to the point of charge; the
charge itself needs either a manual run-through in a browser, or a country
whose Address Element has no state dropdown, to get past the automation
barrier. Everything downstream of "order paid" is separately verified (§28).
