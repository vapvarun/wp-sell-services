# REST API Audit Report

**Date:** 2026-01-11
**Plugin:** WP Sell Services v1.0.0
**Scope:** All REST API controllers in `src/API/`

---

## Executive Summary

Audited **9 REST API controllers** with **100+ endpoints**. Found **13 issues** ranging from critical schema mismatches to important consistency problems.

**Critical Issues:** 3
**Important Issues:** 10

---

## Critical Issues

### 1. OrdersController - Non-existent Tables

**File:** `src/API/OrdersController.php:312, 360, 418, 466`
**Severity:** CRITICAL

The OrdersController references database tables that **do not exist** in the schema:
- `wpss_order_messages` (lines 312, 360)
- `wpss_order_deliverables` (lines 418, 466)

**Schema Reality:**
- `wpss_messages` (linked to conversations, not orders directly)
- `wpss_deliveries` (different structure than queried)

**Impact:**
- `GET /orders/{id}/messages` fails with SQL errors
- `POST /orders/{id}/messages` fails to insert
- `GET /orders/{id}/deliverables` fails
- `POST /orders/{id}/deliverables` fails

**Fix:** Use `wpss_conversations` and `wpss_messages` tables via ConversationService

---

### 2. ReviewsController - Missing Schema Column

**File:** `src/API/ReviewsController.php:438, 846`
**Severity:** CRITICAL

The `reviews` table schema does **not include** an `updated_at` column, but the controller attempts to set and return it.

**Impact:**
- UPDATE queries may silently fail
- Response always returns `updated_at: null`

**Fix:** Add `updated_at` column to schema in SchemaManager.php

---

### 3. OrdersController - Requirements Stored to Wrong Location

**File:** `src/API/OrdersController.php:753`
**Severity:** CRITICAL

Requirements are saved using `update_post_meta( $order_id, ... )` but orders are stored in a **custom table**, not as posts.

**Impact:**
- Requirements will **never be retrieved** (post meta on non-post ID)
- Data stored incorrectly

**Fix:** Use the `wpss_order_requirements` table defined in schema

---

## Important Issues

### 4. OrdersController - Inconsistent Deliverables Schema
**File:** `src/API/OrdersController.php:466-478`

Uses `files` column but schema defines `attachments`. Uses `maybe_serialize()` but schema expects JSON.

### 5. ServicesController - Packages Schema Mismatch
**File:** `src/API/ServicesController.php:432, 677-680`

Packages retrieved from post meta but schema defines dedicated `wpss_service_packages` table.

### 6. ServicesController - FAQs Schema Mismatch
**File:** `src/API/ServicesController.php:447-449`

FAQs retrieved from post meta but schema defines `wpss_service_faqs` table.

### 7. ServicesController - Requirements Schema Mismatch
**File:** `src/API/ServicesController.php:693-694`

Requirements saved to post meta but schema defines `wpss_service_requirements` table.

### 8. ReviewsController - Redundant Columns
**File:** Schema lines 424-430

`reviewer_id` and `reviewee_id` are redundant with `customer_id` and `vendor_id`.

### 9. ConversationsController - DateTime Object Usage
**File:** `src/API/ConversationsController.php:428, 432, 491`

Calls `->format('c')` on datetime properties without checking if they're objects. If string from database, will fatal error.

### 10. BuyerRequestsController - Mixed Data Model
**File:** `src/API/BuyerRequestsController.php:326-327`

Permission check uses posts (`wpss_request` post type), but schema defines `wpss_buyer_requests` table.

### 11. ProposalsController - Mixed Data Model
**File:** `src/API/ProposalsController.php:182`

Similar to buyer requests - permission check uses `get_post()` but schema defines `wpss_proposals` table.

---

## Tables Defined But Unused

These 13 tables are created in schema but **never queried** by controllers:

1. `wpss_service_packages` - Packages in post meta instead
2. `wpss_service_faqs` - FAQs in post meta instead
3. `wpss_service_requirements` - Requirements in post meta instead
4. `wpss_service_addons` - Not referenced anywhere
5. `wpss_order_requirements` - Not used
6. `wpss_extension_requests` - Not implemented
7. `wpss_dispute_messages` - Not used
8. `wpss_vendor_profiles` - Vendors use user meta instead
9. `wpss_portfolio_items` - Not implemented
10. `wpss_notifications` - No controller
11. `wpss_wallet_transactions` - No controller
12. `wpss_service_platform_map` - Not implemented
13. `wpss_analytics_events` - No controller

## Tables Queried But Not Defined

1. ❌ `wpss_order_messages` - Used in OrdersController
2. ❌ `wpss_order_deliverables` - Used in OrdersController

---

## Priority Fixes

### Must Fix (Blocks Functionality)
1. OrdersController: Replace non-existent table queries
2. OrdersController: Fix requirements storage
3. ConversationsController: Add null checks for DateTime

### Should Fix (Data Integrity)
4. Add `updated_at` to reviews table
5. Unify service metadata (tables OR meta, not both)
6. Remove redundant review columns

### Can Defer
7. Remove 13 unused tables (40% of schema)
8. Implement missing features or remove tables
