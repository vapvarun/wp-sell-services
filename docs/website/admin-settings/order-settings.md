# Order Settings

Configure order policies, delivery deadlines, revisions, and cancellation rules that govern transactions on your marketplace.

## Default Delivery Deadline

Set the standard delivery timeframe for service orders.

![Order Settings Tab](../images/settings-orders-tab.png)

## Delivery Time Configuration

### Default Days to Deliver

Set how many days vendors have to complete orders by default.

**Configuration:**
1. Go to **WP Sell Services → Settings → Orders**
2. Enter **Default Delivery Deadline** in days
3. Common values: 3, 7, 14, or 30 days
4. Click **Save Changes**

**How It Works:**
- Applies when vendor doesn't specify custom delivery time
- Countdown starts when order is placed
- Vendors see deadline in order details
- Buyers see expected delivery date
- Overdue orders are flagged in dashboard

**Example:**
- Default: 7 days
- Order placed: January 1
- Expected delivery: January 8
- Vendor must deliver by January 8, 11:59 PM

### Custom Delivery Times

Vendors can override default delivery with package-specific times:

**In Service Creation:**
1. Vendor creates package (Basic, Standard, Premium)
2. Sets custom delivery time per package
3. Basic: 7 days, Standard: 5 days, Premium: 3 days
4. Buyer sees delivery time before purchase

**Fast Delivery Add-on:**
- Vendors can offer "Express Delivery" as paid add-on
- Reduces delivery time (e.g., from 7 days to 3 days)
- Additional charge applied

## Revision Limits

Control how many revisions buyers can request per order.

### Maximum Revisions

Set the default number of revisions included with orders.

**Configuration:**
1. Navigate to **Settings → Orders**
2. Set **Maximum Revisions Per Order**
3. Common values: 1, 2, 3, 5, or unlimited
4. Save changes

**How Revisions Work:**

**Buyer Requests Revision:**
1. Reviews delivery
2. Clicks "Request Revision"
3. Describes changes needed
4. Countdown pauses until new delivery

**Vendor Delivers Revision:**
1. Receives revision request
2. Makes requested changes
3. Uploads revised delivery
4. Revision counter decrements

**Revision Counter:**
- Shows remaining revisions (e.g., "2 of 3 revisions used")
- Updates in real-time
- Both parties see current status

### Unlimited Revisions

**[PRO]** Allow unlimited revisions to maximize buyer satisfaction.

**Enable:**
1. Set **Maximum Revisions** to "Unlimited"
2. Or set to 999 for practical unlimited

**Use Cases:**
- Premium service tiers
- Complex design work
- Satisfaction-guarantee marketplace

### Package-Specific Revisions

Vendors can offer different revision counts per package:

| Package | Price | Revisions |
|---------|-------|-----------|
| Basic | $50 | 1 revision |
| Standard | $100 | 3 revisions |
| Premium | $200 | Unlimited |

Configured during service creation.

## Auto-Completion Settings

Automatically mark orders as complete after a certain period.

### Auto-Complete After Delivery

Set how many days after delivery the order auto-completes if buyer takes no action.

**Configuration:**
1. Go to **Settings → Orders**
2. Set **Auto-Complete After** (days)
3. Recommended: 3-7 days
4. Save changes

**Auto-Completion Process:**

**Timeline:**
1. Vendor delivers work (Day 0)
2. Buyer has X days to review
3. If no action taken, order auto-completes
4. Vendor receives payment
5. Both parties can leave reviews

**Example (3-day auto-complete):**
- Delivery submitted: January 1
- Buyer review period: January 1-3
- Auto-complete: January 4 (if no revision requested)

**Benefits:**
- Prevents orders stuck in limbo
- Ensures timely vendor payment
- Encourages buyer engagement
- Reduces admin intervention

### Buyer Notification

Buyers receive reminders before auto-completion:
- 1 day after delivery: "Please review your delivery"
- 1 day before auto-complete: "Order will complete tomorrow"
- On auto-complete: "Order completed, please leave a review"

## Cancellation Policies

Define rules for order cancellations and dispute resolution.

### Who Can Cancel Orders

**Buyers Can Cancel:**
- Before vendor starts work (if allowed)
- Within cancellation window
- By mutual agreement with vendor

**Vendors Can Cancel:**
- Before accepting order (if acceptance required)
- By mutual agreement with buyer
- For valid reasons (inappropriate request, beyond scope)

**Admins Can Cancel:**
- Any order at any time
- With refund or without refund
- To resolve disputes

### Cancellation Windows

Set timeframes for penalty-free cancellations.

**Configuration:**
1. Navigate to **Settings → Orders → Cancellations**
2. Set **Buyer Cancellation Window** (hours)
3. Common values: 0, 1, 4, 24 hours
4. Save changes

**Example (4-hour window):**
- Order placed: 10:00 AM
- Free cancellation until: 2:00 PM
- After 2:00 PM: Vendor must agree

### Cancellation Fees

**[PRO]** Charge cancellation fees for late cancellations.

**Configuration:**
1. Enable **Cancellation Fees** **[PRO]**
2. Set fee amount or percentage
3. Define when fees apply

**Example Fee Structure:**
- Within 4 hours: Free cancellation
- After 4 hours, before delivery: 10% fee
- After delivery submitted: No cancellation (revision only)

### Refund Processing

When orders are cancelled:

**Full Refund:**
- Within cancellation window
- Mutual agreement
- Vendor hasn't started work

