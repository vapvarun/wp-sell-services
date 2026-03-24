# WP Sell Services - Continuous Audit Instructions

**Purpose:** Systematic instructions for auditing both Free and Pro plugins until zero logic/code flow issues remain.
**Scope:** 156 PHP files (free) + 102 PHP files (pro) + 61 templates
**Reference Docs:** `QA-USER-JOURNEYS.md`, `QA-PRO-FEATURES.md`, `BLUEPRINT.md`, `CLAUDE.md`

---

## How to Use This Document

Run each audit phase as a separate agent. After each phase, fix all issues found. Re-run the same phase until it returns zero issues, then move to the next phase. The goal is zero issues across ALL phases.

**Loop:** `Audit Phase N` -> `Fix Issues` -> `Re-audit Phase N` -> `0 issues?` -> `Next Phase`

---

## Phase 1: Hook Wiring Integrity

**Goal:** Every `do_action()` has a matching `add_action()`, and vice versa. No double-firing. Consistent signatures.

### Checks

1. **Status hook chain consistency:**
   - Every location that fires `wpss_order_status_changed` MUST also fire `wpss_order_status_{$status}`
   - Search: `do_action.*wpss_order_status_changed` — verify each has the dynamic counterpart
   - Files to check: `OrderService.php`, `ServiceOrder.php`, `Admin.php`, `ManualOrderPage.php`

2. **No double-fired custom hooks:**
   - `wpss_order_cancelled` — should ONLY fire from `OrderWorkflowManager::handle_order_cancelled()`
   - `wpss_order_completed` — should ONLY fire from `OrderWorkflowManager::handle_order_completed()`
   - Search: `do_action.*wpss_order_cancelled`, `do_action.*wpss_order_completed`
   - Any found outside OWM is a bug (the status hook chain fires them)

3. **Orphaned hooks (do_action with no add_action):**
   - Grep all `do_action( 'wpss_` in free plugin
   - For each, verify at least one `add_action( 'wpss_same_hook'` exists
   - Check both free AND pro plugins for listeners

4. **Orphaned listeners (add_action with no do_action):**
   - Grep all `add_action( 'wpss_` in free plugin
   - For each, verify the hook is actually fired somewhere
   - Pro plugin may fire hooks the free plugin listens to — check both

5. **Filter consistency:**
   - Grep all `apply_filters( 'wpss_` — verify each has at least one use case
   - Verify return types match expectations (e.g., array filter returns array)

### Files to Audit
```
src/Services/OrderWorkflowManager.php (central hook hub)
src/Services/OrderService.php
src/Services/DisputeWorkflowManager.php
src/Models/ServiceOrder.php
src/API/OrdersController.php
src/Admin/Admin.php
src/Admin/Pages/ManualOrderPage.php
src/Core/Plugin.php
```

---

## Phase 2: Status Machine Integrity

**Goal:** Every status transition is valid, every status has labels/colors, and the transition map is complete.

### Checks

1. **Transition map completeness (OrderService::can_transition):**
   - Every status constant in `ServiceOrder` has an entry in the transition map
   - Every target status in the map is a valid constant
   - No unreachable statuses (status that nothing transitions TO)

2. **Status constants vs actual usage:**
   - Grep for hardcoded status strings ('in_progress', 'completed', etc.) in ALL files
   - Each should use `ServiceOrder::STATUS_*` constants, not raw strings
   - Templates and admin pages are the usual offenders

3. **Status labels completeness:**
   - `ServiceOrder::get_statuses()` must include ALL status constants
   - Admin pages that render status dropdowns should use `ServiceOrder::get_statuses()`
   - Check: `Admin.php`, `VendorsPage.php`, `OrdersListTable.php`, `ManualOrderPage.php`

4. **Order lifecycle flow validation (per QA-USER-JOURNEYS.md):**
   ```
   pending_payment -> pending_requirements -> in_progress -> pending_approval -> completed
   Branches: revision_requested, disputed, late, on_hold, cancelled, cancellation_requested
   ```
   - Verify each transition exists in the map
   - Verify the REST API `get_available_actions()` returns correct actions per status per role

### Files to Audit
```
src/Models/ServiceOrder.php (constants + transition map)
src/Services/OrderService.php (can_transition, update_status)
src/API/OrdersController.php (perform_action, get_available_actions)
src/Admin/Admin.php (status dropdowns)
src/Admin/Pages/ManualOrderPage.php (initial statuses)
src/Admin/Pages/VendorsPage.php (status filters)
templates/order/order-view.php (status display)
```

