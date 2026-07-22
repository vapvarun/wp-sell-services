# HANDOFF — 2026-07-22

Resume document. Read this first, then `audit/REFUND-PLAN.md` for detail.
Previous session's handoff archived as `HANDOFF-2026-07-20.md`.

**Both repos are clean and committed.** Free `2dc698b`+, Pro `4a48c11`.
Local DB is NO LONGER at the old baseline — the P3 Stripe run added order 112
and ledger rows #127/#128 (they net to zero). See the test-data note in §0.

---

## 0. Read these before touching anything

1. **`~/.claude/workflows/wbcom-account-billing-standard.md`** — the portfolio
   standard locked this session. Billing identity lives on the USER; gateways
   are RAILS that only report whether money moved; the plugin owns every
   calculation and every log. Backed up at `~/claude-backup/workflows/`.
2. **`audit/manifest.json`** → `money_authorities`,
   `ecommerce_adapter_boundary`, `portfolio_standards`. One authority per
   concept, and which one it is.
3. **`audit/REFUND-PLAN.md`** — Parts I-IX: plan, corrections, QA results.

**Environment gotchas that cost time this session:**

- `?autologin=` **no-ops when already logged in.** Log out first, or you will
  silently test as the wrong user. This caught me three separate times.
- `.min` assets are what ship. Run `npm run build:min` after ANY JS/CSS edit —
  `Assets::filter_loader_src()` swaps to `.min` unless `SCRIPT_DEBUG`.
- Use the host Playwright MCP, not Docker (Docker cannot resolve `.local`).
- WooCommerce is installed but INACTIVE. Activate to test Woo paths, deactivate
  afterwards.
- **Local test data added 2026-07-22:** `realcustomer` (user 2) now has a
  complete US billing profile (Dana Whitfield / Northwind Analytics LLC / GST
  `27AABCU9603R1ZX`), and order 41 carries the matching snapshot. Written
  through the real `wpss_save_billing_address()` path. Ledger baseline is
  untouched. Order 18 deliberately has NO snapshot — keep it that way, it is
  the pre-1.5.0 degradation case.

---

## 1. DONE and verified (14/14 automated + browser)

### Money flow — complete

| Behaviour | Verified |
|---|---|
| Earn credits vendor | +90 |
| Full refund reverses | −90 |
| Partial refund proportional | −36 on $40 of $100; remainder 54.00 left on the order |
| Replay / double-click | idempotent, exactly one reversal row |
| Rail parity (D4a) | identical across standalone / woo / surecart / edd |
| Negative balance | allowed, self-clears from future earnings |
| Connect double-payment | offset row, nets to zero |
| Connect clawback FAILURE | falls back to a wallet debt |
| Dispute full + partial | reverses correctly; amount recorded on dispute AND order |
| Woo refund | real `wc_create_refund()`; status `refunded` not `cancelled`; ONE reversal |
| Admin refund | clicked through the real wp-admin UI |

### Four P0s fixed — all found by REAL-FLOW testing, none by reading code

1. **Refund never reversed vendor earnings** (`7a47aab`) — buyer refunded,
   vendor kept the money.
2. **`partially_refunded` had no handler at all** (`11d48c6`) — buyer told they
   were refunded; nothing happened.
3. **Auto-refund never found the gateway** (`e6b77a5`) —
   `apply_filters('wpss_payment_gateways', [])` returns only Pro-registered
   rails, so for every Stripe/PayPal/offline order **the buyer was never
   refunded**.
4. **Every confirm dialog in wp-admin was unclickable** (`5406736`) —
   `design-system.css` is not enqueued in wp-admin, so the modal rendered
   `position:static` underneath `#adminmenuback`.

Also fixed: Woo/SureCart/FluentCart status hooks fataled under `strict_types`
(`8a99634`); dispute resolution sent every notification twice (`1d6cb48`).

### Profile / billing — data + capture complete

- 12 fields on WooCommerce's exact meta keys + `billing_gst` (`a86b867`)
- 249 countries, 148 currencies, correct minor units (`7655e99`)
- Order snapshot at payment — profile edits cannot rewrite past invoices
- Checkout block ABOVE the payment card, prefilled, collapses to a summary so a
  returning buyer enters card details only (`3f83379`)
- Save-back on payment + profile edit form (`cd38f4f`)
- Vendor profile country: select, with legacy free-text migration (`c714a51`)

---

## 2. PENDING — priority order

### ~~P1. Pro `WCOrderProvider`~~ — FIXED 2026-07-22 (Pro `4a48c11`)

