# Managing Orders

Learn how to manage orders from vendor, buyer, and admin perspectives. This guide covers accepting orders, tracking progress, and handling day-to-day order management.

## Order Management Dashboard

### Vendor View

Access orders from: **Vendor Dashboard → Orders**

![Vendor orders dashboard](../images/admin-vendor-orders.png)

**Dashboard Sections:**
- **Active Orders** - Orders in progress
- **Pending Action** - Requiring your response
- **Completed Orders** - Finished orders
- **Cancelled** - Cancelled orders

**Order Card Shows:**
- Order number and status
- Buyer name and avatar
- Service and package purchased
- Order total and your earnings
- Deadline countdown
- Quick actions (View, Message, Deliver)

### Buyer View

Access orders from: **Buyer Dashboard → My Orders**

![Buyer orders dashboard](../images/frontend-buyer-orders.png)

**Buyer Dashboard Shows:**
- Order status and progress
- Vendor information
- Delivery countdown
- Messages and notifications
- Actions (Review Delivery, Request Revision, Message Vendor)

### Admin View

Access all orders from: **WP Sell Services → Orders**

![Admin orders overview](../images/admin-all-orders.png)

**Admin Dashboard Shows:**
- All marketplace orders
- Filters by status, vendor, buyer, date
- Bulk actions
- Revenue statistics
- Commission tracking

## Order Details Page

Click any order to view complete details:

### Order Information Section

| Field | Information |
|-------|-------------|
| **Order Number** | Unique order ID (e.g., WPSS-202501-1234) |
| **Status** | Current order status with color indicator |
| **Service** | Service name (linked) |
| **Package** | Package tier selected |
| **Vendor** | Vendor name and profile link |
| **Buyer** | Buyer name and contact |
| **Order Date** | Date order was placed |
| **Deadline** | Delivery deadline (countdown) |
| **Total Price** | Full order amount |
| **Commission** | Marketplace commission |
| **Vendor Earnings** | Amount vendor receives |

![Order details page](../images/admin-order-details.png)

### Package & Add-ons Section

Shows exactly what was purchased:

**Package Details:**
- Package name (Basic/Standard/Premium)
- Package price
- Delivery days
- Included revisions
- Package description

**Add-ons Purchased:**
- Add-on name
- Quantity (if applicable)
- Price per add-on
- Total add-on cost

**Example:**
```
Standard Package: $250
├─ 7 pages
├─ 5-day delivery
├─ 3 revisions included

Add-ons:
├─ Extra Fast Delivery: +$25
└─ Source Files Included: +$30

Order Total: $305
```

### Requirements Section

Displays buyer's submitted requirements:

- All questions and answers
- Uploaded files (downloadable)
- Submission timestamp
- Edit history (if buyer updated)

![Order requirements display](../images/admin-order-requirements-view.png)

### Timeline/Activity Section

Shows complete order history:

- Status changes with timestamps
- Deliveries submitted
- Revisions requested
- Messages exchanged
- Extensions requested/approved
- Admin actions

**Example Timeline:**
```
Jan 1, 2025 10:30 AM - Order placed
Jan 1, 2025 10:31 AM - Payment confirmed
Jan 1, 2025 11:15 AM - Requirements submitted
Jan 1, 2025 2:00 PM - Order accepted by vendor
Jan 5, 2025 9:00 AM - Delivery submitted
Jan 5, 2025 3:00 PM - Revision requested
Jan 6, 2025 11:00 AM - Delivery resubmitted
Jan 6, 2025 4:00 PM - Delivery approved
Jan 7, 2025 10:00 AM - Review submitted
Jan 7, 2025 10:01 AM - Order completed
```

## Vendor Order Management

### Accepting New Orders

When a new order arrives (if manual acceptance enabled):

1. Notification email received
2. Go to **Vendor Dashboard → Orders → Pending Action**
3. Click **View Order**
4. Review order details:
   - Service and package
   - Buyer requirements
   - Add-ons purchased
   - Delivery deadline
