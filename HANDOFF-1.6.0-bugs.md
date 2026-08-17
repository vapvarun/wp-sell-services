# Handoff — WPSS 1.6.0 release (2026-08-17)

Everything below is committed and pushed to branch **`1.6.0`** in both repos.
Nothing is left uncommitted.

## The plan (owner's call, 2026-08-17)

**No hotfix. 1.6.0 is the release.** The route to shipping it:

1. **Clear every card in Bugs** — not a triaged subset. Bugs column empty is the
   gate.
2. **Keep `audit/manifest.json` in sync** with each change, in the same commit.
3. **Get the CI checks actually running** the gates (today most run only by
   hand — see "CI gap" below).
4. **Journey checks** over the touched surfaces before handing to QA.
5. **Then QA clears the RFT column.** QA verifies; they do not carry the fix
   work. Every card lands in RFT with replication + proof so they can.

Do NOT widen scope beyond this. The three fatals fixed in `631e711` stay live
for customers until 1.6.0 ships, so shipping is the objective.

## Per-card process (non-negotiable)

**Replicate first (data + code) → plan → fix → verify in the browser →
update `audit/manifest.json` → move to Ready for Testing with proof.**

QA cards are entry points, not specifications. Today, most turned out to be a
symptom of something larger — a store with no writer, or one flow implemented
twice and drifting. Fixing only the reported symptom would have left the real
defect shipping.

Two things earned their keep repeatedly and should not be skipped:
- **Browser-verify per item**, not in a batch at the end. Several defects only
  showed up in computed styles (see the anchor-button note below).
- **Check the sandbox's real data before trusting a code read.** Twice the data
  contradicted what the code appeared to do (`status_note` rows sharing the
  evidence column; bare filename strings in legacy evidence).

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

## CI gap — verified 2026-08-17, needs fixing before release

`.github/workflows/ci.yml` runs **only** four jobs:
`php-lint`, `phpcs` (WPCS), `phpstan`, `phpunit`.

**None of the gates I have been running by hand are in CI:**

| Gate | Command | In CI? |
|---|---|---|
| WPCS | `composer phpcs` | yes |
| PHPStan | `composer phpstan` | yes |
| PHPUnit | `composer test` | yes |
| i18n / version drift | `python3 bin/i18n-verify.py` | **no** |
| Docs audit | `python3 bin/docs-audit.py` | **no** |
| App parity | `python3 bin/app-parity.py` | **no** |
| Manifest freshness | (no check exists) | **no** |

So the i18n gate — which is the thing that catches version drift between the
plugin header, POT `Project-Id-Version`, `package.json` and the readme Stable
tag, i.e. exactly what breaks a release — only runs if someone remembers.
Add these three python gates as CI steps. They are fast and already exit
non-zero on failure, so they drop straight in.

**Manifest freshness has no check at all.** Note `CLAUDE.md` says
`manifest_refresh: agent-enumeration-only` — the deterministic generator
undercounts REST (142 → 7) because routes register through a controller array,
and **will silently clobber the manifest**. Do not wire the generator into CI.
A cheap honest check is: fail if `src/API/*Controller.php` changed in a commit
that did not touch `audit/manifest.json`.

## Journey checks — the infrastructure does not exist here

`CLAUDE.md` points at a QA catalog with per-plugin data files. **None of it is
in this repo** (verified):

- no `audit/journeys/`
- no `docs/qa/qa-config.json`
- no `docs/standards/qa-catalog.md`
- no `audit/ROLE_MATRIX.md`
- no `audit/CODE_FLOWS.md`

`CLAUDE.md`'s READ FIRST block references `audit/ROLE_MATRIX.md` and
`audit/CODE_FLOWS.md` as if they exist. **They do not.** Either create them or
correct that block — right now it sends the next person to missing files.

Until they exist, "journey check" means driving the real flow in the browser as
the real role. The journeys that actually caught defects today, worth writing
down first:

1. **Buyer hires a seller:** post request → seller proposes → buyer sees the
   in-app notification → hire → seller notified → losing bidder notified →
   pay the order. *(The pay step is where card 10208094640 still fails.)*
2. **Dispute:** buyer opens a dispute from the order → both parties see the
   thread and can reply → admin sees every message → order links back to the
   dispute.
3. **Fresh install / upgrade:** activate on a site running WooCommerce → check
   all 6 pages created with the right slugs → Settings > Pages populated →
   save without losing keys → `wp wpss preflight` all PASS.
4. **Vendor discovery:** directory → vendor card → profile → their service.

Each of those is a browser walk, and each maps to cards already fixed, so they
double as regression tests for this release.

## Release checklist (after Bugs is empty)

1. All gates green: `composer phpcs`, `composer phpstan`, `composer test`,
   `python3 bin/i18n-verify.py`, `python3 bin/docs-audit.py`.
2. `wp wpss preflight` — clean (ignore the pre-existing debug.log entry count,
   which is dominated by third-party plugin deprecations on this sandbox).
3. Deactivate → reactivate both plugins; confirm zero fatals. **This caught a
   real activation fatal today** — do not skip it.
4. Rebuild assets: `npm run rtl && npm run build:min`. `Assets.php` swaps to
   `.min`, so unbuilt CSS/JS edits are inert.
5. `readme.txt` changelog for 1.6.0 — **needs today's work folded in**; the
   existing entry predates 8 cards, a schema change (`DB_VERSION` 1.6.1) and a
   new owner setting. WooCommerce-style action-prefix format, no em-dashes,
   no emoji.
6. Update `CLAUDE.md` "Recent Changes".
7. Verify version consistency (all four already read 1.6.0):
   plugin header / readme Stable tag / `package.json` / POT `Project-Id-Version`.
8. Tag. Latest tag today is still `v1.4.0`.

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
   priority, not an open-ended bug sweep. Ship what is fixed; do not widen scope beyond clearing Bugs.
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
