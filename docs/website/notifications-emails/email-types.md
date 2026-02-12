# Email Notification Types

WP Sell Services sends automated email notifications to keep buyers, vendors, and administrators informed throughout the order lifecycle. Each notification is triggered by specific events and can be individually enabled or disabled in settings.

## Overview

The email notification system integrates with WordPress's native `wp_mail()` function and WooCommerce email templates (when WooCommerce is active). All notifications support HTML format with a professional template design.

### Configurable Notification Types

The plugin provides 8 configurable notification types that admins can enable or disable globally:

| Notification | Recipients | Trigger Event |
|--------------|-----------|---------------|
| New Order | Vendor | Buyer completes checkout and places order |
| Order Completed | Both | Buyer accepts delivery or order auto-completes |
| Order Cancelled | Both | Order is cancelled by buyer, vendor, or admin |
| Delivery Submitted | Buyer | Vendor submits delivery files for review |
| Revision Requested | Vendor | Buyer requests changes to submitted delivery |
| New Message | Recipient | Message sent through order conversation |
| New Review | Vendor | Buyer leaves review after order completion |
| Dispute Opened | Both | Either party opens a dispute |

All notification types are enabled by default when the plugin is activated.

---

## Notification Settings Location

Configure which notification types are sent from the admin panel.

### Accessing Settings

1. Navigate to **WordPress Admin → WP Sell Services → Settings**
2. Click the **Emails** tab
3. View the Email Notifications section

**Settings Page Shows:**
- List of 8 notification types
- Checkbox to enable/disable each type
- Description of when each notification is sent
- WooCommerce integration notice (if WooCommerce is active)

### Global Toggle Behavior

**When a notification type is disabled:**
- No email sent regardless of user preferences
- In-app notification still created
- Event is logged in system
- Admin can manually trigger email if needed

**When a notification type is enabled:**
- Email sent automatically when event occurs
- Checks user's notification preferences
- Respects WooCommerce email settings (if applicable)
- Sends to appropriate recipient (buyer, vendor, or both)

---

## 1. New Order Notification

**Recipients:** Vendor (service seller)

**Trigger Event:** A buyer completes checkout and payment for the vendor's service.

**Notification Type Constant:** `TYPE_ORDER_CREATED`

**Settings Key:** `notify_new_order`

**Default Status:** Enabled

### Email Content

**Subject Line:** You received a new order #{order_number}

**Notification Message Includes:**
- Buyer's display name
- Service name
- Order number
- Order amount (formatted price)
- Next step: Wait for buyer requirements

**Sample Notification:**
```
Great news! Sarah Williams has placed an order for your service.

Order Details:
Service: Professional Logo Design
Order Number: #12345
Amount: $250.00

The buyer will submit their requirements shortly. You'll be notified
when they do so you can start working on the order.
```

### Additional Notifications Sent

**Buyer Also Receives:** Order confirmation notification
- Notification type: `order_confirmation`
- Thanks buyer for order
- Shows vendor name and service details
- Next step: Submit requirements

**Vendor Action Required:**
1. Wait for requirements submission notification
2. Review requirements when received
3. Start work on order

### Email Template Location

**Standalone Template:** `/templates/emails/new-order.php`

**WooCommerce Template:** `/templates/emails/woocommerce/new-order.php` (when WC is active)

---

## 2. Requirements Submitted Notification

**Recipients:** Vendor

**Trigger Event:** Buyer submits order requirements through the requirements form.

**Notification Message:** Sent when order transitions to `in_progress` status after requirements submission.

**Settings Key:** Controlled by `notify_new_order` toggle

**Default Status:** Enabled

### Email Content

**Notification Includes:**
- Buyer name
- Order number
- Service name
- Requirements submitted confirmation

**Sample Notification:**
```
Sarah Williams has submitted the requirements for Order #12345.

Service: Professional Logo Design

You can now start working on this order. Please deliver within
the agreed timeframe.
```

**Buyer Also Receives:** Order in progress confirmation
- Notification type: `order_in_progress`
- Confirms vendor has started work
- Shows expected delivery date

### Vendor Action Required

1. Review requirements carefully
2. Check uploaded files
3. Contact buyer if clarification needed
4. Start working on order
5. Upload delivery when complete

---

## 3. Delivery Submitted Notification

**Recipients:** Buyer (customer)

**Trigger Event:** Vendor uploads delivery files and submits for review.

**Notification Type Constant:** `TYPE_DELIVERY_SUBMITTED`

**Settings Key:** `notify_delivery_submitted`

**Default Status:** Enabled

### Email Content

**Subject Line:** Delivery Ready for Review

**Notification Message Includes:**
- Vendor name
- Order number
- Service name
- Delivery submission confirmation
- Next steps: Review and accept or request revision

**Sample Notification:**
```
John Smith has submitted the delivery for Order #12345.

Service: Professional Logo Design

Please review the delivery and either accept it to complete the order,
or request a revision if changes are needed.
```

