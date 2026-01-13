# WP Sell Services - QA Audit Issues

**Date:** 2026-01-14
**Audit Type:** Comprehensive Code Review
**Total Issues Found:** 35+

---

## Summary by Severity

| Severity | Count |
|----------|-------|
| Critical | 3 |
| High | 8 |
| Medium | 15 |
| Low | 10+ |

---

## Critical Issues

### 1. XSS Vulnerability via Inline Event Handlers (ServiceArchiveView)
- **File:** `src/Frontend/ServiceArchiveView.php`
- **Lines:** 125, 138
- **Issue:** Using `onchange="location.href=this.value"` with unsanitized URLs
- **Status:** FIXED - Removed inline handlers, added `wpss-url-select` class

### 2. XSS Vulnerability via Inline Event Handlers (BuyerRequestArchiveView)
- **File:** `src/Frontend/BuyerRequestArchiveView.php`
- **Lines:** 233, 246
- **Issue:** Same XSS vulnerability with inline event handlers
- **Status:** PENDING

### 3. Undefined Property in ReviewService::add_response()
- **File:** `src/Services/ReviewService.php`
- **Line:** 266
- **Issue:** Property name mismatch between database (`reviewee_id`) and model (`reviewed_id`)
- **Impact:** Review responses may fail for vendors
- **Status:** PENDING

---

## High Priority Issues

### 4. Missing 'create_service' Page Key in Sanitizer
- **File:** `src/Admin/Settings.php`
- **Lines:** 1557-1561
- **Issue:** `sanitize_pages_settings()` doesn't include `create_service` key, causing setting to be lost on save
- **Status:** PENDING

### 5. Missing Nonce Field in Category Edit Form
- **File:** `src/Taxonomies/ServiceCategoryTaxonomy.php`
- **Lines:** 160-190
- **Issue:** `edit_form_fields()` doesn't output nonce field, but `save_term_meta()` checks for it
- **Impact:** Editing category meta will fail nonce verification
- **Status:** PENDING

### 6. Excessive Error Logging Without WP_DEBUG Check
- **File:** `src/Integrations/WooCommerce/WCServiceCarrier.php`
- **Lines:** 139, 143, 150, 155, 159, 173, 175
- **Issue:** Multiple `error_log()` calls without `WP_DEBUG` check
- **Impact:** Performance degradation, disk space issues, security information disclosure
- **Status:** PENDING

### 7. Dashboard wpssData Localization Mismatch
- **File:** `assets/js/dashboard.js` vs `src/Frontend/UnifiedDashboard.php`
- **Issue:** `dashboard.js` expects `wpssData` but PHP localizes as `wpssUnifiedDashboard`
- **Impact:** Dashboard AJAX calls will fail silently
- **Status:** PENDING

### 8. Missing currencyFormat Variable Localization
- **File:** `assets/js/dashboard.js`
- **Line:** 741
- **Issue:** Checks `typeof wpssData.currencyFormat` but it's not defined in localization array
- **Status:** PENDING

### 9. Missing Featured Image Ownership Verification
- **File:** `src/Frontend/ServiceWizard.php`
- **Line:** 1708
- **Issue:** `set_post_thumbnail()` trusts attachment ID without verifying ownership
- **Impact:** Security vulnerability - users can use other users' media files
- **Status:** PENDING

### 10. WC Product Service Meta Missing Owner Validation
- **File:** `src/Integrations/WooCommerce/WCProductProvider.php`
- **Lines:** 293-313
- **Issue:** `save_service_meta()` saves `_wpss_service_id` without owner verification
- **Impact:** Privilege escalation - link WC product to someone else's service
- **Status:** PENDING

### 11. Hardcoded Admin URL in Order View Template
- **File:** `templates/order/order-view.php`
- **Lines:** 27-37
- **Issue:** Uses `admin_url('admin-ajax.php')` directly instead of localized URL
- **Status:** PENDING

---

## Medium Priority Issues

### 12. Settings Form Missing Explicit Nonce Verification
- **File:** `src/Admin/Settings.php`
- **Line:** 72
- **Issue:** Settings registration relies on WordPress built-in nonce but form doesn't explicitly output nonce field
- **Status:** PENDING

### 13. Missing Option Key Defaults
- **File:** `src/Admin/Settings.php`
- **Issue:** No default values registered via `add_option()` - fresh installs may have issues
- **Status:** PENDING

### 14. Unsafe Meta Retrieval Without Type Casting
- **File:** `src/Taxonomies/ServiceCategoryTaxonomy.php`
- **Lines:** 233-255
- **Issue:** Static getter methods don't validate retrieved IDs are valid attachments
- **Status:** PENDING

### 15. Inconsistent Package/Addon Sanitization
- **File:** `src/Frontend/ServiceWizard.php`
- **Lines:** 1515-1532, 1550-1565
- **Issue:** `sanitize_addons()` doesn't validate addon IDs exist or prices are valid
- **Status:** PENDING

### 16. Missing Validation for Requirements Field
- **File:** `src/Frontend/ServiceWizard.php`
- **Lines:** 1570-1580
- **Issue:** `sanitize_requirements()` doesn't validate requirement IDs exist
- **Status:** PENDING

### 17. Quantity Validation Conflicts with Comments
- **File:** `src/Integrations/WooCommerce/WCCheckoutProvider.php`
- **Lines:** 32-35, 92-95
- **Issue:** Comments say quantity restrictions removed but code still enforces quantity = 1
- **Status:** PENDING

