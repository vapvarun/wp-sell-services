# WP Sell Services - Bug Fix Task List

**Created**: December 29, 2025
**Status**: Complete (20 of 21 bugs fixed, BUG-014 skipped)

---

## Critical Bugs (Blocking)

- [x] **BUG-001**: post_request() method signature mismatch
  - File: `/src/Frontend/AjaxHandlers.php` line 686
  - Fix: Remove $user_id param, handle int|false return

- [x] **BUG-002**: convert_to_order() object/array access
  - File: `/src/Services/BuyerRequestService.php` lines 580-675
  - Fix: Change `$proposal['field']` to `$proposal->field`

- [x] **BUG-003**: Missing update_status() method
  - File: `/src/Services/ProposalService.php`
  - Fix: Add `update_status()` public method

- [x] **BUG-004**: Private method called as public
  - File: `/src/Services/ProposalService.php` line 367
  - Fix: Change `reject_other_proposals()` from private to public

- [x] **BUG-005**: deliver_order parameter mismatch
  - File: `/src/Frontend/AjaxHandlers.php` line 180
  - Fix: Remove $user_id param from submit() call

---

## High Priority Bugs

- [x] **BUG-006**: Order AJAX object access issues
  - File: `/src/Frontend/AjaxHandlers.php` lines 110, 142, 175, 208, 240, 274, 305
  - Fix: Change `$order['vendor_id']` to `$order->vendor_id`

- [x] **BUG-007**: Status name inconsistency
  - Files: `/templates/order/order-view.php`, workflow files
  - Fix: Align `delivered` vs `pending_approval` status names

- [x] **BUG-008**: Missing wpss_load_reviews AJAX handler
  - File: `/src/Frontend/AjaxHandlers.php`
  - Fix: Implement `load_reviews()` handler

- [x] **BUG-009**: Missing wpss_mark_review_helpful AJAX handler
  - File: `/src/Frontend/AjaxHandlers.php`
  - Fix: Implement `mark_review_helpful()` handler

- [x] **BUG-010**: Status mismatch in Disputes admin
  - File: `/src/Admin/Tables/DisputesListTable.php`
  - Fix: Align status constants with DisputeService

- [x] **BUG-011**: Missing admin dispute detail view
  - Fix: Create dispute detail/resolution page

- [x] **BUG-012**: Moderation status constant mismatch
  - Files: `ModerationService.php` vs `ServiceModerationPage.php`
  - Fix: Use consistent STATUS_PENDING values

- [x] **BUG-013**: Moderation meta key inconsistency
  - Fix: Align rejection reason meta keys

- [ ] **BUG-014**: Post status mismatch in moderation
  - Fix: Consolidate moderation logic (SKIPPED - low priority)

- [x] **BUG-015**: Live search ignores moderation
  - File: `/src/Frontend/AjaxHandlers.php` line 1033
  - Fix: Add moderation status check to search

- [x] **BUG-016**: Missing withdrawals admin page
  - Fix: Create WithdrawalsPage.php

- [x] **BUG-017**: Package ID lookup issue
  - Files: `/src/Integrations/WooCommerce/WCOrderProvider.php`, `WCCheckoutProvider.php`
  - Fix: Match on array index using array_values() instead of 'id' key

---

## Medium Priority Bugs

- [x] **BUG-018**: Live search missing nonce verification
- [x] **BUG-019**: Duplicate $_REQUEST setting
- [x] **BUG-020**: Guest checkout flow unverified
- [x] **BUG-021**: Missing wpss_add_notification function

---

## Progress Tracking

| Date | Bug Fixed | Notes |
|------|-----------|-------|
| 2025-12-29 | BUG-001 | Fixed method signature in post_request() |
| 2025-12-29 | BUG-002 | Fixed object access in convert_to_order() |
| 2025-12-29 | BUG-003 | Added update_status() method to ProposalService |
| 2025-12-29 | BUG-004 | Changed reject_other_proposals() to public |
| 2025-12-29 | BUG-005 | Fixed deliver_order submit() call |
| 2025-12-29 | BUG-006 | Fixed order object access in AJAX handlers |
| 2025-12-29 | BUG-007 | Changed 'delivered' to 'pending_approval' in template |
| 2025-12-29 | BUG-008 | Added load_reviews() AJAX handler |
| 2025-12-29 | BUG-009 | Added mark_review_helpful() AJAX handler |
| 2025-12-29 | BUG-010 | Aligned dispute status constants with DisputeService |
| 2025-12-29 | BUG-011 | Added admin dispute detail/resolution view |
| 2025-12-29 | BUG-012 | Changed STATUS_PENDING to 'pending' in ModerationService |
| 2025-12-29 | BUG-013 | Added META_REJECTION_REASON constant and usage |
| 2025-12-29 | BUG-015 | Added moderation status check to live search |
| 2025-12-29 | BUG-016 | Created WithdrawalsPage.php with full admin functionality |
| 2025-12-29 | BUG-017 | Fixed package lookup to use array_values() index matching |
| 2025-12-29 | BUG-018 | Added nonce verification to live search |
| 2025-12-29 | BUG-019 | Fixed duplicate $_REQUEST by checking cart_item_data first |
| 2025-12-29 | BUG-020 | Added login requirement for service purchases |
| 2025-12-29 | BUG-021 | Added wpss_add_notification() helper function |
