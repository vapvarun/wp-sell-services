# Shortcodes Reference

Complete reference for all WP Sell Services shortcodes. Use these to display marketplace features anywhere on your WordPress site.

## Service Display Shortcodes

### [wpss_services]

Display a grid of services with filtering and sorting options.

**Basic Usage:**
```
[wpss_services]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | number | 12 | Number of services to display |
| `columns` | number | 3 | Grid columns (1-4) |
| `category` | string/int | - | Filter by category slug or ID |
| `tag` | string | - | Filter by tag slug |
| `vendor` | int | - | Show services from specific vendor ID |
| `orderby` | string | date | Sort by: date, title, price, rating, popular |
| `order` | string | DESC | Sort order: DESC or ASC |
| `featured` | boolean | - | Show only featured services (yes/no) |
| `min_price` | number | - | Minimum price filter |
| `max_price` | number | - | Maximum price filter |
| `ids` | string | - | Comma-separated service IDs |
| `exclude` | string | - | Comma-separated IDs to exclude |

**Examples:**

**Featured Services Grid:**
```
[wpss_services featured="yes" limit="8" columns="4"]
```

**Category-Specific Services:**
```
[wpss_services category="logo-design" limit="9" columns="3"]
```

**Vendor's Services:**
```
[wpss_services vendor="42" orderby="rating" order="DESC"]
```

**Popular Services:**
```
[wpss_services orderby="popular" limit="6" columns="3"]
```

**Price Range Filter:**
```
[wpss_services min_price="50" max_price="200"]
```

**Specific Services by ID:**
```
[wpss_services ids="10,25,38,42" columns="4"]
```

---

### [wpss_service_search]

Display a service search form with autocomplete.

**Basic Usage:**
```
[wpss_service_search]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `placeholder` | string | "Search services..." | Search input placeholder |
| `show_filters` | boolean | yes | Show category/price filters |
| `show_button` | boolean | yes | Show search button |
| `autocomplete` | boolean | yes | Enable autocomplete suggestions |
| `redirect` | string | - | Redirect to custom search results page |

**Examples:**

**Simple Search Box:**
```
[wpss_service_search placeholder="What service are you looking for?"]
```

**Search Without Filters:**
```
[wpss_service_search show_filters="no"]
```

**Search with Custom Results Page:**
```
[wpss_service_search redirect="/search-results/"]
```

---

### [wpss_featured_services]

Display featured services in a carousel slider.

**Basic Usage:**
```
[wpss_featured_services]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | number | 8 | Number of services to show |
| `autoplay` | boolean | yes | Auto-advance carousel |
| `autoplay_speed` | number | 3000 | Milliseconds between slides |
| `dots` | boolean | yes | Show navigation dots |
| `arrows` | boolean | yes | Show prev/next arrows |

**Examples:**

**Auto-Playing Carousel:**
```
[wpss_featured_services limit="10" autoplay="yes" autoplay_speed="5000"]
```

**Manual Navigation Only:**
```
[wpss_featured_services autoplay="no" dots="yes" arrows="yes"]
```

---

### [wpss_service_categories]

Display service categories in a grid or list.

**Basic Usage:**
```
[wpss_service_categories]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `layout` | string | grid | Display layout: grid or list |
| `columns` | number | 4 | Grid columns (if grid layout) |
| `show_count` | boolean | yes | Show service count per category |
| `show_icon` | boolean | yes | Show category icon |
| `parent` | int | 0 | Show only children of parent category ID |
| `hide_empty` | boolean | yes | Hide categories with no services |
| `orderby` | string | name | Sort by: name, count, id |
| `order` | string | ASC | Sort order: ASC or DESC |

**Examples:**

**Category Grid with Icons:**
```
[wpss_service_categories columns="4" show_count="yes" show_icon="yes"]
```

**List Layout:**
```
[wpss_service_categories layout="list" orderby="count" order="DESC"]
```

**Subcategories Only:**
```
[wpss_service_categories parent="15" columns="3"]
```

---

## Vendor Shortcodes

### [wpss_vendors]

Display a grid of vendors with profiles and ratings.