---

## Phase 3: Service Layer Method Verification

**Goal:** Every public service method is called somewhere. Every method that templates/controllers call actually exists.

### Checks

1. **For each Service class, list all public methods and verify each is called:**
   - `OrderService` — 15+ methods
   - `DeliveryService` — 10+ methods
   - `DisputeService` — 10+ methods
   - `EarningsService` — 12+ methods
   - `VendorService` — 10+ methods
   - `CommissionService` — 5+ methods
   - `ReviewService` — 8+ methods
   - `NotificationService` — 6+ methods
   - `ConversationService` — 8+ methods
   - `BuyerRequestService` — 10+ methods
   - Dead methods = code to remove

2. **Template service calls verification:**
   - Templates should NOT instantiate services directly (`new FooService()`)
   - Check all templates for `new \WPSellServices\Services\*`
   - These should be passed from the controller/page class

3. **REST API controller method existence:**
   - Each route callback in API controllers must reference an existing method
   - Permission callbacks must exist
   - Sanitization callbacks in route args must be valid PHP callables

4. **Model method verification:**
   - `ServiceOrder::update()` — verify it fires hooks correctly
   - `ServiceOrder::from_db()` — verify all DB columns are mapped
   - `Dispute::from_db()` — verify all DB columns are mapped (meta, evidence, etc.)
   - Every model's `from_db()` must map EVERY column from its DB table schema

### Files to Audit
```
src/Services/*.php (all 27 service classes)
src/API/*.php (all 22 controllers)
src/Models/*.php (all models)
templates/**/*.php (all 61 templates)
```

---

## Phase 4: Cron & Background Process Integrity

**Goal:** Every scheduled cron has a handler. Every handler is scheduled. No schedule conflicts.

### Checks

1. **Cron scheduling single source of truth:**
   - `OrderWorkflowManager::schedule_cron_events()` — THE source for order/vendor crons
   - `DisputeWorkflowManager::init()` — dispute crons (`wpss_cron_daily`)
   - `EarningsService::schedule_auto_withdrawal_cron()` — withdrawal crons
   - `Activator.php` should NOT duplicate any of the above (only EarningsService)

2. **Every scheduled event has a registered handler:**
   - List all `wp_schedule_event('wpss_*')` calls
   - For each, verify `add_action('wpss_same_hook', ...)` exists
   - Verify the handler method exists and has correct signature

3. **Cron handler safety:**
   - Each handler should have a null check after `$this->order_service->get()`
   - Each handler should handle empty query results gracefully
   - Each handler should use `$wpdb->prepare()` for all queries

4. **Deactivation cleanup:**
   - `Deactivator.php` or `OrderWorkflowManager::clear_cron_events()` must unschedule ALL events
   - Verify every scheduled event is cleared on deactivation

### Files to Audit
```
src/Services/OrderWorkflowManager.php (main cron hub)
src/Services/DisputeWorkflowManager.php (dispute crons)
src/Services/EarningsService.php (withdrawal crons)
src/Core/Activator.php (activation scheduling)
src/Core/Deactivator.php (cleanup)
```

---

## Phase 5: REST API Completeness

**Goal:** Every feature has REST endpoints. Every endpoint has proper validation, permissions, and consistent responses.

### Checks

1. **Route registration:**
   - Every controller registered in `API.php` must have `register_routes()`
   - Every route must have `permission_callback`
   - No route should use `__return_true` as permission callback

2. **Permission consistency:**
   - Vendor-only endpoints check `wpss_vendor` capability
   - Admin-only endpoints check `wpss_manage_settings` or similar
   - Owner-check endpoints verify user owns the resource
   - Use `check_permissions()`, `check_admin_permissions()` from RestController base

3. **Response format consistency:**
   - List endpoints use `paginated_response()`
   - Single-item endpoints return the item directly
   - Error responses use `WP_Error` with proper codes
   - All monetary values formatted consistently

4. **Endpoint coverage per QA docs:**
   - Orders: CRUD + status transitions + requirements + cancel + dispute
   - Services: CRUD + search + featured + packages + addons
   - Vendors: list + profile + stats + portfolio
   - Disputes: create + respond + resolve + evidence
   - Earnings: summary + withdrawals + history
   - All other controllers in CLAUDE.md table

