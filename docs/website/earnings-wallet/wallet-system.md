# Earnings Balance System

The plugin tracks vendor earnings using a built-in balance calculation system based on completed orders and withdrawals.

## Overview

WP Sell Services uses a simple, efficient earnings tracking system. There is no separate "wallet" plugin required - earnings are calculated on-the-fly from order and withdrawal data.

### How Earnings Are Tracked

Vendor balance is calculated from two database tables:

1. **`wpss_orders`**: Stores completed orders with vendor_earnings
2. **`wpss_withdrawals`**: Stores withdrawal requests and completed payouts

The available balance is computed as:

```
Total Earned - Withdrawn - Pending Withdrawal = Available Balance
```

## Balance Calculations

### Total Earned

Sum of `vendor_earnings` from all completed orders.

**SQL Query:**
```sql
SELECT SUM(vendor_earnings)
FROM wpss_orders
WHERE vendor_id = {vendor_id}
AND status = 'completed'
```

### Withdrawn

Sum of all completed withdrawals.

**SQL Query:**
```sql
SELECT SUM(amount)
FROM wpss_withdrawals
WHERE vendor_id = {vendor_id}
AND status = 'completed'
```

### Pending Withdrawal

Sum of pending and approved withdrawal requests.

**SQL Query:**
```sql
SELECT SUM(amount)
FROM wpss_withdrawals
WHERE vendor_id = {vendor_id}
AND status IN ('pending', 'approved')
```

### Available Balance

```php
$available = $total_earned - $withdrawn - $pending_withdrawal;
```

This is the amount vendor can withdraw right now.

### Pending Clearance

Sum of order totals from in-progress orders.

**SQL Query:**
```sql
SELECT SUM(total)
FROM wpss_orders
WHERE vendor_id = {vendor_id}
AND status IN ('in_progress', 'pending_approval', 'revision_requested')
```

These earnings are not yet available for withdrawal until orders complete.

## Earnings Service

The `EarningsService` class handles all balance calculations.

### Get Vendor Summary

```php
$earnings_service = new \WPSellServices\Services\EarningsService();
$summary = $earnings_service->get_summary( $vendor_id );

// Returns:
array(
    'total_earned' => 12450.00,
    'available_balance' => 2670.00,
    'pending_clearance' => 1280.00,
    'withdrawn' => 8500.00,
    'pending_withdrawal' => 0.00,
    'completed_orders' => 124
)
```

### Get Earnings History

```php
$history = $earnings_service->get_history( $vendor_id, array(
    'limit' => 20,
    'offset' => 0,
    'start_date' => '2026-01-01',
    'end_date' => '2026-01-31',
    'status' => 'completed'
) );

// Returns array of earnings records
```

### Get Earnings by Period

```php
$monthly = $earnings_service->get_by_period( $vendor_id, 'month', 12 );

// Returns earnings grouped by month for last 12 months
```

## Database Tables

### wpss_orders

Key fields for earnings:

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Order ID |
| `vendor_id` | int | Vendor user ID |
| `total` | decimal | Order total paid by buyer |
| `commission_rate` | decimal | Commission percentage applied |
| `platform_fee` | decimal | Commission amount deducted |
| `vendor_earnings` | decimal | Amount credited to vendor |
| `status` | varchar | Order status |
| `completed_at` | datetime | Order completion date |

### wpss_withdrawals

Key fields for payouts:

| Field | Type | Description |
|-------|------|-------------|
| `id` | int | Withdrawal ID |
| `vendor_id` | int | Vendor user ID |
| `amount` | decimal | Withdrawal amount |
| `method` | varchar | Payment method (paypal, bank_transfer) |
| `details` | text | JSON payment details |
| `status` | varchar | pending, approved, completed, rejected |
| `is_auto` | tinyint | 1 if auto-withdrawal |
| `created_at` | datetime | Request date |
| `processed_at` | datetime | Processing date |

## No Separate Wallet Plugin Required

Unlike some marketplace plugins, WP Sell Services does **not** require:

- TeraWallet
- WooWallet
- WooCommerce Wallet
- MyCred
- Any other wallet plugin

The built-in system is sufficient for most marketplaces.

### Benefits of Built-in System

**Simpler:**
- No additional plugin dependencies
- Fewer compatibility issues
- Easier to maintain

**Faster:**
- Direct database queries
- No wallet plugin overhead
- Optimized for marketplace needs

**More Reliable:**
- Fewer moving parts
- No third-party plugin conflicts
- Full control over calculations

## Transaction Integrity

The system uses database transactions to prevent race conditions.

### Withdrawal Request Example

