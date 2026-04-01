# Installing WP Sell Services

Getting WP Sell Services up and running takes just a few minutes. No technical experience required -- if you can install a WordPress plugin, you can do this.

## Requirements

Before you install, make sure your hosting meets these minimums:

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.0 or higher |
| PHP | 8.1 or higher |

Most modern WordPress hosts already meet these requirements. If you are unsure, check with your hosting provider.

## Install the Plugin

### Option A: Upload from WordPress Admin (Recommended)

1. Go to **Plugins > Add New** in your WordPress dashboard
2. Click **Upload Plugin** at the top
3. Choose the `wp-sell-services.zip` file from your computer
4. Click **Install Now**
5. Click **Activate Plugin**

### Option B: Upload via FTP

1. Unzip the `wp-sell-services.zip` file on your computer
2. Upload the `wp-sell-services` folder to your site's `/wp-content/plugins/` directory
3. Go to **Plugins** in your WordPress dashboard
4. Find **WP Sell Services** in the list and click **Activate**

## What Happens When You Activate

When you activate the plugin, everything sets up automatically:

- **Your marketplace pages** are ready to be created with one click
- **The vendor role** is created so people can register and start selling
- **Default settings** are applied (10% commission, USD currency, open registration)
- **Automated tasks** start running in the background (auto-completing delivered orders, updating vendor stats)

You do not need to touch any database settings or configure anything technical.

## Installing the Pro Version **[PRO]**

The Pro version adds premium features on top of the free plugin. Both run together.

1. Make sure the **free version is installed and active** first
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload `wp-sell-services-pro.zip`
4. Click **Install Now**, then **Activate Plugin**
5. Go to **WP Sell Services > Settings > License**
6. Enter your license key and click **Activate License**

Pro features become available immediately -- no migration or extra setup needed.

## Troubleshooting

### Service pages show "Page Not Found"

Go to **Settings > Permalinks** in your WordPress dashboard and click **Save Changes**. You do not need to change anything -- just saving refreshes the links.

### Plugin will not activate

- **PHP error?** Ask your host to upgrade PHP to 8.1 or higher
- **WordPress error?** Update WordPress via **Dashboard > Updates**
- **"Invalid header" error?** Make sure you are uploading the `.zip` file, not an extracted folder

### Upload size too large

If the zip file is too big to upload through WordPress, use the FTP method instead (Option B above), or ask your hosting provider to increase the upload limit.

## Next Steps

Your plugin is installed and active. Now let's configure your marketplace:

1. **[Complete initial setup](initial-setup.md)** -- Set your marketplace name, currency, and commission rate
2. **[Compare Free vs Pro](free-vs-pro.md)** -- See what each version offers
3. **[Create your first service](../service-creation/service-wizard.md)** -- Test the vendor experience
