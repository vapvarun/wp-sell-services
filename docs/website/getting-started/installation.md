# Installation Guide

This guide walks you through installing WP Sell Services and setting up the required components for your marketplace.

## System Requirements

Before installing, verify your server meets these minimum requirements:

| Requirement | Minimum Version |
|-------------|----------------|
| **WordPress** | 6.4 or higher |
| **PHP** | 8.1 or higher |
| **MySQL** | 5.7 or higher (or MariaDB 10.2+) |
| **WooCommerce** | 8.0+ (optional - see notes) |

**Recommended Server Settings:**
- PHP Memory Limit: 256MB or higher
- Max Upload Size: 64MB or higher (for service galleries and deliverables)
- Max Execution Time: 300 seconds
- PHP Extensions: mysqli, mbstring, json, curl

**Note on WooCommerce**: WooCommerce is optional. WP Sell Services works independently — your marketplace is fully functional for service listings, vendor management, order workflow, messaging, reviews, and dispute resolution. When WooCommerce is active, the plugin automatically enables checkout and payment processing. **[PRO]** version adds additional e-commerce platform options and standalone payment gateways.

## Installing WP Sell Services

### Method 1: WordPress Dashboard (Recommended)

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins → Add New**
3. Click **Upload Plugin** at the top
4. Click **Choose File** and select `wp-sell-services.zip`
5. Click **Install Now**
6. After installation, click **Activate Plugin**

### Method 2: FTP Upload

1. Extract the `wp-sell-services.zip` file
2. Upload the `wp-sell-services` folder to `/wp-content/plugins/` via FTP
3. Navigate to **Plugins** in your WordPress dashboard
4. Find **WP Sell Services** and click **Activate**

### Method 3: WP-CLI

```bash
wp plugin install wp-sell-services.zip --activate
```

## First-Time Activation

When you activate WP Sell Services, the plugin automatically:

### Database Setup
Creates dedicated database tables:
- `{prefix}wpss_orders` - Service orders
- `{prefix}wpss_conversations` - Order messages
- `{prefix}wpss_deliveries` - Work deliverables
- `{prefix}wpss_reviews` - Ratings and reviews
- `{prefix}wpss_disputes` - Dispute cases
- `{prefix}wpss_service_packages` - Service pricing tiers
- `{prefix}wpss_vendor_profiles` - Vendor information
- `{prefix}wpss_buyer_requests` - Job postings
- `{prefix}wpss_proposals` - Vendor proposals
- `{prefix}wpss_earnings` - Commission records
- `{prefix}wpss_withdrawals` - Payout requests
- `{prefix}wpss_notifications` - In-app notifications
- `{prefix}wpss_portfolio_items` - Portfolio entries
- `{prefix}wpss_extension_requests` - Deadline extensions
- `{prefix}wpss_milestones` **[PRO]** - Payment milestones
- `{prefix}wpss_tips` - Tip records

### Content Types
Registers custom post types:
- **wpss_service**: Service listings
- **wpss_request**: Buyer requests

Registers taxonomies:
- **wpss_service_category**: Hierarchical service categories
- **wpss_service_tag**: Service tags

### User Roles
Creates the **wpss_vendor** role with these capabilities:
- `wpss_vendor` - Vendor status marker
- `wpss_manage_services` - Create and manage services
- `wpss_manage_orders` - Handle vendor orders
- `wpss_view_analytics` - Access vendor analytics
- `wpss_respond_to_requests` - Respond to buyer requests
- `read` - WordPress basic capability
- `upload_files` - Upload media
- `edit_posts` - Edit content

Administrators automatically gain all vendor capabilities plus:
- `wpss_manage_settings` - Manage marketplace settings
- `wpss_manage_disputes` - Handle dispute resolution
- `wpss_manage_vendors` - Manage vendor accounts

### Default Options
Sets default configuration:

**General Settings**:
- Platform name: Your site name
- Currency: USD
- E-commerce platform: Auto-detect

**Commission Settings**:
- Commission rate: 10%
- Enable per-vendor rates: Yes

**Payout Settings**:
- Minimum withdrawal: $50
- Clearance days: 14
- Auto-withdrawal: Disabled

**Tax Settings**:
- Tax enabled: No
- Tax label: "Tax"
- Tax rate: 0%

**Vendor Settings**:
- Vendor registration: Open
- Max services per vendor: 20
- Verification required: No
- Service moderation: No

**Order Settings**:
- Auto-complete days: 3
- Revision limit: 2
- Disputes allowed: Yes
- Dispute window: 14 days

**Notification Settings**:
- All email notifications enabled by default

### Cron Events
Schedules automated tasks:
- **wpss_auto_complete_orders**: Hourly - Auto-complete delivered orders
- **wpss_cleanup_expired_requests**: Daily - Clean up expired buyer requests
- **wpss_update_vendor_stats**: Twice daily - Update vendor statistics
- **wpss_process_auto_withdrawals**: Dynamic - Process automatic payouts

## Installing WooCommerce (Optional)

WooCommerce is optional but recommended for checkout and payment processing. When active, all WooCommerce payment gateways work automatically.

### Install WooCommerce

1. Go to **Plugins → Add New**
2. Search for "WooCommerce"
3. Click **Install Now** on WooCommerce by Automattic
4. Click **Activate**
5. Follow WooCommerce setup wizard or skip to configure later

### Configure WooCommerce for Services

WP Sell Services creates a virtual WooCommerce product for each service order automatically. Optimize WooCommerce for digital services:

