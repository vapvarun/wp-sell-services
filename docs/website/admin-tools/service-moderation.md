# Service Moderation

Manage service submissions and review vendor listings to maintain marketplace quality through the admin moderation queue.

## Overview

The service moderation system ensures all vendor services meet your marketplace standards before going live. Admins can approve or reject pending submissions with detailed feedback to vendors.

**Note:** Service moderation must be enabled in settings for this feature to activate.

## Enabling Service Moderation

Service moderation is configured on the Vendor settings tab:

1. Go to **WP Sell Services → Settings → Vendor**
2. Check **Require Service Moderation**
3. Save changes

**When Enabled:**
- New vendor services are submitted as "Pending"
- Services require admin approval before publication
- Pending services are hidden from marketplace
- Vendors receive approval/rejection notifications

**When Disabled:**
- Vendor services publish immediately
- No admin review required
- Services go live when vendor clicks "Publish"

![Vendor Settings - Service Moderation](../images/settings-vendor-moderation.png)

## Service Moderation Workflow

When moderation is enabled:

1. **Vendor Submits**: Vendor creates service and clicks "Publish"
2. **Status: Pending**: Service enters moderation queue with pending status
3. **Admin Reviews**: Admin evaluates service at **WP Sell Services → Moderation**
4. **Decision**: Admin approves or rejects with reason
5. **Notification**: Vendor receives automated email with outcome
6. **Outcome**: Approved services go live, rejected services return to draft

## Accessing the Moderation Queue

The dedicated moderation page shows all pending services.

### Navigation

1. Log in to WordPress admin
2. Go to **WP Sell Services → Moderation**
3. View all services awaiting approval

The moderation menu item shows a badge with pending count when services await review.

![Moderation Menu Badge](../images/admin-moderation-menu.png)

### Moderation Page Layout

The moderation queue displays services in a table with these columns:

| Column | Description |
|--------|-------------|
| Image | Service thumbnail |
| Service | Service title and edit link |
| Vendor | Submitting vendor name and profile link |
| Category | Service category |
| Price | Starting price (basic package) |
| Submitted | Submission date |
| Status | Current moderation status |
| Actions | Approve and Reject buttons |

### Filtering Options

Filter moderation queue by status:

- **All** - View all services regardless of status
- **Pending** - Services awaiting approval
- **Approved** - Previously approved services
- **Rejected** - Previously rejected services

Click tabs above the table to filter view.

![Moderation Queue Filters](../images/moderation-queue-tabs.png)

## Service Review Checklist

Use this checklist to maintain consistent quality standards:

### Title Quality

- [ ] Clear and descriptive
- [ ] Proper grammar and spelling
- [ ] No excessive capitalization (not "BEST SERVICE EVER")
- [ ] No misleading claims
- [ ] Professional language
- [ ] Reasonable length (under 100 characters)

### Description Completeness

- [ ] Service clearly explained
- [ ] Deliverables listed
- [ ] Requirements stated (if any)
- [ ] Process/workflow described
- [ ] Realistic turnaround time
- [ ] Minimum 150 words (recommended)

### Pricing Reasonableness

- [ ] Prices match industry standards
- [ ] Package differences are clear
- [ ] No price manipulation
- [ ] Add-ons are relevant
- [ ] Pricing makes sense for service scope

### Gallery Quality

- [ ] Professional images uploaded
- [ ] Images relevant to service offered
- [ ] No copyrighted material without permission
- [ ] No inappropriate content
- [ ] Minimum 1 image (recommended 3-5)

### Content Compliance

- [ ] No prohibited services
- [ ] No copyright violations
- [ ] No trademark infringement
- [ ] Follows marketplace policies
- [ ] No spam or duplicate content
- [ ] Portfolio items are genuine work samples

### Vendor Profile

- [ ] Vendor profile is complete
- [ ] Contact information valid
- [ ] Professional bio provided

## Approving Services

