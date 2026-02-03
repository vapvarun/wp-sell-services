# Email Notifications

Configure automated email notifications that keep buyers and vendors informed throughout the order lifecycle.

## Email System Overview

WP Sell Services sends automated emails for key marketplace events, ensuring smooth communication between buyers, vendors, and admins.

![Email Settings Tab](../images/settings-emails-tab.png)

## Available Email Notifications

### Order-Related Emails

| Email Type | Recipient | Trigger |
|------------|-----------|---------|
| New Order | Buyer + Vendor | Order placed |
| Order Confirmation | Buyer | Payment confirmed |
| Requirements Needed | Buyer | Vendor requests requirements |
| Order Started | Buyer | Vendor begins work |
| Delivery Submitted | Buyer | Vendor uploads delivery |
| Delivery Accepted | Vendor | Buyer accepts delivery |
| Revision Requested | Vendor | Buyer requests changes |
| Revision Delivered | Buyer | Vendor submits revision |
| Order Completed | Buyer + Vendor | Order finalized |
| Order Cancelled | Buyer + Vendor | Order cancelled |

### Vendor Emails

| Email Type | Trigger | Purpose |
|------------|---------|---------|
| Vendor Registration | Account created | Welcome message |
| Vendor Approved | Admin approval | Account activation |
| Vendor Rejected | Admin rejection | Application denial |
| Service Published | Service goes live | Confirmation |
| Service Rejected | Admin rejects service | Moderation notice |
| Withdrawal Request | Vendor requests payout | Confirmation |
| Withdrawal Processed | Payout completed | Payment receipt |

### Buyer Emails

| Email Type | Trigger | Purpose |
|------------|---------|---------|
| Request Posted | Buyer posts request | Confirmation |
| Request Expired | Request deadline passed | Reminder to extend |
| Offer Received | Vendor sends offer | New offer notification |
| Review Reminder | Order auto-completed | Prompt for feedback |

### Dispute Emails

| Email Type | Recipient | Trigger |
|------------|-----------|---------|
| Dispute Opened | Buyer + Vendor + Admin | Dispute initiated |
| Dispute Message | Relevant party | New message in dispute |
| Dispute Resolved | Buyer + Vendor | Admin resolves dispute |

## Configuring Email Notifications

### Enable/Disable Emails

Control which notifications are sent.

**Configuration:**
1. Go to **WP Sell Services → Settings → Emails**
2. See list of all email types
3. Toggle checkbox to enable/disable each
4. Click **Save Changes**

**Recommendations:**
- Keep all order-related emails enabled
- Disable redundant notifications if using other systems
- Test disabled emails before going live

### Email Content Customization

Personalize email templates to match your brand.

**Customize Email:**
1. Navigate to **Settings → Emails**
2. Click on email name (e.g., "New Order Notification")
3. Edit available fields:
   - **Subject Line** - Email subject
   - **Heading** - Main heading in email
   - **Body Text** - Email content
   - **Footer Text** - Email footer
4. Use available placeholders
5. Preview email
6. Save changes

**Available Placeholders:**

| Placeholder | Description | Example |
|-------------|-------------|---------|
| `{buyer_name}` | Buyer's name | John Smith |
| `{vendor_name}` | Vendor's name | Jane Doe |
| `{order_id}` | Order number | #12345 |
| `{order_total}` | Order amount | $150.00 |
| `{service_title}` | Service name | Logo Design |
| `{delivery_date}` | Expected delivery | January 15, 2026 |
| `{order_url}` | Link to order | Full URL |
| `{site_name}` | Platform name | Creative Hub |
| `{site_url}` | Platform URL | https://example.com |
| `{buyer_email}` | Buyer's email | buyer@example.com |
| `{vendor_email}` | Vendor's email | vendor@example.com |

**Example Subject Lines:**
- `New Order #{order_id} - {service_title}`
- `{vendor_name} delivered your order`
- `Revision requested for order #{order_id}`

## Email Templates

Customize the full HTML email design.

### Template Location

Email templates are located in:
```
wp-sell-services/templates/emails/
```

