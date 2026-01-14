# WP Sell Services Pro

Pro takes your marketplace further with more payment options, better seller tools, cloud storage, and detailed analytics to grow your business.

## What You Get with Pro

### Remove All Limits

The free version has sensible limits. Pro removes them all:

| Feature | Free | Pro |
|---------|------|-----|
| Pricing Packages | 3 | Unlimited |
| Gallery Images | 5 | Unlimited |
| Videos | 1 | Unlimited |
| FAQs | 5 | Unlimited |
| Service Extras | 3 | Unlimited |

Your sellers can create as comprehensive a listing as they want.

### Better Seller Tools

| Feature | What It Does |
|---------|--------------|
| Copy Services | Duplicate a service to create variations quickly |
| Schedule Services | Set services to go live at a specific date/time |
| Service Templates | Start from proven formats |
| AI Descriptions | Generate compelling service descriptions |
| Bulk Editing | Update multiple services at once |
| Per-Service Stats | See how each service performs |

## Payment Options

### Payment Platforms

Choose what works best for your business:

| Platform | Best For |
|----------|----------|
| WooCommerce | Most sites - already included in free version |
| Easy Digital Downloads | Sites already using EDD |
| Fluent Cart | Modern, lightweight checkout |
| SureCart | Headless commerce, subscription focus |
| Standalone Mode | Run without any other e-commerce plugin |

### Easy Digital Downloads

If you already have an EDD-powered site, use your existing setup:

1. Make sure EDD is installed and configured
2. Go to **WP Sell Services → Settings → General**
3. Select "Easy Digital Downloads"
4. Your services will use EDD's checkout

### Fluent Cart

A lightweight, modern checkout experience:

1. Install and set up Fluent Cart
2. Select "Fluent Cart" in your settings
3. Services use Fluent Cart's checkout flow

### SureCart

Great for subscriptions and digital products:

1. Install SureCart and connect your account
2. Select "SureCart" in your settings
3. Configure how products map to services

### Standalone Mode

Run your marketplace without WooCommerce or any other e-commerce plugin. Payments go directly through Stripe, PayPal, or Razorpay.

**Perfect for:**
- New sites without existing e-commerce setup
- Simple payment needs
- Maximum control over checkout

**Setup:**
1. Select "Standalone" as your platform
2. Set up at least one payment gateway (see below)
3. Orders process directly through your chosen gateway

## Direct Payment Gateways

When using Standalone mode, connect to these payment processors directly.

**Important:** These only work in Standalone mode. If you're using WooCommerce, EDD, or other platforms, use their built-in payment settings instead.

### Stripe

Accept credit cards, Apple Pay, Google Pay, and more.

**Getting Started:**

1. Create a Stripe account at stripe.com
2. Go to **WP Sell Services → Settings → Stripe**
3. Enter your keys:
   - Publishable Key (starts with pk_)
   - Secret Key (starts with sk_)
   - Webhook Secret (starts with whsec_)
4. Turn on test mode while setting up

**Connecting Notifications:**

Stripe needs to tell your site when payments happen. Add this address in your Stripe Dashboard:
```
https://yoursite.com/wp-json/wpss/v1/webhooks/stripe
```

Tell Stripe to send these events:
- `payment_intent.succeeded` - Payment completed
- `payment_intent.payment_failed` - Payment failed
- `charge.refunded` - Refund processed

**What Stripe Accepts:**
- Credit and debit cards
- Apple Pay and Google Pay
- Works worldwide with automatic currency conversion

**Currencies:** USD, EUR, GBP, CAD, AUD, JPY, CHF, SEK, NOK, DKK, NZD, SGD, HKD, MXN, BRL, INR, PLN, CZK

**Fee Options:**

You can pass gateway fees to buyers or absorb them yourself:

| Setting | What It Does |
|---------|-------------|
| Gateway Fee % | Percentage added per transaction (e.g., 2.9%) |
| Fixed Fee | Flat amount per transaction (e.g., $0.30) |
| Pass to Buyer | Add fees to buyer's total instead of taking from seller |

### PayPal

Accept PayPal payments and credit cards through PayPal's system.

**Getting Started:**

1. Go to **WP Sell Services → Settings → PayPal**
2. Connect your PayPal merchant account
3. Choose sandbox (testing) or live mode
4. Set up the notification webhook

