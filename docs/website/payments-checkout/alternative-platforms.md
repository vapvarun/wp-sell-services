# Alternative E-commerce Platforms **[PRO]**

WP Sell Services Pro supports multiple e-commerce platforms beyond WooCommerce for flexible payment processing and marketplace management.

## Overview

The free version integrates with WooCommerce when available for checkout and payments. WP Sell Services Pro adds support for additional platforms:

- Easy Digital Downloads (EDD)
- FluentCart
- SureCart
- Standalone Mode (no external platform needed)

## Supported Platforms

| Platform | Best For | Pros | Cons |
|----------|----------|------|------|
| **WooCommerce** (Free) | Full-featured stores | Extensive gateway support, mature ecosystem | Heavier performance impact |
| **Easy Digital Downloads** **[PRO]** | Digital services | Lightweight, digital-focused | Fewer gateways than WooCommerce |
| **FluentCart** **[PRO]** | Modern checkouts | Fast, simple | Limited extensions |
| **SureCart** **[PRO]** | Subscription services | Modern UI, tax automation | Requires SureCart account |
| **Standalone Mode** **[PRO]** | Pure service marketplaces | No dependencies, fastest | Limited to 4 direct gateways |

## Easy Digital Downloads (EDD) **[PRO]**

### Requirements

- Easy Digital Downloads plugin installed and activated
- WP Sell Services Pro
- PHP 8.0+

### Setup

1. Install Easy Digital Downloads plugin
2. Activate EDD
3. Go to **Downloads → Settings** in WordPress admin
4. Configure EDD payment gateways and currency
5. Go to **WP Sell Services → Settings → General**
6. Select **E-commerce Platform:** Easy Digital Downloads
7. Click **Save Changes**

### How It Works

When EDD is active:

- Service posts create EDD downloads automatically
- Service packages map to EDD price variations
- EDD handles checkout and payment processing
- Service orders created after EDD payment completes
- Vendor dashboard shows order status

### EDD Payment Gateways

Use any EDD payment gateway extension:

- PayPal Standard (free with EDD)
- Stripe (requires EDD Stripe extension)
- Manual payments
- Other EDD gateway extensions

Configure gateways in **Downloads → Settings → Payment Gateways**.

## FluentCart **[PRO]**

### Requirements

- FluentCart plugin installed and activated
- WP Sell Services Pro
- PHP 8.0+

### Setup

1. Install FluentCart plugin
2. Activate FluentCart
3. Go to **FluentCart → Settings**
4. Configure payment methods (Stripe, PayPal)
5. Go to **WP Sell Services → Settings → General**
6. Select **E-commerce Platform:** FluentCart
7. Click **Save Changes**

### How It Works

FluentCart provides a lightweight checkout:

- Services mapped to FluentCart products
- Single-page checkout experience
- Stripe and PayPal built-in
- Fast performance, minimal overhead

## SureCart **[PRO]**

### Requirements

- SureCart plugin installed and activated
- SureCart account (free to create)
- WP Sell Services Pro
- PHP 8.0+

### Setup

1. Install SureCart plugin
2. Create account at SureCart.com
3. Activate SureCart and connect account
4. Go to **SureCart → Settings**
5. Configure payment processor (Stripe recommended)
6. Go to **WP Sell Services → Settings → General**
7. Select **E-commerce Platform:** SureCart
8. Click **Save Changes**

### How It Works

SureCart offers modern checkout features:

- Beautiful checkout forms
- Automatic tax calculation (TaxJar integration)
- Subscription billing support
- Payment Request buttons (Apple Pay, Google Pay)

Configure payment processors in SureCart settings.

## Standalone Mode **[PRO]**

### Requirements

- WP Sell Services Pro only (no other plugins needed)
- PHP 8.0+
- SSL certificate required

### Setup

1. Go to **WP Sell Services → Settings → General**
2. Select **E-commerce Platform:** Standalone Mode
3. Click **Save Changes**
4. Go to **Settings → Payments**
5. Configure at least one payment gateway:
   - Stripe **[PRO]**
   - PayPal **[PRO]**
   - Razorpay **[PRO]**
   - Offline Payments **[PRO]**
6. Test checkout flow

### How It Works

Standalone mode operates independently:

- No WooCommerce, EDD, or other platform required
- Built-in cart and checkout system
- Direct payment gateway integration
- Native order management
- Service-optimized checkout flow

See [Standalone Marketplace Mode](standalone-mode.md) for complete guide.

### Available Payment Gateways in Standalone Mode

Only these 4 gateways work with Standalone mode:

1. **Stripe** - Card payments, Apple Pay, Google Pay
2. **PayPal** - PayPal balance, cards, Venmo
3. **Razorpay** - UPI, cards, net banking, wallets (India)
4. **Offline Payments** - Bank transfer, check, manual verification

Configure in **Settings → Payments → [Gateway Name]** after enabling Standalone mode.

See [Stripe Payments](stripe-payments.md) and [Other Payment Gateways](other-gateways.md) for setup instructions.

## Switching Platforms

### Before Switching

1. Complete all active orders in current platform
2. Backup your database
3. Export order history for records
4. Test on staging site first

### Switching Process

1. Go to **WP Sell Services → Settings → General**
2. Change **E-commerce Platform** to new platform
3. Click **Save Changes**
4. Reconfigure payment gateways in new platform
5. Test checkout flow thoroughly

**Note:** Existing orders remain in the original platform's order system. Historical data is preserved in WPSS tables and accessible via admin.

## Platform Comparison Chart

| Feature | WooCommerce | EDD | FluentCart | SureCart | Standalone |
|---------|-------------|-----|------------|----------|------------|
| Service checkout | ✓ | ✓ | ✓ | ✓ | ✓ |
| Physical products | ✓ | ✗ | ✗ | ✓ | ✗ |
| Payment gateways | 100+ | 20+ | 2 built-in | Stripe/PayPal | 4 direct |
| Performance | Good | Excellent | Excellent | Excellent | Best |
| Setup complexity | Medium | Medium | Low | Low | Minimal |
| Dependencies | WooCommerce | EDD | FluentCart | SureCart | None |
| Tax automation | Extensions | Basic | ✗ | ✓ TaxJar | Basic |

## Related Documentation

- [WooCommerce Checkout](woocommerce-checkout.md) - Default free platform
- [Standalone Marketplace Mode](standalone-mode.md) **[PRO]** - Complete independence
- [Stripe Payments](stripe-payments.md) **[PRO]** - Direct gateway for Standalone
- [Other Payment Gateways](other-gateways.md) **[PRO]** - PayPal, Razorpay, Offline
- [Currency & Tax Configuration](currency-tax-config.md) - Financial settings
