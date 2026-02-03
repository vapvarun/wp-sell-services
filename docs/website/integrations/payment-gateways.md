# Payment Gateways

Configure payment processing for your marketplace using WooCommerce gateways or Pro direct integrations.

## Payment Gateway Overview

WP Sell Services supports multiple payment processing methods depending on your e-commerce platform.

### Gateway Options

| Gateway | Free | Pro | Platforms |
|---------|------|-----|-----------|
| **WooCommerce Gateways** | ✓ | ✓ | WooCommerce only |
| **Stripe Direct** | | **[PRO]** | All platforms |
| **PayPal Direct** | | **[PRO]** | All platforms |
| **Razorpay** | | **[PRO]** | All platforms |

## WooCommerce Payment Gateways (Free)

Use any WooCommerce payment gateway with the free version.

### Included with WooCommerce

**PayPal Standard:**
- Free with WooCommerce
- PayPal-hosted payment
- Simple setup
- Worldwide coverage

**Direct Bank Transfer:**
- Manual payment method
- Buyer transfers money offline
- Admin marks order as paid
- Good for local/trusted transactions

**Check Payments:**
- Offline payment method
- Order on hold until check received
- Manual fulfillment

**Cash on Delivery:**
- Not applicable for digital services
- Can be repurposed for "Pay Later" scenarios

### Popular WooCommerce Gateway Extensions

**Stripe for WooCommerce:**
- Credit/debit cards
- Apple Pay & Google Pay
- Strong Customer Authentication (SCA)
- No redirects (inline checkout)

**PayPal Checkout:**
- PayPal & credit cards
- One-touch checkout
- Venmo support (US)
- Pay Later options

**Square:**
- Credit/debit cards
- In-person and online
- Integrated POS
- US, Canada, UK, Australia

**Authorize.Net:**
- Traditional payment processor
- Credit/debit cards
- eChecks
- Recurring billing

### Setup WooCommerce Gateways

**Configure Payment Methods:**

1. Go to **WooCommerce → Settings → Payments**
2. See list of available payment methods
3. Click gateway to configure
4. Enter API credentials (varies by gateway)
5. Enable gateway
6. Save changes
7. Test with test order

**Test Mode:**
Most gateways offer test/sandbox mode:
- Use test API keys
- Process test transactions
- No real money charged
- Verify integration works

**Example: Stripe Setup**

1. Install **WooCommerce Stripe Gateway** plugin
2. Activate plugin
3. Go to **WooCommerce → Settings → Payments → Stripe**
4. Get API keys from Stripe dashboard
5. Enter **Publishable Key** and **Secret Key**
6. Enable **Test Mode** (for testing)
7. Configure **Statement Descriptor** (appears on credit card statements)
8. Enable **Apple Pay** and **Google Pay** (optional)
9. Save changes
10. Test purchase

**Supported Currencies:**
Check gateway documentation for supported currencies. Most support major currencies (USD, EUR, GBP, etc.).

---

## Stripe Direct Checkout (Pro)

**[PRO]** Direct Stripe integration without WooCommerce, optimized for marketplaces.

### Why Stripe Direct?

**Advantages:**
- Works with any e-commerce platform (including Standalone)
- Stripe Connect for automatic vendor payouts
- Split payments (platform commission + vendor payout)
- No WooCommerce overhead
- Modern checkout experience
- Strong fraud protection

**Best For:**
- Automated vendor payouts
- Multi-vendor marketplaces
- International platforms
- Modern payment UX

### Stripe Direct Setup

**Requirements:**
- WP Sell Services Pro
- Stripe account
- SSL certificate (HTTPS)

**Configuration Steps:**

1. **Create Stripe Account:**
   - Sign up at stripe.com
   - Complete business verification
   - Note your country

2. **Get API Keys:**
   - Go to Stripe Dashboard → Developers → API Keys
   - Copy **Publishable Key** and **Secret Key**
   - For testing: Use test mode keys

3. **Configure in WP Sell Services:**
   - Go to **WP Sell Services → Settings → Payments → Stripe**
   - Enable **Stripe Direct Checkout**
   - Enter **Publishable Key**
   - Enter **Secret Key**
   - Select **Stripe Account Country**
   - Save changes

