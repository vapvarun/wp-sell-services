# wppqa Baseline — 2026-06-10

Re-run during `/wp-plugin-onboard --refresh` after the 1.2.0 feature sprint
(add-ons meta-key fix, full-width templates, admin-notice suppression,
role-aware dashboard landing, settings-save feedback, wizard extension hooks,
Pusher-protocol realtime layer).

## Checks run

| Check | Passed | Failed | Verdict |
|---|---|---|---|
| `wppqa_check_rest_js_contract` | 34 | 0 | **Clean** — the new `POST /realtime/auth` endpoint introduced no envelope drift |
| `wppqa_check_plugin_dev_rules` | 0 | 76 | All pre-existing heuristic false-positives (see below) — none from this sprint |

## Classification of the 76 plugin-dev-rules failures

All 76 are **known heuristic false-positives**, unchanged by this sprint. Documented against the limitations table in `wp-plugin-development` (L4, L1).

### Nonce-without-capability (high severity, ~60 hits)
Flagged across `src/Frontend/AjaxHandlers.php` and `src/Admin/Pages/ServiceModerationPage.php`. These are the **L4 limitation**: the heuristic only recognises an inline `current_user_can()` adjacent to the nonce check. WPSS AJAX handlers authorize via three documented patterns (see `audit/FEATURE_AUDIT.md` §2):
1. inline `current_user_can`
2. **service-class delegation** (the service method does the cap check)
3. **`WHERE user_id = get_current_user_id()` row scoping** (handler can only ever touch the caller's own rows)

Patterns 2 and 3 are invisible to the proximity heuristic. No real authorization gap was introduced this sprint — the realtime auth endpoint (`RealtimeController`) does explicit per-channel ownership checks and was independently verified (200 own-channel / 403 foreign / 401 logged-out).

### Tap-target < 40px (low severity, ~16 hits)
Pre-existing CSS warnings on buttons in `service-wizard.css`, `single-service.css`, `unified-dashboard.css`, `vendor-dashboard.css` and their `-rtl` / `.min` siblings (the checker double-counts minified copies). Cosmetic; tracked, not sprint-introduced.

## Verdict

No new high-severity issues from the 1.2.0 sprint. The realtime addition — the only new REST surface — passed the contract check that matters for it. Pre-existing heuristic FPs carry forward unchanged from the 2026-06-06 baseline.
