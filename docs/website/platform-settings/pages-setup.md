# Pages Setup

WP Sell Services needs a few dedicated pages to run your marketplace. The good news: you can create them all in one click, or set them up manually if you prefer.

---

## Required Pages

Your marketplace needs three core pages:

| Page | What It Does |
|------|-------------|
| **Services** | The main browsing page where visitors find and explore services |
| **Dashboard** | The unified account area for buyers and vendors |
| **Become a Vendor** | The registration page for users who want to sell |

![Pages settings tab](../images/settings-pages-tab.png)

![Full pages settings](../images/settings-pages.png)

---

## One-Click Setup (Recommended)

The fastest way to get started:

1. Go to **WP Sell Services > Settings > Pages**
2. Click **Auto-Create All Pages**
3. Done -- the plugin creates and assigns all three pages automatically

The pages are published immediately with the correct content, SEO-friendly URLs, and everything wired up and ready to go.

---

## Manual Page Setup

Prefer to create pages yourself? Here is how.

### Services Page

1. Go to **Pages > Add New**
2. Give it a title like "Services" or "Browse Services"
3. Add the Services Grid block (or the services page element)
4. Publish the page
5. Go to **WP Sell Services > Settings > Pages** and select this page in the Services dropdown
6. Save Changes

**Tip:** For a richer catalog page, combine the search bar, category grid, and service grid on the same page.

### Dashboard Page

1. Create a new page titled "Dashboard"
2. Add the Dashboard block (or the dashboard page element)
3. Publish and assign it in **Settings > Pages > Dashboard**

The dashboard automatically shows different content based on who is logged in:

- **Buyers** see their orders, requests, messages, favorites, and profile settings
- **Vendors** see their services, sales, earnings, analytics, messages, and portfolio
- **Users with both roles** see everything

Logged-in buyers who are not yet vendors will see a "Become a Vendor" button in their dashboard.

### Become a Vendor Page

1. Create a new page titled "Become a Vendor" or "Start Selling"
2. Add the Vendor Registration block (or the vendor registration page element)
3. Add some persuasive content above the form -- explain why someone should sell on your marketplace, highlight benefits like no listing fees and flexible pricing
4. Publish and assign it in **Settings > Pages > Become a Vendor**

---

## Optional Pages You Can Add

Beyond the three required pages, consider adding these for a richer marketplace:

| Page | What to Add |
|------|------------|
| **Vendor Directory** | A grid of all marketplace vendors sorted by rating |
| **Featured Services** | A curated showcase of your best services |
| **Top Vendors** | Your highest-rated sellers |
| **Buyer Requests** | The request board with a "Post a Request" form |

---

## Changing Assigned Pages

Already have pages you want to use instead?

1. Go to **WP Sell Services > Settings > Pages**
2. Each setting shows a dropdown of all your published pages
3. Select the page you want for each function
4. Make sure the page contains the correct block or page element
5. Save Changes

You can also create individual pages one at a time using the **Create Page** button next to each dropdown.

---

## Page Template Tips

For the best results:

- Use a **Full Width** template for the Services catalog and Dashboard pages
- The Dashboard does not need any extra content -- the page element generates the full interface
- For the Become a Vendor page, add marketing content (benefits, testimonials, earnings potential) above the registration form
- Add your marketplace pages to your site's navigation menu so visitors can find them easily

---

## Troubleshooting

**Page shows raw text instead of the marketplace content?**
Make sure the plugin is active, the page is published (not a draft), and clear all caches.

**Dashboard shows wrong content for a user?**
The dashboard adapts to user roles. Buyers see buyer sections, vendors see vendor sections. Verify the user's role at **Users > All Users**. For new vendors, check if admin approval is required.

**Pages return 404 errors?**
Go to **Settings > Permalinks** and click Save Changes to refresh your URL structure. Also verify the page is published and not trashed.

**"Permission denied" when accessing dashboard?**
The dashboard requires users to be logged in. For vendor sections, the user must have an approved vendor account.