Both violations closed. The provider now reads OUR `_wpss_packages` price
instead of `$item->get_total()` / `_line_total`, and stores the BASE currency
from the raw `woocommerce_currency` option instead of `$order->get_currency()`.

Verified with Woo active: a line charging 31.75 EUR against our 50.00 package
yields 50.00; with `woocommerce_currency` filtered to EUR — exactly what
CURCY/Aelia do — the stored currency stays USD.

**The money contract now has no known violations.**

### ~~P2. Invoice display~~ — DONE 2026-07-22 (free `6b15377`)

`templates/partials/billing-summary.php` is now the ONLY read-only billing
renderer; the buyer order view and the admin order screen both include it, so
the two surfaces cannot drift. Reads the order snapshot only — never the live
profile, which would print an address the buyer was not billed under.

One thing the plan did not anticipate: `billing_address` arrives in **two
shapes**. `ServiceOrder` decodes it to an array, but `Admin::render_order_detail`
passes a raw `$wpdb` row where it is still a JSON string. Both confirmed against
real rows. The partial handles both rather than making callers hydrate a model.

Verified on order 41 (full snapshot) and order 18 (none): identical content on
both surfaces, `US` → "United States", clean at 1280px and 390px, and order 18
renders nothing without disturbing the section after it.

Also styled `.wpss-order-detail-item`, which had no CSS anywhere and was
inheriting whatever the theme gave it.

**Billing identity is now complete end to end: captured, stored, snapshotted,
and displayed.**

### ~~P3. Live Stripe charge~~ — DONE 2026-07-22 (free `2dc698b`)

**Money moved, and came back.** Fully scripted — the old blocker (Stripe's
React-controlled Address Element) died with `3f83379`.

Order 112, `realcustomer` buys the $50 service:

| Stage | Evidence |
|---|---|
| Charge | `pi_3Tw2R4SY8ch105Oa0oAnnFqN` — **Stripe API** says `succeeded`, 5000 usd, metadata `order_id: 112` |
| Minor units | 5000 for $50.00 — correct |
| Currency | stored `USD` (base), not the display currency |
| Snapshot | written automatically at payment: 12 keys, company + GST |
| Split | 45.00 vendor / 5.00 platform |
| Completion | ledger `#127 order_earning +45.00`, balance 45 |
| Refund | clicked **Process Refund** in real wp-admin; confirm dialog `position:fixed z-index:160001` (the `5406736` P0 fix holds) |
| Refund at Stripe | `re_3Tw2R4SY8ch105Oa0Lau4aUw succeeded 5000 usd` — **exactly one**, no double refund |
| Reversal | ledger `#128 order_reversal -45.00`, balance back to 0 |

Order 112 is left refunded on the local site as the reference artefact.

**The run found a real bug** (see commit): a full refund showed the buyer no
amount at all, because `refunded_amount`'s NULL-means-full sentinel was being
read directly by the template. Now resolved once in
`wpss_get_order_refunded_amount()`. Same lesson as every other P0 this sprint —
reading the code would not have caught it; running the flow did.

### Payout model — ALREADY BUILT (verified 2026-07-23, no code written)

Asked for: "payout weekly / bi-weekly / monthly on a minimum threshold, via
Stripe Connect or manual CSV — so we never take money back from vendors."

**All of it exists.** Do not rebuild it.

| Piece | Where | Default |
|---|---|---|
| Cadence select | `Settings.php` `auto_withdrawal_schedule` | weekly (Mon) / biweekly (1st+15th) / **monthly (1st)** |
| Minimum threshold | `auto_withdrawal_threshold` | 500 (min 100) |
| On/off | `auto_withdrawal_enabled` | false |
| Cron | `EarningsService::schedule_auto_withdrawal_cron()` | reschedules on option save |
| Clearance hold | `wpss_payouts.clearance_days` | **14 days** |
| Rail abstraction | Pro `Payouts/PayoutMethodsCoordinator`, `PayoutsProviderInterface` | pluggable |
| Stripe Connect | Pro `StripeConnect/` | — |
| PayPal Payouts | Pro `PayPalPayouts/` (batch) | — |
| CSV | Pro `Analytics/DataExporter` | — |

**The clawback protection is real and enforced**, proved empirically: a credit
dated today reports `ledger 45.00 / in_clearance 45.00 / available 0.00`; the
same credit dated 16 days ago reports `available 45.00`.
`EarningsController::request_withdrawal()` gates on
`get_summary()['available_balance']` under a row lock — the ONE balance
authority, not a re-derivation. So a refund inside the window finds the money
still unwithdrawn and nothing is taken back from the vendor.

