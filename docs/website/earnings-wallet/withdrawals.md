# Vendor Withdrawals

When vendors are ready to get paid, they submit a withdrawal request from their dashboard. Here is how the entire process works -- from request to payment.

## How Withdrawals Work

1. **Vendor earns money** -- orders are completed and earnings pass the clearance period
2. **Vendor requests withdrawal** -- picks an amount and payment method
3. **Admin reviews** -- approves or rejects the request
4. **Admin sends payment** -- processes payment via bank transfer or PayPal
5. **Vendor gets paid** -- funds arrive in their account

## Minimum Withdrawal Amount

Vendors must have a minimum balance before they can request a withdrawal. The default minimum is $50, but you can change this in **Settings > Payments > Payout Settings**.

If a vendor's available balance is below the minimum, the withdrawal button is disabled and they see how much more they need to earn.

## Withdrawal Methods

Vendors choose how they want to be paid when they submit a request:

### Bank Transfer

- Vendor provides: bank name, account holder, account number, routing/sort code
- Processing time: 3-7 business days
- Usually free for domestic transfers
- Best for regular, larger withdrawals

### PayPal

- Vendor provides: their PayPal email address
- Processing time: 1-3 business days
- Best for international vendors or quick payouts

## How Vendors Request a Withdrawal

From the vendor dashboard:

1. Go to **Earnings** section
2. Check the available balance (must meet minimum threshold)
3. Click **Request Withdrawal**
4. Enter the amount (or click **Withdraw All** for the full balance)
5. Select payment method: **Bank Transfer** or **PayPal**
6. Enter payment details
7. Review and click **Submit Request**

The vendor sees a confirmation with a request ID and status. They also receive an email confirming the submission.

## Withdrawal Statuses

| Status | What It Means |
|--------|-------------|
| **Pending** | Request submitted, waiting for admin review |
| **Approved** | Admin approved it, payment is being processed |
| **Completed** | Payment has been sent to the vendor |
| **Rejected** | Request denied -- funds return to vendor's available balance |

The typical flow is: **Pending > Approved > Completed**

If rejected, the funds go right back to the vendor's balance so they are not lost.

## What Admins Do

See the full admin workflow in [Withdrawal Approvals](../admin-tools/withdrawal-approvals.md). In short:

1. Go to **WP Sell Services > Withdrawals**
2. Review pending requests (vendor info, amount, payment details)
3. **Approve** the request
4. Process payment externally (send the bank transfer or PayPal payment)
5. Return to the request and click **Mark as Completed**

Admins can also **Reject** a request with a reason if something is wrong (incomplete payment details, outstanding disputes, etc.).

## Withdrawal History

Vendors can see all their past withdrawal requests in **Dashboard > Earnings > Withdrawals**, including:

- Request ID and amount
- Payment method used
- Current status
- Date requested and date processed
- Any admin notes

## Clearance Period

Earnings do not become available for withdrawal immediately. After an order is completed, there is a clearance period (default: 14 days) before those earnings can be withdrawn. This protects against:

- Late buyer disputes
- Chargebacks from payment processors
- Quality issues discovered after delivery

Admins can adjust the clearance period (0-90 days) in **Settings > Payments > Payout Settings**.

## Common Questions

**"Why can't I withdraw?"** Check that your available balance meets the minimum threshold and that you do not already have a pending withdrawal request.

**"My request was rejected -- what now?"** Check the rejection reason (visible in your withdrawal history), fix the issue (usually incomplete payment details), and submit a new request.

**"How long until I receive payment?"** After admin approval, bank transfers typically take 3-7 business days and PayPal takes 1-3 business days.

## Related Docs

- [Earnings Dashboard](earnings-dashboard.md) -- Tracking vendor income
- [Commission System](commission-system.md) -- How earnings are calculated
- [Automated Payouts](automated-payouts.md) -- Schedule automatic withdrawals
- [Withdrawal Approvals](../admin-tools/withdrawal-approvals.md) -- Admin processing guide
