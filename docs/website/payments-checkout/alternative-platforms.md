# Alternative E-commerce Platforms **[PRO]**

WP Sell Services Pro supports several e-commerce platforms beyond WooCommerce, so you can pick the one that best fits your site.

## Supported Platforms at a Glance

| Platform | Best For | Included In |
|----------|----------|-------------|
| **WooCommerce** | Full-featured stores with 100+ payment gateways | Pro |
| **Easy Digital Downloads** | Lightweight digital-focused marketplaces | Pro |
| **FluentCart** | Fast, modern single-page checkouts | Pro |
| **SureCart** | Subscription services with built-in tax automation | Pro |
| **Standalone** | Pure service marketplaces, no extra plugins needed | Free + Pro |

## Easy Digital Downloads (EDD) **[PRO]**

EDD is a popular choice if your site focuses on digital products and services. It is lighter than WooCommerce and great for marketplaces that do not need physical product features.

**How to set it up:**

1. Install and activate Easy Digital Downloads
2. Configure your payment gateways in **Downloads > Settings**
3. Go to **WP Sell Services > Settings > General**
4. Select **Easy Digital Downloads** as your e-commerce platform
5. Click **Save Changes**

Once connected, services map to EDD downloads and packages become EDD price variations. Checkout and payments are handled entirely by EDD.

**Payment gateways available:** PayPal (built into EDD), Stripe (via EDD Stripe extension), plus other EDD gateway extensions.

## FluentCart **[PRO]**

FluentCart is a newer, lightweight checkout plugin that focuses on speed and simplicity. It is a good fit if you want a fast, modern checkout without the overhead of a full e-commerce platform.

**How to set it up:**

1. Install and activate FluentCart
2. Configure Stripe or PayPal in **FluentCart > Settings**
3. Go to **WP Sell Services > Settings > General**
4. Select **FluentCart** as your e-commerce platform
5. Click **Save Changes**

FluentCart provides a single-page checkout experience with Stripe and PayPal built in.

## SureCart **[PRO]**

SureCart is ideal if you need subscription billing or automated tax calculation. It has a modern interface and handles tax compliance through its TaxJar integration.

**How to set it up:**

1. Install SureCart and create a free account at SureCart.com
2. Connect your Stripe account through SureCart settings
3. Go to **WP Sell Services > Settings > General**
4. Select **SureCart** as your e-commerce platform
5. Click **Save Changes**

SureCart includes Apple Pay, Google Pay, and automatic tax calculation out of the box.

## Which Platform Should You Choose?

Here is a practical guide:

- **Already using WooCommerce?** Stick with WooCommerce. You get 100+ gateways and full extension support.
- **Want the simplest setup?** Use standalone mode. No extra plugins, no dependencies.
- **Selling only digital services?** EDD is purpose-built for this.
- **Need the fastest checkout?** FluentCart or standalone mode are your best bets.
- **Need subscription billing or tax automation?** SureCart handles both beautifully.

## Switching Between Platforms

You can change platforms at any time:

1. Finish all active orders on your current platform first
2. Go to **WP Sell Services > Settings > General**
3. Select your new platform and save
4. Reconfigure payment gateways in the new platform
5. Test checkout thoroughly

Existing orders remain accessible -- they stay in the system they were created in. Only new orders use the new platform.

## Platform Comparison

| Feature | WooCommerce | EDD | FluentCart | SureCart | Standalone |
|---------|-------------|-----|------------|----------|------------|
| Payment gateways | 100+ | 20+ | Stripe, PayPal | Stripe, PayPal | 4 built-in |
| Performance impact | Medium | Low | Low | Low | Minimal |
| Tax automation | Via extensions | Basic | No | Yes (TaxJar) | Basic |
| Physical products | Yes | No | No | Yes | No |
| Setup effort | Medium | Medium | Easy | Easy | Easiest |

## Related Docs

- [WooCommerce Checkout](woocommerce-checkout.md) **[PRO]** -- Full WooCommerce integration guide
- [Standalone Mode](standalone-mode.md) -- Built-in checkout, no plugins needed
- [Stripe Payments](stripe-payments.md) -- Direct Stripe integration for standalone
- [Currency and Tax](currency-tax-config.md) -- Financial settings
