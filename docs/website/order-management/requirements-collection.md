# Requirements Collection

Learn how to collect project details from buyers after they purchase your service. Requirements collection ensures you have all necessary information before starting work, reducing revisions and miscommunication.

## What Is Requirements Collection?

Requirements collection is the process where buyers provide project-specific details after completing payment. This step occurs between payment confirmation and work beginning.

### Why It Matters

**For Vendors:**
- Get clear project specifications before starting
- Reduce back-and-forth communication
- Minimize revision requests
- Avoid scope creep

**For Buyers:**
- Structured way to communicate needs
- Upload reference files and materials
- Ensure vendor understands requirements
- Start with clear expectations

### When It Happens

```
Payment Confirmed → Pending Requirements → Requirements Submitted → In Progress
```

1. **Buyer completes payment** → Order status: `pending_payment`
2. **Payment confirmed** → Order status: `pending_requirements`
3. **Buyer submits requirements form** → Order status: `in_progress`
4. **Vendor starts work** → Deadline calculated and set

## Reminder Schedule

Buyers receive automatic email reminders to submit requirements:

| Day | Reminder Type | Email Sent |
|-----|---------------|------------|
| 1 | First Reminder | "Please submit your requirements" |
| 3 | Second Reminder | "Your vendor is waiting for requirements" |
| 5 | Final Warning | "Submit requirements or order may be affected" |

**Reminder System:**
- Runs via daily cron: `wpss_send_requirements_reminders`
- Tracks reminder count per order in options table
- Stops sending after 3 reminders sent
- Vendor also notified on first reminder

## Timeout Behavior

**Default:** Requirements timeout is disabled (set to 0 days)

When enabled in **Settings → Orders → Requirements Timeout Days**:

### Auto-Start Mode (Default)

**Setting:** `auto_start_on_timeout` = Enabled

**What Happens:**
- Order auto-transitions to `in_progress` without requirements
- Vendor notified: "Order auto-started, buyer didn't submit requirements"
- Buyer notified: "Order started, please contact vendor with details"
- Vendor can request details via messaging

**Example:** Timeout set to 7 days
```
Day 0 → Payment confirmed (pending_requirements)
Day 1 → First reminder sent
Day 3 → Second reminder sent
Day 5 → Final reminder sent
Day 7 → Order auto-starts (in_progress)
```

### Auto-Cancel Mode

**Setting:** `auto_start_on_timeout` = Disabled

**What Happens:**
- Order auto-transitions to `cancelled`
- Automatic refund processed
- Buyer notified: "Order cancelled due to no requirements submitted"
- Vendor notified: "Order cancelled, buyer didn't submit requirements"

## Requirements Form Builder

Vendors create custom requirements forms for each service.

### Accessing the Form Builder

**For Vendors:**
1. Go to **Dashboard → My Services**
2. Click **Edit** on a service
3. Navigate to **Requirements** tab
4. Click **Add Field** to start building

**For Admins:**
1. Go to **WP Admin → WP Sell Services → Services**
2. Click **Edit** on any service
3. Scroll to **Requirements Form** metabox
4. Configure fields for that service

![Requirements form builder](../images/requirements-form-builder.png)

### Form Builder Interface

| Component | Description |
|-----------|-------------|
| Field Type | Select input type (text, textarea, file, select) |
| Question | The question shown to the buyer |
| Placeholder | Example text inside the field |
| Help Text | Additional instructions below the field |
| Required | Whether buyer must fill this field |
| Field Order | Drag to reorder fields |
| Actions | Edit, duplicate, or delete field |

**Note:** The field's `question` property is used as the database key, not `label`.

## Available Field Types

Only 4 field types are exposed in the service creation wizard:

### 1. Text (Single Line)

**Best For:** Short answers like names, URLs, titles

**Configuration:**
- Question: "Website URL"
- Placeholder: "https://example.com"
- Help Text: "Enter the URL where you want the service delivered"
- Required: Yes/No

**Use Cases:**
- Website URL
- Company name
- Contact email
- Social media handle
- Reference link

### 2. Textarea (Multiple Lines)

**Best For:** Detailed descriptions, long-form content

**Configuration:**
- Question: "Project Description"
- Placeholder: "Describe your project in detail..."
- Help Text: "Include goals, target audience, and specific requirements"
- Required: Yes/No

**Use Cases:**
- Project overview
- Design preferences
- Content to be written
- SEO keywords list
- Feature requirements

### 3. File Upload

**Best For:** Reference materials, existing assets, examples

**Configuration:**
- Question: "Upload Reference Files"
- Help Text: "Upload any reference materials, brand guidelines, or examples"
- Required: Yes/No

