# Deliveries & Revisions

Learn how to submit deliveries, handle revision requests, and manage the approval process. This guide covers the complete delivery workflow from submission to acceptance.

## Delivery Workflow

```
Work Complete → Submit Delivery → Buyer Reviews → Approve or Revise
                                                         ↓
                                                   Revision?
                                                    ↓     ↓
                                              YES ←┘     └→ NO
                                               ↓            ↓
                                        Resubmit      Completed
```

## Submitting Deliveries

### When to Submit

Submit delivery when:
- All work is complete
- All requirements fulfilled
- Files tested and verified
- Ready for buyer review

**Don't submit if:**
- Work is partially complete
- Awaiting buyer clarification
- Files not tested
- Missing required elements

### Delivery Submission Process

1. Go to **Order Details** page
2. Ensure order status is `in_progress`
3. Click **Submit Delivery** button
4. Fill out delivery form:
   - Upload files
   - Write delivery message
   - Add delivery notes
5. Review submission
6. Click **Submit**

![Submit delivery button](../images/admin-delivery-submit-button.png)

### Uploading Deliverable Files

**File Upload Requirements:**

| Setting | Default Value | Configurable |
|---------|---------------|--------------|
| **Maximum Files** | 5 per delivery | Yes (admin settings) |
| **Max File Size** | 50MB per file | Yes (admin settings) |
| **Total Size Limit** | 250MB per delivery | Yes (admin settings) |

**Allowed File Types (Default):**
- Archives: ZIP, RAR, 7Z
- Documents: PDF, DOC, DOCX, XLS, XLSX
- Images: JPG, PNG, GIF, SVG
- Design: AI, PSD, EPS, INDD
- Code: HTML, CSS, JS (in ZIP)
- Media: MP4, MOV (for video services)

**Custom File Types:**

Developers can modify allowed types:

```php
add_filter( 'wpss_delivery_allowed_file_types', function( $types ) {
    $types[] = 'sketch';  // Add Sketch files
    $types[] = 'fig';     // Add Figma files
    return $types;
}, 10 );
```

![File upload interface](../images/admin-delivery-file-upload.png)

### Writing Delivery Messages

A great delivery message includes:

**1. Summary of Work:**
```
Hi [Buyer Name],

I've completed your WordPress website design as requested. Here's what's included:
```

**2. File Breakdown:**
```
📦 Deliverable Files:
1. complete-website.zip - Full WordPress theme
2. design-mockups.pdf - Page designs and layouts
3. setup-guide.pdf - Installation instructions
4. logo-files.zip - Logo in multiple formats
```

**3. Instructions:**
```
🚀 Installation Steps:
1. Backup your existing site (if applicable)
2. Upload complete-website.zip via Appearance → Themes
3. Activate the theme
4. Follow setup-guide.pdf for configuration
```

**4. Next Steps:**
```
✅ What's Next:
- Review the design on your site
- Test on mobile devices
- Let me know if any adjustments are needed
- You have 3 revisions included
```

**5. Closing:**
```
Thank you for your order! I'm happy to make any revisions you need.

Best regards,
[Your Name]
```

![Delivery message editor](../images/admin-delivery-message.png)

### Delivery Best Practices

✅ **File Organization:**
- Name files clearly (not "file1.zip")
- Include version numbers if multiple iterations
- Organize files in logical folders (inside ZIP)
- Remove unnecessary files (cache, temp files)

✅ **Documentation:**
- Always include setup/usage instructions
- Create README files for complex deliverables
- Include credentials (if applicable, securely)
- List dependencies or requirements

✅ **Quality Check:**
- Test all files before uploading
- Verify ZIP files extract properly
- Check for viruses/malware
- Ensure files open correctly

✅ **Professional Presentation:**
- Use proper formatting in message
- Proofread your message
- Be enthusiastic and helpful
- Thank the buyer

❌ **Avoid:**
- Uploading broken/corrupt files
- Missing critical files
- Vague delivery messages
- No instructions provided
- Rushed submissions with errors