### 18. Missing Validation of Package/Addon Data from Cart
- **File:** `src/Integrations/WooCommerce/WCCheckoutProvider.php`
- **Lines:** 54-65
- **Issue:** Package/addon IDs from `$_REQUEST` not validated against service data
- **Impact:** Potential price manipulation
- **Status:** PENDING

### 19. Inconsistent Review Property Mapping
- **File:** `src/Models/Review.php`
- **Lines:** 166-175
- **Issue:** Fallback from `reviewee_id` to `vendor_id` suggests schema inconsistency
- **Status:** PENDING

### 20. Potential Race Condition in Review Submission
- **File:** `src/Services/ReviewService.php`
- **Lines:** 31-51
- **Issue:** Time gap between `has_review()` check and `$wpdb->insert()` allows duplicate reviews
- **Status:** PENDING

### 21. Missing Nonce Verification for Public AJAX
- **File:** `src/Frontend/AjaxHandlers.php`
- **Lines:** 57-60, 874-875, 969-970
- **Issue:** `load_reviews()` and `mark_review_helpful()` with `nopriv` use single shared nonce
- **Status:** PENDING

### 22. Vendor Role Assignment Without Verification
- **File:** `src/Services/VendorService.php`
- **Lines:** 89-93
- **Issue:** No verification that `add_role()` succeeded before adding capabilities
- **Status:** PENDING

### 23. Missing Nonce for Vendor Registration AJAX
- **File:** `src/Frontend/AjaxHandlers.php`
- **Line:** 1398
- **Issue:** Public registration with nonce that may not be passed correctly
- **Status:** PENDING

### 24. CSS !important Overuse
- **File:** `assets/css/design-system.css`
- **Lines:** 158, 199-245
- **Issue:** Extensive use of `!important` flags causing CSS specificity issues
- **Status:** PENDING

### 25. Potential Race Condition in Dashboard Tab Switching
- **File:** `assets/js/dashboard.js`
- **Lines:** 139-157
- **Issue:** `switchTab()` doesn't prevent rapid clicks during loading
- **Status:** PENDING

### 26. Missing Error Handling in Chart Initialization
- **File:** `assets/js/dashboard.js`
- **Lines:** 206-212
- **Issue:** Checks if Chart.js exists but doesn't handle load failures
- **Status:** PENDING

---

## Low Priority Issues

### 27. Inconsistent Sanitization in submit_requirements()
- **File:** `src/Frontend/AjaxHandlers.php`
- **Lines:** 373-394
- **Issue:** Legacy format uses `sanitize_text_field`, new format uses `sanitize_textarea_field`
- **Status:** PENDING

### 28. Incomplete Validation in open_dispute()
- **File:** `src/Frontend/AjaxHandlers.php`
- **Lines:** 1036-1038
- **Issue:** Uses loose comparisons that could pass with "0" string
- **Status:** PENDING

### 29. Transient Race Condition in mark_review_helpful()
- **File:** `src/Frontend/AjaxHandlers.php`
- **Lines:** 978-1009
- **Issue:** Transient set AFTER increment, not before
- **Status:** PENDING

### 30. Inconsistent Event Handler Cleanup
- **File:** `assets/js/single-service.js`
- **Lines:** 127-131
- **Issue:** Uses namespaced events but doesn't clean up consistently
- **Status:** PENDING

### 31. Missing Default Value in formatPrice
- **File:** `assets/js/single-service.js`
- **Lines:** 674-679
- **Issue:** Falls back to hardcoded `$` without validating `wpssService.currencyFormat`
- **Status:** PENDING

### 32. Incomplete Pagination State Management
- **File:** `src/Frontend/ServiceArchiveView.php`
- **Lines:** 186-203
- **Issue:** `paginate_links()` doesn't preserve filter parameters
- **Status:** PENDING

### 33. Cart Count Update May Fail Silently
- **File:** `assets/js/single-service.js`
- **Lines:** 538-546
- **Issue:** Updates multiple cart selectors without error handling
- **Status:** PENDING

### 34. Potentially Undefined Variables in Templates
- **File:** `templates/vendor-dashboard.php`
- **Line:** 71
- **Issue:** Using nullsafe operator but `$stats` object properties not guaranteed
- **Status:** PENDING (has fallback)

### 35. Missing Global Declaration Position
- **File:** `src/Frontend/AjaxHandlers.php`
- **Line:** 987
- **Issue:** Global `$wpdb` declared after significant logic
- **Status:** PENDING

---

## Fix Progress

| Issue # | Status | Commit |
|---------|--------|--------|
| 1 | FIXED | Pending |
| 2-35 | PENDING | - |

---

## Recommended Fix Order

### Phase 1 - Critical & Security (Immediate)
1. XSS vulnerabilities (Issues #1, #2)
2. ReviewService property mismatch (#3)
3. Security validations (#9, #10)

### Phase 2 - High Priority (24 hours)
4. Settings sanitizer (#4)
5. Category edit nonce (#5)
6. Error logging (#6)
7. Dashboard localization (#7, #8)

### Phase 3 - Medium Priority (Week 1)
8. Validation issues (#15-18)
9. Race conditions (#20, #25, #29)
10. WooCommerce fixes (#17, #18)

### Phase 4 - Low Priority (Week 2)
11. Code quality improvements
12. CSS cleanup
13. Event handler consistency

---

## Testing Checklist After Fixes

- [ ] Service creation (admin + frontend wizard)
- [ ] Service display (archive + single)
- [ ] Category management (create + edit)
- [ ] Order flow (cart + checkout + view)
- [ ] Review submission and display
- [ ] Vendor registration
- [ ] Dashboard functionality
- [ ] WooCommerce integration
- [ ] No JavaScript console errors
- [ ] AJAX endpoints working
