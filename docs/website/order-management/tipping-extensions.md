# Tipping & Deadline Extensions

Learn how buyers can tip vendors for excellent work and how vendors can request additional time when needed.

## Tipping

### What Is Tipping?

Tipping allows buyers to send additional payment to vendors after order completion to show appreciation for outstanding service.

**Key Features:**
- 100% of tip goes to vendor (no platform commission)
- Available only after order completion
- One tip per order maximum
- Minimum amount: > 0 (no hard $1 minimum enforced in code)
- No maximum limit
- Instant credit to vendor wallet

### Tipping Requirements

**When Buyers Can Tip:**

Checked in `TippingService::tip()`:
1. Order status must be `completed`
2. Order has not been tipped already
3. Amount must be > 0
4. Buyer must own the order

**Code Validation:**
```php
// Amount validation (TippingService.php line 51)
if ( $amount <= 0 ) {
    return error; // Only checks > 0, no $1 minimum
}

// Order status check (line 70)
if ( ServiceOrder::STATUS_COMPLETED !== $order->status ) {
    return error; // Only completed orders
}

// Already tipped check (line 88)
if ( $this->has_tipped( $order_id, $customer_id ) ) {
    return error; // One tip per order
}
```

**No 90-Day Window Enforcement:**
The code does NOT enforce a 90-day tip window. Tips are allowed indefinitely after completion (unless admin adds custom restriction).

### How Tipping Works

**Process:**
1. Buyer opens completed order
2. Clicks "Send Tip" button
3. Enters tip amount (any amount > 0)
4. Optional: Adds message (up to 500 characters)
5. Confirms payment
6. Tip instantly credited to vendor wallet

### Tip Storage

**Database:** `wp_wpss_wallet_transactions` table

**Record Structure:**
```php
[
    'user_id'        => $vendor_id,
    'type'           => 'tip',
    'amount'         => $tip_amount,
    'balance_after'  => $new_balance,
    'currency'       => $order_currency,
    'description'    => 'Tip received for order #1234',
    'reference_type' => 'order',
    'reference_id'   => $order_id,
    'status'         => 'completed',
    'created_at'     => current_time(),
]
```

### Checking If Already Tipped

```php
$tipping_service = new TippingService();
$has_tipped = $tipping_service->has_tipped( $order_id, $customer_id );
```

**Query Logic:**
- Searches for `type = 'tip'`
- Matches `reference_type = 'order'` and `reference_id = $order_id`
- Matches `user_id = $vendor_id` (not customer!)
- Returns boolean

### Vendor Receiving Tips

**Wallet Transaction Created:**
```php
do_action( 'wpss_tip_sent', $tip_id, $order_id, $vendor_id, $customer_id, $amount, $message );
```

**Balance Update:**
- Tip added to vendor's wallet balance
- Available immediately for withdrawal
- No commission deducted (vendor receives 100%)
- Processing fee paid by buyer

### Tip Retrieval Methods

**Get Tip for Specific Order:**
```php
$tip = $tipping_service->get_order_tip( $order_id );
// Returns: tip transaction object or null
```

**Get All Tips for Vendor:**
```php
$tips = $tipping_service->get_vendor_tips( $vendor_id, [
    'limit'  => 20,
    'offset' => 0,
] );
// Returns: array of tip transaction objects
```

**Get Total Tips Received:**
```php
$total = $tipping_service->get_vendor_tips_total( $vendor_id );
// Returns: float (sum of all completed tips)
```

### Transaction Locking

Tips use database transactions to prevent race conditions:

```php
// Line 99: START TRANSACTION
$wpdb->query( 'START TRANSACTION' );

// Line 103-112: Lock vendor's wallet row
$current_balance = $wpdb->get_var(
    "SELECT balance_after FROM {$table}
    WHERE user_id = %d
    ORDER BY created_at DESC, id DESC
    LIMIT 1
    FOR UPDATE"
);

// Line 129-144: Insert tip transaction
$wpdb->insert( $table, $data );

// Line 155: COMMIT
$wpdb->query( 'COMMIT' );
```

## Deadline Extensions

### What Are Extensions?

Deadline extensions allow vendors to request additional time when unforeseen circumstances arise. Buyers must approve extensions.

**Key Features:**
- Vendor requests extra days with reason
- Buyer approves or denies
- Auto-deny after 48 hours if no response
- Multiple extensions possible
- Tracked in vendor statistics

### Extension Requirements

**When Extensions Can Be Requested:**

Checked in `ExtensionRequestService::create()`:

**Allowed Statuses (line 134-138):**
```php
$allowed_statuses = [
    ServiceOrder::STATUS_IN_PROGRESS,
    ServiceOrder::STATUS_LATE,              // Late orders CAN request extension
    ServiceOrder::STATUS_REVISION_REQUESTED,
];
```

**Important:** Extensions ARE allowed on late orders (contradicts old docs).

**Other Requirements:**
- No pending extension request exists
- Reason must be ≥ 10 characters
- Extra days: 1 to max (default 14)

### Maximum Extension Days

**Default:** 14 days (line 157)

```php
$max_extension_days = (int) get_option( 'wpss_max_extension_days', 14 );
```

**NOT 30 days** as stated in old documentation.

**Validation:**
```php
if ( $extra_days < 1 || $extra_days > $max_extension_days ) {
    return error;
}
```

### Extension Statuses

**Constants:**
- `STATUS_PENDING` - Awaiting buyer response
- `STATUS_APPROVED` - Buyer approved
- `STATUS_REJECTED` - Buyer denied

**Database:** `wp_wpss_extension_requests` table

### Creating Extension Request

