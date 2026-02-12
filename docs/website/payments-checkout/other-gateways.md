# PayPal, Razorpay & Offline Payments **[PRO]**

Additional payment gateways for Standalone mode marketplaces, providing alternatives to Stripe for global payment processing.

## Overview

**Important:** These gateways are only available in **Standalone Mode** with WP Sell Services Pro.

Three additional payment options:

1. **PayPal** - Global payment solution with smart buttons
2. **Razorpay** - India-focused gateway (UPI, cards, net banking, wallets)
3. **Offline Payments** - Bank transfer, check, manual verification

## PayPal **[PRO]**

### Requirements

- PayPal Business account
- WP Sell Services Pro
- Standalone mode enabled
- SSL certificate
- PHP 8.0+

### Setup

1. Create PayPal Business account at [paypal.com/business](https://paypal.com/business)
2. Complete business verification
3. Link bank account
4. Go to [PayPal Developer Dashboard](https://developer.paypal.com/dashboard/)
5. Create app to get **Client ID** and **Secret**
6. In WordPress, go to **WP Sell Services → Settings → Payments**
7. Click **PayPal** tab
8. Enable PayPal and enter credentials
9. Click **Save Changes**

### Webhook Setup

1. In PayPal Developer Dashboard, go to **Webhooks**
2. Add endpoint: `https://yoursite.com/wp-json/wpss/v1/paypal/webhook`
3. Select events:
   - `PAYMENT.CAPTURE.COMPLETED`
   - `PAYMENT.CAPTURE.REFUNDED`
4. Save webhook
5. Copy **Webhook ID** to WP Sell Services settings

### Features

- PayPal balance or linked cards
- Credit/Debit cards (no PayPal account needed)
- Venmo (US only)
- Smart Payment Buttons

### Transaction Fees

| Type | Fee |
|------|-----|
| Domestic (US) | 2.9% + $0.30 |
| International | 4.4% + fixed fee (varies) |

## Razorpay **[PRO]**

### Requirements

- Razorpay account (India)
- Indian bank account
- WP Sell Services Pro
- Standalone mode enabled
- SSL certificate
- PHP 8.0+

### Setup

1. Sign up at [razorpay.com](https://razorpay.com)
2. Complete KYC (PAN, GSTIN, bank details)
3. Get API keys from **Settings → API Keys**
4. In WordPress, go to **WP Sell Services → Settings → Payments**
5. Click **Razorpay** tab
6. Enable Razorpay
7. Enter **Key ID** and **Key Secret**
8. Click **Save Changes**

### Webhook Setup

1. In Razorpay Dashboard, go to **Webhooks**
2. Add endpoint: `https://yoursite.com/wp-json/wpss/v1/razorpay/webhook`
3. Select events:
   - `payment.authorized`
   - `payment.captured`
   - `payment.failed`
   - `refund.created`
4. Copy **Webhook Secret** to WP Sell Services settings

### Payment Methods

- **UPI** - Google Pay, PhonePe, Paytm, BHIM (free)
- **Cards** - Visa, Mastercard, Amex, RuPay
- **Net Banking** - 50+ Indian banks
- **Wallets** - Paytm, PhonePe, Mobikwik

### Transaction Fees

| Method | Fee |
|--------|-----|
| UPI | Free (promotional) |
| Domestic Cards | 2% |
| International Cards | 3% + GST |
| Net Banking | ₹3-10 per transaction |

### Test Cards

| Card Number | Scenario |
|-------------|----------|
| 4111 1111 1111 1111 | Success |
| 4012 0000 3333 0026 | Decline |

Test UPI: `success@razorpay` (succeeds) or `failure@razorpay` (fails)

## Offline Payments **[PRO]**

### Requirements

- WP Sell Services Pro
- Standalone mode enabled

### Setup

1. Go to **WP Sell Services → Settings → Payments**
2. Click **Offline** tab
3. Enable **Offline Payments**
4. Configure settings:
   - **Title:** "Bank Transfer" or "Check Payment"
   - **Instructions:** Your bank details
5. Add payment details:
   ```
   Bank Name: Example Bank
   Account Number: 1234567890
   Routing/IFSC: EXPL0001234

   Include order number in transfer reference.
   ```
6. Click **Save Changes**

### How It Works

When buyer selects offline payment:

1. Order created with status **Pending Payment**
2. Buyer sees payment instructions
3. Buyer makes transfer and enters reference number
4. Admin receives notification
5. Admin verifies payment in bank statement
6. Admin clicks **Confirm Payment** in order
7. Order status changes to **In Progress**
8. Vendor notified to begin work

### Confirming Payments

1. Go to **WP Sell Services → Orders**
2. Filter by **Pending Payment**
3. Open order
4. Verify bank statement shows transfer
5. Match amount and reference
6. Click **Confirm Payment**
7. Order begins processing

## Gateway Comparison

| Feature | PayPal | Razorpay | Offline |
|---------|--------|----------|---------|
| Instant payment | ✓ | ✓ | ✗ |
| Auto-confirmation | ✓ | ✓ | ✗ |
| Refunds | ✓ | ✓ | Manual |
| Best for | Global | India | High-trust buyers |
| Transaction fees | 2.9-4.4% | 0-3% | None |

## Related Documentation

- [Standalone Mode](standalone-mode.md) **[PRO]** - Required for these gateways
- [Stripe Payments](stripe-payments.md) **[PRO]** - Primary card gateway
- [Alternative Platforms](alternative-platforms.md) - Other e-commerce options
- [Currency & Tax Config](currency-tax-config.md) - Financial settings
