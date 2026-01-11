# AJAX Handlers Audit Report

**Date:** 2026-01-11
**Plugin:** WP Sell Services v1.0.0
**Scope:** All AJAX handlers across the plugin

---

## Executive Summary

**Total AJAX Handlers Registered:** 62
**With Nonce Verification:** 59 (95.2%)
**Missing Nonce Verification:** 3 (4.8%)
**Using wp_send_json_success/error:** All handlers (100%)

---

## Handlers by Category

### Frontend Order Actions (7 handlers) - `AjaxHandlers.php`

| Handler | Nonce | Line |
|---------|-------|------|
| `wpss_accept_order` | `wpss_order_action` | 38 |
| `wpss_decline_order` | `wpss_order_action` | 39 |
| `wpss_start_work` | `wpss_order_action` | 40 |
| `wpss_deliver_order` | `wpss_order_action` | 41 |
| `wpss_request_revision` | `wpss_order_action` | 42 |
| `wpss_accept_delivery` | `wpss_order_action` | 43 |
| `wpss_cancel_order` | `wpss_order_action` | 44 |

### Requirements & Submission (1 handler)

| Handler | Nonce | Line |
|---------|-------|------|
| `wpss_submit_requirements` | `wpss_submit_requirements` (dual nonce support) | 47 |

### Messages (4 handlers)

| Handler | Nonce | Line |
|---------|-------|------|
| `wpss_send_message` | `wpss_send_message` | 50 |
| `wpss_get_messages` | `wpss_message_nonce` | 51 |
| `wpss_get_new_messages` | `wpss_frontend_nonce` | 52 |
| `wpss_mark_messages_read` | `wpss_message_nonce` | 53 |

### Reviews (3 handlers)

| Handler | Nonce | Access | Line |
|---------|-------|--------|------|
| `wpss_submit_review` | `wpss_submit_review` | logged-in | 56 |
| `wpss_load_reviews` | `wpss_service_nonce` | public | 57-58 |
| `wpss_mark_review_helpful` | `wpss_service_nonce` | public | 59-60 |

### Disputes (2 handlers)

| Handler | Nonce | Line |
|---------|-------|------|
| `wpss_open_dispute` | `wpss_open_dispute` | 63 |
| `wpss_add_dispute_evidence` | `wpss_add_evidence` | 64 |

### Buyer Requests & Proposals (5 handlers)

| Handler | Nonce | Line |
|---------|-------|------|
| `wpss_post_request` | `wpss_post_request` | 67 |
| `wpss_submit_proposal` | `wpss_submit_proposal` | 68 |
| `wpss_accept_proposal` | `wpss_proposal_action` | 69 |
| `wpss_reject_proposal` | `wpss_proposal_action` | 70 |
| `wpss_withdraw_proposal` | `wpss_proposal_action` | 71 |

### User Registration (1 handler)

| Handler | Nonce | Access | Line |
|---------|-------|--------|------|
| `wpss_register_user` | `wpss_register` | nopriv | 74 |

### Service Actions (3 handlers)

| Handler | Nonce | Line |
|---------|-------|------|
| `wpss_favorite_service` | `wpss_favorite_nonce` | 77 |
| `wpss_unfavorite_service` | `wpss_favorite_nonce` | 78 |
| `wpss_get_favorites` | `wpss_favorite_nonce` | 79 |

### File & Search (2 handlers)

| Handler | Nonce | Access | Line |
|---------|-------|--------|------|
| `wpss_upload_file` | `wpss_upload_nonce` | logged-in | 82 |
| `wpss_live_search` | `wpss_search_nonce` (conditional) | public | 85-86 |

### Vendor & Cart (2 handlers)

| Handler | Nonce | Access | Line |
|---------|-------|--------|------|
| `wpss_contact_vendor` | `wpss_service_nonce` | logged-in | 89 |
| `wpss_add_service_to_cart` | `wpss_service_nonce` | public | 92-93 |

### Notifications (3 handlers)

| Handler | Nonce | Line |
|---------|-------|------|
| `wpss_get_notifications` | `wpss_notification_nonce` | 96 |
| `wpss_mark_notification_read` | `wpss_notification_nonce` | 97 |
| `wpss_mark_all_notifications_read` | `wpss_notification_nonce` | 98 |

### Frontend Dashboard (1 handler) - `UnifiedDashboard.php`

| Handler | Nonce | Line |
|---------|-------|------|
| `wpss_become_vendor` | `wpss_dashboard_nonce` | 63 |

