# Displaying Your Marketplace

WP Sell Services gives you 20 ready-made page elements to build every part of your marketplace. Add a services catalog, vendor directory, user dashboard, buyer requests board, and more -- all by placing elements on your WordPress pages.

---

## How It Works

Each page element is a building block you can place on any page using either the block editor or a simple tag. The plugin also auto-creates the most important pages during setup, so you may already have everything in place.

You do not need to write any code. Just add the element to a page, publish, and it works.

---

## Marketplace Pages

These elements power the public-facing browsing experience.

### Services Catalog

Displays a grid of all published services with thumbnails, prices, ratings, and vendor info. This is the main browsing page of your marketplace -- the equivalent of a product catalog. Supports filtering by category, tag, or vendor, and can be sorted by date, price, rating, or sales.

### Service Search Bar

A search form with a keyword field and category dropdown. Place it on your homepage or at the top of your services page so visitors can quickly find what they need.

### Service Categories

Shows your service categories in a visual grid with icons and service counts. Great for your homepage or a dedicated "Browse by Category" section. Visitors click a category to see all services within it.

### Featured Services

Highlights services you have marked as featured. Perfect for homepage spotlights, promotional sections, or "Editor's Picks" areas. Only shows services with the featured flag enabled.

### Buyer Requests Board

Lists all open buyer requests so vendors can browse projects and submit proposals. You can filter by category and budget range.

### Post a Request Form

The form buyers use to submit a new request. Place it on a dedicated "Post a Request" page or alongside the requests board. Requires the user to be logged in.

---

## User Pages

These elements power the logged-in user experience.

### Unified Dashboard

The single most important page element. It creates a full-featured dashboard that automatically adapts to the user's role:

- **Buyers** see their orders, requests, messages, favorites, and profile settings
- **Vendors** see their services, sales orders, earnings, analytics, messages, and portfolio
- **Dual-role users** see both buyer and vendor sections

One page, one element, serves everyone.

### My Orders

Shows the user's order list. Can be configured to show buyer orders (services purchased) or vendor orders (services sold). Includes status filtering and pagination.

### Order Details

Displays the full details of a specific order. Used on a dedicated page -- the order ID comes from the URL automatically. Only the buyer, vendor, or admin involved in the order can view it.

### Login Form

A simple login form using WordPress authentication. Shows an "already logged in" message for authenticated users. You can set a custom redirect URL for after login.

### Registration Form

A user registration form with username, email, and password fields. Requires WordPress registration to be enabled in Settings > General.

### Vendor Registration

The registration form specifically for users who want to become vendors. This is different from the general registration form -- use this on your "Become a Vendor" or "Start Selling" page.

### Shopping Cart

Displays the shopping cart where buyers can review selected services, packages, and add-ons before proceeding to checkout. Shows item details, quantities, prices, and a total. Buyers can remove items or proceed to the checkout page.

### Service Checkout

The checkout flow for standalone mode. Displays billing details, order review, payment method selection, and the Place Order button. This is the page where buyers complete their purchase when using the built-in checkout system (not WooCommerce).

### My Account

An account management page for standalone mode. Shows the logged-in user's profile information, saved addresses, and account settings. This is separate from the vendor dashboard and focuses on personal account details.

### Service Creation Wizard

The multi-step service creation form available to vendors. Guides vendors through creating a new service with steps for title, description, category, pricing packages, add-ons, requirements, media uploads, and FAQs. Only visible to users with the vendor role.

---

## Widget Elements

Smaller, focused elements perfect for sidebars, footers, or supplementary sections.

### Vendor Directory

A grid of vendor profiles showing names, avatars, ratings, and review counts. Sort by rating, join date, name, or sales volume. Use it for a dedicated "Our Vendors" page or a sidebar widget.

### Top Vendors

Highlights the highest-rated vendors on your marketplace. Great for homepage sections like "Our Best Sellers" or sidebar widgets that showcase top talent.

### Vendor Profile

Displays a specific vendor's full profile page. Typically used on a dedicated profile page where the vendor ID comes from the URL. Can also be set to show a specific vendor by ID.

### Buyer Request Listing

A compact listing of active buyer requests with budget and category info. Useful for sidebar placements or "Latest Opportunities" sections on vendor-facing pages.

---

## Where to Use What

| Goal | Recommended Element |
|------|-------------------|
| Main marketplace browsing page | Services Catalog + Service Search Bar |
| Homepage | Featured Services + Service Categories + Top Vendors |
| User account area | Unified Dashboard |
| Vendor recruitment page | Vendor Registration |
| Buyer request marketplace | Buyer Requests Board + Post a Request Form |
| Vendor directory page | Vendor Directory |
| Sidebar | Service Search Bar, Top Vendors, or Buyer Request Listing |

---

## Tips

- **Start with auto-created pages.** During setup, the plugin creates the essential pages for you. Customize from there.
- **Combine multiple elements** on a single page for richer layouts. For example, put the search bar above the service categories, then the service grid below.
- **All elements work in widgets too.** Add them to sidebars or footer areas for compact displays.
- **Elements also work as Gutenberg blocks.** See [Block Editor Elements](gutenberg-blocks.md) for the drag-and-drop alternative.

---

## Troubleshooting

**Element not showing anything?**
Make sure there is content to display -- published services, registered vendors, or open requests. An empty marketplace will show empty grids.

**Page showing raw text instead of the element?**
Check that the plugin is active, the page is published (not draft), and clear your site cache.

**Styling looks off?**
Some themes may need minor CSS adjustments. Check [Customizing the Look](template-overrides.md) for details on template customization.