**Partial Refund:**
- Work partially completed
- Negotiated settlement
- Admin discretion

**No Refund:**
- Delivery accepted
- Outside cancellation window
- Buyer fault (no requirements provided)

See [Payment Gateways](../integrations/payment-gateways.md) for refund processing details.

## Late Order Handling

Manage orders that exceed delivery deadline.

### Late Order Flags

Orders automatically flagged when:
- Delivery deadline passes
- No delivery submitted
- No extension requested

**Dashboard Display:**
- Vendor sees "Overdue" badge
- Buyer sees "Delayed" status
- Admin receives notification

### Automatic Actions

Configure what happens with late orders:

**Options:**
1. **Notify Only** - Send reminders, no penalties
2. **Offer Auto-Cancel** - Allow buyer to cancel with refund
3. **Vendor Penalties** - Reduce vendor rating **[PRO]**
4. **Automatic Cancellation** - Cancel after X days late

**Configuration:**
1. Go to **Settings → Orders → Late Orders**
2. Select action
3. Set late threshold (days past deadline)
4. Save changes

### Delivery Extensions

Allow vendors to request deadline extensions.

**Vendor Process:**
1. Opens order
2. Clicks "Request Extension"
3. Explains reason and requests additional days
4. Buyer receives notification

**Buyer Options:**
- Accept extension (deadline updates)
- Reject extension (original deadline remains)
- Counter-offer different extension

**Automatic Extension:**
- If revision requested near deadline
- Deadline extends by original delivery time
- Ensures fair completion time

## Order Notification Preferences

Control who receives notifications for order events.

### Admin Notifications

Enable/disable admin alerts:

- ☑ New order placed
- ☑ Order delivered
- ☑ Revision requested
- ☑ Order completed
- ☑ Order cancelled
- ☑ Dispute opened
- ☐ Order late (optional)

**Configuration:**
1. Navigate to **Settings → Orders → Notifications**
2. Check events to monitor
3. Enter admin email addresses (comma-separated for multiple)
4. Save changes

### Buyer Notifications

Buyers automatically receive emails for:
- Order confirmation
- Delivery submitted
- Revision approved
- Order completed
- Cancellation confirmation

### Vendor Notifications

Vendors automatically receive emails for:
- New order received
- Revision requested
- Order cancelled
- Payment received
- Review left by buyer

Customize email templates in [Email Notifications](email-notifications.md).

## Order Requirements

Manage how buyers provide project details to vendors.

### Requirement Collection

**Options:**
1. **At Checkout** - Buyer provides details before payment
2. **After Purchase** - Buyer submits requirements after payment
3. **Flexible** - Vendor specifies per service

**Configuration:**
1. Go to **Settings → Orders → Requirements**
2. Select collection timing
3. Save changes

**Recommendation:** "After Purchase" provides better conversion rates.

### Required vs Optional Fields

Vendors can mark requirement fields as:
- **Required** - Order can't start without it
- **Optional** - Vendor can proceed without it

**Example Requirements:**
- Brand colors (required)
- Logo files (required)
- Competitor examples (optional)
- Deadline preference (optional)

### Requirement Submission Deadline

Set timeframe for buyers to submit requirements after purchase.

**Configuration:**
1. Set **Requirement Deadline** (days)
2. Recommended: 3-7 days
3. Save changes

**If Buyer Misses Deadline:**
- Vendor can request cancellation
- Order doesn't count against vendor metrics
- Buyer notified multiple times before deadline

## Order Priority Levels (Pro)

**[PRO]** Allow priority order processing for premium buyers.

### Priority Tiers

**Standard Orders:**
- Normal queue position
- Standard delivery time
- Regular support

**Priority Orders:** **[PRO]**
- Queue jumping
- Faster delivery (-20% time)
- Priority support

**Express Orders:** **[PRO]**
- Top priority
- Fastest delivery (-50% time)
- Dedicated support
- Additional fee

**Configuration:**
1. Enable **Priority Orders** **[PRO]**
2. Set priority fees (% or flat)
3. Define delivery time reductions
4. Save changes

## Troubleshooting

### Orders Not Auto-Completing

**Check:**
1. Auto-complete setting is enabled (not 0)
2. Order was actually delivered (not draft)
3. WP Cron is running (test with WP Crontrol plugin)
4. No cron job failures in debug log

### Revisions Not Decrementing

**Verify:**
1. Revision was actually submitted (not draft)
2. Order has revisions remaining
3. Vendor uploaded revision (not just commented)
4. Cache cleared

### Cancellation Not Working

**Troubleshoot:**
1. User has permission to cancel
2. Within cancellation window
3. Order status allows cancellation (not completed)
4. Payment gateway supports refunds

### Late Orders Not Flagged

**Confirm:**
1. Delivery deadline has actually passed
2. Timezone settings correct in WordPress
3. Cron jobs running properly
4. Late order detection enabled

## Related Documentation

- [Vendor Settings](vendor-settings.md) - Service creation rules
- [Payment Settings](payment-settings.md) - Commission and refunds
- [Email Notifications](email-notifications.md) - Order email templates
- [Advanced Settings](advanced-settings.md) - Additional order controls

## Next Steps

After configuring order settings:

1. [Set up email notifications](email-notifications.md)
2. [Configure advanced features](advanced-settings.md)
3. Test order flow with sample transactions
4. Create buyer/vendor guidelines
5. Monitor first real orders closely
