# Notification Center

Stay updated with real-time in-app notifications for orders, messages, deliveries, and marketplace activity. Never miss an important update.

## What is the Notification Center?

The notification center is your hub for all marketplace activity. It shows you important updates in real-time without relying solely on email.

**What You'll See:**
- New orders received
- Order status changes
- New messages
- Deliveries submitted
- Reviews received
- Dispute updates
- Deadline warnings
- Account activity

**Where to Find It:**
- Bell icon (🔔) in site header
- Dashboard notification panel
- WooCommerce My Account → Notifications

![Notification center](../images/frontend-notification-center.png)

## Types of Notifications

The system sends **11 different notification types**. Here's what each means:

### 1. Order Created

**Who Receives:** Vendor

**When:** New order placed

**What It Says:**
```
New Order Received

Great news! [Buyer Name] has placed an order for your service.

Order Details:
Service: [Service Name]
Order Number: #WPSS-202501-1234
Amount: $150.00

The buyer will submit their requirements shortly.
```

**What to Do:**
- Prepare to accept order
- Wait for buyer requirements
- Check your availability

![Order created notification](../images/frontend-notification-order-created.png)

---

### 2. Order Status Changed

**Who Receives:** Vendor and Buyer

**When:** Order moves to new status

**Examples:**

**To Buyer (Order Started):**
```
Your Order is In Progress

[Vendor Name] has received your requirements and started working
on Order #WPSS-202501-1234.

Service: Logo Design

You'll be notified when the delivery is ready for review.
```

**To Vendor (Requirements Submitted):**
```
Order Ready to Start

[Buyer Name] has submitted requirements for Order #WPSS-202501-1234.

You can now start working on this order.
```

**What to Do:**
- Check new status
- Take appropriate action
- Review order details

---

### 3. New Message

**Who Receives:** Message recipient

**When:** Someone sends you a message

**What It Says:**
```
New Message Received

You have received a new message from [Sender Name].

Order: #WPSS-202501-1234
Service: Website Development

Message:
"Hi! I have a question about the color scheme we discussed..."

Log in to your dashboard to view the full conversation and reply.
```

**What to Do:**
- Read message
- Reply within 24 hours
- Address questions/concerns

![New message notification](../images/frontend-notification-message.png)

---

### 4. Delivery Submitted

**Who Receives:** Buyer

**When:** Vendor uploads final delivery

**What It Says:**
```
Delivery Ready for Review

[Vendor Name] has submitted the delivery for Order #WPSS-202501-1234.

Service: Brand Identity Package

Please review the delivery and either accept it to complete the order,
or request a revision if changes are needed.
```

**What to Do:**
- Download deliverable files
- Review work thoroughly
- Approve or request revision

---

### 5. Delivery Accepted

**Who Receives:** Vendor and Buyer

**To Buyer:**
```
Order Completed

Order #WPSS-202501-1234 has been completed successfully!

Service: Website Development
Seller: [Vendor Name]

Thank you for your business. Please consider leaving a review.
```

**To Vendor:**
```
Order Completed - Payment Released

Congratulations! [Buyer Name] has accepted the delivery
for Order #WPSS-202501-1234.

The payment has been released to your account.
```

**What to Do:**
- **Buyer:** Leave a review
- **Vendor:** Thank buyer, download files for portfolio

![Delivery accepted](../images/frontend-notification-completed.png)

---

### 6. Revision Requested

**Who Receives:** Vendor

**When:** Buyer requests changes

**What It Says:**
```
Revision Requested

[Buyer Name] has requested a revision for Order #WPSS-202501-1234.

Service: Logo Design

Please review their feedback and submit an updated delivery.
```

**What to Do:**
- Read revision feedback
- Make requested changes
- Resubmit delivery

---

### 7. Review Received

**Who Receives:** Vendor

**When:** Buyer leaves a review

**What It Says:**
```
New Review Received

[Buyer Name] has left a review for Logo Design Service.

Rating: ★★★★★ (5/5)

Review:
"Excellent work! The vendor was professional, responsive, and
delivered exactly what I needed. Highly recommend!"

Thank you for providing excellent service! Reviews help build
your reputation and attract more customers.
```

**What to Do:**
- Read review
- Thank buyer (reply to review)
- Address any concerns mentioned

![Review notification](../images/frontend-notification-review.png)

---

### 8. Dispute Opened

**Who Receives:** Buyer and Vendor

**When:** Someone opens a dispute

**What It Says:**
```
Dispute Opened

A dispute has been opened for Order #WPSS-202501-1234.

Service: Website Development

Our support team will review the case and get back to you soon.
Please prepare any relevant information.
```

**What to Do:**
- Review dispute details
- Gather evidence
- Respond to admin inquiries
- Cooperate with resolution

