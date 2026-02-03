# Pages Setup

Configure the required marketplace pages that power your service platform. These pages use shortcodes to display marketplace functionality.

## Required Pages Overview

WP Sell Services requires four core pages to function:

| Page | Purpose | Required Shortcode |
|------|---------|-------------------|
| Services Catalog | Browse all services | `[wpss_services]` |
| Become a Vendor | Vendor registration | `[wpss_register]` |
| Dashboard | Unified account area for buyers and vendors | `[wpss_dashboard]` |
| Buyer Requests | Browse and post requests | `[wpss_buyer_requests]` |

![Pages Setup Tab](../images/settings-pages-tab.png)

## Auto-Create Pages

The quickest way to set up your marketplace pages.

### One-Click Setup

1. Go to **WP Sell Services → Settings → Pages**
2. Click **Auto-Create All Pages**
3. Plugin creates all four pages with correct shortcodes
4. Pages are published and ready to use

**What Gets Created:**

- `/services/` - Services catalog page
- `/become-a-vendor/` - Vendor registration
- `/dashboard/` - User dashboard
- `/buyer-requests/` - Request marketplace

### Benefits

- Correct shortcodes automatically inserted
- Proper page structure and content
- SEO-friendly slugs
- Immediate functionality

## Manual Page Setup

Prefer to create pages yourself? Follow this guide.

### Services Catalog Page

Displays all available services with search and filtering.

**Create the Page:**

1. Go to **Pages → Add New**
2. Title: "Services" or "Browse Services"
3. Add the shortcode: `[wpss_services]`
4. Publish the page
5. Copy the page URL
6. Go to **WP Sell Services → Settings → Pages**
7. Select this page in the **Services Catalog** dropdown

**Optional Attributes:**

```
[wpss_services limit="12" columns="3" orderby="date" order="DESC"]
```

See [Shortcodes Reference](../marketplace-display/shortcodes-reference.md) for all options.

### Become a Vendor Page

Registration form for users who want to sell services.

**Create the Page:**

1. **Pages → Add New**
2. Title: "Become a Vendor" or "Start Selling"
3. Add shortcode: `[wpss_register]`
4. Add descriptive content about vendor benefits
5. Publish and assign in Settings → Pages

**Example Content:**

```
Join our marketplace and start selling your services!

[wpss_register]

Benefits of becoming a vendor:
- Reach thousands of buyers
- Flexible pricing and packages
- Manage orders easily
- Secure payments
```

### Dashboard Page

The unified dashboard serves all users with a single shortcode that adapts based on their role.

**Create the Page:**

1. **Pages → Add New**
2. Title: "Dashboard" or "My Account"
3. Add shortcode: `[wpss_dashboard]`
4. Publish and assign in Settings → Pages

**Understanding the Unified Dashboard:**

WP Sell Services uses a single `[wpss_dashboard]` shortcode for all users. The dashboard automatically shows different sections based on whether the user is a buyer, vendor, or both.

