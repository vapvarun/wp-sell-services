# Running Your Marketplace

This guide covers everything you need to manage your service marketplace - from initial setup to daily operations.

## Your Dashboard

When you open **WP Sell Services** in WordPress, you see an overview of your marketplace:

### At a Glance

Four cards show your marketplace health:
- **Total Orders** - All orders ever placed
- **In Progress** - Orders currently being worked on
- **Completed** - Successfully finished orders
- **Revenue** - Your platform earnings (commissions)

### Quick Actions

Buttons for common tasks:
- **Add Service** - Create a service yourself
- **View Orders** - See all orders
- **Manage Services** - Browse service listings
- **Settings** - Configure your marketplace

### Recent Activity

A table showing your latest orders so you can quickly jump to anything that needs attention.

## Settings Overview

Go to **WP Sell Services → Settings** to configure your marketplace. Settings are organized into tabs:

| Tab | What It Controls |
|-----|-----------------|
| General | Platform name, payment system, currency |
| Pages | Where key marketplace pages live |
| Commission | How much you earn from each sale |
| Tax | Tax calculations (if applicable) |
| Payouts | How sellers get paid |
| Vendor | Seller registration and capabilities |
| Orders | Order workflow and deadlines |
| Notifications | Email alerts |
| Advanced | Moderation, reviews, file uploads |

## Basic Configuration

### Your Platform Identity

In **Settings → General**, set up the basics:

| Setting | What to Enter |
|---------|--------------|
| Platform Name | Your marketplace name (shown in emails) |
| Platform Logo | Logo for emails and branding |
| Support Email | Where users can reach you |

### Choosing a Payment System

Also in General settings, pick how orders get processed:

| Option | Best For |
|--------|----------|
| WooCommerce | Most sites - uses existing payment gateways |
| Standalone (Pro) | Sites without WooCommerce |
| Easy Digital Downloads (Pro) | Sites already using EDD |
| Fluent Cart (Pro) | Lightweight checkout |
| SureCart (Pro) | Modern commerce platform |

**WooCommerce is recommended** because it supports dozens of payment gateways and handles taxes automatically.

### Currency

Set your default currency and how prices appear:
- Currency (USD, EUR, GBP, etc.)
- Symbol position (before or after the amount)
- Decimal separator (period or comma)

## Setting Your Commission

Go to **Settings → Commission** to decide how much you earn.

### The Basics

| Setting | What It Does |
|---------|-------------|
| Commission Rate | Your percentage of each sale |
| Commission Type | Percentage or flat fee per order |
| Minimum Commission | The least you'll take from any order |
| Maximum Commission | The most you'll take (optional) |

### Example

With 20% commission:
- $100 order → You keep $20, seller gets $80
- $500 order → You keep $100, seller gets $400

Most marketplaces charge between 10% and 30%.

### Per-Seller Commission (Pro)

You can set different rates for individual sellers:
1. Go to **WP Sell Services → Vendors**
2. Click on a seller
3. Set their custom commission rate

Use this to reward top performers or offer promotional rates.

## Managing Pages

Go to **Settings → Pages** to tell the plugin where key pages are.

The plugin creates these automatically during setup:

| Page | What It's For |
|------|--------------|
| Services | Browse all services |
| Become a Vendor | Seller registration |
| Dashboard | User account area |
| Buyer Requests | Where buyers post jobs |

If any are missing, create a new WordPress page and add the matching shortcode.

## Seller Settings

Go to **Settings → Vendor** to control how sellers work on your platform.

### Registration Options

| Setting | What It Does |
|---------|-------------|
| Anyone Can Register | Open registration vs. invitation only |
| Auto-Approve Sellers | Skip manual approval for new sellers |
| Require Email Verification | Verify email before approval |

### What Sellers Can Do

| Setting | Default |
|---------|---------|
| Edit Published Services | Yes |
| Delete Their Services | Yes |
| View Their Analytics | Yes |

### Service Limits

Control what sellers can add to each service:

