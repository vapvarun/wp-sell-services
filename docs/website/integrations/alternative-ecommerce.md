# Alternative E-commerce Platforms

**[PRO]** WP Sell Services Pro supports multiple e-commerce platforms beyond WooCommerce, giving you flexibility in how you process payments and manage products.

## Supported Platforms

### Platform Overview

| Platform | Best For | Complexity | Performance |
|----------|----------|------------|-------------|
| **WooCommerce** (Free) | Full-featured stores | Medium | Good |
| **Easy Digital Downloads** **[PRO]** | Digital products | Low | Excellent |
| **FluentCart** **[PRO]** | Lightweight stores | Very Low | Excellent |
| **SureCart** **[PRO]** | Modern checkouts | Low | Excellent |
| **Standalone Mode** **[PRO]** | No extra plugin | Minimal | Best |

## Easy Digital Downloads (EDD)

Perfect for marketplaces focused on digital services and products.

### Why Choose EDD?

**Advantages:**
- Lightweight and fast
- Built specifically for digital products
- Simpler than WooCommerce
- Lower resource usage
- Excellent reporting
- Strong ecosystem

**Best For:**
- Digital service marketplaces
- Download-heavy platforms
- Sites prioritizing performance
- Developers familiar with EDD

### EDD Setup

**Requirements:**
- Easy Digital Downloads 3.0+
- WP Sell Services Pro
- PHP 8.0+

**Installation:**

1. Install Easy Digital Downloads plugin
2. Activate EDD
3. Go to **Downloads → Settings**
4. Configure payment gateways
5. Set currency and location
6. Go to **WP Sell Services → Settings → General**
7. Select **E-commerce Platform:** Easy Digital Downloads
8. Save changes

**Configuration Steps:**

**Payment Gateways:**
- PayPal Standard (free)
- Stripe (requires EDD Stripe extension)
- Manual payments
- Other EDD payment gateway extensions

**Tax Settings:**
- Enable/disable taxes
- Configure tax rates by location
- EU VAT support

**Download Settings:**
- File download method
- Download limits (not applicable for services)
- File download log

### EDD Integration Features

**Service-to-Download Mapping:**
- Service creates EDD download
- Service packages = price variations
- Service files = download files
- Service categories = download categories

**Order Processing:**
- EDD payment → Service order created
- Requirements collected
- Vendor delivers work
- Files added to EDD download
- Buyer receives download link

**Reporting:**
- EDD sales reports
- Service-specific analytics
- Vendor earnings tracking
- Export to CSV

### EDD-Specific Features

**Variable Pricing:**
Services with multiple packages use EDD variable pricing:

```
Basic Package: $50
Standard Package: $100
Premium Package: $200
```

**Download Tracking:**
Track delivery file downloads:
- Download count per order
- IP address logging
- Download date/time

**Customer Management:**
- EDD customer profiles
- Purchase history
- Customer notes
- Lifetime value tracking

---

## FluentCart

Ultra-lightweight cart and checkout solution.

### Why Choose FluentCart?

**Advantages:**
- Extremely lightweight (minimal code)
- Fast performance
- Simple setup
- Modern interface
- Low overhead

**Best For:**
- Speed-critical sites
- Minimalist marketplaces
- Sites wanting simple checkout
- Performance optimization focus

### FluentCart Setup

**Requirements:**
- FluentCart plugin
- WP Sell Services Pro
- PHP 8.0+

**Installation:**

1. Install FluentCart plugin
2. Activate FluentCart
3. Go to **FluentCart → Settings**
4. Configure payment methods
5. Set currency
6. Go to **WP Sell Services → Settings → General**
7. Select **E-commerce Platform:** FluentCart
8. Save changes

**Configuration:**

**Payment Methods:**
- Stripe (built-in)
- PayPal (built-in)
- Offline payments
- Custom gateways via hooks

**Checkout Settings:**
- One-page checkout
- Guest checkout option
- Required fields
- Optional fields

**Email Notifications:**
- Order confirmation
- Payment receipts
- Custom email templates

### FluentCart Integration