## Buyer Review Process

### Buyer Receives Delivery

When you submit delivery:

1. Buyer receives email notification
2. Order status → `pending_approval`
3. Buyer goes to order page
4. Downloads deliverable files
5. Reviews work against requirements

![Buyer delivery notification](../images/frontend-delivery-notification.png)

### Buyer Options

Buyers have three choices:

**1. Approve Delivery** (satisfied with work)
- Work meets requirements
- Quality is acceptable
- Ready to complete order

**2. Request Revision** (needs changes)
- Specific changes needed
- Work doesn't meet requirements
- Minor adjustments required

**3. Open Dispute** (major issues)
- Work significantly below expectations
- Vendor unresponsive
- Scope not met

### Auto-Approval

If buyer doesn't respond:

**Timeline:**
- Delivery submitted
- Buyer has X days to review (default: 7 days)
- Reminder sent after 48 hours
- Auto-approved after 7 days

**Configuration:** WP Sell Services → Settings → Orders → "Auto-approve deliveries after"

**Benefits:**
- Prevents indefinite pending status
- Protects vendor from non-responsive buyers
- Ensures timely order completion

![Auto-approval countdown](../images/admin-delivery-auto-approve.png)

## Revision System

### How Revisions Work

**Revision Allocation:**

Revisions are included in each package:
- Basic package: 1-2 revisions
- Standard package: 2-4 revisions
- Premium package: 4-unlimited revisions

**Revision Counter:**
- Tracks revisions used vs. included
- Displays on order page
- Warning when limit approaching

**Example:**
```
Revisions: 2 of 3 used
Remaining: 1 revision
```

### Requesting Revisions (Buyer)

When buyer wants changes:

1. View delivery on order page
2. Click **Request Revision**
3. Fill out revision request form:
   - Describe changes needed
   - Reference specific requirements
   - Upload reference files (optional)
4. Submit request

![Revision request form](../images/frontend-revision-request-form.png)

**Good Revision Requests:**

✅ **Specific:**
"Change the header background from blue to dark gray (#333333). The current blue clashes with the logo."

✅ **Reasonable:**
"Adjust the font size on the About page to match the Home page. It's currently too small."

✅ **Within Scope:**
"Fix the broken contact form submit button. It's not working on mobile devices."

**Bad Revision Requests:**

❌ **Vague:**
"I don't like it. Make it better."

❌ **Out of Scope:**
"Add 5 more pages and create a membership system." (not in original requirements)

❌ **Complete Redo:**
"Actually, I want a completely different style. Start over."

### Receiving Revision Requests (Vendor)

When buyer requests revision:

1. Email notification received
2. Order status → `revision_requested`
3. View revision details on order page
4. Review buyer's feedback
5. Make requested changes
6. Resubmit delivery

![Vendor revision notification](../images/admin-revision-received.png)

### Making Revisions

**Process:**

1. Review revision feedback carefully
2. Make requested changes
3. Test changes thoroughly
4. Upload revised files
5. Write message explaining changes:

**Example Revision Message:**
```
Hi [Buyer Name],

I've made the requested revisions:

✅ Changes Made:
1. Changed header background to dark gray (#333333)
2. Adjusted About page font size to match Home page (16px)
3. Fixed contact form button on mobile devices

📎 Updated Files:
- revised-website-v2.zip (complete updated theme)
- changes-summary.pdf (visual comparison)

Please review and let me know if any further adjustments are needed.
You have 1 revision remaining.

Best regards,
[Your Name]
```

6. Submit revised delivery

![Resubmit delivery](../images/admin-delivery-resubmit.png)

### Revision Limits

**When Revisions Are Exhausted:**

Scenario: Buyer used all included revisions but wants more changes.

**Options:**

**1. Goodwill Revision (Recommended):**
- If changes are minor, complete as goodwill
- Builds buyer trust
- May result in better review

**2. Additional Revision Purchase:**
- Buyer can purchase extra revisions
- Priced via service add-ons
- Creates new mini-order for revisions

