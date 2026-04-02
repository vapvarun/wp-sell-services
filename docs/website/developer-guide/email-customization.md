# Email Customization Guide

Customize every aspect of WP Sell Services emails -- from sender details and branding to the content of individual notification types. This guide covers template overrides, content filters, and header/footer customization.

## Email Architecture

Every email sent by the plugin flows through `EmailService` (`src/Services/EmailService.php`), which:

1. Loads the HTML template from `templates/emails/{type}.php`
2. Wraps it with the shared header (`email-header.php`) and footer (`email-footer.php`)
3. Applies content filters
4. Sends via `wp_mail()` (which uses your configured SMTP plugin if installed)

---

## Template Overrides

All email templates are theme-overridable. Copy the template to your theme to customize the HTML structure and design.

### Override Path

```
Plugin default:  wp-sell-services/templates/emails/{template}.php
Theme override:  yourtheme/wp-sell-services/emails/{template}.php
Child theme:     yourchildtheme/wp-sell-services/emails/{template}.php
```

Child theme overrides take priority over parent theme, which takes priority over plugin defaults.

### Available Templates

**26 HTML templates** in `templates/emails/`:

| Template | Notification Type |
|----------|-------------------|
| `new-order.php` | New order placed |
| `order-in-progress.php` | Vendor started work |
| `delivery-ready.php` | Delivery submitted for review |
| `order-completed.php` | Order completed |
| `order-cancelled.php` | Order cancelled |
| `requirements-submitted.php` | Buyer submitted project requirements |
| `requirements-reminder.php` | Reminder to submit requirements |
| `revision-requested.php` | Buyer requested changes |
| `cancellation-requested.php` | Cancellation request filed |
| `new-message.php` | New message in order conversation |
| `dispute-opened.php` | Dispute opened |
| `dispute-escalated.php` | Dispute escalated to admin |
| `seller-level-promotion.php` | Vendor reached new seller level |
| `moderation-pending.php` | Service submitted for review |
| `moderation-approved.php` | Service approved by admin |
| `moderation-rejected.php` | Service rejected by admin |
| `moderation-response.php` | Vendor responded to moderation feedback |
| `vendor-contact.php` | Message via vendor contact form |
| `withdrawal-requested.php` | Vendor requested payout |
| `withdrawal-approved.php` | Payout approved |
| `withdrawal-rejected.php` | Payout rejected |
| `withdrawal-auto.php` | Automatic withdrawal processed |
| `generic.php` | Generic notification (fallback) |
| `test-email.php` | Test email from settings |
| `email-header.php` | Shared header with logo and branding |
| `email-footer.php` | Shared footer with links |

**10 plain text templates** in `templates/emails/plain/`:

Plain text versions are used by email clients that do not support HTML. They follow the same naming convention and override path.

### Example: Customizing the Order Completed Email

```bash
# 1. Create the override directory in your theme
mkdir -p yourtheme/wp-sell-services/emails/

# 2. Copy the template
cp wp-sell-services/templates/emails/order-completed.php yourtheme/wp-sell-services/emails/

# 3. Edit the copy in your theme
```

---

## Header and Footer Customization

The shared header and footer wrap every email. Override them to change the logo, colors, or branding across all notifications at once.

### Overriding the Header

Copy `templates/emails/email-header.php` to `yourtheme/wp-sell-services/emails/email-header.php` and customize:

- Logo image and link
- Background color and accent colors
- Header layout and spacing

### Overriding the Footer

Copy `templates/emails/email-footer.php` to `yourtheme/wp-sell-services/emails/email-footer.php` and customize:

- Footer text and links
- Social media links
- Legal/compliance text
- Unsubscribe link (if applicable)

### Header Variables Filter

Modify the variables available in the email header template:

```php
add_filter( 'wpss_email_header_vars', function( $vars, $type ) {
    // Add a custom banner for order-related emails
    if ( str_starts_with( $type, 'order' ) ) {
        $vars['banner_text'] = 'Order Update';
    }
    return $vars;
}, 10, 2 );
```

---

## Content Filters

Modify email content programmatically without overriding template files. These filters are useful for adding dynamic content, custom messages, or conditional sections.

### General Email Filters

| Filter | Parameters | Purpose |
|--------|-----------|---------|
| `wpss_email_subject` | `string $subject, string $type, string $to` | Change the email subject line |
| `wpss_email_from_name` | `string $from_name` | Change the sender display name |
| `wpss_email_data` | `array $email` | Modify the entire email data array before sending |
| `wpss_notification_email_content` | `string $content, string $subject, int $user_id, array $data` | Modify any notification email content |

