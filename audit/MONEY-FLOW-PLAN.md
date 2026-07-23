# Money flow — audit and plan

**Single source for ALL money work.** Charge, commission, credit, clearance,
payout, refund, dispute, reconciliation. Payout is one stage of this, not a
separate project.

- Rules that govern the code: `docs/architecture/MONEY-FLOW.md`
- Refund sprint detail and history: `audit/REFUND-PLAN.md`
- HANDOFF.md restates none of this; it links here. **If docs disagree, this
  file wins.**

Last updated 2026-07-23.

---

## 0. The decisions that shape everything

1. **The platform holds the money.** Buyer pays the full amount to the platform;
   the vendor is credited in our ledger and paid later. No real-time splits.
2. **Payout on a schedule** — weekly / bi-weekly / monthly, above a threshold,
   after a clearance window. A refund inside the window finds the money still
   unpaid, so **nothing is ever clawed back from a vendor**.
3. **Never force a rail on the site owner.** We do not know what they have —
   Stripe Connect is not available everywhere and many owners pay by bank
   transfer. **Manual/CSV is the default and first-class.** Stripe and PayPal
   layer on top. A site with no gateway configured must have a complete flow.
4. **Gateways are rails.** They report whether money moved. Every amount, split,
   commission and log belongs to this plugin.
5. **One authority per concept**, and money moves idempotently everywhere.

---

## 1. The full flow, stage by stage

Each stage: what it does, whether it is verified, and what is open.

### Stage 1 — Checkout and billing identity ✅ DONE

Billing captured on WooCommerce's own `billing_*` user meta (+ `billing_gst`),
249 countries / 148 currencies from one helper each, snapshotted onto the order
at payment so a later profile edit cannot rewrite an invoice, and displayed via
one partial on buyer + admin screens.

Verified: `6b15377`, `2dc698b`. Nothing open.

### Stage 2 — Charge ✅ DONE

Full amount to the platform, stored once in **base currency**, correct minor
units (3-decimal and zero-decimal currencies included).

Verified end to end against the Stripe API on order 112: `pi_…` succeeded,
5000 usd, metadata `order_id: 112`. Adapter boundary holds — Pro's
`WCOrderProvider` reads OUR package price and the site's base currency, not
Woo's (`4a48c11`).

### Stage 3 — Order money fields ✅ DONE, with a trap

`vendor_earnings` / `platform_fee` are written on the order **at payment**, via
the single commission authority (`CommissionService::compute_breakdown`).

⚠️ **The trap:** payment does NOT credit the vendor. The ledger row is created
at *completion*. A paid in-flight order therefore has `vendor_earnings = 45.00`
and **zero ledger rows** — confirmed on orders 40, 41 and 112. See Stage 6 open
item 3.

### Stage 4 — Completion → ledger credit ✅ DONE

`order_earning` row written at completion. Verified on order 112:
ledger `#127 +45.00`, balance 45.

### Stage 5 — Clearance ✅ BUILT AND PROVEN

`clearance_days` (default 14) holds credits from withdrawal. Proved
empirically: a credit dated today reports `available 0.00` at `ledger 45.00`;
the same credit at 16 days reports `available 45.00`.
`request_withdrawal()` gates on `get_summary()['available_balance']` under a row
lock — the one balance authority, not a re-derivation.

**S5.1 — ✅ CLOSED 2026-07-23, but the OPPOSITE way round (owner decision).**
T3 (`e32f17c`) floored clearance at 1 day. The owner has since ruled that the
hold is **their** policy call, not ours: clearance now **defaults to 0 (no
hold)** and 0 is a supported value, with 7 / 14 / 30 offered as the owner's
refund window. Zero is safe here because the wallet ledger records a
refund-after-payout as a negative balance that future earnings clear (2.1) —
the platform never silently absorbs it. One accessor
(`EarningsService::get_clearance_days()`) now owns the value so the field,
activator and runtime cannot drift. Both modes verified: 7 holds recent credits
out of `available_balance`, 0 makes the full ledger balance available.

Original finding (superseded): `clearance_days` had `min => 0` in `Settings.php`. Zero silently
deletes the entire protection the model depends on. Raise the floor, or present
as Weekly / Bi-weekly / Monthly so it reads as policy. *(10 minutes.)*

