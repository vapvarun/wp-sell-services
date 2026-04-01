# Vendor Earnings Dashboard

The earnings dashboard gives every vendor a clear view of their income -- what they have earned, what is available to withdraw, and what is still being processed.

![Vendor Earnings Dashboard](../images/vendor-earnings-dashboard.png)

## What Vendors See

When a vendor goes to **Dashboard > Earnings**, they see these key numbers at a glance:

| Metric | What It Means |
|--------|-------------|
| **Total Earned** | Lifetime earnings from all completed orders (after commission) |
| **Available Balance** | Money ready to withdraw right now |
| **Pending Clearance** | Earnings from recent orders still in the clearance period |
| **Withdrawn** | Total amount successfully paid out over time |
| **Pending Withdrawal** | Amount currently in a withdrawal request awaiting admin approval |

There is also a count of **Completed Orders** so vendors can see their overall activity.

## Understanding the Numbers

### Available Balance

This is the amount a vendor can actually withdraw. For earnings to appear here, three things must be true:

1. The order is **completed** (buyer accepted delivery)
2. The **clearance period** has passed (default: 14 days after completion)
3. The amount is **not already** in a pending withdrawal request

### Pending Clearance

These are earnings from completed orders that have not yet passed the clearance period. Think of it as a safety buffer -- it gives buyers time to report issues before funds are released.

For example, if an order completed on January 15 and the clearance period is 14 days, those earnings become available on January 29.

### Tips

Tips from buyers are tracked separately. They are:
- 100% commission-free (the vendor keeps everything)
- Available immediately (no clearance period)
- Shown as a separate line in the dashboard

## Earnings History

Below the summary, vendors see a detailed transaction history showing every completed order and their earnings from it:

- **Order number** and **service name**
- **Order total** (what the buyer paid)
- **Vendor earnings** (after platform commission)
- **Commission rate** applied and **platform fee** deducted
- **Date** the order was completed

Vendors can filter their history by date range and service, making it easy to track income over specific periods.

## Earnings by Period

Vendors can view their earnings grouped by:

- **Daily** -- last 30 days
- **Weekly** -- last 12 weeks
- **Monthly** -- last 12 months
- **Yearly** -- all-time

Each period shows the number of orders completed, total earnings, and average per order. This helps vendors spot income trends and plan ahead.

## Requesting a Withdrawal

From the earnings dashboard, vendors can click **Request Withdrawal** to start a payout. They will need to:

1. Enter the amount they want to withdraw (must meet the minimum threshold)
2. Select a payment method (bank transfer or PayPal)
3. Provide payment details
4. Submit the request for admin review

See [Withdrawals](withdrawals.md) for the full withdrawal process.

## Clearance Period

The clearance period is set by the marketplace admin. The default is 14 days, but it can be adjusted from 0 to 90 days in **Settings > Payments > Payout Settings**.

A clearance period protects both buyers and the platform by allowing time for:
- Buyers to report quality issues after delivery
- Disputes to be filed and resolved
- Chargebacks to be processed

## Related Docs

- [Commission System](commission-system.md) -- How earnings are calculated
- [Withdrawals](withdrawals.md) -- How vendors request payouts
- [Automated Payouts](automated-payouts.md) -- Automatic scheduled payouts
- [Wallet System](wallet-system.md) **[PRO]** -- External wallet integrations
