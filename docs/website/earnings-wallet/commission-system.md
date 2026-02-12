# Commission System

The commission system determines how revenue is split between the platform and vendors for each completed order.

## Overview

When a buyer purchases a service, the order total is divided between the platform commission and vendor earnings. The platform takes a percentage commission, and the vendor receives the remainder.

### How Commission Works

1. **Order Created**: Buyer completes purchase
2. **Total Calculated**: Package price + add-ons
3. **Commission Applied**: Platform percentage deducted
4. **Vendor Earnings**: Remaining amount credited to vendor
5. **Payment Released**: Funds available after clearing period

### Key Components

| Component | Description |
|-----------|-------------|
| **Order Total** | Full price paid by buyer |
| **Commission Rate** | Platform percentage (default 10%) |
| **Platform Fee** | Dollar amount taken by platform |
| **Vendor Earnings** | Amount vendor receives after commission |

## Default Commission Settings

The plugin comes with these default commission settings:

**Global Commission Rate**: 10%
**Per-Vendor Rates**: Enabled (vendors can have custom rates)

### Commission Calculation

```
Order Total: $100.00
Commission (10%): $10.00
Vendor Earnings: $90.00
```

The commission is stored in the `platform_fee` field and vendor earnings in `vendor_earnings` when an order is marked as completed.

## Configuring Global Commission

Set the default commission rate that applies to all vendors.

### Admin Configuration

1. Go to **WP Sell Services → Settings → Payments**
2. Find **Commission Settings** section
3. Enter **Commission Rate (%)**: Value between 0-50
4. Check **Per-Vendor Rates** to allow custom rates per vendor
5. Click **Save Commission Settings**

![Commission settings](../images/settings-payments-commission.png)

**Commission Rate Field**:
- Minimum: 0%
- Maximum: 50%
- Step: 0.1%
- Default: 10%
- Description: "Default percentage deducted from vendor earnings for all orders"

### Commission on Add-ons

Commission is calculated on the full order total, including add-ons. Tips are excluded from commission calculations.

## Per-Vendor Commission Rates

When **Enable Vendor Rates** is checked, you can set custom commission rates for individual vendors.

### Setting Custom Vendor Rates

**Via Vendor Profile:**

1. Go to **WP Sell Services → Vendors**
2. Click on vendor name
3. Find **Commission Settings** section
4. Enter custom commission rate
5. Click **Update**

The custom rate overrides the global rate for that vendor's orders.

**Use Cases:**
- Reward top performers with lower commission
- Promotional rates for new vendors
- Premium vendors with higher commission
- Special partnership agreements

### Commission Priority

When calculating commission, the system uses:

1. **Per-Vendor Rate** (if set) - highest priority
2. **Global Rate** (default) - fallback

## When Commission is Calculated

Commission is calculated when the order is marked as **completed**.

### Order Completion Flow

```
Order In Progress → Delivered → Buyer Accepts → Order Completed
                                                  ↓
                                         Commission Calculated
                                         Vendor Earnings Credited
```

**Database Fields Updated:**
- `commission_rate`: The percentage applied
- `platform_fee`: Dollar amount of commission
- `vendor_earnings`: Net amount to vendor

### Example Timeline

**Day 1**: Order placed for $100
**Day 3**: Vendor delivers work
**Day 5**: Buyer accepts delivery
**Day 5**: Order status changes to "completed"
  - Commission calculated: $10 (10%)
  - Vendor earnings: $90
  - Funds added to vendor balance

## Commission in Order Details

### Admin Order View

Admins see complete financial breakdown:

```
Order Total: $100.00
Commission (10%): $10.00
Vendor Earnings: $90.00
```

### Vendor Order View

Vendors see their earnings after commission:

```
Order Total: $100.00
Your Earnings: $90.00
```

Commission details are stored in the `wpss_orders` table with these fields:
- `total`: Order total amount
- `commission_rate`: Percentage applied
- `platform_fee`: Commission amount
- `vendor_earnings`: Vendor's net earnings

## Tips and Commission

Tips are commission-free. 100% of tip amounts go directly to the vendor.

**Example with Tip:**

```
Order Total: $100.00
Tip: $10.00

Commission (10% of $100): $10.00
Vendor Earnings: $90.00 + $10.00 tip = $100.00
```

Tips are tracked separately and not included in commission calculations.

## REST API Endpoints

The Earnings Controller provides API access to commission and earnings data.

### GET /wpss/v1/earnings/summary

Retrieve vendor earnings summary including commission paid.

**Response:**
```json
{
  "total_earned": 12450.00,
  "available_balance": 2670.00,
  "pending_clearance": 1280.00,
  "withdrawn": 8500.00,
  "pending_withdrawal": 0.00,
  "completed_orders": 124
}
```

### GET /wpss/v1/earnings/history

Get detailed earnings history with commission breakdown per order.

**Parameters:**
- `page`: Page number (default: 1)
- `per_page`: Results per page (default: 20, max: 100)

**Response includes:**
- Order number and service
- Total amount
- Vendor earnings after commission
- Commission rate applied
- Platform fee amount

## Refunds and Commission

When an order is refunded, commission is reversed.

### Full Refund

```
Original Order: $100.00
Commission Collected: $10.00
Vendor Earned: $90.00

→ Full Refund Issued
→ Commission reversed: -$10.00 (returned to buyer)
→ Vendor earnings deducted: -$90.00
```

### Partial Refund

Commission is reversed proportionally:

```
Original Order: $100.00
Commission: $10.00
Partial Refund: $50.00

→ Commission reversed: $5.00
→ Vendor earnings deducted: $45.00
```

## Troubleshooting

### Commission Not Calculated

**Check:**
1. Order status is "completed"
2. Global commission rate is set in Settings
3. Vendor earnings field populated
4. No database errors in debug log

**Debug:**

Enable debug mode in **Settings → Advanced** to log commission calculations.

### Wrong Commission Amount

**Verify:**
1. Correct commission rate applied (global or per-vendor)
2. Order total is accurate
3. No manual adjustments made
4. Commission calculation happened on completion

**Solution:**

Check the order's `commission_rate`, `platform_fee`, and `vendor_earnings` fields in the database.

## Developer Hooks

### Filters

**`wpss_commission_rate`**

Modify commission rate before calculation:

```php
add_filter( 'wpss_commission_rate', function( $rate, $vendor_id, $order ) {
    // Custom logic to modify commission rate
    return $rate;
}, 10, 3 );
```

**`wpss_vendor_earnings`**

Adjust vendor earnings after commission:

```php
add_filter( 'wpss_vendor_earnings', function( $earnings, $order_total, $commission ) {
    // Custom adjustment logic
    return $earnings;
}, 10, 3 );
```

### Actions

**`wpss_commission_calculated`**

Fires after commission is calculated:

```php
add_action( 'wpss_commission_calculated', function( $order_id, $commission_amount ) {
    // Log or process commission
}, 10, 2 );
```

## Next Steps

- **Configure Withdrawals**: Set up [withdrawal system](withdrawals.md) for vendor payouts
- **View Earnings Dashboard**: Understand the [earnings dashboard](earnings-dashboard.md)
- **Set Up Automated Payouts**: Enable [automated payouts](automated-payouts.md) **[PRO]**
- **Review Order Workflow**: Learn about [order lifecycle](../order-management/order-lifecycle.md)