---

### 9. Dispute Resolved

**Who Receives:** Buyer and Vendor

**When:** Admin resolves dispute

**What It Says:**
```
Dispute Resolved

The dispute for Order #WPSS-202501-1234 has been resolved
with a partial refund.

Service: Website Development
Refund Amount: $50.00

The refund will be processed according to our refund policy.

If you have questions, please contact support.
```

**What to Do:**
- Review resolution
- Check refund/payment status
- Contact support if questions

---

### 10. Deadline Warning

**Who Receives:** Vendor

**When:** Deadline approaching (24 hours before)

**What It Says:**
```
Order Deadline Approaching

The delivery deadline for Order #WPSS-202501-1234 is in 24 hours.

Due: January 15, 2025 at 11:59 PM

Please ensure you deliver the order on time to maintain
your seller rating.
```

**What to Do:**
- Check work progress
- Submit delivery ASAP
- Request extension if needed

![Deadline warning](../images/frontend-notification-deadline.png)

---

### 11. Vendor Registered

**Who Receives:** New vendor (and admin)

**When:** Vendor account created

**What It Says:**
```
Welcome to the Marketplace!

Congratulations! Your vendor account has been created.
You can now start creating services and accepting orders.

Get Started:
• Create your first service
• Complete your profile
• Set up payment information
• Read our seller guide
```

**What to Do:**
- Complete vendor profile
- Create first service
- Read marketplace policies

---

## Accessing Notifications

### Notification Bell Icon

**Location:** Site header (top right, next to user menu)

**Features:**
- Shows unread count badge
- Click to open dropdown
- Displays 5 most recent notifications
- "View All" link to full list

**Dropdown Preview:**
```
🔔 Notifications (3)

✉️ New Message from John Doe
   Order #WPSS-202501-1234
   2 minutes ago

📦 Delivery Submitted
   Order #WPSS-202501-1235
   1 hour ago

⭐ New Review Received
   Logo Design Service
   3 hours ago

[View All Notifications]
```

![Notification bell dropdown](../images/frontend-notification-bell.png)

### Full Notifications Page

**Access:** Dashboard → Notifications (or click "View All" in bell dropdown)

**Shows:**
- All notifications (paginated)
- Filter by type
- Filter by read/unread
- Search notifications
- Mark as read options
- Delete notifications

**Layout:**
```
Notifications

Filters: [All Types ▼] [Unread Only ☐] [Search...]

Today

✉️ New Message from Sarah Wilson
   Order #WPSS-202501-1250
   10 minutes ago  [Mark Read]

📦 Delivery Ready for Review
   Order #WPSS-202501-1248
   1 hour ago  [Mark Read]

Yesterday

⭐ Review Received (5 stars)
   Website Design Service
   Yesterday at 3:45 PM  [Mark Read]

[Load More]
```

![Full notifications page](../images/frontend-notifications-page.png)

### Dashboard Widget

**Location:** Dashboard homepage

**Shows:**
- 3-5 most recent notifications
- Quick actions (View Order, Reply, etc.)
- Unread count
- Link to full notifications

## Managing Notifications

### Mark as Read

**Single Notification:**
1. Click on notification
2. Automatically marked as read
3. Badge count decreases

**Or:**
- Hover over notification
- Click "Mark Read" button

**Multiple Notifications:**
- Check boxes next to notifications
- Click "Mark Selected as Read"

**All Notifications:**
- Click "Mark All as Read" button
- All unread notifications cleared
- Badge resets to 0

![Mark as read](../images/frontend-notification-mark-read.png)

### Deleting Notifications

**Why Delete?**
- Clean up old notifications
- Remove resolved issues
- Reduce clutter

**How to Delete:**
1. Full notifications page
2. Hover over notification
3. Click "Delete" (trash icon)
4. Confirm deletion

**Bulk Delete:**
- Check multiple notifications
- Click "Delete Selected"
- Confirm action

**Auto-Cleanup:**
- Notifications older than 90 days auto-deleted (configurable)
- Keeps notification center manageable

### Filtering Notifications

**By Type:**
- Orders
- Messages
- Deliveries
- Reviews
- Disputes
- System notifications

**By Status:**
- Unread only
- Read only
- All notifications

**By Date:**
- Today
- Last 7 days
- Last 30 days
- Custom date range

**Search:**
- Search by keyword
- Order number
- Sender name
- Service name

![Notification filters](../images/frontend-notification-filters.png)

## Email Notifications

### In-App vs Email

Both work together:

| Feature | In-App | Email |
|---------|--------|-------|
| **Real-time** | Yes | Slight delay |
| **Action Links** | Yes | Yes |
| **Archiving** | Manual | Automatic (email client) |
| **Filtering** | Yes | Yes (email rules) |
| **Mobile** | Dashboard access | Email app |
| **Offline** | No | Yes (cached emails) |

