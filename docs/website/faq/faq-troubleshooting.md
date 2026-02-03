# FAQ & Troubleshooting

Find answers to common questions and solutions to issues you may encounter with WP Sell Services.

## General Questions

### What e-commerce plugin do I need?

**Free Version:**
- Requires WooCommerce (version 6.0 or higher)
- WooCommerce handles cart, checkout, and payments

**Pro Version:**
- Choose from multiple platforms:
  - WooCommerce
  - Easy Digital Downloads (EDD)
  - FluentCRM Cart
  - SureCart
  - **Standalone mode** (built-in payments via Stripe/PayPal/Razorpay)

You can switch platforms anytime from **Settings → E-commerce**.

### Does it work with my theme?

Yes! WP Sell Services works with any WordPress theme. The plugin:
- Uses standard WordPress templates
- Includes default styling
- Supports template overrides for customization
- Works with page builders (Elementor, Beaver Builder, etc.)

If you experience styling issues, see [Template Customization](../customization/template-overrides.md).

### Can I use it without WooCommerce?

**Free version**: No, WooCommerce is required.

**Pro version**: Yes! Use standalone mode with direct payment processing:
1. Go to **Settings → E-commerce → Platform**
2. Select **Standalone Mode**
3. Configure payment gateway (Stripe, PayPal, or Razorpay)
4. Save settings

No shopping cart plugin needed in standalone mode.

### How do I migrate from another marketplace plugin?

**Manual Migration:**
1. Export data from your current plugin (if available)
2. Install WP Sell Services
3. Create vendors and services manually or via import

**Pro Migration Tools:**
- Import services from CSV
- Bulk vendor creation
- Order history import

Contact support for migration assistance from specific plugins.

### Is it GDPR compliant?

Yes! WP Sell Services includes GDPR features:
- User data export (WordPress privacy tools)
- User data erasure (WordPress privacy tools)
- Privacy policy integration
- Cookie consent compatibility
- Data retention settings
- Anonymized analytics export option

Configure in **Settings → Privacy**.

### What languages are supported?

**Plugin Translation:**
- Translation-ready (all strings use gettext)
- Includes POT file for translators
- Works with WPML, Polylang, TranslatePress
- RTL language support

**Available Translations:**
- English (default)
- Spanish
- French
- German
- **[PRO]** Community translations for 15+ languages

Contribute translations at translate.wordpress.org.

## Setup & Configuration

### Pages aren't displaying correctly

**Symptoms:** Service listings, vendor profiles, or order pages show 404 or incorrect content.

**Solution 1: Regenerate Permalinks**
1. Go to **Settings → Permalinks**
2. Click **Save Changes** (no changes needed)
3. Visit pages again

**Solution 2: Check Page Assignments**
1. Go to **WP Sell Services → Settings → Pages**
2. Verify each page is assigned:
   - Services Archive
   - Vendor Dashboard
   - Buyer Dashboard
   - Submit Service
   - Buyer Requests
3. If pages are missing, create them with correct shortcodes
4. Reassign in settings

**Solution 3: Theme Compatibility**
1. Temporarily switch to Twenty Twenty-Four theme
2. If pages work, your theme has compatibility issues
3. Enable **Theme Compatibility Mode** in **Settings → Advanced**
4. Or override templates in your theme (see [Template Overrides](../customization/template-overrides.md))

### Shortcodes showing as text

**Symptoms:** Instead of content, you see `[wpss_services]` or similar text.

**Causes & Solutions:**

**Cause 1: Plugin not activated**
- Check **Plugins → Installed Plugins**
- Activate WP Sell Services

**Cause 2: Invalid shortcode**
- Verify shortcode spelling: `[wpss_services]` not `[wp_services]`
- Check [Shortcodes Reference](../customization/shortcodes.md) for correct syntax

**Cause 3: Page builder conflict**
- Some page builders encode shortcodes
- Use the page builder's shortcode widget instead of text widget
- Or add shortcode via page builder's WordPress block