**Basic Usage:**
```
[wpss_vendors]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | number | 12 | Number of vendors to display |
| `columns` | number | 3 | Grid columns (1-4) |
| `orderby` | string | rating | Sort by: rating, date, name, sales |
| `order` | string | DESC | Sort order: DESC or ASC |
| `featured` | boolean | - | Show only featured vendors |
| `category` | string | - | Filter by service category |
| `verified` | boolean | - | Show only verified vendors |

**Examples:**

**Top-Rated Vendors:**
```
[wpss_vendors orderby="rating" order="DESC" limit="8"]
```

**Verified Vendors Only:**
```
[wpss_vendors verified="yes" columns="4"]
```

**Category Experts:**
```
[wpss_vendors category="graphic-design" orderby="sales"]
```

---

### [wpss_vendor_profile]

Display a specific vendor's profile page.

**Basic Usage:**
```
[wpss_vendor_profile id="42"]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | number | - | **Required:** Vendor user ID |
| `show_services` | boolean | yes | Display vendor's services |
| `show_reviews` | boolean | yes | Display vendor reviews |
| `show_about` | boolean | yes | Display about section |
| `show_stats` | boolean | yes | Display statistics |

**Examples:**

**Full Profile:**
```
[wpss_vendor_profile id="42"]
```

**Profile Without Reviews:**
```
[wpss_vendor_profile id="42" show_reviews="no"]
```

---

### [wpss_top_vendors]

Display highest-rated or best-selling vendors.

**Basic Usage:**
```
[wpss_top_vendors]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | number | 10 | Number of vendors |
| `orderby` | string | rating | Sort by: rating or sales |
| `show_stats` | boolean | yes | Show vendor statistics |

**Examples:**

**Top 5 by Rating:**
```
[wpss_top_vendors limit="5" orderby="rating"]
```

**Best Sellers:**
```
[wpss_top_vendors limit="10" orderby="sales"]
```

---

## Buyer Request Shortcodes

### [wpss_buyer_requests]

Display a list of active buyer requests.

**Basic Usage:**
```
[wpss_buyer_requests]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | number | 20 | Requests per page |
| `category` | string | - | Filter by category |
| `orderby` | string | date | Sort by: date, budget, offers |
| `order` | string | DESC | Sort order |
| `budget_min` | number | - | Minimum budget filter |
| `budget_max` | number | - | Maximum budget filter |

**Examples:**

**Recent Requests:**
```
[wpss_buyer_requests limit="15" orderby="date" order="DESC"]
```

**High-Budget Requests:**
```
[wpss_buyer_requests budget_min="500" orderby="budget" order="DESC"]
```

**Category-Specific Requests:**
```
[wpss_buyer_requests category="web-development" limit="10"]
```

---

### [wpss_post_request]

Display the "Post a Request" form for buyers.

**Basic Usage:**
```
[wpss_post_request]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `redirect` | string | - | Redirect URL after submission |
| `show_budget` | boolean | yes | Show budget field |
| `show_deadline` | boolean | yes | Show deadline field |
| `show_attachments` | boolean | yes | Allow file attachments |

**Examples:**

**Full Request Form:**
```
[wpss_post_request]
```

**Simple Request Form:**
```
[wpss_post_request show_budget="no" show_deadline="no"]
```

**Custom Redirect:**
```
[wpss_post_request redirect="/request-submitted/"]
```

---

## Dashboard Shortcodes

### [wpss_dashboard]

Display the unified buyer/vendor dashboard.

**Basic Usage:**
```
[wpss_dashboard]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `default_tab` | string | overview | Default tab: overview, orders, services, earnings |

**Examples:**

**Dashboard with Orders Tab:**
```
[wpss_dashboard default_tab="orders"]
```

**Note:** This is a complex shortcode that generates a full account interface. No additional attributes typically needed.

---

### [wpss_my_orders]

Display user's order list (buyers and vendors see different views).

**Basic Usage:**
```
[wpss_my_orders]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `status` | string | all | Filter by: all, active, completed, cancelled |
| `limit` | number | 10 | Orders per page |
| `orderby` | string | date | Sort by: date, total, status |

**Examples:**

**Active Orders Only:**
```
[wpss_my_orders status="active"]
```

**Completed Orders:**
```
[wpss_my_orders status="completed" limit="20"]
```

---

### [wpss_order_details]

Display details for a specific order.

**Basic Usage:**
```
[wpss_order_details id="12345"]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | number | - | **Required:** Order ID |

