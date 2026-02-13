# Pages Setup

Configure the three required marketplace pages that power your service platform using shortcodes.

## Required Pages Overview

WP Sell Services requires three core pages to function properly:

| Page | Purpose | Required Shortcode |
|------|---------|-------------------|
| Services | Browse all services | `[wpss_services]` |
| Dashboard | Unified buyer and vendor dashboard | `[wpss_dashboard]` |
| Become a Vendor | Vendor registration | `[wpss_vendor_registration]` |

![Pages settings tab](../images/settings-pages-tab.png)

![Full pages settings](../images/settings-pages.png)

## Auto-Create Pages

The quickest way to set up your marketplace pages in one click.

### One-Click Setup

1. Go to **WP Sell Services → Settings → Pages**
2. Click **Auto-Create All Pages** button
3. Plugin creates all three pages with correct shortcodes
4. Pages are published and assigned automatically

**What Gets Created:**

- `/services/` - Services catalog page
- `/dashboard/` - Unified user dashboard
- `/become-a-vendor/` - Vendor registration

### Benefits

- Correct shortcodes automatically inserted
- Pages published and assigned in one step
- SEO-friendly permalinks
- Immediate functionality

### After Auto-Creation

Each page can be customized:
- Edit page content and layout
- Change permalink structure
- Assign custom page template
- Add additional content blocks

## Manual Page Setup

Prefer to create pages yourself? Follow this step-by-step guide.

### Services Catalog Page

Displays all available services with search, filtering, and categories.

**Create the Page:**

1. Go to **Pages → Add New**
2. Title: "Services" or "Browse Services"
3. Add the shortcode: `[wpss_services]`
4. Publish the page
5. Copy the page URL
6. Go to **WP Sell Services → Settings → Pages**
7. Select this page in **Services Page** dropdown
8. Save changes

**Optional Shortcode Attributes:**

```
[wpss_services limit="12" columns="3" orderby="date" order="DESC"]
```

**Available Attributes:**
- `limit` - Services per page (default: 12)
- `columns` - Grid columns (default: 3)
- `orderby` - Sort by: date, title, rating, price
- `order` - ASC or DESC

See [Shortcodes Reference](../marketplace-display/shortcodes-reference.md) for complete list.

### Dashboard Page

The unified dashboard serves all users with a single shortcode that dynamically displays buyer or vendor features based on the logged-in user's role.

**Create the Page:**

1. **Pages → Add New**
2. Title: "Dashboard" or "My Account"
3. Add shortcode: `[wpss_dashboard]`
4. Publish the page
5. Assign in **Settings → Pages → Dashboard**
6. Save changes

**Understanding the Unified Dashboard:**

WP Sell Services uses one `[wpss_dashboard]` shortcode for all users. The dashboard automatically adapts content based on user role.

**What Buyers See:**
- My Orders (services purchased)
- My Requests (projects posted)
- Messages (vendor conversations)
- Favorites (saved services)
- Profile Settings

**What Vendors See:**
- Vendor Dashboard (stats overview)
- My Services (manage listings)
- Sales Orders (incoming orders)
- Earnings (revenue, withdrawals)
- Analytics (performance metrics)
- Messages (buyer conversations)
- Profile & Portfolio

**What Dual-Role Users See:**
- All buyer sections (for purchases)
- All vendor sections (for sales)
- Clear separation between activities
- Toggle between buyer and vendor modes

**"Become a Vendor" Button:**

For logged-in users who are buyers but not vendors:
- Dashboard shows "Become a Vendor" button
- Clicking redirects to vendor registration page
- After approval, vendor sections appear
- No page switching needed

**Why One Dashboard?**

Benefits of unified approach:
- Simple for users (one destination)
- Easy for admins (one shortcode)
- Better for dual-role users
- Consistent interface
- Mobile-friendly

### Become a Vendor Page

Registration form for users who want to sell services on your marketplace.

**Create the Page:**

1. **Pages → Add New**
2. Title: "Become a Vendor" or "Start Selling"
3. Add shortcode: `[wpss_vendor_registration]`
4. Add persuasive content about vendor benefits
5. Publish the page
6. Assign in **Settings → Pages → Become a Vendor**
7. Save changes

**Important:** Use `[wpss_vendor_registration]` not `[wpss_register]`.

**Example Content:**

```
# Start Your Service Business Today

Join our marketplace and reach thousands of buyers!

## Why Sell With Us?

- No listing fees
- Flexible pricing options
- Professional dashboard
- Secure payments
- 24/7 support

[wpss_vendor_registration]

Questions? Contact us at support@example.com
```

## Assigning Existing Pages

Already have pages you want to use? Assign them to marketplace functions.

### Assignment Process