5. Click **Accept Order** or **Decline Order**

![Accept order screen](../images/admin-order-accept-screen.png)

**Accept Order:**
- Confirms you can complete the work
- Starts the delivery countdown
- Order status → `in_progress`
- Buyer notified

**Decline Order:**
- Required to provide reason
- Order cancelled automatically
- Buyer refunded
- Order doesn't count against your statistics

**When to Decline:**
- Requirements are unclear/incomplete
- Out of your expertise
- Impossible deadline
- Already overbooked

### Working on Orders

Once order is `in_progress`:

**Best Practices:**

1. **Start Promptly:**
   - Begin work within 24 hours
   - Shows professionalism
   - Builds buyer confidence

2. **Communicate Progress:**
   - Send update messages every 1-2 days
   - Share work-in-progress screenshots
   - Let buyer know you're on track

3. **Ask Questions Early:**
   - Clarify requirements immediately
   - Don't wait until deadline approaches
   - Use order messaging system

4. **Track Your Time:**
   - Monitor time spent vs. deadline
   - Request extension if needed (early)
   - Don't wait until last minute

5. **Prepare Deliverables:**
   - Organize files clearly
   - Name files descriptively
   - Create README or instructions
   - Test everything before submitting

![Order progress tracking](../images/admin-order-progress.png)

### Submitting Deliveries

When work is complete:

1. Go to **Order Details**
2. Click **Submit Delivery**
3. Upload deliverable files:
   - Maximum 5 files per delivery (configurable)
   - Accepted formats: ZIP, PDF, JPG, PNG, DOC, etc.
   - Max file size: 50MB per file (configurable)
4. Write delivery message:
   - Explain what's included
   - Provide instructions for use
   - Thank the buyer
5. Click **Submit Delivery**

![Delivery submission form](../images/admin-order-submit-delivery.png)

**Delivery Message Example:**
```
Hi [Buyer Name],

Your WordPress website is complete! Here's what's included:

📦 Deliverables:
- Complete WordPress theme files (theme.zip)
- Design mockups (mockups.pdf)
- Setup instructions (README.pdf)
- Logo files in multiple formats (logos.zip)

🚀 Next Steps:
1. Upload theme.zip via Appearance → Themes → Add New
2. Follow instructions in README.pdf
3. Let me know if you need any help!

I'm including 3 revisions, so if anything needs adjusting, just let me know.

Thanks for your order!
```

**After Submission:**
- Order status → `pending_approval`
- Buyer notified via email
- Buyer reviews delivery
- You can't resubmit unless revision requested

### Handling Revision Requests

When buyer requests revisions:

1. Email notification received
2. Go to **Order Details**
3. View revision feedback
4. Review buyer's specific requests
5. Make requested changes
6. Resubmit delivery

**Revision Counter:**
- Tracks revisions used vs. included
- Example: "2 of 3 revisions used"
- Warning when limit approaching

**Types of Revisions:**

**Minor Revisions (should honor):**
- Color adjustments
- Text changes
- Small layout tweaks
- Bug fixes

**Major Changes (out of scope):**
- Complete redesigns
- New features not in original requirements
- Additional pages/sections
- Changed project direction

**If Revision is Out of Scope:**
1. Message buyer explaining why
2. Offer as additional service (new order)
3. Suggest alternative solution within scope
4. Be professional and helpful

![Revision request details](../images/admin-order-revision-details.png)

### Requesting Deadline Extensions

If you can't meet the deadline:

1. Go to **Order Details**
2. Click **Request Extension**
3. Enter:
   - Number of days needed
   - Reason for extension
   - (Optional) Offer discount/compensation
4. Submit request

**Buyer Response:**
- Buyer approves → deadline extended
- Buyer denies → must deliver by original deadline or face cancellation
- No response after 48 hours → request denied