**Cause 4: Visual editor issue**
- Switch to Text/Code editor
- Paste shortcode again
- Update page

### WooCommerce cart not working with services

**Symptoms:** Add to cart button doesn't work, or cart shows errors.

**Solution 1: Clear WooCommerce Cache**
```
WooCommerce → Status → Tools → Clear transients
```

**Solution 2: Regenerate Product Links**
1. Go to **WP Sell Services → Settings → E-commerce**
2. Click **Regenerate Product Links**
3. Wait for completion message

**Solution 3: Check WooCommerce Version**
- Requires WooCommerce 6.0+
- Update WooCommerce if outdated

**Solution 4: Disable Conflicting Plugins**
- Temporarily disable other WooCommerce extensions
- Test add to cart
- Re-enable plugins one by one to identify conflict

**Solution 5: Check Product Settings**
- Go to **WooCommerce → Products**
- Find service products (named like services)
- Verify they're published and in stock

### Vendor registration form not appearing

**Symptoms:** Vendor signup form doesn't show on registration page.

**Solution 1: Enable Vendor Registration**
1. Go to **Settings → Vendors → Registration**
2. Enable **Allow Vendor Registration**
3. Save settings

**Solution 2: Check WordPress Registration**
1. Go to **Settings → General**
2. Enable **Anyone can register**
3. Save settings

**Solution 3: Verify Shortcode**
- Registration page should have: `[wpss_vendor_registration]`
- Or use WordPress default registration with automatic vendor role assignment

**Solution 4: Admin Approval Required**
- If registration requires approval, users won't see vendor features until approved
- Check **Settings → Vendors → Registration → Approval Mode**

**Solution 5: Role Conflict**
- User might already have an account
- Users can't register as vendor if already registered
- Admins must assign vendor role manually in **Users → Edit User**

## Order Issues

### Order stuck in pending

**Symptoms:** Order remains in "Pending Acceptance" status indefinitely.

**Causes & Solutions:**

**Cause 1: Vendor hasn't accepted**
- Vendors must manually accept orders
- Check if vendor received notification email
- Vendor can accept in **Vendor Dashboard → Orders → Accept**

**Cause 2: Email notifications not working**
- Test email: **Settings → Email → Send Test Email**
- If test fails, configure SMTP plugin (WP Mail SMTP recommended)
- Check spam folder

**Cause 3: Auto-acceptance disabled**
- Enable in **Settings → Orders → Auto-Accept Orders**
- Orders auto-accept after X hours

**Cause 4: Payment not confirmed**
- Check WooCommerce order status
- Order must be "Processing" or "Completed" in WooCommerce
- If payment pending, resolve payment issue first

**Solution: Manual Status Change (Admins)**
1. Go to **WP Sell Services → Orders**
2. Click order
3. Change status to "In Progress"
4. Save

### Buyer can't submit requirements

**Symptoms:** Requirements form doesn't appear or file upload fails.

**Solution 1: Check Order Status**
- Requirements can only be submitted after vendor accepts
- Order status must be "Accepted" or "In Progress"

**Solution 2: File Upload Issues**
1. Check file size (default max: 50MB)
2. Check file type (allowed types in **Settings → Files**)
3. Increase PHP upload limits if needed:
   ```
   upload_max_filesize = 100M
   post_max_size = 100M
   ```

**Solution 3: Permissions**
- Verify buyer is logged in
- Buyer must be the order owner

**Solution 4: Already Submitted**
- Requirements can only be submitted once
- If resubmission needed, buyer must contact vendor
- Vendor can request updated requirements

**Solution 5: Check Requirements Form**
- Admin: Go to **Settings → Orders → Requirements**
- Verify **Enable Requirements** is checked
- Check if custom fields are configured correctly

### Delivery files not uploading

**Symptoms:** Vendor can't upload delivery files, or upload fails.

**Solution 1: File Size Limits**
- Check **Settings → Files → Max File Size**
- Increase if needed (requires PHP limit increase)
- Default: 50MB per file