### Buyer Action Required

1. View delivery files
2. Review against requirements
3. Choose one of two actions:
   - **Accept delivery:** Completes the order
   - **Request revision:** Send back with feedback

### Auto-Completion Notice

If configured, notification includes:
- Auto-completion deadline (e.g., 3 days)
- Reminder that order completes automatically if no action taken

---

## 4. Order Completed Notification

**Recipients:** Both buyer and vendor

**Trigger Event:** Buyer accepts delivery OR order auto-completes after review period.

**Notification Type Constant:** `TYPE_DELIVERY_ACCEPTED`

**Settings Key:** `notify_order_completed`

**Default Status:** Enabled

### Buyer Version

**Subject Line:** Order Completed

**Notification Message Includes:**
- Order number
- Service name
- Vendor name
- Completion confirmation
- Invitation to leave review

**Sample Notification (Buyer):**
```
Order #12345 has been completed successfully!

Service: Professional Logo Design
Seller: John Smith

Thank you for your business. If you're satisfied with the service, please
consider leaving a review to help other buyers.
```

### Vendor Version

**Subject Line:** Order Completed - Payment Released

**Notification Message Includes:**
- Buyer name
- Order number
- Service name
- Payment release confirmation
- Earnings added to balance

**Sample Notification (Vendor):**
```
Congratulations! Sarah Williams has accepted the delivery for Order #12345.

Service: Professional Logo Design

The payment has been released to your account. Thank you for providing
excellent service!
```

---

## 5. Revision Requested Notification

**Recipients:** Vendor

**Trigger Event:** Buyer requests changes after reviewing a delivery.

**Notification Type Constant:** `TYPE_REVISION_REQUESTED`

**Settings Key:** `notify_revision_requested`

**Default Status:** Enabled

### Email Content

**Subject Line:** Revision Requested

**Notification Message Includes:**
- Buyer name
- Order number
- Service name
- Revision request details

**Sample Notification:**
```
Sarah Williams has requested a revision for Order #12345.

Service: Professional Logo Design

Please review their feedback and submit an updated delivery.
```

### Vendor Action Required

1. Read revision feedback
2. Ask questions if feedback is unclear
3. Make requested changes
4. Submit revised delivery
5. Notify buyer of resubmission

---

## 6. Order Cancelled Notification

**Recipients:** Both buyer and vendor

**Trigger Event:** Order is cancelled by buyer, vendor, or administrator.

**Settings Key:** `notify_order_cancelled`

**Default Status:** Enabled

### Email Content

**Notification Message Includes:**
- Order number
- Service name
- Who initiated cancellation
- Cancellation reason (if provided)
- Refund information

**Sample Notification (Buyer):**
```
Order #12345 has been cancelled.

Service: Professional Logo Design

If you have any questions about this cancellation, please contact support.
```

**Sample Notification (Vendor):**
```
Order #12345 from Sarah Williams has been cancelled.

Service: Professional Logo Design

If you have any questions about this cancellation, please contact support.
```

---

## 7. New Message Notification

**Recipients:** Message recipient (buyer or vendor)

**Trigger Event:** A message is sent through the order conversation system.

**Notification Type Constant:** `TYPE_NEW_MESSAGE`

**Settings Key:** `notify_new_message`

**Default Status:** Enabled

### Email Content

**Subject Line:** New Message Received

**Notification Message Includes:**
- Sender name
- Order number (if part of order conversation)
- Service name (if applicable)
- Message preview (first 50 words)

**Sample Notification:**
```
You have received a new message from John Smith.

Order: #12345
Service: Professional Logo Design

Message:
"Hi, I have a question about the color palette. Would you prefer..."

Log in to your dashboard to view the full conversation and reply.
```

### Message Preview

- Truncates long messages to 50 words
- Includes HTML preview in styled box
- Shows visual indication it's a message quote

---

## 8. New Review Notification

**Recipients:** Vendor

**Trigger Event:** Buyer leaves a review after order completion.

**Notification Type Constant:** `TYPE_REVIEW_RECEIVED`

**Settings Key:** `notify_new_review`

**Default Status:** Enabled

### Email Content

**Subject Line:** New Review Received

**Notification Message Includes:**
- Reviewer name
- Service name
- Star rating (displayed as stars: ★★★★★)
- Review comment (if provided)

**Sample Notification:**
```
Sarah Williams has left a review for Professional Logo Design.

Rating: ★★★★★ (5/5)

Review:
"Excellent work! John was very responsive and delivered exactly what I needed.
The logo looks professional and modern. Highly recommended!"

Thank you for providing excellent service! Reviews help build your reputation
and attract more customers.
```

---

## 9. Dispute Opened Notification

**Recipients:** Both buyer and vendor

**Trigger Event:** Either party opens a dispute for an order.

**Notification Type Constant:** `TYPE_DISPUTE_OPENED`

