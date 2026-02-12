# Email Configuration

Configure email notification settings in WP Sell Services to control which emails are sent and how they are delivered.

## Overview

The email system uses WordPress's native `wp_mail()` function to deliver notifications. For better deliverability, install an SMTP plugin like WP Mail SMTP or FluentSMTP.

## Accessing Email Settings

1. Navigate to **WordPress Admin → WP Sell Services → Settings**
2. Click the **Emails** tab
3. Configure notification settings

## Email Notification Settings

### Notification Toggles

Control which email types are sent globally. All toggles are enabled by default.

**Available Settings:**

| Setting Key | Description | Default |
|-------------|-------------|---------|
| `notify_new_order` | Send email when vendor receives new order | Enabled |
| `notify_order_completed` | Send email when order is completed | Enabled |
| `notify_order_cancelled` | Send email when order is cancelled | Enabled |
| `notify_delivery_submitted` | Send email when vendor submits delivery | Enabled |
| `notify_revision_requested` | Send email when buyer requests revision | Enabled |
| `notify_new_message` | Send email for new messages | Enabled |
| `notify_new_review` | Send email when vendor receives review | Enabled |
| `notify_dispute_opened` | Send email when dispute is opened | Enabled |

**How Toggles Work:**

- **Checked (Enabled):** Emails sent when events occur
- **Unchecked (Disabled):** No emails sent (in-app notifications still created)
- Changes apply to all users immediately

### Settings Location in Code

**Option Name:** `wpss_notifications`

**Storage:** WordPress options table

**Defaults Set By:** `Activator.php` on plugin activation

## WooCommerce Integration

When WooCommerce is active, WP Sell Services integrates with WooCommerce's email system.

### How It Works

**Automatic Detection:**
```php
if ( class_exists( 'WooCommerce' ) ) {
    // WooCommerce integration enabled
}
```

**Email Registration:**
- Plugin registers custom WooCommerce email classes
- Each notification type becomes a WooCommerce email
- Inherits WooCommerce email templates and styling

**Additional Settings:**
When WooCommerce is active, additional email settings are available at:
- **WooCommerce → Settings → Emails**
- Configure subject lines, headings, and additional content
- Customize email colors and branding

### Benefits

- **Consistent Branding:** Uses your WooCommerce email template
- **Unified Management:** Configure all emails in one place
- **Template Compatibility:** Works with WooCommerce email plugins
- **Professional Design:** Inherits WooCommerce email styling

### WooCommerce Email Settings Notice

The settings page displays this notice when WooCommerce is active:

```
To customize email content, subjects, and templates, go to WooCommerce > Settings > Emails.
```

## Email Delivery

### WordPress Default (wp_mail)

By default, WordPress uses PHP's `mail()` function.

**Limitations:**
- May be unreliable on shared hosting
- Emails often marked as spam
- No delivery tracking
- No authentication

### SMTP Recommended

Install an SMTP plugin for reliable email delivery:

**Recommended Plugins:**
- **WP Mail SMTP** - Most popular, supports major providers
- **FluentSMTP** - Modern interface, Amazon SES support
- **Post SMTP** - Advanced features, detailed logging
- **Easy WP SMTP** - Simple setup

**Benefits:**
- Better deliverability
- Less spam filtering
- Email authentication (SPF, DKIM)
- Delivery tracking and logging

## Email Template System

### Template Location

**Standalone Templates:** `/templates/emails/`

**WooCommerce Templates:** `/templates/emails/woocommerce/` (when WC is active)

### Template Override

Copy templates to your theme for customization:

**Theme Override Location:**
```
/wp-content/themes/your-theme/wp-sell-services/emails/
```

**Process:**
1. Copy template file from plugin
2. Paste into theme override folder
3. Edit the copied file
4. Plugin automatically uses your version

### Email Template Structure

All emails use this structure:

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; font-family: sans-serif; background-color: #f5f5f5;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table width="600" style="background-color: #ffffff;">
                    <!-- Header with logo -->
                    <tr style="background-color: #1e3a5f;">
                        <td style="padding: 30px;">
                            <h1 style="color: #ffffff;">[Site Name]</h1>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            [Email Content]
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr style="background-color: #f8f9fa;">
                        <td style="padding: 25px 30px;">
                            [Footer Text]
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

**Template Features:**
- Responsive design
- Inline CSS for email client compatibility
- Table-based layout
- Professional color scheme (#1e3a5f default header)

## Notification Service Class

**Location:** `src/Services/NotificationService.php`

### Key Methods

**`should_send_email( $user_id, $type )`**
- Checks admin notification settings
- Verifies notification type is enabled
- Returns true if email should be sent

**`send_email( $user_id, $subject, $message, $data )`**
- Sends email using `wp_mail()`
- Uses HTML content type
- Builds email with template

**`build_email_content( $message, $data )`**
- Generates HTML email from template
- Includes header, content, footer
- Adds "View Order Details" button if order_id present

### Email Logic Flow

```php
// 1. Create notification
$notification_id = $notification_service->create(
    $user_id,
    $type,
    $title,
    $message,
    $data
);

// 2. Check if email should be sent
if ( $this->should_send_email( $user_id, $type ) ) {
    // 3. Send email
    $this->send_email( $user_id, $title, $message, $data );
}
```

## Troubleshooting

### Emails Not Sending

**Problem:** No emails received by anyone

**Solutions:**
1. Check notification toggle is enabled in settings
2. Test WordPress email with password reset
3. Install SMTP plugin (WP Mail SMTP or FluentSMTP)
4. Check PHP `mail()` function is available
5. Review server mail logs

### Emails Going to Spam

**Problem:** Emails arrive in spam folder

**Solutions:**
1. Use SMTP plugin with authentication
2. Configure SPF record for your domain
3. Set up DKIM signing
4. Use domain email address (not Gmail/Yahoo)
5. Test with mail-tester.com

### Wrong Recipients

**Problem:** Emails sent to incorrect users

**Solutions:**
1. Verify user email addresses in WordPress
2. Check notification code for correct recipient
3. Clear any caching plugins
4. Review error logs

## Developer Hooks

### Filter Email Content

```php
/**
 * Modify email content before sending.
 *
 * @param string $email_content HTML email content.
 * @param string $subject       Email subject.
 * @param int    $user_id       Recipient user ID.
 * @param array  $data          Email data.
 */
add_filter( 'wpss_notification_email_content', function( $email_content, $subject, $user_id, $data ) {
    // Modify email content
    return $email_content;
}, 10, 4 );
```

### Action on Notification Created

```php
/**
 * Fires when notification is created.
 *
 * @param int    $notification_id Notification ID.
 * @param int    $user_id         User ID.
 * @param string $type            Notification type.
 * @param array  $data            Notification data.
 */
add_action( 'wpss_notification_created', function( $notification_id, $user_id, $type, $data ) {
    // Custom action when notification created
}, 10, 4 );
```

## Next Steps

- **[Email Types](email-types.md)** - Details on all 8 notification types
- **[In-App Notifications](in-app-notifications.md)** - Configure notification center
- **[Platform Settings](../platform-settings/general-settings.md)** - Global settings

---

*Documentation based on plugin version 1.0.0*
