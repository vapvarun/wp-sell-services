# Advanced Settings

Configure data management, debugging, and learn about the automated background tasks that keep your marketplace running smoothly.

![Advanced Settings Tab](../images/settings-advanced-tab.png)

---

## Delete Data on Uninstall

By default, uninstalling the plugin keeps all your marketplace data intact. If you enable this option, uninstalling will permanently delete everything: services, orders, vendor profiles, reviews, conversations, earnings history, and all plugin settings.

### When to Enable This

- You are testing the plugin temporarily and want a clean removal
- You are shutting down the marketplace and moving to a different solution
- Compliance requires complete data removal

### When to Keep It Disabled

- You might reinstall the plugin later
- You need to preserve transaction records
- You want a safety net in case of accidental uninstall

### What Stays After Deletion

Even with this option enabled, some things are not removed:
- WordPress user accounts (buyers and vendors remain as WP users)
- Uploaded media files (images, documents in your media library)
- Payment records held by your payment processor (Stripe, PayPal, etc.)

**Important:** This is irreversible. Always export your data and create a database backup before uninstalling with this option enabled.

---

## Debug Mode

Enable detailed logging when you need to troubleshoot issues. When active, the plugin records information about orders, payments, emails, file uploads, and background tasks to your WordPress debug log.

1. Go to **WP Sell Services > Settings > Advanced**
2. Check **Enable Debug Mode**
3. Save Changes

**What gets logged:**
- Order creation and status changes
- Payment processing and commission calculations
- Email delivery attempts (success and failure)
- File upload operations
- Background task execution
- Errors and warnings

**Where to view logs:**
- **Free version:** Check the WordPress debug log at `wp-content/debug.log`
- **Pro version:** Go to **WP Sell Services > System > Logs** for a filterable log viewer

**Tip:** Enable debug mode only when troubleshooting. Disable it in normal operation to keep your logs clean and avoid any (minor) overhead.

---

## Max Upload Size

Set the maximum file size for uploads (delivery files, attachments, requirement files). The default is 50MB. This is capped by your server's PHP settings -- if your server allows only 25MB uploads, that will be the actual limit regardless of what you set here.

---

## Allowed File Types

Control which file types vendors and buyers can upload. By default, common formats are allowed: images (JPG, PNG, GIF), documents (PDF, DOC, DOCX), archives (ZIP), and design files (PSD, AI).

Add or remove file extensions to match your marketplace needs.

---

## Demo Content

### Import Demo Content

Quickly populate your marketplace with sample services, vendors, and categories for testing or demonstration purposes. Go to **Settings > Advanced** and click **Import Demo Content**.

### Delete Demo Content

When you are done testing, click **Delete Demo Content** to remove all sample data without affecting your real marketplace content.

---

## Automated Background Tasks

WP Sell Services runs three scheduled tasks automatically to keep your marketplace in good shape.

### Auto-Complete Orders

**Runs every hour.** If a buyer does not accept or request revisions on a delivered order within the configured time limit (default: 3 days), the order is automatically marked as complete and payment is released to the vendor.

Configure the auto-complete delay at **Settings > Orders > Auto-Complete Days**.

### Expire Old Buyer Requests

**Runs once daily.** Buyer requests that have passed their expiration deadline are automatically marked as expired and hidden from vendor listings.

### Update Vendor Statistics

**Runs twice daily.** Recalculates vendor performance metrics including overall rating, total earnings, completion rate, response time, and service counts. This keeps vendor profiles and rankings accurate without impacting real-time performance.

### If Background Tasks Are Not Running

WordPress scheduled tasks rely on site traffic to trigger. On low-traffic sites, tasks may run late. If you notice orders not auto-completing or vendor stats being outdated, set up a real server cron job through your hosting control panel to ping your site every 15 minutes.

### Additional Background Tasks **[PRO]**

- **Auto-Withdrawals** -- Automatically processes vendor payouts when earnings reach a threshold
- **Cloud Storage Sync** -- Keeps local and cloud storage in sync, cleans up orphaned files

---

## Troubleshooting

**Background tasks not running?**
Install the free WP Crontrol plugin to check if scheduled tasks are registered. If your site has low traffic, set up a real server cron job.

**Debug log not showing anything?**
Make sure WordPress debugging is also enabled in your `wp-config.php` file, and that the `wp-content` directory is writable.

**Data still present after uninstall?**
The "Delete Data on Uninstall" option must be enabled before you uninstall. Also, use the proper WordPress uninstall process (Plugins page) rather than deleting files via FTP.
