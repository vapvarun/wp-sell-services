# wppqa baseline — wp-sell-services (FREE)

Generated: 2026-05-08

## Per-check results

| Check | Pass | Fail | Skip | Duration |
|---|---|---|---|---|
| `wppqa_check_plugin_dev_rules` | 0 | **86** | 0 | 195ms |
| `wppqa_check_rest_js_contract` | 32 | 0 | 0 | 27ms |
| `wppqa_check_wiring_completeness` | 0 | 0 | 1 (n/a) | 0ms |

## Top findings (by severity)

### 🔴 High severity

| # | Type | Count | Files |
|---|---|---|---|
| 1 | `alert()`/`confirm()` in JS | 5 | `assets/js/admin-settings-demo.js:21,56`, `admin-settings-pages.js:53`, `unified-dashboard.js:116,120` |
| 2 | `$_POST`/`$_GET` iteration | 3 | `BuyerRequestMetabox.php:327`, `ServiceMetabox.php:1065`, `AjaxHandlers.php:638` |
| 3 | Nonce check without capability check | ~13 | `Admin.php:1266,1319`, `ServiceModerationPage.php:1211`, `AjaxHandlers.php:189,225,267,299,357,390,422,483,526,690,901,936,971` (some are valid object-ownership patterns the rule's regex doesn't credit) |

### 🟡 Low severity

| # | Type | Count | Notes |
|---|---|---|---|
| 4 | Tap target < 40px | ~65 | Mostly button height 36px in dashboard/wizard CSS — accessibility nit, not blocker |

## Real-vs-likely-false-positive triage

**Real issues (worth fixing):**
- 5 `alert()`/`confirm()` calls — replace with toast (window.wpssToast or similar)
- 3 `$_POST`/`$_GET` iteration patterns — read explicit keys instead
- Some "nonce-no-cap" in `Admin.php` and `ServiceModerationPage.php` — these are admin actions (not AJAX with object ownership), likely real gaps

**Likely false positives (document, don't fix):**
- AjaxHandlers.php "nonce-no-cap" findings where the handler verifies object ownership via `$resource->customer_id/vendor_id !== $current_user_id` immediately after the nonce check. wppqa's regex only recognizes `current_user_can()` — the ownership pattern is equivalent or stronger but not credited.
- Tap-target warnings on minified CSS files — duplicates the unminified counterparts.

## Counter-evidence vs. earlier audit

The `Item 5 — Capability audit on FREE's AjaxHandlers.php (72 handlers)` task in this session concluded "PASS — no fixes needed" based on a heuristic that credited 3 patterns: inline `current_user_can`, delegation to a service class with `$user_id` param, and DB writes scoped via `WHERE user_id = X`. wppqa is stricter — it requires literal `current_user_can` near the nonce check.

For an authoritative answer, re-run wppqa after fixing the real gaps and accept the rest as document-and-defer.

## Action items (deferred to future sprint, not blocking release)

1. Replace 5 `alert()`/`confirm()` with toast component
2. Audit 3 superglobal-iteration patterns and refactor to explicit-key reads
3. Add `current_user_can( 'manage_options' )` (or appropriate cap) immediately after nonce in `Admin.php:1266,1319` and `ServiceModerationPage.php:1211`
4. Suppress wppqa false positives via `wppqa-config.json` once we author one (track ownership-checked handlers as "ownership_authorized" exemption)