1. Navigate to **WP Sell Services → Settings → Pages**
2. Each setting shows a dropdown of all published pages
3. Select the appropriate page for each function
4. Ensure page contains the correct shortcode
5. Click **Save Changes**

**Page Validation:**

The plugin checks if selected pages contain required shortcodes. If missing, you'll see a warning to add the shortcode.

### Page Requirements

Each assigned page must include its shortcode:

| Page Type | Required Shortcode |
|-----------|-------------------|
| Services | `[wpss_services]` or Services Grid block |
| Dashboard | `[wpss_dashboard]` |
| Become a Vendor | `[wpss_vendor_registration]` |

### Create Individual Pages

Don't want to auto-create all pages at once? Create pages individually:

1. Go to **Settings → Pages**
2. Click **Create Page** next to any page field
3. Plugin creates that specific page
4. Page is auto-assigned
5. Repeat for other pages as needed

## Page Content Best Practices

### Services Catalog

Enhance the catalog with additional elements:

```
# Professional Services Marketplace

Find the perfect service for your needs from verified vendors.

[wpss_service_search]

[wpss_service_categories]

[wpss_services limit="12" columns="3"]
```

This structure provides search, categories, then service grid.

### Become a Vendor

Include persuasive content before the form:

```
# Start Earning Today

Turn your skills into income on our marketplace.

## Benefits

- Keep 90% of your earnings
- Set your own prices
- Flexible working hours
- Professional tools included

## Requirements

- Valid email address
- Skill or service to offer
- Professional portfolio (optional)

[wpss_vendor_registration]
```

### Dashboard

Keep it simple - the shortcode generates the full interface:

```
[wpss_dashboard]
```

**Optional: Add Welcome Message**

```
# Welcome to Your Dashboard

Manage your orders, services, and account all in one place.

[wpss_dashboard]
```

The dashboard automatically shows buyer or vendor sections based on the logged-in user's role.

## Additional Optional Pages

Consider creating these supplementary pages:

### Vendor Directory

```
[wpss_vendors columns="4" orderby="rating"]
```

Browse all marketplace vendors with ratings and statistics.

### Featured Services

```
[wpss_featured_services limit="8"]
```

Showcase premium or promoted services on homepage.

### Top Vendors

```
[wpss_top_vendors limit="10"]
```

Display highest-rated vendors.

## Page Templates

Some WordPress themes offer specific page templates that work well with marketplace pages.

### Recommended Templates

- **Full Width** - Best for services catalog and dashboard
- **Sidebar Right** - Good for browse pages
- **Blank Template** - Best for dashboard (custom styling)

Select template in page editor sidebar under **Page Attributes → Template**.

## Troubleshooting

### Shortcode Displays as Text

**Symptoms:** Shortcode shows as `[wpss_services]` instead of rendering

**Solutions:**
1. Verify exact shortcode spelling
2. Check page is published, not draft
3. Clear all caches (site and browser)
4. Deactivate conflicting plugins
5. Switch to default WordPress theme temporarily

### Dashboard Shows Wrong Content

**Symptoms:** User doesn't see expected sections

**Solutions:**
1. Verify user role at **Users → All Users**
2. Check vendor registration status (must be approved)
3. Confirm user is logged in (dashboard requires authentication)
4. Clear user sessions: **Settings → Pages → Clear Sessions** **[PRO]**
5. Log out and back in
6. Clear browser cookies

**Remember:** The unified dashboard shows different content based on user role. Buyers see buyer sections, vendors see vendor sections, dual-role users see both.

### "Page Not Found" Error

**Symptoms:** Selected pages return 404 errors

**Solutions:**
1. Flush permalinks: **Settings → Permalinks → Save Changes**
2. Verify page is published (not private or draft)
3. Check for permalink conflicts with other plugins
4. Ensure page wasn't deleted

### Permission Denied Messages

**Symptoms:** "You don't have permission to access this page"

**Solutions:**
1. Verify page is public (not password protected)
2. Check vendor registration is open in **Settings → Vendor**
3. User must be logged in for dashboard access
4. Vendor account must be approved

## Related Documentation

- [Shortcodes Reference](../marketplace-display/shortcodes-reference.md) - Complete shortcode list and attributes
- [Gutenberg Blocks](../marketplace-display/gutenberg-blocks.md) - Block-based page building
- [Template Overrides](../marketplace-display/template-overrides.md) - Custom templates
- [Vendor Registration](../vendor-system/vendor-registration.md) - Registration workflow

## Next Steps

After setting up pages:

1. Test each page as a logged-out visitor
2. Create a test vendor account to verify dashboard
3. Place a test order to verify buyer dashboard
4. Customize page designs to match your brand
5. Add pages to site navigation menu
