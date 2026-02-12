# Shortcodes Reference

Complete reference for all WP Sell Services shortcodes. Display marketplace features anywhere on your WordPress site.

## Available Shortcodes

WP Sell Services includes 13 shortcodes organized into 5 categories:

- **Service Display** (4 shortcodes)
- **Vendor Display** (3 shortcodes)
- **Buyer Requests** (2 shortcodes)
- **Dashboard** (2 shortcodes)
- **Authentication** (2 shortcodes)

---

## Service Display Shortcodes

### [wpss_services]

Display a grid of services with filtering and sorting.

**Basic Usage:**
```
[wpss_services]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `category` | string/int | - | Filter by category slug or ID |
| `tag` | string/int | - | Filter by tag slug or ID |
| `vendor` | int | - | Show services from specific vendor (user ID) |
| `limit` | number | 12 | Number of services to display |
| `columns` | number | 4 | Grid columns (1-4) |
| `orderby` | string | date | Sort by: date, title, price, rating, sales |
| `order` | string | DESC | Sort order: DESC or ASC |
| `featured` | string | - | Show only featured services (true/1) |

**Sorting Options:**

- `orderby="date"` - Publication date (newest first by default)
- `orderby="title"` - Alphabetical by title
- `orderby="price"` - By starting price (uses `_wpss_starting_price` meta)
- `orderby="rating"` - By average rating (uses `_wpss_rating_average` meta)
- `orderby="sales"` - By total sales (uses `_wpss_total_sales` meta)

**Examples:**

**Featured Services Grid:**
```
[wpss_services featured="true" limit="8" columns="4"]
```

**Category-Specific Services:**
```
[wpss_services category="logo-design" limit="9" columns="3"]
```

**Vendor's Services:**
```
[wpss_services vendor="42" orderby="rating" order="DESC"]
```

**Top-Rated Services:**
```
[wpss_services orderby="rating" order="DESC" limit="6"]
```

---

### [wpss_service_search]

Display a service search form with category dropdown.

**Basic Usage:**
```
[wpss_service_search]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `placeholder` | string | "Search services..." | Search input placeholder text |
| `show_categories` | string | true | Show category dropdown (true/false) |
| `button_text` | string | "Search" | Search button text |
| `action` | string | - | Form action URL (defaults to services archive) |

**Examples:**

**Simple Search Box:**
```
[wpss_service_search placeholder="What service are you looking for?"]
```

**Search Without Category Filter:**
```
[wpss_service_search show_categories="false"]
```

**Custom Button Text:**
```
[wpss_service_search button_text="Find Services"]
```

**Custom Results Page:**
```
[wpss_service_search action="/custom-search/"]
```

---

### [wpss_featured_services]

Display featured services (delegates to `wpss_services` with `featured="true"`).

**Basic Usage:**
```
[wpss_featured_services]
```

**Attributes:**

Accepts all `wpss_services` attributes. The `featured` attribute is automatically set to `true`.

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | number | 12 | Number of featured services |
| `columns` | number | 4 | Grid columns |
| `category` | string/int | - | Filter by category |
| `tag` | string/int | - | Filter by tag |
| `orderby` | string | date | Sort order |
| `order` | string | DESC | ASC or DESC |

**Examples:**

**Featured Services with Custom Layout:**
```
[wpss_featured_services limit="6" columns="3"]
```

**Featured Services by Category:**
```
[wpss_featured_services category="web-development" limit="8"]
```

---

### [wpss_service_categories]

Display service categories in a grid with icons and counts.

