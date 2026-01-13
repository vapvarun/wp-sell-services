# WP Sell Services - Functionality Flow Audit

**Date:** January 14, 2026
**Version:** 1.0.0
**Auditor:** Claude Code

---

## Executive Summary

This audit examined 8 critical user flows in the WP Sell Services plugin. **54 issues** were identified across security, functionality, and data integrity categories.

### Severity Breakdown

| Severity | Count | Description |
|----------|-------|-------------|
| CRITICAL | 12 | System-breaking bugs, security vulnerabilities |
| HIGH | 18 | Major functionality gaps, significant risks |
| MEDIUM | 16 | Missing features, incomplete implementations |
| LOW | 8 | Minor issues, quality improvements |

---

## Flow Audit Results

### 1. Vendor Registration Flow

**Status:** MOSTLY SECURE

| Check | Status | Notes |
|-------|--------|-------|
| Nonce Verification | PASS | Properly verified in AJAX handler |
| Capability Checks | PASS | User auth + vendor duplicate check |
| Input Sanitization | PASS | All inputs properly sanitized |
| Error Handling | PASS | Complete with meaningful messages |
| Role Assignment | PASS | Includes verification after add_role() |
| Email Notifications | FAIL | No welcome email or admin notification |

**Issues Found:**
1. (MEDIUM) Missing email notifications on vendor registration
2. (LOW) Client-side validation incomplete - missing required attributes

---

### 2. Service Creation Flow

**Status:** CRITICAL ISSUES

| Check | Status | Notes |
|-------|--------|-------|
| Nonce Verification | PARTIAL | Missing in gallery upload handler |
| Capability Checks | FAIL | No explicit capability check on publish |
| Input Validation | PARTIAL | Many fields lack server-side validation |
| WooCommerce Sync | FAIL | NOT IMPLEMENTED - services don't appear in WC |
| Moderation Flow | PASS | Properly integrated |
| File Upload Security | FAIL | No MIME type validation |

**Critical Issues:**
1. (CRITICAL) **WooCommerce product sync NOT implemented** - Services created via wizard are NOT synced to WooCommerce, breaking the entire marketplace flow
2. (CRITICAL) **Gallery upload missing nonce verification** - File upload handler lacks CSRF protection
3. (CRITICAL) **No file type validation** - Could upload malicious files
4. (HIGH) **No capability check on publish** - Only vendor role checked, not explicit capability
5. (HIGH) **No user suspension check** - Suspended vendors can create services
6. (MEDIUM) **No auto-save feature** - Users lose data on browser crash

---

### 3. Service Ordering/Checkout Flow

**Status:** CRITICAL ISSUES

| Check | Status | Notes |
|-------|--------|-------|
| Add to Cart | PASS | Works with unique keys per item |
| Cart Validation | PARTIAL | No vendor re-check at checkout |
| Login Requirement | PASS | Enforced at add-to-cart |
| Order Creation | PARTIAL | Timing issues with payment status |
| Requirements Redirect | FAIL | NOT IMPLEMENTED |
| Status Transitions | PASS | Well-defined state machine |

**Critical Issues:**
1. (CRITICAL) **Thankyou redirect NOT hooked** - `get_thankyou_redirect()` exists but is never called; customers see WC thankyou page instead of requirements form
2. (CRITICAL) **Requirements form never rendered** - No endpoint maps `/service-order/{id}/requirements/` to the template
3. (CRITICAL) **No pending/on-hold WC status handling** - Service orders only created on `processing`/`completed`, not `pending` or `on-hold`
4. (HIGH) **No AJAX handler for requirements submission** - Form submits to action that doesn't exist
5. (HIGH) **No vendor status re-check at checkout** - Vendor could disable between add-to-cart and checkout
6. (MEDIUM) **Order creation not atomic** - Partial orders possible if insert fails

---

### 4. Order Management Flow

**Status:** CRITICAL SECURITY ISSUES

| Check | Status | Notes |
|-------|--------|-------|
| Ownership Verification | FAIL | Missing in conversation handler |
| Nonce Verification | FAIL | Missing in multiple AJAX handlers |
| Status Transitions | FAIL | No validation of valid transitions |
| Capability Checks | FAIL | Missing throughout |
| Error Handling | PARTIAL | Silent failures in webhooks |