### Stage 6 — Payout ❌ THE BIG GAP

Cadence, threshold and cron scheduling are built
(`auto_withdrawal_schedule`, `auto_withdrawal_threshold`,
`schedule_auto_withdrawal_cron()`). **Nothing actually pays.**

**S6.1 The run stops at "pending" — ✅ RESOLVED for the manual rail (2026-07-23, T1).**
`create_auto_withdrawal()` still inserts rows at `WITHDRAWAL_PENDING` — that is
now correct by design: pending IS the manual batch, and the ending exists
(admin: export → pay offline → mark paid). Automated rails picking rows up from
`pending` is T4-T6 work.

**S6.2 CSV export — ✅ DONE 2026-07-23 (T2).**
`WithdrawalsPage::export_csv()` (admin_post, nonce + cap) streams the current
status/method filter as CSV, keyset-batched at 500 rows, columns a bank or
PayPal bulk upload needs. Free-side — the plan's "reuse `Analytics/DataExporter`"
premise was wrong: that class is **Pro-only**, and the manual rail must be
complete on free-only sites. **Export never mutates status** (rule 2.4) —
verified: exported, then confirmed statuses unchanged.

**S6.3 "Mark paid" terminal step — ✅ DONE 2026-07-23 (T1).**
`EarningsService::mark_paid()` (manifest → `money_authorities.payout_terminal`):
row lock (`FOR UPDATE`), terminal-state guard, status flip + ledger debit in ONE
transaction. Idempotent — verified twice = one debit (browser + CLI replay, and
REST replay returns 400 `wpss_already_finalised`). Every path routes through
it: `process_withdrawal('completed')`, admin single + bulk, REST
`PUT /withdrawals/{id}` — the REST controller's duplicate inline UPDATE (a
second money path with no notification and no ledger guarantee) was removed.
Admin screen: Mark-paid on pending AND approved rows, method filter,
`(status, created_at)` + `method` indexes, `wpssConfirm` bulk, empty/filtered
states, 390px, verified at 2003 rows (0.7 ms page query).

**Open — S6.5 PayPal payouts RE-PAY vendors every run. P0.**
`PayoutsBatchService::get_pending_payouts()` computes what a vendor is owed as
`SUM(vendor_earnings)` over completed orders `WHERE payment_status != 'paid_out'`
— but **nothing anywhere writes `paid_out`**. The string appears exactly twice in
both repos: once in a docblock, once in that SELECT. So every run computes the
same amount again. Run a batch twice and the vendor is paid twice; put it on a
weekly cadence and they are paid weekly, forever.

**Open — S6.6 PayPal payouts bypass the ledger entirely. Architectural.**
That query reads `wpss_orders.vendor_earnings` directly. It never consults
`wpss_wallet_transactions`, so it is a **second money authority** — violating
rule 2.1 — and it ignores clearance completely, paying out earnings that have
not matured. Any withdrawal already taken through the wallet is invisible to it.

**Open — S6.7 No idempotency on batch creation.**
`create_batch()` takes no lock or transaction, so a double-click or two admins
acting at once create two batches. Compounds S6.5.

**Open — S6.8 Payout vendor query caps at 500 with an N+1.**
`VendorPayoutProfileService` uses `'number' => 500` and loops
`foreach ( $user_query->get_results() … )`. Vendor 501 is silently never paid.

**Open — S6.9 PayPal Payouts is gated behind PayPal approval.**
Verified against PayPal's own docs 2026-07-23. The Payouts API (formerly Mass
Pay) is still supported, but it is **not enabled on Business accounts by
default**: it needs a verified Business account plus an application stating
business type, purpose and expected volume, and approval takes days. Sandbox
works without approval; **production does not**.

Two consequences:

1. A site owner can enter valid credentials, pass every settings check, and
   still be unable to pay anyone. The code must surface "not approved yet" as a
   distinct, explainable state — not a generic API failure. **Whether it does is
   unverified.**