### Vendor-Specific Email Filters

| Filter | Parameters | Purpose |
|--------|-----------|---------|
| `wpss_vendor_welcome_email_content` | `string $content, object $user, string $platform_name` | Customize the welcome email for new vendors |
| `wpss_vendor_pending_email_content` | `string $content, object $user, string $platform_name` | Customize the "application pending" email |
| `wpss_vendor_approved_email_content` | `string $content, object $user, string $platform_name` | Customize the vendor approval email |
| `wpss_vendor_rejected_email_content` | `string $content, object $user, string $platform_name` | Customize the vendor rejection email |
| `wpss_admin_vendor_notification_content` | `string $content, object $user` | Customize the admin notification when a new vendor registers |

---

## Common Customization Examples

### Change the Sender Name

```php
add_filter( 'wpss_email_from_name', function( $name ) {
    return 'DesignHub Marketplace';
} );
```

### Change Subject Lines

```php
add_filter( 'wpss_email_subject', function( $subject, $type, $to ) {
    if ( 'new_order' === $type ) {
        return 'You have a new project on DesignHub!';
    }
    return $subject;
}, 10, 3 );
```

### Add Onboarding Content to Vendor Welcome Email

```php
add_filter( 'wpss_vendor_welcome_email_content', function( $content, $user, $platform ) {
    $content .= '<h3>Getting Started</h3>';
    $content .= '<ul>';
    $content .= '<li>Complete your profile with a photo and bio</li>';
    $content .= '<li>Create your first service listing</li>';
    $content .= '<li>Set up your payment method for withdrawals</li>';
    $content .= '</ul>';
    return $content;
}, 10, 3 );
```

### Add Custom Footer Text to All Emails

```php
add_filter( 'wpss_notification_email_content', function( $content, $subject, $user_id, $data ) {
    $content .= '<p style="color:#999;font-size:12px;">Need help? Contact us at support@example.com</p>';
    return $content;
}, 10, 4 );
```

### Customize the Vendor Rejection Email

```php
add_filter( 'wpss_vendor_rejected_email_content', function( $content, $user, $platform ) {
    // Replace the default content entirely
    $content  = '<p>Hi ' . esc_html( $user->display_name ) . ',</p>';
    $content .= '<p>Your application to sell on ' . esc_html( $platform ) . ' was not approved at this time.</p>';
    $content .= '<p>Common reasons include incomplete profile information or services that do not match our marketplace categories.</p>';
    $content .= '<p>You are welcome to reapply after updating your profile.</p>';
    return $content;
}, 10, 3 );
```

---

## Email Trigger Hooks

Emails are sent in response to specific action hooks. If you need to run custom logic alongside an email (or conditionally suppress an email), hook into the same action:

| Email Type | Triggered By |
|-----------|-------------|
| New Order | `wpss_order_status_changed` (to `pending_requirements`) |
| Delivery Ready | `wpss_delivery_submitted` |
| Order Completed | `wpss_order_completed` |
| Dispute Opened | `wpss_dispute_opened` |
| Vendor Registered | `wpss_vendor_registered` |
| Withdrawal Requested | `wpss_withdrawal_requested` |
| Level Promotion | `wpss_vendor_level_promoted` |
| Requirements Reminder | `wpss_send_requirements_reminder_email` |

---

## WooCommerce Email Integration **[PRO]**

When using WooCommerce as the e-commerce platform, marketplace emails can optionally integrate with WooCommerce's email system:

- Emails inherit your WooCommerce email template and branding
- Configure subject lines at **WooCommerce > Settings > Emails**
- Works with WooCommerce email customizer plugins
- Provides a consistent look across store and marketplace emails

---

## Testing Emails

1. Go to **WP Sell Services > Settings > Emails**
2. Click **Send Test Email**
3. Check your inbox (and spam folder)

The test email uses `emails/test-email.php` and shows your current header/footer branding. Use it to verify that your template overrides and SMTP configuration are working correctly.

---

## Related Documentation

- [Email Notification Types](../notifications-emails/email-types.md) -- All 27 email types explained
- [Email Configuration](../notifications-emails/email-configuration.md) -- SMTP setup and delivery settings
- [Hooks and Filters Reference](hooks-filters.md) -- Complete hooks reference
- [Theme Integration](theme-integration.md) -- Template override system
