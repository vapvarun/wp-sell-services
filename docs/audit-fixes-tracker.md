# Audit Fixes Tracker

**Source:** Security and Performance audits from 2026-01-11
**Status:** In Progress

---

## Security Audit Fixes

### HIGH Priority
| ID | Issue | File | Status |
|----|-------|------|--------|
| H1 | Unvalidated column names in count() | AbstractRepository.php:248-266 | FIXED (previous session) |

### MEDIUM Priority
| ID | Issue | File | Status |
|----|-------|------|--------|
| M1 | Missing nonce in save_term_meta() | ServiceCategoryTaxonomy.php:214-238 | FIXED |
| M2 | PHP file uploads allowed | DeliveryService.php:344-348 | FIXED |
| M3 | IP address storage without consent | AjaxHandlers.php:953 | PENDING |

### LOW Priority
| ID | Issue | File | Status |
|----|-------|------|--------|
| L1 | Missing bounds checking for floats | BuyerRequestMetabox.php:301,305 | FIXED |
| L2 | Live search nonce skip for guests | AjaxHandlers.php:1547-1551 | PENDING |
| L3 | Direct DB query in template | templates/order/order-view.php:52-59 | FIXED |
| L4 | Verbose error messages | Various AJAX handlers | PENDING |

---

## Performance Audit Fixes (Deferred - Focus on Functionality First)

### N+1 Query Issues
| File | Issue | Status |
|------|-------|--------|
| VendorsController.php:481-494 | get_vendor_reviews() N+1 | PENDING |
| VendorsController.php:596-617 | prepare_item_for_response() N+1 | PENDING |
| VendorsController.php:634-647 | prepare_service_for_response() N+1 | PENDING |
| ServiceArchiveView.php:239-245 | Nested loop for categories | PENDING |
| NotificationService.php:130-141 | get_unread_count() on every call | PENDING |

### Database Indexes (Recommended)
| Table | Index | Status |
|-------|-------|--------|
| wpss_orders | idx_customer (customer_id) | PENDING |
| wpss_orders | idx_vendor (vendor_id) | PENDING |
| wpss_orders | idx_status_date (status, created_at) | PENDING |
| wpss_reviews | idx_service (service_id) | PENDING |
| wpss_reviews | idx_vendor_status (vendor_id, status) | PENDING |
| wpss_notifications | idx_user_unread (user_id, is_read) | PENDING |

---

## Completion Log
- 2026-01-12: M2 fixed - Removed PHP from allowed file types in DeliveryService.php
- 2026-01-12: M1 fixed - Added nonce verification to ServiceCategoryTaxonomy::save_term_meta()
- 2026-01-12: L1 fixed - Added bounds checking for float budget values in BuyerRequestMetabox
- 2026-01-12: L3 fixed - Replaced direct DB query with DeliveryService in order-view.php
