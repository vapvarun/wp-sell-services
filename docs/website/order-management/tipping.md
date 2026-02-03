# Tipping Vendors

Send tips to vendors as a thank-you for excellent work. Tips go 100% to the vendor with no commission deducted.

## What is Tipping?

Tipping allows buyers to send bonus payments to vendors after an order is completed. Think of it as a way to show extra appreciation for exceptional service, quick delivery, or going above and beyond.

**Key Features:**
- Send tips after order completion
- 100% of the tip goes to the vendor (no marketplace commission)
- Tips appear in vendor earnings separately
- Optional tip message
- One tip per order

**Why Tip?**
- Reward exceptional work
- Encourage vendors to go the extra mile
- Show appreciation for fast delivery
- Build strong vendor relationships
- Thank vendors who exceeded expectations

![Tipping interface](../images/frontend-order-tip.png)

## How Tipping Works

### When You Can Tip

Tips can only be sent **after an order is completed**. You'll see the tipping option on:
- Completed order pages
- Order confirmation emails (tip link included)
- Your order history

**Order Status Requirements:**
- Order must have status: **Completed**
- Cannot tip pending, in-progress, or cancelled orders
- One tip per order (cannot tip multiple times)

### Tip Amounts

You decide how much to tip:

| Typical Tip | When to Use |
|-------------|-------------|
| 5-10% of order value | Good service, met expectations |
| 15-20% of order value | Excellent work, exceeded expectations |
| 25%+ of order value | Outstanding service, went above and beyond |
| Custom amount | Any amount you feel is appropriate |

**Minimum Tip:** Typically $1.00 (configurable by admin)
**Maximum Tip:** No limit (send as much as you want)

## Sending a Tip (Buyers)

### Step-by-Step Process

1. **Go to Completed Order:**
   - Navigate to **My Account → Orders**
   - Click on a completed order
   - Or use the tip link from your order confirmation email

2. **Find the Tip Section:**
   - Scroll to the bottom of the order details
   - Look for "Tip Your Vendor" or "Show Appreciation" section

3. **Enter Tip Amount:**
   - Type your tip amount in the input field
   - Currency matches your order currency
   - Example: Enter "25.00" for $25.00

4. **Add Message (Optional):**
   - Click "Add Message" (if available)
   - Write a short thank-you note
   - Example: "Thank you for the amazing work and quick turnaround!"

5. **Send Tip:**
   - Click **Send Tip** button
   - Payment processed through original payment method
   - Confirmation message appears

6. **Confirmation:**
   - Email confirmation sent to you
   - Vendor notified of tip received
   - Tip added to vendor's earnings

![Send tip form](../images/frontend-send-tip-form.png)

### Tip with Message Example

```
Tip Amount: $50.00

Message: "Your work was absolutely fantastic! The website
you designed exceeded all my expectations. The fast delivery
and excellent communication made this a great experience.
Thank you!"
```

## Vendor Perspective

### Receiving Tips

Vendors are notified when they receive tips:

**Notification Includes:**
- Tip amount
- Buyer's message (if provided)
- Order reference
- Date received

**Where Tips Appear:**
- **Dashboard → Earnings:** Tips section shows total tips
- **Wallet Balance:** Tips added immediately (no holding period)
- **Earnings Report:** Separate "Tips" row in earnings breakdown

![Vendor tips dashboard](../images/admin-vendor-tips.png)

### Tip vs. Order Earnings

| Type | Commission | When Released | Includes |
|------|------------|---------------|----------|
| **Order Earnings** | Commission deducted | After order completion | Service price, add-ons |
| **Tips** | No commission | Immediately | 100% tip amount |

**Example:**
```
Order Total: $500.00
Commission (10%): $50.00
Vendor Earnings: $450.00

Tip Received: $75.00
Commission on Tip: $0.00
Vendor Receives: $75.00

Total Vendor Payment: $525.00
```

### Viewing Tips Received

Access your tips from: **Vendor Dashboard → Earnings → Tips**

**Tips Table Shows:**
- Date received
- Order number (linked)
- Buyer name
- Tip amount
- Buyer's message
- Total tips earned (all time)

**Filtering Tips:**
- By date range
- By buyer
- By service
- Export to CSV

![Tips history table](../images/admin-tips-history.png)

### Thanking Tippers

When you receive a tip:

1. **Send Thank-You Message:**
   - Use order messaging system
   - Example: "Thank you so much for the generous tip! I'm glad you're happy with the work. Looking forward to working with you again!"

2. **Save Buyer as Favorite:**
   - Mark buyer as preferred customer
   - Prioritize their future orders
   - Offer them repeat customer discounts

