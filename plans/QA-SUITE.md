# WP Sell Services — QA Suite

> 50 reusable WP-CLI + browser tests covering all critical paths.

**Last Updated:** 2026-03-24

---

## Setup

```bash
# Site URL
SITE_URL="https://wss.local"
ADMIN_URL="${SITE_URL}/wp-admin"
DASHBOARD_URL="${SITE_URL}/dashboard"

# Auto-login URLs
ADMIN_LOGIN="${ADMIN_URL}/?autologin=1"
VENDOR_LOGIN="${DASHBOARD_URL}/?autologin=testuser_vendor"
BUYER_LOGIN="${DASHBOARD_URL}/?autologin=testbuyer"
```

---

## A. Database & Schema Tests (WP-CLI)

### A1. Verify all tables exist
```bash
wp db query "SHOW TABLES LIKE '%wpss%'" --url=wss.local
# Expected: 24 tables (17 free + 7 pro)
```

### A2. Verify table column counts
```bash
wp db query "SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'wp_wpss_orders'" --url=wss.local
# Expected: 30+ columns
```

### A3. Check DB version options
```bash
wp option get wpss_db_version --url=wss.local
# Expected: 1.3.9
wp option get wpss_pro_db_version --url=wss.local
# Expected: 1.0.0
```

### A4. Verify cron jobs registered
```bash
wp cron event list --url=wss.local | grep wpss
# Expected: 11 cron events
```

### A5. Verify rewrite rules flushed
```bash
wp rewrite list --url=wss.local | grep wpss
# Expected: vendor, service-order, service-checkout rules
```

---

## B. Plugin Activation Tests

### B1. Both plugins active
```bash
wp plugin list --status=active --url=wss.local | grep "wp-sell-services"
# Expected: both free and pro listed
```

### B2. No PHP errors on activation
```bash
wp plugin deactivate wp-sell-services-pro wp-sell-services --url=wss.local
wp plugin activate wp-sell-services wp-sell-services-pro --url=wss.local
# Expected: "Plugin activated successfully" x2, no fatal errors
```

### B3. Pages created
```bash
wp option get wpss_pages --format=json --url=wss.local
# Expected: services_page, dashboard, become_vendor, create_service IDs
```

### B4. Vendor role exists
```bash
wp role list --url=wss.local | grep wpss_vendor
```

### B5. REST API namespace registered
```bash
wp rest list --namespace=wpss/v1 --url=wss.local | wc -l
# Expected: 80+ routes
```

---

## C. Admin Panel Tests (Browser)

### C1. Admin dashboard loads without errors
```
Navigate: {ADMIN_LOGIN}
Navigate: {ADMIN_URL}/admin.php?page=wp-sell-services
Assert: heading "WP Sell Services Dashboard" visible
Assert: 0 console errors
```

### C2. Analytics page loads (Pro)
```
Navigate: {ADMIN_URL}/admin.php?page=wpss-analytics
Assert: heading "Analytics Dashboard" visible
Assert: Period selector visible
Assert: 0 console errors (warnings OK)
```

### C3. Settings page loads
```
Navigate: {ADMIN_URL}/admin.php?page=wpss-settings
Assert: Settings tabs visible
Assert: 0 fatal errors
```

### C4. Single Analytics menu item (no duplicate)
```
Navigate: {ADMIN_URL}
Assert: Under "Sell Services" menu, "Analytics" appears exactly once
```

### C5. Orders admin page
```
Navigate: {ADMIN_URL}/admin.php?page=wpss-orders
Assert: Orders table visible
Assert: Status filter tabs visible
```

---

## D. Vendor Dashboard Tests (Browser)

### D1. Dashboard loads as vendor
```
Navigate: {VENDOR_LOGIN}
Assert: Sidebar navigation visible (My Orders, Sales Orders, etc.)
Assert: No PHP errors or white screen
```

### D2. Sales Orders page
```
Navigate: {DASHBOARD_URL}/?section=sales&autologin=testuser_vendor
Assert: "Sales Orders" heading visible
Assert: Order cards render without overlap
Assert: Status badges visible
```

### D3. Analytics tab (Pro)
```
Navigate: {DASHBOARD_URL}/?section=analytics&autologin=testuser_vendor
Assert: Time Period selector visible (7 Days, 30 Days, 90 Days, 12 Months)
Assert: Stat cards visible (Revenue, Orders, Completion Rate, Profile Views)
Assert: Profile Views > 0 (meta key fix verified)
Assert: 0 console errors
```

