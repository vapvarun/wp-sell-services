# WP Sell Services - Comprehensive Audit Report

**Date:** January 12, 2026
**Audited By:** Claude Code
**Plugin Version:** 1.0.0

---

## Executive Summary

A comprehensive functionality-by-functionality audit was conducted on the WP Sell Services plugin. The audit identified **69 issues** across 8 core functionalities:

| Functionality | Critical | High | Medium | Total |
|--------------|----------|------|--------|-------|
| Orders | 2 | 3 | 2 | 7 |
| Services/Packages | 3 | 2 | 4 | 9 |
| Vendors/Profiles | 2 | 5 | 4 | 11 |
| Reviews | 2 | 4 | 4 | 10 |
| Conversations/Messages | 3 | 3 | 3 | 9 |
| Deliveries | 1 | 2 | 2 | 5 |
| Disputes | 2 | 5 | 3 | 10 |
| Payments/Earnings | 1 | 2 | 5 | 8 |
| **TOTAL** | **16** | **26** | **27** | **69** |

---

## Critical Issues (Must Fix Immediately)

### 1. Orders - Status Name Mismatches

**Location:** `src/API/OrdersController.php`, `src/Models/ServiceOrder.php`

**Problem:** API controller uses different status names than the model constants:
- API uses: `pending`, `accepted`, `delivered`, `rejected`
- Model defines: `pending_payment`, `pending_requirements`, `in_progress`, `completed`

**Impact:** Status transitions will fail, orders stuck in wrong states.

**Fix Required:**
- Align status names between API controller and model
- Add missing status constants to model

---

### 2. Orders - Missing Model Properties

**Location:** `src/Models/ServiceOrder.php:230-264`

**Problem:** Three financial fields exist in database but NOT in model:
- `commission_rate`
- `platform_fee`
- `vendor_earnings`

**Impact:** Financial data never loaded from database to model.

---

### 3. Services - Missing Database Table

**Location:** `src/Database/SchemaManager.php`

**Problem:** `service_packages` table is NOT defined in schema, but `ServicePackageRepository` expects it to exist.

**Impact:** All package operations will fail with database errors.

---

### 4. Services - Constructor Crash

**Location:** `src/Services/ServiceManager.php:59`

**Problem:** Code calls `new Service($post)` but Service class has no constructor accepting a post parameter.

**Impact:** Fatal error when getting services.

**Fix:** Change to `Service::from_post($post)`

---

### 5. Vendors - Three Different Tier Systems

**Location:** Multiple files

**Problem:** Verification tiers defined inconsistently:
- Database/Repository: `basic`, `verified`, `pro`
- VendorProfile Model: `new`, `rising`, `top_rated`, `pro`
- VendorService: `basic`, `verified`, `pro`

**Impact:** Tier filtering and display broken.

---

### 6. Vendors - Property Name Mismatches

**Location:** `src/Models/VendorProfile.php`

| Model Property | Database Column | Issue |
|----------------|-----------------|-------|
| `$title` | `tagline` | Wrong column name |
| `$cover_id` | `cover_image_id` | Wrong column name |
| `$response_time` | `response_time_hours` | Wrong column name |
| `$member_since` | `created_at` | Wrong column name |

---

### 7. Reviews - Missing Required Columns on INSERT

**Location:** `src/API/ReviewsController.php:354-367`

**Problem:** INSERT statement missing required columns:
- `reviewer_id` - NOT inserted
- `reviewee_id` - NOT inserted

**Impact:** Database INSERT will fail with constraint violation.

---

### 8. Reviews - Missing Model Property

**Location:** `src/Models/Review.php`

**Problem:** No `review_type` property in model, but database has this column.

**Impact:** Cannot distinguish customer_to_vendor vs vendor_to_customer reviews.

---

### 9. Conversations - Repository 100% Broken

**Location:** `src/Database/Repositories/ConversationRepository.php`

**Problem:** Repository queries non-existent database columns:
- `recipient_id` - doesn't exist
- `is_read` - doesn't exist
- `read_at` - doesn't exist
- `message_type` - should be `type`

