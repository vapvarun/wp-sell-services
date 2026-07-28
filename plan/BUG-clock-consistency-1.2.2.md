# BUG: the order lifecycle is written in one clock and swept in another

**Branch:** `1.2.2` (free) · paired with `wp-sell-services-pro` `1.2.2`
**Reported:** 2026-07-12 · **Status:** verified at code level, NOT yet fixed
**Severity:** P0 — member-facing, money-adjacent, and it damages vendor reputation

This is one root cause, not eight bugs. Everything below follows from it.

---

## The root cause

WordPress has more than one clock, and this plugin uses several without deciding which is canonical:

| Expression | Returns |
|---|---|
| `current_time( 'mysql' )` | the SITE's local time (Settings → General) |
| `current_time( 'mysql', true )` | UTC (the second arg is the GMT flag) |
| `gmdate(...)`, `time()`, `new DateTimeImmutable()` | UTC (WordPress pins PHP's default timezone to UTC) |
| SQL `NOW()` | the DATABASE SERVER's clock — usually UTC in production, and independent of both |

**The order lifecycle is WRITTEN in site-local time and SWEPT by UTC crons.** On a UTC site the two
agree and nothing looks wrong, which is why this has survived. On any other site they diverge by the
site's offset — up to 14 hours.

---

## Worst symptom first: every order is marked late, hours early

`OrderWorkflowManager::check_late_orders()` (`src/Services/OrderWorkflowManager.php:176`) runs hourly,
on by default, and does:

```php
WHERE status = %s AND delivery_deadline < %s   // bound to current_time( 'mysql' )  -- SITE-LOCAL
```

But `delivery_deadline` is **UTC on every write path**:

| Writer | file:line | Clock |
|---|---|---|
| normal checkout | `src/Services/OrderService.php:485` | `new DateTimeImmutable('+N days')` → PHP default tz → **UTC** |
| milestones | `src/Services/MilestoneService.php:207` | `gmdate()` → **UTC** |
| admin manual order | `src/Admin/Pages/ManualOrderPage.php:692` | **UTC** |

So on a site at UTC+5:30, `current_time('mysql')` runs 5½ hours ahead of the deadline's own clock and
**every order flips to `late` 5½ hours before it actually is**. The vendor and the buyer are both
notified. The order's status changes.

It does not stop at a notification: `src/Database/Repositories/VendorProfileRepository.php:213` scores
each vendor's on-time rate as `completed_at <= delivery_deadline`. **The mismatch damages vendor
reputation on data that was never late.**

---

## The same defect, six more places

| # | Where | What breaks | Severity |
|---|---|---|---|
| 1 | `OrderWorkflowManager.php:176` | every order marked late early (above) | **P0** |
| 2 | `MilestoneService.php:207` + `:238` | **one INSERT writes `delivery_deadline` in UTC and `created_at` in site-local.** Two clocks in a single statement. | P1 |
| 3 | `MilestoneService.php:670`, `ExtensionOrderService.php:614`, `TippingService.php:132` | Abandon-cleanup crons threshold on `gmdate(time() - 48h)` (UTC) against a `created_at` written local. Daily, unconditional, **no admin opt-out**. A buyer's unpaid proposal is auto-cancelled early or late by the site's offset — on a UTC-8 site, after ~40 real hours instead of 48. | P1 |
| 4 | `EarningsService.php:82` | **Vendor payout clearance window.** `completed_at` (local) vs `DATE_SUB(NOW(), ...)` (database clock). On a UTC-X site, funds become withdrawable *before* the configured clearance has actually elapsed. Reachable via REST `/earnings/summary`, and it gates `request_withdrawal()`. | **P1 — money** |
| 5 | `Admin/Pages/ManualOrderPage.php:656,692,706` | This one admin path writes `created_at` / `completed_at` / `delivery_deadline` in **UTC** while every other path writes local. One column, a mixture. | P2 |
| 6 | `AuditLogService.php:278` | Retention `DELETE` on `NOW()` vs a local `created_at`. **Defaults to `0` = never delete**, so it only bites owners who opt in. | P2 |
| 7 | `EarningsService.php:261` | Same defect — but `get_by_period()` has **zero callers repo-wide**. Dead code. Fix opportunistically. | P3 |

**Explicitly NOT a bug:** `OrderRepository::get_overdue()` (`:291`) has the identical shape to #1 and
looks like an eighth finding. It is **dead code, zero callers.** Recorded so the next audit does not
re-find it and mistake it for the live bug in `check_late_orders()`.

---

## It is worse across the free/pro seam

`wpss_orders` is owned by **free**, but **pro writes into it directly** — and pro's convention is the
opposite of free's. The same column, `wpss_orders.created_at`, is written by five paths in two clocks:

| Writer | Plugin | Clock |
|---|---|---|
| `OrderService`, `OrderRepository`, `StandaloneOrderProvider`, `MilestoneService`, `TippingService`, `ExtensionOrderService` | free | **site-local** |
| `Admin/Pages/ManualOrderPage.php:656` | free | **UTC** |
| `RecurringServices/RecurringOrderFactory.php:90` | **pro** | **site-local** |
| `Integrations/WooCommerce/WCOrderProvider.php:440` | **pro** | **UTC** |
| `Integrations/EDD/EDDOrderProvider.php:577` | **pro** | **site-local** |

**A WooCommerce order and an EDD order placed in the same second on the same site are stamped hours
apart.** Then the 48-hour auto-cancel cron, the payout clearance window, and revenue-by-period
analytics all read that column as if it were coherent.

Pro-wide counts: **32 × `current_time('mysql', true)`** and **71 × `gmdate()`** (UTC) versus **10 ×
`current_time('mysql')`** (local). Pro is *mostly* UTC; free is *mostly* local. Neither is wrong on its
own. Together, in one table, they are.

### The seam diagnosis (per `~/.claude/workflows/free-pro-seam-standard.md`)

Pro raw-`INSERT`s into free's table because **free never exposed a door.** Per the standard: *"When you
find a raw write from EXT into a BASE table, the bug report is not 'EXT is sloppy' — it is 'BASE is
missing a method.'"*

Pro reaches into `wpss_orders` in roughly 30 places. Most are **reads for aggregates** (analytics
collectors, commission rules, vendor dashboards), which the standard permits. The **writes** are the
defect, and there are five files doing them: `RecurringOrderFactory`, `WCOrderProvider`,
`EDDOrderProvider`, `PayoutsBatchService`, `PaymentController`.

Ownership is otherwise clean: free owns 4 tables, pro owns 7 (`wpss_pro_*`), **no contested table** —
no dual-`dbDelta` collision.

---

## Proposed fix

**Pick ONE clock. Recommend UTC.** Money and deadlines must not move when an owner edits the site
timezone in Settings → General — and today they do.

1. **Free: add an order-creation/update service** that stamps timestamps. This is the missing door.
2. **Free: normalize every writer** to `current_time('mysql', true)`:
   `OrderService.php:208`, `OrderRepository.php:320`, `StandaloneOrderProvider.php:145`,
   `MilestoneService.php:238,539`, `TippingService.php:248,425`,
   `ExtensionOrderService.php:223,255,393,435`, `AuditLogService.php:129`.
3. **Free: normalize every reader** to UTC:
   `OrderWorkflowManager.php:176` (`gmdate('Y-m-d H:i:s')` instead of `current_time('mysql')`),
   `EarningsService.php:82,261`, `AuditLogService.php:278` — bind a PHP-computed UTC value rather than
   using SQL `NOW()`, so the database server's own timezone stops mattering.
4. **Pro: stop raw-writing into `wpss_orders`.** Route `RecurringOrderFactory`, `WCOrderProvider`,
   `EDDOrderProvider`, `PaymentController`, `PayoutsBatchService` through free's new service. Aggregate
   READS may stay.
5. **Both: one rule, enforced.** Add the two-clock check to each plugin's commit gate so this cannot
   come back. Detector: `~/dev/bin/portfolio-bug-scan.php`.

### Prove it before fixing it

Per the standard: *"Code-reading says 'this cannot work'; a red test says 'here it is, not working.'"*
Write these **first**, watch them fail, then fix:

- **Late sweep:** site at UTC+5:30. Create an order with a deadline 1 minute in the future (real UTC).
  Run `check_late_orders()`. It must NOT be late. **Today it is.**
- **Abandon cleanup:** site at UTC-8. Create a pending order, backdate real creation to 47h59m.
  Run `cleanup_abandoned_*()`. It must survive. **Today it is cancelled.**
- **Seam:** on a non-UTC site, create one order through free's checkout and one through pro's
  `WCOrderProvider` at the same instant. Assert their `created_at` values are equal. **Today they differ
  by the site's offset.**

### The migration is the hard part — do not guess

Existing `wpss_orders` rows are a **mixture** of local and UTC, and **nothing in the row records which
clock wrote it.** Do not silently rewrite payment history. Options, in order of safety:

1. **Normalize going forward only.** Accept that historical clearance windows and late-flags were
   already wrong. Safest; loses nothing.
2. **Infer per-row** from a paired column written by a known path (e.g. an order with a
   `platform_order_id` came from pro's WC provider → UTC). Only viable where the provenance is
   unambiguous.

**This is a business decision, not an engineering one.** It affects payout history. Escalate it.

---

## How this was found

Six of the seven bugs fixed in `wb-gamification` 1.6.4 were this same class. Hand-auditing for it does
not scale to a 122-repo portfolio, so it became a static detector
(`~/dev/bin/portfolio-bug-scan.php`), validated by pointing it at wb-gamification's pre-fix commit — it
independently rediscovered the bugs found by hand, then found one that had been missed.

Across the portfolio, the detector reported 43 HIGH candidates. **Most were refuted.** buddynext,
jetonomy, wpmediaverse, eventonomy and learnomy are **clean** — learnomy's drip scheduling in
particular is UTC end-to-end and well built. wp-sell-services is the one product where every claim
survived verification.
