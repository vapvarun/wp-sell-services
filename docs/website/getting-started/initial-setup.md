# Initial Setup Guide

After installing WP Sell Services, follow this step-by-step guide to configure your marketplace and prepare it for vendors and buyers.

## Setup Checklist

Track your progress through these essential configuration steps:

- [ ] Create and assign required pages
- [ ] Configure commission and payout settings
- [ ] Set vendor registration preferences
- [ ] Enable email notifications
- [ ] Configure WooCommerce for services
- [ ] Test the complete marketplace workflow

## Step 1: Create Required Pages

WP Sell Services uses 3 dedicated pages to power the marketplace frontend. Each page uses a shortcode to render its content.

### Pages Overview

Navigate to **WP Sell Services → Settings → Pages** to manage these pages:

| Page | Shortcode | Purpose |
|------|-----------|---------|
| **Services** | `[wpss_services]` | Public service browsing and search |
| **Dashboard** | `[wpss_dashboard]` | Unified dashboard for both buyers and vendors |
| **Become a Vendor** | `[wpss_vendor_registration]` | Vendor registration form |

![Pages settings screen](../images/settings-pages.png)

### Creating Pages

You have two options for each page:

**Option A: Auto-Create (Recommended)**

1. Go to **WP Sell Services → Settings → Pages**
2. Click the **Create Page** button next to any unassigned page
3. The plugin creates the page with the correct shortcode and title
4. If a page with the matching shortcode already exists, it links to that page instead of creating a duplicate

**Option B: Manual Assignment**

1. Create a new WordPress page with the appropriate shortcode in its content
2. Return to **Settings → Pages** and select the page from the dropdown
3. Click **Save Changes**

### Adding Pages to Navigation

After creating pages, add them to your site menu:

1. Go to **Appearance → Menus**
2. Add the **Services** and **Become a Vendor** pages to your primary menu
3. Add the **Dashboard** page to a secondary or user-account menu
4. Save the menu

## Step 2: Configure Commission Settings

The commission system determines how revenue is split between your platform and vendors on every completed order.

Navigate to **WP Sell Services → Settings → Payments → Commission Settings**.

### Global Commission Rate

| Setting | Range | Default | Description |
|---------|-------|---------|-------------|
| **Commission Rate (%)** | 0 - 50 | 10% | Percentage deducted from vendor earnings on all orders |
| **Per-Vendor Rates** | On/Off | On | Allow custom commission rates per vendor |

![Commission settings](../images/settings-commission.png)

**Example Calculation (10% commission):**

```
Service Package: $100.00
Add-ons: $20.00
Order Total: $120.00
Platform Commission (10%): $12.00
Vendor Earnings: $108.00
```

Commission is calculated on the full order total, including add-ons. Tips are excluded from commission.

### Per-Vendor Rates

When **Per-Vendor Rates** is enabled, you can override the global rate for individual vendors through their vendor profile. This is useful for rewarding top performers or negotiating custom rates.

For detailed commission documentation, see [Commission System](../earnings-wallet/commission-system.md).

## Step 3: Configure Payout Settings

Still within the **Payments** tab, expand the **Payout Settings** section to configure vendor withdrawals.

### Withdrawal Settings

| Setting | Range | Default | Description |
|---------|-------|---------|-------------|
| **Minimum Withdrawal** | 0 - 1,000 | $50 | Minimum balance required to request a withdrawal |
| **Clearance Period (Days)** | 0 - 90 | 14 | Days after order completion before earnings become available |

The clearance period protects the platform against disputes and refunds by holding earnings for a set number of days after an order is completed.

### Automatic Withdrawals

| Setting | Default | Description |
|---------|---------|-------------|
| **Enable Auto-Withdrawal** | Off | Automatically process withdrawals for qualifying vendors |
| **Auto-Withdrawal Threshold** | $500 | Minimum available balance to trigger auto-withdrawal |
| **Auto-Withdrawal Schedule** | Monthly | Options: Weekly (every Monday), Bi-weekly (1st and 15th), Monthly (1st of month) |

