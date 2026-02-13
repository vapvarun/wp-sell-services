# Stripe Payments **[PRO]**

Stripe provides direct payment processing for Standalone mode marketplaces, supporting cards, Apple Pay, Google Pay, and more.

## Overview

**Important:** Stripe direct integration is only available in **Standalone Mode** with WP Sell Services Pro.

- For WooCommerce mode, use WooCommerce Stripe extensions
- For EDD mode, use EDD Stripe gateway
- For standalone mode, use this built-in integration

## Requirements

- WP Sell Services Pro
- Standalone mode enabled
- Stripe account (free to create)
- SSL certificate (HTTPS required)
- PHP 8.0+

## Creating a Stripe Account

1. Visit [stripe.com](https://stripe.com)
2. Click **Sign up**
3. Enter business email and create password
4. Complete account setup:
   - Business type and details
   - Bank account information
   - Tax identification
   - Identity verification
5. Activate account

## Obtaining API Keys

### Test Mode Keys

Use these for testing before going live:

1. Log in to [Stripe Dashboard](https://dashboard.stripe.com/)
2. Toggle **Test mode** switch (top right)
3. Go to **Developers → API keys**
4. Copy **Publishable key** (starts with `pk_test_`)
5. Reveal and copy **Secret key** (starts with `sk_test_`)

### Live Mode Keys

After testing, switch to live keys:

1. In Stripe Dashboard, toggle **Test mode** OFF
2. Go to **Developers → API keys**
3. Copy **Publishable key** (starts with `pk_live_`)
4. Reveal and copy **Secret key** (starts with `sk_live_`)

**Security:** Never share your secret key or commit it to version control.

## Configuration in WP Sell Services

### Enable Standalone Mode

First, ensure standalone mode is active:

1. Go to **WP Sell Services → Settings → General**
2. Select **E-commerce Platform:** Standalone Mode
3. Click **Save Changes**

See [Standalone Mode](standalone-mode.md) for full setup.

### Configure Stripe

1. Go to **WP Sell Services → Settings → Payments**
2. Click **Stripe** tab
3. Check **Enable Stripe**
4. Enter credentials:
   - **Publishable Key:** Your `pk_test_` or `pk_live_` key
   - **Secret Key:** Your `sk_test_` or `sk_live_` key
5. Configure options:
   - **Test Mode:** ON for testing, OFF for live
   - **Title:** "Credit Card" (shown to buyers)
   - **Description:** "Pay securely with credit or debit card"
6. Click **Save Changes**


## Webhook Configuration

Webhooks notify your site when payments complete.

### Webhook URL

Copy this URL:

```
https://yoursite.com/wp-json/wpss/v1/stripe/webhook
```

### Setup Webhook in Stripe

1. Go to [Stripe Dashboard → Webhooks](https://dashboard.stripe.com/webhooks)
2. Click **Add endpoint**
3. Paste webhook URL
4. Select events to listen for:
   - `payment_intent.succeeded`
   - `payment_intent.payment_failed`
   - `charge.refunded`
5. Click **Add endpoint**
6. Copy the **Signing secret** (starts with `whsec_`)
7. Paste in **WP Sell Services → Settings → Payments → Stripe → Webhook Secret**
8. Click **Save Changes**


## Testing Stripe

### Test Card Numbers

Stripe provides test cards for different scenarios:

| Card Number | Scenario | CVC | Expiry |
|-------------|----------|-----|--------|
| 4242 4242 4242 4242 | Success | Any 3 digits | Any future date |
| 4000 0025 0000 3155 | Requires 3D Secure | Any 3 digits | Any future date |
| 4000 0000 0000 9995 | Decline (insufficient funds) | Any 3 digits | Any future date |
| 4000 0000 0000 0069 | Decline (expired card) | Any 3 digits | Any future date |

### Test Checkout

1. Add a service to cart
2. Go to checkout
3. Select **Credit Card** payment method
4. Enter test card: `4242 4242 4242 4242`
5. Enter any 3-digit CVC (e.g., 123)
6. Enter any future expiry (e.g., 12/26)
7. Click **Place Order**
8. Order should complete successfully

## Checkout Experience

When buyer selects Stripe:

1. Card input fields appear inline on checkout page
2. Buyer enters card details
3. Stripe validates card in real-time
4. 3D Secure/SCA authentication if required
5. Payment processes securely
6. Order created immediately on success
7. Buyer redirected to order confirmation

## Supported Payment Methods

### Credit & Debit Cards

- Visa
- Mastercard
- American Express
- Discover
- Diners Club
- JCB

### Digital Wallets (if enabled)

- Apple Pay (automatically shown on Safari/iOS)
- Google Pay (automatically shown on Chrome/Android)
- Payment Request API

## Transaction Fees

Stripe charges per transaction:

| Transaction Type | Fee |
|------------------|-----|
| Domestic cards (US) | 2.9% + $0.30 |
| International cards | 3.9% + $0.30 |
| Currency conversion | +1% |

Example:

```
Service price: $100.00
Stripe fee: $3.20 (2.9% + $0.30)
You receive: $96.80
```

## Refunds

### Processing Refunds

1. Go to **WP Sell Services → Orders**
2. Open order
3. Click **Refund**
4. Select **Full Refund** or **Partial Refund**
5. Enter amount (for partial)
6. Click **Process Refund**

Refunds appear in buyer's account in 5-10 business days.

### Refund Fees

- Stripe refunds the percentage fee (2.9% or 3.9%)
- Stripe does NOT refund the $0.30 fixed fee
- You lose $0.30 per refunded transaction

## Security & Compliance

### PCI Compliance

Stripe handles PCI compliance. Your site never stores or processes raw card data.

- Card data goes directly to Stripe's servers
- Stripe.js securely tokenizes payment information
- Your server only receives secure tokens

### 3D Secure / SCA

Stripe automatically handles Strong Customer Authentication (SCA) required in Europe:

- Stripe Payment Intents API includes built-in 3DS
- Authentication happens in modal overlay
- No additional configuration needed

## Troubleshooting

### Payment Fails Silently

Check webhook is configured correctly:

1. Go to Stripe Dashboard → Webhooks
2. Click on your webhook
3. Check **Recent deliveries** tab
4. Verify 200 OK responses

### Card Declined

Common reasons:

- Insufficient funds
- Incorrect card details
- Card expired
- Fraud prevention triggered

Enable debug logging:

```php
// Add to wp-config.php
define( 'WPSS_STRIPE_DEBUG', true );
```

Check logs at `wp-content/uploads/wpss-logs/stripe.log`

### Webhook Not Receiving Events

Verify:

1. Webhook URL is correct (`/wp-json/wpss/v1/stripe/webhook`)
2. Webhook secret matches in settings
3. Site is accessible (not localhost without tunnel)
4. No firewall blocking Stripe IPs

## Related Documentation

- [Standalone Mode](standalone-mode.md) **[PRO]** - Required for Stripe direct integration
- [Other Payment Gateways](other-gateways.md) **[PRO]** - PayPal, Razorpay, offline
- [Alternative Platforms](alternative-platforms.md) - Other e-commerce options
- [Currency & Tax Config](currency-tax-config.md) - Financial settings