**Available Templates:**
- `new-order.php` - New order notification
- `order-delivered.php` - Delivery submitted
- `order-completed.php` - Order finalized
- `revision-requested.php` - Revision needed
- `order-cancelled.php` - Cancellation notice
- `vendor-welcome.php` - New vendor welcome
- `dispute-opened.php` - Dispute notification
- `header.php` - Email header
- `footer.php` - Email footer

### Creating Template Overrides

Override default templates in your theme.

**Step-by-Step:**

1. **Create Directory** in your theme:
   ```
   your-theme/wp-sell-services/emails/
   ```

2. **Copy Template** from plugin to theme:
   ```
   Plugin: wp-sell-services/templates/emails/new-order.php
   Theme: your-theme/wp-sell-services/emails/new-order.php
   ```

3. **Edit Template** in theme directory
4. Changes persist through plugin updates

**Example: Customizing New Order Email**

```php
<?php
/**
 * New Order Email Template
 */

// Get order data
$order_id = $email_data['order_id'];
$order = wpss_get_order( $order_id );
?>

<h1>New Order Received!</h1>

<p>Hi <?php echo esc_html( $email_data['vendor_name'] ); ?>,</p>

<p>Great news! You have a new order:</p>

<table>
    <tr>
        <th>Order Number:</th>
        <td>#<?php echo esc_html( $order_id ); ?></td>
    </tr>
    <tr>
        <th>Service:</th>
        <td><?php echo esc_html( $email_data['service_title'] ); ?></td>
    </tr>
    <tr>
        <th>Buyer:</th>
        <td><?php echo esc_html( $email_data['buyer_name'] ); ?></td>
    </tr>
    <tr>
        <th>Amount:</th>
        <td><?php echo wpss_format_price( $email_data['order_total'] ); ?></td>
    </tr>
    <tr>
        <th>Delivery By:</th>
        <td><?php echo esc_html( $email_data['delivery_date'] ); ?></td>
    </tr>
</table>

<p>
    <a href="<?php echo esc_url( $email_data['order_url'] ); ?>"
       style="background: #0073aa; color: #fff; padding: 12px 24px; text-decoration: none;">
        View Order Details
    </a>
</p>
```

See [Template Overrides](../marketplace-display/template-overrides.md) for more details.

## Email Styling

Customize the appearance of all emails.

### Email Header and Footer

**Customize:**
1. Go to **Settings → Emails → Email Template**
2. Upload **Header Logo** (recommended: 300×100 pixels)
3. Set **Header Background Color**
4. Edit **Footer Text** (copyright, links, contact)
5. Set **Footer Background Color**
6. Save changes

**Example Footer:**
```
© 2026 Creative Hub Marketplace
Contact Support | Terms of Service | Privacy Policy
```

### Email Colors

Configure color scheme:

| Element | Default | Customizable |
|---------|---------|--------------|
| Primary Color | #0073aa | Yes |
| Text Color | #333333 | Yes |
| Background | #f7f7f7 | Yes |
| Link Color | #0073aa | Yes |
| Button Color | #0073aa | Yes |

**Change Colors:**
1. Navigate to **Settings → Emails → Styling**
2. Use color picker for each element
3. Preview changes
4. Save

### Custom CSS

**[PRO]** Add custom CSS to email templates.

**Add CSS:**
1. Go to **Settings → Emails → Custom CSS** **[PRO]**
2. Enter CSS rules
3. CSS applies to all email templates
4. Save changes

**Example CSS:**
```css
/* Custom email styles */
.order-table {
    border: 2px solid #0073aa;
    border-radius: 8px;
}

.cta-button {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    font-weight: bold;
}
```

## Email Sender Configuration

Set who emails appear to come from.

### From Name and Address

**Configuration:**
1. Go to **Settings → Emails → Sender**
2. Set **From Name** (e.g., "Creative Hub Marketplace")
3. Set **From Email** (e.g., "noreply@yourdomain.com")
4. Save changes

**Best Practices:**
- Use your domain (not Gmail/Yahoo)
- Match your platform name
- Use no-reply@ for automated emails
- Use support@ for reply-required emails

