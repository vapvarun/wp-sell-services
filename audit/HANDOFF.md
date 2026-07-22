# HANDOFF — 2026-07-22

Resume document. Read this first, then `audit/REFUND-PLAN.md` for detail.
Previous session's handoff archived as `HANDOFF-2026-07-20.md`.

**Both repos are clean and committed.** Free `d64487d`+, Pro `4a48c11`.
Local DB restored to baseline (seed vendor 987655 = 120, 4 ledger rows).

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

### P2. Invoice display — last slice of task 12

GST is captured but displayed nowhere, so it cannot do the job it was added
for. Read from `$order->billing_address` (already decoded on the model).

- Admin order screen: billing address + company + GST
- Buyer order view: same
- Any invoice / export surface

### P3. Live Stripe charge — still UNVERIFIED

The flow is proven up to the point of charge; the charge itself never ran.
Blocked on automating Stripe's React-controlled Address Element. **A manual
browser run settles it in five minutes** — buy the $50 service as
`realcustomer`, whose billing profile is already complete.

### P4. Smaller

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