**Basic Usage:**
```
[wpss_service_categories]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `parent` | int | 0 | Show only children of this parent category ID |
| `show_count` | string | true | Show service count per category |
| `columns` | number | 4 | Grid columns |
| `hide_empty` | string | true | Hide categories with no services |
| `limit` | number | 12 | Maximum categories to display |

**Note:** Categories are ordered by count (descending) and limited by the `limit` attribute. The `orderby` and `order` attributes are not user-configurable.

**Examples:**

**Category Grid with Icons:**
```
[wpss_service_categories columns="4" show_count="true"]
```

**Top 6 Categories:**
```
[wpss_service_categories limit="6" columns="3"]
```

**Subcategories of Parent:**
```
[wpss_service_categories parent="15" columns="3"]
```

**Show Empty Categories:**
```
[wpss_service_categories hide_empty="false"]
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
| `columns` | number | 4 | Grid columns (1-4) |
| `orderby` | string | rating | Sort by: rating, date, name, sales |
| `order` | string | DESC | Sort order: DESC or ASC |

**Examples:**

**Top-Rated Vendors:**
```
[wpss_vendors orderby="rating" order="DESC" limit="8"]
```

**Newest Vendors:**
```
[wpss_vendors orderby="date" order="DESC" columns="4"]
```

**Best-Selling Vendors:**
```
[wpss_vendors orderby="sales" limit="10"]
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
| `id` | number | (query var) | Vendor user ID (required) |

**Note:** If no ID is provided, the shortcode checks for a `vendor_id` query parameter. Uses template file `vendor/profile.php` or fallback rendering.

**Examples:**

**Specific Vendor Profile:**
```
[wpss_vendor_profile id="42"]
```

**Auto-Detect from URL:**
```
[wpss_vendor_profile]
```
(Works when URL contains `?vendor_id=42`)

---

### [wpss_top_vendors]

Display highest-rated vendors (delegates to `wpss_vendors` with `orderby="rating"`).

**Basic Usage:**
```
[wpss_top_vendors]
```

**Attributes:**

Accepts `limit` and `columns`. The `orderby` is hardcoded to `rating` and `order` to `DESC`.

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | number | 12 | Number of top vendors |
| `columns` | number | 4 | Grid columns |

**Examples:**

**Top 5 Vendors:**
```
[wpss_top_vendors limit="5"]
```

**Top 10 with Custom Layout:**
```
[wpss_top_vendors limit="10" columns="5"]
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
| `limit` | number | 10 | Requests per page |
| `category` | string/int | - | Filter by category ID |
| `budget_min` | number | - | Minimum budget filter |
| `budget_max` | number | - | Maximum budget filter |

**Note:** There is no `orderby` or `order` attribute. Requests are retrieved from `BuyerRequestService` in the order provided by that service.

**Examples:**

**Recent Requests:**
```
[wpss_buyer_requests limit="15"]
```

**High-Budget Requests:**
```
[wpss_buyer_requests budget_min="500"]
```

**Category-Specific Requests:**
```
[wpss_buyer_requests category="5" limit="10"]
```

**Budget Range:**
```
[wpss_buyer_requests budget_min="100" budget_max="500"]
```

---

### [wpss_post_request]

Display the "Post a Request" form for buyers.

**Basic Usage:**
```
[wpss_post_request]
```

**Attributes:**

This shortcode accepts no attributes. The form includes:
- Title (required, max 100 chars)
- Description (required, textarea)
- Category (dropdown)
- Budget Min/Max (number inputs)
- Deadline (date input)

**Requirements:**
- User must be logged in (shows login prompt if not)
- Form includes nonce for security

**Example:**
```
[wpss_post_request]
```

---

## Dashboard Shortcodes

### [wpss_my_orders]

Display user's order list (buyer or vendor view).

**Basic Usage:**
```
[wpss_my_orders]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `type` | string | customer | View type: customer or vendor |
| `status` | string | - | Filter by order status |
| `limit` | number | 20 | Orders per page |

**Note:** There is no `orderby` attribute. Orders are always sorted by `created_at DESC`.

**Requirements:**
- User must be logged in (shows login prompt if not)

**Examples:**

**Active Orders Only:**
```
[wpss_my_orders status="active"]
```

**Vendor Orders:**
```
[wpss_my_orders type="vendor" limit="50"]
```

**Customer Completed Orders:**
```
[wpss_my_orders type="customer" status="completed"]
```

---

### [wpss_order_details]

Display details for a specific order.

**Basic Usage:**
```
[wpss_order_details]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `order_id` | number | (from URL) | Order ID to display |

