# Currency & Tax Configuration

Configure your marketplace's currency and tax settings for accurate pricing and compliant tax collection.

## Overview

WP Sell Services provides centralized currency and tax management for your service marketplace. All financial settings are configured in **Settings → General** and **Settings → Payments** tabs.

## Currency Configuration

### Available Currencies

WP Sell Services supports 10 major global currencies:

| Currency | Code | Symbol | Region |
|----------|------|--------|--------|
| US Dollar | USD | $ | United States |
| Euro | EUR | € | European Union |
| British Pound | GBP | £ | United Kingdom |
| Canadian Dollar | CAD | C$ | Canada |
| Australian Dollar | AUD | A$ | Australia |
| Indian Rupee | INR | ₹ | India |
| Japanese Yen | JPY | ¥ | Japan |
| Chinese Yuan | CNY | ¥ | China |
| Brazilian Real | BRL | R$ | Brazil |
| Mexican Peso | MXN | $ | Mexico |

### Setting Your Currency

1. Go to **WP Sell Services → Settings → General**
2. Select **Currency** from the dropdown
3. Click **Save Changes**

![Currency setting](../images/settings-currency.png)

The selected currency applies to all service prices, order totals, vendor earnings, and withdrawal amounts throughout your marketplace.

**Important:** Changing currency after orders exist does not convert existing amounts. Only new orders use the new currency.

## Tax Configuration

### Tax System

WP Sell Services includes a built-in tax calculation system that works independently of e-commerce platforms.

Tax settings are configured in **Settings → Payments** tab under the **Tax Settings** section.

### Available Tax Settings

| Setting | Options | Description |
|---------|---------|-------------|
| **Enable Tax** | On/Off | Activate tax calculation on service orders |
| **Tax Label** | Text | Display name (e.g., "VAT", "GST", "Sales Tax") |
| **Tax Rate (%)** | 0-50% | Percentage rate applied to service prices |
| **Prices Include Tax** | Yes/No | Whether displayed prices already include tax |
| **Tax on Commission** | None/Platform/Vendor | How tax applies to platform commission |

### Configuring Tax

1. Go to **WP Sell Services → Settings → Payments**
2. Scroll to **Tax Configuration** section
3. Check **Enable Tax** to activate
4. Enter **Tax Label** (e.g., "VAT" or "Sales Tax")
5. Set **Tax Rate (%)** (e.g., 20 for 20%)
6. Choose **Prices Include Tax**:
   - **Yes**: Displayed price includes tax (common in EU)
   - **No**: Tax added at checkout (common in US)
7. Select **Tax on Commission**:
   - **None**: No tax on commission
   - **Platform**: Platform collects tax on full amount
   - **Vendor**: Vendor handles own tax obligations
8. Click **Save Tax Settings**

![Tax settings](../images/settings-tax.png)

### Tax Calculation Examples

**Scenario 1: Prices Exclude Tax (US-style)**

```
Service Price: $100.00
Tax Rate: 7%
Tax Amount: $7.00
Total Charge: $107.00
```

**Scenario 2: Prices Include Tax (EU-style VAT)**

```
Display Price: €120.00 (includes 20% VAT)
Net Price: €100.00
VAT Amount: €20.00
Total Charge: €120.00 (no change at checkout)
```

**Scenario 3: Tax on Commission**

```
Service Price: $100.00
Platform Commission (15%): $15.00
Tax Rate: 10%
Tax on Commission (Platform setting): $1.50 additional tax
Vendor Receives: $85.00
Platform Receives: $15.00 commission + $1.50 tax
```

## E-commerce Platform Selection

### Platform Options

WP Sell Services supports multiple e-commerce platforms:

| Platform | Version | Type |
|----------|---------|------|
| **WooCommerce** | Free | Full e-commerce system |
| **Easy Digital Downloads** **[PRO]** | Pro | Digital products |
| **FluentCart** **[PRO]** | Pro | Lightweight checkout |
| **SureCart** **[PRO]** | Pro | Modern checkout |
| **Standalone Mode** **[PRO]** | Pro | No dependency |

### Selecting Platform

1. Go to **WP Sell Services → Settings → General**
2. Find **E-Commerce Integration** section
3. Select **E-commerce Platform**:
   - Auto-detect (recommended)
   - WooCommerce
   - Easy Digital Downloads **[PRO]**
   - FluentCart **[PRO]**
   - SureCart **[PRO]**
   - Standalone Mode **[PRO]**
4. Click **Save Changes**

![E-commerce platform selector](../images/settings-ecommerce-platform.png)

**Auto-detect** automatically uses the first active e-commerce plugin found. If WooCommerce is installed and active, it will be used by default.

### Platform Comparison

| Feature | WooCommerce | Standalone **[PRO]** | Others **[PRO]** |
|---------|-------------|----------------------|------------------|
| Marketplace checkout | ✓ | ✓ | ✓ |
| Payment gateways | 100+ extensions | Direct integration | Varies |
| Physical products | ✓ | ✗ | Varies |
| Performance | Good | Excellent | Good-Excellent |
| Setup complexity | Medium | Low | Low-Medium |

See [Alternative E-commerce Platforms](alternative-platforms.md) for detailed comparison.

## Commission Settings

### Commission Configuration

Platform commission is separate from tax settings and configured in **Settings → Payments** tab.

1. Go to **WP Sell Services → Settings → Payments**
2. Find **Platform Commission** section
3. Set **Commission Rate (%)** (default: 10%)
4. Choose **Per-Vendor Rates**:
   - Enable to allow custom commission per vendor
   - Disable to use global rate for all vendors
5. Click **Save Commission Settings**

![Commission settings](../images/settings-commission.png)

### Commission Calculation

Commission is calculated on the service total before tax:

```
Service Price: $100.00
Commission Rate: 15%
Commission Amount: $15.00
Vendor Earnings: $85.00
```

If tax is enabled:

```
Service Price: $100.00
Tax (7%): $7.00
Total Charged: $107.00
Commission (15% of $100): $15.00
Vendor Receives: $85.00
Platform Receives: $15.00
Tax Collected: $7.00 (handled per tax settings)
```

See [Commission System](../earnings-wallet/commission-system.md) for details.

## Integration with E-commerce Platforms

### WooCommerce Mode

When using WooCommerce:

- Currency settings sync from WooCommerce
- Tax calculation can use WooCommerce tax engine or WPSS tax system
- Payment gateways use WooCommerce gateways
- Commission calculated after WooCommerce processes payment

### Standalone Mode **[PRO]**

When using Standalone mode:

- All settings managed in WP Sell Services only
- Direct payment gateway integration (Stripe, PayPal, Razorpay)
- Native tax calculation using WPSS settings
- No external platform dependencies

See [Standalone Marketplace Mode](standalone-mode.md) for full setup guide.

## Related Documentation

- [Alternative E-commerce Platforms](alternative-platforms.md) - EDD, FluentCart, SureCart, Standalone
- [WooCommerce Checkout](woocommerce-checkout.md) - Default platform setup
- [Stripe Payments](stripe-payments.md) **[PRO]** - Direct gateway integration
- [Other Payment Gateways](other-gateways.md) **[PRO]** - PayPal, Razorpay, offline
- [Commission System](../earnings-wallet/commission-system.md) - How earnings are calculated