Auto-withdrawal is optional and disabled by default. When enabled, the system creates and processes withdrawal requests automatically for vendors whose available balance meets the threshold.

## Step 4: Configure Tax Settings (Optional)

Expand the **Tax Settings** section within the Payments tab if you need to apply tax to service orders.

| Setting | Default | Description |
|---------|---------|-------------|
| **Enable Tax** | Off | Enable tax calculation on orders |
| **Tax Label** | "Tax" | Display label (e.g., VAT, GST, Sales Tax) |
| **Tax Rate (%)** | 0% | Default tax rate (0 - 50%, step 0.01) |
| **Prices Include Tax** | Off | Whether service prices already include tax |
| **Tax on Commission** | None | Options: No tax on commission, Platform collects tax on full amount, Vendor handles own tax |

**Note**: If you use WooCommerce for checkout, WooCommerce may handle its own tax calculations. These settings apply when using non-WooCommerce checkout or when WooCommerce tax is not configured.

## Step 5: Configure Vendor Settings

Navigate to **WP Sell Services → Settings → Vendor** to control how vendors join and operate on your marketplace.

### Registration Settings

| Setting | Options | Default | Description |
|---------|---------|---------|-------------|
| **Vendor Registration** | Open / Requires Approval / Closed | Open | Controls who can become a vendor |
| **Max Services per Vendor** | 1 - 100 | 20 | Maximum active services a vendor can create |
| **Require Verification** | On/Off | Off | Require vendors to verify identity before selling |
| **Service Moderation** | On/Off | Off | Require admin approval before services are published |

### Registration Modes Explained

**Open** -- Anyone can register as a vendor and begin selling immediately. Administrators are automatically registered as vendors on activation.

**Requires Approval** -- Users submit a vendor application, but an admin must approve them before they can create services.

**Closed** -- Only administrators can create vendor accounts. The vendor registration page is disabled.

### Service Moderation

When enabled, newly created or edited services are held in a "pending review" status until an admin approves them. This gives you control over marketplace quality but adds administrative overhead.

## Step 6: Configure Email Notifications

Navigate to **WP Sell Services → Settings → Emails** to manage which email notifications are sent.

### Available Notification Types

All 8 notification types are enabled by default. Each toggle is a master switch -- when disabled, that email is never sent regardless of other settings.

| Notification | Default | Sent To |
|-------------|---------|---------|
| **New Order** | On | Vendor |
| **Order Completed** | On | Both |
| **Order Cancelled** | On | Both |
| **Delivery Submitted** | On | Buyer |
| **Revision Requested** | On | Vendor |
| **New Message** | On | Recipient |
| **New Review** | On | Vendor |
| **Dispute Opened** | On | Both + Admin |

**WooCommerce Email Integration**: If WooCommerce is active, you can customize email content, subjects, and templates through **WooCommerce → Settings → Emails**.

### SMTP Configuration (Recommended)

For reliable email delivery in production, install an SMTP plugin such as WP Mail SMTP or FluentSMTP. WordPress's default `wp_mail()` function may not deliver reliably on all hosting environments.

## Step 7: Configure WooCommerce for Services

The free version uses WooCommerce for checkout and payment processing. WP Sell Services creates a virtual carrier product automatically during activation -- vendors do not manually create WooCommerce products.

### Recommended WooCommerce Settings

**Products** (WooCommerce → Settings → Products):
- Uncheck **Enable product reviews** -- WP Sell Services has its own review system

**Shipping** (WooCommerce → Settings → Shipping):
- Remove or disable all shipping zones and methods -- services are digital and do not require shipping

**Payments** (WooCommerce → Settings → Payments):
- Enable at least one payment gateway (PayPal, Stripe, bank transfer, etc.)
- Test a payment with a small amount before launching

**[PRO]** The Pro version supports additional e-commerce platforms (EDD, FluentCart, SureCart) and a standalone mode that does not require WooCommerce at all. See [General Settings](../platform-settings/general-settings.md) for platform selection details.

## Step 8: General Platform Settings

Navigate to **WP Sell Services → Settings → General** for basic platform configuration.

### Platform Identity