1. Navigate to **WooCommerce → Settings → Products**
2. Uncheck **Enable product reviews** (WP Sell Services has its own review system)
3. Navigate to **WooCommerce → Settings → Shipping**
4. Disable all shipping methods (services are digital)
5. Navigate to **WooCommerce → Settings → Tax**
6. Configure tax settings for your jurisdiction (optional)

**Note**: Vendors don't create WooCommerce products manually. WP Sell Services creates a carrier product automatically during checkout.

## Installing Pro Version **[PRO]**

The Pro version extends the free version with premium features.

**Important**: Install and activate the **free** version first before installing Pro.

### Installation Steps

1. Install and activate WP Sell Services (free version)
2. Navigate to **Plugins → Add New → Upload Plugin**
3. Upload `wp-sell-services-pro.zip`
4. Click **Install Now**, then **Activate Plugin**
5. Navigate to **WP Sell Services → Settings → License**
6. Enter your license key
7. Click **Activate License**

### Pro Features Activation

After activating Pro, you gain access to:

**E-commerce Platforms**:
- Easy Digital Downloads (EDD) integration
- FluentCart integration
- SureCart integration
- Standalone mode (no e-commerce plugin required)

**Payment Gateways**:
- Stripe Direct integration
- PayPal Commerce Platform
- Razorpay
- Offline payments with proof upload

**Wallet System**:
- 4 wallet provider integrations
- Automated payout scheduling

**Cloud Storage**:
- Amazon S3
- Google Cloud Storage
- DigitalOcean Spaces
- Custom S3-compatible storage

**Analytics**:
- Vendor analytics dashboard
- Admin analytics dashboard
- CSV/PDF export

**Extended Limits**:
- Unlimited packages per service
- Unlimited gallery images
- Unlimited videos
- Unlimited FAQs
- Unlimited add-ons

Configure Pro features at **WP Sell Services → Settings → Pro Features**.

## Troubleshooting Installation

### Plugin Won't Activate

**Error**: "This plugin requires PHP 8.1 or higher"

**Solution**: Contact your hosting provider to upgrade PHP. Most hosts allow PHP version changes in cPanel or the hosting dashboard.

**Error**: "This plugin requires WordPress 6.4 or higher"

**Solution**: Update WordPress via **Dashboard → Updates**.

**Error**: "The plugin does not have a valid header"

**Solution**: Ensure you're uploading the complete `.zip` file, not an extracted folder.

### Database Tables Not Created

If activation succeeds but tables aren't created:

1. Deactivate WP Sell Services
2. Reactivate the plugin
3. Navigate to **WP Sell Services → Settings → Advanced → Database**
4. Click **Verify Database Tables**
5. If issues persist, click **Recreate Tables** (this won't delete existing data)

### WooCommerce Integration Issues

If WooCommerce errors occur after activation:

1. Update WooCommerce to the latest version
2. Clear all caches (WordPress object cache, page cache, CDN cache)
3. Navigate to **WooCommerce → Status → Tools**
4. Click **Clear template cache**
5. Click **Verify base database tables**

### 404 Errors on Service Pages

After activation, if service pages show 404 errors:

1. Navigate to **Settings → Permalinks**
2. Click **Save Changes** (no need to change anything)
3. This flushes WordPress rewrite rules
4. Refresh the service page

### Upload Size Limit Issues

If you can't upload the plugin (file too large):

**Solution 1**: Use FTP upload method instead

**Solution 2**: Increase upload limits in `.htaccess`:
```apache
php_value upload_max_filesize 64M
php_value post_max_size 64M
```

**Solution 3**: Increase limits in `php.ini`:
```ini
upload_max_filesize = 64M
post_max_size = 64M
```

**Solution 4**: Contact your hosting provider

### WooCommerce Carrier Product Issues

If WooCommerce carrier product wasn't created:

1. Navigate to **WP Sell Services → Settings → Advanced**
2. Scroll to **WooCommerce Integration**
3. Click **Create Carrier Product**
4. Verify product creation at **Products → All Products**

### Vendor Role Not Created

If the vendor role wasn't created:

The plugin automatically recreates missing roles on activation. If issues persist:

1. Deactivate WP Sell Services
2. Reactivate the plugin
3. Check **Users → All Users** - filter by "Vendor" role

Administrators are automatically made vendors on activation.

## Post-Installation Checks

After successful installation, verify these items:

### Database Verification

Navigate to **WP Sell Services → Settings → Advanced → Database**:
- All tables should show "✓ Exists"
- Schema version should match plugin version

### Rewrite Rules

Test these URLs work (replace `yoursite.com`):
- `https://yoursite.com/service/` (service archive)
- `https://yoursite.com/buyer-request/` (buyer request archive)
- `https://yoursite.com/vendor/` (vendor archive)

If they show 404, flush permalinks at **Settings → Permalinks**.

### API Endpoints

Test the REST API:
```bash
curl https://yoursite.com/wp-json/wpss/v1/settings
```

Should return JSON with marketplace settings.

### Cron Events

Navigate to **Tools → Site Health → Info → Scheduled Events**:
- Look for `wpss_auto_complete_orders`
- Look for `wpss_cleanup_expired_requests`
- Look for `wpss_update_vendor_stats`

## Next Steps

Now that WP Sell Services is installed:

1. **[Complete Initial Setup](initial-setup.md)** - Configure pages, commission, and settings
2. **[Compare Free vs Pro](free-vs-pro.md)** - Understand version differences
3. **[Create Your First Service](../service-creation/service-wizard.md)** - Test the vendor experience

Your marketplace is ready to configure!
