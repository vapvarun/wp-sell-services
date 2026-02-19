# FAQ & Troubleshooting

Find answers to common questions and solutions to issues with WP Sell Services.

## General Questions

### What is WP Sell Services?

WP Sell Services is a Fiverr-style service marketplace plugin for WordPress. It allows you to create a platform where vendors offer services and buyers purchase them.

**Key Features:**
- Service marketplace with packages
- Order management system
- Buyer requests (like Fiverr requests)
- Vendor profiles and dashboards
- Review and rating system
- Dispute resolution
- Basic analytics
- WooCommerce integration

### Do I need WooCommerce?

**No.** The free version includes a built-in standalone checkout with Stripe, PayPal, and Offline payment gateways. No WooCommerce or any other e-commerce plugin is required.

**Pro Version:** Adds WooCommerce, EDD, FluentCart, and SureCart as alternative e-commerce platform adapters.

### What are the minimum requirements?

- WordPress 6.0 or higher
- PHP 8.0 or higher (PHP 8.1+ recommended)
- MySQL 5.7 or higher / MariaDB 10.2 or higher

### Is it multisite compatible?

Yes, WP Sell Services works on WordPress multisite networks. Each site in the network can run its own marketplace.

## Setup Issues

### Services not displaying

**Check these items:**

1. **Permalinks:**
   - Go to **Settings → Permalinks**
   - Click **Save Changes**
   - Try viewing services again

2. **Service Status:**
   - Services must be published (not draft or pending)
   - Check **Services → All Services** for status

3. **Category Assignment:**
   - Assign at least one category to each service
   - Go to **Services → Categories** to manage

4. **Cache:**
   - Clear site cache if using caching plugin
   - Clear browser cache

### Vendor registration not working

**Solutions:**

1. **Enable Registration:**
   - Go to **Settings → General**
   - Enable **Anyone can register**
   - Go to **Settings → Vendors**
   - Enable **Allow Vendor Registration**

2. **Check User Role:**
   - After registration, user should have `wpss_vendor` role
   - Admin can manually assign role: **Users → Edit User**

3. **Approval Mode:**
   - If **Require Admin Approval** is enabled
   - Admins must approve vendors: **Vendors → Pending**

### Pages showing 404 errors

**Fix:**

1. **Regenerate Permalinks:**
   - **Settings → Permalinks → Save Changes**

2. **Check Page Assignments:**
   - **Settings → Pages**
   - Verify all required pages are assigned
   - If missing, create pages with proper shortcodes

3. **Check Post Type Registration:**
   - Deactivate and reactivate plugin
   - Flush rewrite rules

## Order Issues

### Orders not appearing in vendor dashboard

**Check:**

1. **Order Status:**
   - Orders must have valid status in `wpss_orders` table
   - Check database for order existence

2. **Vendor ID:**
   - Verify order has correct `vendor_id`
   - Must match WordPress user ID

3. **Database Connection:**
   - Ensure database queries are working
   - Check WordPress debug.log for errors

### Buyer can't submit requirements

**Solutions:**

1. **Order Status:**
   - Requirements can only be submitted after vendor accepts order
   - Order must be "In Progress" status

2. **Already Submitted:**
   - Requirements can only be submitted once
   - Check if requirements already exist for this order

3. **File Upload Issues:**
   - Check file size limits: **Settings → Media**
   - Verify upload directory permissions: `wp-content/uploads/`
   - Increase PHP limits if needed:
     ```
     upload_max_filesize = 100M
     post_max_size = 100M
     ```

### Delivery files not uploading

**Troubleshoot:**

1. **File Size:**
   - Default max: 50MB per file
   - Increase in **Settings → Files**
   - Must also increase PHP limits

2. **File Type:**
   - Check allowed file types in settings
   - Add missing extensions: **Settings → Files → Allowed Types**

3. **Storage:**
   - Verify `wp-content/uploads/wpss/` directory exists
   - Check directory permissions (755 or 775)
   - Ensure sufficient disk space

4. **PHP Configuration:**
   ```php
   // Add to wp-config.php for debugging
   ini_set('upload_max_filesize', '100M');
   ini_set('post_max_size', '100M');
   ini_set('max_execution_time', 300);
   ```

