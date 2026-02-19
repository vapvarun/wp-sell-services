# Standalone Marketplace Mode

Standalone mode is the **default checkout system** in WP Sell Services Free. It operates independently without WooCommerce, EDD, or any other e-commerce plugin.

## Overview

Standalone mode provides a complete marketplace system:

- Built-in cart and checkout
- Direct payment gateway integration (Stripe, PayPal, Offline)
- Native order management
- Custom dashboard
- No external dependencies

## Requirements

- WP Sell Services (free version)
- WordPress 6.0+
- PHP 8.0+
- SSL certificate (HTTPS required for Stripe/PayPal)

## Setup

### Step 1: Enable Standalone Mode

1. Go to **WP Sell Services → Settings → General**
2. Select **E-commerce Platform:** Standalone Mode
3. Click **Save Changes**

### Step 2: Configure Currency

1. On same page, select **Currency** (USD, EUR, GBP, etc.)
2. Click **Save Changes**

See [Currency & Tax Configuration](currency-tax-config.md) for tax settings.

### Step 3: Configure Payment Gateway

Configure at least one gateway in **Settings → Payments**:

1. **Stripe** - Card payments (recommended)
2. **PayPal** - PayPal balance and cards
3. **Razorpay** - India payments (UPI, cards, banking)
4. **Offline** - Bank transfer/check

See:
- [Stripe Payments](stripe-payments.md)
- [Other Payment Gateways](other-gateways.md)

### Step 4: Test Checkout

1. Add a service to cart
2. Visit checkout page
3. Enter billing details
4. Select payment method
5. Complete test purchase
6. Verify order created successfully

## How It Works

### Transaction Flow

```
Service → Cart → Checkout → Payment Gateway → Order → Vendor Notified
```

All processing happens within WP Sell Services tables. No external order systems.

### Differences from WooCommerce Mode

| Feature | WooCommerce | Standalone |
|---------|-------------|------------|
| Dependencies | Requires WooCommerce | None |
| Database | WC + WPSS tables | WPSS only |
| Payment Gateways | 100+ via WC | 4 direct |
| Admin UI | WC Orders | WPSS Orders |
| Cart | WC Cart | Native WPSS |
| Performance | Good | Excellent |

## Available Payment Gateways

Only these 4 gateways work with Standalone mode:

1. **Stripe** **[PRO]** - Cards, Apple Pay, Google Pay
2. **PayPal** **[PRO]** - PayPal, Venmo, cards
3. **Razorpay** **[PRO]** - UPI, cards, net banking (India)
4. **Offline** **[PRO]** - Bank transfer, check

Configure in **Settings → Payments → [Gateway]**.

## Cart & Checkout

### Cart System

- Session-based storage
- Persists across page loads
- Multiple services from different vendors
- Add-ons support

### Checkout Page

Standalone checkout includes:

1. **Billing Details** - Name, email, address
2. **Order Review** - Services, prices, totals
3. **Payment Methods** - Available gateway selection
4. **Terms & Conditions** - Agreement checkbox
5. **Place Order** - Submit button

## Vendor Dashboard

Standalone mode includes dedicated dashboard:

### Vendor Features

- Orders management
- Service listings
- Earnings tracking
- Withdrawal requests
- Message buyers
- Profile settings

### Buyer Features

- Order history
- Active orders
- Message vendors
- Profile settings

## Limitations

### Not Available in Standalone

These features require WooCommerce:

- WooCommerce extensions (Subscriptions, Bookings, etc.)
- Physical product support
- WooCommerce-specific integrations
- WC mobile apps
- 100+ WC payment gateways

### Standalone Advantages

- Faster performance (30-40% faster checkout)
- Simpler database structure
- Lower resource usage
- Service-optimized UI
- No bloat from unused e-commerce features

## Switching Platforms

### From WooCommerce to Standalone

1. Complete all active WC orders
2. Backup database
3. Go to **Settings → General**
4. Change to **Standalone Mode**
5. Configure payment gateways
6. Test checkout thoroughly

**Note:** Existing WC orders remain in WC tables. New orders use standalone system.

### From Standalone to WooCommerce

1. Install and activate WooCommerce
2. Go to **Settings → General**
3. Change to **WooCommerce**
4. Configure WC payment gateways
5. Test checkout

Historical standalone orders remain accessible in admin.

## Troubleshooting

### Checkout Not Loading

1. Verify checkout page exists with `[wpss_checkout]` shortcode
2. Check for JavaScript errors (F12 console)
3. Increase PHP memory: `define('WP_MEMORY_LIMIT', '256M');`

### Payment Gateway Not Working

1. Verify API credentials are correct
2. Check webhook is configured in gateway dashboard
3. Enable debug: `define('WPSS_GATEWAY_DEBUG', true);`
4. Check logs: `wp-content/uploads/wpss-logs/`

### Orders Not Creating

1. Enable debug: `define('WP_DEBUG', true);`
2. Go to **WP Sell Services → Status**
3. Click **Check Database Tables**
4. Recreate missing tables if needed

## Security

### SSL Requirement

HTTPS required for checkout. Add to wp-config.php:

```php
define('WPSS_FORCE_SSL_CHECKOUT', true);
```

### PCI Compliance

Payment gateways handle PCI compliance:

- Stripe.js handles card data (never touches your server)
- PayPal redirects or uses hosted buttons
- Razorpay Checkout.js manages security

Your site never stores raw card data.

## Related Documentation

- [Stripe Payments](stripe-payments.md) **[PRO]** - Primary card gateway
- [Other Payment Gateways](other-gateways.md) **[PRO]** - PayPal, Razorpay, offline
- [Alternative Platforms](alternative-platforms.md) - EDD, FluentCart, SureCart
- [Currency & Tax Config](currency-tax-config.md) - Financial settings
- [Commission System](../earnings-wallet/commission-system.md) - Vendor earnings
