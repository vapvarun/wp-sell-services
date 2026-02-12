# Automated Payouts

Automate withdrawal processing for vendors who reach a balance threshold on a scheduled basis.

## Overview

Automated payouts reduce manual withdrawal requests by automatically creating withdrawal requests for vendors when their available balance reaches a configured threshold on scheduled days.

### How It Works

1. **Admin Enables**: Admin enables auto-withdrawal in settings
2. **Threshold Set**: Minimum balance required (default $500)
3. **Schedule Configured**: Weekly or monthly processing
4. **Vendor Qualifies**: Vendor's balance reaches threshold
5. **Auto-Request Created**: System creates withdrawal request automatically
6. **Admin Processes**: Admin reviews and processes payment

### Key Benefits

**For Vendors:**
- No need to manually request withdrawals
- Predictable payment schedule
- Automatic balance management

**For Admins:**
- Batch process multiple payouts
- Scheduled processing days
- Reduced manual request volume

## Default Settings

The plugin comes with these default auto-withdrawal settings:

**Auto-Withdrawal Enabled**: Disabled (false)
**Threshold**: $500
**Schedule**: Monthly (1st of month)

## Admin Configuration

Enable and configure automated payouts for your marketplace.

### Enabling Auto-Withdrawal

1. Go to **WP Sell Services → Settings → Payments**
2. Scroll to **Automatic Withdrawals** section
3. Check **Enable Auto-Withdrawal**
4. Configure settings (see below)
5. Click **Save Payout Settings**

![Auto-withdrawal settings](../images/settings-auto-withdrawal.png)

### Threshold Amount

**Auto-Withdrawal Threshold**: Minimum balance to trigger automatic withdrawal.

**Field Details:**
- Minimum: 100
- Maximum: 10,000
- Step: 50
- Default: 500
- Description: "Minimum available balance to trigger automatic withdrawal"

**Example:**
- Threshold: $500
- Vendor A has $480 available → No auto-withdrawal
- Vendor B has $520 available → Auto-withdrawal created for $520

### Schedule Options

Choose how often auto-withdrawals are processed.

**Available Schedules:**

| Schedule | Processing Day | Example Dates |
|----------|----------------|---------------|
| **Weekly** | Every Monday at 2 AM | Jan 8, Jan 15, Jan 22, Jan 29 |
| **Monthly** | 1st of month at 2 AM | Jan 1, Feb 1, Mar 1, Apr 1 |

**Note**: There is NO bi-weekly option in the current implementation.

**Field Details:**
- Options: weekly, monthly
- Default: monthly
- Description: "When automatic withdrawals are processed"

### Cron Scheduling

Auto-withdrawal processing is handled by WordPress cron.

**Cron Event**: `wpss_process_auto_withdrawals`
**Frequency**: Based on selected schedule (weekly/monthly)
**Time**: 2:00 AM server time

The cron job is automatically scheduled when auto-withdrawal is enabled and cleared when disabled.

## How Auto-Withdrawal Processing Works

### Processing Flow

Every scheduled day (e.g., Monday for weekly, 1st for monthly):

1. **Cron Runs**: WordPress cron triggers at 2 AM
2. **Check Enabled**: Verify auto-withdrawal is enabled
3. **Find Vendors**: Query vendors with completed orders
4. **Check Balances**: Calculate available balance for each vendor
5. **Check Threshold**: Vendor balance >= threshold?
6. **Check Payment Method**: Vendor has payment method configured?
7. **Create Request**: Generate withdrawal request automatically
8. **Notify**: Send email to vendor and admin

### Eligibility Requirements

For a vendor to receive auto-withdrawal:

1. ✓ Available balance >= threshold ($500 default)
2. ✓ Payment method configured (PayPal or bank account)
3. ✓ Payment details saved in user meta
4. ✓ No pending auto-withdrawal already exists

### Example Processing

**Monday, January 15 at 2:00 AM:**

```
System processes auto-withdrawals:

Vendor A:
- Available: $750
- Threshold: $500
- Payment Method: PayPal (john@example.com)
→ Auto-withdrawal created: $750

Vendor B:
- Available: $450
- Threshold: $500
→ Skipped (below threshold)

Vendor C:
- Available: $600
- Threshold: $500
- Payment Method: Not configured
→ Skipped (no payment method)

Vendor D:
- Available: $520
- Threshold: $500
- Pending auto-withdrawal exists
→ Skipped (already has pending)

Results: 1 created, 3 skipped
```