**Extension Best Practices:**
- Request early (don't wait until deadline)
- Be honest about reason
- Offer solution (partial delivery, discount)
- Communicate proactively

See **[Deadline Extensions Guide](deadline-extensions.md)** for details.

### Communicating with Buyers

Use the order messaging system:

1. Go to **Order Details**
2. Click **Messages** tab
3. Type message
4. (Optional) Attach files
5. Send message

**Buyer receives:**
- Email notification
- Message in their dashboard
- Option to reply

**Communication Tips:**
- Respond within 24 hours
- Be professional and friendly
- Set expectations clearly
- Document important agreements in messages

See **[Order Messaging Guide](order-messaging.md)** for details.

## Buyer Order Management

### Viewing Orders

Buyers access orders from: **Buyer Dashboard → My Orders**

**Order Statuses Explained:**

| Status | What It Means | Your Action |
|--------|---------------|-------------|
| Pending Requirements | Submit your requirements | Click "Submit Requirements" |
| Pending Acceptance | Waiting for vendor to accept | Wait for vendor |
| In Progress | Vendor is working | Track progress, communicate |
| Pending Approval | Review the delivery | Approve or request revision |
| Revision Requested | Vendor making changes | Wait for resubmission |
| Pending Review | Leave a review | Rate and review vendor |
| Completed | Order finished | Download files, leave review |

![Buyer order status view](../images/frontend-buyer-order-status.png)

### Submitting Requirements

After purchase:

1. Redirected to requirements form (or access via order)
2. Fill out all required fields
3. Upload requested files
4. Review answers
5. Click **Submit Requirements**

**Tips:**
- Be thorough and specific
- Upload high-quality files
- Clarify any uncertainties
- Don't rush (good requirements = better results)

### Tracking Order Progress

During `in_progress` status:

**What You Can Do:**
- View deadline countdown
- Message vendor with questions
- Check for vendor updates
- Download delivered files (after submission)

**What You Can't Do:**
- Change requirements (major changes)
- Cancel without vendor agreement
- Rush delivery (unless extension negotiated)

### Reviewing Deliveries

When vendor submits delivery:

1. Email notification received
2. Go to **My Orders → [Order]**
3. Click **View Delivery**
4. Download files
5. Review work carefully
6. Choose action:
   - **Approve Delivery** (if satisfied)
   - **Request Revision** (if changes needed)

![Delivery review interface](../images/frontend-delivery-approve-reject.png)

**Approving Delivery:**
1. Click **Approve Delivery**
2. Confirm approval
3. Order status → `pending_review`
4. Funds released to vendor
5. Prompted to leave review

**Requesting Revision:**
1. Click **Request Revision**
2. Describe changes needed (be specific)
3. Submit revision request
4. Vendor makes changes and resubmits

**Revision Request Tips:**
- Be specific about what needs changing
- Refer to original requirements
- Be reasonable (minor tweaks, not complete redo)
- Communicate respectfully

### Leaving Reviews

After order completion:

1. Go to **My Orders → Completed**
2. Click **Leave Review** on order
3. Rate on 4 criteria (1-5 stars each):
   - **Communication** - Responsiveness and clarity
   - **Quality** - Work quality and professionalism
   - **Delivery** - On-time delivery and reliability
   - **Overall** - Overall experience
4. Write review comment (optional but recommended)
5. Submit review

**Review Window:** 14 days after completion (configurable)

**Review Best Practices:**
- Be honest but fair
- Mention specific positives
- Constructive feedback for improvement
- Acknowledge vendor efforts

![Leave review form](../images/frontend-leave-review-form.png)

## Admin Order Management

### Monitoring All Orders

Admins oversee all marketplace orders:

**Dashboard Overview:**
- Total orders (today, week, month)
- Active orders count
- Average order value
- Commission earned
- Pending issues

### Filtering Orders

**Filter by Status:**
- All orders
- Active (in progress, pending approval)
- Completed
- Cancelled
- Disputed
- Late orders only

**Filter by User:**
- Select vendor (view their orders)
- Select buyer (view their purchases)

**Filter by Date:**
- Today, This Week, This Month
- Custom date range

**Filter by Service:**
- Select specific service
- View all orders for that service

**Search Orders:**
- Order number
- Buyer/vendor name
- Service name

![Admin order filters](../images/admin-order-filters.png)

### Intervening in Orders

Admins can take actions when needed:

**Manual Status Change:**
1. Open order
2. Click **Change Status**
3. Select new status
4. Add reason (recommended)
5. Save

**Refunding Orders:**
1. Open order
2. Click **Refund Order**
3. Choose:
   - Full refund
   - Partial refund (enter amount)
4. Add reason
5. Process refund

**Extending Deadlines:**
1. Open order
2. Click **Extend Deadline**
3. Enter new deadline date
4. Add reason
5. Save (both parties notified)

**Messaging Parties:**
- Admin can message buyer and vendor
- Mediate disputes
- Clarify misunderstandings
- Provide support

![Admin order actions](../images/admin-order-actions.png)

### Handling Disputes

See **[Dispute Resolution Guide](dispute-resolution.md)** for complete details.

**Quick Summary:**
1. Dispute opened by buyer or vendor
2. Admin reviews dispute details
3. Admin reviews evidence from both parties
4. Admin proposes resolution
5. Resolution accepted or escalated

### Bulk Order Actions

Select multiple orders and apply actions:

**Available Bulk Actions:**
- Export to CSV
- Send message to all (selected vendors/buyers)
- Change status (with caution)
- Mark as reviewed

**Use Cases:**
- Export monthly reports
- Send announcement to active order participants
- Clean up old orders

## Order Statistics & Reports

### Vendor Analytics

Track your performance:

**Metrics:**
- Total orders (all time)
- Active orders
- Completion rate
- Average delivery time
- Average order value
- Total revenue
- Commission paid

**Time-Based Reports:**
- Orders per day/week/month
- Revenue trends
- Peak order times

**Service Performance:**
- Orders per service
- Best-selling service
- Package popularity
- Add-on attachment rates

![Vendor order statistics](../images/admin-vendor-order-stats.png)

### Admin Analytics

Marketplace-wide insights:

**Overview:**
- Total marketplace revenue
- Commission earned
- Active vendors
- Total orders processed

**Vendor Comparisons:**
- Top vendors by revenue
- Top vendors by orders
- Vendor growth trends

**Service Insights:**
- Popular categories
- Average order values by category
- Service performance

## Troubleshooting Common Issues

### Order Stuck in Pending Payment

**Causes:**
- Payment gateway issue
- Buyer abandoned checkout
- Bank transfer not yet received

**Solutions:**
- Contact buyer to complete payment
- Cancel order after 24 hours (auto-cancel enabled)
- Manually mark as paid (if payment received off-platform)

### Requirements Not Submitted

**Causes:**
- Buyer forgot
- Buyer doesn't know how
- Requirements too complex

**Solutions:**
- Send reminder message
- Simplify requirements (for future orders)
- Offer to help via phone/chat
- Auto-cancel after 7 days (configurable)

### Buyer Won't Approve Delivery

**Causes:**
- Buyer is busy
- Buyer forgot to review
- Buyer is unhappy but hasn't communicated

**Solutions:**
- Message buyer for feedback
- Auto-approve after 7 days (configurable)
- Offer revision if needed
- Admin can manually approve

### Late Order Disputes

**Causes:**
- Vendor couldn't meet deadline
- Unexpected issues arose
- Scope creep

**Solutions:**
- Vendor requests extension (with explanation)
- Offer partial refund/discount
- Admin mediates if dispute escalates

## Best Practices

### For Vendors

✅ **Communication:**
- Respond to messages within 24 hours
- Provide progress updates every 2-3 days
- Be proactive about potential delays

✅ **Delivery:**
- Submit high-quality work
- Include clear instructions
- Test everything before submitting
- Deliver on or before deadline

✅ **Professionalism:**
- Be courteous and professional
- Accept constructive feedback
- Honor included revisions
- Go above and beyond when possible

### For Buyers

✅ **Requirements:**
- Be thorough and specific
- Provide all necessary files/info
- Clarify uncertainties upfront

✅ **Communication:**
- Respond promptly to vendor questions
- Be available during order execution
- Provide constructive revision feedback

✅ **Reviews:**
- Leave honest reviews
- Be fair and balanced
- Acknowledge vendor effort

### For Admins

✅ **Monitoring:**
- Review disputed orders daily
- Monitor late orders
- Check for stuck orders

✅ **Intervention:**
- Step in when communication breaks down
- Mediate disputes fairly
- Support both vendors and buyers

✅ **Optimization:**
- Analyze order flow bottlenecks
- Adjust settings based on data
- Improve documentation based on common issues

## Manual Order Creation

Admins can create orders manually without checkout - useful for testing, offline arrangements, or special cases.

### When to Use Manual Orders

**Common Use Cases:**
- Testing the complete order workflow
- Creating orders from offline agreements
- Demo/training purposes
- Special arrangements with vendors/buyers
- Migrating orders from another platform
- Creating sample orders for new vendors

**Important:** Manual orders function exactly like regular orders - they go through the same workflow, notifications, and processes.

### Accessing Manual Order Creation

Navigate to **WP Sell Services → Orders → Create Test Order**

![Manual order creation page](../images/admin-create-manual-order.png)

### Step-by-Step: Creating a Manual Order

Follow these steps to create a manual order:

#### Step 1: Select Service

1. Click **Service** dropdown
2. Browse or search for service
3. Select the service
4. Service details load automatically

**Service dropdown shows:**
- Service name
- Vendor name (in parentheses)
- Starting price

#### Step 2: Choose Package

1. After selecting service, package dropdown appears
2. Select package tier:
   - Basic
   - Standard
   - Premium
   - (Or custom package names)

**Package details shown:**
- Package name
- Price
- Delivery days
- Revisions included
- Features list

**If no packages:** Price field auto-populates with base service price.

#### Step 3: Select Buyer

1. Click **Customer (Buyer)** dropdown
2. Choose the buyer (WordPress user)
3. Select from existing users or create new user first

**Buyer dropdown shows:**
- User display name
- Email address

**Creating New Buyer:**
If buyer doesn't exist:
1. Go to **Users → Add New**
2. Create user account
3. Return to order creation
4. Select new user as buyer

#### Step 4: Select Vendor (Auto-Selected)

Vendor is automatically selected based on service author.

**Override Vendor (if needed):**
Some services allow selecting different vendor:
1. Click **Vendor** dropdown (if available)
2. Choose vendor to fulfill order
3. Useful for multi-vendor services

#### Step 5: Set Price

**Option 1: Use Package Price (Default)**
- Leave **Total Amount** field empty
- Order uses selected package price automatically

**Option 2: Custom Price Override**
1. Enter custom amount in **Total Amount** field
2. Useful for:
   - Discounted orders
   - Special pricing arrangements
   - Testing different price points
   - Promotional orders

**Example:**
```
Package Price: $200
Custom Override: $150 (25% discount)
Buyer pays: $150
```

#### Step 6: Set Delivery Deadline

**Option 1: Auto-Calculate (Recommended)**
- Leave deadline field empty
- System calculates based on package delivery days
- Example: Package = 5 days → Deadline = 5 days from now

**Option 2: Custom Deadline**
1. Click **Delivery Deadline** field
2. Choose custom date from calendar
3. Useful for:
   - Rush orders (shorter deadline)
   - Long-term projects (extended deadline)
   - Specific completion dates

#### Step 7: Choose Initial Status

Select starting order status:

| Status | When to Use |
|--------|-------------|
| **Pending Payment** | Testing checkout/payment flow |
| **Pending Requirements** | Buyer needs to submit requirements |
| **Pending Acceptance** | Vendor must accept order |
| **In Progress** | Skip to active work (most common for manual orders) |
| **Completed** | Creating historical/sample order |

**Most Common:** Choose "In Progress" to skip payment and start work immediately.

#### Step 8: Add Order Notes (Optional)

Add internal admin notes:
- Why order was created manually
- Special arrangements
- Reference information
- Testing notes

**Example Note:**
```
Manual order for client "ABC Corp" - agreed offline.
Special 20% discount applied.
Extended deadline per client request.
```

#### Step 9: Create Order

1. Review all information
2. Click **Create Order** button
3. Order is created immediately
4. Redirected to order details page

### What Happens After Creation

Once order is created:

1. **Order Record Created:**
   - Unique order ID assigned (e.g., WPSS-202501-1234)
   - Order appears in admin orders list
   - Visible to vendor and buyer

2. **Notifications Sent:**
   - Vendor receives "New Order" email
   - Buyer receives "Order Confirmation" email
   - Emails match selected initial status

3. **Workflow Begins:**
   - Order enters normal workflow
   - Status changes trigger as usual
   - All features available (messages, delivery, revisions)

4. **Earnings Calculated:**
   - Commission calculated on order total
   - Vendor earnings recorded
   - Financial tracking updated

### Manual Order Workflow

After creating manual order, it follows standard workflow:

**If Status = "Pending Requirements":**
1. Buyer receives email to submit requirements
2. Buyer submits via their dashboard
3. Vendor receives notification
4. Order → In Progress

**If Status = "In Progress":**
1. Vendor starts work immediately
2. Countdown begins
3. Normal delivery process
4. Buyer approves/requests revisions

**If Status = "Completed":**
- Order marked complete
- No further action needed
- Good for sample/demo orders

### Manual Order Best Practices

**For Testing:**
1. Use test users (test-buyer@example.com, test-vendor@example.com)
2. Set low prices ($1-$10)
3. Use short deadlines (1-2 days)
4. Mark clearly in notes: "TEST ORDER - DO NOT FULFILL"
5. Delete test orders after testing

**For Real Orders:**
1. Verify buyer and vendor information
2. Double-check pricing
3. Add clear notes explaining circumstances
4. Use appropriate initial status
5. Monitor order progress closely

**Avoid:**
- Creating orders for non-existent users
- Missing critical information
- Forgetting to notify buyer/vendor separately
- Using manual orders when normal checkout works

### Managing Manual Orders

Manual orders appear in all order lists and can be managed normally:

- View in **Orders** list
- Filter by status
- Update status
- Add messages
- Process deliveries
- Handle revisions
- Issue refunds
- All standard order actions available

### Editing Manual Orders

After creation, you can modify:

**Via Order Details Page:**
1. Change status
2. Adjust deadline
3. Add/remove requirements
4. Update order notes
5. Modify order amount (with caution)

**What You Can't Change:**
- Service or package (would require new order)
- Buyer or vendor (creates confusion)
- Order ID

### Deleting Manual Orders

Remove test or erroneous orders:

1. Go to **Orders** list
2. Find manual order
3. Hover over order
4. Click **Trash**
5. Confirm deletion

**Permanent Deletion:**
1. Go to **Orders → Trash**
2. Find order
3. Click **Delete Permanently**

**Note:** Only delete test orders. Real orders should be cancelled properly to maintain records.

## Order Notes

Admins can add internal notes to any order - visible only to other admins.

### What Are Order Notes?

**Order Notes:**
- Internal admin-only messages
- Not visible to buyers or vendors
- Used for tracking decisions, issues, or information
- Timestamped and attributed to admin who added them
- Permanent record on order

**Order Notes vs. Order Messages:**

| Feature | Order Notes | Order Messages |
|---------|-------------|----------------|
| **Visibility** | Admins only | Buyer, vendor, admins |
| **Purpose** | Internal tracking | Communication |
| **Notifications** | None | Email sent |
| **Use Cases** | Admin decisions, flags | Questions, updates |

### When to Use Order Notes

**Common Use Cases:**

1. **Recording Phone Conversations:**
   ```
   "Spoke with buyer on 01/15 - agreed to extend deadline to 01/25.
   Buyer traveling, will review delivery after return."
   ```

2. **Tracking Internal Decisions:**
   ```
   "Approved refund request due to vendor quality issues.
   Suspended vendor pending investigation."
   ```

3. **Flagging Issues:**
   ```
   "URGENT: Both parties claiming different requirements.
   Review chat logs before mediating dispute."
   ```

4. **Documenting Special Arrangements:**
   ```
   "Custom commission rate applied: 10% instead of 20%.
   Per agreement with high-volume vendor."
   ```

5. **Handoff Notes (Between Admins):**
   ```
   "Needs follow-up on 01/20 to confirm delivery received.
   Buyer has been slow to respond."
   ```

6. **Policy Violation Tracking:**
   ```
   "Vendor requested off-platform payment (violation).
   Warned vendor. Monitoring for repeat behavior."
   ```

### How to Add Order Notes

From the order details page:

1. Open any order (click order number or **View**)
2. Scroll to **Order Notes** section (usually bottom or sidebar)
3. Click **Add Note**
4. Type your note in text field
5. (Optional) Choose note type/color:
   - **General** (gray) - Regular information
   - **Important** (yellow) - Needs attention
   - **Issue** (red) - Problem flagged
6. Click **Add Note** button
7. Note appears in timeline immediately

![Add order note interface](../images/admin-add-order-note.png)

**Note Display:**
```
[01/15/2025 2:30 PM] by AdminName
Spoke with buyer - extending deadline to 01/25.
Buyer traveling, will review after return.
```

### Viewing Order Notes

**In Order Timeline:**
- Order notes appear in order activity timeline
- Distinguished from messages by icon/color
- Shows admin name and timestamp
- Most recent at top (or bottom, depending on settings)

**Notes List View:**
Some themes show separate **Notes** tab:
1. Click **Notes** tab in order details
2. See all notes in chronological order
3. Filter by note type
4. Search notes

### Best Practices for Order Notes

**Do:**
- Be clear and specific
- Include dates and times
- Note who you spoke with (buyer, vendor, both)
- Record decisions and reasons
- Use for anything that needs documentation
- Add notes for unusual situations
- Date future follow-ups

**Don't:**
- Put sensitive personal information (keep minimal)
- Use notes for communication (use messages instead)
- Write vague notes ("looked into this")
- Forget to add notes for important actions
- Use unprofessional language

**Example Good Notes:**

✅ **Good:**
```
"Extended deadline from 01/15 to 01/22 per vendor request.
Vendor had personal emergency (verified). Buyer notified and agreed.
No refund needed."
```

❌ **Bad:**
```
"Extended deadline."
```

### Order Notes for Team Management

If multiple admins manage marketplace:

**Use Notes To:**
1. **Handoff Tasks:**
   ```
   "@JohnAdmin - Please follow up with vendor tomorrow
   about revised delivery. Buyer expects update by EOD."
   ```

2. **Share Context:**
   ```
   "Context: This is buyer's 3rd order dispute this month.
   Previous 2 disputes were valid. Possible pattern."
   ```

3. **Document Actions:**
   ```
   "Processed partial refund of $50 (50%) per dispute resolution.
   Buyer accepted settlement. Case closed."
   ```

4. **Set Reminders:**
   ```
   "REMINDER: Check back on 01/20 if delivery not submitted.
   Vendor has history of late deliveries."
   ```

### Exporting Order Notes

Include notes in order reports:

1. Go to **Orders → Reports**
2. Select orders
3. Check **Include Order Notes** option
4. Export as CSV or PDF
5. Notes appear in separate column

**Use For:**
- Audit trails
- Performance reviews
- Training materials
- Legal documentation
- Process improvement

## Next Steps

- **[Deliveries & Revisions](deliveries-revisions.md)** - Complete delivery process
- **[Deadline Extensions](deadline-extensions.md)** - Requesting and approving extensions
- **[Order Messaging](order-messaging.md)** - Communication best practices
- **[Dispute Resolution](dispute-resolution.md)** - Handling conflicts

Effective order management ensures marketplace success!