**Service Products:**
- Lightweight product structure
- Service packages as variations
- Minimal database overhead
- Fast queries

**Checkout Experience:**
- Single-page checkout
- Inline validation
- Real-time price updates
- Mobile-optimized

**Order Management:**
- Simple order list
- Order status updates
- Refund processing
- Customer communication

---

## SureCart

Modern, conversion-optimized checkout platform.

### Why Choose SureCart?

**Advantages:**
- Beautiful, modern UI
- Optimized for conversions
- One-click upsells
- Subscription support
- Tax automation (TaxJar integration)

**Best For:**
- Conversion-focused marketplaces
- Modern design preference
- Subscription services
- International sales (tax complexity)

### SureCart Setup

**Requirements:**
- SureCart plugin
- WP Sell Services Pro
- PHP 8.0+

**Installation:**

1. Install SureCart plugin
2. Create SureCart account (connects via API)
3. Activate SureCart
4. Complete SureCart onboarding
5. Go to **WP Sell Services → Settings → General**
6. Select **E-commerce Platform:** SureCart
7. Authenticate connection
8. Save changes

**Configuration:**

**Payment Processors:**
- Stripe (recommended)
- PayPal
- Multiple currencies
- Automatic tax calculation

**Checkout Builder:**
- Drag-and-drop checkout design
- Custom checkout fields
- Upsells and bumps
- Checkout templates

**Subscription Billing:**
- Recurring services
- Trial periods
- Metered billing
- Dunning management

### SureCart Integration

**Service Checkout:**
- Beautiful checkout forms
- Real-time validation
- Address autocomplete
- Payment request buttons (Apple Pay, Google Pay)

**Order Bumps:**
Add upsells at checkout:
- "Add express delivery for +$20"
- "Include source files for +$50"
- One-click add to order

**Tax Automation:**
Automatic tax calculation:
- US sales tax
- EU VAT
- Canada GST/HST
- Australia GST

**Subscription Services:**
- Recurring service packages
- Automatic billing
- Subscription management
- Proration support

---

## Standalone Mode

**[PRO]** No external e-commerce plugin required.

### Why Choose Standalone Mode?

**Advantages:**
- No dependencies (besides WP Sell Services)
- Absolute maximum performance
- Full control over payment flow
- Minimal conflicts
- Reduced complexity

**Best For:**
- Service-only marketplaces
- Performance-critical sites
- Custom payment flows
- Developers wanting full control

### Standalone Setup

**Requirements:**
- WP Sell Services Pro
- Payment gateway configured
- PHP 8.0+

**Activation:**

1. Go to **WP Sell Services → Settings → General**
2. Select **E-commerce Platform:** Standalone Mode
3. Save changes
4. Go to **Settings → Payments**
5. Configure payment gateway (Stripe, PayPal, or Razorpay)
6. Test checkout flow

### Standalone Features

**Built-In Cart:**
- Lightweight cart system
- Session-based storage
- AJAX cart updates
- Cart persistence

**Checkout:**
- Custom checkout page
- Service order checkout
- Requirements collection
- Payment processing

**Payment Gateways:**
Direct integration without WooCommerce/EDD:
- **Stripe Checkout** - Hosted checkout page
- **PayPal Standard** - PayPal-hosted payment
- **Razorpay** - Indian payment gateway

**Order Management:**
- Native order system
- No WooCommerce/EDD overhead
- Service-optimized order structure
- Fast queries

### Standalone Payment Flow

**Checkout Process:**

1. Buyer adds service to cart
2. Clicks "Checkout"
3. Fills requirements (if applicable)
4. Enters billing information
5. Selects payment method
6. Redirected to payment gateway
7. Payment completed
8. Redirected back to site
9. Order created
10. Vendor notified

**Direct Payment Integration:**
```php
// Example: Stripe Checkout Session
$session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'line_items' => [[
        'price_data' => [
            'currency' => 'usd',
            'product_data' => [
                'name' => 'Logo Design - Premium Package',
            ],
            'unit_amount' => 20000, // $200.00
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => site_url('/order-success/?order_id={ORDER_ID}'),
    'cancel_url' => site_url('/checkout/'),
]);
```