**Solution 2: File Type Restrictions**
- Check allowed file types: **Settings → Files → Allowed Types**
- Add file extension if missing (e.g., `.psd`, `.ai`)

**Solution 3: Storage Quota**
- **[PRO]** If using cloud storage, check storage quota
- Switch to local uploads temporarily: **Settings → Files → Storage**

**Solution 4: Server Upload Limits**
Add to `.htaccess` (Apache):
```apache
php_value upload_max_filesize 100M
php_value post_max_size 100M
php_value max_execution_time 300
```

Or `php.ini`:
```ini
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
max_input_time = 300
```

**Solution 5: Permissions**
- Check WordPress uploads folder permissions: `wp-content/uploads/`
- Should be `755` or `775`
- Fix: `chmod -R 755 wp-content/uploads`

### Auto-completion not working

**Symptoms:** Orders don't auto-complete after buyer acceptance.

**Solution 1: Enable Auto-Completion**
1. Go to **Settings → Orders → Completion**
2. Enable **Auto-Complete After Acceptance**
3. Set delay (e.g., 24 hours)
4. Save settings

**Solution 2: Check WP-Cron**
- Auto-completion requires WordPress cron
- Test cron: **Tools → Site Health → Info → Cron**
- If disabled, enable in `wp-config.php`:
  ```php
  define( 'DISABLE_WP_CRON', false );
  ```

**Solution 3: Setup Real Cron**
For better reliability:
1. Disable WP-Cron: `define( 'DISABLE_WP_CRON', true );`
2. Add to server cron:
   ```bash
   */15 * * * * wget -q -O - https://example.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
   ```

**Solution 4: Manual Completion**
- Buyers can manually mark complete
- Or wait for review window to expire (auto-accepts delivery)

## Vendor Issues

### Can't create services

**Symptoms:** Vendor can't access service creation form or submission fails.

**Solution 1: Check Vendor Status**
1. Go to **Users → All Users**
2. Find vendor user
3. Verify role is **Vendor** or **Administrator**
4. Check vendor approval status

**Solution 2: Enable Service Creation**
1. Go to **Settings → Vendors → Capabilities**
2. Enable **Allow Vendors to Create Services**
3. Save settings

**Solution 3: Service Limits**
- Free version: Check service limits in **Settings → Vendors → Limits**
- Vendor may have reached max services
- Increase limit or upgrade plan

**Solution 4: Moderation Required**
- If moderation enabled, services go to **Pending** status
- Admins must approve: **Services → Pending**
- Vendor won't see service published until approved

**Solution 5: Form Validation**
- Check browser console for JavaScript errors
- Disable conflicting plugins
- Try different browser

### Services not appearing in search

**Symptoms:** Published services don't show in search results or listings.

**Solution 1: Check Service Status**
1. Go to **WP Sell Services → Services**
2. Verify service status is **Published** (not Draft or Pending)
3. If Pending, approve the service

**Solution 2: Rebuild Search Index**
1. Go to **Settings → Advanced → Search**
2. Click **Rebuild Search Index**
3. Wait for completion

**Solution 3: Category Assignment**
- Verify service has category assigned
- Services without categories may not appear in filtered views
- Edit service → Assign category → Update

**Solution 4: Vacation Mode**
- Check if vendor is in vacation mode
- Services from vendors in vacation mode are hidden
- Disable in **Vendor Dashboard → Settings → Vacation Mode**

**Solution 5: Cache Issues**
- Clear site cache (if using caching plugin)
- Clear browser cache
- Check if caching plugin excludes WP Sell Services pages

### Earnings not showing correctly

**Symptoms:** Vendor earnings display incorrect amounts or don't update.

**Solution 1: Check Commission Settings**
1. Go to **Settings → Commission**
2. Verify commission rate
3. Check if vendor has custom rate: **Vendors → Edit → Commission**

