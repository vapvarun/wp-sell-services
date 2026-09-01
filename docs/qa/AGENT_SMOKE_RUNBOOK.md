# Agent smoke runbook — WP Sell Services

The canonical contract for a pre-release browser smoke. Sections are **what to
verify and why**, not a click script: read the code to find the selector, the
route or the row, then assert the contract from the UI *and* from the server.

This plugin already carries its detailed flows in [`audit/journeys/`](../../audit/journeys/).
They are the contract for section C — do not restate them here, and do not let
the two drift. `docs/qa/TESTING-GUIDELINE.md` explains how to report a finding.

**Rail matters.** `wpss_general[ecommerce_platform]` decides which integration
loads. On `standalone` the WooCommerce adapter is not loaded at all, so a
Woo-only assertion cannot pass or fail there — it is unreachable. Set the rail a
journey needs, and restore it to `standalone` afterwards. A retest run against
the wrong rail is what produced a whole round of false bounces on 2026-08-31.

## A — Fresh install

1. Both plugins activate with no fatal; `wp plugin list` shows 1.7.0 for each.
2. Frontend returns 200 and is not white.
3. `wp wpss preflight` reports no failures.
4. Schema: `wpss_db_version` matches the shipped constant; every table in
   `SchemaManager::CORE_TABLES` exists.
5. Retired tables are reported, not created (`SchemaManager::get_retired_tables()`).

## B — Upgrade

1. Deactivate + reactivate both plugins: zero fatals, no duplicate rows.
2. An order file stored before 1.7.0 still downloads, and is migrated into the
   private store on first access rather than left public.
3. Existing orders keep their totals; no migration rewrites money.

## C — Core flows

Walk every journey in `audit/journeys/` (01-08). Each states its own
preconditions, steps and expectations. A journey that cannot run on the current
rail is `skipped`, not `pass`.

Beyond the journeys, assert for 1.7.0 specifically:

- Catalog checkout with tax on: the amount on the Pay button, the gateway
  charge and the stored order `total` are the same number, and `platform_fee`
  is a percentage of the **pre-tax** subtotal.
- A tip, a paid extension and a milestone phase each land on their own order
  page after payment, never on a requirements step.
- A submitted milestone phase offers Approve **and** Request changes; sending it
  back moves the phase to `revision_requested`, writes the reason to the phase,
  posts it to the parent thread, and leaves the seller a Resubmit control.
- A completed milestone contract shows its contract total and paid-so-far on the
  summary **and** the service line - never `0.00` - plus a Project complete card.
- A buyer's brief renders for buyer, seller and owner, including when the
  service has no configured questions.

## D — Regression guards

Specific fixtures; follow literally.

1. **Stats vs chips.** For the buyer with the most orders, the four summary
   cards equal the four filter-chip counts. They read different rows twice.
2. **Deleted-service revenue.** Analytics Top Services excludes `service_id = 0`
   (request parents and milestone phases legitimately have none).
3. **Suspended vendor.** A suspended vendor is refused on the money REST surface,
   reads included.
4. **Negative balance.** A vendor below zero sees an explanation, not a bare
   minus figure labelled Available for Withdrawal.
5. **Offline method identity.** Renaming or deleting a named offline method does
   not change what a past order says.

## E — Pro smoke (combo only)

1. Every Pro screen loads with no fatal: Analytics, Wallet, Stripe Connect,
   PayPal payouts, Commission rules, Subscription plans, Cloud storage.
2. With a storage provider active, a delivery upload reaches the provider, the
   local copy is removed, and the download resolves to a signed URL through the
   permission-checked endpoint.
3. On the WooCommerce rail: a fee-only pay-order (tip / extension / phase) shows
   no empty `Subtotal 0.00` row above the real amount.

## F — Cross-cutting

1. 1280px and 390px on every touched surface; no horizontal scroll on the body.
2. Dark mode follows the host theme's signal. **BuddyX on wss.local has no dark
   mode**, so a dark-mode finding here is not evidence — use a Reign site.
3. RTL: no `margin-left`/`right` regressions on changed components.
4. REST: every registered route carries a permission callback, and no GET 5xxs.

## Debug log protocol

Baseline the byte count before the walk; diff after each section. Anything
attributable to this plugin is a failure. Entries from the whitelist in
`qa-config.json` (third-party QA plugins, jQuery migrate, and the refund guard's
own deliberate refusal logging) are noise, not findings.

## Failure protocol

Record every failure and continue — never halt. For each, capture the URL, the
expectation, what actually happened, and classify `origin`:

- **`from`** — this plugin caused it.
- **`for`** — surfaced here but owned elsewhere (theme, third-party plugin,
  server). Say which, and prove it by toggling that thing rather than asserting.

A card reaches the Bugs column only when it is verified real *and* we intend to
fix it now. Everything else goes to Suggestion or Not now.