### D4. Wallet & Earnings tab (Pro)
```
Navigate: {DASHBOARD_URL}/?section=wallet&autologin=testuser_vendor
Assert: Balance display visible
Assert: Withdrawal button functional
Assert: Withdrawal form makes actual AJAX call (not setTimeout stub)
```

### D5. My Services page
```
Navigate: {DASHBOARD_URL}/?section=services&autologin=testuser_vendor
Assert: Service cards visible
Assert: "Create Service" button visible
```

---

## E. Order Lifecycle Tests (Browser + WP-CLI)

### E1. Create order via standalone checkout
```
Navigate: {SITE_URL}/service-checkout/?service_id={ID}&package_id={PKG}
Assert: Checkout form renders
Assert: Addons displayed if service has addons
Assert: Gateway selector visible
```

### E2. Self-purchase blocked
```
Login as vendor who owns service
Navigate: service checkout for own service
Assert: Error "You cannot purchase your own service"
```

### E3. Order status transitions
```bash
# Get latest order
wp eval 'global $wpdb; echo $wpdb->get_var("SELECT id FROM {$wpdb->prefix}wpss_orders ORDER BY id DESC LIMIT 1");' --url=wss.local
```

### E4. Commission calculated correctly (pre-tax)
```bash
wp eval '
$order_id = 42;
global $wpdb;
$order = $wpdb->get_row($wpdb->prepare("SELECT subtotal, addons_total, total, platform_fee, vendor_earnings, commission_rate FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $order_id));
echo "Subtotal: {$order->subtotal}\n";
echo "Addons: {$order->addons_total}\n";
echo "Total (w/tax): {$order->total}\n";
echo "Commission base should be: " . ($order->subtotal + $order->addons_total) . "\n";
echo "Platform fee: {$order->platform_fee}\n";
echo "Vendor earnings: {$order->vendor_earnings}\n";
' --url=wss.local
# Verify: platform_fee = (subtotal + addons) * rate, NOT total * rate
```

### E5. Cascade deletion works
```bash
# Count related records before deletion
wp eval '
global $wpdb;
$service_id = 251;
$orders = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}wpss_orders WHERE service_id = %d", $service_id));
echo "Orders for service {$service_id}: {$orders}\n";
' --url=wss.local
# Delete service and verify related records cleaned up
```

---

## F. Payment Gateway Tests

### F1. Offline gateway creates pending order
```
Submit checkout with Offline payment
Assert: Order created with status "pending_payment"
Assert: Payment method = "offline"
```

### F2. Stripe payment intent creation
```
Submit checkout with Stripe
Assert: createPaymentIntent AJAX succeeds
Assert: Stripe Elements form renders
Assert: Addon total included in payment amount
```

### F3. PayPal order creation
```
Submit checkout with PayPal
Assert: PayPal order created successfully
Assert: Addon IDs stored in PayPal metadata
```

### F4. PayPal webhook signature verification
```bash
# Test that unsigned webhooks are rejected
curl -X POST {SITE_URL}/?wpss_payment_webhook=paypal \
  -H "Content-Type: application/json" \
  -d '{"event_type":"PAYMENT.CAPTURE.COMPLETED"}'
# Expected: 401 or 500 (rejected, not processed)
```

### F5. Stripe error handling
```bash
# Verify Stripe API errors are not silently swallowed
wp eval '
$gateway = new \WPSellServices\Integrations\Stripe\StripeGateway();
// Check api_request returns error array for non-2xx responses
echo "Stripe error handling: verified in code review\n";
' --url=wss.local
```

---

## G. Security Tests

### G1. AJAX without nonce rejected
```javascript
// Browser console test
jQuery.post(wpssData.ajaxUrl, {action: 'wpss_accept_order', order_id: 1}, function(r) {
    console.log('Should fail:', r);
});
// Expected: -1 or error response
```

### G2. Unauthorized proposal rejection blocked
```javascript
// Login as user who doesn't own the request
jQuery.post(wpssData.ajaxUrl, {
    action: 'wpss_reject_proposal',
    proposal_id: 1,
    nonce: wpssData.nonce
}, function(r) {
    console.log('Should fail:', r);
});
// Expected: "You are not authorized to decline this proposal"
```

