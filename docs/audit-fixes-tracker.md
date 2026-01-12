# Audit Fixes Tracker

**Source:** Security, API, AJAX, Templates, and Performance audits from 2026-01-11
**Status:** Complete (excluding performance items which are deferred)

---

## REST API Audit Fixes (CRITICAL)

### CRITICAL Priority
| ID | Issue | File | Status |
|----|-------|------|--------|
| API-C1 | OrdersController references NON-EXISTENT tables (wpss_order_messages, wpss_order_deliverables) | OrdersController.php:312,360,418,466 | N/A - Code already correct |
| API-C2 | ReviewsController uses `updated_at` column NOT in schema | ReviewsController.php:438,846 | N/A - Schema has column |
| API-C3 | OrdersController saves requirements to post_meta (wrong location) | OrdersController.php:753 | N/A - Code uses correct table |

### IMPORTANT Priority
| ID | Issue | File | Status |
|----|-------|------|--------|
| API-I1 | Inconsistent deliverables schema (files vs attachments, serialize vs JSON) | OrdersController.php:466-478 | DEFERRED - Naming only, code works correctly with JSON decode |
| API-I2 | ServicesController packages from post_meta but schema defines table | ServicesController.php:432,677-680 | RESOLVED - Standardized on post_meta, removed table definition |
| API-I3 | ServicesController FAQs from post_meta but schema defines table | ServicesController.php:447-449 | RESOLVED - Standardized on post_meta, removed table definition |
| API-I4 | ServicesController requirements to post_meta but schema defines table | ServicesController.php:693-694 | RESOLVED - Standardized on post_meta, removed table definition |
| API-I5 | ConversationsController DateTime without null checks | ConversationsController.php:428,432,491 | FIXED |

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
| M3 | IP address storage without consent | AjaxHandlers.php:953 | N/A - IP hashed for transient key (1 week expiry), not stored permanently |

### LOW Priority
| ID | Issue | File | Status |
|----|-------|------|--------|
| L1 | Missing bounds checking for floats | BuyerRequestMetabox.php:301,305 | FIXED |
| L2 | Live search nonce skip for guests | AjaxHandlers.php:1547-1551 | N/A - Acceptable for public read-only endpoint |
| L3 | Direct DB query in template | templates/order/order-view.php:52-59 | FIXED |
| L4 | Verbose error messages | Various AJAX handlers | N/A - Messages are appropriately generic, no sensitive info exposed |

---

## AJAX Audit Fixes

### MEDIUM Priority
| ID | Issue | File | Status |
|----|-------|------|--------|
| AJAX-1 | VendorDashboard 8 AJAX handlers need nonce/capability verification | VendorDashboard.php:94-101 | VERIFIED - All have check_ajax_referer() |

---

## Templates Audit Fixes (Grade: A - Minor Issues)

### LOW Priority (Code Quality)
| ID | Issue | File | Status |
|----|-------|------|--------|
| TPL-1 | Double escaping on _n() returns | dashboard/sections/requests.php:104-106 | N/A - Correct escaping (redundant but safe) |
| TPL-2 | Double escaping on _n() returns | content-request-card.php:135-137 | N/A - Correct escaping (redundant but safe) |
| TPL-3 | Double escaping on _n() returns | single-request.php:210,227-228 | N/A - Correct escaping (redundant but safe) |

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
- 2026-01-12: L4 reviewed - Error messages are appropriately generic
- 2026-01-12: M3 reviewed - IP only used for transient key (hashed), acceptable
- 2026-01-12: TPL-1,2,3 reviewed - Escaping is correct (redundant but safe)
- 2026-01-12: L2 reviewed - Acceptable for public read-only search endpoint
- 2026-01-12: API-I1-I4 reviewed - Design decisions, code functions correctly
- 2026-01-12: API-I5 fixed - Removed .format('c') calls on datetime values in ConversationsController
- 2026-01-12: AJAX-1 verified - All VendorDashboard handlers have check_ajax_referer()
- 2026-01-12: M2 fixed - Removed PHP from allowed file types in DeliveryService.php
- 2026-01-12: M1 fixed - Added nonce verification to ServiceCategoryTaxonomy::save_term_meta()
- 2026-01-12: L1 fixed - Added bounds checking for float budget values in BuyerRequestMetabox
- 2026-01-12: L3 fixed - Replaced direct DB query with DeliveryService in order-view.php
- 2026-01-12: API-I2,I3,I4 resolved - Standardized on post_meta approach for packages, FAQs, requirements:
  - Removed table definitions from SchemaManager.php (wpss_service_packages, wpss_service_faqs, wpss_service_requirements)
  - Updated ServiceManager.php save_faqs(), delete_faqs(), save_requirements(), delete_requirements() to use post_meta
  - Simplified functions.php wpss_get_service_packages() and wpss_get_service_requirements() to use post_meta only
  - Updated ManualOrderPage.php to use post_meta instead of database query for package lookup
  - Decision: post_meta is sufficient for current needs; can migrate to dedicated tables later if needed
