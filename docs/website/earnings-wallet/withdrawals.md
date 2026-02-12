# Vendor Withdrawals

Vendors can request withdrawals to transfer earnings from their marketplace balance to their bank account or PayPal.

## Overview

The withdrawal system allows vendors to request payouts of their available earnings. After completing orders and passing the clearance period, vendors can withdraw funds through supported payment methods.

### Withdrawal Flow

1. **Earnings Available**: Orders complete and pass clearance period (14 days default)
2. **Request Withdrawal**: Vendor submits withdrawal request
3. **Admin Review**: Admin approves or rejects request
4. **Payment Processing**: Admin processes payment via selected method
5. **Confirmation**: Vendor receives payment and confirmation

### Key Terms

| Term | Definition |
|------|------------|
| **Available Balance** | Earnings ready for withdrawal |
| **Minimum Threshold** | Minimum amount required to withdraw (default $50) |
| **Clearance Period** | Days after order completion before withdrawal (default 14 days) |
| **Withdrawal Methods** | PayPal or Bank Transfer |
| **Processing Time** | Days until payment arrives |

## Minimum Withdrawal Amount

Platforms set a minimum withdrawal threshold to reduce processing overhead.

### Default Minimum

**Minimum Withdrawal**: $50

This can be configured by admins in **Settings → Payments → Payout Settings**.

### Checking Minimum

Before requesting withdrawal, vendors see:

```
Available Balance: $1,250.00
Minimum Withdrawal: $50.00

✓ You meet the minimum requirement
```

If balance is below minimum:

```
Available Balance: $35.00
Minimum Withdrawal: $50.00

✗ Minimum not met. Need $15.00 more to withdraw.
```

### Admin Configuration

1. Go to **WP Sell Services → Settings → Payments**
2. Scroll to **Withdrawal Settings** section
3. Set **Minimum Withdrawal**: Value between 0-1000
4. Default: 50
5. Click **Save Payout Settings**

## Available Withdrawal Methods

The plugin supports two withdrawal methods by default.

### 1. Bank Transfer

Direct deposit to vendor's bank account.

**Details Required:**
- Bank name
- Account holder name
- Account number
- Routing number (US) / IFSC (India) / Sort code (UK)

**Characteristics:**
- **Processing Time**: 3-7 business days
- **Fees**: Usually free
- **Best For**: Regular domestic withdrawals

### 2. PayPal

Transfer to vendor's PayPal account.

**Details Required:**
- PayPal email address

**Characteristics:**
- **Processing Time**: 1-3 business days
- **Fees**: Usually free for domestic
- **Best For**: International vendors, quick payouts

## Requesting a Withdrawal

### Vendor: Submit Withdrawal Request

**Step 1: Navigate to Earnings**

1. Log in to vendor dashboard
2. Go to **Earnings** or **Wallet** section
3. View your available balance

**Step 2: Check Balance**

Verify you have sufficient available balance:

```
Available Balance: $1,250.00
Minimum Withdrawal: $50.00
Pending Withdrawal: $0.00
```

**Step 3: Click Request Withdrawal**

Button is enabled if balance ≥ minimum.

**Step 4: Enter Amount**

Enter withdrawal amount or click **Withdraw All**:

```
┌─────────────────────────────────────────────────┐
│  Withdrawal Request                             │
├─────────────────────────────────────────────────┤
│  Available Balance: $1,250.00                   │
│  Minimum: $50.00                                │
│                                                  │
│  Amount: $ [________] [Withdraw All]            │
│                                                  │
│  [Continue]                                     │
└─────────────────────────────────────────────────┘
```

**Step 5: Select Payment Method**

Choose Bank Transfer or PayPal:

```
┌─────────────────────────────────────────────────┐
│  Payment Method                                 │
├─────────────────────────────────────────────────┤
│  ● Bank Transfer (3-7 days)                     │
│  ○ PayPal (1-3 days)                            │
│                                                  │
│  [Continue]                                     │
└─────────────────────────────────────────────────┘
```

**Step 6: Enter Payment Details**

Provide payment method details:

