# Stripe Payments

Accept credit cards, debit cards, Apple Pay, and Google Pay on your marketplace using Stripe -- the most popular online payment processor.

## What You Need

- A free Stripe account at [stripe.com](https://stripe.com)
- An SSL certificate on your site (HTTPS)
- WP Sell Services with standalone mode enabled

## Setting Up Stripe

### Sandbox vs Live Mode

Always start with Stripe's **Test Mode** before processing real payments. Test mode uses separate API keys and never charges real cards.

| | Test Mode | Live Mode |
|---|-----------|-----------|
| API keys start with | `pk_test_` / `sk_test_` | `pk_live_` / `sk_live_` |
| Real charges | No | Yes |
| Webhook events | Test events only | Real events |
| Dashboard URL | Same dashboard, toggle "Test mode" | Same dashboard |

**To switch:** Toggle "Test mode" in your Stripe Dashboard (top right), copy the new keys, and update them in WP Sell Services settings. Remember to update the webhook endpoint secret too -- test and live webhooks have separate secrets.

### Get Your API Keys

Stripe gives you two sets of keys -- test keys for trying things out, and live keys for real payments.

1. Log in to [Stripe Dashboard](https://dashboard.stripe.com/)
2. Toggle **Test mode** (top right) to start with test keys
3. Go to **Developers > API keys**
4. Copy your **Publishable key** and **Secret key**

Test keys start with `pk_test_` and `sk_test_`. Live keys start with `pk_live_` and `sk_live_`.

### Add Keys to Your Site

1. Go to **WP Sell Services > Settings > Gateways**
2. Click the **Stripe** tab
3. Check **Enable Stripe**
4. Paste your Publishable Key and Secret Key
5. Turn on **Test Mode** while you are setting up
6. Set a display title like "Credit Card" (this is what buyers see)
7. Click **Save Changes**

### Set Up Webhooks

Webhooks let Stripe tell your site when a payment succeeds or fails.

1. In your Stripe Dashboard, go to **Developers > Webhooks**
2. Click **Add endpoint**
3. Paste your webhook URL: `https://yoursite.com/wpss-payment/stripe/callback`
4. Select these events: `payment_intent.succeeded`, `payment_intent.payment_failed`, `charge.refunded`
5. Click **Add endpoint**
6. Copy the **Signing secret** that appears
7. Paste it into **WP Sell Services > Settings > Gateways > Stripe > Webhook Secret**
8. Click **Save Changes**

## How Checkout Works with Stripe

When a buyer chooses to pay by card:

1. Secure card fields appear right on your checkout page
2. Stripe validates the card details in real time
3. If 3D Secure authentication is needed, a verification popup appears
4. Payment processes securely -- card data never touches your server
5. Order is created instantly on success
6. Buyer sees an order confirmation page

## Testing Before Going Live

Use Stripe's test card numbers to try out checkout without charging real money:

| Card Number | What Happens |
|-------------|-------------|
| 4242 4242 4242 4242 | Payment succeeds |
| 4000 0025 0000 3155 | 3D Secure verification required |
| 4000 0000 0000 9995 | Declined (insufficient funds) |

Use any future expiry date and any 3-digit CVC. When everything works, switch to live keys and turn off Test Mode.

## 3D Secure and Strong Customer Authentication

Stripe automatically handles 3D Secure (SCA), which is required for European transactions. No extra setup from you -- buyers see a quick verification step when their bank requires it.

## Supported Payment Methods

**Cards:** Visa, Mastercard, American Express, Discover, Diners Club, JCB

**Digital wallets:** Apple Pay shows automatically on Safari/iOS, and Google Pay shows on Chrome/Android. No additional configuration needed.

## Supported Currencies

Stripe supports 135+ currencies. Your marketplace currency is set in **Settings > General**. Stripe will process payments in whatever currency you configure.

## Stripe Connect for Direct Vendor Payments **[PRO]**

With WP Sell Services Pro, you can enable Stripe Connect so payments go directly to each vendor's Stripe account, with your platform commission deducted automatically. This means funds reach vendors faster and you avoid handling payouts manually.

## Refunds

To refund an order:

1. Go to **WP Sell Services > Orders** and open the order
2. Click **Refund**
3. Choose full or partial refund
4. Click **Process Refund**

Refunds appear in the buyer's account within 5-10 business days. Stripe refunds the percentage fee but keeps the fixed fee (typically $0.30 per transaction).

## Transaction Fees

Stripe charges per transaction (rates vary by country):

- **Domestic cards (US):** 2.9% + $0.30
- **International cards:** 3.9% + $0.30

For example, on a $100 service, Stripe keeps about $3.20 and you receive $96.80.

## Security

Your site never stores or processes card data. Stripe handles all PCI compliance through their secure payment form. Card details go directly to Stripe's servers, and your site only receives a secure payment token.

## Troubleshooting

| Problem | Solution |
|---------|----------|
| "Payment failed" on every attempt | Check that your keys match the mode (test keys for test mode, live keys for live mode) |
| Webhooks not received | Verify the webhook URL is exactly `https://yoursite.com/wpss-payment/stripe/callback` (no trailing parameters). Check your site uses HTTPS. |
| 3D Secure popup not appearing | Ensure your site is not blocking iframes. Some security plugins block the Stripe verification popup. |
| "No such payment_intent" error | You are mixing test and live keys. Ensure both publishable and secret keys are from the same mode. |
| Webhook signature verification failed | The webhook signing secret must match the specific endpoint. Re-copy it from Stripe Dashboard > Developers > Webhooks > your endpoint > Signing secret. |
| Apple Pay / Google Pay not showing | These require HTTPS and domain verification in Stripe Dashboard > Settings > Payment methods. |

## Related Docs

- [Standalone Mode](standalone-mode.md) -- Required for Stripe direct integration
- [Other Payment Gateways](other-gateways.md) -- PayPal, Razorpay, offline
- [Currency and Tax](currency-tax-config.md) -- Financial settings