## Vendor Payment Method Setup

Vendors must configure their payment method for auto-withdrawal to work.

### Required User Meta

The system checks these user meta fields:

**`wpss_payout_method`**: Payment method (paypal or bank_transfer)
**`wpss_payout_details`**: Array with payment details

### PayPal Setup

Vendor saves:
```php
update_user_meta( $vendor_id, 'wpss_payout_method', 'paypal' );
update_user_meta( $vendor_id, 'wpss_payout_details', array(
    'paypal_email' => 'vendor@example.com'
) );
```

### Bank Transfer Setup

Vendor saves:
```php
update_user_meta( $vendor_id, 'wpss_payout_method', 'bank_transfer' );
update_user_meta( $vendor_id, 'wpss_payout_details', array(
    'bank_name' => 'Example Bank',
    'account_holder' => 'John Doe',
    'account_number' => '1234567890',
    'routing_number' => '123456789'
) );
```

## Withdrawal Request Creation

When a vendor qualifies, the system automatically creates a withdrawal request.

### Request Details

**Amount**: Full available balance
**Method**: Vendor's configured payment method
**Status**: Pending
**Is Auto**: Flagged as automatic (is_auto = 1 in database)

### Database Record

The withdrawal is inserted into `wpss_withdrawals` table:

```sql
INSERT INTO wpss_withdrawals (
    vendor_id,
    amount,
    method,
    details,
    status,
    is_auto,
    created_at
) VALUES (
    123,
    750.00,
    'paypal',
    '{"paypal_email":"vendor@example.com"}',
    'pending',
    1,
    '2026-01-15 02:00:00'
);
```

### Duplicate Prevention

The system prevents creating duplicate auto-withdrawals:

**Check**: Query for existing pending or approved auto-withdrawals for vendor
**Result**: If found, skip creating new request

This ensures only one auto-withdrawal request exists per vendor at a time.

## Notifications

Both vendors and admins receive email notifications when auto-withdrawals are created.

### Vendor Notification

**Subject**: Auto Withdrawal Created

```
An automatic withdrawal of $750.00 has been scheduled based on your payout settings.

Request ID: WD-1234
Amount: $750.00
Method: PayPal
Status: Pending

This will be reviewed and processed by our team.
```

### Admin Notification

**Subject**: [Platform Name] Auto Withdrawal Request

```
An automatic withdrawal of $750.00 has been created for vendor John Doe.
Please review in the admin panel.

Request ID: WD-1234
Vendor: John Doe (ID: 123)
Amount: $750.00
Method: PayPal
```

**Email Type**: `withdrawal_auto`
**Controlled by**: `wpss_notifications` settings

Admins can disable auto-withdrawal emails in **Settings → Emails**.

## Processing Auto-Withdrawals

Admins process auto-withdrawals the same way as manual withdrawal requests.

### Admin Workflow

1. Go to **WP Sell Services → Withdrawals**
2. Filter by **Auto-Withdrawal** or view all pending
3. Click request to review details
4. Verify vendor and amount
5. Approve request
6. Process payment via PayPal/bank
7. Mark as completed in system

Auto-withdrawals are marked with "Auto" badge in the admin list for easy identification.

## Schedule Examples

### Weekly (Every Monday)

```
January 2026:
- Week 1: Monday, Jan 8 - Process auto-withdrawals
- Week 2: Monday, Jan 15 - Process auto-withdrawals
- Week 3: Monday, Jan 22 - Process auto-withdrawals
- Week 4: Monday, Jan 29 - Process auto-withdrawals

February 2026:
- Week 1: Monday, Feb 5 - Process auto-withdrawals
...
```

### Monthly (1st of Month)

```
January 1, 2026: Process auto-withdrawals
February 1, 2026: Process auto-withdrawals
March 1, 2026: Process auto-withdrawals
April 1, 2026: Process auto-withdrawals
...
```

**Note**: If the 1st falls on a non-working day, the cron still processes. Admin can process payments on the next business day.

## Last Run Information

The system tracks the last auto-withdrawal run.

