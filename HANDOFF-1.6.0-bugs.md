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

## In progress — ROOT CAUSE PROVEN, fix not yet written

**Cards 10208094640 + 10208199238 — proposal orders with `service_id = 0`.**

### The root cause (proven live, 2026-08-17)

`ServiceOrder::get_service()` is this, at `src/Models/ServiceOrder.php:737`:

```php
public function get_service(): ?Service {
    $post = get_post( $this->service_id );   // service_id is 0 on proposal orders
    return $post ? Service::from_post( $post ) : null;
}
```

**`get_post( 0 )` does not return null — it returns the global `$post`.** That
is standard WordPress behaviour and the whole bug. On the checkout page the
global post *is* the checkout page, so a proposal order silently resolves its
"service" to **the page you happen to be looking at**.

Verified on the live sandbox:

```
global $post; $post = get_post( 297 );
get_post( 0 )  ->  #297 ("Service Checkout")
```

That single line explains all of it:
- checkout line title reads **"Service Checkout"** — the page's title
- the form posts **`service_id=297`** — the page's ID (confirmed in the live
  DOM: `hidden_fields.service_id === "297"`)
- wp-admin shows **"Service Deleted"** for the same orders (card 10208199238),
  because there the global post is different or absent

**Correcting an earlier note in this handoff:** I first suspected
`build_proposal_service_placeholder()`. It is **not** at fault — called directly
against order 3128 it returns the right title
(`'Playwright QA — Need a landing page redesign for TestMarketplace'`, `id = 0`).
It is simply never reached, because `get_service()` returns a truthy Service
(the page) and the `if ( ! $service )` fallback never fires.

### The fix

Guard the ID before asking WordPress for it — one place, and every caller of
`get_service()` is fixed at once:

```php
public function get_service(): ?Service {
    if ( $this->service_id <= 0 ) {
        return null;            // proposal/request orders have no service post
    }
    $post = get_post( $this->service_id );
    return $post && 'wpss_service' === $post->post_type
        ? Service::from_post( $post )
        : null;
}
```

The `post_type` check matters too: without it any post ID that happens to match
resolves as a "service".

Then `render_pay_order_checkout()`'s existing `if ( ! $service )` branch starts
working and the placeholder (which is already correct) takes over.

**Before changing it, grep every `get_service()` caller** — some may currently
depend on the truthy-global-post accident. `wpss_get_order()`-based admin
screens are the ones to check first.

### Still to diagnose

3. **Offline Pay leaves the order `pending_payment`** with `payment_method = NULL`
   and no receipt. Not yet investigated. Compare against the cart Offline path,
   which QA reports works. Note the form does carry `wpss_offline_nonce`, so the
   gateway is being offered; the question is what the POST handler does with
   `pay_order` present.

### Reproduction

```
/service-checkout/?pay_order=3128     (as wpss_buyer_amelia)
```
Order 3128 / `WPSS-9UEPWHXB`, `$350`, `service_id = 0`, `platform = 'request'`,
title in `meta.proposal_snapshot.request_title`.

Journey `audit/journeys/01-buyer-hires-seller.md` step 7 covers this.

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

## CI — gap CLOSED 2026-08-17 (commit below)

`.github/workflows/ci.yml` previously ran only `php-lint`, `phpcs`, `phpstan`,
`phpunit`. The gates that catch release-breaking mistakes ran **only when
someone remembered**. Two jobs added:

| Gate | Command | In CI |
|---|---|---|
| PHP lint / WPCS / PHPStan / PHPUnit | (existing) | yes |
| i18n + version drift | `python3 bin/i18n-verify.py` | yes — `project-gates` |
| Docs audit | `python3 bin/docs-audit.py` | yes — `project-gates` |
| App parity | `python3 bin/app-parity.py` | yes — `project-gates` |
| Manifest freshness | diff-based | yes — `manifest-freshness` |

All three python gates were confirmed exit-0 on the current tree before being
added, so CI is not red on arrival.

**`manifest-freshness` is deliberately diff-based, not a regeneration.** It
fails when a contract file (`src/API/*Controller.php`,
`src/Database/SchemaManager.php`, `src/CLI/*.php`) changes without
`audit/manifest.json` changing in the same push/PR. It does **not** run the
deterministic generator — `CLAUDE.md` marks this plugin
`manifest_refresh: agent-enumeration-only` because REST registers through a
controller-array wrapper, so the generator undercounts REST (142 → 7) and would
silently clobber the file. **Never wire the generator in.**

The logic was tested against real commits before landing: `4264144` (touched
SchemaManager *and* the manifest) passes, `e29809c` (no contract files) passes,
and a synthetic SchemaManager-without-manifest change correctly fails.

## Journeys — gap CLOSED 2026-08-17

`audit/journeys/` now exists with the four walks that actually caught defects,
plus a README covering how to run one and the verified role table:

| File | Covers | Status |
|---|---|---|
| `01-buyer-hires-seller.md` | request → proposal → hire → pay | **step 7 fails** (10208094640) |
| `02-dispute.md` | open → both parties reply → admin reads → back to order | passes |
| `03-install-and-upgrade.md` | activation, pages, settings, preflight, upgrade | passes |
| `04-vendor-discovery.md` | directory → profile → service | passes |

`audit/ROLE_MATRIX.md` was also missing and is now written **from live role
data**, not from code comments. The load-bearing fact in it: **a buyer holds no
WPSS capability at all** — a buyer is a plain subscriber, so every buyer-side
gate must be an ownership check, never `current_user_can()`.

`CLAUDE.md`'s READ FIRST block pointed at `audit/CODE_FLOWS.md`, which has
**never existed**. Corrected to point at `audit/FLOW-AUDIT.md` (which does) and
to the new journeys directory. Every link in that block now resolves — checked.

Still not present, and fine for now: `docs/qa/qa-config.json` and
`docs/standards/qa-catalog.md` (the cross-plugin catalog convention). The
journeys above are the working substitute.

**Not yet written**, worth adding as cards clear: messages/unread, review
submit + display, withdrawal/payout, milestones, extensions, and the three
non-standalone rails (WooCommerce, EDD, FluentCart).

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