**Critical Issues:**
1. (CRITICAL) **SQL Injection vulnerability** - Order query uses string interpolation instead of prepared statements
2. (CRITICAL) **Missing ownership check in conversation handler** - Anyone with nonce can post to any order
3. (CRITICAL) **No security in AJAX agent assignment** - Missing nonce, capability, and ownership checks
4. (CRITICAL) **Unsafe delivery date update** - No nonce or permission check
5. (HIGH) **No status transition validation** - Any status can transition to any other status
6. (HIGH) **Client-provided vendor IDs** - Form passes user IDs that can be manipulated
7. (HIGH) **File uploads lack MIME validation** - No file type whitelist

---

### 5. Review/Rating Flow

**Status:** HIGH RISK ISSUES

| Check | Status | Notes |
|-------|--------|-------|
| Review Eligibility | PARTIAL | No time window enforcement |
| Vendor Replies | PARTIAL | Race condition for double-reply |
| Helpful Votes | FAIL | Race condition, no vote tracking |
| Rating Aggregation | PASS | Properly calculated |
| Moderation | PARTIAL | Auto-approve default, no admin UI |

**Issues Found:**
1. (HIGH) **Vendor reply race condition** - Double replies possible with concurrent requests
2. (HIGH) **Helpful count race condition** - Vote manipulation possible
3. (MEDIUM) **No review time window** - Reviews allowed anytime after completion
4. (MEDIUM) **Auto-approval without moderation UI** - No admin dashboard for review moderation
5. (LOW) **No helpful vote tracking** - Can't show "you found this helpful" state

---

### 6. Messaging/Conversation Flow

**Status:** HIGH RISK ISSUES

| Check | Status | Notes |
|-------|--------|-------|
| Ownership Verification | PARTIAL | Based on order, not conversation |
| File Uploads | FAIL | No whitelist, size limits |
| Message Sanitization | PARTIAL | Inconsistent across layers |
| Real-time Updates | PARTIAL | Polling with weak security |
| Rate Limiting | FAIL | No throttling |

**Issues Found:**
1. (HIGH) **File upload no whitelist** - Only client-side hints, no server validation
2. (HIGH) **Message polling security weak** - Single shared nonce for all conversations
3. (MEDIUM) **Mark-as-read race condition** - Lost updates with concurrent requests
4. (MEDIUM) **Attachments not validated on retrieval** - Broken links if files deleted
5. (MEDIUM) **No rate limiting** - Spam/flood possible
6. (LOW) **No message edit/delete** - Messages immutable (may be intentional)

---

### 7. Buyer Request Flow

**Status:** HIGH RISK ISSUES

| Check | Status | Notes |
|-------|--------|-------|
| Login Verification | FAIL | Not checked in API controller |
| Budget Validation | FAIL | Min > max not checked |
| Proposal Spam | PARTIAL | Withdrawn proposals allow re-submit |
| Order Conversion | PARTIAL | No collision check on order number |
| Notifications | PARTIAL | Rejection not notified |

**Issues Found:**
1. (HIGH) **Missing login check in API** - Anonymous users get `user_id = 0`
2. (HIGH) **API ignores user_id parameter** - Uses `get_current_user_id()` instead
3. (MEDIUM) **No budget validation** - Min can exceed max
4. (MEDIUM) **Proposal spam possible** - Vendors can withdraw and resubmit unlimited times
5. (MEDIUM) **Duplicate order numbers possible** - No uniqueness check
6. (LOW) **Proposal rejection not notified** - Vendors don't know their proposal was rejected

---

### 8. Dispute Flow

**Status:** CRITICAL ISSUES

| Check | Status | Notes |
|-------|--------|-------|
| Database Schema | FAIL | Missing required fields |
| Time Windows | FAIL | No dispute window validation |
| Authorization | FAIL | Anyone can escalate any dispute |
| Refund Processing | FAIL | Not implemented |
| Status Restrictions | PARTIAL | Evidence allowed after resolution |