**Best Practice:** Use both. Check dashboard for real-time updates, rely on email when away.

### Email Notification Contents

Email notifications include:

**Header:**
- Marketplace logo
- Notification type

**Body:**
- Same content as in-app notification
- Formatted for email readability
- Action button (View Order, Reply, etc.)

**Footer:**
- Link to dashboard
- Unsubscribe options
- Support contact info

![Email notification](../images/email-notification-example.png)

### Email Preferences

Configure which notifications send emails:

**Access:** Dashboard → Settings → Notifications

**Options:**

| Notification Type | Email? | Default |
|-------------------|--------|---------|
| New Orders | ☑️ | Yes |
| Order Status Changes | ☑️ | Yes |
| New Messages | ☑️ | Yes |
| Deliveries | ☑️ | Yes |
| Reviews | ☑️ | Yes |
| Disputes | ☑️ | Yes |
| Deadline Warnings | ☑️ | Yes |
| System Updates | ☐ | No |

**Email Frequency:**
- Instant (as they happen)
- Digest (once daily summary)
- Off (in-app only)

**Important:** Some critical notifications (disputes, payment issues) always send emails regardless of settings.

![Email preferences](../images/frontend-notification-preferences.png)

## Notifications for Different Roles

### Buyers

**Key Notifications to Watch:**

**High Priority:**
- ✅ Delivery Ready for Review (action required)
- ✅ Order Status Changed (track progress)
- ✅ New Message from Vendor (respond promptly)
- ✅ Dispute Updates (resolution status)

**Medium Priority:**
- ⚡ Order Confirmed (confirmation)
- ⚡ Order Started (tracking)
- ⚡ Deadline Approaching (awareness)

**Low Priority:**
- 💬 System updates
- 💬 Marketplace news

**Buyer Notification Flow:**
```
1. Order Confirmation → Order placed successfully
2. Order Started → Vendor began work
3. Delivery Submitted → Review deliverable
4. Delivery Approved → Leave review
5. Order Completed → Thank you
```

### Vendors

**Key Notifications to Watch:**

**High Priority:**
- ✅ New Order Received (start process)
- ✅ Revision Requested (update delivery)
- ✅ New Message (customer inquiry)
- ✅ Review Received (track reputation)
- ✅ Deadline Warning (submit on time)

**Medium Priority:**
- ⚡ Requirements Submitted (start work)
- ⚡ Delivery Approved (payment released)
- ⚡ Dispute Opened (take action)

**Low Priority:**
- 💬 Vendor tips received
- 💬 Profile views

**Vendor Notification Flow:**
```
1. New Order → Review details
2. Requirements Submitted → Start work
3. Deadline Warning (optional) → Deliver soon
4. Delivery Approved → Payment received
5. Review Received → Read and respond
```

### Admins

**Admin-Specific Notifications:**

**Critical:**
- 🚨 New Dispute Opened
- 🚨 Payment Issue
- 🚨 Vendor Verification Needed
- 🚨 Refund Request

**Important:**
- ⚠️ New Vendor Registered
- ⚠️ Withdrawal Request
- ⚠️ Order Stuck (no action)
- ⚠️ Support Ticket

**Info:**
- ℹ️ Daily summary
- ℹ️ Weekly reports
- ℹ️ New service published

**Admin Dashboard:**
- Separate notification feed
- Admin-only notifications
- Moderation queue
- System alerts

![Admin notifications](../images/admin-notification-center.png)

## Notification Preferences

### Customizing Notifications

**Access:** Dashboard → Account Settings → Notifications

### What You Can Control

**Notification Channels:**
- ☑️ In-app notifications
- ☑️ Email notifications
- ☑️ SMS notifications (if enabled)
- ☐ Push notifications (mobile app)

**Notification Types:**
- Select which types to receive
- Different settings for each channel
- Granular control

**Examples:**

**Vendor Who Checks Dashboard Often:**
```
In-App: All notifications enabled
Email: Only critical (disputes, deadlines)
SMS: Disabled
```

**Busy Buyer:**
```
In-App: All enabled
Email: All enabled (digest mode)
SMS: Critical only
```

**Part-Time Vendor:**
```
In-App: All enabled
Email: All enabled (instant)
SMS: New orders, deadlines
```

### Quiet Hours

**Set "Do Not Disturb" times:**

**Configure:**
- Start time: 10:00 PM
- End time: 8:00 AM
- Time zone: Auto-detect

**During Quiet Hours:**
- In-app notifications still delivered
- Emails queued until quiet hours end
- SMS disabled
- Push notifications disabled