Note it is a **rolling per-credit hold**, not a calendar cycle: each credit
matures 14 days after ITS completion. That is stronger than a fixed cycle for
this goal, and worth stating before someone "fixes" it into cycle dates.

**Gaps worth deciding (not bugs):**

1. `clearance_days` has `min => 0` — a site owner can set 0 and silently delete
   the entire protection this model depends on. Consider a floor, or presets
   (Weekly / Bi-weekly / Monthly) that read as policy rather than a raw number.
2. Not verified: whether the auto-withdrawal cron itself honours clearance, and
   whether cadence + threshold have ever been run end to end. The settings and
   the cron scheduler exist; **the payout run was not exercised.**

### P4. Smaller

- **Two refund entry points with DISJOINT status gates.** `AjaxHandlers.php`
  case `'refund'` allows `pending_payment` / `pending_requirements` /
  `accepted`; the admin Process Refund button allows `completed` / `cancelled`
  (`Admin.php:1794`). Neither covers `in_progress`, `delivered`, `revision`,
  `late`, `on_hold`. Nobody owns "which statuses are refundable" — it is
  duplicated and contradictory.
- **`refunded_amount` is written BEFORE the status change, and is left lying if
  the transition is rejected.** Reproduced on order 40: `update_status(40,
  'refunded')` returned **false** for a paid `pending_requirements` order — the
  status AjaxHandlers explicitly advertises as refundable — but the column had
  already been set to 50.00. The order then reads as "$50.00 refunded" on every
  display surface while never having been refunded. Had to restore it by hand.
  Fix: write the amount only after the transition is accepted, or roll it back.
- **A refund on a paid-but-uncredited order would debit a vendor who was never
  paid.** `reverse_earnings_for_refund()` guards only on
  `vendor_earnings === null`, but a paid order carries `vendor_earnings=45.00`
  with ZERO ledger rows (credit lands at completion). Currently masked because
  the transition is refused — remove that gate without fixing this and the
  vendor goes to −45 for money they never received.

- **Paid in-flight orders cannot be refunded from admin at all.** The Process
  Refund button is gated to `completed` / `cancelled` (`Admin.php:1794`), so a
  buyer who paid and is sitting in `pending_requirements`, `in_progress` or
  `delivered` has no admin refund path — the admin must first move the order to
  cancelled. Disputes are covered by their own resolution UI, but the plain
  "paid, vendor never started, buyer wants out" case is not. Found during the
  P3 run. **Policy call, not a silent fix** — decide whether the gate should
  include every paid status.
- Admin partial-refund input — the server accepts `refund_amount`, the metabox
  UI only offers a full refund.
- Vendor profile `city` is still free text next to a country select.
- `CLI/PreflightCommand.php:591` counts gateways via `apply_filters([])`, so its
  "N registered" figure under-reports (same bug class as P0 #3; cosmetic here).

### P5. Findings NOT from this work (raised during QA, not fixed)

- **F1** `.wpss-btn--primary` hover does nothing — on BuddyX **and** Twenty
  Twenty-Four, despite the rule existing and the tokens differing. Affects every
  primary button in the plugin.
- **F2** `wpss-fullwidth-page` overflows horizontally on a generic theme
  (1312px inside a 1280px viewport). Most installs do not run our themes.

---

## 3. Release requirement

**Free and Pro must ship together.** Free's reversal fix and Pro's Woo status
change are two halves of one behaviour: free alone makes Woo reverse **twice**
(the old map still routes to `cancelled`); Pro alone stops Woo reversing at all.

`DB_VERSION` is now **1.5.0** (`refunded_amount`, `billing_address`).
The wallet-ledger backfill runs off its own `wpss_ledger_reconciled` flag, NOT a
version compare, so a release that forgets to bump cannot skip it.

---

## 4. The lesson worth carrying

**Every P0 this session was found by running the real flow, not by reading.**
16/16 automated checks passed while the admin confirm dialog was unclickable and
buyers were never being refunded.

Equally: **five plan premises turned out false** when actually checked —
`offline_submit` "pays vendors nothing" (it doesn't); the dispute double-wiring
mechanism; `wpss_disputes.refund_amount` "already stored" (that column was
dead); FluentCart and EDD "have refund handlers" (neither does). Treat every
audit claim as a lead, and reproduce before writing code.