2. PayPal Payouts is a **third** set of credentials for the owner to obtain:
   the PayPal *checkout* gateway has its own client ID/secret, and Payouts uses
   a separate pair (`PayoutsSettingsRenderer`). Nothing in the UI explains that
   they are different.

This strengthens decision 3 rather than weakening it: **even PayPal is not
universally available.** Manual/CSV — no credentials, no approval, works in
every country — is the only rail that can be relied on to exist, which is
exactly why it is the default.

**Consequence for the plan: T5/T6 must NOT build on `PayoutsBatchService` as it
stands.** PayPal payouts have to move onto the ledger and terminate in the same
mark-paid step as every other rail (T1). Treating PayPal as "the rail that
already works" — which is how it was sequenced — was wrong.

**Open — S6.4 Stripe Connect is the wrong shape.**
`ConnectPaymentProcessor` injects `transfer_data`, so per `ConnectLedgerBridge`:
*"Stripe pays the vendor's share straight to their connected account at CHARGE
time — the money never passes through the wallet."* That bypasses clearance
entirely and makes refunds depend on `reverse_transfer`, which the same file
says *"fails routinely … once the vendor has paid out to their bank there is
nothing to pull."* Directly opposed to decision 2.

### Stage 7 — Refund, partial refund, dispute ✅ MOSTLY DONE

One proportional formula (`wpss_get_refund_vendor_share`), one shared settle
path, clamped partials, idempotent reversal. Verified against the Stripe API on
order 112: `re_… succeeded 5000 usd`, exactly one refund, ledger `#128 −45.00`.
Dispute full + partial verified. `apply_refund_status()` now writes the amount,
transitions, and undoes the write if the transition is refused (`085cf14`).

**Open — S7.1 Refundable statuses are duplicated and contradictory.**
`AjaxHandlers` case `'refund'` allows `pending_payment` / `pending_requirements`
/ `accepted`; the admin button allows `completed` / `cancelled`
(`Admin.php:1794`). Neither covers `in_progress`, `delivered`, `revision`,
`late`, `on_hold`. **Nobody owns "which statuses are refundable."**

**POLICY DECIDED 2026-07-23 (owner): if the buyer PAID, it is refundable — at
any stage.** Do not gate refunds on workflow progress. The reason is the common
real case: quality problems surface *after* delivery, so `delivered`,
`revision`, `late`, `on_hold` and `in_progress` must all be refundable, not just
the two disjoint sets we have today. An unpaid order stays out of scope simply
because there is nothing to refund.

Implementation: ONE authority (e.g. `wpss_order_is_refundable( $order )`)
consulted by the AJAX path, the admin button and REST — replacing both hardcoded
lists — with a filter so a site owner can tighten it. **T10 must land first**
(see S7.2): widening the gate before the uncredited-order guard exists would
debit vendors who were never credited.

**Open — S7.2 A refund on a paid-but-uncredited order would debit a vendor who
was never paid.** `reverse_earnings_for_refund()` guards only on
`vendor_earnings === null`, but Stage 3's trap means a paid order has earnings
set with no ledger row. Currently masked because the transition is refused —
**fix this before widening S7.1's gate**, or the vendor goes to −45 for money
they never received.

**Open — S7.3** Admin metabox offers only full refunds; the server already
accepts `refund_amount`.

### Stage 8 — Reconciliation and reporting ⚠️ UNAUDITED

Pro's `LedgerExporter` reads `balance_after` for statement CSVs. Not reviewed
this sprint. Assume nothing.

**Open — S8.1** `CLI/PreflightCommand.php:591` counts gateways via
`apply_filters([])`, so its "N registered" figure under-reports (same bug class
as the P0 that meant buyers were never refunded; cosmetic here).

---

## 2. Target payout flow (Stage 6 detail)

```
charge (FULL, platform) → completion credit → clearance
  └─ scheduled run: matured balance ≥ threshold
       └─ payout batch (pending)
            ├─ manual  → CSV → admin pays offline → MARK PAID
            ├─ stripe  → /v1/transfers → MARK PAID on success
            └─ paypal  → batch payout → MARK PAID on success
                 └─ MARK PAID (one terminal step) → ledger debit
```

**The admin's actual flow** — this is the acceptance test:

1. Settings → Payouts: cadence, threshold, clearance, and **payout method:
   Manual (CSV) / Stripe / PayPal**. Manual is default and needs no config.
2. Run fires. Admin sees a **Payouts** screen: vendor, matured amount, method,
   status.
3. Manual: *Export CSV* → pay by bank/PayPal outside the system → *Mark Paid*
   (per row and bulk).
4. Stripe/PayPal: rows settle themselves; failures stay `pending` with the error
   visible and retryable. A partial failure never blocks the batch.
5. Marked-paid writes the ledger debit; vendor balance drops.
6. Vendor sees the payout and its status in their dashboard.

Anything an admin cannot do from that screen is not done.

---

## 3. Tasks, sequenced

Manual path first — it ships a complete flow for every site owner on earth with
no gateway dependency. Then rails. Charge-time changes last, because they carry
migration risk.

| # | Repo | Task | Stage |
|---|---|---|---|
| ~~T1~~ | free | ✅ DONE 2026-07-23. `mark_paid()` keystone + Withdrawals screen upgraded (NOT rebuilt — the page existed; a second screen would have duplicated it) | S6.1 S6.3 |
| ~~T2~~ | free | ✅ DONE 2026-07-23. Free-side `export_csv()` — `DataExporter` premise was wrong (Pro-only). Export never mutates status, verified | S6.2 |
| ~~T3~~ | free | ✅ CLOSED 2026-07-23 — clearance is the OWNER's call: default 0 (no hold), 0 supported, 7/14/30 offered; one accessor owns the value. Supersedes `e32f17c`'s 1-day floor | S5.1 |
| T4 | free | Rail seam `apply_filters( 'wpss_execute_payout', … )`. Free implements nothing — free-only sites stay manual, and that is a complete flow | S6 |
| T5 | pro | `StripeConnectPayoutsProvider implements PayoutsProviderInterface` — N × `/v1/transfers`, **Idempotency-Key from withdrawal_id**, per-item results | S6.4 |
| T6 | pro | `PayoutMethodsCoordinator` implements the seam, dispatching by vendor method | S6.4 |
| T7 | pro | Remove `transfer_data` from `ConnectPaymentProcessor` | S6.4 |
| T8 | pro | Retire the `reverse_transfer` clawback path for new orders | S6.4 |
| T9 | free | One authority for refundable statuses + policy decision | S7.1 |
| T10 | free | Guard reversal against uncredited orders — **before T9** | S7.2 |
| T11 | free | Admin partial-refund input | S7.3 |
| T12 | free | Audit `LedgerExporter` / reconciliation; fix Preflight gateway count | S8 |

**T7 migration hazard — do not skip.** Sites already on Connect have in-flight
orders that WERE split; those vendors must not be credited again at completion.
`ConnectLedgerBridge` exists to prevent exactly that double credit and must keep
handling pre-change orders while new orders take the wallet path. Gate on
something durable per order (`connect_transfer_id` present), never a global
setting.

---

## 4. Verification — nothing is done until these pass

**Payout**
1. Threshold: under → skipped; over → included.
2. Clearance: a credit inside the window is NOT paid, even above threshold.
3. Cadence: weekly / bi-weekly / monthly each schedule and fire.
4. **Manual path end to end on a site with NO Stripe and NO PayPal configured**:
   batch → CSV → mark paid → ledger debit → vendor balance drops.
5. Mark-paid idempotency: twice = one debit.
6. Export does not mutate status.
7. Real Stripe test-mode transfer confirmed **at the Stripe API** — the standard
   set by order 112, not our own DB columns.
8. Cron run twice → ONE transfer, ONE debit.
9. One bad destination does not block the batch.

**Refund**
10. Refund during clearance: no `reverse_transfer`, no negative balance.
11. Refund on a paid-but-uncredited order does not debit the vendor.
12. Legacy split orders still reconcile after T7 — no double credit.

**Every surface**
13. Payouts list at 2000+ rows: paginated, `COUNT(*)`, indexed, no N+1.
14. 390px, dark mode, RTL, empty/error/loading states.
15. Verified in a browser, not by reading code — every money defect found so far
    was found by running the flow while automated checks passed.