**Connecting Notifications:**

Add this address in your PayPal Developer Dashboard:
```
https://yoursite.com/wp-json/wpss/v1/webhooks/paypal
```

Tell PayPal to send these events:
- `CHECKOUT.ORDER.APPROVED` - Buyer approved checkout
- `PAYMENT.CAPTURE.COMPLETED` - Payment captured
- `PAYMENT.CAPTURE.REFUNDED` - Refund processed

**What PayPal Accepts:**
- PayPal checkout
- Credit and debit cards
- Pay Later options
- Automatic seller protection

**Currencies:** USD, EUR, GBP, CAD, AUD, JPY, CHF, SEK, NOK, DKK, NZD, SGD, HKD, MXN, BRL, INR, PLN, CZK, HUF, ILS, PHP, THB, TWD, RUB, MYR

### Razorpay

Popular choice for Indian marketplaces with support for local payment methods.

**Getting Started:**

1. Go to **WP Sell Services → Settings → Razorpay**
2. Enter your Key ID and Key Secret
3. Set up the notification webhook
4. Optionally customize the checkout color

**Connecting Notifications:**

Add this address in your Razorpay Dashboard:
```
https://yoursite.com/wp-json/wpss/v1/webhooks/razorpay
```

Tell Razorpay to send these events:
- `payment.captured` - Payment successful
- `payment.failed` - Payment failed
- `refund.created` - Refund processed
- `order.paid` - Order completed

**What Razorpay Accepts:**
- UPI payments
- Credit and debit cards
- Net banking
- Wallets (Paytm, PhonePe, and more)

**Currencies:** INR (primary), USD, EUR, GBP, SGD, AED, AUD, CAD, CNY, HKD, JPY, MYR, MXN, NZD, PHP, RUB, SAR, THB

**Extra Settings:**
- Auto-Capture: Automatically capture payments (recommended)
- Theme Color: Match the checkout to your brand

## Seller Wallets

The wallet system manages seller earnings and payouts.

### Built-In Wallet

The default wallet tracks everything for you:

- Automatically credits earnings when orders complete
- Handles withdrawal requests
- Keeps transaction history
- Includes admin approval process

**Setting Up:**

1. Go to **WP Sell Services → Settings → Wallet**
2. Select "Internal Wallet"
3. Configure:
   - Minimum withdrawal amount
   - Clearance period (days before funds are available)
   - Auto-payout option

### How Money Flows

```
Order Completes → Clearance Period → Available Balance → Withdrawal Request → Admin Approval → Payout
```

**Auto-payout:** Turn this on to automatically credit earnings when orders complete. Sellers still request withdrawals, but you skip manual approval for the initial credit.

### Third-Party Wallets

If you already use a wallet plugin, connect to it:

| Provider | What It Is |
|----------|-----------|
| TeraWallet | WooCommerce wallet plugin |
| WooWallet | Another WooCommerce wallet |
| MyCred | Points-based reward system |

Setup:
1. Install and configure the wallet plugin
2. Select it in WP Sell Services wallet settings
3. Seller earnings sync automatically

## Cloud Storage

Store delivery files in the cloud instead of your server.

### Why Use Cloud Storage?

- Handle large files without filling up your server
- Faster downloads for buyers worldwide
- Better security and backup
- Works great for video, audio, and large design files

### Amazon S3

The enterprise choice with global reach.

**Setup:**

1. Create an S3 bucket in your AWS account
2. Create a user with permission to access the bucket
3. Go to **WP Sell Services → Settings → Storage**
4. Enter your:
   - Access Key ID
   - Secret Access Key
   - Bucket Name
   - Region
5. Optionally set up CloudFront for faster downloads

**Benefits:**
- Scales to any size
- Global content delivery available
- Automatic encryption
- Fine-grained access control

### Google Cloud Storage

Perfect if you're already in the Google ecosystem.

**Setup:**

1. Create a storage bucket in Google Cloud
2. Create a service account with storage permissions
3. Download the JSON key file
4. Upload the key or paste its contents in settings
5. Enter your bucket name

**Benefits:**
- Integrates with Google services
- Multi-region options
- Strong security model

### DigitalOcean Spaces

Simple and affordable cloud storage.

**Setup:**