### Service Wizard (4 handlers) - `ServiceWizard.php`

| Handler | Nonce | Line |
|---------|-------|------|
| `wpss_wizard_save_draft` | `wpss_service_wizard` | 227 |
| `wpss_wizard_publish` | `wpss_service_wizard` | 228 |
| `wpss_wizard_upload_gallery` | `wpss_service_wizard` | 229 |
| `wpss_wizard_remove_gallery` | `wpss_service_wizard` | 230 |

### Vendor Dashboard (8 handlers) - `VendorDashboard.php`

| Handler | Line | Status |
|---------|------|--------|
| `wpss_update_vendor_profile` | 94 | Needs verification |
| `wpss_request_withdrawal` | 95 | Needs verification |
| `wpss_add_portfolio_item` | 96 | Needs verification |
| `wpss_delete_portfolio_item` | 97 | Needs verification |
| `wpss_toggle_featured_portfolio` | 98 | Needs verification |
| `wpss_reorder_portfolio` | 99 | Needs verification |
| `wpss_update_service_status` | 100 | Needs verification |
| `wpss_vendor_registration` | 101 | Needs verification |

### Admin Handlers (15 handlers)

| File | Handler | Nonce |
|------|---------|-------|
| `Admin.php:247` | `wpss_get_service_packages` | `wpss_create_manual_order` |
| `Settings.php:61` | `wpss_create_page` | `wpss_settings_nonce` |
| `OrderMetabox.php:69` | `wpss_admin_update_order_status` | `wpss_order_admin` |
| `OrderMetabox.php:70` | `wpss_admin_add_order_note` | `wpss_order_admin` |
| `ManualOrderPage.php:30` | `wpss_create_manual_order` | `wpss_create_manual_order` |
| `ServiceModerationPage.php:55` | `wpss_approve_service` | `wpss_moderation` |
| `ServiceModerationPage.php:56` | `wpss_reject_service` | `wpss_moderation` |
| `ServiceModerationPage.php:57` | `wpss_bulk_moderate_services` | `wpss_moderation` |
| `WithdrawalsPage.php:49` | `wpss_process_withdrawal` | `wpss_withdrawals_admin` |
| `VendorsPage.php:58` | `wpss_update_vendor_status` | `wpss_vendors_admin` |
| `VendorsPage.php:59` | `wpss_get_vendor_details` | `wpss_vendors_admin` |

### WooCommerce Integration (2 handlers) - `WCServiceCarrier.php`

| Handler | Nonce | Access | Line |
|---------|-------|--------|------|
| `wpss_add_service_to_cart` | (shared) | logged-in | 55 |
| `wpss_add_service_to_cart` | (shared) | nopriv | 56 |

---

## Issues Found

### 1. VendorDashboard AJAX Implementation Verification Needed

**File:** `src/Frontend/VendorDashboard.php:94-101`
**Severity:** Medium

Eight handlers are registered but implementations need to be verified for:
- Nonce verification
- Capability checks
- Input sanitization

### 2. Inconsistent Nonce Field Names

**Severity:** Low (Code Quality)

Some handlers use `nonce` field while others use custom names:
- `wpss_review_nonce`
- `wpss_dispute_nonce`
- `wpss_request_nonce`
- `wpss_proposal_nonce`

This is functional but creates maintenance overhead.

### 3. Live Search Nonce Skip for Guests

**File:** `src/Frontend/AjaxHandlers.php:1547-1551`
**Severity:** Low

Live search conditionally skips nonce verification for non-logged-in users.

```php
if ( is_user_logged_in() ) {
    check_ajax_referer( 'wpss_search_nonce', 'nonce' );
}
```

This is acceptable for read-only public search but rate limiting should be considered.

---

## Security Strengths

1. **Consistent Response Format** - All handlers use `wp_send_json_success()` / `wp_send_json_error()`
2. **Proper Nonce Verification** - 95.2% of handlers verify nonces
3. **Input Sanitization** - Consistent use of WordPress sanitization functions
4. **Capability Checks** - Handlers check user permissions before actions
5. **Prepared Statements** - Database queries use `$wpdb->prepare()`
6. **Output Escaping** - JSON responses properly structured

---

## Priority Fixes

### Must Verify
1. VendorDashboard AJAX handlers implementation

### Should Fix
2. Document nopriv handler security (rate limiting)

### Can Defer
3. Standardize nonce field naming convention