**Solution 2: Payment Status**
- Earnings only show for completed orders
- Check order status: **Vendor Dashboard → Orders**
- Orders in progress don't add to available balance

**Solution 3: Withdrawal History**
- Check if earnings were already withdrawn
- View in **Vendor Dashboard → Earnings → History**

**Solution 4: Recalculate Earnings**
1. Go to **WP Sell Services → Settings → Advanced**
2. Click **Recalculate All Earnings**
3. Wait for completion (may take time for many orders)

**Solution 5: Clearance Period**
- Earnings may be in clearance period
- Check **Settings → Payments → Clearance Period**
- Funds available after clearance (e.g., 14 days after order completion)

### Withdrawal request pending

**Symptoms:** Vendor withdrawal request stuck in pending status.

**Explanation:**
- Withdrawal requests require manual admin approval (for security)
- This is expected behavior

**Admin Action Required:**
1. Go to **WP Sell Services → Withdrawals**
2. Review pending requests
3. Verify vendor payment details
4. Process payment externally (bank transfer, PayPal, etc.)
5. Mark withdrawal as **Approved** or **Completed**
6. Or reject with reason

**Auto-Approval (Pro):**
- **[PRO]** Enable auto-approval for trusted vendors
- Go to **Settings → Payments → Auto-Approve Withdrawals**
- Set minimum vendor tier or order count
- Requires payment gateway integration

## Technical Issues

### PHP version compatibility

**Minimum Requirements:**
- PHP 7.4 or higher
- Recommended: PHP 8.0+

**Check PHP Version:**
1. Go to **Tools → Site Health → Info → Server**
2. Look for PHP version

**Upgrade PHP:**
- Contact hosting provider to upgrade
- Most hosts allow PHP version selection in control panel

**Compatibility Warnings:**
- PHP 7.4: Basic compatibility (deprecated)
- PHP 8.0+: Full support, better performance
- PHP 8.2+: Recommended for Pro features

### Plugin conflicts and debugging

**Common Conflicts:**
- Other marketplace plugins
- Custom user role managers
- Heavy page builders
- Aggressive caching plugins

**Debug Mode:**
Enable WordPress debug mode to identify issues:

Add to `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check log: `wp-content/debug.log`

**Conflict Testing:**
1. Deactivate all plugins except WP Sell Services
2. Test if issue persists
3. Reactivate plugins one by one
4. Identify conflicting plugin

**Theme Conflict Testing:**
1. Switch to Twenty Twenty-Four theme
2. Test functionality
3. If works, theme has conflict
4. Enable **Theme Compatibility Mode** in settings

### Template override not working

**Symptoms:** Custom template changes don't appear on frontend.

**Solution 1: Verify File Location**
Correct structure:
```
your-theme/
└── wp-sell-services/
    ├── single-service.php
    ├── archive-services.php
    └── vendor-dashboard.php
