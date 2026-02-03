# General Settings

Configure the core settings for your marketplace platform including branding, currency, and e-commerce integration.

## Platform Branding

Customize your marketplace name and identity to match your brand.

### Platform Name

Set a custom name for your marketplace that appears throughout the site:

1. Go to **WP Sell Services → Settings → General**
2. Enter your marketplace name in the **Platform Name** field
3. This name appears in emails, page titles, and frontend displays
4. Default: "WP Sell Services"

**Example:** "Creative Hub Marketplace" or "Expert Services Network"

![General Settings Tab](../images/settings-general-tab.png)

## Currency Configuration

Choose from 15+ supported currencies for your marketplace transactions.

### Available Currencies

| Currency | Symbol | Code |
|----------|--------|------|
| US Dollar | $ | USD |
| Euro | € | EUR |
| British Pound | £ | GBP |
| Indian Rupee | ₹ | INR |
| Australian Dollar | A$ | AUD |
| Canadian Dollar | C$ | CAD |
| Japanese Yen | ¥ | JPY |
| Chinese Yuan | ¥ | CNY |
| Swiss Franc | CHF | CHF |
| Brazilian Real | R$ | BRL |

Plus: Mexican Peso, Russian Ruble, Singapore Dollar, South African Rand, and more.

### Setting Your Currency

1. Navigate to **General Settings**
2. Select your preferred currency from the **Currency** dropdown
3. Currency applies globally to all services and transactions
4. Click **Save Changes**

**Note:** Currency cannot be changed after services are published. Set this during initial setup.

## E-commerce Platform Selection

Choose which e-commerce system powers your marketplace transactions.

### Available Platforms

| Platform | Free | Pro Required |
|----------|------|--------------|
| WooCommerce | ✓ | No |
| Easy Digital Downloads | | **[PRO]** |
| FluentCart | | **[PRO]** |
| SureCart | | **[PRO]** |
| Standalone Mode | | **[PRO]** |

### WooCommerce (Default)

The free version integrates with WooCommerce for:
- Product management
- Cart and checkout
- Payment gateway options
- Order management
- Customer accounts

**Requirements:**
- WooCommerce 6.0 or higher
- Install and activate WooCommerce before using WP Sell Services

### Pro Platforms

**[PRO]** Upgrade to WP Sell Services Pro for alternative e-commerce options:

- **Easy Digital Downloads** - Digital products specialist
- **FluentCart** - Lightweight cart solution
- **SureCart** - Modern checkout experience
- **Standalone Mode** - No external e-commerce plugin required

See [Alternative E-commerce Platforms](../integrations/alternative-ecommerce.md) for detailed setup.

### Switching Platforms

1. Go to **General Settings → E-commerce Platform**
2. Select your preferred platform
3. Complete platform-specific configuration
4. Test checkout flow before going live

**Warning:** Switching platforms affects existing orders. Plan migrations carefully.

## Date and Time Formats

Configure how dates and times display throughout the marketplace.

### Date Format

Choose from WordPress standard formats:
- `F j, Y` - January 1, 2026
- `Y-m-d` - 2026-01-01
- `m/d/Y` - 01/01/2026
- `d/m/Y` - 01/01/2026

### Time Format

Select 12-hour or 24-hour time display:
- `g:i a` - 3:30 pm
- `H:i` - 15:30

**Note:** These settings follow your WordPress general settings by default. Override them here if needed for marketplace-specific displays.

## Global Marketplace Settings

### Service Approval Mode

Control how new services are published:
- **Auto-approve** - Services go live immediately
- **Manual review** - Admin approval required

Configure this in [Advanced Settings](advanced-settings.md).

### Vendor Registration

Set whether new vendors can self-register:
- **Open** - Anyone can register as vendor
- **Closed** - Admin creates vendor accounts

Configure this in [Vendor Settings](vendor-settings.md).

## Related Settings

- [Pages Setup](pages-setup.md) - Configure marketplace pages
- [Payment Settings](payment-settings.md) - Commission configuration
- [Vendor Settings](vendor-settings.md) - Vendor registration rules
- [Advanced Settings](advanced-settings.md) - Feature toggles and optimization

## Next Steps

After configuring general settings:

1. [Set up required pages](pages-setup.md)
2. [Configure commission rates](payment-settings.md)
3. [Define vendor registration rules](vendor-settings.md)
4. Test your marketplace with a sample service