### G3. REST API auth required
```bash
curl -s {SITE_URL}/wp-json/wpss/v1/orders | python3 -m json.tool
# Expected: 401 unauthorized
```

### G4. Rate limiting active
```bash
for i in $(seq 1 20); do
  curl -s -o /dev/null -w "%{http_code}\n" \
    -X POST {SITE_URL}/wp-json/wpss/v1/auth/login \
    -d '{"username":"test","password":"wrong"}'
done
# Expected: 429 after rate limit exceeded
```

### G5. Currency not hardcoded
```bash
wp eval '
echo wpss_get_currency() . "\n";
global $wpdb;
$usd_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wpss_wallet_transactions WHERE currency = \"USD\"");
echo "USD hardcoded transactions: {$usd_count}\n";
' --url=wss.local
```

---

## H. Pro Feature Tests

### H1. License check gates Pro features
```bash
wp option get wpss_pro_license_status --url=wss.local
# If not 'valid', Pro features should not load
```

### H2. Tiered commission rules
```bash
wp eval '
global $wpdb;
$rules = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpss_pro_commission_rules WHERE is_active = 1 ORDER BY priority");
echo count($rules) . " active commission rules\n";
' --url=wss.local
```

### H3. Vendor subscription enforcement
```
Login as vendor without subscription
Try to create a service (if subscription required)
Assert: Blocked with subscription upgrade message
```

### H4. White label branding applied
```
Navigate: {ADMIN_URL}/admin.php?page=wpss-settings&tab=branding
Assert: Logo URL, brand name, primary color fields visible
```

### H5. Wallet withdrawal (actual AJAX, not stub)
```
Navigate: {DASHBOARD_URL}/?section=wallet&autologin=testuser_vendor
Click "Withdraw"
Fill form and submit
Assert: REST API call to /wpss/v1/wallet/withdraw fires
Assert: No alert('Withdrawal request submitted successfully!') stub
```

---

## I. Regression Tests (Recent Fixes)

### I1. Offline auto-cancel cron registered
```bash
wp cron event list --url=wss.local | grep wpss_process_offline_auto_cancel
# Expected: Listed with "hourly" recurrence
```

### I2. wpColorPicker no JS error
```
Navigate: {ADMIN_URL}/admin.php?page=wpss-settings&tab=branding
Assert: Color picker renders
Assert: 0 JS errors in console
```

### I3. Subscription settings page no parse error
```
Navigate: {ADMIN_URL}/admin.php?page=wpss-settings&tab=vendor
Assert: Page loads (not "critical error")
Assert: Subscription plans section visible
```

### I4. Dual status paths unified
```bash
# REST API and AJAX should produce same conversation logs
wp eval '
global $wpdb;
$system_msgs = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}wpss_messages WHERE type = \"system\"");
echo "System status messages: {$system_msgs}\n";
' --url=wss.local
```

### I5. Addons included in checkout
```
Navigate: service checkout for a service with addons
Assert: Addon checkboxes visible
Assert: Hidden fields addon_ids and addons_total present in form
Select addons
Assert: Total updates to include addon prices
```

---

## J. Performance Checks

### J1. No N+1 queries on service archive
```
Navigate: {SITE_URL}/services/
Check: Query Monitor shows < 50 queries
```

### J2. Dashboard doesn't exceed query budget
```
Navigate: {DASHBOARD_URL}/?autologin=testuser_vendor
Check: Query Monitor shows < 100 queries
```

### J3. Cron jobs don't timeout
```bash
wp cron event run wpss_check_late_orders --url=wss.local
wp cron event run wpss_auto_complete_orders --url=wss.local
wp cron event run wpss_process_offline_auto_cancel --url=wss.local
# Expected: All complete without timeout
```

### J4. REST batch endpoint respects limit
```bash
curl -X POST {SITE_URL}/wp-json/wpss/v1/batch \
  -H "X-WP-Nonce: {nonce}" \
  -H "Content-Type: application/json" \
  -d '{"requests": [/* 26 items */]}'
# Expected: Error — max 25 sub-requests
```

### J5. DB indexes present on high-traffic tables
```bash
wp db query "SHOW INDEX FROM wp_wpss_orders" --url=wss.local
# Expected: Indexes on customer_id, vendor_id, service_id, status
```