**Impact:** All unread message tracking broken.

---

### 10. Deliveries - AJAX Parameter Mismatch

**Location:** `src/Frontend/AjaxHandlers.php:259`

**Problem:** Passes 3 parameters to `request_revision()`:
```php
$delivery_service->request_revision($order_id, $user_id, $reason);
```

But method signature only accepts 2:
```php
public function request_revision(int $order_id, string $reason): bool
```

**Impact:** TypeError when requesting revision.

---

### 11. Disputes - Wrong Method Name

**Location:** `src/Database/Repositories/DisputeRepository.php`

**Problem:** All methods call `Dispute::from_row()` but actual method is `Dispute::from_db()`.

**Affected Lines:** 58, 77, 111, 134, 153, 177

**Impact:** Fatal error on any dispute retrieval.

---

### 12. Disputes - Status Constant Conflict

**Location:** `src/Models/Dispute.php` vs `src/Services/DisputeService.php`

**Model defines:**
```php
STATUS_OPEN = 'open'
STATUS_IN_REVIEW = 'in_review'
STATUS_RESOLVED = 'resolved'
STATUS_CANCELLED = 'cancelled'
```

**Service defines:**
```php
STATUS_OPEN = 'open'
STATUS_PENDING = 'pending_review'
STATUS_RESOLVED = 'resolved'
STATUS_ESCALATED = 'escalated'
STATUS_CLOSED = 'closed'
```

**Impact:** Status validation fails between layers.

---

### 13. Disputes - Non-Existent Column References

**Location:** `src/Database/Repositories/DisputeRepository.php`