### Reply-To Address

Set where replies should go.

**Configuration:**
1. Set **Reply-To Email** (e.g., "support@yourdomain.com")
2. Ensure this inbox is monitored
3. Save changes

## Email Delivery Settings

Ensure emails reach recipients reliably.

### SMTP Configuration

Use SMTP for better email deliverability instead of PHP mail().

**Recommended SMTP Plugins:**
- **WP Mail SMTP** - Most popular, free version available
- **Easy WP SMTP** - Simple setup
- **Post SMTP** - Feature-rich

**SMTP Providers:**
- SendGrid (free up to 100/day)
- Mailgun (free up to 5,000/month)
- Amazon SES (pay as you go)
- Gmail SMTP (limited)

**Setup Steps:**
1. Install WP Mail SMTP plugin
2. Configure SMTP credentials
3. Test email delivery
4. Verify emails arrive correctly

### Email Logging

**[PRO]** Track all sent emails for debugging.

**Enable Logging:**
1. Go to **Settings → Emails → Logging** **[PRO]**
2. Enable **Email Log**
3. Set retention period (days)
4. Save changes

**View Logs:**
1. Navigate to **WP Sell Services → Email Logs**
2. See all sent emails with:
   - Recipient
   - Subject
   - Timestamp
   - Delivery status
   - Error messages (if failed)

## Testing Emails

Test email templates before going live.

### Send Test Email

**Process:**
1. Go to **Settings → Emails**
2. Click on email template
3. Click **Send Test Email**
4. Enter test recipient email
5. Click **Send**
6. Check inbox (and spam folder)

**What to Check:**
- Subject line displays correctly
- Placeholders replaced with data
- Styling appears as expected
- Links work properly
- Mobile responsive design
- Images load correctly

### Email Preview

Preview emails without sending:

1. Click email template name
2. Click **Preview**
3. See rendered email in browser
4. Test with different screen sizes

## Email Triggers and Hooks

For developers: customize when emails are sent.

### Email Trigger Hooks

```php
// Trigger custom email
do_action( 'wpss_send_email', 'email-template-name', $email_data );

// Prevent specific email
add_filter( 'wpss_send_email_new_order', '__return_false' );

// Modify email data before sending
add_filter( 'wpss_email_data_new_order', function( $data ) {
    $data['custom_field'] = 'Custom value';
    return $data;
} );

// Add custom email type
add_filter( 'wpss_email_types', function( $types ) {
    $types['custom_email'] = 'Custom Email Name';
    return $types;
} );
```

## Troubleshooting

### Emails Not Being Sent

**Check:**
1. Email notification is enabled in settings
2. Recipient email address is valid
3. PHP mail function works (test with plugin)
4. SMTP configured correctly (if using SMTP)
5. Check server mail logs
6. No email queue errors

### Emails Going to Spam

**Solutions:**
1. Configure SMTP with authenticated service
2. Add SPF record to DNS
3. Add DKIM record to DNS
4. Use domain email (not free provider)
5. Avoid spam trigger words in subject/body
6. Include unsubscribe link (for marketing emails)

### Placeholders Not Replacing

**Verify:**
1. Placeholder syntax correct (e.g., `{order_id}`)
2. Data available when email sent
3. No typos in placeholder names
4. Template file not cached
5. Clear cache and test again

### Styling Not Appearing

**Troubleshoot:**
1. Email client supports HTML (most do)
2. Inline CSS used (not external stylesheet)
3. Preview in multiple email clients
4. Test in Gmail, Outlook, Apple Mail
5. Use email testing tool (Litmus, Email on Acid)

## Related Documentation

- [Order Settings](order-settings.md) - When emails are triggered
- [Vendor Settings](vendor-settings.md) - Vendor notification preferences
- [Template Overrides](../marketplace-display/template-overrides.md) - Customizing templates
- [Advanced Settings](advanced-settings.md) - Email performance options

## Next Steps

After configuring email notifications:

1. Set up SMTP for reliable delivery
2. Customize email templates to match brand
3. Send test emails to team members
4. Monitor email delivery rates
5. Adjust based on recipient feedback
