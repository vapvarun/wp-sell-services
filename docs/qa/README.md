# QA: what to check and how

One page, in the order QA works. Everything QA needs is linked from here; nothing else in `docs/qa/` or `audit/` is a QA entry point.

| File | Owner | What it is |
|---|---|---|
| `README.md` (this page) | developer | the steps |
| [`FEATURE-CATALOG.md`](FEATURE-CATALOG.md) | developer | every feature area, the journey that walks it, the guards that protect it, and the known gaps |
| [`../../audit/journeys/`](../../audit/journeys/) | developer | one role walking one flow, with `Expect` lines |
| [`../../audit/gate-parameters.md`](../../audit/gate-parameters.md) | developer | the conditions every flow is walked under (rail, theme, dark, 390, RTL, upgraded site, time zone) |
| [`TESTING-GUIDELINE.md`](TESTING-GUIDELINE.md) | developer | how to judge what you see (expectation first) |
| [`CHECKLIST.md`](CHECKLIST.md) | developer | the per-surface walk: one row per check, with its role, URL and measurable assertion; this is what a release walk runs |
| [`CORE_PATHS.md`](CORE_PATHS.md) | developer | the flows that matter most; a bug there outranks one on an edge |
| [`smoke-surfaces.json`](smoke-surfaces.json) | developer | which files belong to which walked surface; the release gate re-walks only a changed surface (an unmapped file re-walks everything) |
| `qa-config.json`, `.qa-results/walks.json` (untracked, local to the tester), `../../audit/escapes.json` | QA | gate config, walk ledger, regression-guard rows |
| `QA-HANDOFF-1.9.1.md` | developer | what changed this release, by fix group (replaced each release; older ones go to `archive/`) |
| `OWNER_INVENTORY.md` | generated | what the site owner can see; regenerate, never edit |

Tests all live in Free: `tests/` for the core and `tests/pro/` for Pro features (each skips with a message when Pro is not active). Run any of them with `wp eval-file tests/<file>.php` from the WordPress root.

## Fast flow (run this first)

Verification time follows the diff, not the plugin.

1. `node bin/qa-plan.cjs [<sha>]` lists the surfaces the diff touched and how many checks each persona must run. It reports `runAll: true` and the unmapped files when a change touches a file no surface owns.
2. Static: `php vendor/bin/phpcs --standard=phpcs.xml` on the changed files, and the guards.
3. Browser: for each persona the plan names, log in (`?autologin=`), then paste `checks/runner.fn.js` plus that persona's `checks/<persona>.json` into one Playwright `browser_evaluate`. It returns pass or fail per row (status, PHP notice, text present or absent, horizontal overflow at the stated width).
4. Anything a check cannot express (a double click, an approve-twice, a visual judgement) stays a row in `CHECKLIST.md` for a human or a targeted walk.
5. A fix is not ready for testing until its check row exists in `checks/` or `CHECKLIST.md`.

## 1. Set up (every session)

1. Plugin on the branch named on the card, Free and Pro at origin HEAD.
2. `bash bin/qa-fixtures.sh` creates the QA users. Journeys create their own services and orders; do not rely on a named account or "the latest order".
3. Pick the rail the journey names (`standalone` or `woo`) and restore it afterwards. A Woo-only step cannot pass or fail on the standalone rail.
4. Use `?autologin=<login>`; never fill login forms by hand.

## 2. Run the gate

```
bin/run-guards.sh                  # every checks.static guard in qa-config.json; ends "guards done, red=N"
php vendor/bin/phpcs --standard=phpcs.xml <changed php files>
node <qa-mcp>/bin/wppqa-run.mjs --plugin <plugin dir> --only <class>   # roundtrip, parity, states, presentation, crawl, release
```

The wp-plugin-qa MCP replays only those six classes. It does not replay `static` guards, so `bin/run-guards.sh` is their gate. Static code standards (WPCS, PHPStan, PHP compatibility) belong to the `wpcs` MCP; that server needs the plugin root as its path so it finds `phpcs.xml`.