**Note:** If no attribute provided, checks `$_GET['order_id']` from URL. User must be the customer, vendor, or admin to view.

**Requirements:**
- User must be logged in
- User must have permission to view order

**Example:**
```
[wpss_order_details]
```
(Typically used on a dedicated page; order ID comes from URL parameter)

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
| `redirect` | string | home_url() | Redirect URL after successful login |

**Note:** Uses WordPress core `wp_login_form()` function. Shows "already logged in" message if user is logged in.

**Examples:**

**Simple Login Form:**
```
[wpss_login]
```

**Login with Dashboard Redirect:**
```
[wpss_login redirect="/dashboard/"]
```

**Login with Custom Redirect:**
```
[wpss_login redirect="/my-services/"]
```

---

### [wpss_register]

Display registration form.

**Basic Usage:**
```
[wpss_register]
```

**Attributes:**

This shortcode accepts no attributes. The form includes:
- Username (required)
- Email (required)
- Password (required, min 8 chars)

**Requirements:**
- User registration must be enabled in WordPress (Settings → General)
- Shows "already logged in" message if user is logged in
- Includes link to login page

**Example:**
```
[wpss_register]
```

---

## Common Page Setups

### Homepage

```
# Welcome to Our Marketplace

[wpss_service_search]

## Featured Services
[wpss_featured_services limit="8" columns="4"]

## Browse by Category
[wpss_service_categories columns="4"]

## Top Services
[wpss_services orderby="rating" limit="6" columns="3"]
```

---

### Services Catalog Page

```
# Browse All Services

[wpss_service_search show_categories="true"]

[wpss_services limit="12" columns="3" orderby="date"]
```

---

### Vendor Directory Page

```
# Our Vendors

[wpss_top_vendors limit="3"]

## All Vendors
[wpss_vendors columns="4" orderby="rating" limit="12"]
```

---

### Buyer Requests Page

```
# Project Requests

[wpss_post_request]

## Active Requests
[wpss_buyer_requests limit="15"]
```

---

### My Orders Page

```
# My Orders

[wpss_my_orders type="customer" limit="20"]
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
[wpss_service_search show_categories="false"]
```

---

## Using Shortcodes in PHP

Execute shortcodes in theme templates:

```php
<?php echo do_shortcode('[wpss_services limit="6" columns="3"]'); ?>
```

Pass dynamic values:

```php
<?php
$category_id = get_queried_object_id();
echo do_shortcode("[wpss_services category='{$category_id}' limit='9']");
?>
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
[wpss_vendors orderby="rating" limit="8" columns="4"]

## Latest Services
[wpss_services category="design" limit="9" columns="3"]
```

---

## Shortcode Not Working?

**Troubleshooting:**

1. Check spelling (shortcodes are case-sensitive)
2. Ensure page is published (not draft)
3. Clear cache (site and browser)
4. Verify plugin is active
5. Check for PHP errors in debug log
6. Test in default WordPress theme
7. Disable other plugins temporarily
8. Verify services/vendors exist in database

**Common Issues:**

- **No output:** Check if there's matching content (services, vendors, etc.)
- **Wrong attributes:** Review exact attribute names above
- **Styling issues:** May need custom CSS for your theme

---

## Related Documentation

- [Gutenberg Blocks](gutenberg-blocks.md) - Block editor alternatives
- [Pages Setup](../platform-settings/pages-setup.md) - Required page configuration
- [Template Overrides](template-overrides.md) - Custom page templates
- [Search Filters](search-filters.md) - Advanced search options

---

## Next Steps

1. Create pages with shortcodes
2. Test each shortcode functionality
3. Customize with available attributes
4. Style with your theme's CSS
5. Combine shortcodes for rich layouts