---

## Switching Platforms

Change e-commerce platforms with migration support.

### Migration Process

**Before Switching:**

1. **Backup database** - Critical!
2. **Export orders** - Save existing order data
3. **Notify vendors** - Warn of potential downtime
4. **Plan migration** - Off-peak hours recommended

**Switching Steps:**

1. Go to **WP Sell Services → Settings → General**
2. Select new **E-commerce Platform**
3. Click **Save Changes**
4. Plugin detects platform change
5. Migration wizard appears
6. Follow migration steps
7. Test thoroughly before going live

**Migration Options:**

**Full Migration:**
- Migrate all existing orders
- Convert products to new platform
- Preserve order history
- Update customer records

**Fresh Start:**
- Keep old orders in previous system
- New orders use new platform
- No data migration
- Clean slate approach

### Platform Comparison

**Feature Comparison:**

| Feature | WooCommerce | EDD | FluentCart | SureCart | Standalone |
|---------|-------------|-----|------------|----------|------------|
| Service Products | ✓ | ✓ | ✓ | ✓ | ✓ |
| Package Variations | ✓ | ✓ | ✓ | ✓ | ✓ |
| Multiple Gateways | ✓✓✓ | ✓✓ | ✓ | ✓✓ | ✓ |
| Subscriptions | ✓ (plugin) | ✓ (plugin) | ✗ | ✓ | ✗ |
| Tax Automation | ✓ (plugin) | ✓ | ✗ | ✓ | ✗ |
| Performance | Good | Excellent | Excellent | Excellent | Best |
| Complexity | High | Medium | Low | Low | Minimal |
| Extensions | 1000+ | 100+ | Few | Growing | N/A |

**Performance Benchmarks:**

| Platform | Page Load | Cart Update | Checkout |
|----------|-----------|-------------|----------|
| WooCommerce | 1.2s | 0.8s | 2.1s |
| EDD | 0.8s | 0.4s | 1.3s |
| FluentCart | 0.6s | 0.3s | 1.0s |
| SureCart | 0.7s | 0.3s | 1.1s |
| Standalone | 0.5s | 0.2s | 0.8s |

*Average times on standard hosting. Your results may vary.*

---

## Platform-Specific Configuration

### Payment Gateway Compatibility

**WooCommerce:**
- All WooCommerce payment gateways
- 100+ gateway extensions available

**Easy Digital Downloads:**
- EDD payment gateway extensions
- Stripe, PayPal, Authorize.Net
- Regional gateways via extensions

**FluentCart:**
- Stripe (built-in)
- PayPal (built-in)
- Custom gateways via API

**SureCart:**
- Stripe (primary)
- PayPal
- Built-in tax support

**Standalone:**
- Stripe Direct **[PRO]**
- PayPal Direct **[PRO]**
- Razorpay **[PRO]**

See [Payment Gateways](payment-gateways.md) for detailed setup.

---

## Troubleshooting

### Products Not Creating in New Platform

**Check:**
1. Platform plugin is active
2. Platform is correctly selected in settings
3. Service has valid pricing
4. No errors in debug log
5. Platform API connection working (for SureCart)

### Orders Not Syncing

**Verify:**
1. Webhooks are configured (Stripe, PayPal)
2. Order status is correct
3. Service order creation enabled
4. No conflicts with other plugins
5. Check error logs

### Migration Failed

**Troubleshoot:**
1. Restore database backup
2. Switch back to original platform
3. Identify error in migration log
4. Fix issue
5. Retry migration

---

## Related Documentation

- [WooCommerce Setup](woocommerce-setup.md) - Default platform
- [Payment Gateways](payment-gateways.md) - Gateway configuration
- [Payment Settings](../admin-settings/payment-settings.md) - Commission setup
- [Wallet Systems](wallet-systems.md) - Vendor payouts

---

## Next Steps

1. Evaluate which platform fits your needs
2. Test platform before going live
3. Configure payment gateways
4. Test complete order flow
5. Monitor performance after switch
