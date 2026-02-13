# General Settings

Configure the core settings for your marketplace platform including branding, currency, and e-commerce integration.

## Platform Branding

Customize your marketplace name to match your brand identity.

### Platform Name

Set a custom name that appears throughout your marketplace:

1. Go to **WP Sell Services → Settings → General**
2. Enter your marketplace name in the **Platform Name** field
3. This name appears in emails, page titles, and frontend displays
4. Default: Your WordPress site name (`get_bloginfo('name')`)
5. Save changes

**Example:** "Creative Hub Marketplace" or "Expert Services Network"

![General settings tab](../images/settings-general-tab.png)

![Full general settings](../images/settings-general.png)

**Where Platform Name Appears:**
- Email subject lines and headers
- Notification messages
- Payment receipts
- Admin dashboard titles
- Public-facing widgets

## Currency Configuration

Choose from 10 supported currencies for your marketplace transactions.

### Available Currencies

WP Sell Services supports these 10 currencies:

| Currency | Symbol | Code |
|----------|--------|------|
| US Dollar | $ | USD |
| Euro | € | EUR |
| British Pound | £ | GBP |
| Canadian Dollar | C$ | CAD |
| Australian Dollar | A$ | AUD |
| Indian Rupee | ₹ | INR |
| Japanese Yen | ¥ | JPY |
| Chinese Yuan | ¥ | CNY |
| Brazilian Real | R$ | BRL |
| Mexican Peso | $ | MXN |

### Setting Your Currency

1. Navigate to **General Settings**
2. Select your preferred currency from the **Currency** dropdown
3. Currency applies globally to all services, orders, and payments
4. Click **Save Changes**

**Default:** USD (US Dollar)

### Important Currency Notes

**Single Currency Marketplace:**
- All services must be priced in the selected currency
- Buyers pay in the marketplace currency
- Vendor earnings are calculated in that currency
- Payment gateways handle currency conversion if needed

**Changing Currency:**
You can change currency at any time, but consider:
- Existing service prices remain in old currency amounts
- New services use new currency
- May confuse buyers seeing mixed currencies
- Best to set currency during initial setup

**Multi-Currency Support:**
**[PRO]** The Pro version adds multi-currency features:
- Automatic currency detection by buyer location
- Live exchange rate updates
- Display prices in buyer's preferred currency
- Vendors still receive payouts in marketplace currency

## E-Commerce Platform Selection

Choose which e-commerce system powers your marketplace checkout and payments.

### Available Platforms

| Platform | Free | Pro Required |
|----------|------|--------------|
| WooCommerce | ✓ | No |
| Easy Digital Downloads | | **[PRO]** |
| FluentCart | | **[PRO]** |
| SureCart | | **[PRO]** |
| Standalone Mode | | **[PRO]** |

### Auto-Detect (Recommended)

**Default Setting:** Auto-detect

The plugin automatically detects which e-commerce platform is installed and active:
1. Checks for WooCommerce
2. Falls back to other supported platforms (Pro)
3. Uses first available active platform

**Configuration:**
1. Go to **Settings → General → E-Commerce Integration**
2. Select **Auto-Detect (recommended)**
3. Save changes

**Current Active Platform** displays which platform is detected and in use.

### WooCommerce (Default)

The free version integrates with WooCommerce for:
- Service product management
- Shopping cart functionality
- Secure checkout process
- Payment gateway options
- Order management
- Customer account integration

**Requirements:**
- WooCommerce 6.0 or higher installed and activated
- Compatible with all major WooCommerce payment gateways
- Works with WooCommerce subscriptions for recurring services

**Setup:**
1. Install WooCommerce from WordPress plugin repository
2. Activate WooCommerce
3. Complete WooCommerce setup wizard
4. WP Sell Services auto-detects WooCommerce
5. Service orders process through WooCommerce checkout

### Pro E-Commerce Platforms

**[PRO]** Upgrade to WP Sell Services Pro for alternative e-commerce options:

**Easy Digital Downloads (EDD):**
- Optimized for digital product sales
- Lightweight compared to WooCommerce
- Simple checkout process
- Recurring payments support

**FluentCart:**
- Modern, fast checkout experience
- Optimized for conversions
- One-click upsells
- Lightweight and performant

**SureCart:**
- Hosted checkout solution
- PCI-compliant by default
- Simple pricing and setup
- Modern UI/UX

**Standalone Mode:**
- No external e-commerce plugin required
- Built-in checkout system
- Direct Stripe or PayPal integration
- Reduced plugin conflicts
- Simpler setup for simple marketplaces

See [Payment Gateways](../payments-checkout/payment-gateways-pro.md) for platform-specific configuration.

### Switching Platforms

To change e-commerce platforms:

1. Go to **General Settings → E-Commerce Platform**
2. Select your preferred platform
3. Ensure that platform is installed and activated
4. Save changes
5. Complete platform-specific configuration
6. Test checkout flow before going live

**Warning:** Switching platforms affects how orders are processed. Test thoroughly on staging site before changing on live marketplace.

**Migration Considerations:**
- Existing orders remain in original platform
- New orders use new platform
- Payment gateway settings may need reconfiguration
- WooCommerce-specific features (subscriptions, etc.) require WooCommerce

## E-Commerce Integration Status

The settings page displays current integration status:

**Integration Status Box:**
- **Currently Active:** Shows detected platform name
- **Status:** Connected or Not Installed
- **Version:** Platform version number (if available)

**No Platform Detected:**
If you see "No e-commerce platform detected":
1. Install WooCommerce or another supported platform
2. Activate the platform plugin
3. Refresh settings page
4. Verify integration status updates

## Related Settings

- [Pages Setup](pages-setup.md) - Configure marketplace pages
- [Commission Settings](../earnings-wallet/commission-settings.md) - Platform earnings
- [Vendor Settings](../vendor-system/vendor-settings.md) - Registration rules
- [Payment Gateways](../payments-checkout/woocommerce-integration.md) - Payment configuration
- [Advanced Settings](advanced-settings.md) - System options

## Troubleshooting

### Platform Name Not Updating

**Solutions:**
1. Clear all caches (WordPress, theme, hosting)
2. Verify settings were saved successfully
3. Check for hardcoded site name in email templates
4. Flush email template cache **[PRO]**

### Currency Symbol Not Displaying

**Check:**
1. Theme supports UTF-8 encoding
2. Database uses utf8mb4 charset
3. PHP mbstring extension enabled
4. Browser font supports currency symbols

### E-Commerce Platform Not Detected

**Verify:**
1. WooCommerce (or chosen platform) is installed
2. Platform is activated (check Plugins page)
3. Platform version meets minimum requirements
4. No PHP errors preventing platform initialization
5. Refresh WP Sell Services settings page

### WooCommerce Checkout Not Working

**Solutions:**
1. Verify WooCommerce setup completed
2. Check payment gateway configuration
3. Ensure permalinks flushed (Settings → Permalinks → Save)
4. Test with default WooCommerce theme
5. Check for WooCommerce/plugin conflicts

## Next Steps

After configuring general settings:

1. [Set up required pages](pages-setup.md)
2. [Configure commission rates](../earnings-wallet/commission-settings.md)
3. [Define vendor registration rules](../vendor-system/vendor-settings.md)
4. [Configure payment gateways](../payments-checkout/woocommerce-integration.md)
5. Test marketplace with sample service and order
