# Standalone Checkout Mode

WP Sell Services includes its own built-in checkout system, so you can run a fully independent marketplace without WooCommerce or any other e-commerce plugin.

## What Is Standalone Mode?

Standalone mode means your marketplace handles everything on its own -- cart, checkout, payments, and orders. No extra plugins needed. It is the default for the free version and the fastest way to get started.

## When Should You Use Standalone Mode?

Choose standalone mode if:

- You only sell services (no physical products)
- You want a lightweight, fast checkout experience
- You do not need WooCommerce extensions
- You want fewer plugins on your site

Choose WooCommerce mode if you need access to 100+ payment gateways, WooCommerce extensions like Subscriptions or Bookings, or already run a WooCommerce store.

## How It Works for Buyers

1. Buyer browses services and clicks **Add to Cart**
2. Cart page shows selected services, packages, and add-ons
3. Buyer proceeds to checkout and enters billing details
4. Buyer picks a payment method (Stripe, PayPal, bank transfer, etc.)
5. Buyer clicks **Place Order** and receives an order confirmation
6. Vendor is notified and work begins

Buyers can purchase services from multiple vendors in a single checkout. Each service becomes its own separate order with independent delivery tracking.

## Setting Up Standalone Mode

1. Go to **WP Sell Services > Settings > General**
2. Under **E-commerce Platform**, select **Standalone Mode**
3. Click **Save Changes**
4. Go to **Settings > Gateways** and enable at least one payment gateway
5. Test the full checkout with a sample service

## Available Payment Gateways

These gateways work directly with standalone mode:

| Gateway | What It Supports |
|---------|-----------------|
| **Stripe** | Credit/debit cards, Apple Pay, Google Pay |
| **PayPal** | PayPal balance, cards, Venmo |
| **Razorpay** **[PRO]** | UPI, cards, net banking, wallets (India) |
| **Offline/Bank Transfer** | Manual payments you confirm yourself |

See [Stripe Payments](stripe-payments.md) and [Other Payment Gateways](other-gateways.md) for setup details.

## What the Checkout Page Includes

- **Billing details** -- name, email, address
- **Order review** -- services, prices, and totals
- **Payment method selector** -- choose from your enabled gateways
- **Terms and conditions** checkbox (optional)
- **Place Order** button

## Standalone vs WooCommerce: Quick Comparison

| | Standalone | WooCommerce **[PRO]** |
|--|-----------|----------------------|
| Extra plugins needed | None | WooCommerce required |
| Payment gateways | 3 built-in + Razorpay **[PRO]** | 100+ via WooCommerce extensions |
| Checkout speed | Fastest | Good |
| Physical products | No | Yes |
| Best for | Pure service marketplaces | Stores that also sell products |

## Switching Between Modes

You can switch at any time from **Settings > General**. A few things to keep in mind:

- **Finish active orders first.** Existing orders stay in the system they were created in.
- **Reconfigure payment gateways** after switching, since each mode uses its own gateways.
- **Test checkout** thoroughly after any switch.

## What Buyers and Vendors See

**Vendors get a dashboard with:**
- Incoming orders and delivery management
- Service listings and editing
- Earnings tracking and withdrawal requests
- Messaging with buyers

**Buyers get:**
- Order history and active order tracking
- Messaging with vendors
- Profile and account settings

## Related Docs

- [Stripe Payments](stripe-payments.md) -- Card payments setup
- [Other Payment Gateways](other-gateways.md) -- PayPal, Razorpay, offline
- [WooCommerce Checkout](woocommerce-checkout.md) **[PRO]** -- WooCommerce integration
- [Currency and Tax](currency-tax-config.md) -- Financial settings
