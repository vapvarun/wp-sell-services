# Post-Launch: Automated Action Audit in CI

## Problem

PHPStan and WPCS are PHP-only — they can't detect cross-layer bugs between JS and PHP (wrong nonce strings, missing AJAX handlers, orphaned buttons). These bugs cause silent 403 errors that QA finds late.

## Solution: 3 Layers of Cross-Layer Testing

### Layer 1: Automated Action-Audit CI Script (Priority 1)

Custom PHP/Node script that runs in GitHub Actions alongside WPCS and PHPStan.

**What it does:**
- Parses all JS files for `$.ajax()` / `$.post()` calls → extracts action names + nonce variables
- Parses all PHP files for `wp_ajax_*` registrations → extracts handler + `check_ajax_referer()` params
- Parses `wp_localize_script()` calls → maps nonce variable names to action strings
- Cross-references everything and fails CI on mismatch

**Catches:**
- AJAX action with no PHP handler (returns 0/-1)
- Nonce action string mismatch (causes 403)
- Nonce parameter key mismatch (causes 403)
- Dead handlers (registered but never called from JS)
- Orphaned buttons (interactive elements with no JS handler)

**Implementation:** `composer action-audit` command, runs in CI workflow after WPCS.

### Layer 2: AJAX Integration Tests (Priority 2)

PHPUnit tests that fire `wp_ajax_*` actions with correct/incorrect nonces.

```php
// Verify handler exists and accepts correct nonce
public function test_accept_proposal_with_correct_nonce() {
    wp_set_current_user($this->buyer_id);
    $_POST['nonce'] = wp_create_nonce('wpss_proposal_action');
    $_POST['proposal_id'] = $this->proposal_id;
    $response = $this->_handleAjax('wpss_accept_proposal');
    $this->assertTrue($response['success']);
}

// Verify handler rejects wrong nonce
public function test_accept_proposal_rejects_wrong_nonce() {
    wp_set_current_user($this->buyer_id);
    $_POST['nonce'] = wp_create_nonce('wrong_action');
    $this->expectException(WPAjaxDieContinueException::class);
    $this->_handleAjax('wpss_accept_proposal');
}
```

**Covers:** All 78 AJAX actions in Free plugin, 22 in Pro plugin.

### Layer 3: Playwright E2E Smoke Tests (Priority 3)

Browser tests for critical user flows:

- Accept/reject proposal → no 403
- Send order message → message appears
- Submit delivery → status changes
- Add to cart → cart updates
- Vendor registration → profile created
- Open dispute → dispute created

**Slowest but highest confidence** — tests the full stack including JS execution.

## Current Audit Baseline (April 2026)

| Plugin | AJAX Handlers | Verified OK | Issues Found & Fixed |
|--------|--------------|-------------|---------------------|
| Free | 78 | 55 verified, ~6 dead | 5 fixed (2 Basecamp bugs + 3 audit) |
| Pro | 22 + 5 REST | All 27 verified | 0 issues |

## Dead Handlers to Clean Up

Low priority — registered but never called from JS:

| Handler | File | Notes |
|---------|------|-------|
| `wpss_send_direct_message` | AjaxHandlers.php:74 | May be REST-only |
| `wpss_mark_messages_read` | AjaxHandlers.php:77 | No JS caller |
| `wpss_upload_file` | AjaxHandlers.php:108 | Standalone uploader, unused |
| `wpss_live_search` | AjaxHandlers.php:111 | May be block/inline JS |
| `wpss_get_new_messages` | AjaxHandlers.php:76 | Redundant with wpss_get_messages |
| `wpss_get_favorites` | AjaxHandlers.php:103 | REST-only |
| `wpss_update_cart_item` | AjaxHandlers.php:128 | Cart has add/remove only |
| `wpss_remove_requirement_file` | AjaxHandlers.php:129 | Client-side only |
| `wpss_skip_requirements` | AjaxHandlers.php:130 | May be form POST |
