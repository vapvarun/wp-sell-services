# Handoff — WPSS 1.6.0 bug sweep (2026-08-17)

Everything below is committed and pushed to branch **`1.6.0`** in both repos.
Nothing is left uncommitted.

## Process being followed (agreed with the owner)

Per card: **replicate first (data + code) → plan → fix → verify in the browser →
update `audit/manifest.json` → move to Ready for Testing with proof.**
QA cards are entry points, not specifications — several turned out to be
describing a symptom of a larger defect.

Board: project `45156734`. Bugs = `9381846253`, **Ready for Testing =
`9381846126`** (not `9381846060`, that is In Testing).

## Shipped today

| Commit | Card | What it actually was |
|---|---|---|
| `a5a0ce0` | 10208003415 | Vendors page: a Settings mapping with **no writer** |
| `c341923` | 10208047602 | **8 copies** of the page registry; the creator the admin UI calls set no slug |
| `2a45054` | — | `@since 1.6.1` → `1.6.0` sweep (pro) |
| `d0d4dd6` | 10208094430, 10208094503 | Proposal notification type defined since 1.0.0, **written by nothing** |
| `4264144` | 10208075086, 10208074988 | Dispute conversation split across **two stores**; order hid the dispute |
| `e29809c` | 10208183009 | Vendor links landed on the directory (**regression from `a5a0ce0`**) |
| `57eaee1` | 10208047602 (bounce) | Owner switch for the cart link |

### Cross-cutting things worth remembering

- **`DB_VERSION` is now `1.6.1`** (`SchemaManager`). It moves independently of
  `WPSS_VERSION`. A schema change inside an already-numbered release **must**
  bump it or `install()` short-circuits on `needs_update()` and the columns
  never appear. This cost me a debugging cycle.
- **Activation does not load `src/functions.php`.** The activation hook loads
  only the Composer autoloader, so any global helper called from `Activator`
  fatals and leaves the plugin *silently inactive*. Now required explicitly in
  `wpss_activate()`. Any future helper call from the activator is safe.
- **`is_email_service_handling()`'s `$covered_types`** must list any type that
  `EmailService` already mails, or the recipient gets the branded email **plus**
  a plain duplicate from `NotificationService::create()`.
- **`NotificationService::send()`** is a `switch` on type. A type with no `case`
  silently renders "You have a new notification." — add a case for every new type.
- **`action_url`** is now written by `create()` and rendered as the notification
  title link. It was a column/model property/REST field with no writer since 1.0.0.
- Theme-fit: **BuddyX puts `border: 2px solid transparent` on anchor buttons**,
  which outranks a single-class rule. Any `<a class="wpss-btn…">` needs its
  states pinned (see `unified-dashboard.css`), and `.min` + RTL rebuilt
  (`npm run rtl && npm run build:min`).

## In progress — NOT finished

**Card 10208094640 — proposal hire checkout.** Replicated, root cause partly
mapped, **no fix written yet**.

Confirmed at data level (order 3128 / `WPSS-9UEPWHXB`):
- `service_id = 0`, `platform = 'request'`, `payment_method = NULL`,
  `status = 'pending_payment'`
- the real title is in `meta.proposal_snapshot.request_title`

Three sub-problems from the card:
1. Line title shows "Service Checkout" (the checkout **page** name) instead of
   the request title.
2. The form posts `service_id=297` — the checkout page ID.
3. Offline Pay leaves the order `pending_payment` with no receipt.

Where to look: `src/Integrations/Standalone/StandaloneCheckoutProvider.php`
- `render_checkout_shortcode()` → `render_pay_order_checkout()` (line ~360)
- `build_proposal_service_placeholder()` — **the likely source of (1) and (2)**;
  it fabricates a service when `service_id = 0` and I suspect it falls back to
  the current page. Read this first.
- `render_checkout_form()` (line ~486) is shared by cart checkout and pay_order
  via `$is_pay_order`. There are **two `wpss_checkout` nonce sites** (lines ~886
  and ~1655) — check whether that is two form implementations drifting, which is
  this codebase's recurring defect.
- For (3), compare against the cart Offline path, which QA says works.

**Card 10208199238** ("Admin shows Service Deleted for proposal orders") is the
**same `service_id = 0` root cause** — fix both together.

## Remaining Bugs (13 after the moves above)

Natural groups:

- **`service_id = 0` / proposal order identity:** 10208094640 (in progress),
  10208199238
- **"guard hides the CTA and nothing replaces it"** — same shape as the dispute
  CTA already fixed: 10208211608 (admin order hides open dispute),
  10208142348 (review CTA hidden, review never shown)
- **Page-registry follow-ups:** 10208199338 (`/create-service/` published but
  empty — note `create_service` is a *virtual route* in
  `wpss_get_default_page_slugs()`, deliberately not in the page registry;
  the page should probably not exist, or should carry the wizard)
- **Standalone:** 10208211769 (Settings `?tab=` links open General — 1.3.0 moved
  to hash routing, so these links are stale), 10208142467 (Become a Vendor still
  offers Register to existing vendors), 10208075268 (messages unread inflated),
  10207973462 (sticky sidebar vs theme header)
- **Older//larger:** 10138985537 (P1 i18n), 10154919636 (normalise API),
  10163575694 (guest purchase — owner decided: always create an account with
  First/Last/Email), 10154920673 (push notifications)

## Open questions for the owner

1. **Losing bidders now get notified.** `reject_other_proposals()` fired nothing,
   so sellers who lost were rejected silently — fixed. But hiring on a request
   with 200 proposals now sends 200 notifications **and** 200 emails at once.
   Acceptable, in-app only, or batched?
2. **Push notifications (10154920673)** — Free or Pro? Blocked until decided.
3. ~~**Release exposure** — hotfix or wait?~~ **DECIDED 2026-08-17: no hotfix.
   1.6.0 is the release vehicle.** Do not cut `v1.4.1`.

   Consequence to hold onto: the latest tag is still `v1.4.0`, so the three
   shipped fatals fixed in `631e711` — including the customer-reported one —
   stay live for customers **until 1.6.0 ships**. That makes shipping 1.6.0 the
   priority, not an open-ended bug sweep. Bank the fixes that are done rather
   than widening scope.
4. **Cart link default** — currently off. Flip to on when the site has zero
   published WooCommerce products? (One-line change; I kept it off because
   "no products today" isn't proof of none tomorrow.)

## Sandbox state (local only, not product changes)

- 17 orphan `cart-N` pages → **Trash** (recoverable), to prove clean-install
  behaviour.
- QA repro page "WPSS RFT Bug Repro Vendors" deleted for the same reason.
- `wpss_general['use_marketplace_cart_link']` left **on** while testing — turn
  it off to see default behaviour.
- Test data created: proposals #26–28, orders #3129+, dispute #22 replies.