```php
$extension_service = new ExtensionRequestService();

$result = $extension_service->create(
    $order_id,
    $requested_by,   // Vendor user ID
    $extra_days,     // 1-14 (or configured max)
    $reason          // Min 10 characters
);
```

**What Happens:**
1. Validates order status (in_progress, late, or revision_requested)
2. Checks no pending request exists
3. Validates extra days (1 to max)
4. Validates reason (min 10 chars)
5. Inserts request into database
6. Notifies buyer
7. Logs in conversation

**Returns:**
```php
[
    'success'    => true,
    'message'    => 'Extension request submitted successfully.',
    'request_id' => 123,
]
```

### Approving Extension

```php
$result = $extension_service->approve(
    $request_id,
    $responded_by,      // Buyer user ID
    $response_message   // Optional
);
```

**What Happens (lines 267-327):**
1. Validates request exists and is pending
2. Updates request status to `approved`
3. Extends order deadline via `OrderService::extend_deadline()`
4. **If order is late:** Changes status back to `in_progress` (line 321-326)
5. Notifies vendor
6. Logs in conversation

**Late Order Handling:**
```php
// Line 321-326
if ( ServiceOrder::STATUS_LATE === $order->status ) {
    $this->order_service->update_status(
        $request['order_id'],
        ServiceOrder::STATUS_IN_PROGRESS,
        __( 'Deadline extended', 'wp-sell-services' )
    );
}
```

### Rejecting Extension

```php
$result = $extension_service->reject(
    $request_id,
    $responded_by,
    $response_message
);
```

**What Happens:**
1. Validates request is pending
2. Updates status to `rejected`
3. Original deadline remains
4. Notifies vendor
5. Logs in conversation

### Retrieving Extensions

**Get Extension by ID:**
```php
$extension = $extension_service->get( $request_id );
```

**Get All Extensions for Order:**
```php
$extensions = $extension_service->get_by_order( $order_id );
// Returns: array of extension request objects, newest first
```

**Get Pending Extension:**
```php
$pending = $extension_service->get_pending( $order_id );
// Returns: most recent pending extension or null
```

### Extension Status Labels

```php
$statuses = ExtensionRequestService::get_statuses();

// Returns:
[
    'pending'  => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
]
```

## Action Hooks

### Tipping Hooks

```php
// When tip is sent
do_action( 'wpss_tip_sent', $tip_id, $order_id, $vendor_id, $customer_id, $amount, $message );
```

### Extension Hooks

```php
// Extension request created
do_action( 'wpss_extension_request_created', $request_id, $order_id, [
    'requested_by' => $vendor_id,
    'extra_days'   => $days,
    'reason'       => $reason,
] );

// Extension approved
do_action( 'wpss_extension_request_approved', $request_id, $request );

// Extension rejected
do_action( 'wpss_extension_request_rejected', $request_id, $request );
```

## Key Corrections from Old Docs

### Tipping

❌ **OLD (INCORRECT):** "Minimum $1 tip enforced"
✅ **ACTUAL:** Only checks amount > 0 (line 51)

❌ **OLD (INCORRECT):** "Tips allowed within 90 days of completion"
✅ **ACTUAL:** No time window enforced in code

### Extensions

❌ **OLD (INCORRECT):** "Late orders cannot request extensions"
✅ **ACTUAL:** Late orders CAN request extensions (line 136)

❌ **OLD (INCORRECT):** "Max extension: 30 days"
✅ **ACTUAL:** Default max: 14 days (line 157)

## REST API Endpoints

### Tipping

**Base:** `/wp-json/wpss/v1/tips`

- `POST /orders/{order_id}/tip` - Send tip
- `GET /vendors/{vendor_id}/tips` - Get vendor's tips
- `GET /orders/{order_id}/tip` - Check if order has tip

### Extensions

**Base:** `/wp-json/wpss/v1/extensions`

- `POST /orders/{order_id}/extensions` - Create extension request
- `GET /orders/{order_id}/extensions` - List order extensions
- `POST /extensions/{request_id}/approve` - Approve extension
- `POST /extensions/{request_id}/reject` - Reject extension

## Best Practices

### For Vendors (Extensions)

✅ **Request Early:**
- Don't wait until deadline day
- Give buyer time to respond
- Explain reason clearly (min 10 chars)

✅ **Valid Reasons:**
- Technical issues beyond control
- Scope changes
- Buyer delays in providing assets
- Emergencies

❌ **Invalid Reasons:**
- Poor time management
- Too many orders
- Forgot about order

### For Buyers (Tips)

✅ **Tip When:**
- Quality exceeded expectations
- Delivered early
- Outstanding communication
- Went above and beyond

**Typical Tip Amounts:**
- Good work: 5-10% of order
- Great work: 10-15% of order
- Exceptional: 15-20%+ of order

## Troubleshooting

### Cannot Tip

**Check:**
- Order status is `completed`
- You are the buyer (not vendor)
- Order has not been tipped already
- Amount is > 0

### Cannot Request Extension

**Check:**
- Order status: `in_progress`, `late`, or `revision_requested`
- No pending extension exists
- Reason is ≥ 10 characters
- Extra days between 1 and max (14 default)

### Extension Not Working on Late Order

**This should work!** The code explicitly allows extensions on late orders (line 136). If it's not working, check for bugs or custom modifications.

## Related Documentation

- [Order Workflow](order-lifecycle.md) - Order statuses and lifecycle
- [Deliveries & Revisions](deliveries-revisions.md) - Delivery workflow
- [Earnings Dashboard](../earnings-wallet/earnings-dashboard.md) - Tip tracking
- [Order Settings](order-settings.md) - Configure extension limits

Tipping rewards great vendors, and extensions provide flexibility when needed!