```php
$wpdb->query( 'START TRANSACTION' );

// Lock vendor's withdrawals
$wpdb->get_var(
    "SELECT SUM(amount) FROM {$table}
    WHERE vendor_id = {$vendor_id}
    AND status = 'pending'
    FOR UPDATE"
);

// Check available balance
$summary = $earnings_service->get_summary( $vendor_id );

if ( $amount > $summary['available_balance'] ) {
    $wpdb->query( 'ROLLBACK' );
    return error;
}

// Create withdrawal
$wpdb->insert( $table, $data );

$wpdb->query( 'COMMIT' );
```

This prevents double-withdrawal scenarios.

## Clearance Period

Earnings from completed orders don't become immediately available. There's a clearance period.

### Default Clearance

**Clearance Days**: 14 (configurable)

Orders completed today become available for withdrawal 14 days later.

### Purpose

- Buyer can file disputes
- Chargebacks can be processed
- Quality issues can be identified
- Platform fraud protection

### Configuration

Admins set clearance period:

1. Go to **Settings → Payments → Payout Settings**
2. Set **Clearance Period (Days)**: 0-90
3. Default: 14

## REST API Access

Vendors access balance via REST API.

### GET /wpss/v1/earnings/summary

Returns vendor's earnings summary.

**Response:**
```json
{
  "total_earned": 12450.00,
  "available_balance": 2670.00,
  "pending_clearance": 1280.00,
  "withdrawn": 8500.00,
  "pending_withdrawal": 0.00,
  "completed_orders": 124,
  "currency": "USD"
}
```

### GET /wpss/v1/earnings/history

Returns detailed earnings history.

**Parameters:**
- `page`: Page number
- `per_page`: Results per page (max 100)
- `start_date`: Filter from date
- `end_date`: Filter to date

## Developer Hooks

### Filters

**`wpss_vendor_available_balance`**

Modify available balance calculation:

```php
add_filter( 'wpss_vendor_available_balance', function( $balance, $vendor_id ) {
    // Custom adjustment
    return $balance;
}, 10, 2 );
```

**`wpss_vendor_total_earned`**

Modify total earned calculation:

```php
add_filter( 'wpss_vendor_total_earned', function( $earned, $vendor_id ) {
    // Custom adjustment
    return $earned;
}, 10, 2 );
```

### Actions

**`wpss_earnings_calculated`**

Fires after earnings are calculated:

```php
add_action( 'wpss_earnings_calculated', function( $vendor_id, $summary ) {
    // Log or process earnings data
}, 10, 2 );
```

## Troubleshooting

### Balance Not Updating

**Check:**
1. Order status is "completed"
2. `vendor_earnings` field is populated
3. Database queries executing correctly
4. No caching issues

**Debug:**
```php
global $wpdb;
$orders = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}wpss_orders
    WHERE vendor_id = {$vendor_id}
    AND status = 'completed'"
);
print_r( $orders );
```

### Wrong Available Balance

**Verify:**
1. All completed orders counted
2. All withdrawals subtracted
3. Pending withdrawals subtracted
4. No double-counting

**Manual Calculation:**
```php
$total_earned = $wpdb->get_var( "SELECT SUM(vendor_earnings)..." );
$withdrawn = $wpdb->get_var( "SELECT SUM(amount)..." );
$pending = $wpdb->get_var( "SELECT SUM(amount)..." );
$available = $total_earned - $withdrawn - $pending;
```

### Clearance Period Not Working

**Check:**
1. Clearance period setting in admin
2. Order completion timestamp
3. Current date and time
4. Timezone settings

## Comparison: Built-in vs External Wallet

### Built-in Earnings System (Current)

**Pros:**
- Simple, no dependencies
- Fast database queries
- Full control
- No compatibility issues

**Cons:**
- Basic functionality only
- No advanced wallet features
- No buyer wallets

### External Wallet Plugins (Not Included)

**Examples**: TeraWallet, WooWallet, MyCred

**Pros:**
- Advanced features (buyer wallets, cashback, etc.)
- Partial payments
- User-to-user transfers
- Loyalty programs

**Cons:**
- Additional plugin required
- More complexity
- Potential compatibility issues
- Extra cost (for some)

### Recommendation

For most service marketplaces, the built-in earnings system is sufficient. Only consider external wallet plugins if you need:

- Buyer wallets (not just vendor payouts)
- Cashback/loyalty systems
- Partial payment from wallet
- User-to-user money transfers

## Next Steps

- **View Dashboard**: Check [earnings dashboard](earnings-dashboard.md)
- **Request Withdrawal**: Learn [withdrawal process](withdrawals.md)
- **Commission Setup**: Configure [commission rates](commission-system.md)
- **Automated Payouts**: Enable [auto-withdrawal](automated-payouts.md)