1. Create a Space in your DigitalOcean account
2. Generate access keys
3. Enter credentials in settings
4. Optionally enable the CDN

**Benefits:**
- Simple pricing ($5/month for 250GB)
- Built-in CDN option
- Easy to set up

## Analytics Dashboard

See how your marketplace is performing with charts, trends, and downloadable reports.

### What You Can Track

| Section | What It Shows |
|---------|--------------|
| Revenue | Total earnings, trends over time, comparisons |
| Orders | Count, status breakdown, completion rate |
| Services | Top performers, views, conversion rates |
| Sellers | Top earners, ratings, activity levels |

### Time Periods

Analyze your data across:
- Last 7 days
- Last 30 days
- Last 90 days
- Last 12 months
- Custom date range

### Charts

Visual representations including:
- Revenue trend lines
- Order status breakdowns
- Service performance comparisons
- Seller earnings rankings

### Export Your Data

Download reports as:
- CSV (works with any spreadsheet)
- Excel (.xlsx format)

Available reports:
- Orders
- Revenue
- Seller earnings
- Service performance

### Dashboard Widgets

Four widgets appear on your WordPress admin dashboard:

1. **Revenue Overview** - Quick revenue snapshot
2. **Orders Summary** - Order counts and statuses
3. **Top Services** - Best-performing services
4. **Top Sellers** - Highest-earning sellers

### Analytics Settings

Go to **WP Sell Services → Settings → Analytics** to configure:

| Setting | Options | What It Does |
|---------|---------|--------------|
| Data Retention | 30 days, 90 days, 1 year, Forever | How long to keep detailed data |
| Seller Analytics | Yes/No | Let sellers see their own stats |

**Data Retention:**
- Shorter periods save database space
- Longer periods let you analyze historical trends
- Summary data is always kept regardless

**Seller Analytics:**
When enabled, sellers see a simplified dashboard with:
- Order trends
- Revenue over time
- Service performance
- Review trends

## AI-Powered Descriptions

Help sellers write better service descriptions using AI.

### Setting Up AI

1. Create an OpenAI account at [platform.openai.com](https://platform.openai.com)
2. Go to API Keys and create a new key
3. Go to **WP Sell Services → Settings → AI**
4. Paste your API key
5. Choose a model:
   - GPT-3.5-turbo: Fast and affordable (recommended for most)
   - GPT-4: Higher quality, costs more

### How Sellers Use It

1. Enter their service title and key points
2. Click **Generate Description**
3. AI creates a draft
4. They edit and personalize it

### Costs

Each description uses OpenAI credits:
- GPT-3.5-turbo: About $0.002 per description
- GPT-4: About $0.06 per description

Monitor usage at platform.openai.com.

### Tips for Better Results

- Provide specific key points
- Include target audience
- Mention unique selling points
- Always review and personalize the output

## Installing Pro

### Requirements

- WP Sell Services (free) version 1.0.0+
- WordPress 6.4+
- PHP 8.1+
- Valid Pro license

### Installation

1. Purchase WP Sell Services Pro
2. Download the ZIP file from your account
3. Go to **Plugins → Add New → Upload**
4. Upload and activate
5. Go to **WP Sell Services → Settings → License**
6. Enter and activate your license key

### Getting Updates

With a valid license, updates appear automatically:

1. Go to **Plugins → Updates**
2. Update WP Sell Services Pro when available
3. Always update the free plugin first, then Pro

## Troubleshooting

### License Problems

**Won't activate:**
- Check for extra spaces in the key
- Make sure your site URL matches what you registered
- Verify you haven't exceeded your activation limit
- Contact support if it persists

**License expired:**
- Renew through your account
- Reactivate after renewal

### Payment Gateway Issues

**Not working:**
- Verify the platform plugin is active
- Check your credentials are correct
- Review error logs for details
- Enable test mode to debug

### Cloud Storage Problems

**Uploads failing:**
- Verify credentials and permissions
- Check bucket/region configuration
- Try a small file first
- Check PHP upload limits

### Getting Help

For Pro-specific issues:
1. Check this documentation
2. Review error logs
3. Contact Pro support with:
   - Your license key
   - Error messages
   - Steps to reproduce
   - WordPress and PHP versions

Pro users get priority support included with your license.
