# Withdrawal Approvals

Process vendor withdrawal requests through the admin panel, managing payouts with approval, rejection, and completion tracking.

## Overview

The withdrawal approval system allows administrators to review vendor withdrawal requests and process payments. Admins can approve, reject, or mark withdrawals as completed once payment is sent.

**Page Location:** WordPress Admin → WP Sell Services → Withdrawals

**Access Required:** `manage_options` capability (administrators only)

## Accessing the Withdrawals Page

1. Log in to WordPress admin
2. Go to **WP Sell Services → Withdrawals** (submenu of WP Sell Services)
3. Page slug: `wpss-withdrawals`

## Dashboard Statistics

The withdrawals page displays 4 status cards showing:

### Pending

- Count of pending withdrawal requests
- Total amount in pending withdrawals
- Color: Yellow/Warning (#dba617)

### Approved

- Count of approved (not yet completed) withdrawals
- Color: Blue (#2271b1)

### Completed

- Count of completed withdrawals
- Total amount successfully paid out
- Color: Green (#00a32a)

### Rejected

- Count of rejected withdrawal requests
- Color: Red (#d63638)

Statistics calculated from `wpss_withdrawals` table using SQL aggregation.

## Withdrawal List Table

The withdrawals table shows all requests with the following columns:

### Columns

| Column | Data Source | Description |
|--------|-------------|-------------|
| ID | `w.id` | Withdrawal request ID |
| Vendor | `u.display_name`, `u.user_email` | Vendor name, email, avatar |
| Amount | `w.amount` | Withdrawal amount (formatted price) |
| Method | `w.method` | Payment method label |
| Status | `w.status` | Current status badge |
| Date | `w.created_at` | Request date, processed date if applicable |
| Actions | - | Action buttons based on status |

### Vendor Column Details

Shows:
- Avatar (32x32px, rounded)
- Display name (linked to user edit page)
- Email address (smaller text)

### Method Column Details

Shows payment method label plus account details:

**PayPal:**
- Shows email from `details` JSON

**Bank Transfer:**
- Shows bank name
- Last 4 digits of account number (masked: `***1234`)

### Status Column Details

Status displayed as badge with color coding:

| Status | Badge Color | Background | Text Color |
|--------|-------------|------------|-----------|
| `pending` | Yellow | `#fff3cd` | `#856404` |
| `approved` | Blue | `#d1e7f3` | `#0a4b78` |
| `completed` | Green | `#d4edda` | `#155724` |
| `rejected` | Red | `#f8d7da` | `#721c24` |

Also shows truncated admin note (5 words) with full note in tooltip if present.

### Date Column Details

Shows:
- Request date (formatted with WordPress date/time format)
- Processed date below if `processed_at` is not null (labeled "Processed: [date]")

### Actions Column

Actions shown based on current status:

**For Pending Status:**
- **Approve** button (primary blue button)
- **Reject** button (standard button)

**For Approved Status:**
- **Mark Completed** button (primary blue button)
- **Reject** button (standard button)

**For Completed/Rejected:**
- Status message only ("Payment sent" or "Request rejected")

## Filtering Withdrawals

Use the status filter tabs above the table:

### Filter Tabs

- **All** - Shows all withdrawals
- **Pending** - Shows only `status = 'pending'`
- **Approved** - Shows only `status = 'approved'`
- **Completed** - Shows only `status = 'completed'`
- **Rejected** - Shows only `status = 'rejected'`

Each tab shows count in parentheses from statistics.

## Processing Withdrawals

### Modal Interface

When you click an action button (Approve, Reject, Mark Completed), a modal opens:

**Modal Title:** Changes based on action:
- "Approve Withdrawal"
- "Mark as Completed"
- "Reject Withdrawal"

**Modal Content:**
- Confirmation message with amount and vendor name
- Optional admin note textarea
- Cancel and Confirm buttons

### Approving a Withdrawal

1. Click **Approve** button on pending withdrawal
2. Modal opens with confirmation: "Approve this withdrawal request for [amount] from [vendor]?"
3. Optionally add admin note
4. Click **Confirm**
5. AJAX request to `wpss_process_withdrawal` with:
   - `withdrawal_id` - Request ID
   - `action_type` - `approve`
   - `admin_note` - Optional note
6. Status updates to `approved`
7. Page reloads to show updated status

**What Happens:**
- Database: `status` → `'approved'`
- Database: `processed_at` → current timestamp
- Database: `processed_by` → current admin user ID
- Database: `admin_note` → saved if provided
- Vendor receives notification: "Your withdrawal request for [amount] has been approved."

### Marking as Completed

1. Click **Mark Completed** button on approved withdrawal
2. Modal opens: "Mark this withdrawal as completed. This means payment has been sent to [vendor]."
3. Optionally add admin note (e.g., transaction reference)
4. Click **Confirm**
5. Status updates to `completed`

**What Happens:**
- Database: `status` → `'completed'`
- Database: `processed_at` → current timestamp
- Database: `processed_by` → current admin user ID
- Vendor receives notification: "Your withdrawal request for [amount] has been completed."

### Rejecting a Withdrawal

1. Click **Reject** button (available for pending or approved)
2. Modal opens: "Reject this withdrawal request from [vendor]? The funds will be returned to their available balance."
3. Add admin note explaining rejection reason
4. Click **Confirm**
5. Status updates to `rejected`

**What Happens:**
- Database: `status` → `'rejected'`
- Database: `processed_at` → current timestamp
- Database: `processed_by` → current admin user ID
- Funds remain in vendor's available balance (not deducted)
- Vendor receives notification: "Your withdrawal request for [amount] has been rejected."

## Withdrawal Statuses

The system uses 4 statuses defined in `EarningsService`:

```php
public const WITHDRAWAL_PENDING   = 'pending';
public const WITHDRAWAL_APPROVED  = 'approved';
public const WITHDRAWAL_COMPLETED = 'completed';
public const WITHDRAWAL_REJECTED  = 'rejected';
```

### Status Flow

**Normal Flow:**
`pending` → `approved` → `completed`

**Rejection Flow:**
`pending` → `rejected`
OR
`approved` → `rejected`

**Status Meanings:**

- **Pending:** Newly submitted, awaiting admin review
- **Approved:** Admin approved, payment needs to be sent
- **Completed:** Payment sent, withdrawal finished
- **Rejected:** Request denied, funds returned to vendor balance

## Payment Methods

Supported withdrawal methods (from `EarningsService::get_withdrawal_methods()`):

```php
'paypal'        => 'PayPal',
'bank_transfer' => 'Bank Transfer',
```

Additional methods can be added via filter:

```php
apply_filters( 'wpss_withdrawal_methods', $methods );
```

### Method Details Storage

Payment account details stored as JSON in `details` column:

**PayPal:**
```json
{
  "email": "vendor@example.com"
}
```

**Bank Transfer:**
```json
{
  "bank_name": "Chase Bank",
  "account_number": "1234567890",
  "routing_number": "021000021",
  "account_holder": "John Smith"
}
```

## Vendor Withdrawal Limits

### Minimum Withdrawal Amount

Default: $50.00

Retrieved from settings via `EarningsService::get_min_withdrawal_amount()`:

1. Checks `wpss_payouts['min_withdrawal']`
2. Falls back to `wpss_vendor['min_payout_amount']`
3. Default: 50.0

Vendors cannot request withdrawal below this amount.

### Available Balance Calculation

Before allowing withdrawal, system calculates:

```php
$total_earned       = // Sum of vendor_earnings from completed orders
$withdrawn          = // Sum of completed withdrawals
$pending_withdrawal = // Sum of pending + approved withdrawals
$available          = $total_earned - $withdrawn - $pending_withdrawal
```

Withdrawal amount cannot exceed available balance.

## Auto-Withdrawal System **[PRO]**

**Note:** Auto-withdrawal features are part of the Pro version.

### Checking if Enabled

```php
EarningsService::is_auto_withdrawal_enabled()
// Returns bool from wpss_payouts['auto_withdrawal_enabled']
```

### Auto-Withdrawal Settings

Retrieved from `wpss_payouts` option:

- `auto_withdrawal_threshold` - Minimum balance to trigger (default: 500)
- `auto_withdrawal_schedule` - Frequency: 'weekly' or 'monthly' (default: 'monthly')

### Eligible Vendors

Auto-withdrawal processes vendors who meet ALL criteria:

1. Available balance ≥ threshold
2. Payout method configured (`wpss_payout_method` user meta)
3. Payout details complete (`wpss_payout_details` user meta)

### Processing Auto-Withdrawals

Triggered via WordPress cron: `wpss_process_auto_withdrawals`

**Schedule:**
- Weekly: Next Monday at 2:00 AM
- Monthly: 1st day of next month at 2:00 AM

**Process:**
1. Get eligible vendors via `get_eligible_vendors_for_auto_withdrawal()`
2. Create withdrawal request for each with `is_auto = 1`
3. Notify admin via email (if `withdrawal_auto` email type enabled)
4. Notify vendor via in-app notification
5. Log results in `wpss_last_auto_withdrawal_run` option

## Database Query Details

### Getting Withdrawals

Main query in `get_withdrawals()`:

```sql
SELECT w.*, u.display_name as vendor_name, u.user_email as vendor_email
FROM {$wpdb->prefix}wpss_withdrawals w
LEFT JOIN {$wpdb->users} u ON w.vendor_id = u.ID
WHERE [status filter]
ORDER BY w.created_at DESC
LIMIT [per_page] OFFSET [offset]
```

Supports:
- Pagination (20 per page default)
- Status filtering
- Sorting by created_at DESC

### Getting Statistics

Statistics query:

```sql
SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as completed_amount
FROM {$wpdb->prefix}wpss_withdrawals
```

## WordPress Hooks

### Action: Withdrawal Processed

Fires when withdrawal status is updated:

```php
/**
 * Fires when withdrawal is processed.
 *
 * @param int    $withdrawal_id Withdrawal ID.
 * @param string $status        New status (approved/completed/rejected).
 * @param object $withdrawal    Withdrawal data object.
 */
do_action( 'wpss_withdrawal_processed', $withdrawal_id, $status, $withdrawal );
```

### Action: Withdrawal Requested

Fires when vendor creates withdrawal request:

```php
/**
 * Fires when withdrawal is requested.
 *
 * @param int   $withdrawal_id Withdrawal ID.
 * @param int   $vendor_id     Vendor user ID.
 * @param float $amount        Withdrawal amount.
 */
do_action( 'wpss_withdrawal_requested', $withdrawal_id, $vendor_id, $amount );
```

### Action: Auto-Withdrawal Created **[PRO]**

Fires when auto-withdrawal is created:

```php
/**
 * Fires when auto withdrawal is created.
 *
 * @param int   $withdrawal_id Withdrawal ID.
 * @param int   $vendor_id     Vendor user ID.
 * @param float $amount        Withdrawal amount.
 */
do_action( 'wpss_auto_withdrawal_created', $withdrawal_id, $vendor_id, $amount );
```

## AJAX Implementation

### Action: `wpss_process_withdrawal`

**Nonce:** `wpss_withdrawals_admin`

**Parameters:**
- `withdrawal_id` (int) - Withdrawal request ID
- `action_type` (string) - `approve`, `complete`, or `reject`
- `admin_note` (string) - Optional admin note

**Process:**
1. Verify nonce and admin capability
2. Validate withdrawal ID exists
3. Map action_type to status constant
4. Call `EarningsService::process_withdrawal()`
5. Return JSON success/error

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Withdrawal updated successfully."
  }
}
```

## Email Notifications

### To Vendor

Notification created via `NotificationService::create()` when withdrawal is processed:

**Title:** "Withdrawal Update"

**Message:** "Your withdrawal request for [amount] has been [status]."

**Data:** `{ withdrawal_id: [id] }`

### To Admin **[PRO]**

When vendor requests withdrawal (if `withdrawal_requested` email type enabled):

**To:** `admin_email` from WordPress settings

**Subject:** "[Platform Name] New Withdrawal Request"

**Message:** "Vendor [name] has requested a withdrawal of [amount]. Please review in the admin panel."

## Technical Details

**Page Hook:** `sell-services_page_wpss-withdrawals`

**AJAX URL:** `admin_url('admin-ajax.php')`

**Database Table:** `{$wpdb->prefix}wpss_withdrawals`

**Table Columns:**
- `id` - Auto-increment primary key
- `vendor_id` - Foreign key to wp_users
- `amount` - DECIMAL withdrawal amount
- `method` - VARCHAR payment method
- `details` - TEXT JSON payment details
- `status` - VARCHAR (pending/approved/completed/rejected)
- `admin_note` - TEXT optional admin note
- `processed_at` - DATETIME processing timestamp
- `processed_by` - INT admin user ID
- `is_auto` - TINYINT auto-withdrawal flag **[PRO]**
- `created_at` - DATETIME request timestamp

## Pagination

The withdrawals list supports pagination:

- Default: 20 items per page
- URL parameter: `paged`
- Total pages calculated: `ceil(total / per_page)`
- Uses WordPress `paginate_links()` for pagination display

## Styling

Custom CSS included inline in page:

- Grid layout for statistics cards (4 columns)
- Status badges with color coding
- Responsive table design
- Modal overlay with centered content
- Vendor info flex layout with avatar

## Common Workflows

### Process Pending Withdrawal

1. Vendor submits withdrawal request
2. Admin clicks **Pending** filter tab
3. Reviews request details and vendor balance
4. Clicks **Approve**
5. Adds note: "Approved - will process via PayPal"
6. Confirms approval
7. Processes payment externally via PayPal
8. Returns to withdrawals page
9. Clicks **Mark Completed**
10. Adds note: "PayPal Transaction ID: [txn_id]"
11. Confirms completion

### Reject Invalid Request

1. Admin reviews pending withdrawal
2. Notices vendor's available balance is insufficient
3. Clicks **Reject**
4. Adds note: "Insufficient available balance. Please verify earnings."
5. Confirms rejection
6. Vendor receives notification and can review balance

## Best Practices

**Before Approving:**
- Verify vendor has sufficient available balance
- Check payment account details are complete
- Review vendor's withdrawal history
- Ensure no disputes or issues with recent orders

**Adding Admin Notes:**
- Document payment reference numbers
- Note payment method/platform used
- Record any special circumstances
- Provide clear rejection reasons

**Payment Processing:**
- Process approved withdrawals within 3-5 business days
- Keep external payment records (PayPal transactions, bank transfers)
- Mark completed only after payment is sent
- Respond to vendor questions about delays

## Next Steps

- **[Vendor Management](vendor-management.md)** - Managing vendor accounts
- **[Commission System](../earnings-wallet/commission-system.md)** - Understanding earnings calculation
- **[Vendor Dashboard](../vendor-system/vendor-dashboard.md)** - Vendor earnings view
