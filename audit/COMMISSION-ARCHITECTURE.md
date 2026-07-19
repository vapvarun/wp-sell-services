# Commission & Money — Target Architecture (senior-dev design)

Written after the journey audit surfaced that commission is computed in **more than
one place** and gateways **re-derive** it instead of consuming our value. This is the
design we *should* have, not a patch on what exists.

## Principle

**WP Sell Services is the single source of truth for money.** For every order it computes
the definitive breakdown — `base`, `platform_fee`, `vendor_earnings` — **once**, supporting
percentage, flat, tiered, and per-plan-override natively. Every downstream consumer
(Stripe Connect split, PayPal payout, wallet ledger, earnings summary, invoices) **reads
that persisted value**. A payment gateway is an *execution adapter*: it moves the amount we
tell it to. It never interprets rate types or recomputes a fee.

## Current state (3 divergent computations — the bug class)

| Where | What it does | Problem |
|---|---|---|
| `CommissionService::calculate()` (free) | base → rate (via `wpss_commission_rate`, **percentage only**) → fee → earnings; persists `vendor_earnings` | Percentage-only seam can't express a **flat** fee (#7). Tiered/override reach it, but only as percentages. |
| `StripeConnect\ConnectPaymentProcessor::calculate_fee()` (pro) | `total * global_rate/100` | **Recomputes from the global rate.** Ignores tiered rules, per-plan `commission_override`, and flat fees. Split ≠ our earnings. |
| `PayoutsBatchService::get_pending_payouts()` (pro) | sums vendor pending amount | OK *if* it sums the persisted ledger earnings — must confirm it never re-derives. |

Net effect: the `commission_override` / tiered / flat rules only affect the **wallet** path;
the **Stripe Connect** path pays a different number. Two answers to "what's the fee?".

## Target design

1. **One authority — `CommissionService::calculate( $order ): CommissionResult`.**
   Returns a value object: `{ base, platform_fee, vendor_earnings, rate_type, effective_rate, source }`.
   Resolution pipeline (highest precedence first), each able to return a **percentage or a flat amount**:
   1. Active subscription plan `commission_override`
   2. Matching tiered rule (`rate_type` = percentage | flat)
   3. Global rate
   The result is normalised to a concrete `platform_fee` **amount** against `base`.

2. **Amount-based extension seam — `wpss_commission_fee`.**
   `apply_filters( 'wpss_commission_fee', $fee_amount, $order, CommissionResult $partial )`.
   Pro's TieredCommission + subscription override hook **this** and return real amounts
   (flat = the flat value; percentage = `base * rate/100`). The legacy percentage-only
   `wpss_commission_rate` filter is kept for third-party BC and folded in as a
   percentage→amount step, so nobody breaks.

3. **Persist the breakdown on the order** (`platform_fee`, `vendor_earnings`, `commission_source`)
   at the moment the order is completed/paid — already partly done for `vendor_earnings`.

4. **Consumers read, never recompute:**
   - `ConnectPaymentProcessor` → split by the order's persisted `platform_fee` / `vendor_earnings`.
     Delete `calculate_fee()`'s independent percentage math.
   - `PayoutsBatchService` → pay the persisted ledger earnings.
   - Wallet / earnings / invoices → already consume `vendor_earnings`.

## What this fixes (and subsumes)

- **#7 flat commission** — flat is first-class in the pipeline; no percentage-conversion hack.
- **#8 rules "always Active"** — display reads the real `is_active`; unrelated to math, fixed alongside.
- **#3 commission_override** — already hooked; moves onto the amount seam so it *also* reaches the Stripe Connect split (today it does not).
- **The deeper defect** — Stripe Connect and the wallet finally agree on one number.

## Migration / back-compat

- Keep `wpss_commission_rate` working (percentage overrides) — internally converted to an amount.
- No schema break: add `platform_fee` / `commission_source` columns additively if not present; `vendor_earnings` already exists.
- Behaviour parity for pure-percentage sites (the common case) must be byte-for-byte; add a regression check: global-rate order earnings identical before/after.

## Rollout

1. Introduce `CommissionResult` + amount pipeline in `CommissionService` (free), keep old filter as a shim. Verify percentage parity.
2. Repoint `ConnectPaymentProcessor` + payout to the persisted breakdown. Verify split == earnings.
3. Move TieredCommission (flat + percentage) and subscription override onto `wpss_commission_fee`.
4. Retire the divergent `calculate_fee()` math.

This replaces the per-symptom commission fixes (#3/#7/#8) with one correct foundation and
closes the gateway-recompute gap the audit found.
