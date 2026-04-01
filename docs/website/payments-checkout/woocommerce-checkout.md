# WooCommerce Checkout Integration **[PRO]**

If you already use WooCommerce, WP Sell Services Pro can plug right into it -- giving your marketplace access to WooCommerce's payment gateways, checkout, and order system.

## How It Works

When a buyer purchases a service, here is what happens behind the scenes:

1. The service is added to the WooCommerce cart (using a special hidden product)
2. Buyer completes the standard WooCommerce checkout you are already familiar with
3. The plugin automatically creates separate marketplace orders -- one per service/vendor
4. Order statuses stay in sync between WooCommerce and the marketplace

You get the best of both worlds: WooCommerce's mature payment ecosystem and WP Sell Services' purpose-built order management.

## Why Use WooCommerce Mode?

| Benefit | Details |
|---------|---------|
| 100+ payment gateways | Use any WooCommerce payment extension |
| Familiar admin experience | Manage service orders alongside product orders |
| Extension ecosystem | Compatible with WooCommerce Subscriptions, Bookings, and more |
| HPOS compatible | Works with WooCommerce's High-Performance Order Storage |

## Setting It Up

1. Install and activate WooCommerce (if you have not already)
2. Complete the WooCommerce setup wizard
3. Go to **WP Sell Services > Settings > General**
4. The plugin auto-detects WooCommerce -- no manual selection needed
5. A hidden "Service Order" product is created automatically (this powers the checkout)

That is it. Services are now purchasable through WooCommerce checkout.

**Important:** Do not delete the "Service Order" product from your Products list. If it disappears, just save your WP Sell Services settings again to recreate it.

## Cart and Checkout Experience

When buyers add services to their cart:

- The cart shows the **service title**, **package name** (Basic, Standard, or Premium), **vendor name**, and **selected add-ons**
- Pricing is calculated dynamically from the service data
- Buyers can purchase services from **multiple vendors** in a single checkout
- After payment, the plugin splits the WooCommerce order into separate marketplace orders -- one per service, each with its own vendor, deadline, and conversation

## Order Sync Between Systems

Orders stay connected between WooCommerce and your marketplace:

**WooCommerce to Marketplace:** When a WooCommerce order status changes (e.g., payment received, cancelled, refunded), the linked marketplace orders update automatically.

**Marketplace to WooCommerce:** When a service order is marked as completed or cancelled in the marketplace, the WooCommerce order updates too.

This means you can manage orders from either place and they stay consistent.

## What Buyers and Vendors See

**Buyers** see their service orders in:
- The WooCommerce **My Account > Orders** tab (WooCommerce orders)
- A dedicated **Service Orders** tab with delivery tracking, messaging, and file downloads

**Vendors** see their dashboard with:
- Incoming orders and delivery management
- Earnings overview and withdrawal requests
- Service listings and messaging

## Multi-Vendor Cart Support

Buyers can add services from different vendors into one cart. After checkout, each service becomes its own independent order with:

- Its own vendor assignment
- Separate delivery deadline
- Independent requirements gathering
- Private conversation between buyer and vendor
- Individual dispute handling if needed

## When to Choose WooCommerce vs Standalone

| Choose WooCommerce if you... | Choose Standalone if you... |
|----|-----|
| Already run a WooCommerce store | Want a lightweight setup with no extra plugins |
| Need a specific WooCommerce payment gateway | Only need Stripe, PayPal, or bank transfer |
| Want to sell physical products alongside services | Run a pure service marketplace |
| Use WooCommerce extensions (Subscriptions, etc.) | Want the fastest possible checkout |

## Related Docs

- [Standalone Mode](standalone-mode.md) -- Built-in checkout without WooCommerce
- [Alternative Platforms](alternative-platforms.md) **[PRO]** -- EDD, FluentCart, SureCart
- [Currency and Tax](currency-tax-config.md) -- Financial settings