3. **Mention in Review Response:**
   - If buyer leaves a review, mention appreciation for tip in your response

## Tips and Commissions

### No Commission on Tips

Marketplace commission is **never** deducted from tips:

**Regular Order:**
```
Service Price: $300
Add-ons: $50
Order Total: $350
Commission (10%): $35
Vendor Receives: $315
```

**With Tip:**
```
Order Earnings: $315 (after commission)
Tip Received: $60
Commission on Tip: $0
Vendor Total: $375
```

### Why No Commission?

Tips are:
- Gifts from buyers to vendors
- Recognition of exceptional work
- Not part of the service price
- Buyer-initiated bonus payments

The marketplace facilitates the tip but doesn't take a cut.

## Tips and Withdrawals

### Accessing Tip Money

Tips are added to your wallet balance immediately:

1. **Instant Credit:**
   - Tips credited as soon as buyer sends them
   - No waiting period
   - Available for immediate withdrawal

2. **Withdraw Tips:**
   - Go to **Dashboard → Earnings → Withdraw**
   - Select withdrawal method (PayPal, bank transfer, etc.)
   - Minimum withdrawal amount applies (typically $50)
   - Tips combined with order earnings for withdrawal

3. **Separate Tracking:**
   - Tips tracked separately in earnings report
   - Shows "Tips" vs "Order Earnings"
   - Tax reporting may differ (consult tax advisor)

![Withdrawal with tips](../images/admin-withdraw-with-tips.png)

## Tips vs. Reviews

Tips and reviews are **separate and independent**:

| Action | When | Required? | Impact |
|--------|------|-----------|--------|
| **Leaving Review** | After completion | Optional | Affects vendor rating |
| **Sending Tip** | After completion | Optional | Affects vendor earnings |

**Both are appreciated but separate:**
- You can tip without reviewing
- You can review without tipping
- You can do both
- You can do neither

**Best Practice:** Leave a review to help other buyers, tip if service was exceptional.

## Admin: Managing Tips

### Viewing All Tips

Access from: **WP Sell Services → Tips** or **Analytics → Tips Report**

**Admin Tips Dashboard:**
- Total tips processed (all time)
- Tips this month
- Average tip amount
- Top tipped vendors
- Recent tips activity

![Admin tips overview](../images/admin-tips-overview.png)

### Tips by Vendor

**View individual vendor tips:**
1. Go to **Vendors → [Vendor Name]**
2. Click **Earnings** tab
3. View **Tips** section

**Shows:**
- Total tips received
- Number of tips
- Average tip per order
- Tip percentage of total earnings

### Tips in Order Details

When viewing an order, admins see:
- Whether order has been tipped
- Tip amount
- Tip date
- Tipper's message

**Order Timeline Shows:**
```
Jan 7, 2025 10:00 AM - Order completed
Jan 7, 2025 3:45 PM - Tip received: $35.00
Message: "Great work, thank you!"
```

### Tips Settings

Configure tipping options: **Settings → Orders → Tipping**

**Available Settings:**

| Setting | Options | Default |
|---------|---------|---------|
| Enable Tipping | Yes/No | Yes |
| Minimum Tip | Amount | $1.00 |
| Suggested Tips | Percentages (comma-separated) | 10%, 15%, 20% |
| Allow Custom Tips | Yes/No | Yes |
| Tip Message | Required/Optional/Hidden | Optional |
| Tip Button Text | Custom text | "Send Tip" |

![Tips settings](../images/admin-tips-settings.png)

### Tips and Analytics

Tips included in analytics reports:

**Dashboard Analytics:**
- Tips contribute to "Total Revenue" graph
- Separate "Tips Revenue" metric
- Tips per order average
- Tipping rate (% of completed orders that receive tips)

**Vendor Performance:**
- Tips received as vendor quality indicator
- Vendors with high tip rates highlighted
- Tip amount correlated with ratings

**Export Reports:**
- Include tips in earnings exports
- Separate column for tip amounts
- Filter reports by tips only

## Best Practices

### For Buyers

**When to Tip:**
- ✅ Vendor delivered early
- ✅ Work exceeded expectations
- ✅ Excellent communication
- ✅ Vendor solved unexpected problems
- ✅ You're extremely satisfied
- ✅ Vendor went above and beyond

**When NOT to Tip:**
- ❌ Work met basic expectations (review instead)
- ❌ You're already paying premium price
- ❌ Order had issues (even if resolved)
- ❌ You feel pressured

**Tip Amount Guidelines:**
- Small orders ($50-$100): $5-$10 tip
- Medium orders ($100-$500): $10-$50 tip
- Large orders ($500+): $50-$100+ tip
- Outstanding work: 20-30% of order value

