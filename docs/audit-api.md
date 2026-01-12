# REST API Audit Report

**Date:** 2026-01-11
**Plugin:** WP Sell Services v1.0.0
**Scope:** All REST API controllers in `src/API/`

---

## Executive Summary

**Controllers Audited:** 9
**Total Endpoints:** 100+
**Critical Issues:** 3
**Important Issues:** 10

---

## Critical Issues

### 1. OrdersController - Non-Existent Database Tables

**File:** `src/API/OrdersController.php:312, 360, 418, 466`
**Confidence:** 100%

The OrdersController references tables that **do not exist** in the schema:
- `wpss_order_messages` (lines 312, 360)
- `wpss_order_deliverables` (lines 418, 466)

**Schema Reality:**
```php
// Schema defines these instead:
- wpss_conversations (order_id)
- wpss_messages (conversation_id, sender_id, content)
- wpss_deliveries (order_id, vendor_id, message, attachments)
```

**Impact:**
- `GET /orders/{id}/messages` will fail with SQL errors
- `POST /orders/{id}/messages` will fail to insert
- `GET /orders/{id}/deliverables` will fail
- `POST /orders/{id}/deliverables` will fail

**Fix:** Update queries to use `wpss_conversations`/`wpss_messages` and `wpss_deliveries` tables.

---

### 2. ReviewsController - Missing `updated_at` Column

**File:** `src/API/ReviewsController.php:438, 846`
**Confidence:** 95%

The reviews table schema does NOT include an `updated_at` column, but the controller attempts to set and return it.

```php
// Controller tries to set:
$updates['updated_at'] = current_time( 'mysql' ); // Column doesn't exist!

// Schema (line 423-449) only has created_at, no updated_at
```

**Impact:**
- UPDATE queries may silently fail
- Response always returns `updated_at: null`

**Fix:** Add migration:
```sql
ALTER TABLE wp_wpss_reviews
ADD COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

---

### 3. OrdersController - Requirements Storage Bug

**File:** `src/API/OrdersController.php:753`
**Confidence:** 85%

Requirements are saved using `update_post_meta()` on an order ID, but orders are in a **custom table**, not posts.

```php
// Orders are in wpss_orders table (not posts)
$order = ServiceOrder::find( $order_id );

// But requirements saved to post meta:
update_post_meta( $order_id, '_wpss_submitted_requirements', $sanitized_requirements );
// This will NEVER work - order_id is not a post ID!
```

**Fix:** Use `wpss_order_requirements` table (schema lines 294-305).

---

## Important Issues

### 4. Services Using Post Meta Instead of Tables

**Files:** `src/API/ServicesController.php:432, 447, 693`

The schema defines dedicated tables for packages, FAQs, and requirements, but the controller uses post meta instead:

| Data | Controller Uses | Schema Defines |
|------|-----------------|----------------|
| Packages | `_wpss_packages` post meta | `wpss_service_packages` table |
| FAQs | `_wpss_faqs` post meta | `wpss_service_faqs` table |
| Requirements | `_wpss_requirements` post meta | `wpss_service_requirements` table |

**Impact:** 3 database tables created but completely unused.

---

### 5. DateTime Fatal Error Risk

**File:** `src/API/ConversationsController.php:428, 432, 433, 491`

Code calls `->format('c')` on datetime properties without checking if they're objects:

```php
'created_at' => $message->created_at ? $message->created_at->format( 'c' ) : null,
// Fatal if created_at is a string from database!
```

**Fix:**
```php
'created_at' => is_object( $message->created_at )
    ? $message->created_at->format( 'c' )
    : $message->created_at,
```

---

### 6-10. Additional Schema Issues

| Issue | File | Problem |
|-------|------|---------|
| Redundant columns | ReviewsController | `reviewer_id`/`reviewee_id` duplicate `customer_id`/`vendor_id` |
| Dead column | Schema | `review_type` exists but bidirectional reviews not implemented |
| Mixed data model | BuyerRequestsController:326 | Uses posts AND custom table inconsistently |
| Mixed data model | ProposalsController:182 | Same issue |
| Inconsistent deliverables | OrdersController:466 | Uses `files` column but schema has `attachments` |

---

## Schema Summary

### Tables Queried But Don't Exist
1. `wpss_order_messages`
2. `wpss_order_deliverables`

### Tables Defined But Never Used (40% of schema!)
1. `wpss_service_packages`
2. `wpss_service_faqs`
3. `wpss_service_requirements`
4. `wpss_service_addons`
5. `wpss_order_requirements`
6. `wpss_extension_requests`
7. `wpss_dispute_messages`
8. `wpss_vendor_profiles`
9. `wpss_portfolio_items`
10. `wpss_notifications`
11. `wpss_wallet_transactions`
12. `wpss_service_platform_map`
13. `wpss_analytics_events`

---

## Recommendations

### High Priority (Do Now)
1. Fix OrdersController table references
2. Add `updated_at` to reviews table
3. Fix order requirements storage
4. Add DateTime null checks

### Medium Priority (Next Sprint)
5. Decide: use schema tables OR post meta consistently
6. Remove redundant review columns
7. Clarify buyer requests data model
8. Remove or implement unused tables