### Stored Option

**Option Name**: `wpss_last_auto_withdrawal_run`

**Data Stored:**
```php
array(
    'timestamp' => '2026-01-15 02:00:00',
    'processed' => 12,
    'failed' => 2
)
```

Admins can view this information to verify auto-withdrawal is running correctly.

## Disabling Auto-Withdrawal

To stop auto-withdrawal processing:

1. Go to **Settings → Payments → Automatic Withdrawals**
2. Uncheck **Enable Auto-Withdrawal**
3. Click **Save Payout Settings**

**Result:**
- Cron job is cleared (`wp_clear_scheduled_hook`)
- No new auto-withdrawals created
- Existing pending withdrawals remain (still need processing)

## Developer Hooks

### Actions

**`wpss_auto_withdrawal_created`**

Fires when auto-withdrawal is created:

```php
add_action( 'wpss_auto_withdrawal_created', function( $withdrawal_id, $vendor_id, $amount ) {
    // Custom logic after auto-withdrawal created
}, 10, 3 );
```

### Filters

**`wpss_auto_withdrawal_threshold`**

Modify threshold per vendor:

```php
add_filter( 'wpss_auto_withdrawal_threshold', function( $threshold, $vendor_id ) {
    // Custom threshold logic
    return $threshold;
}, 10, 2 );
```

**`wpss_auto_withdrawal_schedule`**

Modify schedule:

```php
add_filter( 'wpss_auto_withdrawal_schedule', function( $schedule ) {
    // Return 'weekly' or 'monthly'
    return $schedule;
}, 10, 1 );
```

### Functions

**Check if auto-withdrawal is enabled:**
```php
$enabled = \WPSellServices\Services\EarningsService::is_auto_withdrawal_enabled();
```

**Get threshold amount:**
```php
$threshold = \WPSellServices\Services\EarningsService::get_auto_withdrawal_threshold();
```

**Get schedule:**
```php
$schedule = \WPSellServices\Services\EarningsService::get_auto_withdrawal_schedule();
```

**Get eligible vendors:**
```php
$earnings_service = new \WPSellServices\Services\EarningsService();
$eligible = $earnings_service->get_eligible_vendors_for_auto_withdrawal();
```

**Manually trigger processing:**
```php
$earnings_service = new \WPSellServices\Services\EarningsService();
$result = $earnings_service->process_auto_withdrawals();
```

## Troubleshooting

### Auto-Withdrawals Not Processing

**Check:**
1. Auto-withdrawal is enabled in settings
2. WordPress cron is functioning
3. Check last run timestamp
4. Verify server time is correct
5. Check error logs

**Debug:**
```php
// Check if cron is scheduled
$timestamp = wp_next_scheduled( 'wpss_process_auto_withdrawals' );
echo date( 'Y-m-d H:i:s', $timestamp );

// Manually trigger (for testing)
do_action( 'wpss_process_auto_withdrawals' );
```

### Vendor Not Getting Auto-Withdrawal

**Verify:**
1. Vendor balance >= threshold
2. Payment method configured
3. Payment details saved
4. No pending auto-withdrawal exists
5. Available balance (not pending clearance)

**Check Vendor Meta:**
```php
$method = get_user_meta( $vendor_id, 'wpss_payout_method', true );
$details = get_user_meta( $vendor_id, 'wpss_payout_details', true );
var_dump( $method, $details );
```

### Wrong Schedule Running

**Verify:**
1. Schedule setting in admin
2. Check scheduled cron time
3. Confirm cron recurrence

```php
$crons = _get_cron_array();
foreach ( $crons as $timestamp => $cron ) {
    if ( isset( $cron['wpss_process_auto_withdrawals'] ) ) {
        echo date( 'Y-m-d H:i:s', $timestamp );
        print_r( $cron['wpss_process_auto_withdrawals'] );
    }
}
```

## Next Steps

- **Manual Withdrawals**: Learn about [manual withdrawal process](withdrawals.md)
- **Earnings Dashboard**: Track earnings in [vendor dashboard](earnings-dashboard.md)
- **Commission System**: Understand [commission calculations](commission-system.md)
- **Admin Processing**: Admins see [withdrawal approval workflow](../admin-tools/withdrawal-approvals.md)