Before a release, `grunt verify:smoke-gate` dates both smoke reports against Free and Pro. A changed file that `smoke-surfaces.json` maps to a surface is covered once that surface is re-walked and recorded under `surfaces` in the report (`ran_against`, and `pro_ran_against` for Pro files); a file it does not map invalidates the whole walk. `node tests/smoke-gate-surfaces.test.cjs` proves both.

A red guard on a surface is where the walk starts. A green gate does not replace the walk; it covers the known shapes only. Prove a guard can fail before trusting it (`audit/escapes.json` has a `fails_on` for each).

## 3. Walk the core paths

Rank order from `CORE_PATHS.md`, each as its named role, on a clean install. A broken core path outranks every card.

## 4. Walk the journeys

For each file in `audit/journeys/` (list and roles in `audit/journeys/README.md`):

1. Log in as the stated role, on the stated rail.
2. Walk the steps in order, in a real browser. Check each `Expect` as you go.
3. Where a step says *computed style*, read it; themes override links and buttons.
4. Record date, rail and result in `.qa-results/walks.json` (untracked, local to the tester).

The catalog says which journey covers which area. An area with no journey is a gap: walk it by hand from the catalog row and file the gap.

## 5. Apply the gate parameters

Each flow that renders UI is walked under the parameters in `audit/gate-parameters.md`: Reign, BuddyX, BuddyX Pro and Twenty Twenty-Five; light and dark; 1440 and 390; LTR and RTL; fresh and upgraded site; three time zones where a date shows. Do not walk every flow under every parameter; walk each flow under the parameters its row lists, and each parameter on the flows that exercise it.

## 6. Check the cards

From Ready for Testing, by fix group in `QA-HANDOFF-1.9.1.md`.

1. Run the card's acceptance bar literally, as the stated role, then the negative case.
2. Check every door the handoff lists as fixed, unaffected or not covered. An unlisted door is a question for the developer.
3. Follow the documentation for the flow literally. A wrong instruction is a product defect.
4. Verdict on the card: one group verdict, a full note only on a failure. Move the card and read the column back.
5. Money cards last, after the Stripe test mode and PayPal sandbox keys are wired.

## 7. Report

Who you were, what you came to do, what you expected, what happened (screen or response body), URL, role, viewport, rail, theme, and whether Pro was licensed. Quote the doc line you followed. Say if the site carried legacy data. Check the board before filing; a finding that already has a card is a comment on that card.

A bug becomes a check line in the journey that owns the behaviour, tagged `card:<id>`, and a guard row in `escapes.json`. No card goes to Done without both.

## 8. Standing checks (any release)

- **Debug log:** note its size before the walk and diff after each journey; anything from this plugin is a failure. Third-party QA plugins and the refund guard's deliberate refusals are noise.
- **REST:** every registered route has a permission callback and no GET answers 5xx (`tests/audit/rest-reachability.php`). Test over HTTP, not `rest_do_request()`.
- **Suspended vendor:** refused on the money routes, reads included.
- **Negative balance:** a vendor below zero sees an explanation, not a bare minus figure.
- **Pro:** every Pro screen loads without a fatal; with a storage provider active a delivery upload reaches it and the download is a signed URL. Pro registers nothing without a valid licence.
- **Fee-only pay-order** (tip, extension, phase) on the Woo rail shows no empty `Subtotal 0.00` row.

## 9. Environment traps

- A 5-minute per-recipient message-email cooldown hides repeat emails; clear the transient or change recipient.
- Dark mode needs a host theme with a dark signal; BuddyX has none on a stock site, so use Reign for dark findings.
- A retest on the wrong rail produces false bounces.
- On a shared local site, restore every option, theme and rail you change, and say so in the verdict.

## 10. Failure protocol

Record every failure and continue; never halt a walk. Classify `origin`: `from` (this plugin caused it) or `for` (theme, third-party plugin or server; prove it by toggling that thing). A card reaches Bugs only when it is verified real and we intend to fix it now; everything else goes to Suggestion or Not now.
