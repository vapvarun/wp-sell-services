# Payout flow — plan and audit

**Single source for payout work.** HANDOFF.md must not restate any of this;
it links here. If something below conflicts with another doc, this file wins.

Last updated 2026-07-23.

---

## 0. The decisions

1. Vendors are paid on a **weekly / bi-weekly / monthly schedule**, above a
   **minimum threshold**. Not real-time split payments.
2. Money is **held by the platform through clearance**, then sent. A refund
   inside the window never claws anything back.
3. **The rail is the site owner's choice and we never force Stripe.** We do not
   know what an owner has — Stripe Connect is not available in every country,
   and plenty of owners pay by bank transfer. **Manual/CSV is the default and
   must be first-class**, not a degraded fallback.
4. **Every payout ends in "marked paid"** — by the rail on success, or by the
   admin by hand. That single terminal step is what writes the ledger debit.

---

## 1. Audit — verified state, not assumed

Checked against the code and, where marked, run.

| Piece | State | Evidence |
|---|---|---|
| Cadence select (weekly / biweekly / monthly) | **built** | `Settings.php` `auto_withdrawal_schedule`, default monthly |
| Minimum threshold | **built** | `auto_withdrawal_threshold`, default 500 |
| Cron scheduling | **built** | `EarningsService::schedule_auto_withdrawal_cron()`, hook `wpss_process_auto_withdrawals` |
| Clearance hold | **built + PROVEN** | fresh credit → `available 0.00`; same credit at 16 days → `available 45.00` |
| Withdrawal gate honours clearance | **built** | `request_withdrawal()` reads `get_summary()['available_balance']` under a row lock |
| Manual withdrawal → admin marks paid | **built** | free's only payout model |
| **Scheduled run actually pays** | **NO** | `create_auto_withdrawal()` inserts status `pending` and returns; no rail called |
| **Stripe pays out on a schedule** | **NO** | Connect splits at charge time instead (below) |
| **CSV export of a payout batch** | **NO** | `Analytics/DataExporter` exists but is not wired to a payout run |

### Gap A — the scheduled run stops at "pending"

`EarningsService::create_auto_withdrawal()` writes a `wpss_withdrawals` row at
`WITHDRAWAL_PENDING` and returns. So "automatic withdrawals" today means
*automatically creating requests an admin must still pay by hand*. Cadence and
threshold work. The payout does not happen.

Note this is **not useless** — it is exactly the manual flow we want to keep,
just unfinished. The batch it produces is what CSV export and mark-paid need.

### Gap B — Stripe Connect is charge-time split, not payout

`ConnectPaymentProcessor` injects `transfer_data`, so per `ConnectLedgerBridge`:
*"Stripe pays the vendor's share straight to their connected account at CHARGE
time — the money never passes through the wallet."*

That bypasses clearance entirely and makes refunds depend on `reverse_transfer`,
which the same file says *"fails routinely ... once the vendor has paid out to
their bank there is nothing to pull."* Directly opposed to decision 2.

---

## 2. Target flow

```
charge (FULL amount, platform account)
  └─ completion → ledger credit (vendor)
       └─ clearance window (default 14d)
            └─ scheduled run: eligible = matured balance ≥ threshold
                 └─ payout batch created (status: pending)
                      ├─ rail = manual  → CSV export → admin pays offline → MARK PAID
                      ├─ rail = stripe  → /v1/transfers → auto MARK PAID on success
                      └─ rail = paypal  → batch payout → auto MARK PAID on success
                           └─ MARK PAID (single terminal step) → ledger debit
```

**One terminal step.** Whether a rail confirmed it or an admin clicked it, the
ledger debit is written in exactly one place. No rail gets its own bookkeeping.

---

## 3. The admin's actual flow (think as the site owner)

This is the acceptance test for the whole feature.

1. Settings → Payouts: choose cadence, threshold, clearance days, and **payout
   method: Manual (CSV) / Stripe / PayPal**. Manual is the default and requires
   no configuration whatsoever.
2. The run fires on schedule. Admin gets a **Payouts** screen listing this
   batch: vendor, matured amount, method, status.
3. **Manual:** admin clicks *Export CSV* → pays each vendor by bank transfer or
   PayPal outside the system → returns and clicks *Mark Paid* (per row, and
   bulk for the batch).
4. **Stripe/PayPal:** rows settle themselves; failures stay `pending` with the
   error visible and are retryable. A partial failure must never block the rest
   of the batch.
