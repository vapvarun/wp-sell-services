# QA rebuild: what changed here and what is yours

Branch `qa/gate-setup`, from `1.9.0` at 7803409a. QA-owned files only. Merge it when you are ready; nothing here touches `tests/`, `src/` or `audit/journeys/`.

## What QA changed
- `docs/qa/qa-config.json`: removed `base_url` and `wp_path` (site facts do not belong in committed config). Added `checks.static`: 35 guards, one per script that already backed an escape (`wp eval-file tests/...`, `bin/docs-audit.py`, `bin/check-skeleton.php`). The admin persona is now `wpss_qa_admin`, created by `bin/qa-fixtures.sh` like the others.
- `audit/escapes.json`: the 34 old rows migrated to the gate's schema (`card, title, guard, added, fails_on, check`, plus `note`). 42 rows now, because rows that named two scripts became two rows. Every `guard` resolves to a check in the config. `fails_on` is carried over from the old rows; it has not been re-proven.
- `docs/qa/walks.json`: new, QA-owned, empty. QA records each journey walk here, so journey files stay yours.

## How to run it
Needs the QA MCP branch `feat/static-class-site-independent` (pushed to `vapvarun/wp-plugin-qa-mcp-server`; not merged yet).
```
node <mcp>/bin/wppqa-run.mjs --plugin <path to this plugin inside the WP install> --only static --allow-static
```
`--allow-static` is required: static guards run commands from this config, so they are off by default. Result on 2026-10-18: all 35 guards pass on the local site (31 through the runner, 4 Pro scripts run directly because the check was run from a checkout outside the install).

Ready check: `python3 <claude-backup>/skills/wp-qa-gate/scripts/ready-check.py <plugin dir>` (branch `qa-rebuild` in `vapvarun/claude-backup`). Today it fails two checks, both yours:
- R7: 20 journeys with no walk date (QA fills `walks.json` after you rewrite the journeys).
- R7b: 6 journeys say `Rail: any`. Name the rail.

## What is yours
1. Rewrite `audit/journeys/` as role-by-flow (your plan in `~/work-artifacts/scratch/wpss-verify/feature-catalog-and-journey-plan.md`). Name the rail in each (`**Rail:** standalone, woo`).
2. Rewrite the four site-dependent tests to seed their own data or skip with a message: `test-notification-types-registry.php`, `test-account-standing.php`, `test-demo-delete-contract.php`, `test-cli-guards.php`. No "busiest user", "latest order", or persona names.
3. Add the class guards for this cycle's three bounces (AJAX writers vs REST standing gate, one notification type vocabulary, rendered heading outline for every gateway tab) as `tests/` scripts, run in your CI, and add the entry to `checks.static` in the same commit: this file and `escapes.json` are still QA-owned, so tell QA the ids and QA adds the rows.
4. Group handoff on Ready for Testing: rail and entry point, roles, every door, acceptance output, one negative case.

## What QA does next
Close the unconfirmed screens (Pro badges, milestones, extensions, tips, seller levels, plans, wallet, admin at 390px), turn the catalog into `crawl` and `presentation` entries, walk your rewritten journeys, then test the 65 Ready for Testing cards by fix group.

Not pushed: this branch is local only. `1.9.0` has 68 commits that are not on origin and the repository is public, so pushing a branch built on top of them would publish those commits. Push `1.9.0` first when you are ready.