**What Buyers See:**
- My Orders (services they've purchased)
- My Requests (projects they've posted)
- Messages (conversations with vendors)
- Favorites (saved services)
- Profile Settings
- Order history and receipts

**What Vendors See:**
- Vendor Dashboard (statistics overview)
- My Services (create and manage services)
- Sales Orders (incoming orders from buyers)
- Earnings (revenue, withdrawals)
- Analytics (performance metrics)
- Messages (conversations with buyers)
- Profile & Portfolio

**What Dual-Role Users See (Both Buyer and Vendor):**
- All buyer sections (for services they purchase)
- All vendor sections (for services they sell)
- Clear separation between buying and selling activities
- Toggle between buyer and vendor views

**Start Selling Button:**

For users who are buyers but not yet vendors:
- "Start Selling" or "Become a Vendor" button appears
- Clicking redirects to vendor registration page
- After vendor approval, dashboard shows vendor sections

**Dashboard Navigation:**

The dashboard includes a tabbed or sidebar navigation:
- Dashboard Home (overview)
- Orders/Sales (context-specific)
- Services (vendors only)
- Requests (buyers)
- Messages (all users)
- Earnings (vendors only)
- Settings (all users)

**Why One Dashboard?**

Benefits of the unified approach:
- Simpler for users (one place for everything)
- Easier for admins (one shortcode to manage)
- Better for dual-role users (no switching pages)
- Consistent interface across all features
- Mobile-friendly responsive design

### Buyer Requests Page

Marketplace where buyers post project requests and vendors can submit offers.

**Create the Page:**

1. **Pages → Add New**
2. Title: "Buyer Requests" or "Post a Request"
3. Add shortcode: `[wpss_buyer_requests]`
4. Publish and assign in Settings → Pages

**Additional Shortcodes:**

- `[wpss_post_request]` - Standalone request form
- `[wpss_buyer_requests limit="20"]` - Custom request limit

## Assigning Existing Pages

Already have pages you want to use? Assign them to marketplace functions.

### Assignment Process

1. Navigate to **WP Sell Services → Settings → Pages**
2. Each page setting shows a dropdown of all published pages
3. Select the appropriate page for each function
4. Ensure the page contains the correct shortcode
5. Click **Save Changes**

**Important:** The plugin checks if the selected page contains the required shortcode. If missing, you'll see a warning.

### Page Requirements

Each assigned page must include:

| Page Type | Required Element |
|-----------|------------------|
| Services Catalog | `[wpss_services]` shortcode or Service Grid block |
| Become a Vendor | `[wpss_register]` shortcode |
| Dashboard | `[wpss_dashboard]` shortcode |
| Buyer Requests | `[wpss_buyer_requests]` shortcode |

## Page Content Best Practices

### Services Catalog

Add helpful content before the shortcode:

```
Browse professional services from verified vendors

[wpss_service_search]

[wpss_service_categories]

[wpss_services limit="12" columns="3"]
```

This structure provides search, categories, then service grid.

### Become a Vendor

Include persuasive content:

```
# Start Your Service Business Today

Why sell on [Platform Name]:
- No listing fees
- Flexible pricing options
- Professional dashboard
- Secure payments

[wpss_register]
```

### Dashboard

Keep it simple - the shortcode generates the full interface:

```
[wpss_dashboard]
```

No additional content needed. The unified dashboard includes all navigation and features for both buyers and vendors.

**Alternative: Add Welcome Message**

```
# Welcome to Your Dashboard

Manage your orders, services, and account all in one place.

[wpss_dashboard]
```

The dashboard automatically adapts to show buyer or vendor features based on the logged-in user's role.

### Buyer Requests

Combine browsing and posting:

```
# Project Requests

Post your project and receive custom offers from vendors.

[wpss_post_request]

## Browse Active Requests

[wpss_buyer_requests limit="15"]
```

## Additional Pages (Optional)

Consider creating these supplementary pages:

### Vendor Directory

```
[wpss_vendors columns="4" orderby="rating"]
```

Browse all vendors with ratings and statistics.

### Featured Services

```
[wpss_featured_services]
```

Showcase premium or promoted services.

### Top Vendors

```
[wpss_top_vendors limit="10"]
```

Display highest-rated vendors.

## Page Templates

Some themes offer specific page templates that work well with marketplace pages.

### Recommended Templates

- **Full Width** - Services catalog, dashboard
- **Sidebar Right** - Buyer requests, vendor directory
- **Blank Template** - Dashboard (for custom styling)

Select template in page editor sidebar under **Page Attributes → Template**.

## Troubleshooting

### Page Not Working

**Symptoms:** Shortcode displays as text, no functionality

**Solutions:**
1. Verify shortcode spelling exactly matches documentation
2. Check page is published, not draft
3. Clear cache (site cache and browser cache)
4. Test in different browser
5. Deactivate other plugins temporarily

### Dashboard Shows Wrong Content

**Symptoms:** User doesn't see expected sections

**Solutions:**
1. Check user role (**Dashboard → Users**)
2. Verify user has completed vendor registration (for vendor features)
3. Confirm vendor account is approved (vendor sections require approval)
4. Clear user sessions: **Settings → Pages → Clear Sessions**
5. Log out and log back in
6. Clear browser cache

**Note:** The unified dashboard shows different sections based on user role. Buyers only see buyer sections, vendors see vendor sections, and dual-role users see both.

### Permission Errors

**Symptoms:** "You don't have permission" messages

**Solutions:**
1. Verify page is public, not password protected
2. Check [Vendor Settings](vendor-settings.md) - registration might be closed
3. Confirm user is logged in for dashboard access

## Related Documentation

- [Shortcodes Reference](../marketplace-display/shortcodes-reference.md) - Complete shortcode list
- [Gutenberg Blocks](../marketplace-display/gutenberg-blocks.md) - Block-based page building
- [Template Overrides](../marketplace-display/template-overrides.md) - Custom page templates

## Next Steps

After setting up pages:

1. [Configure commission rates](payment-settings.md)
2. [Set up vendor registration rules](vendor-settings.md)
3. Test each page with a test user account
4. Customize page designs with your theme