5. Marked-paid rows write the ledger debit and the vendor's balance drops.
6. The vendor sees the payout and its status in their earnings dashboard.

Anything an admin cannot do from that screen is not done.

---

## 4. Tasks

Sequenced so the risky work comes last.

### FREE — P1. Finish the run: batch → mark paid *(do first)*
`src/Services/EarningsService.php`, admin Payouts screen.
- Keep `create_auto_withdrawal()` producing `pending` rows — that IS the batch.
- Add the terminal step: **mark paid** (single + bulk), which writes the ledger
  debit through the existing `record_withdrawal_debit()` and flips status to
  completed. Idempotent: marking twice must not debit twice.
- Admin Payouts screen: list, filter by status/method, export, mark paid.
- **Big-site**: this is a list — pagination, `COUNT(*)`, indexes on
  `(status, vendor_id)`, no N+1 on vendor lookups. 2000 vendors on day one.

### FREE — P2. CSV export of a payout batch
Reuse `Analytics/DataExporter` rather than writing a second exporter.
Columns must be what a bank/PayPal bulk upload actually needs: vendor, payout
email/account, amount, currency, reference/withdrawal id. Exporting must not
change status — **export and mark-paid are separate acts**, because an export
that auto-marked would lie the moment a transfer failed.

### FREE — P3. Rail seam
`apply_filters( 'wpss_execute_payout', null, $withdrawal_id, $vendor_id, $amount, $method )`.
Free implements **nothing** — free-only sites stay manual, which is a complete
and supported flow, not a limitation. Pro hooks the seam.

### FREE — P4. Stop clearance being switched off by accident
`Settings.php` `clearance_days` has `min => 0`; zero silently deletes the whole
protection. Raise the floor or present as Weekly / Bi-weekly / Monthly.

### PRO — P5. `StripeConnectPayoutsProvider implements PayoutsProviderInterface`
New: `src/StripeConnect/StripeConnectPayoutsProvider.php`
- Stripe has no batch transfer endpoint: N calls to `/v1/transfers`,
  `destination = <connected account>`, **Idempotency-Key derived from
  withdrawal_id** so a retried cron cannot pay twice.
- Per-item results; one failed vendor must not roll back the others.

### PRO — P6. Route the seam
`src/Payouts/PayoutMethodsCoordinator.php` — implement `wpss_execute_payout`,
dispatch by the vendor's chosen method. `is_stripe_rail_enabled()` /
`render_stripe_rail()` already exist for the UI.

### PRO — P7. Stop splitting at charge time
`ConnectPaymentProcessor` — remove `transfer_data` / `application_fee_amount`.
Platform takes the whole charge.

**Migration hazard — do not skip.** Sites already on Connect have in-flight
orders that WERE split; those vendors must not be credited again at completion.
`ConnectLedgerBridge` exists precisely to prevent that double credit and must
keep handling pre-change orders while new orders take the wallet path. Gate on
something durable per order (`connect_transfer_id` present), never on a global
setting.

### PRO — P8. Retire the clawback path
`ConnectLedgerBridge::note_clawback_outcome()` and the `reverse_transfer` branch
go dead for new orders. Keep for legacy split orders; do not delete blindly.

---

## 5. Verification — none of this is done until these pass

1. Threshold: under → skipped; over → included.
2. Clearance: a credit inside the window is NOT paid out, even above threshold.
3. Cadence: weekly / bi-weekly / monthly each schedule and fire.
4. **Manual path end to end**: batch → CSV → mark paid → ledger debit → vendor
   balance drops. On a site with **no Stripe and no PayPal configured at all.**
5. Mark-paid idempotency: twice = one debit.
6. Export does not mutate status.
7. Real Stripe test-mode transfer, confirmed **at the Stripe API** — same
   standard as the order 112 charge and refund.
8. Payout idempotency: run the cron twice → ONE transfer, ONE debit.
9. Partial failure: one bad destination does not block the batch.
10. Refund during clearance: no `reverse_transfer`, no negative balance.
11. Legacy split orders still reconcile after P7 — no double credit.
12. Admin Payouts list at 2000+ rows: paginated, no N+1.
13. 390px + dark mode on every new screen.

---

## 6. Sequencing

**P1 → P2 → P4** first: that delivers a complete, shippable payout flow for
every site owner on earth, with no gateway dependency at all. Then **P3 → P6 →
P5** adds Stripe as an option. **P7/P8** last — they change charge-time
behaviour and carry the migration risk.

A site owner with no Stripe must never be worse off than one with it.
