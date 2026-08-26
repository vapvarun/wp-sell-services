# wppqa baseline — wp-sell-services (FREE)

Generated: 2026-06-06 (refresh on branch 1.2.0, plugin v1.1.1)

## Drift vs. previous baseline (2026-05-08)

**ZERO DRIFT.** Identical results to the 2026-05-08 baseline — no source-category files
changed between v1.1.0 and v1.1.1 (only the main-file autoloader guard, version bump,
vendor refresh, and CSS build artefacts).

## Per-check results

| Check | Pass | Fail | Skip | Duration |
|---|---|---|---|---|
| `wppqa_check_plugin_dev_rules` | 0 | **86** | 0 | 200ms |
| `wppqa_check_rest_js_contract` | 32 | 0 | 0 | 25ms |
| `wppqa_check_wiring_completeness` | 0 | 0 | 1 (n/a) | 0ms |

## Top findings (unchanged — see 2026-05-08 baseline for full triage)

### High severity
1. `alert()`/`confirm()` in JS — 5 calls: `assets/js/admin-settings-demo.js:21,56`, `admin-settings-pages.js:53`, `unified-dashboard.js:116,120`
2. `$_POST`/`$_GET` iteration — 3: `BuyerRequestMetabox.php:327`, `ServiceMetabox.php:1065`, `AjaxHandlers.php:638`
3. Nonce-no-cap — ~13+ in `Admin.php:1266,1319`, `ServiceModerationPage.php:1211`, `AjaxHandlers.php` (many are object-ownership patterns the rule's regex doesn't credit — see 2026-05-08 triage)

### Low severity
4. Tap target < 40px — ~65 hits (36px buttons in dashboard/wizard CSS, incl. minified duplicates)

## Real-vs-false-positive triage

Unchanged from 2026-05-08 baseline:
- **Real**: 5 alert/confirm, 3 superglobal iterations, nonce-no-cap in `Admin.php` + `ServiceModerationPage.php`
- **Likely FP**: AjaxHandlers nonce-no-cap where object-ownership check follows the nonce; tap-target warnings on minified files (duplicates)

## Action items

These are the prime candidates for the 1.2.0 "full audit + hardening" AutoVAP run:
1. Replace 5 `alert()`/`confirm()` with toast/modal component
2. Refactor 3 superglobal-iteration patterns to explicit-key reads
3. Add capability checks after nonce in `Admin.php:1266,1319`, `ServiceModerationPage.php:1211`
4. Triage remaining AjaxHandlers nonce-no-cap findings (confirm ownership pattern, document as accepted)
5. Tap-target + breakpoint cleanup (low priority)
