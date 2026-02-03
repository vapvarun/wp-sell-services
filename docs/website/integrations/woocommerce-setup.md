# WooCommerce Setup

WP Sell Services integrates seamlessly with WooCommerce for product management, checkout, and order processing.

## WooCommerce Overview

WooCommerce serves as the e-commerce engine for the free version of WP Sell Services, handling payments, cart, and checkout.

![WooCommerce Setup](../images/settings-woocommerce-setup.png)

## Requirements

### Minimum Versions

- **WordPress:** 6.0 or higher
- **WooCommerce:** 6.0 or higher (8.0+ recommended)
- **PHP:** 8.0 or higher
- **MySQL:** 5.7 or higher

### Installation

**Install WooCommerce:**

1. Go to **Plugins → Add New**
2. Search for "WooCommerce"
3. Click **Install Now** on WooCommerce by Automattic
4. Click **Activate**
5. Complete WooCommerce setup wizard
6. Install WP Sell Services plugin
7. Activate WP Sell Services

**WooCommerce Setup Wizard:**
- Store location and currency
- Payment methods
- Shipping (not needed for services - disable)
- Tax settings (configure if required)
- Sample products (skip)

## How Services Integrate with WooCommerce

### Service-to-Product Mapping

Each service creates a corresponding WooCommerce product.

**Service Creation Process:**

1. Vendor creates service in WP Sell Services
2. Plugin automatically creates WooCommerce product
3. Product type: Virtual (no shipping)
4. Product visibility: Controlled by service status
5. Product data syncs with service data

**What Syncs:**
- ✓ Service title → Product name
- ✓ Service description → Product description
- ✓ Service pricing → Product prices
- ✓ Service images → Product gallery
- ✓ Service categories → Product categories
- ✓ Service status → Product status

**What Doesn't Sync:**
- ✗ WooCommerce inventory (always in stock)
- ✗ Shipping settings (virtual products)
- ✗ Product reviews (uses service reviews instead)

### Product Variations for Packages

Service packages (Basic, Standard, Premium) become product variations.

**Example Service:**
- **Basic Package:** $50 - 7 days - 1 revision
- **Standard Package:** $100 - 5 days - 3 revisions
- **Premium Package:** $200 - 3 days - unlimited revisions

**WooCommerce Product:**
- Variable Product
- 3 variations (Basic, Standard, Premium)
- Each variation has its own price and attributes
- Buyer selects package variation at checkout

### Add-ons as Product Add-ons

Service add-ons (Extra Fast Delivery, Source Files, etc.) integrate with WooCommerce.

**Compatible Plugins:**
- **WooCommerce Product Add-Ons** (official)
- **YITH WooCommerce Product Add-Ons**
- Built-in WP Sell Services add-on system

**Add-on Display:**
1. Buyer views service
2. Selects package
3. Sees available add-ons
4. Checks desired add-ons
5. Price updates dynamically
6. Adds to cart with add-ons

## Cart and Checkout Flow

### Adding Service to Cart

**Buyer Flow:**

1. Browse services
2. Click service to view details
3. Select package (Basic/Standard/Premium)
4. Choose add-ons (optional)
5. Click "Add to Cart"
6. Redirected to cart or continue shopping
7. Proceed to checkout

**Cart Display:**
- Service title
- Selected package name
- Package details (delivery time, revisions)
- Selected add-ons
- Total price
- Vendor name

### Service Order Requirements

After adding to cart, buyers may need to provide requirements.

**Requirement Collection Timing:**

**Option 1: Before Checkout** (Recommended)
1. Add to cart
2. Redirect to requirements page
3. Fill out service requirements
4. Proceed to checkout
5. Complete payment

**Option 2: After Purchase**
1. Add to cart and checkout
2. Complete payment
3. Redirected to requirements page
4. Fill out requirements
5. Vendor receives order with requirements

Configure in **WP Sell Services → Settings → Orders → Requirements**.

### Checkout Process

**Standard WooCommerce Checkout:**

1. Billing information
2. Payment method selection
3. Order review
4. Terms and conditions
5. Place order button

**Service-Specific Checkout Fields:**
- Special instructions (optional)
- Project deadline preference
- Delivery file format preference

**Add Custom Fields:**
```php
// Add to functions.php
add_filter( 'wpss_checkout_fields', function( $fields ) {
    $fields['service_notes'] = array(
        'type'        => 'textarea',
        'label'       => 'Project Details',
        'placeholder' => 'Describe your project...',
        'required'    => false,
    );
    return $fields;
} );
```

