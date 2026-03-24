# WP Sell Services — Gap Analysis & Improvement Areas

**Date:** 2026-03-17 | **Cross-referenced: Codebase + Basecamp Bug Reports**

---

## Confirmed Bugs (from Basecamp)

### CRITICAL — 15 Open Bugs (Unassigned)

| # | Bug | Root Cause | Fix Location |
|---|-----|-----------|--------------|
| 1 | "Required Skills" field missing in Buyer Request form | `required_skills` param absent from `BuyerRequestsController::create_item()` route args | `src/API/BuyerRequestsController.php` lines 97-129 |
| 2 | Vendor Reply Not Visible in Messages after "Contact Me" | Direct conversation messages may not be returning to buyer in query | `src/Services/ConversationService.php`, `src/API/ConversationsController.php` |
| 3 | Product Image instead of User Avatar in Messages List | Avatar resolution in message list template using wrong ID | `templates/dashboard/sections/messages.php`, avatar filter in `Plugin.php` |
| 4 | Email sent to vendor when withdrawal is *approved* (wrong trigger) | Email type mismatch — approval action triggering wrong email template | `src/Services/EmailService.php` withdrawal handler |
| 5 | Pro Feature Notice on Earnings Page (for vendors) | `ProTeaser` showing earnings upsell even when context is inappropriate | `src/Admin/ProTeaser.php` — condition check may fire on frontend |
| 6 | No option to upload profile cover image | `VendorProfile.cover_id` exists in model but no frontend upload field | `templates/dashboard/sections/profile.php` — add cover image upload |
| 7 | Duplicate email notification settings | Possible overlap between legacy WC email settings and new standalone settings | `src/Admin/Settings.php` email tab |
| 8 | Buyer-initiated order cancellation issues | Cancellation flow may not properly handle buyer-side initiation | `src/Services/OrderService.php` cancellation methods |
| 9 | Service Requirements Not Collected During Purchase | Requirements step may be skipped in standalone checkout flow | `src/Integrations/Standalone/StandaloneCheckoutProvider.php` |
| 10 | Seller Unable to Update Order Status from "Manage" Button | Frontend JS action handler may not match REST endpoint | `assets/js/frontend.js` order action handlers |
| 11 | Same Email Body for Admin and Vendor on Dispute Opened | `EmailService` dispute handler sends identical content to both recipients | `src/Services/EmailService.php` dispute email method |
| 12 | Order Timeline Not Updated After Order Cancellation | System message may not be added to conversation on cancel | `src/Services/OrderWorkflowManager.php` |
| 13 | Duplicate Redirection on "View Cart" and "Checkout" Buttons | Multiple redirect triggers in standalone checkout flow | `src/Integrations/Standalone/StandaloneCheckoutProvider.php` |
| 14 | WordPress database error in error log | Possible missing column or malformed query | `src/Database/SchemaManager.php`, check `debug.log` |
| 15 | Commission Not Applied Correctly | Rate may not account for add-on totals or edge cases | `src/Services/CommissionService.php` |

### UI Issues (3 cards — today)

| # | Issue | Assigned | Fix Location |
|---|-------|----------|--------------|
| 1 | Success Message UI Not Displayed Properly | Nitin | CSS / JS toast handler |
| 2 | Inconsistent Font Size for "Direct Message" Label | Unassigned | `assets/css/unified-dashboard.css` |
| 3 | Spacing Issue on Buyer Request Detail Page | Unassigned | `.wpss-request-content` CSS |

### Ready for Testing (5 cards)

| # | Issue | Assigned |
|---|-------|----------|
| 1 | Inconsistent UI Layout for `.wpss-order-card` After Completion | Nitin |
| 2 | Cancel Button Not Working on Contact Modal | Unassigned |
| 3 | Withdrawal screen | Nitin |
| 4 | Action status | Nitin |
| 5 | Plain Text Email for Direct Message via "Contact Me" | Unassigned |

---

## Architectural Gaps (Not in Basecamp — Found via Code Audit)

### HIGH PRIORITY