5. **Request validation:**
   - Required params validated (not empty, correct type)
   - IDs validated as positive integers
   - Status values validated against allowed list
   - File uploads validated by type and size

### Files to Audit
```
src/API/*.php (all 22 controllers)
src/API/API.php (controller registration)
src/API/RestController.php (base class)
```

---

## Phase 6: Template Data Flow

**Goal:** Templates receive correct data, use proper escaping, and handle edge cases.

### Checks

1. **Variable availability:**
   - Every variable used in a template must be set before the template is included
   - Check `$order`, `$dispute`, `$vendor`, `$service` etc. — are they null-checked?
   - Templates that use `$order->vendor_id` must handle deleted users

2. **Output escaping (OWASP):**
   - All output uses `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
   - No raw `echo $variable` without escaping
   - `wpss_format_price()` output goes through `esc_html()` or `wp_kses_post()`
   - User-generated content (messages, descriptions) uses `wp_kses_post()`

3. **Model property accuracy:**
   - Templates reference model properties that actually exist
   - No `$model->meta['something']` unless meta is a mapped property
   - No `$model->old_property_name` from refactored models
   - Cross-reference every `$dispute->`, `$order->`, `$vendor->` with model class

4. **Form action correctness:**
   - AJAX forms have `action` hidden input
   - REST-based forms use correct endpoint URLs
   - Nonce verification present for all form submissions
   - File upload forms have `enctype="multipart/form-data"`

5. **Conditional rendering:**
   - Status-dependent UI shows correct buttons per role per status
   - Vendor-only sections check `wpss_is_vendor()` or `$is_vendor`
   - Admin-only sections check `current_user_can('wpss_manage_settings')`

### Files to Audit
```
templates/order/order-view.php (most complex)
templates/order/order-requirements.php
templates/disputes/dispute-view.php
templates/dashboard/sections/*.php (all sections)
templates/vendor/*.php
templates/emails/*.php (all email templates)
```

---

## Phase 7: Pro Plugin Integration

**Goal:** Pro extends Free cleanly via hooks. No feature duplication. Graceful degradation when Pro is deactivated.

### Checks

1. **Hook-based extension:**
   - Pro registers features via `wpss_loaded` action
   - Pro adapters register via `wpss_ecommerce_adapters` filter
   - Pro gateways register via `wpss_payment_gateways` filter
   - No Pro code should modify Free plugin files directly

2. **Graceful degradation:**
   - Deactivating Pro should not cause PHP errors in Free
   - Free features must work independently without Pro
   - Settings saved by Pro should not break Free when Pro is removed
   - Pro admin tabs should disappear cleanly

3. **Status hook chain in Pro order providers:**
   - WCOrderProvider, FluentCartOrderProvider, SureCartOrderProvider
   - When these change order status, they must fire both hooks:
     - `wpss_order_status_changed`
     - `wpss_order_status_{$new_status}`
   - Verify correct argument signatures match Free plugin expectations

4. **No duplicate functionality:**
   - Pro should not redefine service classes that exist in Free
   - Pro should not register duplicate REST endpoints
   - Pro should not duplicate admin pages/tabs (extend existing ones)

### Files to Audit
```
# Pro plugin
wp-sell-services-pro/src/Pro.php (main bootstrap)
wp-sell-services-pro/src/Integrations/WooCommerce/*.php
wp-sell-services-pro/src/Integrations/FluentCart/*.php
wp-sell-services-pro/src/Integrations/SureCart/*.php
wp-sell-services-pro/src/Integrations/EDD/*.php

# Free plugin hooks
src/Core/Plugin.php (wpss_loaded)
src/Integrations/ (adapter interfaces)
```

---

## Phase 8: Email & Notification Completeness

**Goal:** Every status change sends the right emails/notifications to the right people.

### Checks

1. **Email triggers per status:**
   | Status Change | Email Type | Recipient |
   |--------------|-----------|-----------|
   | -> pending_requirements | Order confirmed | Buyer + Vendor |
   | -> in_progress | Requirements submitted | Vendor |
   | -> pending_approval | Delivery submitted | Buyer |
   | -> completed | Order completed | Buyer + Vendor |
   | -> cancelled | Order cancelled | Buyer + Vendor |
   | -> revision_requested | Revision requested | Vendor |
   | -> disputed | Dispute opened | Buyer + Vendor |
   | -> cancellation_requested | Cancellation requested | Vendor |

2. **Email template pairs:**
   - Every HTML template in `templates/emails/` has a matching `plain/` version
   - Template variables match between HTML and plain versions

3. **Notification triggers:**
   - `OrderWorkflowManager` creates notifications for every status change
   - `DisputeWorkflowManager` creates notifications for dispute events
   - In-app notifications match email notifications

4. **EmailService completeness:**
   - Every `TYPE_*` constant has a `send_*()` method
   - Every `send_*()` method is called from somewhere (not dead code)
   - `handle_status_change()` has a case for every status

### Files to Audit
```
src/Services/EmailService.php
src/Services/NotificationService.php
src/Services/OrderWorkflowManager.php
src/Services/DisputeWorkflowManager.php
templates/emails/*.php
templates/emails/plain/*.php
```

---

## Phase 9: Database Schema Consistency

**Goal:** Models match DB schema. Repositories query correct columns. No orphaned tables.

### Checks

1. **Model-to-schema mapping:**
   - For each model's `from_db()`, verify every DB column is mapped to a property
   - For each DB table, verify every column appears in the model
   - Schema: `src/Database/SchemaManager.php`
   - Models: `src/Models/*.php`

2. **Repository query correctness:**
   - SELECT queries reference correct column names
   - INSERT/UPDATE queries include all required columns
   - Foreign keys reference existing tables
   - No hardcoded table names (use `$wpdb->prefix . 'wpss_*'`)

3. **Migration safety:**
   - `MigrationManager` handles upgrades between versions
   - Schema changes are additive (no dropping columns without migration)
   - Version checks prevent re-running completed migrations

### Files to Audit
```
src/Database/SchemaManager.php
src/Database/MigrationManager.php
src/Database/Repositories/*.php
src/Models/*.php
```

---

## Phase 10: End-to-End Flow Verification

**Goal:** Trace complete user journeys through code and verify every step works.

### Flow 1: Buyer Purchases Service (Standalone Mode)
```
1. Browse services -> ServicesController::index() -> templates/archive-service.php
2. View service -> ServicesController::show() -> templates/single-service.php
3. Add to cart -> CartController::add() -> stores in session/DB
4. Checkout -> StandaloneCheckoutProvider -> OfflineGateway or Stripe/PayPal
5. Payment complete -> wpss_order_payment_complete -> OWM::handle_payment_complete()
6. Requirements form -> order-requirements.php -> RequirementsService::submit()
7. Vendor starts work -> OrdersController::perform_action('start')
8. Delivery -> OrdersController::perform_action('deliver') -> DeliveryService
9. Buyer approves -> OrdersController::perform_action('complete')
10. Commission recorded -> CommissionService::record()
11. Review left -> ReviewService::create()
```

### Flow 2: Buyer Cancels Order
```
1. Pre-work cancel -> OrdersController('cancel') -> immediate cancel
2. In-progress cancel -> OrderService::request_cancellation()
3. Vendor accepts -> OrdersController('accept-cancellation')
4. OR vendor disputes -> DisputeService::open()
5. OR 48h timeout -> OWM::process_cancellation_timeouts()
```

### Flow 3: Dispute Resolution
```
1. Buyer opens dispute -> DisputeService::open()
2. Evidence submitted -> DisputeService::add_evidence()
3. Admin mediates -> DisputeService::respond()
4. Resolution -> DisputeService::resolve()
5. Refund if applicable -> wpss_dispute_resolved hook
```

### Flow 4: Vendor Earnings & Withdrawal
```
1. Order completes -> CommissionService::record()
2. Clearance period -> EarningsService checks clearance_days
3. Available balance -> EarningsService::get_summary()
4. Request withdrawal -> EarningsService::request_withdrawal()
5. Admin approves -> EarningsService::approve_withdrawal()
6. Payout processed -> gateway-specific logic
```

For each flow: trace the actual code path file-by-file and verify each step calls the correct service method, fires the correct hooks, and produces the expected output.

---

## Audit Result Tracking

After each phase, record results:

```
## Phase N: [Name] - Run [X]
Date: YYYY-MM-DD
Issues Found: N
Issues Fixed: N
Remaining: N
Status: PASS / FAIL

### Issues
1. [severity] Description - File:Line - FIXED/PENDING
2. ...
```

**Completion criteria:** ALL phases return 0 issues in their latest run.
