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

---

## Exact surface map (from the 2026-07-19 sweep — start here next session)

The fee formula `round(base * rate/100, 2)` / independent fee derivation lives in **6 places**.
Phase 1 (shipped, `4e45059`) added the `wpss_commission_fee` seam in surface #1. The remaining
work repoints the other five + the two Pro rate hooks:

| # | Surface | File:line | Action |
|---|---|---|---|
| 1 | `CommissionService::calculate()` | free `src/Services/CommissionService.php` | ✅ done — home of the amount seam. Extract a `compute_breakdown(float $base, object $order): array` static that #2–#5 all call. |
| 2 | Standalone order create | free `src/Integrations/Standalone/StandaloneOrderProvider.php:98` | replace local `round(base*rate/100,2)` with the shared helper |
| 3 | Woo order create (pro) | pro `src/Integrations/WooCommerce/WCOrderProvider.php:407` | shared helper |
| 4 | Manual order (admin) | free `src/Admin/Pages/ManualOrderPage.php:609` | shared helper |
| 5 | Demo seeder | free `src/Demo/MarketplaceSeeder.php:685` | shared helper (test data; low risk) |
| 6 | Stripe Connect split (pro) | pro `src/StripeConnect/ConnectPaymentProcessor.php:147` `calculate_fee()` | read the order's persisted `platform_fee`; delete the independent `total*global_rate/100` math |
| + | Tiered rules (pro) | pro `src/TieredCommission/TieredCommissionManager.php:67` | move off `wpss_commission_rate` (percentage) onto `wpss_commission_fee` (amount); return flat directly, percentage as base*rate/100 |
| + | Plan override (pro) | pro `src/VendorSubscriptions/SubscriptionManager.php:133` | same — move onto `wpss_commission_fee` so it also reaches the Stripe split |

Consumers already reading the persisted breakdown (leave, just confirm): `EarningsController`,
`VendorAnalyticsController`, `OrdersController`, `Admin::…` fee display, `VendorProfileRepository`
total_commission, PayPal `PayoutsBatchService::get_pending_payouts` (confirm it sums ledger earnings).

Parity gate for every step: a pure-percentage global-rate order must produce identical
`platform_fee` + `vendor_earnings` before/after. Verify a flat rule AND a plan override both
flow through to the Stripe Connect `application_fee_amount`, not just the wallet.

QA verification card: Basecamp WP Sell Services / Bugs #10109128594.

---

## Consolidation COMPLETE (2026-07-19 — free `1a68189`, pro `24093f8`)

The design above is now implemented. Status per surface:

| # | Surface | Status |
|---|---|---|
| 1 | `CommissionService::calculate()` | ✅ delegates to the new `compute_breakdown(float $base, object $order)` static authority; `get_commission_rate()` → static `resolve_commission_rate()` |
| 2 | Standalone order create | ✅ repointed to `compute_breakdown` (partial order `{id:0,vendor_id,service_id}`) |
| 3 | Woo order create (pro) | ✅ repointed to `compute_breakdown` |
| 4 | Manual order (admin) | ⏸ intentionally NOT repointed — admin sets an explicit per-order rate in the form; re-resolving via the engine would silently override that choice. Admin authority preserved; low divergence risk (offline/admin-created). |
| 5 | Demo seeder | ✅ repointed; fixed `COMMISSION_RATE` constant removed (demo now matches the live engine) |
| 6 | Stripe Connect split (pro) | ✅ `filter_payment_intent_args` reads the persisted `platform_fee` (zero-decimal-aware → smallest unit), capped at the charge. `calculate_fee()` renamed `calculate_fee_fallback()` and used ONLY when order_id 0 / no persisted fee. |
| + | Tiered rules (pro) | ✅ percentage rules stay on `wpss_commission_rate`; FLAT rules resolve on `wpss_commission_fee` (return the flat amount, not "flat %"). Shared `find_matching_rule()` keeps both filters on the same rule. |
| + | Plan override (pro) | ✅ stays on `wpss_commission_rate` (percentage) AND re-asserts on `wpss_commission_fee` (prio 30) so an active override beats a flat tiered rule. Reaches the Stripe split automatically now that the split reads the persisted fee. |
| — | PayPal payout | ✅ already summed the persisted ledger `vendor_earnings` — confirmed, unchanged. |

**Why the split needed BOTH the creation-time repoint AND the persisted read:** the
Connect split runs at PAYMENT time, before completion. It reads the fee persisted
at order CREATION. So the creation-time sites had to resolve the full rate
(tiered/override via `wpss_commission_rate`) for the persisted value to be correct
when the gateway reads it. Repoint + gateway-read together kill the divergence.

**Precedence (highest first):** plan `commission_override` (%) → tiered rule
(% or flat) → per-vendor rate → global. Enforced by filter priorities:
tiered rate 20 / override rate 30 on `wpss_commission_rate`; tiered flat 20 /
override 30 on `wpss_commission_fee`.

**Verification (`wp eval-file`, 11/11 pass):** pure-% global order byte-identical
before/after; flat rule → flat fee; override 25% beats flat $10; Connect reads
persisted $50 → 5000 cents (not recomputed); both Pro filters wired at runtime.
PHPCS net-zero across all 7 files.

**Not yet done (separate cards):** #8 rules table "always Active" display
(`CommissionSettingsRenderer.php:148`); full end-to-end Stripe Connect 3DS split
verification in the browser with a real connected account (unit/DB path verified,
live 3DS pass still owed alongside the Stripe subscription trio).