**File Restrictions:**
- **Max Size:** 50MB per file
- **Allowed Types:** 24 file extensions (see below)

**Allowed File Types:**
```
Images: jpg, jpeg, png, gif, webp
Documents: pdf, doc, docx, xls, xlsx, ppt, pptx, txt, rtf, csv
Archives: zip, rar, 7z
Media: mp3, wav, mp4, mov, avi
Design: psd, ai, eps, svg
```

**Use Cases:**
- Brand logo files
- Reference designs
- Content documents
- Audio/video samples
- Design mockups

### 4. Select (Dropdown)

**Best For:** Single choice from predefined options

**Configuration:**
- Question: "Select Package Type"
- Choices: Enter comma-separated options
  - Example: `Basic, Standard, Premium`
- Required: Yes/No

**Use Cases:**
- Package selection
- Service tier
- Preferred style
- Industry category
- Time zone preference

**Note:** While the code supports `radio`, `checkbox`, and `number` types internally, only the 4 types above are exposed in the service creation wizard UI.

## File Upload System

### Upload Process

1. Buyer selects file(s) from their device
2. JavaScript validates file type and size (client-side)
3. File uploaded via AJAX or form submission
4. Server validates file type again (security)
5. WordPress media library processes file
6. Attachment ID stored with requirements

### File Security

**Validation Steps:**
1. `wp_check_filetype()` verifies extension
2. Extension checked against whitelist
3. File size checked against 50MB limit
4. Files marked as `post_status: 'private'` in media library
5. Order ID stored in `_wpss_order_id` post meta

**Filterable:** Developers can modify allowed types:
```php
add_filter('wpss_requirements_allowed_file_types', function($types) {
    $types[] = 'sketch'; // Add Sketch files
    return $types;
});
```

### Storage Location

Uploaded files are stored in WordPress uploads directory:
```
/wp-content/uploads/YYYY/MM/filename.ext
```

Attachments are created as `private` posts, making them accessible only to:
- Order buyer
- Order vendor
- Site administrators

## Submitting Requirements

### Buyer Submission Flow

1. Buyer receives "Submit Requirements" email
2. Clicks link to requirements form
3. Fills out all required fields
4. Uploads any requested files
5. Reviews submission
6. Clicks **Submit Requirements**

**What Happens Next:**
- Requirements saved to `wpss_order_requirements` table
- Order status changes to `in_progress`
- Vendor receives "Requirements Submitted" notification
- Delivery deadline calculated and set
- Vendor can view requirements in order details

### Validation

**Client-Side (JavaScript):**
- Required fields must have values
- File size checked before upload
- File type validated against allowed list

**Server-Side (PHP):**
- Required field presence checked
- File types validated via `wp_check_filetype()`
- File sizes checked (max 50MB)
- `select`/`radio` values validated against defined choices
- `number` fields validated as numeric

**Validation Errors:**
```
"Website URL is required."
"Invalid selection for Package Type."
"Project Description must be a number."
```

### Late Submission

**Setting:** `allow_late_requirements` (default: disabled)

