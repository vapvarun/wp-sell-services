# Full flow audit — what we have vs what people expect

Purpose: for every flow a marketplace is expected to have, record **what exists**,
**what a site owner / buyer / vendor expects**, and **the gap**. Gaps become
tasks. This is the doc that decides what we build next.

Started 2026-07-23. **This is a skeleton with one flow completed.** Flows marked
`NOT AUDITED` have not been checked — that label means *unknown*, not *fine*.
Do not read an unaudited row as working.

Money detail lives in `MONEY-FLOW-PLAN.md`; rules in
`../docs/architecture/MONEY-FLOW.md`. This file must not restate them.

---

## Status legend

- ✅ audited, works, verified by running it
- ⚠️ audited, gap found
- ❌ audited, missing
- ❓ **NOT AUDITED** — unknown

---

## A. Money flow ✅ AUDITED

Full stage-by-stage audit in `MONEY-FLOW-PLAN.md`. Summary:

| Stage | State |
|---|---|
| Checkout + billing identity | ✅ verified |
| Charge (base currency, minor units) | ✅ verified at the Stripe API |
| Order money fields | ✅ (with the payment-vs-completion trap documented) |
| Completion → ledger credit | ✅ verified |
| Clearance hold | ✅ proven empirically |
| **Payout** | ❌ run never pays; PayPal rail re-pays every cycle (P0) |
| Refund / partial / dispute | ⚠️ 3 open (status gates, uncredited guard, partial UI) |
| Reconciliation / statements | ❓ never audited |

---

## B. Flows NOT yet audited

Each needs the same treatment: walk it as the actual user, at 390px and desktop,
on a theme we do not ship, and record have/expect/gap.

| Flow | Actor | Why it matters |
|---|---|---|
| Vendor onboarding → first service published | vendor | ❓ First-run experience decides whether a marketplace ever gets supply. |
| Service discovery: search, filter, sort, categories | buyer | ❓ Must work at 2000+ services — pagination, indexes, no N+1. |
| Order lifecycle: requirements → delivery → revision → approval | both | ❓ The core product loop. Partially exercised during refund work, never audited as a journey. |
| Messaging / conversations + attachments | both | ❓ Services are sold by conversation; this is not a side feature. |
| Reviews and ratings | both | ❓ Trust surface. |
| Buyer requests + proposals | both | ❓ Second marketplace direction. |
| Notifications + emails (every actor, every event) | all | ❓ Known duplicate-send bug class already found once (`1d6cb48`). |
| Vendor earnings dashboard | vendor | ⚠️ Touched during ledger work; balance states verified, rest not. |
| Admin moderation queue | admin | ❓ |
| Disputes as a journey (not just the money) | all | ❓ Money path verified; the human path not. |
| Multisite / RTL / translation | all | ❓ |
| Uninstall / data removal | admin | ❓ |

---

## C. Carried forward — NOT yet triaged

**`TASKS.md` holds 38 open items, including P0s.** They predate this sprint and
have not been re-checked against current code. Several may already be fixed;
several are almost certainly still live. Highest-signal, unverified:

- ~~**(P0) PayPal and Razorpay hit the unregistered-gateway-action wall**~~ —
  **REFUTED 2026-07-23.** The Stripe bug was a *mismatch*: the checkout JS posted
  `wpss_stripe_process_payment` and nothing listened. Both of these match:

  | Gateway | JS posts | PHP registers |
  |---|---|---|
  | PayPal | `wpss_paypal_create_order`, `wpss_paypal_capture` | both |
  | Razorpay | `wpss_razorpay_create_order`, `wpss_razorpay_verify_payment` | both |

  **Caveat:** matching action names proves the wiring exists, not that either
  flow works. Neither has been run. They stay ❓ until a real payment is put
  through each, to the standard set by order 112 (confirmed at the rail's API).

- (P2) Verify wallet credit + earnings roll-up on completion — largely covered
  by the order 112 run (`ledger #127 +45.00`); re-check against the item as
  written.

**Triage status: 8 of 38 done** (28/30, 29, 32, 38, 39, 40 and the duplicates
they collapse). Confirmed real and promoted into `MONEY-FLOW-PLAN.md` S6.5-S6.8:
PayPal payouts re-pay every run, bypass the ledger, have no batch idempotency,
and cap at 500 vendors. Item 32 (re-run browser checkout, verify
`platform_fee`/`vendor_earnings` persisted) is **done** — order 112 persisted
45.00/5.00 via `compute_breakdown`.

30 remain unchecked. Continue: mark each fixed / live / invalid
with evidence, fold the live ones into the flow rows above, then archive
`TASKS.md`.

---

## D. How to audit a flow

1. Walk it as the real actor in a browser — log out first (`?autologin=` no-ops
   when already logged in).
2. Desktop **and** 390px, on a theme we do not ship. Most installs are not on
   BuddyX or Reign.
3. Empty, error and loading states. Not just the happy path.
4. At scale where it is a list: 2000+ rows, pagination, `COUNT(*)`, no N+1.
5. Check the DB and the rail's own API, not just the screen.
6. Record what a user *expects* even where nothing is broken — a missing
   expectation is a gap, not a bug.