**Exceptions:**
- Critical alerts (disputes, payment issues)
- Deadline in next 2 hours
- Buyer-marked urgent messages

![Notification quiet hours](../images/frontend-notification-quiet-hours.png)

### Notification Grouping

**Reduce notification fatigue:**

**Grouped Notifications:**
```
Instead of:
• New message from John
• New message from John
• New message from John

Shows:
• 3 new messages from John (Order #1234)
```

**Grouping Rules:**
- Same type + same order = grouped
- Time window: 1 hour
- Click to expand all

**Enable/Disable:** Settings → Notifications → Group similar notifications

## Troubleshooting

### Not Receiving Notifications

**Check In-App:**
1. Go to Dashboard → Notifications
2. See if notifications appearing there?
3. If yes, issue is with bell icon display
4. If no, notifications not being created

**Check Email:**
1. Check spam/junk folder
2. Verify email preferences enabled
3. Check email address on account
4. Test with "Send Test Notification" button

**Common Causes:**
- Email preferences disabled
- Email address incorrect
- Email client blocking
- Quiet hours active
- Browser notifications blocked

**Fix:**
- Update email preferences
- Whitelist marketplace email domain
- Check browser notification permissions
- Disable quiet hours temporarily

### Too Many Notifications

**Feeling Overwhelmed?**

**Solutions:**

1. **Disable Low-Priority Notifications:**
   - Keep: Orders, deliveries, disputes
   - Disable: System updates, marketing

2. **Switch to Digest Mode:**
   - Settings → Email Frequency → Daily Digest
   - One email per day with all notifications

3. **Use Filters:**
   - Dashboard shows only unread
   - Archive old notifications

4. **Set Quiet Hours:**
   - No notifications during sleep/work hours

5. **Enable Grouping:**
   - Combine similar notifications

### Notification Badge Not Updating

**Bell icon shows wrong count:**

**Quick Fix:**
1. Refresh page
2. Clear browser cache
3. Log out and log back in

**Still Wrong?**
- May be cached count
- Check actual notifications page
- Contact support if persists

### Missing Order Notification

**Expected notification didn't arrive:**

**Check:**
1. Notification preferences (enabled for that type?)
2. Quiet hours (active during notification time?)
3. Email spam folder
4. Dashboard notifications page (may be there but not emailed)

**Report:**
- If truly missing, contact support
- Provide order number and expected notification type

### Can't Delete Notifications

**Delete button not working:**

**Possible Reasons:**
- Browser JavaScript error
- Slow connection
- Already deleted (refresh page)

**Try:**
- Refresh page
- Try different browser
- Use bulk delete
- Contact support

## Best Practices

### For All Users

**Daily Routine:**
- ✅ Check notifications each morning
- ✅ Respond to messages within 24 hours
- ✅ Mark notifications as read after acting
- ✅ Clean up old notifications weekly

**Don't:**
- ❌ Ignore deadline warnings
- ❌ Let notifications pile up
- ❌ Disable critical notification types
- ❌ Rely solely on email (check dashboard too)

### For Vendors

**Stay Responsive:**
- Check notifications 2-3 times daily
- Respond to buyer messages quickly
- Act on delivery requests within hours
- Don't miss deadline warnings

**Notification Priority:**
1. Disputes (handle immediately)
2. New orders (respond within 1 hour)
3. Messages (respond within 2 hours)
4. Deadline warnings (deliver ASAP)
5. Reviews (read and respond daily)

### For Buyers

**Track Your Orders:**
- Enable all order-related notifications
- Check when delivery submitted
- Respond to vendor questions quickly
- Approve deliveries within 2-3 days

**Communication:**
- Reply to vendor messages
- Be available during order execution
- Provide feedback promptly

### For Admins

**Monitor Critical Issues:**
- Check dispute notifications hourly
- Review payment issues immediately
- Respond to escalated tickets fast
- Monitor stuck orders

**Set Up Filters:**
- Critical notifications → email + SMS
- Routine notifications → dashboard only
- Reports → weekly digest email

## Notification Statistics

### Your Notification Activity

**View Stats:** Dashboard → Notifications → Statistics

**Metrics:**
- Notifications received (last 30 days)
- Average response time
- Unread notifications
- Most common notification type
- Peak notification times

**Use Insights:**
- Identify busy times
- Improve response times
- Adjust notification settings
- Track engagement

![Notification statistics](../images/frontend-notification-stats.png)

## Related Topics

- **[Order Messaging](order-messaging.md)** - Communicating during orders
- **[Managing Orders](managing-orders.md)** - Order management for buyers and vendors
- **[Deliveries & Revisions](deliveries-revisions.md)** - Delivery notification workflow
- **[Email Notifications](../admin-settings/email-notifications.md)** - Admin email settings

Never miss an important marketplace update with the notification center!