When enabled, buyers can submit requirements even after work has started:
- Only allowed if order is `in_progress` AND no requirements exist yet
- Vendor receives "Late Requirements Submitted" notification
- Order stays in `in_progress` (doesn't reset deadline)
- Useful for flexible services where work can start without full details

## Viewing Submitted Requirements

### Vendor View

**From Order Details:**
1. Go to **Dashboard → Orders**
2. Click on order
3. View **Requirements** tab
4. See all submitted fields and attachments

**Display Format:**
- Field question as label
- Submitted value displayed below
- File attachments shown with download links
- Submission timestamp shown

### Admin View

**From Admin Panel:**
1. Go to **WP Sell Services → Orders**
2. Open order
3. View **Requirements** meta box
4. See all submitted data

### Data Structure

Requirements are stored as JSON in the database:

```json
{
  "field_data": {
    "Website URL": "https://example.com",
    "Project Description": "Create a modern homepage...",
    "Select Package Type": "Premium"
  },
  "attachments": [
    {
      "id": 123,
      "key": "reference_files",
      "name": "logo.png",
      "url": "https://site.com/wp-content/uploads/2024/02/logo.png",
      "type": "image/png",
      "size": 45678
    }
  ],
  "submitted_at": "2024-02-15 10:30:45"
}
```

## Best Practices

### For Vendors (Creating Forms)

1. **Keep it focused** - Only ask what you truly need
2. **Use clear questions** - Avoid jargon or ambiguous wording
3. **Provide examples** - Use placeholder text to guide buyers
4. **Mark required wisely** - Only require fields you can't work without
5. **Add help text** - Explain why you need the information
6. **Test your form** - Place a test order to see the buyer experience

**Good Question:**
```
Question: "What is your target audience?"
Help Text: "Example: Millennial professionals aged 25-35 interested in fitness"
```

**Bad Question:**
```
Question: "TG demographics?"
Help Text: (none)
```

### For Buyers (Submitting)

1. **Submit promptly** - Vendors can't start until you do
2. **Be thorough** - More detail = better results
3. **Include examples** - Upload reference materials if possible
4. **Ask questions** - Use messaging if something is unclear
5. **Review before submitting** - Can't edit after submission

### For Admins

1. **Review service forms** - Ensure vendors ask appropriate questions
2. **Monitor submission rates** - Identify services with low submission rates
3. **Set reasonable timeouts** - Balance buyer protection and vendor patience
4. **Enable late submissions carefully** - Only for flexible service types
5. **Check email deliverability** - Ensure reminder emails reach buyers

## Configuration

### Order Settings

**Location:** WP Admin → WP Sell Services → Settings → Orders

| Setting | Default | Description |
|---------|---------|-------------|
| Allow Late Requirements | Disabled | Allow submission after work started |
| Requirements Timeout Days | 0 (disabled) | Days before taking timeout action |
| Auto-Start on Timeout | Enabled | Start order vs cancel when timeout reached |

### Email Notifications

Requirement-related emails are configured in **Settings → Emails**:

- ✅ `requirements_reminder` - Buyer reminder emails (day 1, 3, 5)
- ✅ `requirements_submitted` - Vendor notification
- ✅ `requirements_timeout` - Both parties on timeout action

## Troubleshooting

### Buyer Can't Access Form

**Symptoms:** Requirements form shows "Not Available" or 404

**Causes:**
- Order not in `pending_requirements` status
- Payment not confirmed yet
- Service has no requirements configured

**Solutions:**
1. Check order status in database
2. Verify payment was successful
3. Confirm service has requirements fields defined

### File Upload Fails

**Symptoms:** "Upload failed" error or silent failure

**Causes:**
- File exceeds 50MB limit
- File type not in allowed list
- Server `upload_max_filesize` too low
- WordPress media upload permissions issue

**Solutions:**
1. Check file size: Must be under 50MB
2. Verify file type is in allowed list (24 extensions)
3. Increase `upload_max_filesize` in php.ini if needed
4. Check uploads directory permissions (775 or 755)

### Reminders Not Sending

**Symptoms:** Buyers not receiving reminder emails

**Causes:**
- Cron not running
- Email notifications disabled
- Email deliverability issue

**Solutions:**
1. Test cron: `wp cron event run wpss_send_requirements_reminders`
2. Check Settings → Emails: Ensure reminder emails enabled
3. Test site email: Send test email from WordPress
4. Check spam folder
5. Configure SMTP plugin if needed

### Timeout Not Working

**Symptoms:** Orders stay in `pending_requirements` past timeout

**Causes:**
- Timeout days set to 0 (disabled)
- Cron not running

**Solutions:**
1. Verify `requirements_timeout_days` > 0
2. Test cron: `wp cron event run wpss_check_requirements_timeout`
3. Check cron schedule: `wp cron event list`

## Developer Reference

### Hooks

**Actions:**
```php
// Fires when requirements are submitted
do_action('wpss_requirements_submitted', $order_id, $field_data, $attachments);

// Fires when timeout action is taken
do_action('wpss_requirements_timeout', $order_id, $auto_start);
```

**Filters:**
```php
// Modify allowed file types
apply_filters('wpss_requirements_allowed_file_types', $types);

// Customize validation rules
apply_filters('wpss_requirements_validation_rules', $rules, $field);
```

### Database Schema

**Table:** `{prefix}wpss_order_requirements`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) | Primary key |
| `order_id` | bigint(20) | Order ID (foreign key) |
| `field_data` | longtext | JSON of submitted field values |
| `attachments` | longtext | JSON of uploaded file data |
| `submitted_at` | datetime | Submission timestamp |

### Service Meta

**Meta Key:** `_wpss_requirements`

**Structure:**
```php
[
    [
        'type' => 'text',
        'question' => 'Website URL',
        'placeholder' => 'https://',
        'help_text' => 'Enter your site URL',
        'required' => true
    ],
    [
        'type' => 'file',
        'question' => 'Upload Logo',
        'required' => false
    ]
]
```

## Related Documentation

- [Order Lifecycle](order-lifecycle.md)
- [Order Messaging](order-messaging.md)
- [Order Settings](order-settings.md)