**Bank Transfer:**
```
Bank Name: [_________________________]
Account Holder: [_____________________]
Account Number: [_____________________]
Routing Number: [_____________________]
```

**PayPal:**
```
PayPal Email: [_______________________]
```

**Step 7: Submit Request**

Review and confirm:

```
┌─────────────────────────────────────────────────┐
│  Confirm Withdrawal                             │
├─────────────────────────────────────────────────┤
│  Amount: $1,250.00                              │
│  Method: Bank Transfer                          │
│  Account: ****7890                              │
│                                                  │
│  [Cancel] [Submit Request]                      │
└─────────────────────────────────────────────────┘
```

**Step 8: Request Submitted**

Confirmation message appears:

```
✓ Withdrawal request submitted

Request ID: #WD-1234
Amount: $1,250.00
Status: Pending

Admin will review within 1-2 business days.

[View Request]
```

### Email Notification

Vendor receives email confirmation:

```
Subject: Withdrawal Request Submitted (#WD-1234)

Your withdrawal request has been submitted.

Amount: $1,250.00
Method: Bank Transfer
Status: Pending

We'll review your request within 1-2 business days.
```

## Withdrawal Statuses

Withdrawal requests go through these statuses:

### Status Definitions

| Status | Description | Vendor Action | Admin Action |
|--------|-------------|---------------|--------------|
| **Pending** | Awaiting admin review | Wait | Review and approve/reject |
| **Approved** | Approved, payment processing | Wait for payment | Process payment |
| **Completed** | Payment sent successfully | Confirm receipt | None |
| **Rejected** | Request denied | Review reason | None |

### Status Flow

```
Pending → Approved → Completed
    ↓
Rejected
```

When rejected, funds return to vendor's available balance.

## Admin: Processing Withdrawals

Admins review and process withdrawal requests.

### Viewing Requests

1. Go to **WP Sell Services → Withdrawals**
2. View list of pending requests
3. Click request to see details

### Request Details

Admins see:
- Vendor name and ID
- Vendor email
- Total earnings and withdrawal history
- Request amount
- Payment method and details
- Available balance verification

### Approving Request

1. Click **Approve** button
2. Status changes to "Approved"
3. Vendor receives approval email
4. Admin processes payment externally
5. Admin marks as "Completed" in system

### Rejecting Request

1. Click **Reject** button
2. Enter rejection reason
3. Status changes to "Rejected"
4. Funds return to vendor balance
5. Vendor receives rejection email

### Marking as Completed

After payment is sent:

1. Click **Mark as Completed**
2. Enter transaction ID
3. Enter payment date
4. Add notes (optional)
5. Click **Save**
6. Status changes to "Completed"
7. Vendor receives completion email

## Withdrawal History

Vendors can view all past withdrawal requests.

### Viewing History

1. Go to **Dashboard → Earnings**
2. Click **Withdrawals** tab
3. View list of all requests

### History Table

| ID | Amount | Method | Status | Date |
|----|--------|--------|--------|------|
| WD-234 | $1,250 | Bank | Processing | Jan 15 |
| WD-210 | $980 | Bank | Completed | Dec 28 |
| WD-198 | $1,100 | PayPal | Completed | Dec 15 |

### Request Details

Click any request to view:
- Request ID and status
- Amount and method
- Payment details
- Timeline of status changes
- Transaction ID (if completed)
- Admin notes (if any)

## Clearance Period

Earnings must pass a clearance period before withdrawal.

### Default Settings

**Clearance Days**: 14

This means earnings from completed orders become available 14 days after order completion.

### Purpose

- Buyer can identify issues after delivery
- Time for disputes to be filed
- Platform fraud protection
- Chargeback prevention

### Tracking Clearance

In earnings dashboard:

```
Pending Clearance: $385.00

Order #1234 - $125.00
  Completed: Jan 15
  Available: Jan 29 (in 5 days)

Order #1235 - $150.00
  Completed: Jan 18
  Available: Feb 1 (in 8 days)
```

### Admin Configuration

Change clearance period:

1. Go to **Settings → Payments → Payout Settings**
2. Set **Clearance Period (Days)**: 0-90
3. Default: 14
4. Click **Save**