**Example:**

```
[wpss_order_details id="12345"]
```

**Note:** Automatically detects if user is buyer or vendor and shows appropriate view.

---

## Authentication Shortcodes

### [wpss_login]

Display login form.

**Basic Usage:**
```
[wpss_login]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `redirect` | string | - | Redirect URL after login |
| `show_register_link` | boolean | yes | Show "Register" link |
| `show_lost_password` | boolean | yes | Show "Forgot password" link |

**Examples:**

**Simple Login Form:**
```
[wpss_login]
```

**Login with Custom Redirect:**
```
[wpss_login redirect="/dashboard/"]
```

**Login Only (No Links):**
```
[wpss_login show_register_link="no" show_lost_password="no"]
```

---

### [wpss_register]

Display registration form (buyer or vendor).

**Basic Usage:**
```
[wpss_register]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `role` | string | buyer | User role: buyer or vendor |
| `redirect` | string | - | Redirect URL after registration |
| `show_login_link` | boolean | yes | Show "Already registered?" link |

**Examples:**

**Vendor Registration:**
```
[wpss_register role="vendor"]
```

**Buyer Registration with Redirect:**
```
[wpss_register role="buyer" redirect="/welcome/"]
```

---

## Common Page Setups

### Homepage

```
# Welcome to Our Marketplace

[wpss_service_search]

## Featured Services
[wpss_featured_services limit="8"]

## Browse by Category
[wpss_service_categories columns="4"]

## Top Rated Services
[wpss_services orderby="rating" limit="6" columns="3"]
```

---

### Services Catalog Page

```
# Browse All Services

[wpss_service_search show_filters="yes"]

[wpss_services limit="12" columns="3" orderby="date" order="DESC"]
```

---

### Vendor Directory Page

```
# Our Vendors

Find the perfect expert for your project.

[wpss_top_vendors limit="3"]

## All Vendors
[wpss_vendors columns="3" orderby="rating" limit="12"]
```

---

### Buyer Requests Page

```
# Project Requests

Post your project and receive custom offers from vendors.

[wpss_post_request]

## Active Requests
[wpss_buyer_requests limit="15"]
```

---

### Dashboard Page

```
[wpss_dashboard]
```

---

### My Orders Page

```
# My Orders

[wpss_my_orders limit="10"]
```

---

## Using Shortcodes in Widgets

All shortcodes work in widget areas:

1. Go to **Appearance → Widgets**
2. Add **Custom HTML** or **Shortcode** widget
3. Paste shortcode
4. Save widget

**Example: Sidebar Search**
```
[wpss_service_search show_filters="no"]
```

---

## Using Shortcodes in PHP

Execute shortcodes in theme templates:

```php
<?php echo do_shortcode('[wpss_services limit="6" columns="3"]'); ?>
```

---

## Combining Shortcodes

Multiple shortcodes work together on the same page:

```
# Design Services

[wpss_service_search]

## Popular Categories
[wpss_service_categories parent="5" columns="3"]

## Featured Designers
[wpss_vendors category="design" limit="8" columns="4"]

## Latest Services
[wpss_services category="design" limit="9" columns="3"]
```

---

## Shortcode Not Working?

**Troubleshooting:**
1. Check spelling exactly (case-sensitive)
2. Ensure page is published (not draft)
3. Clear cache (site and browser)
4. Verify plugin is active
5. Check for JavaScript errors in browser console
6. Test in default WordPress theme
7. Disable other plugins temporarily

---

## Related Documentation

- [Gutenberg Blocks](gutenberg-blocks.md) - Block editor alternatives
- [Pages Setup](../admin-settings/pages-setup.md) - Required page configuration
- [Template Overrides](template-overrides.md) - Custom page templates
- [Search Filters](search-filters.md) - Advanced search options

---

## Next Steps

1. Create pages with shortcodes
2. Test each shortcode functionality
3. Customize with available attributes
4. Style with your theme's CSS
5. Combine shortcodes for rich layouts