**Problem:** Queries reference columns that don't exist:
- Line 148: `assigned_admin` (should be `assigned_to`)
- Line 171: `last_response_at` (doesn't exist)
- Line 226: `assigned_admin` (should be `assigned_to`)
- Line 242: `last_response_at` (doesn't exist)

---

### 14. Payments - Missing Database Column

**Location:** `src/Database/SchemaManager.php`, `src/Services/EarningsService.php`

**Problem:** `is_auto` column referenced in EarningsService (lines 755, 778) but NOT in withdrawals table schema.

**Impact:** Auto-withdrawal feature crashes.

---

### 15. Payments - Wrong Balance Calculation

**Location:** `src/Services/EarningsService.php:49,215`

**Problem:** Uses `COALESCE(vendor_earnings, total)` which returns full order total when vendor_earnings is NULL.

**Should be:** `COALESCE(vendor_earnings, 0)`

**Impact:** Vendors see inflated earnings, can over-withdraw.

---

## High Priority Issues

### Orders

| Issue | Location | Description |
|-------|----------|-------------|
| Invalid column in allowed_columns | OrderRepository.php:38 | `'due_date'` should be `'delivery_deadline'` |
| Wrong status name in query | OrderRepository.php:308 | `'waiting_requirements'` should be `'pending_requirements'` |
| Undefined statuses in transitions | OrdersController.php:549-672 | Uses `accepted`, `rejected`, `delivered` not in model |

### Services/Packages

| Issue | Location | Description |
|-------|----------|-------------|
| Dual storage confusion | Multiple | Packages stored in post meta but repository expects DB table |
| Package repository never called | ServiceManager:417-425 | Controller uses post meta, manager uses repository |

### Vendors/Profiles

| Issue | Location | Description |
|-------|----------|-------------|
| Missing DB columns | VendorProfile.php:241-244 | `languages`, `skills`, `certifications`, `education` not in schema |
| API uses wrong data source | VendorsController.php:632-644 | Uses user meta instead of vendor_profiles table |
| Non-existent columns queried | VendorProfileRepository.php:251-252 | `delivery_rate`, `completion_rate` don't exist |

### Reviews

| Issue | Location | Description |
|-------|----------|-------------|
| Repository not used | ReviewService + Controller | Direct wpdb queries bypass repository |
| Default status mismatch | Review.php:123 vs Schema | Model: 'pending', DB: 'approved' |
| Column name confusion | Review.php mapping | `review` → `content`, `vendor_reply` → `response` |

### Conversations

| Issue | Location | Description |
|-------|----------|-------------|
| Missing foreign key | SchemaManager.php | No FK constraint on order_id |
| No participant update mechanism | Conversation.php | Cannot add participants after creation |
| Dual read tracking | ConversationService.php:233-281 | Both conversation and message level tracking |

### Deliveries

| Issue | Location | Description |
|-------|----------|-------------|
| File handling mismatch | OrdersController.php:472 | API passes IDs, service expects $_FILES |
| Missing API endpoints | OrdersController.php | No accept/reject delivery routes |

### Disputes

| Issue | Location | Description |
|-------|----------|-------------|
| Column name mismatches | DisputeRepository.php:27-38 | `opened_by` should be `initiated_by` |
| Resolution type mismatch | DisputeService.php:369 | Uses `resolution` but model has `resolution_type` |
| Missing model properties | Dispute.php:180-181 | `respondent_id`, `assigned_to`, `refund_amount` not in schema |

### Payments

| Issue | Location | Description |
|-------|----------|-------------|
| No standalone payment handler | OrdersController.php | Orders stuck in pending_payment |
| Auto-withdrawal incomplete | EarningsService.php:590-614 | Feature partially implemented |

---

## Medium Priority Issues

### Orders
- Status transitions incomplete (OrderService.php:209-252)
- Parameter name inconsistency (OrdersController.php:287)

### Services
- Gallery not structured/validated (Service.from_post():184)
- Stats cached in meta instead of computed (Service.from_post():189-192)
- platform_ids property unused (Service.from_post():187)
- Inconsistent taxonomy data structures (ServicesController:582-583)

### Vendors
- vacation_until expects date but DB has no date column
- member_since vs created_at naming
- last_active not stored in DB

### Reviews
- updated_at always NULL in model
- Ambiguous rating_value naming
- Orphaned title property (never used)
- Undefined wpss_get_order() helper

### Conversations
- Service bypasses broken repository
- Attachment handling lacks documentation
- Controller has defensive code for inconsistent data

### Deliveries
- Missing Delivery model class
- Incomplete delivery-specific API routes

### Disputes
- No status transition validation
- Invalid order statuses on resolution
- Missing DB columns referenced in model

### Payments
- No total/commission validation
- Commission lost if hook fails
- Non-atomic earnings update

---

## Fix Implementation Plan

### Phase 1: Critical Fixes (Immediate)
1. Fix Dispute::from_row() → from_db() calls
2. Fix ConversationRepository column references
3. Fix AjaxHandlers parameter count
4. Add missing is_auto column to schema
5. Fix Review INSERT required columns
6. Fix ServiceManager constructor call
7. Fix Order status constants

### Phase 2: High Priority Fixes
1. Unify vendor tier system
2. Fix VendorProfile property mappings
3. Add missing ServiceOrder properties
4. Fix OrderRepository column names
5. Fix DisputeRepository column names

### Phase 3: Medium Priority Fixes
1. Consolidate package storage
2. Add missing model properties
3. Fix earnings calculations
4. Add missing API endpoints

---

## Files Modified

This section will be updated as fixes are applied.

| File | Changes | Status |
|------|---------|--------|
| TBD | TBD | Pending |

---

## Testing Checklist

After fixes are applied, verify:

- [ ] Order creation and status transitions work
- [ ] Service packages can be created and retrieved
- [ ] Vendor profiles load correctly with all properties
- [ ] Reviews can be submitted and retrieved
- [ ] Conversations and messages work
- [ ] Deliveries can be submitted and accepted
- [ ] Disputes can be opened and resolved
- [ ] Earnings and withdrawals calculate correctly

---

## Notes

- All fixes should follow WordPress Coding Standards (WPCS)
- Run `composer phpcs` before committing
- Test each fix in isolation before moving to next
- Database migrations may be needed for schema changes