## REST API Endpoints

Vendors can manage withdrawals programmatically.

### POST /wpss/v1/withdrawals

Create withdrawal request.

**Authentication**: Required
**Permission**: Vendor with approved status

**Parameters:**
```json
{
  "amount": 1250.00,
  "method": "bank_transfer",
  "details": {
    "bank_name": "Example Bank",
    "account_holder": "John Doe",
    "account_number": "1234567890",
    "routing_number": "123456789"
  }
}
```

**Response:**
```json
{
  "id": 234,
  "amount": 1250.00,
  "method": "bank_transfer",
  "status": "pending",
  "created_at": "2026-01-15 14:30:00"
}
```

### GET /wpss/v1/withdrawals

Get withdrawal history.

**Parameters:**
- `page`: Page number
- `per_page`: Results per page (max 100)
- `status`: Filter by status (pending/approved/completed/rejected)

**Response:**
```json
{
  "items": [
    {
      "id": 234,
      "amount": 1250.00,
      "method": "bank_transfer",
      "status": "pending",
      "created_at": "2026-01-15 14:30:00"
    }
  ],
  "total": 45,
  "pages": 3,
  "page": 1,
  "per_page": 20
}
```

### PUT /wpss/v1/withdrawals/{id}

Admin only: Process withdrawal.

**Authentication**: Required
**Permission**: Administrator

**Parameters:**
```json
{
  "status": "approved",
  "note": "Payment being processed"
}
```

**Response:**
```json
{
  "id": 234,
  "status": "approved",
  "notes": "Payment being processed",
  "processed_at": "2026-01-16 09:30:00"
}
```

### GET /wpss/v1/withdrawals/methods

Get available withdrawal methods.

**Response:**
```json
{
  "bank_transfer": "Bank Transfer",
  "paypal": "PayPal"
}
```

## Troubleshooting

### Cannot Request Withdrawal

**Reasons:**
1. Balance below minimum ($50)
2. Pending withdrawal already exists
3. Account restrictions
4. No available balance (all in clearance)

**Solution:**
- Wait for more earnings
- Check pending withdrawals
- Contact admin if account restricted

### Request Rejected

**Common Reasons:**
1. Incomplete payment details
2. Insufficient balance
3. Account verification required
4. Outstanding disputes

**Solution:**
- Update payment information
- Wait for balance to increase
- Complete verification
- Resolve disputes

### Payment Not Received

**Steps:**
1. Check request status (must be "Completed")
2. Verify payment details are correct
3. Check processing time (3-7 days for bank)
4. Contact your bank/PayPal
5. Contact admin with transaction ID

### Wrong Amount Received

**Check:**
1. Withdrawal fees (if any)
2. Currency conversion (if applicable)
3. Bank incoming transfer fees
4. Transaction ID matches

## Developer Hooks

### Filter Minimum Withdrawal

```php
add_filter( 'wpss_minimum_withdrawal_amount', function( $minimum ) {
    return 100; // Custom minimum
}, 10, 1 );
```

### Filter Withdrawal Methods

```php
add_filter( 'wpss_withdrawal_methods', function( $methods ) {
    $methods['stripe'] = __( 'Stripe', 'wp-sell-services' );
    return $methods;
}, 10, 1 );
```

### Action on Withdrawal Request

```php
add_action( 'wpss_withdrawal_requested', function( $withdrawal_id, $vendor_id, $amount ) {
    // Custom logic when withdrawal requested
}, 10, 3 );
```

### Action on Withdrawal Processed

```php
add_action( 'wpss_withdrawal_processed', function( $withdrawal_id, $status, $withdrawal ) {
    // Custom logic when withdrawal approved/rejected/completed
}, 10, 3 );
```

## Next Steps

- **View Earnings**: Check your [earnings dashboard](earnings-dashboard.md)
- **Commission System**: Understand [commission calculations](commission-system.md)
- **Automated Payouts**: Set up [automated withdrawals](automated-payouts.md) **[PRO]**
- **Admin Guide**: Admins see [withdrawal approval workflow](../admin-tools/withdrawal-approvals.md)
