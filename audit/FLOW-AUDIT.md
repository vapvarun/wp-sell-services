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

### Triage results — the list was stale, the count was wrong

I repeated "36 remain" from `TASKS.md` without checking it. Cross-checked
against current code, a third of what I checked was already fixed and several
line references have drifted so far they no longer point at the described code.
**Treat every remaining item as unverified until re-checked**, and do not quote
the open count as if it were real.

**Already done — close these:**

| Item | Claim | Evidence it is fixed |
|---|---|---|
| 126 | Orders/Services pagination needs `count_by_customer()` | method exists in `OrderRepository` |
| 127 | H#6 profile notifications surface | `templates/dashboard/sections/notifications.php` present (item 122's own text also says H#6 shipped in `a4cad8f`) |
| 125 | H#2 "View all reviews" load-more | `load-more` wired in `frontend.js` |
| 49 | Commission rules table "always prints Active" | now reads `get_conditions()['is_active']`; no longer unconditional |
| 32 | Re-run checkout, verify fee/earnings persisted | order 112 persisted 45.00/5.00 via `compute_breakdown` |
| 28/30 | PayPal + Razorpay unregistered-action wall | refuted above — JS and PHP action names match |

**Confirmed still open:**

| Item | Claim | Evidence it is real |
|---|---|---|
| 38/39/40 | PayPal payouts re-pay, no lock, 500 cap | promoted to `MONEY-FLOW-PLAN.md` S6.5-S6.8 (P0) |
| 104/109/129 | Drag-dropped requirement files never submitted | `DataTransfer` appears 0 times in `requirements-form.js` |
| 112 | Messaging unread badge unindexed `JSON_CONTAINS` | still 3 occurrences in `ConversationRepository` |
| 88/124 | Manual Order page unbounded selects | no select2/AJAX search in `ManualOrderPage` |

**Line references have drifted — re-locate before trusting:**
45, 46, 47 (the Stripe subscription trio) cite lines in
`SubscriptionBillingHandler.php` / `StripeRecurringBilling.php` that no longer
contain the described code; the files have since moved under
`VendorSubscriptions/` and `RecurringServices/`. These are money and need a real
browser 3DS run, not a grep.

**Not yet re-checked:** 31, 59, 82/122, 83/123, 113, 114, 115 (~30 P3s), 128,
130, 135, 136, 139, 140, 141.

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
