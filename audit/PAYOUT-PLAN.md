# Scheduled Stripe payouts — plan

**Decision (2026-07-23):** vendors are paid on a **weekly / bi-weekly / monthly
schedule via Stripe**, above a minimum threshold. **Not** real-time split
payments. Money is held by the platform through clearance and only then sent,
so a refund inside the window never has to claw anything back.

---

## 1. Where we actually are

Verified against the code, not assumed:

| Piece | State |
|---|---|
| Cadence select (weekly / biweekly / monthly) | **built** — `Settings.php` `auto_withdrawal_schedule` |
| Minimum threshold | **built** — `auto_withdrawal_threshold`, default 500 |
| Cron scheduling | **built** — `EarningsService::schedule_auto_withdrawal_cron()`, hook `wpss_process_auto_withdrawals` |
| Clearance hold (14d) | **built and enforced** — proved: fresh credit `available 0.00`, 16-day-old credit `available 45.00` |
| Withdrawal gate | **built** — `request_withdrawal()` uses `get_summary()['available_balance']` under a row lock |
| The scheduled run pays anyone | **NO** |
| Stripe pays out on a schedule | **NO** |

### The two real gaps

**Gap A — the run stops at "pending".**
`EarningsService::create_auto_withdrawal()` inserts a `wpss_withdrawals` row with
status `WITHDRAWAL_PENDING` and returns. No rail is called. So "automatic
withdrawals" today means *automatically creating requests an admin must still
pay by hand*. The cadence and threshold work; the payout does not happen.

**Gap B — Stripe Connect is the wrong shape.**
`ConnectPaymentProcessor` injects `transfer_data` into the PaymentIntent, so
Stripe pays the vendor at CHARGE time and the money never reaches the wallet
(`ConnectLedgerBridge` docblock). That bypasses clearance completely and makes
refunds depend on `reverse_transfer`, which the same file says "fails routinely
... once the vendor has paid out to their bank there is nothing to pull."

PayPal is already the right shape (`PayoutsProviderInterface`, batch, scheduled).
Stripe is the odd one out.

---

## 2. Target design

Platform takes the **full charge**. Vendor share is credited to the wallet ledger
at completion, matures after `clearance_days`, and is sent by the scheduled run
through a payout rail. Same model for Stripe and PayPal. `reverse_transfer`
becomes unnecessary — nothing has left yet.

```
charge (full, platform)
  -> completion: ledger credit
  -> clearance window
  -> scheduled run: threshold check -> rail transfer -> ledger debit
```

---

## 3. Tasks

### PRO — P1. `StripeConnectPayoutsProvider implements PayoutsProviderInterface`
New: `src/StripeConnect/StripeConnectPayoutsProvider.php`
- `create_batch( array $items, string $currency ): array` — Stripe has no batch
  transfer endpoint, so this is N calls to `/v1/transfers` with
  `destination = <connected account>`. **Idempotency-Key per item**, derived
  from `(withdrawal_id)`, so a retried run cannot pay twice.
- `get_batch_status()`, `is_configured()` (Connect enabled + keys present).
- Partial failure is the normal case: return per-item results; one vendor's
  failed transfer must not roll back the rest.

### PRO — P2. Stop splitting at charge time
`src/StripeConnect/ConnectPaymentProcessor.php` — remove the `transfer_data` /
`application_fee_amount` injection. Platform takes the whole charge.

**Migration hazard, do not skip:** sites already running Connect have in-flight
orders that WERE split. After this change those vendors must not be credited
again at completion. `ConnectLedgerBridge` currently exists precisely to prevent
that double credit — it must keep handling pre-change orders while new orders
take the wallet path. Gate on something durable per order (presence of
`connect_transfer_id`), not on a global setting.

### PRO — P3. Retire the clawback path
`ConnectLedgerBridge::note_clawback_outcome()` and the `reverse_transfer` branch
become dead for new orders. Keep for legacy split orders; do not delete blindly.

### FREE — P4. Make the scheduled run actually pay
`src/Services/EarningsService.php`
- `create_auto_withdrawal()` currently ends at `pending`. Add a rail-execution
  step after it, fired through a seam free itself does not implement:
  `apply_filters( 'wpss_execute_payout', null, $withdrawal_id, $vendor_id, $amount, $method )`.
- Free's own behaviour stays manual (free has no rails) — unchanged for
  free-only sites. Pro's coordinator hooks the seam.
- On success: mark withdrawal `completed` and record the ledger debit via the
  existing `record_withdrawal_debit()`. On failure: leave `pending`, log, do not
  retry blindly.

### PRO — P5. Route the seam to a rail
`src/Payouts/PayoutMethodsCoordinator.php` — implement `wpss_execute_payout`,
dispatching to the Stripe or PayPal provider by the vendor's chosen method.
`is_stripe_rail_enabled()` / `render_stripe_rail()` already exist for the UI.

### FREE — P6. Stop clearance being switched off by accident
`Settings.php` `clearance_days` has `min => 0`. Zero silently deletes the entire
protection this model depends on. Raise the floor, or present it as
Weekly / Bi-weekly / Monthly so it reads as policy rather than a raw number.

---

## 4. Verification (none of this is done until these pass)

1. Threshold: vendor under it is skipped; vendor over it is paid.
2. Clearance: a credit inside the window is NOT paid out, even above threshold.
3. Cadence: weekly / bi-weekly / monthly each schedule and fire.
4. Real Stripe test-mode transfer to a connected account; confirm at the Stripe
   API, the same way the charge and refund were confirmed on order 112.
5. Idempotency: run the cron twice, assert ONE transfer and ONE ledger debit.
6. Partial failure: one bad destination does not block the other vendors.
7. Refund during clearance: no `reverse_transfer`, no negative vendor balance.
8. Legacy split orders still reconcile — no double credit after P2.

---

## 5. Sequencing

P4 + P5 first (make the run pay, via PayPal which already works) — that proves
the scheduled-payout machinery end to end without touching how money is charged.
Then P1, then P2/P3, which are the risky ones because they change charge-time
behaviour and need the migration gate.

P6 any time; it is ten minutes.