| # | Gap | Impact | Recommendation |
|---|-----|--------|----------------|
| 1 | **No caching layer** — Dashboard stats (`API::get_dashboard()`) run raw `$wpdb->get_row()` on every call | Performance at scale — every dashboard load hits DB | Add transient caching (5-min TTL) for dashboard stats, invalidate on order status change |
| 2 | **Cart stored in user meta** (`_wpss_cart`) — lost when user logs in from new device | UX — buyers lose cart items cross-device | Consider session-based cart with fallback to user meta, or sync cart on login |
| 3 | **`VendorDashboard` (deprecated) still initialized** alongside `UnifiedDashboard` | Potential duplicate shortcodes/AJAX handlers, confusion | Remove `VendorDashboard` initialization from `Plugin.php` before 2.0 |
| 4 | **No refund flow for Stripe/PayPal** in free plugin UI | Admin can't issue refunds through the plugin interface | Add refund action on admin order detail page that calls gateway `process_refund()` |
| 5 | **`update_option()` for reminder tracking** (`wpss_requirements_reminder_{order_id}`) | `wp_options` table bloat at scale (one row per order) | Move to `wpss_notifications` table or use transients with autoload=false |

### MEDIUM PRIORITY

| # | Gap | Impact | Recommendation |
|---|-----|--------|----------------|
| 6 | **Package data in post meta** (`_wpss_packages` serialized array) | Can't query "services under $50" without full table scan | Already have `wpss_service_packages` table — migrate fully, deprecate post meta |
| 7 | **No webhook retry handling** for failed Stripe/PayPal events | Missed payment confirmations if webhook fails once | Add webhook event logging table and retry queue |
| 8 | **No mobile push notification infrastructure** | `_wpss_push_devices` user meta exists but no push service integration | Wire FCM/APNs push via `NotificationService` alongside email/in-app |
| 9 | **No automated testing for payment flows** | Stripe/PayPal flows are untestable without manual setup | Add PHPUnit tests using Stripe test mode + mock gateway |
| 10 | **REST API has no versioning strategy** | Breaking changes will affect mobile apps | Document v1 contract, plan v2 namespace for breaking changes |

### LOW PRIORITY (Polish)

| # | Gap | Impact | Recommendation |
|---|-----|--------|----------------|
| 11 | No bulk actions on admin orders/disputes tables | Admin efficiency at scale | Add bulk status change, bulk export |
| 12 | No activity log / audit trail for admin actions | Compliance / accountability | Log admin status overrides, moderation decisions, dispute resolutions |
| 13 | No multi-language support beyond .pot file | International marketplace readiness | Verify all strings are translatable, test with Polylang/WPML |
| 14 | No rate limiting on public REST endpoints | API abuse potential | Extend `RateLimiter` to public endpoints (search, categories) |
| 15 | Inline jQuery in `wpss_standalone_migration_notice` handler | CSP-strict environments will block it | Move to enqueued script file |

---

## Feature Completeness Score

| Category | Implemented | Working | Score |
|----------|------------|---------|-------|
| Service Marketplace Core | 8/8 | 7/8 (requirements collection bug) | 87% |
| Order Management | 8/8 | 6/8 (status actions + timeline bugs) | 75% |
| Payment Infrastructure | 10/10 | 8/10 (cart redirect + commission bugs) | 80% |
| Vendor Ecosystem | 8/8 | 7/8 (cover image missing UI) | 87% |
| Buyer Experience | 9/9 | 7/9 (skills field + messaging bugs) | 78% |
| Communication System | 4/4 | 2/4 (email body + avatar bugs) | 50% |
| Commission & Revenue | 7/7 | 6/7 (commission calculation bug) | 85% |
| Dispute Resolution | 5/5 | 4/5 (same email body bug) | 80% |
| Analytics & Reporting | 3/3 | 3/3 | 100% |
| Admin Controls | 8/8 | 7/8 (duplicate settings) | 87% |
| Developer Extensibility | 9/9 | 9/9 | 100% |
| SEO | 4/4 | 4/4 | 100% |
| Security | 7/7 | 7/7 | 100% |
| **OVERALL** | **90/90** | **77/90** | **85%** |

---

## Priority Action Plan

### Week 1 — Fix Critical Bugs (15 Basecamp cards)
1. Fix all 15 open bugs in Bugs column
2. Test all 5 Ready for Testing cards
3. Fix 3 UI issues

### Week 2 — Close Architectural Gaps
1. Add dashboard stats caching
2. Fix deprecated VendorDashboard initialization
3. Move requirement reminders out of wp_options
4. Add refund UI for admin

### Week 3 — Enterprise Polish
1. Add activity/audit log
2. Add bulk admin actions
3. Webhook retry handling
4. REST API versioning documentation

---

## QA Status

- **Ready For Development:** 2 cards (QA Free 400+ tests, QA Pro 15 features) — Assigned to Amit
- **In Testing:** Multiple cards including "Service Order URL renders without scripts"
- **Ready for Testing:** 5 cards — Partially assigned to Nitin