**Include a Message:**
- Explain why you're tipping
- Mention specific things you appreciated
- Encourages vendor to continue excellent service

### For Vendors

**Don't Ask for Tips:**
- ❌ Don't request tips in deliverables
- ❌ Don't mention tips in order messages
- ❌ Don't make tips feel expected
- ✅ Let quality work speak for itself

**Earn Tips Naturally:**
- ✅ Exceed delivery expectations
- ✅ Communicate proactively
- ✅ Add extra value when possible
- ✅ Deliver ahead of schedule
- ✅ Be friendly and professional

**When You Receive Tips:**
- Always thank the buyer personally
- Acknowledge their message
- Maintain the same quality for their future orders
- Don't assume future tips

### For Admins

**Encourage Tipping Culture:**
- Add tipping info to buyer onboarding
- Show tip prompts after positive reviews
- Highlight tipping benefits in help docs
- Feature highly-tipped vendors (with permission)

**Monitor for Issues:**
- Watch for vendors soliciting tips
- Check for tip refund requests
- Monitor tip-to-order ratio
- Address buyer complaints about tip pressure

**Promote Fairly:**
- Don't require tipping
- Make it easy but optional
- Ensure 100% goes to vendor
- Communicate no-commission clearly

## Frequently Asked Questions

### Can I get my tip refunded?

Tips are generally non-refundable as they're voluntary payments made after order completion. However, if you accidentally sent the wrong amount, contact support immediately.

### Are tips taxable?

**For Vendors:** Tips are income and may be taxable. Consult your tax advisor. The platform reports tips separately in earnings statements.

**For Buyers:** Tips are not tax-deductible (unless business expense).

### Can I tip before the order is completed?

No. Tips can only be sent after an order reaches "Completed" status. This ensures you're tipping based on the final delivered work.

### What if I already left a review? Can I still tip?

Yes! Reviews and tips are independent. You can tip at any time after the order is completed, even if you already left a review.

### Can I tip multiple times on the same order?

No. Only one tip per order is allowed. If you want to send more appreciation, consider booking another service from the vendor.

### Does the vendor see my tip message?

Yes, if you include a message with your tip, the vendor sees it in their notification and earnings dashboard.

### What payment methods can I use for tips?

Tips are processed through the same payment method you used for the order (credit card, PayPal, etc.). A separate payment authorization may be required.

### Can vendors decline tips?

Technically yes, but most vendors appreciate tips. If a vendor wants to decline, they would need to contact the admin to process a refund.

### How quickly do vendors receive tips?

Tips are credited to the vendor's wallet balance immediately. They can withdraw according to the normal withdrawal schedule (no holding period for tips).

### Are tips shown publicly?

No. Tip amounts and messages are private between you, the vendor, and marketplace admins. They don't appear on public profiles or reviews.

### Can I see how many people have tipped a vendor?

No. Tipping history is private. You can only see your own tips sent and (if you're a vendor) tips you've received.

### What if I accidentally tipped the wrong amount?

Contact support immediately. If the tip hasn't been withdrawn by the vendor yet, it may be possible to adjust or refund it.

## Troubleshooting

### "Tip" Button Not Showing

**Possible Causes:**
- Order not yet completed
- Tipping disabled by admin
- Already tipped this order
- Order too old (if time limit set)

**Solution:** Check order status. If completed and still no button, contact support.

### Tip Payment Failed

**Possible Causes:**
- Payment method expired
- Insufficient funds
- Payment gateway issue

**Solution:**
- Update payment method
- Try different payment method
- Wait a few minutes and retry
- Contact support if issue persists

### Vendor Says They Didn't Receive Tip

**Check:**
1. Confirm you received payment confirmation
2. Check your bank/card statement
3. Ask vendor to check **Dashboard → Earnings → Tips**
4. Contact support with order number and timestamp

**Timeline:**
- Tips should appear in vendor dashboard within minutes
- Email notification sent to vendor immediately

### Tip Amount Doesn't Match

If the vendor sees a different amount than you sent:
- Check currency conversion (if using different currencies)
- Verify no additional fees added by payment processor
- Contact support with screenshots

## Related Topics

- **[Order Workflow](order-workflow.md)** - Understanding order statuses and completion
- **[Managing Orders](managing-orders.md)** - Buyer and vendor order management
- **[Reviews & Ratings](../buyer-guide/reviews-ratings.md)** - Leaving reviews for vendors
- **[Earnings & Payouts](../vendor-guide/earnings-payouts.md)** - How vendors access their earnings

Tips are a great way to show appreciation and build strong vendor relationships!
