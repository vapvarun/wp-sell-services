# Initial Setup Guide

After activating WP Sell Services, a quick setup gets your marketplace ready for vendors and buyers. Most of this takes under 10 minutes.

## Setup Wizard

When you first activate the plugin, the Setup Wizard walks you through the essentials:

### Platform Name
This is the name of your marketplace -- it appears in emails, notifications, and on the frontend. It defaults to your WordPress site name, but you can change it to something like "DesignHub" or "FreelanceMarket."

### Currency
Choose the currency for your entire marketplace. All service prices, earnings, and payouts use this currency. Options include USD, EUR, GBP, CAD, AUD, INR, JPY, CNY, BRL, and MXN.

**Tip:** Set your currency before vendors start listing services. Changing it later can cause pricing confusion.

### Commission Rate
This is the percentage you earn on every completed order. The default is 10%, meaning if a vendor sells a $100 service, you keep $10 and the vendor receives $90. You can set this anywhere from 0% to 50%.

### Vendor Registration Mode
Choose how vendors join your marketplace:

| Mode | What Happens |
|------|-------------|
| **Open** | Anyone can sign up as a vendor and start selling immediately |
| **Requires Approval** | People apply to become vendors, and you approve or decline each application |
| **Closed** | Only you (the admin) can create vendor accounts |

## Essential Pages

Your marketplace needs 3 pages to function. The plugin can create them for you automatically:

1. Go to **WP Sell Services > Settings > Pages**
2. Click the **Create Page** button next to each page

| Page | What It Does |
|------|-------------|
| **Services** | The public browsing page where buyers discover and search for services |
| **Dashboard** | Where both vendors and buyers manage orders, messages, earnings, and services |
| **Become a Vendor** | The registration page for new vendors |

After creating these pages, add the **Services** and **Become a Vendor** pages to your site's main menu at **Appearance > Menus**.

## Quick Configuration Checklist

Here is everything you should review before inviting your first vendors:

### Commission and Payouts
Go to **WP Sell Services > Settings > Payments**:

- **Commission rate** -- Your platform's cut on each sale (default: 10%)
- **Per-vendor rates** -- Turn on if you want to set different rates for specific vendors
- **Minimum withdrawal** -- The smallest amount a vendor can cash out (default: $50)
- **Clearance period** -- How many days earnings are held after order completion before they become available (default: 14 days)

### Vendor Settings
Go to **WP Sell Services > Settings > Vendor**:

- **Max services per vendor** -- How many active services each vendor can have (default: 20)
- **Require verification** -- Whether vendors must verify their identity before selling
- **Service moderation** -- Whether you want to review and approve every new service before it goes live

### Order Settings
Go to **WP Sell Services > Settings > Orders**:

- **Auto-complete days** -- If a buyer does not respond after delivery, the order auto-completes after this many days (default: 3)
- **Revision limit** -- Default number of revisions per order (default: 2)
- **Allow disputes** -- Whether buyers can open disputes (default: on)
- **Dispute window** -- How many days after completion a buyer can dispute (default: 14)

### Email Notifications
Go to **WP Sell Services > Settings > Emails** to review which emails are sent. All 8 notification types are on by default:

- New order placed (sent to vendor)
- Order completed, cancelled (sent to both)
- Delivery submitted (sent to buyer)
- Revision requested (sent to vendor)
- New message (sent to recipient)
- New review (sent to vendor)
- Dispute opened (sent to both parties and admin)

### Tax Settings (Optional)
If you need to charge tax, go to the **Tax** section under Payments:

- Enable tax and set a rate
- Choose a label (Tax, VAT, GST, etc.)
- Decide whether your service prices already include tax

## Test Your Marketplace

Before going live, run through a quick test:

1. **Create a test vendor account** -- Visit the "Become a Vendor" page and register (or use your admin account, which is automatically a vendor)
2. **Create a test service** -- Go to the Dashboard and walk through the service creation wizard
3. **Place a test order** -- Log in as a different user, find the test service, and purchase it
4. **Complete the order** -- Submit a delivery as the vendor, accept it as the buyer, and leave a review

This confirms your entire marketplace workflow is functioning.

## Recommended Next Steps

Once your settings are in place:

- **Add service categories** at **WP Sell Services > Categories** (e.g., Design, Writing, Marketing, Development)
- **Customize your homepage** using the included blocks (Service Grid, Featured Services, Categories) in the block editor
- **Install an SMTP plugin** like WP Mail SMTP or FluentSMTP for reliable email delivery

## What's Next

- **[Create your first service](../service-creation/service-wizard.md)** -- Learn how the vendor experience works
- **[Compare Free vs Pro](free-vs-pro.md)** -- Understand what the Pro version adds
- **[Order management](../order-management/order-lifecycle.md)** -- See how orders flow from purchase to completion