When a service meets quality standards, approve it to publish on the marketplace.

### Approval Process

1. Click **Approve** button next to the service
2. System prompts for confirmation
3. Optionally add admin note (internal use only)
4. Confirm approval

**Keyboard Shortcut:** Select service row and press `A` for quick approval.

### What Happens on Approval

**Immediate Effects:**
- Service status changes from "Pending" to "Approved"
- Service post status changes to "Published"
- Service appears in marketplace immediately
- Service becomes searchable
- Service appears in vendor's public profile
- Vendor can start receiving orders

**Vendor Notification:**
Vendor receives email containing:
- Service approval confirmation
- Service title and marketplace link
- Publication date and time
- Link to view service
- Encouragement to promote service

**Admin Visibility:**
- Service moves from Pending to Approved tab
- Service shows "Approved" status badge
- Vendor statistics update

![Service Approval Email](../images/email-service-approved.png)

### Bulk Approval

Approve multiple services at once:

1. Check boxes next to services to approve
2. Select **Approve** from bulk actions dropdown
3. Click **Apply**
4. Confirm bulk approval
5. All selected services are approved simultaneously

## Rejecting Services

Reject services that don't meet quality standards or violate policies.

### Rejection Process

1. Click **Reject** button next to the service
2. System prompts for rejection reason
3. Enter detailed feedback explaining the rejection
4. Confirm rejection

**Rejection Reason Field:**
Provide specific, actionable feedback so vendor knows what to fix.

### What Happens on Rejection

**Immediate Effects:**
- Service status changes to "Rejected"
- Service remains hidden from marketplace
- Rejection reason is stored
- Vendor receives rejection notification
- Service returns to draft state for vendor to edit

**Vendor Actions:**
- Vendor can view rejection reason in their dashboard
- Vendor can edit service to address issues
- Vendor can resubmit for approval after corrections
- Resubmitted services enter moderation queue again

**Admin Visibility:**
- Service moves to Rejected tab
- Rejection reason visible to admins
- Service shows "Rejected" status badge

![Service Rejection Dialog](../images/moderation-reject-dialog.png)

### Writing Effective Rejection Messages

**Good Example:**
```
Your service description needs more detail. Please add:

- Specific deliverables you'll provide
- Your process/workflow (step-by-step)
- Typical turnaround time
- Requirements from buyers

Also, please upload at least 2 relevant portfolio samples
showing your work quality. Current images are too generic.
```

**Poor Example:**
```
Service rejected. Needs improvement.
```

**Best Practices:**
- Be specific about what needs fixing
- Reference exact issues (images, description, pricing)
- Provide actionable guidance
- Remain professional and encouraging
- Link to relevant policies if applicable

### Common Rejection Reasons

**Poor Quality Description:**
```
Your description is too brief. Please expand to at least
200 words explaining your service, process, deliverables,
and what makes your approach unique.
```

**Inappropriate Images:**
```
The images you've uploaded do not represent your service.
Please upload relevant work samples or portfolio pieces
that demonstrate the type of work you'll deliver.
```

**Copyright Violation:**
```
The portfolio images appear to be stock photos or work
from other sources. Please only upload your original work.
If using licensed content, provide proof of license.
```

**Prohibited Service:**
```
The service you've listed falls under our prohibited
categories. Please review our Service Guidelines at
[link] before submitting a different service.
```

**Misleading Pricing:**
```
Your Basic package pricing ($5) doesn't align with the
deliverables promised (full website design). Please adjust
either the price to reflect the work scope, or reduce
deliverables to match the price point.
```

### Rejection Notification Email

Vendor receives automated email with:
- Service title
- Rejection reason (admin feedback)
- Instructions to edit and resubmit
- Link to edit service
- Link to marketplace policies
- Support contact information

## Moderation Actions Only

**Important:** The moderation system supports only two actions:

**✓ Approve** - Publish service to marketplace
**✗ Reject** - Return to draft with feedback