```

**Solution 2: Clear Cache**
- Clear all caches (site, server, browser)
- Disable caching temporarily for testing

**Solution 3: Check Template Hierarchy**
- Template must match plugin template name exactly
- Check plugin templates: `wp-sell-services/templates/`

**Solution 4: Force Template Refresh**
1. Rename template file
2. Refresh page (should use default)
3. Rename back to original
4. Refresh again

See [Template Override Guide](../customization/template-overrides.md) for details.

### REST API returning 401

**Symptoms:** API requests return "Unauthorized" error.

**Solution 1: Authentication Required**
- REST API requires authentication
- Use cookie auth (logged in user) with nonce
- Or use Application Password

**Solution 2: Nonce Issues**
For JavaScript requests:
```javascript
headers: {
    'X-WP-Nonce': wpApiSettings.nonce
}
```

Verify nonce is localized:
```php
wp_localize_script( 'my-script', 'wpApiSettings', [
    'nonce' => wp_create_nonce( 'wp_rest' ),
] );
```

**Solution 3: Application Password**
1. Generate password: **Users → Profile → Application Passwords**
2. Use in Authorization header:
   ```
   Authorization: Basic base64(username:password)
   ```

**Solution 4: Permalink Issues**
- Ensure permalinks are enabled (not default `?p=123`)
- Regenerate: **Settings → Permalinks → Save**

**Solution 5: Server Configuration**
Some servers block authorization headers. Add to `.htaccess`:
```apache
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
```

### Email notifications not sending

**Symptoms:** Users don't receive order notifications, messages, etc.

**Solution 1: Test Email**
1. Go to **Settings → Email**
2. Click **Send Test Email**
3. Check if received

**Solution 2: Configure SMTP**
WordPress mail() function is unreliable. Use SMTP:

1. Install **WP Mail SMTP** plugin
2. Configure with:
   - Gmail SMTP
   - SendGrid
   - Mailgun
   - Amazon SES
3. Test email again

**Solution 3: Check Email Settings**
1. Go to **Settings → Email → Notifications**
2. Verify enabled notifications:
   - ☑ Order Created
   - ☑ Order Status Changed
   - ☑ New Message
   - etc.
3. Check recipient roles are correct

**Solution 4: Spam Folder**
- Check spam/junk folders
- Add site email to contacts
- Use professional SMTP (improves deliverability)

**Solution 5: Server Mail Limits**
- Some hosts limit email sending
- Check host email logs
- Contact host if emails are being blocked

**Solution 6: Email Log**
**[PRO]** Check email log:
1. Go to **Settings → Email → Log**
2. View sent emails and delivery status
3. Identify failed emails

## Common Error Messages

### "Commission rate not configured"

**Solution:**
1. Go to **Settings → Commission**
2. Set default commission rate (e.g., 20%)
3. Save settings

### "Service approval required"

**Not an error** - Service submitted for admin review.

**Admin action:**
1. Go to **WP Sell Services → Services**
2. Filter by **Pending Review**
3. Review and approve/reject services

### "Insufficient balance"

**Cause:** Vendor trying to withdraw more than available balance.

**Solution:**
- Check available balance in **Vendor Dashboard → Earnings**
- Wait for orders to complete and clear
- Withdraw only available amount

### "Order cannot be cancelled"

**Causes:**
- Order already delivered
- Order already completed
- Cancellation window expired

**Solution:**
- Orders in late stages can't be cancelled
- Use dispute system instead: **Order → Open Dispute**

### "File type not allowed"

**Solution:**
1. Go to **Settings → Files → Allowed File Types**
2. Add file extension (e.g., `.psd`, `.ai`, `.zip`)
3. Save settings
4. Try upload again

## Getting Support

### Documentation

Browse complete documentation:
- [Getting Started Guide](../getting-started/installation.md)
- [Settings Reference](../settings/general-settings.md)
- [Developer Guide](../developer-guide/hooks-filters.md)

### Support Channels

**Free Version:**
- WordPress.org support forum
- Documentation site
- Community forums

**Pro Version:**
- Priority email support
- Live chat support
- Dedicated support portal
- Phone support (Enterprise plan)

### Before Contacting Support

Provide this information for faster resolution:

1. **WordPress Environment:**
   - WordPress version
   - PHP version
   - Active theme
   - Active plugins

2. **WP Sell Services:**
   - Plugin version (free or pro)
   - Settings configuration (screenshots)

3. **Issue Details:**
   - Description of problem
   - Steps to reproduce
   - Expected vs actual behavior
   - Screenshots or screen recording
   - Error messages (from debug.log)

4. **What You've Tried:**
   - Troubleshooting steps already attempted
   - Results of each attempt

### System Information

Get system info for support:
1. Go to **WP Sell Services → Settings → System Status**
2. Click **Copy System Info**
3. Paste in support ticket

## Related Documentation

- [Installation Guide](../getting-started/installation.md)
- [Settings Overview](../settings/general-settings.md)
- [Order Workflow](../order-workflow/order-lifecycle.md)
- [Vendor Guide](../vendor-features/vendor-dashboard.md)
