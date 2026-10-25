# Functionality + UX Catalog

**One row per customer-observable promise.** Not per class, not per route, not per file.

This is the answer to "does every free and Pro feature actually work as expected,
and did we check it this release?" It exists because our other inventories
(`audit/FEATURE_AUDIT.md`, `audit/CAPABILITIES.md`, `docs/qa/FEATURE-SCORECARD.md`)
are *descriptive* — they record what the code contains. A descriptive inventory
can never catch a dead capability, because the code really is there.

The dispute withdraw/escalate bug is the worked example: `DisputeWorkflowManager::cancel()`
existed, `POST /disputes/{id}/cancel` existed and was reachable, `app-parity.py`
reported "0 features with no reachable REST route" — and no template on the site
called it. Every inventory we had said that feature shipped. No customer could use it.

## Files

| File | Contents |
|---|---|
| `rows-marketplace.json` | Discovery, single service, cart, checkout, order lifecycle |
| `rows-vendor-admin.json` | Vendor onboarding/profile, wizard, dashboard sections, disputes, reviews, wp-admin, settings |
| `rows-pro.json` | Everything in wp-sell-services-pro |
| `CATALOG.md` | Generated human-readable view. Never hand-edit. |

## The row

```jsonc
{
  "id": "DISP-04",
  "area": "Disputes",
  "tier": "free",                      // free | pro
  "promise": "...",                    // what a customer can do, present tense, no class names
  "entry_points": {
    "frontend": "dashboard/disputes -> Withdraw button",  // or "MISSING", or "n/a - <reason>"
    "admin":    "n/a - admins resolve, not withdraw",
    "api":      "POST wpss/v1/disputes/{id}/cancel"
  },
  "roles":  { "buyer": "can", "other_party": "cannot", "admin": "can" },
  "ux_bar": "...",                     // TESTABLE. An observable state, never "works well".
  "source_files": ["..."],             // links the row to a release diff
  "last_verified": null                // { version, date, result, evidence }
}
```

### Why each field earns its place

- **`entry_points` with explicit `n/a` + reason.** `MISSING` in the frontend cell
  *is* the dead-capability alarm. This makes CLAUDE.md's "three entry points per
  data store" rule mechanical instead of aspirational. Five rows in the first
  pass came back `MISSING`.
- **`roles`.** Gating asserted per actor. "Vendor sees a button that 403s" is a
  failing row, not a cosmetic nitpick.
- **`ux_bar`.** The half that catches confusing UX. It must name an observable
  state so two people reading it reach the same verdict:
  - good: *"The control is absent for anyone the API would refuse, never shown-then-rejected."*
  - good: *"The newly chosen file visibly replaces the old one."*
  - good: *"The empty state says what to do next, not a bare zero."*
  - bad: *"Works well."* / *"Looks clean."* / *"Feels responsive."*
- **`source_files`.** How a release picks which rows to re-verify.

## How a release uses it

Three buckets. Checking all ~145 rows every release dies in two cycles, so
don't pretend otherwise:

1. **Core rows** — the flows in `docs/qa/CORE_PATHS.md`. Every release, no exceptions.
2. **Touched rows** — `source_files` ∩ `git diff --name-only <last-tag>..HEAD`.
   Mechanical. No judgement call, so no "we exercised the wizard earlier in the cycle".
3. **The rest** — rotation, or on a major.

A row whose `last_verified.version` is not the version being tagged is **stale**.
Stale core-or-touched rows degrade the release verdict, the same way
`verify:smoke-gate` in `Gruntfile.js` refuses to build on a stale smoke report.

**This is strictly better than journeys for the skip problem.** Journeys are 8
coarse things that get skipped wholesale — that is exactly how
`05-vendor-creates-a-service` was skipped for 1.7.1 and gave us four wizard bugs
in 1.7.2. Catalog rows are fine-grained and diff-linked: 1.7.2 touching
`src/Frontend/ServiceWizard.php` would have forced every wizard row to re-verify,
and nobody could have quietly dropped them.

## Keeping it true

The catalog rots the moment nothing breaks when it goes stale. Four rules:

1. **Rows are enumerated from code; expectations are written by hand.**
   `entry_points` and `source_files` are regenerable from the manifest plus a
   grep. Only `promise` and `ux_bar` are human-written, and those barely churn.
2. **The diff picks the rows** (above).
3. **Staleness degrades the verdict** (above).
4. **Every bug we fix adds or sharpens exactly one row.** 1.7.2 contributed about
   twenty `ux_bar` sentences. *"The newly chosen file visibly replaces the old
   one"* is now a rule we own forever, written the day it bit us.

## Status

Seeded 2026-09-19 against free 1.7.2 / Pro 1.7.2, enumerated from source.

**Almost every row starts `last_verified: null`, and that is the honest state** —
it is the first real measurement of how much of this product we actually check
each release. The number is meant to be uncomfortable.

### Dead capabilities found in the first pass

Routes that exist, are reachable, and have no web caller at all:

| Row | Route | Cost to the customer |
|---|---|---|
| `SRCH-07` | `GET wpss/v1/search` | Combined service+vendor search. A buyer typing a vendor's name into catalog search gets zero results; nothing on the web ever calls it. |
| `ORD-03b` | `POST wpss/v1/orders/{id}/requirements/skip` | Every web buyer must submit requirements even on a service whose fields are all optional. |
| `VEND-11` | `GET wpss/v1/vendors/me/level` | Tier progress. A vendor only ever learns their level changed from an email; there is no in-dashboard "3 more orders to Pro". |

Fixed in 1.7.2, listed because they are the same shape and are what prompted this catalog:

| Row | Route | Was |
|---|---|---|
| `DISP-04` | `POST /disputes/{id}/cancel`, `/escalate` | Full API, zero UI. |
| `DASH-08` | paginated notifications | Data layer and REST paginated; template hardcoded a 50-row cap. |

### Adjacent findings worth their own cards

- `DASH-06` — Withdraw Proposal exists on the request page but **not** in
  `dashboard/proposals`, so the list a vendor actually lives in is read-only.
- `CART-01` / `CART-03` — `POST /cart/add` and `DELETE /cart/{key}` are
  registered but the web uses legacy admin-ajax. Two implementations of one flow.
- `ORD-22` — the order timeline is built twice (PHP in the template, and the REST
  route for app clients). They can drift.
- `ORD-18` — `templates/order/order-view.php` still has 4 raw `alert()` calls
  where the rest of the page uses the shared toast/confirm system.
- Pro `RECUR-01` — the whole Recurring Services feature is behind
  `wpss_pro_recurring_feature_available`, default **false**, because renewals
  cannot yet collect a payment method. Correct call; but the 1.7.2 changelog
  initially advertised a fix inside it, which would have sent owners hunting for
  a settings card that does not render.
- Pro `WALLET-01` / `SETTINGS-01` — `audit/manifest.json` and
  `audit/FEATURE_AUDIT.md` claim four wallet providers and eight Pro settings
  tabs. Current source registers **one** provider and **one** new tab.
  Pro's `FEATURE_AUDIT.md` is stamped 1.3.1 while Pro ships 1.7.2.