## Buyer Request Issues

### Requests not expiring automatically

**Solution:**

Expiration requires WordPress cron to be functioning.

**Check WP-Cron:**
1. **Tools → Site Health → Info → wp-cron**
2. Verify cron is not disabled

**Enable Cron:**
```php
// In wp-config.php, ensure this is false or not present
define( 'DISABLE_WP_CRON', false );
```

**Setup Real Cron (recommended):**
```bash
# Add to server crontab
*/15 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

### Can't accept proposal

**Verify:**

1. **Request Status:**
   - Request must be 'open' or 'in_review'
   - Check `_wpss_status` post meta

2. **Proposal Status:**
   - Proposal must be 'pending' in `wpss_proposals` table
   - Not already accepted or rejected

3. **Ownership:**
   - You must be the request author
   - Check `post_author` field

4. **Not Expired:**
   - Request must not be past `expires_at` date

### Order not created after accepting proposal

**Check:**

1. **Database Permissions:**
   - Ensure WordPress can insert into `wpss_orders` table
   - Check database error logs

2. **Return Value:**
   - Check the `convert_to_order()` return value
   - `success => false` indicates specific error in `message`

3. **Debug Log:**
   - Enable WP_DEBUG and WP_DEBUG_LOG
   - Check `wp-content/debug.log` for errors

## Dispute Issues

### Can't open dispute

**Requirements:**

1. **Order Must Exist:**
   - Order ID is valid in `wpss_orders` table

2. **User Authorization:**
   - Must be order customer OR order vendor
   - Cannot open dispute if not involved in order

3. **One Per Order:**
   - Each order can only have one dispute
   - Check if dispute already exists

4. **No Status Restriction:**
   - Disputes can be opened at any order stage
   - No automatic time window enforcement

### Evidence not showing

**Verify:**

1. **Evidence Stored:**
   - Check `evidence` column in `wpss_disputes` table
   - Should contain valid JSON

2. **JSON Format:**
   - Evidence must be valid JSON array
   - Use `get_evidence()` method, not direct DB query

3. **Evidence Types:**
   - Supported: 'text', 'image', 'file', 'link'
   - Check for typos in type field

### Dispute resolution not processing refund

**Important:** The `resolve()` method does NOT process actual payments.

**You must manually:**
1. Process refund through WooCommerce:
   - **WooCommerce → Orders → Find Order → Refund**
2. OR use payment gateway admin panel
3. OR credit buyer's wallet (if Pro wallet enabled)

The dispute system only:
- Records the refund amount
- Updates dispute and order status
- Notifies parties

## Analytics Issues

### Service views not tracking

**Service views require manual implementation:**

```php
// Add to functions.php or custom plugin
add_action( 'template_redirect', function() {
    if ( is_singular( 'wpss_service' ) && ! is_admin() ) {
        $service_id = get_the_ID();
        $views = (int) get_post_meta( $service_id, '_wpss_views', true );
        update_post_meta( $service_id, '_wpss_views', $views + 1 );
    }
} );
```

Views are stored in `_wpss_views` post meta. Not tracked by default.

### Profile views not counting

**Profile views also require manual tracking:**

```php
add_action( 'wpss_vendor_profile_viewed', function( $vendor_id ) {
    $views = (int) get_user_meta( $vendor_id, '_wpss_profile_views', true );
    update_user_meta( $vendor_id, '_wpss_profile_views', $views + 1 );
} );
```

Fire the `wpss_vendor_profile_viewed` action when profile is viewed.

### Analytics data not updating

**Performance Tip:** Cache analytics to reduce database load.

**Clear Cache After Changes:**
```php
// Delete transients after order completion
delete_transient( 'wpss_admin_dashboard_stats' );
delete_transient( 'wpss_vendor_stats_' . $vendor_id . '_30days' );
```

## Email & Notification Issues

### Emails not sending

**Solutions:**

1. **Test WordPress Email:**
   - **Settings → Email → Send Test**
   - If fails, email is broken at WordPress level

2. **Configure SMTP:**
   - Install **WP Mail SMTP** plugin
   - Configure with Gmail, SendGrid, or Mailgun
   - Improves deliverability dramatically

3. **Check Spam:**
   - Check spam/junk folders
   - Emails from WordPress often marked as spam

4. **Enable Notifications:**
   - **Settings → Notifications**
   - Verify enabled for each event type

### Notifications going to wrong users

**Check:**

1. **Role Filtering:**
   - Notifications are role-specific
   - Admins, vendors, and buyers get different notifications

2. **Email Addresses:**
   - Verify user email addresses are correct
   - **Users → Edit User**

## Performance Issues

### Slow analytics dashboard

**Optimize:**

1. **Add Database Indexes:**
   ```sql
   ALTER TABLE wp_wpss_orders ADD INDEX idx_status (status);
   ALTER TABLE wp_wpss_orders ADD INDEX idx_vendor (vendor_id);
   ALTER TABLE wp_wpss_orders ADD INDEX idx_created (created_at);
   ```

2. **Use Caching:**
   - Cache analytics results in transients
   - Cache duration: 15-60 minutes

3. **Limit Results:**
   - Use `get_recent_orders(20)` not `get_recent_orders(1000)`
   - Paginate large result sets

### Slow search results

**Improve Search:**

1. **Use Search Service:**
   - Built-in `SearchService` optimizes queries

2. **Limit Search Scope:**
   - Search specific categories
   - Limit posts_per_page to 20-50

3. **Add Search Index:**
   - Consider search plugin like Relevanssi
   - Or ElasticSearch for large sites

## File Storage Issues

### Running out of disk space

**Solutions:**

1. **Increase Server Storage:**
   - Upgrade hosting plan
   - Contact hosting provider

2. **Set Retention Policy:**
   - Auto-delete old deliveries after 90 days
   - Manually archive completed orders

3. **Optimize Files:**
   - Compress large files before upload
   - Use efficient file formats
   - Remove unnecessary files

4. **Future: Cloud Storage:**
   - Cloud storage is planned for Pro version
   - Will offload files to S3/GCS/DigitalOcean

### Attachments missing after period

**Check:**

1. **File Cleanup:**
   - Do you have automatic cleanup enabled?
   - Check **Settings → Files → Retention**

2. **Manual Deletion:**
   - Were files manually deleted from server?

3. **Backup:**
   - Restore from backup if available

## Common Error Messages

### "Dispute already exists for this order"

**Meaning:** Each order can only have one dispute.

**Solution:**
- View existing dispute instead of creating new one
- Update existing dispute with additional evidence

### "Order not found"

**Causes:**
- Order ID doesn't exist in database
- Order was deleted
- Using wrong order ID

**Fix:**
- Verify order ID is correct
- Check `wpss_orders` table for order
- Use order number instead if searching

### "Invalid dispute status"

**Causes:**
- Trying to set status not in the 5 valid statuses
- Typo in status name

**Valid Statuses:**
- `open`
- `pending_review`
- `escalated`
- `resolved`
- `closed`

### "Evidence cannot be added to closed dispute"

**Meaning:** Disputes with status 'closed' cannot be modified.

**Solution:**
- Evidence can only be added before dispute is closed
- Contact admin to reopen if necessary

## Getting Help

### Documentation Resources

- **Getting Started:** [Installation Guide](../getting-started/installation.md)
- **Settings Reference:** [Platform Settings](../platform-settings/general-settings.md)
- **Developer Docs:** [Hooks & Filters](../developer-guide/hooks-filters.md)
- **REST API:** [API Reference](../developer-guide/rest-api.md)

### Enable Debug Mode

For troubleshooting, enable WordPress debug:

```php
// Add to wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check logs at: `wp-content/debug.log`

### System Information

Get system info for support:

1. **Tools → Site Health**
2. Go to **Info** tab
3. Review:
   - WordPress version
   - PHP version
   - Active plugins
   - Database info
   - File permissions

### Contact Support

**Before contacting support, have ready:**

1. WordPress version
2. PHP version
3. WP Sell Services version
4. Active theme name
5. List of active plugins
6. Error messages (from debug.log)
7. Steps to reproduce issue
8. Screenshots if applicable

**Support Channels:**
- **Free Version:** WordPress.org support forum
- **Pro Version:** Priority email support

---

**Can't find your answer?**

Search the full documentation or post in the WordPress.org support forum. For Pro customers, contact priority support directly.