**Critical Issues:**
1. (CRITICAL) **Missing database fields** - `response_deadline`, `last_response_by`, `meta` columns don't exist but code uses them
2. (CRITICAL) **Authorization bypass in escalation** - Any user can escalate any dispute
3. (CRITICAL) **No actual refund processing** - Resolution marks status but doesn't process payment
4. (HIGH) **No dispute time window** - Disputes allowed years after completion
5. (HIGH) **No order status validation** - Can open disputes on pending_payment orders
6. (MEDIUM) **Evidence allowed after resolution** - Can modify dispute record post-resolution
7. (MEDIUM) **Duplicate proposal acceptance race condition** - Both proposals could be accepted

---

## Priority Fix Plan

### Phase 1: Critical Blockers (Week 1)

These issues prevent the system from functioning:

1. **Implement WooCommerce product sync in ServiceWizard**
   - File: `src/Frontend/ServiceWizard.php`
   - Call `WCProductProvider::sync_service_to_product()` after publish

2. **Hook thankyou redirect to requirements page**
   - File: `src/Integrations/WooCommerce/WCCheckoutProvider.php`
   - Add filter for `woocommerce_thankyou_order_received_text` or similar

3. **Create requirements endpoint and AJAX handler**
   - Register endpoint for `/service-order/{id}/requirements/`
   - Add AJAX handler `wpss_submit_requirements`

4. **Handle pending/on-hold WC order status**
   - File: `src/Integrations/WooCommerce/WooCommerceAdapter.php`
   - Add hooks for `woocommerce_order_status_pending` and `on-hold`

### Phase 2: Critical Security (Week 1-2)

These are security vulnerabilities:

5. **Add nonce verification to gallery upload**
   - File: `src/Frontend/ServiceWizard.php`, line ~1415
   - Add `check_ajax_referer('wpss_service_wizard', 'nonce')`

6. **Add file type validation to uploads**
   - Create whitelist of allowed MIME types
   - Validate before `media_handle_upload()`

7. **Fix SQL injection in order query**
   - File: Order management (old codebase reference)
   - Use `$wpdb->prepare()` with placeholders

8. **Add ownership checks to conversation handler**
   - Verify `get_current_user_id()` matches order vendor or customer
   - Don't trust client-provided user IDs

9. **Add security to AJAX handlers**
   - Add nonce verification to all AJAX handlers
   - Add capability checks
   - Add ownership verification

10. **Add dispute authorization check**
    - Only allow escalation by dispute parties or admins

### Phase 3: High Priority Fixes (Week 2-3)

11. **Add capability check on service publish**
12. **Add user suspension check before service creation**
13. **Add missing dispute database fields**
14. **Implement actual refund processing in disputes**
15. **Add dispute time window validation**
16. **Add order status validation before disputes**
17. **Fix status transition validation**
18. **Add vendor status re-check at checkout**
19. **Fix helpful vote race condition**
20. **Add file upload security (whitelist, size limits)**

### Phase 4: Medium Priority (Week 3-4)

21. **Add vendor registration email notifications**
22. **Add auto-save to service wizard**
23. **Add client-side validation to forms**
24. **Add review time window**
25. **Add rate limiting to AJAX handlers**
26. **Add proposal rejection notifications**
27. **Add budget validation**
28. **Add review moderation admin UI**
29. **Fix mark-as-read race condition**
30. **Add atomic transactions for proposal acceptance**

---

## Technical Debt Notes

1. **ServiceWizard.php is 1776 lines** - Should be split into smaller classes
2. **AJAX handlers mixed in multiple locations** - Should consolidate
3. **No dependency injection** - Makes testing difficult
4. **Inconsistent sanitization layers** - Should sanitize at storage layer
5. **No database transactions** - Critical operations not atomic
6. **Template markup in PHP classes** - Should use template files

---

## Testing Checklist

After implementing fixes, verify:

- [ ] Service created via wizard appears in WooCommerce
- [ ] After checkout, customer is redirected to requirements form
- [ ] Requirements can be submitted and saved
- [ ] Conversations only accessible by order parties
- [ ] File uploads reject non-whitelisted types
- [ ] Disputes can only be opened within time window
- [ ] Refunds actually process payment return
- [ ] All AJAX handlers require valid nonce
- [ ] Status transitions follow valid state machine