| Setting | Default | Description |
|---------|---------|-------------|
| **Platform Name** | Your WordPress site name | Name displayed in emails, notifications, and frontend |
| **Currency** | USD | Marketplace currency (10 supported currencies) |
| **E-Commerce Platform** | Auto-detect | Which e-commerce system handles checkout |

The **Auto-detect** setting is recommended for e-commerce platform selection. It automatically detects WooCommerce (or other platforms with **[PRO]**) and uses the first available active platform.

### Supported Currencies

USD, EUR, GBP, CAD, AUD, INR, JPY, CNY, BRL, MXN.

All services, orders, and earnings use the selected currency. Changing currency after vendors have listed services may cause pricing confusion, so it is best to set this during initial setup.

## Step 9: Configure Order Settings

Navigate to **WP Sell Services → Settings → Orders** to define order workflow behavior.

| Setting | Default | Description |
|---------|---------|-------------|
| **Auto-Complete Days** | 3 | Days after delivery before auto-completing if buyer does not respond. 0 to disable. |
| **Default Revision Limit** | 2 | Default revisions per order. Can be overridden per service. |
| **Allow Disputes** | On | Allow buyers to open disputes on orders |
| **Dispute Window (Days)** | 14 | Days after completion within which disputes can be opened |
| **Late Requirements Submission** | Off | Allow buyers to submit requirements after work has started |
| **Requirements Timeout (Days)** | 0 | Days to wait for requirements before taking action. 0 to disable. |
| **Auto-Start on Timeout** | On | If requirements timeout, auto-start the order (instead of cancelling) |

The **Auto-Complete** feature prevents orders from sitting indefinitely in "delivered" status. After the vendor delivers work and the buyer does not respond within the configured number of days, the order is automatically marked as completed.

## Step 10: Test Your Marketplace

Before inviting real vendors and buyers, test the complete workflow.

### Create a Test Vendor

1. Open your site in an incognito/private browser window
2. Visit the **Become a Vendor** page
3. Complete the registration form
4. If registration mode is "Requires Approval," log in as admin and approve the application
5. Administrators are automatically registered as vendors, so you can also test from the admin account

### Create a Test Service

1. Log in as a vendor (or as admin, who is auto-registered as a vendor)
2. Go to the **Dashboard** page
3. Create a new service through the service wizard with:
   - A title and description
   - At least one package with pricing
   - A main image
4. Publish the service
5. Verify it appears on the **Services** page

### Place a Test Order

1. Log in as a different user (buyer)
2. Browse to the test service
3. Select a package and proceed to checkout
4. Complete payment through WooCommerce
5. Switch to the vendor account and check the order in the dashboard
6. Submit a delivery
7. Switch back to the buyer account and accept the delivery
8. Leave a review

This tests the complete order lifecycle from purchase through completion and review.

## Advanced Settings

Navigate to **WP Sell Services → Settings → Advanced** for system-level options.

| Setting | Default | Description |
|---------|---------|-------------|
| **Delete Data on Uninstall** | Off | Remove all plugin data when uninstalling. Leave off to preserve data during troubleshooting. |
| **Debug Mode** | Off | Enable debug logging for troubleshooting. Disable in production. |

## Recommended Post-Setup Steps

After completing initial configuration:

1. **Add service categories** -- Create categories for your marketplace niche (e.g., Design, Writing, Development)
2. **Customize your homepage** -- Use Gutenberg blocks (Service Grid, Featured Services, Categories) to showcase services
3. **Flush permalinks** -- Go to **Settings → Permalinks** and click **Save Changes** to ensure service URLs work correctly
4. **Test REST API** -- Verify the API is accessible at `https://yoursite.com/wp-json/wpss/v1/settings`

## Next Steps

Your marketplace is now configured. Continue with:

1. **[Creating Services](../service-creation/service-wizard.md)** -- Learn how vendors create service listings
2. **[Order Management](../order-management/order-lifecycle.md)** -- Understand the full order lifecycle
3. **[Commission System](../earnings-wallet/commission-system.md)** -- Deep dive into how earnings work
4. **[Free vs Pro Comparison](free-vs-pro.md)** -- Understand what the Pro version adds