| Feature | Free Version | Pro Version |
|---------|-------------|-------------|
| Pricing Packages | 3 | Unlimited |
| Gallery Images | 5 | Unlimited |
| Videos | 1 | Unlimited |
| FAQs | 5 | Unlimited |
| Extras | 3 | Unlimited |

## Order Settings

Go to **Settings → Orders** to configure how orders work.

### Key Options

| Setting | What It Does | Recommended |
|---------|-------------|-------------|
| Auto-Complete Days | Days after delivery before automatic acceptance | 3 days |
| Revision Limit | Max changes per order | 3 |
| Dispute Window | Days to open a dispute after delivery | 7 days |

### Order Statuses

Orders move through these stages:

1. **Pending Payment** - Waiting for checkout
2. **Pending Requirements** - Waiting for buyer's project brief
3. **In Progress** - Seller is working
4. **Delivered** - Work submitted for review
5. **Revision Requested** - Buyer asked for changes
6. **Completed** - Buyer accepted
7. **Disputed** - Problem escalated to you
8. **Cancelled** - Order cancelled

## Payout Settings

Go to **Settings → Payouts** to control how sellers get paid.

### Clearance Period

How long funds are held before sellers can withdraw.

| Setting | Recommended | Why |
|---------|-------------|-----|
| Clearance Days | 7-14 days | Protects against chargebacks and disputes |

### Withdrawal Limits

| Setting | Recommended |
|---------|-------------|
| Minimum Withdrawal | $50 |
| Maximum Withdrawal | $5000 or unlimited |

### Automatic Payouts (Pro)

You can enable automatic withdrawals:
- Set a threshold amount
- Choose a schedule (weekly, bi-weekly, monthly)
- Sellers get paid automatically when their balance reaches the threshold

## Approving Services

### When Moderation Is On

If you've enabled service moderation, new listings need your approval.

Go to **WP Sell Services → Services** and filter by **Pending**:

1. Review the service details
2. Check it follows your guidelines
3. Click **Approve** to publish or **Reject** to decline

### Service Moderation Page

For faster moderation, use **WP Sell Services → Moderation**:

- **Pending** tab - Services waiting for review
- **Approved** tab - Published services
- **Rejected** tab - Declined services

Use checkboxes for bulk actions - approve or reject multiple services at once.

## Managing Sellers

Go to **WP Sell Services → Vendors** to see everyone selling on your platform.

### Overview Stats

At the top, you'll see:
- Total sellers
- Active (selling)
- Pending (awaiting approval)
- Suspended
- Rejected

### Filtering and Search

- Search by name or email
- Filter by status
- Sort by date, earnings, or rating

### Seller Details

Click any seller to see:
- Profile information
- Statistics (orders, earnings, rating)
- Recent orders
- Actions (change status, adjust commission)

### Seller Statuses

| Status | What It Means | Effect |
|--------|--------------|--------|
| Active | Approved and selling | Services visible, can receive orders |
| Pending | Waiting for your approval | Can't sell yet |
| Suspended | Temporarily disabled | Services hidden, existing orders continue |
| Rejected | Application declined | Can't sell |

## Managing Orders

Go to **WP Sell Services → Orders** to see all orders.

### Viewing Orders

- Filter by status (In Progress, Completed, Disputed, etc.)
- Search by order ID, buyer, or seller
- Click any order to see full details

### Order Details

Each order shows:
- Order information (service, package, price)
- Requirements the buyer submitted
- Conversation history
- Deliveries
- Status history

### What You Can Do

As admin, you can:
- Change order status
- Add notes
- Process refunds
- Cancel orders
- Extend deadlines

### Creating Orders Manually

For testing or special situations, go to **WP Sell Services → Create Order**:

1. Select a service
2. Choose a package
3. Pick the buyer and seller
4. Set initial status
5. Create

Use this for:
- Testing your order workflow
- Creating orders for offline payments
- Helping customers who had issues

### Order Finances

Each order shows a breakdown:

| Line | What It Is |
|------|-----------|
| Service Price | Package price + any extras |
| Platform Fee | Your commission |
| Seller Earning | What the seller receives |
| Tax | If applicable |

## Processing Payouts

Go to **WP Sell Services → Withdrawals** to handle payout requests.

### Payout Statuses

| Status | What It Means |
|--------|--------------|
| Pending | Waiting for you to process |
| Approved | Marked for payment |
| Completed | Successfully paid |
| Rejected | Declined |

### Processing a Request

1. Review the pending request
2. Check the seller's payment details
3. Send payment through your method (PayPal, bank transfer, etc.)
4. Click **Mark as Completed**

The seller gets notified automatically.

### Rejecting a Request

If you need to decline:
1. Click the request
2. Click **Reject**
3. Enter a reason (sent to the seller)

The seller's balance is restored so they can try again.

## Resolving Disputes

Go to **WP Sell Services → Disputes** when buyers and sellers have problems.

### Dispute Statuses

| Status | What It Means |
|--------|--------------|
| Open | Waiting for your review |
| In Review | You're investigating |
| Resolved | Decision made |
| Closed | Completed |

### Reviewing a Dispute

Each dispute shows:
- The order in question
- Who opened it and why
- Messages from both sides
- Evidence (files, screenshots)

### Making a Decision

Choose a resolution:

| Option | What Happens |
|--------|-------------|
| Favor Buyer - Full Refund | Buyer gets 100% back |
| Favor Buyer - Partial Refund | Buyer gets specified %, seller keeps rest |
| Favor Seller | Seller keeps payment, order completes |
| Mutual Agreement | Custom resolution |
| Cancel Order | Order cancelled, funds returned |

Add notes explaining your decision (both parties see this), then resolve.

## Email Notifications

Go to **Settings → Notifications** to control what emails get sent.

### Buyer Emails

- Order confirmation
- Requirements reminder
- Work delivered
- Order completed

### Seller Emails

- New order received
- Requirements submitted
- Revision requested
- Order completed
- Payout processed

### Admin Emails

- New seller registration
- Dispute opened
- Payout requested

### Customizing Emails

You can set:
- Email header (logo, branding)
- Email footer (links, contact info)
- From name and email address

## Advanced Settings

Go to **Settings → Advanced** for more options.

### Service Moderation

| Setting | What It Does |
|---------|-------------|
| Moderate New Services | Review before publishing |
| Moderate Edits | Review service updates |
| Auto-Approve Trusted | Skip moderation for established sellers |

### Reviews

| Setting | Default |
|---------|---------|
| Allow Reviews | Yes |
| Review Window | 30 days after completion |
| Moderate Reviews | No |
| Allow Responses | Yes |

### File Uploads

| Setting | Default |
|---------|---------|
| Max File Size | 10 MB |
| Allowed Types | Images, PDF, ZIP, etc. |

## Regular Tasks

### Weekly

- [ ] Process pending payouts
- [ ] Review open disputes
- [ ] Approve new seller applications
- [ ] Check for services needing moderation

### Monthly

- [ ] Review your analytics
- [ ] Check error logs
- [ ] Update terms of service if needed
- [ ] Respond to feedback and suggestions

### Quarterly

- [ ] Audit seller accounts
- [ ] Review commission rates
- [ ] Update documentation
- [ ] Test the payment flow

## Troubleshooting

### Orders Not Appearing

- Check WooCommerce is syncing properly
- Verify payment gateways are configured
- Look at error logs

### Emails Not Sending

- Check your email settings
- Consider using an SMTP plugin
- Test with a simple contact form

### Payment Issues

- Verify gateway settings
- Check API credentials
- Enable test mode to debug

### Getting Debug Info

If you need to investigate:

1. Enable WordPress debug mode in wp-config.php:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
```

2. Check logs at `/wp-content/debug.log`

## Next Steps

- **Want more features?** See the [Pro Guide](../pro/README.md)
- **Questions about payments?** Check the Pro guide for Stripe, PayPal, and Razorpay setup
- **Need analytics?** Pro includes detailed reports and charts