**3. Decline Further Revisions:**
- Explain revision limit reached
- Offer paid revision option
- May result in negative review

**Best Practice:** For minor changes, offer 1-2 goodwill revisions beyond the limit. For major changes, require payment.

![Revision limit reached](../images/admin-revision-limit.png)

### What Counts as a Revision?

Define clearly in your service description:

**Counts as ONE Revision:**
- All changes submitted in one revision request
- Multiple small adjustments made together
- Fixes to previously delivered work

**Examples:**

**One Revision:**
- "Change logo color to blue, adjust header spacing, fix footer alignment"
- All requested in one revision request
- You make all changes in one resubmission

**Multiple Revisions:**
- Request 1: "Change logo color" → You submit
- Request 2: "Adjust header spacing" → You submit
- Request 3: "Fix footer alignment" → You submit

**Not Counted as Revisions:**
- Bug fixes (functionality not working as specified)
- Your errors (work doesn't match requirements)
- Technical issues (broken files, etc.)

### Revision Timeframe

**Vendor Revision Deadline:**

When revision is requested, consider these timelines:

**Quick Revisions (minor changes):** 1-2 days
- Color changes
- Text edits
- Small layout adjustments

**Moderate Revisions:** 2-4 days
- Multiple section changes
- Code modifications
- Design iterations

**Major Revisions:** 4-7 days
- Significant rework
- Multiple pages/sections
- Complex changes

**Pro Tip:** Communicate your revision timeline upfront:
"I'll have your revisions completed within 48 hours."

## Delivery Versions

### Tracking Multiple Submissions

Each delivery submission is tracked:

**Version History:**
- Version 1: Initial delivery
- Version 2: After first revision
- Version 3: After second revision
- etc.

**Access Previous Versions:**
1. Go to order page
2. Click **Delivery History**
3. View all submissions
4. Download files from any version

![Delivery version history](../images/admin-delivery-versions.png)

**Benefits:**
- Buyer can compare versions
- Revert to previous version if needed
- Track changes over time
- Evidence for dispute resolution

## File Storage & Access

### Where Files Are Stored

**Server Storage:**
- Location: `/wp-content/uploads/wpss-deliveries/`
- Organized by: Order ID
- Secure: Not publicly accessible

**Access Control:**
- Buyer can download their order files
- Vendor can download their delivery files
- Admin can access all files
- No public access (requires login + permission)

### Cloud Storage Integration [PRO]

**[PRO]** Store deliveries in cloud:

**Supported Providers:**
- Amazon S3
- Google Cloud Storage
- Cloudflare R2
- Custom S3-compatible storage

**Benefits:**
- Unlimited storage
- Faster downloads
- Reduced server load
- Geographic distribution

**Configuration:**
WP Sell Services → Settings → Storage → Cloud Storage

![Cloud storage settings](../images/admin-cloud-storage-pro.png)

### Download Links

**Delivery Download Links:**
- Temporary secure links (expires after 24-48 hours)
- Regenerates on access
- Prevents unauthorized sharing

**Long-term Access:**
- Buyers have indefinite access to completed order files
- Vendors have access to their deliveries
- Configurable: Admin can set expiration (e.g., 90 days after completion)

## Delivery Notifications

### Email Notifications

**Vendor Notifications:**
- Revision requested
- Delivery approved
- Auto-approval pending (reminder)

**Buyer Notifications:**
- Delivery submitted
- Reminder to review (after 48 hours)
- Revision submitted
- Auto-approval countdown (24 hours before)

**Admin Notifications:**
- Late deliveries
- Delivery disputes
- Auto-approvals (optional)

![Delivery notification email](../images/email-delivery-submitted.png)

## Handling Delivery Issues

### Corrupt/Broken Files

**If buyer reports broken files:**

1. Verify files are not corrupt (test download yourself)
2. Re-upload files if needed
3. Use different compression (ZIP vs RAR)
4. Reduce file size if too large
5. Split into multiple smaller files

**Prevention:**
- Test ZIP files after creating
- Use reliable compression software
- Scan for viruses before upload

### Missing Files

**If buyer reports missing files:**

1. Review original requirements
2. Check what was promised
3. If your error: submit missing files immediately (doesn't count as revision)
4. If not promised: explain and offer as add-on

### File Download Issues

**Common problems:**

**Download Timeout:**
- Files too large
- Solution: Split into smaller files or use cloud storage

**Access Denied:**
- Permission issue
- Solution: Admin checks file permissions

**Link Expired:**
- Temporary link expired
- Solution: Regenerate download link

## Quality Assurance Checklist

Before submitting delivery:

### File Quality

- [ ] All files included
- [ ] Files open/extract properly
- [ ] No corrupt or broken files
- [ ] Proper file naming
- [ ] Version controlled (if multiple iterations)

### Functionality

- [ ] Features work as specified
- [ ] Tested on required browsers/devices
- [ ] No broken links or errors
- [ ] Performance optimized
- [ ] Security checked

### Documentation

- [ ] Setup instructions included
- [ ] README file (if applicable)
- [ ] Credentials provided (securely)
- [ ] Dependencies listed
- [ ] Usage examples included

### Presentation

- [ ] Files organized logically
- [ ] Delivery message written
- [ ] Changes explained (if revision)
- [ ] Professional communication
- [ ] Thanked the buyer

## Delivery Analytics

### Vendor Metrics

Track delivery performance:

**Metrics:**
- Average delivery time (vs. deadline)
- First-time approval rate
- Average revisions per order
- Auto-approval rate (buyer didn't review)
- Delivery-to-completion time

**Performance Indicators:**

**Good Performance:**
- 80%+ first-time approval rate
- Average 0-1 revisions per order
- Delivery before deadline (90%+ on-time)

**Needs Improvement:**
- Below 60% first-time approval
- 2+ revisions per order average
- Frequent late deliveries

![Delivery performance analytics](../images/admin-delivery-analytics.png)

## Best Practices

### For Vendors

✅ **Before Submitting:**
- Complete all required work
- Test everything thoroughly
- Organize files clearly
- Write comprehensive delivery message

✅ **During Revisions:**
- Respond promptly (within 24 hours)
- Make requested changes exactly
- Communicate what you changed
- Be professional and courteous

✅ **Quality Standards:**
- Exceed buyer expectations when possible
- Include bonus files/documentation
- Deliver before deadline
- Offer proactive improvements

### For Buyers

✅ **Reviewing Deliveries:**
- Review within 2-3 days
- Test all functionality
- Check against original requirements
- Provide specific feedback if requesting revisions

✅ **Revision Requests:**
- Be specific about changes needed
- Reference original requirements
- Be reasonable and fair
- Communicate clearly

✅ **Approval:**
- Approve promptly if satisfied
- Leave a review
- Acknowledge good work

## Troubleshooting

### Delivery Not Received

**Vendor says submitted, buyer says not received:**

1. Check order status (should be `pending_approval`)
2. Verify files uploaded successfully
3. Check spam folder for notification email
4. Admin can view delivery on backend
5. Resend notification email

### Can't Upload Files

**Error: "File too large"**
- Compress files more
- Split into multiple files
- Admin increases upload limit

**Error: "File type not allowed"**
- Check allowed file types
- ZIP disallowed types
- Admin adds file type to whitelist

### Revision Disputes

**Buyer wants changes outside scope:**

1. Review original requirements
2. Explain what's within scope
3. Offer out-of-scope work as new order
4. Be professional and helpful
5. If dispute escalates, admin mediates

## Next Steps

- **[Order Workflow](order-workflow.md)** - Complete order lifecycle
- **[Deadline Extensions](deadline-extensions.md)** - Extending delivery dates
- **[Order Messaging](order-messaging.md)** - Communication during orders
- **[Dispute Resolution](dispute-resolution.md)** - Handling conflicts

Great deliveries and smooth revisions lead to 5-star reviews!