**No "Request Changes" Action:**
If a service needs minor improvements, you must reject it with detailed feedback. The vendor will edit and resubmit. There is no intermediate "pending changes" status.

**Workflow for Minor Issues:**
1. Reject with specific, actionable feedback
2. Vendor edits service
3. Vendor resubmits (goes back to moderation queue)
4. You review again and approve

## Moderation Page Features

### Quick Actions

Each service row provides quick action buttons:

- **View** - View service on frontend (opens in new tab)
- **Edit** - Edit service in WordPress editor
- **Approve** - Approve service
- **Reject** - Reject service with reason

### Status Badges

Visual badges indicate current status:

- **Pending** - Yellow badge, awaiting review
- **Approved** - Green badge, published
- **Rejected** - Red badge, rejected with reason

### Service Preview

Click service title to preview:
- Service details page
- All packages and pricing
- Gallery images
- Description and FAQs
- Vendor profile
- Service requirements

## Admin Notifications

Stay informed about pending services:

### Dashboard Notice

When services are pending:
- Notice appears at top of admin pages
- Shows count of pending services
- "Review Now" link to moderation page
- Dismissible until new services are submitted

### Moderation Menu Badge

Moderation menu item shows:
- Red notification badge
- Count of pending services
- Updates in real-time

### Email Notifications (Pro)

**[PRO]** Admins receive email when:
- New service is submitted for review
- Service is resubmitted after rejection
- Daily digest of pending services (configurable)

## Vendor Dashboard View

Vendors see moderation status in their dashboard:

**Pending Services:**
- Yellow "Pending Review" badge
- Message: "Your service is awaiting admin approval"
- Cannot receive orders while pending

**Rejected Services:**
- Red "Rejected" badge
- Rejection reason displayed
- "Edit Service" button to make corrections
- Instructions to resubmit

**Approved Services:**
- Green "Live" badge
- View count and order statistics
- Full editing capabilities
- Can pause/unpublish if needed

## Moderation Settings

Configure moderation behavior in settings:

### Vendor Settings Tab

**Require Service Moderation:**
- Checkbox to enable/disable
- When disabled, services publish immediately
- When enabled, all new services require approval

**Who Can Bypass Moderation:**
- Admins and shop managers always bypass
- Services created by admins publish immediately
- Regular vendors always require approval

### Notification Settings

**Email Toggles:**
- New service submitted (admin notification)
- Service approved (vendor notification)
- Service rejected (vendor notification)

Configure at **Settings → Emails**.

## Troubleshooting

### Services Not Appearing in Queue

**Check:**
1. Service moderation is enabled in Vendor settings
2. Services are actually pending (check post status)
3. No caching plugin showing stale data
4. Admin has proper permissions

### Approval Not Working

**Verify:**
1. Service is not already approved
2. No conflicting plugins interfering
3. Check browser console for JavaScript errors
4. Try manual approval via Services list

### Vendor Not Receiving Notifications

**Check:**
1. Email notifications enabled in Settings → Emails
2. WordPress can send emails (test with password reset)
3. Emails not going to spam folder
4. Vendor email address is valid

### Bulk Actions Not Working

**Solutions:**
1. Select fewer services (system may timeout with large batches)
2. Check server PHP execution time limits
3. Use individual actions for best reliability
4. Check for JavaScript errors in console

## Related Documentation

- [Vendor Settings](../platform-settings/vendor-settings.md) - Enable/disable moderation
- [Email Configuration](../notifications-emails/email-configuration.md) - Notification settings
- [Service Creation](../service-creation/service-creation-wizard.md) - Vendor submission process

## Next Steps

After setting up moderation:

1. Enable service moderation in Vendor settings
2. Establish clear quality guidelines for vendors
3. Create rejection reason templates for consistency
4. Monitor moderation queue daily
5. Provide timely feedback to vendors (within 24-48 hours)
