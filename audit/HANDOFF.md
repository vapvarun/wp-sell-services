# WP Sell Services 1.2.2 — session handoff

Last updated 2026-07-20. Branch **1.2.2** on both repos, everything committed
and pushed. Repos live at `~/dev/repos/wp-sell-services{,-pro}` (the Local site
symlinks to them).

---

## READ THIS FIRST — three things that will waste your time

1. **`.min` assets are what actually ship.** `Assets.php` rewrites asset URLs to
   `.min` when the file exists, so **edits to `assets/js/*.js` are inert until
   you rebuild**. `npm run build:min` fails (no node_modules). Use:
   `npx terser assets/js/FILE.js -c -m -o assets/js/FILE.min.js`
   This silently ate part of a session.
2. **Use the HOST Playwright MCP** (`mcp__plugin_playwright_playwright__*`), not
   the Docker one — the Docker browser cannot resolve `wp-sell-services.local`.
3. **`?autologin=N` does nothing if you are already logged in** (the mu-plugin
   bails on `is_user_logged_in()`). Log out first via the admin-bar logout URL,
   or you will silently test as the wrong user. This caused one false "bug".

---

## Environment

- Site: `http://wp-sell-services.local` · dashboard `/dashboard/`, sections are
  pretty-permalinked (`/dashboard/disputes/`). `orders` is the DEFAULT section,
  so bare `/dashboard/` IS the orders view.
- Auto-login: `?autologin=1` (admin `varundubey`, ID 1) / `?autologin=realcustomer`
  (buyer `realcustomer`, ID 2).
- Theme: **BuddyX 5.1.4** (GitHub `master`, ships built assets). BuddyX Pro /
  Reign not installed.
- Stripe: TEST keys + webhook configured and working. Webhook URL pattern
  `/wpss-payment/{gateway}/callback/`.
- Public tunnel (ephemeral — restart if dead):
  `https://gig-democrat-absolutely-timber.trycloudflare.com`
- **PayPal: `wpss_paypal_settings` is EMPTY.** Needs `client_id`,
  `client_secret`, `webhook_id` before any PayPal work can be tested.
- WooCommerce: installed but **deactivated** (activate to test the Woo path).
- Tour modal: suppress with `wp user meta update <id> wpss_tour_completed 1`.
- Seeded test data: order #41 (paid, real Stripe PaymentIntent), demo dispute #4,
  notifications for users 1 + 2, service #9 priced ($50 Basic / $100 Standard).

---

## What shipped this session

**Checkout was completely broken and is now fixed** — no gateway could complete a
purchase. Chain of three P0s, each proven by the error advancing:
`bare 0` (unregistered action) → "requires additional action" (unconfirmed
intent) → "requires a description" → "requires name and address" → **paid**.
End-to-end proof: order **41**, `payment_status=paid`,
`transaction_id=pi_3TvBOkSY8ch105Oa1irGYMdv`, commission 10% → `platform_fee`
5.00 / `vendor_earnings` 45.00. That was also the first real payment ever to
exercise the commission engine.

Clusters **D, E, F, G, H** closed (see `TASKS.md`), plus: Woo currency deferral,
uniform notifications partial, PayPal pay_order guards, currency-aware amount
matching, 3-decimal currency fix, commission on EDD/renewal/manual orders, the
wallet balance consolidation, and the setup-wizard credential removal.

---

## NEXT — in priority order

### 1. Refund — CRITICAL, and **UNVERIFIED**
Card `10110740922`. Claim: 8 divergent refund implementations; only the
`cancelled` path reverses earnings, so a refund leaves the vendor credited and
**the platform pays twice**; Woo maps refund→`cancelled` while SureCart
maps→`refunded`.

**This came from a sub-agent audit and I never verified it at code level.** Both
wallet claims that came from the same sweep turned out to be *understated*, so
treat this as a lead, not a spec. **Read the eight paths first** (listed in
`DUPLICATE-FLOWS-money.md` §2), confirm with a seeded refund, then design one
`RefundService`.

### 2. Finish the commission card
Card `10110741743`. Three of four paths done. Remaining:
- pro `API/PaymentController.php:341` — 4th order-creation path, still NULL commission
- `TippingService.php:376` — last hand-rolled fee math
- Not yet done: a live EDD purchase / renewal proving non-NULL `platform_fee`.

### 3. Order status authority
Card `10110741858`. Six writers bypass `OrderService::update_status()`. Largest
blast radius — do it with a regression pass over every platform integration.

### 4. UI duplications
Card `10110742943`, full list in `DUPLICATE-FLOWS-ui.md`. Best first picks: the
search-shortcode param contract (silently disables moderation + vacation
filtering), then the four read/write key mismatches (each shows a customer a
wrong number), then service card ×6.

### 5. Gateways
PayPal `10110287493` / Razorpay `10110288339` — both hit the original
unregistered-action wall. The seam already exists: declare
`data-wpss-own-submit` on the gateway's payment-fields container and let its own
script own submit (see `StripeGateway` + `StandaloneCheckoutProvider`).

### 6. Before tagging 1.2.2
Version bump, changelog, and the **mandatory Docker install test** of the built
zip with Reign + free/pro. QA should re-run a full checkout now that it works.

---

## Blocked on the owner

- **Wallet formula** — resolved for now (derived ledger sum is canonical), but if
  you want `balance_after` retired as a column entirely that is a schema call.
- **Multi-currency storage contract** (card `10110476797`) — does an order row
  store base currency + rate, or the shopper's currency? `platform_fee` /
  `vendor_earnings` are persisted per order, so payouts and the Connect split
  depend on the answer. Nothing should be coded until this is decided.

---

## The rule that came out of this session

Codified in `CLAUDE.md` → **"ONE FLOW, ONE IMPLEMENTATION"**. Every serious bug
found this session was the same shape — the same flow implemented more than once,
copies drifting apart:

| Flow | Copies | Customer impact |
|---|---|---|
| Stripe checkout | 2 | checkout could not complete at all |
| Commission fee math | 6 | wallet ≠ Stripe Connect split |
| Wallet balance | 2 families | balance shown as 9999 vs true 120 |
| Notifications | 3 | two surfaces broken |
| Wizard vs Settings | 2 writers | PayPal credentials silently discarded |
| Featured services | 2 meta keys | shortcode returned nothing |

**Verification discipline that mattered:** unit-level `wp eval-file` checks passed
for months while checkout was 100% broken. Nothing counts as verified until the
real user flow runs. When a fix lands, prove it by showing the *behaviour change*
(error advancing, DB row before/after), not by asserting it.

**Audit inventories:** `DUPLICATE-FLOWS-money.md` (9 families),
`DUPLICATE-FLOWS-ui.md` (17), `TASKS.md` (the 1.2.2 sprint, cluster J = checkout).