4. **Enable Stripe Connect** (for automatic payouts):
   - In Stripe settings, enable **Stripe Connect**
   - Configure **Application Fee** (your commission)
   - Vendors connect their Stripe accounts
   - Payments automatically split

### Stripe Checkout Features

**Payment Methods:**
- Credit/debit cards (Visa, Mastercard, Amex, etc.)
- Apple Pay
- Google Pay
- Link (Stripe's one-click checkout)
- Alipay (China)
- WeChat Pay (China)
- SEPA Direct Debit (Europe)
- And 40+ more

**Checkout Experience:**
- Hosted checkout page (Stripe-hosted)
- Embedded checkout (on your site) **[PRO]**
- Mobile-optimized
- Multi-language support
- Automatic tax collection (Stripe Tax)

**Security:**
- PCI compliance handled by Stripe
- 3D Secure authentication
- Fraud detection (Stripe Radar)
- Dispute management

### Stripe Connect for Vendor Payouts

**How It Works:**

1. **Vendor Connects Stripe:**
   - Vendor goes to dashboard
   - Clicks "Connect Stripe Account"
   - Redirected to Stripe
   - Authorizes connection
   - Returns to your site

2. **Buyer Makes Purchase:**
   - Buyer pays $100 for service
   - Payment goes to platform Stripe account

3. **Automatic Split:**
   - Platform commission: $15 (held in platform account)
   - Vendor payout: $85 (transferred to vendor's Stripe account)
   - Transfer happens immediately or on schedule

4. **Vendor Receives Funds:**
   - Funds appear in vendor's Stripe account
   - Vendor withdraws to bank (via Stripe)
   - Platform doesn't handle manual payouts

**Configuration:**
```
Commission Rate: 15%
Order Total: $100
Platform Receives: $15 (application fee)
Vendor Receives: $85 (automatic transfer)
```

### Supported Currencies

Stripe supports 135+ currencies:
- USD, EUR, GBP, CAD, AUD
- JPY, CNY, INR, BRL, MXN
- And many more

**Dynamic Currency Conversion:**
Buyers can pay in their local currency while you receive in yours.

---

## PayPal Direct Checkout (Pro)

**[PRO]** Direct PayPal integration for marketplaces.

### Why PayPal Direct?

**Advantages:**
- No WooCommerce required
- Worldwide recognition
- Buyer protection
- Works with Standalone mode
- PayPal for Marketplaces support

**Best For:**
- Platforms targeting PayPal users
- International marketplaces
- Regions where PayPal is dominant
- Buyer protection emphasis

### PayPal Direct Setup

**Requirements:**
- WP Sell Services Pro
- PayPal Business account
- SSL certificate

**Configuration:**

1. **Create PayPal Business Account:**
   - Sign up at paypal.com
   - Verify business
   - Complete account setup

2. **Get API Credentials:**
   - Log in to PayPal
   - Go to Developer Dashboard
   - Create REST API app
   - Copy **Client ID** and **Secret**

3. **Configure in WP Sell Services:**
   - Go to **WP Sell Services → Settings → Payments → PayPal**
   - Enable **PayPal Direct Checkout**
   - Enter **Client ID**
   - Enter **Secret**
   - Select mode (Sandbox for testing, Live for production)
   - Save changes

### PayPal Checkout Features

**Payment Methods:**
- PayPal balance
- Credit/debit cards (via PayPal)
- PayPal Credit
- Venmo (US only)
- Bank accounts (ACH in US)

**Checkout Options:**
- Smart Payment Buttons
- PayPal Checkout page
- One-touch checkout
- Guest checkout (credit card without PayPal account)

**Buyer Protection:**
- Purchase protection
- Dispute resolution
- Money-back guarantee
- Seller protection (for vendors)

### PayPal for Marketplaces

**Automatic Vendor Payouts:**

**Setup:**
1. Upgrade to PayPal for Marketplaces account
2. Configure in WP Sell Services Pro
3. Vendors receive instant payouts

**Payment Flow:**
1. Buyer pays $100 via PayPal
2. Payment goes to platform PayPal account
3. Platform commission: $15 (retained)
4. Vendor payout: $85 (sent via PayPal mass payment)
5. Vendor receives $85 in their PayPal account

**Payout Options:**
- Instant payouts (to PayPal account)
- Bank transfer (slower, lower fees)
- Schedule: Immediate, daily, weekly

### PayPal Fees

**Standard Rates (vary by country):**
- US: 2.9% + $0.30 per transaction
- International: 4.4% + fixed fee
- Currency conversion: 3-4%

**Marketplace Rates:**
- PayPal for Marketplaces: Custom pricing (negotiate)

**Who Pays Fees:**
- Typically absorbed by platform or vendor
- Can add fee to buyer (check local regulations)

---

## Razorpay (Pro)

**[PRO]** Payment gateway for India and Southeast Asia.

### Why Razorpay?

**Advantages:**
- Optimized for Indian market
- 100+ payment methods (UPI, cards, wallets, netbanking)
- Razorpay Route for vendor payouts
- Low fees
- Instant settlements

**Best For:**
- India-based marketplaces
- Southeast Asian platforms
- Businesses targeting Indian buyers
- UPI payment support

### Razorpay Setup

**Requirements:**
- WP Sell Services Pro
- Razorpay account (Indian business)
- GST registration (India)

**Configuration:**

1. **Create Razorpay Account:**
   - Sign up at razorpay.com
   - Complete KYC verification
   - Activate account

2. **Get API Keys:**
   - Go to Razorpay Dashboard → Settings → API Keys
   - Generate keys
   - Copy **Key ID** and **Key Secret**

3. **Configure in WP Sell Services:**
   - Go to **WP Sell Services → Settings → Payments → Razorpay**
   - Enable **Razorpay**
   - Enter **Key ID**
   - Enter **Key Secret**
   - Save changes

### Razorpay Payment Methods

**Supported Methods:**
- **UPI** - Unified Payments Interface (Google Pay, PhonePe, Paytm)
- **Cards** - Credit/debit cards
- **Netbanking** - All major banks
- **Wallets** - Paytm, PhonePe, Amazon Pay, etc.
- **EMI** - Easy monthly installments
- **PayLater** - Buy now, pay later options

**Checkout Experience:**
- All-in-one checkout modal
- Smart method ranking (most popular first)
- Mobile-optimized
- Regional language support

### Razorpay Route for Vendor Payouts

**Automated Payouts:**

**Setup:**
1. Enable Razorpay Route in dashboard
2. Configure linked accounts
3. Vendors add bank details or UPI ID
4. Automatic payouts on order completion

**Payment Flow:**
1. Buyer pays ₹10,000 via Razorpay
2. Payment to platform account
3. Commission: ₹1,500 (retained)
4. Vendor payout: ₹8,500 (instant transfer)
5. Vendor receives in bank account or UPI

**Payout Speed:**
- UPI: Instant
- Bank transfer: Within 2 hours
- Batch payouts: End of day

### Razorpay Fees

**Payment Acceptance:**
- Domestic cards: 2% + GST
- UPI: 0% (free for now, may change)
- Netbanking: 2% + GST
- Wallets: 2% + GST
- International cards: 3% + GST

**Payout Fees:**
- Razorpay Route transfers: ₹2-5 per transfer
- Instant settlements: Additional fee

---

## Refund Processing

Handle refunds across different payment gateways.

### Full Refunds

**Process:**
1. Admin or vendor initiates refund
2. Refund request sent to payment gateway
3. Gateway processes refund
4. Buyer receives money back (2-10 days depending on gateway)
5. Order status updated to "Refunded"

**Commission Handling:**
- Platform commission is reversed
- Vendor's deducted commission is restored
- If payout already sent to vendor, manual adjustment needed

### Partial Refunds

**Example:**
- Order total: $100
- Refund: $30
- Buyer receives: $30 refund
- Vendor keeps: $70 - commission

**Configuration:**
1. Go to order details
2. Click "Refund"
3. Enter refund amount
4. Add refund reason
5. Process refund

### Gateway-Specific Refunds

**Stripe:**
- Instant refund processing
- Refunds to original payment method
- Fees partially refunded by Stripe

**PayPal:**
- Refund to PayPal balance or original payment method
- Full refund of PayPal fees
- Processed within minutes

**Razorpay:**
- Refund to original payment method
- 5-7 days for card refunds
- Instant for UPI/wallet refunds

---

## Currency Support

Configure multi-currency payment acceptance.

### Single Currency

**Setup:**
1. Go to **WP Sell Services → Settings → General**
2. Select **Currency** (e.g., USD)
3. All services priced in selected currency
4. Buyers pay in that currency

### Multi-Currency (Pro)

**[PRO]** Accept payments in multiple currencies.

**Features:**
- Automatic currency conversion
- Display prices in buyer's local currency
- Checkout in preferred currency
- Exchange rates updated daily

**Configuration:**
1. Enable **Multi-Currency** **[PRO]**
2. Select base currency
3. Add additional currencies
4. Set conversion source (auto or manual rates)
5. Buyers see prices in their currency

**Example:**
- Service price: $100 USD
- British buyer sees: £75 GBP
- Indian buyer sees: ₹8,300 INR
- Checkout in selected currency

---

## Test Mode

Test payment processing before going live.

### Enable Test Mode

**Stripe:**
1. Use test API keys (start with `pk_test_` and `sk_test_`)
2. Test card: 4242 4242 4242 4242
3. Any future expiry date, any CVC

**PayPal:**
1. Use sandbox credentials
2. Create test buyer/seller accounts in PayPal Sandbox
3. Process test transactions

**Razorpay:**
1. Use test mode keys
2. Test card: 4111 1111 1111 1111
3. Test UPI: success@razorpay

**Testing Checklist:**
- ✓ Service purchase completes
- ✓ Payment processes successfully
- ✓ Service order created
- ✓ Vendor receives notification
- ✓ Buyer receives confirmation
- ✓ Commission calculated correctly
- ✓ Test refund processing

---

## Troubleshooting

### Payment Fails at Checkout

**Check:**
1. API keys are correct (no typos)
2. Test mode matches keys (test keys for test mode)
3. SSL certificate valid (HTTPS required)
4. No JavaScript errors in browser console
5. Gateway account active and verified

### Webhooks Not Working

**Verify:**
1. Webhook URL configured in gateway dashboard
2. Webhook endpoint accessible (not blocked by firewall)
3. Webhook secret entered correctly
4. Check webhook logs in gateway dashboard
5. No 500 errors on webhook receiver

### Commission Not Split Correctly

**Troubleshoot:**
1. Commission rate configured in settings
2. Stripe Connect or marketplace payout enabled
3. Vendor account connected
4. No errors in payout logs
5. Test with small amount first

### Currency Mismatch

**Fix:**
1. Ensure currency matches in WP Sell Services and gateway
2. Check multi-currency settings
3. Verify gateway supports selected currency
4. Clear cache and test again

---

## Security Best Practices

### Protecting API Keys

**Do:**
- Store keys in wp-config.php (outside web root if possible)
- Use environment variables
- Never commit keys to version control
- Rotate keys periodically

**Don't:**
- Hardcode keys in theme files
- Share keys via email/chat
- Use production keys in development
- Leave test mode on in production

### PCI Compliance

**Card Data Handling:**
- Never store card numbers on your server
- Use gateway-hosted checkout pages (Stripe Checkout, PayPal)
- Tokenize payment methods
- Let gateway handle PCI compliance

**Platform Responsibility:**
- Secure connection (SSL/TLS)
- Regular security updates
- Monitor for fraud
- Secure admin access

---

## Related Documentation

- [WooCommerce Setup](woocommerce-setup.md) - WooCommerce gateways
- [Alternative E-commerce](alternative-ecommerce.md) - Platform options
- [Payment Settings](../admin-settings/payment-settings.md) - Commission configuration
- [Wallet Systems](wallet-systems.md) - Vendor payout options

---

## Next Steps

1. Choose payment gateway(s) for your marketplace
2. Create gateway account(s)
3. Configure gateway in WP Sell Services
4. Test payment flow end-to-end
5. Switch to live mode
6. Monitor transactions and disputes
