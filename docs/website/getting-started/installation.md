# Installation Guide

This guide walks you through installing WP Sell Services and setting up the required components for your marketplace.

## Requirements

Before installing WP Sell Services, ensure your server meets these requirements:

| Requirement | Minimum Version |
|-------------|----------------|
| **WordPress** | 6.4 or higher |
| **PHP** | 8.1 or higher |
| **MySQL** | 5.7 or higher |
| **WooCommerce** | 8.0 or higher (recommended for free version) |

**Recommended Server Settings:**
- PHP Memory Limit: 256MB or higher
- Max Upload Size: 64MB or higher (for service galleries and deliverables)
- Max Execution Time: 300 seconds

## Installing WP Sell Services

### Method 1: WordPress Dashboard (Recommended)

1. Log in to your WordPress admin dashboard
2. Navigate to **Plugins → Add New**
3. Click **Upload Plugin** at the top of the page
4. Click **Choose File** and select the `wp-sell-services.zip` file
5. Click **Install Now**
6. After installation completes, click **Activate Plugin**

![Upload plugin via dashboard](../images/admin-plugin-upload.png)

### Method 2: FTP Upload

1. Extract the `wp-sell-services.zip` file
2. Upload the `wp-sell-services` folder to `/wp-content/plugins/` via FTP
3. Navigate to **Plugins** in your WordPress dashboard
4. Find **WP Sell Services** and click **Activate**

## Setting Up WooCommerce

The free version of WP Sell Services requires WooCommerce for payment processing.

### Install WooCommerce

1. Go to **Plugins → Add New**
2. Search for "WooCommerce"
3. Click **Install Now** on the official WooCommerce plugin
4. Click **Activate**

![Install WooCommerce](../images/admin-woocommerce-install.png)

### Configure WooCommerce for Services

WP Sell Services creates virtual products in WooCommerce for each service order. Configure these settings:

1. Go to **WooCommerce → Settings → Products**
2. Under **General**, uncheck:
   - "Enable product reviews" (WP Sell Services has its own review system)
3. Go to **WooCommerce → Settings → Shipping**
4. Disable all shipping methods (services are digital)
5. Go to **WooCommerce → Settings → Tax**
6. Configure tax settings according to your location (optional)

**Note:** WP Sell Services creates orders in WooCommerce automatically. Vendors don't create WooCommerce products manually.

## First-Time Activation

When you activate WP Sell Services for the first time:

### Automatic Setup

The plugin automatically:
- Creates 17 database tables for orders, conversations, reviews, disputes, earnings, notifications, etc.
- Registers the `wpss_service` and `wpss_request` custom post types
- Installs default email templates
- Sets up default commission rates (10%)
- Creates the `wpss_vendor` user role with service and order management capabilities

### Setup Wizard

After activation, you'll see the **WP Sell Services Setup Wizard**:

1. **Welcome Screen**: Overview of the setup process
2. **Pages Setup**: Automatically creates required pages (or select existing ones)
3. **Commission Settings**: Set your default commission rate
4. **Vendor Settings**: Choose vendor registration settings
5. **Email Settings**: Configure sender name and email
6. **Complete**: Finish setup and go to settings

![Setup wizard welcome screen](../images/wizard-welcome.png)

You can skip the wizard and configure settings manually later, but we recommend completing it for a faster setup.

## Installing WP Sell Services Pro

**Important:** You must install and activate the free WP Sell Services plugin before installing Pro.

### Installation Steps

1. Install and activate **WP Sell Services** (free version) first
2. Navigate to **Plugins → Add New → Upload Plugin**
3. Upload the `wp-sell-services-pro.zip` file
4. Click **Install Now**, then **Activate Plugin**
5. Enter your license key at **WP Sell Services → Settings → License**

![Pro license activation](../images/admin-pro-license.png)

### What Pro Adds

After activating Pro, you'll gain access to:
- **Payment Gateways**: Direct Stripe, PayPal, and Razorpay integration
- **E-commerce Options**: EDD, FluentCart, SureCart, or Standalone mode
- **Wallet System**: Buyer and vendor wallet features
- **Cloud Storage**: S3, Google Cloud, Cloudflare R2 for deliverables
- **Analytics**: Advanced reporting dashboard
- **Unlimited Limits**: Remove all free version restrictions

Configure Pro features at **WP Sell Services → Settings → Pro Features**.

## Troubleshooting Installation Issues

### Plugin Won't Activate

**Error: "This plugin requires PHP 8.1 or higher"**

Contact your hosting provider to upgrade PHP. Most hosts allow PHP version changes via cPanel or hosting dashboard.

**Error: "The plugin does not have a valid header"**

You may have uploaded the wrong file. Ensure you're uploading the `.zip` file, not an extracted folder.

### Database Tables Not Created

If database tables don't create automatically:

1. Deactivate the plugin
2. Delete the plugin (your settings are stored separately)
3. Re-upload and activate
4. Check **WP Sell Services → Settings → Advanced → Database** to verify table status

### WooCommerce Conflicts

If you see WooCommerce errors after activation:

1. Update WooCommerce to the latest version
2. Clear all caches (plugin cache, theme cache, server cache)
3. Go to **WooCommerce → Status → Tools** and click **Clear template cache**

### 404 Errors on Service Pages

After activation, service pages show 404 errors:

1. Go to **Settings → Permalinks**
2. Click **Save Changes** (don't change anything, just save)
3. This flushes rewrite rules and fixes 404 errors

### Upload Size Limit Issues

If you can't upload the plugin (file too large):

1. Use FTP upload method instead
2. Or increase upload limits via `.htaccess` or `php.ini`
3. Contact your host for assistance

## Next Steps

Now that WP Sell Services is installed:

1. **[Complete Initial Setup](initial-setup.md)** - Configure pages, commission, and settings
2. **[Configure Admin Settings](../settings/general-settings.md)** - Customize your marketplace
3. **[Create Your First Service](../service-management/creating-a-service.md)** - Test the vendor experience

Your marketplace is ready to go!