## Order Management

### WooCommerce Order → Service Order Mapping

When WooCommerce order is placed:

1. WooCommerce creates order (e.g., Order #12345)
2. WP Sell Services creates service order (linked to WooCommerce order)
3. Service order contains service-specific data:
   - Requirements
   - Delivery files
   - Revisions
   - Messages
   - Timeline
4. WooCommerce order tracks payment and basic info

**Admin Order View:**
- WooCommerce order shows payment status
- Service order shows project status
- Both are linked and cross-referenced

**Vendor Order View:**
- Sees service order details
- Delivery timeline
- Requirements
- Upload delivery
- Does NOT see payment details (privacy)

**Buyer Order View:**
- Sees WooCommerce order (payment receipt)
- Sees service order (project details)
- Download deliverables
- Request revisions

### Order Statuses

**WooCommerce Statuses:**
- Pending Payment
- Processing
- On Hold
- Completed
- Cancelled
- Refunded
- Failed

**Service Order Statuses:**
- Requirements Pending
- In Progress
- Delivered
- Revision Requested
- Completed
- Cancelled
- Disputed

**Status Sync:**
- WooCommerce "Completed" ≠ Service "Completed"
- WooCommerce completes when payment received
- Service completes when buyer accepts delivery
- Refunds sync between both systems

## My Account Integration

WP Sell Services integrates with WooCommerce My Account area by adding custom tabs that provide quick access to marketplace features.

### WooCommerce My Account Tabs

WP Sell Services automatically adds up to 5 custom tabs to the WooCommerce My Account page.

**Default WooCommerce Tabs:**
- Dashboard
- Orders
- Downloads
- Addresses
- Account Details

**Added by WP Sell Services:**
- **Vendor Dashboard** (vendors only)
- **My Services** (vendors only)
- **Service Orders**
- **Disputes**
- **Notifications**

### Custom Tab Details

#### Vendor Dashboard Tab

**Who Sees It:** Vendors only

Displays a quick overview of vendor statistics and performance:
- Total services published
- Active orders count
- Pending orders requiring attention
- Total earnings this month
- Average rating
- Quick links to create new service or view sales

**Benefits:**
- One-click access to vendor metrics
- No need to leave WooCommerce interface
- Integrated with familiar account area

#### My Services Tab

**Who Sees It:** Vendors only

Manage all your services from the WooCommerce account:
- View all published services
- Edit service details
- Pause or unpause services
- View service statistics (views, orders, revenue)
- Create new service button

**Actions Available:**
- **Edit**: Modify service details, pricing, packages
- **View**: See service page as buyers see it
- **Pause**: Temporarily disable service
- **Stats**: View detailed service performance

#### Service Orders Tab

**Who Sees It:** All users (buyers and vendors)

**For Buyers:**
- View active service orders you've purchased
- Check order status and delivery timeline
- Download delivered files
- Request revisions
- Message vendors
- Leave reviews after completion

**For Vendors:**
- View incoming service orders
- Check requirements submitted by buyers
- Upload deliveries
- Track revision requests
- Message buyers
- View order deadlines

**Order Information Shown:**
- Order number and date
- Service name and package
- Buyer/Vendor name
- Order status (In Progress, Delivered, etc.)
- Delivery deadline
- Quick action buttons

#### Disputes Tab

**Who Sees It:** All users

View and manage any disputes related to service orders:
- Active disputes requiring your response
- Resolved dispute history
- Dispute status (Open, Under Review, Resolved)
- Upload evidence or additional information
- View admin responses and resolution

**Dispute Actions:**
- View dispute details
- Add comments or evidence
- Upload supporting files
- Accept resolution
- Close dispute

#### Notifications Tab

**Who Sees It:** All users

Central notification center for all marketplace activity:
- New order notifications
- Delivery updates
- Message notifications
- Review notifications
- System announcements
- Dispute updates

**Notification Types:**
- **Order Notifications**: New orders, status changes
- **Delivery Notifications**: Files uploaded, revisions requested
- **Message Notifications**: New messages from buyers/vendors
- **System Notifications**: Important platform updates

**Mark as Read:**
- Click notification to mark as read
- Mark all as read button
- Delete old notifications
- Filter by type or date

### How Tabs Appear

**For Buyers:**
WooCommerce My Account shows:
1. Default WooCommerce tabs
2. Service Orders (if they've ordered services)
3. Notifications
4. Disputes (if any exist)

**For Vendors:**
WooCommerce My Account shows:
1. Default WooCommerce tabs
2. Vendor Dashboard
3. My Services
4. Service Orders (for vendor's incoming orders)
5. Notifications
6. Disputes (if any exist)

**For Dual Role Users (Both Buyer and Vendor):**
All tabs appear, with clear separation between:
- Orders you've placed (as buyer)
- Orders you've received (as vendor)

### Accessing the My Account Page

**Navigation:**

1. Go to **My Account** menu (usually in site header)
2. Or visit `/my-account/` directly
3. Login if not already authenticated
4. Click any custom tab to access marketplace features

**URL Structure:**
- `/my-account/vendor-dashboard/`
- `/my-account/my-services/`
- `/my-account/service-orders/`
- `/my-account/disputes/`
- `/my-account/notifications/`

### Unified or Separate Dashboards

You can use WooCommerce My Account, a standalone WP Sell Services dashboard, or both.

**Option 1: Use WooCommerce My Account**
- All functionality in WooCommerce My Account tabs
- Familiar WooCommerce interface
- Good for WooCommerce-heavy sites
- Less confusing for users familiar with WooCommerce

**Option 2: Use WP Sell Services Dashboard**
- Standalone dashboard page using `[wpss_dashboard]` shortcode
- Custom marketplace interface
- Better for service marketplace focus
- Full-featured dashboard experience

**Option 3: Both (Recommended)**
- WooCommerce My Account for quick access
- Standalone dashboard for full features
- Link between both dashboards
- Users can choose preferred interface

Configure in **WP Sell Services → Settings → Pages → Dashboard**.

### Customizing Tab Order

**[PRO]** Reorder tabs or hide specific tabs.

1. Go to **WP Sell Services → Settings → WooCommerce**
2. Navigate to **My Account Tabs**
3. Drag to reorder tabs
4. Toggle visibility for each tab
5. Save changes

**Example Custom Order:**
1. Dashboard
2. Vendor Dashboard
3. Service Orders
4. My Services
5. Orders (WooCommerce)
6. Notifications
7. Disputes

### Tab Permissions

Each tab checks user permissions automatically:

| Tab | Required Permission |
|-----|---------------------|
| Vendor Dashboard | User must be approved vendor |
| My Services | User must be approved vendor |
| Service Orders | Any logged-in user with orders |
| Disputes | User must have active disputes |
| Notifications | Any logged-in user |

Users without required permissions won't see those tabs.

## Payment Processing

### WooCommerce Payment Gateways

WP Sell Services works with ALL WooCommerce payment gateways.

**Popular Gateways:**
- **Stripe** (via WooCommerce Stripe plugin)
- **PayPal** (included with WooCommerce)
- **Square**
- **Authorize.Net**
- **Amazon Pay**
- **Klarna**
- **Afterpay**

**Setup:**
1. Install WooCommerce payment gateway
2. Configure gateway in **WooCommerce → Settings → Payments**
3. Enable gateway
4. Test transaction
5. Gateway works automatically for service orders

### Commission Handling

**Payment Flow with Commission:**

1. Buyer pays full amount ($100)
2. WooCommerce processes payment
3. Platform receives $100
4. WP Sell Services calculates commission (e.g., 15% = $15)
5. Vendor's earnings = $85
6. Vendor sees $85 in earnings dashboard
7. Vendor withdraws $85 (platform keeps $15 commission)

**Commission Deduction:**
- Automatic on order completion
- Transparent to vendor (shown in order details)
- Not handled by WooCommerce (handled by WP Sell Services)

See [Payment Settings](../admin-settings/payment-settings.md) for commission configuration.

### Vendor Payouts

Vendors don't receive payment directly from WooCommerce.

**Payout Options:**

**Manual Payouts:**
1. Vendor requests withdrawal
2. Admin approves
3. Admin sends payment via PayPal/bank transfer
4. Admin marks as paid in system

**Automated Payouts (Pro):**
- **[PRO]** Stripe Connect - Automatic splits
- **[PRO]** PayPal Payouts API
- **[PRO]** Wallet system (vendors withdraw to wallet)

See [Wallet Systems](wallet-systems.md) for automated payout options.

## Email Integration

WooCommerce emails complement WP Sell Services emails.

### Email Types

**WooCommerce Emails:**
- Order received (buyer)
- Payment receipt (buyer)
- Order status changes
- Refund notifications

**WP Sell Services Emails:**
- Delivery submitted (buyer)
- Requirements needed (buyer)
- New service order (vendor)
- Revision requested (vendor)
- Order completed (both)

**Avoid Duplicate Emails:**
- Disable redundant WooCommerce emails
- Customize which system sends what
- Clear communication in each email

**Configuration:**
Go to **WooCommerce → Settings → Emails** to customize WooCommerce emails.

## HPOS Compatibility

High-Performance Order Storage (HPOS) is fully supported.

### What is HPOS?

WooCommerce's new order storage system (replacing posts table).

**Benefits:**
- Faster order queries
- Better performance with many orders
- Reduced database load
- Improved scalability

**WP Sell Services Support:**
- Full HPOS compatibility
- Works with both HPOS and legacy storage
- Automatic detection and adaptation

### Enabling HPOS

1. Go to **WooCommerce → Settings → Advanced → Features**
2. Enable **High-Performance Order Storage**
3. Save changes
4. WP Sell Services automatically adapts

**No Configuration Required** - Plugin detects HPOS and adjusts queries accordingly.

## WooCommerce Subscriptions Support

**[PRO]** Recurring service subscriptions using WooCommerce Subscriptions.

### Subscription Services

Create recurring services:
- Monthly website maintenance
- Weekly content creation
- Annual logo refresh
- Quarterly marketing reports

**Setup:**
1. Install WooCommerce Subscriptions plugin
2. Create service with subscription pricing
3. Set billing interval (weekly, monthly, yearly)
4. Vendor delivers on schedule
5. Automatic recurring payments

**Example Subscription:**
- Service: Monthly Blog Posts
- Price: $200/month
- Delivery: 4 articles per month
- Billing: Every 30 days

## Troubleshooting

### Service Not Creating WooCommerce Product

**Check:**
1. WooCommerce is active and updated
2. Service has at least one package with price
3. Service status is "Published"
4. No WooCommerce product creation errors in debug log
5. Vendor has permission to create products

**Manual Fix:**
1. Edit service
2. Click "Sync with WooCommerce"
3. Product is created/updated

### Cart Shows Wrong Price

**Verify:**
1. Package pricing is correct in service
2. Add-on pricing is correct
3. No WooCommerce dynamic pricing plugins interfering
4. Cache cleared
5. Test in different browser

### Order Not Appearing in Dashboard

**Troubleshoot:**
1. WooCommerce order was actually completed (not pending)
2. Order contains service product (not regular product)
3. Service order was created (check database or debug log)
4. User is logged in with correct account
5. Clear cache

### Payment Gateway Not Working

**Verify:**
1. Gateway is enabled in WooCommerce
2. Gateway is properly configured (API keys, etc.)
3. Test mode is enabled (if testing)
4. No SSL errors (most gateways require HTTPS)
5. Check WooCommerce → Status → Logs for gateway errors

### Commission Not Calculating

**Check:**
1. Commission rate is set in WP Sell Services settings
2. Order status is "Completed"
3. Order is a service order (not regular product)
4. No errors in commission calculation log
5. Commission appears in vendor earnings dashboard

## Performance Optimization

### Large Numbers of Services

If you have hundreds or thousands of services:

**Optimizations:**
1. Enable object caching (Redis, Memcached)
2. Use WooCommerce HPOS
3. Optimize database tables regularly
4. Implement pagination on all listings
5. Use lazy loading for images

### Database Queries

Reduce database load:

**Settings:**
1. Enable WP Sell Services query caching **[PRO]**
2. Use persistent object cache
3. Limit related services shown
4. Disable unused WooCommerce features (shipping, coupons if not needed)

## Related Documentation

- [Alternative E-commerce Platforms](alternative-ecommerce.md) - EDD, FluentCart, SureCart **[PRO]**
- [Payment Gateways](payment-gateways.md) - Direct payment options **[PRO]**
- [Payment Settings](../admin-settings/payment-settings.md) - Commission configuration
- [Wallet Systems](wallet-systems.md) - Vendor payout options **[PRO]**

## Next Steps

1. Complete WooCommerce setup wizard
2. Configure preferred payment gateways
3. Set up commission rates in WP Sell Services
4. Test complete order flow (purchase to delivery)
5. Configure WooCommerce emails
6. Set up automated backups