**Settings Key:** `notify_dispute_opened`

**Default Status:** Enabled

### Email Content

**Notification Message Includes:**
- Order number
- Service name
- Who filed the dispute
- Dispute reason/category
- Next steps in resolution process

**Sample Notification (Vendor Receives):**
```
A dispute has been opened for Order #12345.

Service: Professional Logo Design

Our support team will review the case and get back to you soon. Please
prepare any relevant information.
```

**Sample Notification (Buyer Receives):**
```
A dispute has been opened for Order #12345.

Service: Professional Logo Design

Our support team will review the case and get back to you soon.
```

---

## Additional Notification Types

The plugin creates additional notifications for specific events that are not individually configurable:

### Requirements Reminder

- Sent to buyer who hasn't submitted requirements
- Not configurable (sent based on order settings)
- Timing based on platform configuration

### Seller Level Promotion

**Notification Type Constant:** `TYPE_VENDOR_REGISTERED`

**Recipients:** Vendor

**Trigger Event:** Vendor account created or promoted to higher level.

**Sample Notification:**
```
Congratulations! Your vendor account on [Platform Name] has been created.
You can now start creating services and accepting orders.
```

---

## WooCommerce Email Integration

When WooCommerce is active, WP Sell Services integrates with WooCommerce's email system.

### How Integration Works

**Email Class Registration:**
- Plugin registers custom WooCommerce email classes
- Each notification type becomes a WooCommerce email
- Inherits WooCommerce email styling and templates

**Settings Location:**
When WooCommerce is active, additional settings appear at:
- **WooCommerce → Settings → Emails**
- WP Sell Services emails listed alongside WooCommerce emails
- Configure subject line, heading, and additional content per email type

**Template Loading:**
- Uses WooCommerce template hierarchy
- Can be overridden in theme
- Supports WooCommerce email customizer plugins

**Benefits:**
- Consistent branding with WooCommerce emails
- Unified email management
- Compatible with WooCommerce email plugins
- Professional template design

---

## Email Format

All email notifications use HTML format with a professional template.

### Template Structure

1. **Header** - Site logo and branding
2. **Content** - Notification message with order details
3. **Action Button** - "View Order Details" link
4. **Footer** - Site name, copyright, links

### Template Features

- Responsive design for mobile devices
- Inline CSS for email client compatibility
- Table-based layout for universal support
- Professional color scheme
- Clickable call-to-action buttons
- Clear typography and spacing

---

## Notification Preferences

Notifications are controlled at two levels:

### Admin Level (Global)

**Location:** WP Sell Services → Settings → Emails → Email Notifications

**Controls:**
- Master switch for each notification type
- When disabled, NO emails sent regardless of user settings
- Affects all users on the platform

### User Level (Individual)

**Note:** Individual user preferences are not currently implemented in the base plugin. All users receive notifications based on admin settings.

**Future Enhancement:**
- Per-user notification preferences
- Available in PRO version or future update

---

## Technical Details

### Database Storage

**In-App Notifications:**
- Stored in `wp_wpss_notifications` table
- Always created even if email is disabled
- Accessible through notification center

**Email Delivery:**
- Uses WordPress `wp_mail()` function
- SMTP plugin recommended for reliability
- No separate email queue (sends immediately)

### Notification Service Class

**Location:** `src/Services/NotificationService.php`

**Key Methods:**
- `create()` - Creates in-app notification
- `send_email()` - Sends email notification
- `should_send_email()` - Checks if email should be sent
- `notify_order_created()` - Handles new order notifications
- `notify_order_status()` - Handles status change notifications
- `notify_new_message()` - Handles message notifications
- `notify_review_received()` - Handles review notifications

### Action Hooks

```php
// Fired when notification is created
do_action( 'wpss_notification_created', $notification_id, $user_id, $type, $data );

// Filter email content before sending
apply_filters( 'wpss_notification_email_content', $email_content, $subject, $user_id, $data );
```

---

## Troubleshooting

### Emails Not Received

**Check these settings:**
1. Notification type enabled in admin settings
2. WordPress email functionality working (test with password reset)
3. Check spam folder
4. Verify user email address is correct
5. Install SMTP plugin for better deliverability

### Wrong Content in Email

**Verify:**
1. Using latest plugin version
2. Template files not corrupted
3. Clear any caching plugins
4. Check for theme conflicts
5. Reset to default template

### Duplicate Emails

**Possible causes:**
1. Multiple notification plugins active
2. Cron running multiple times
3. Database table corruption
4. Check error logs for clues

---

## Next Steps

- **[Email Configuration](email-configuration.md)** - Customize email templates and settings
- **[In-App Notifications](in-app-notifications.md)** - Configure browser notifications
- **[Order Lifecycle](../order-management/order-lifecycle.md)** - Understand order workflow
- **[Platform Settings](../platform-settings/general-settings.md)** - Global configuration

---

*Documentation based on plugin version 1.0.0*
