# Gutenberg Blocks

WP Sell Services provides 6 custom Gutenberg blocks for building marketplace pages with the WordPress block editor.

## Available Blocks

| Block Name | Description | Shortcode Equivalent |
|------------|-------------|---------------------|
| **Service Grid** | Display services in a grid | `[wpss_services]` |
| **Service Search** | Search form with filters | `[wpss_service_search]` |
| **Service Categories** | Category directory | `[wpss_service_categories]` |
| **Featured Services** | Featured services grid | `[wpss_featured_services]` |
| **Seller Card** | Vendor profile display | `[wpss_vendor_profile]` |
| **Buyer Requests** | Request listings | `[wpss_buyer_requests]` |

---

## Accessing Blocks

### In Block Editor

1. Edit a page or post
2. Click the **+** button to add a block
3. Search for "WP Sell Services" or specific block name
4. Click to insert block
5. Configure block settings in the sidebar

**Block Category:**
All blocks are grouped under **WP Sell Services** category in the block inserter.

---

## Service Grid Block

Display services in a customizable grid layout.

**What It Does:**
Renders the services grid shortcode (`[wpss_services]`) with visual controls in the block editor.

**Block Settings:**

The block includes configuration options for:
- Column layout
- Number of items
- Category filter
- Tag filter
- Ordering options

**Equivalent Shortcode:**
```
[wpss_services category="5" limit="12" columns="4" orderby="rating"]
```

**Use Cases:**
- Service showcase pages
- Category-specific service displays
- Vendor portfolio pages
- Homepage service sections

---

## Service Search Block

Add a search form with filters and autocomplete.

**What It Does:**
Renders the service search shortcode (`[wpss_service_search]`) with placeholder and button customization.

**Block Settings:**

Configuration options include:
- Placeholder text
- Show/hide category filter
- Button text customization
- Form action URL

**Equivalent Shortcode:**
```
[wpss_service_search placeholder="Find services..." show_categories="true"]
```

**Use Cases:**
- Homepage hero search
- Service directory pages
- Widget areas
- Landing pages

---

## Service Categories Block

Display service categories with icons and counts.

**What It Does:**
Renders the category grid shortcode (`[wpss_service_categories]`) with configurable layout.

**Block Settings:**

Options include:
- Grid columns
- Show/hide service count
- Parent category filter
- Hide empty categories
- Category limit

**Equivalent Shortcode:**
```
[wpss_service_categories columns="4" show_count="true" limit="12"]
```

**Use Cases:**
- Category browse pages
- Homepage category sections
- Service navigation
- Landing pages

---

## Featured Services Block

Display featured services in a grid.

**What It Does:**
Renders featured services shortcode (`[wpss_featured_services]`) which delegates to the services grid with `featured="true"`.

**Block Settings:**

Configuration options:
- Number of services
- Column layout
- Category filter
- Ordering options

**Equivalent Shortcode:**
```
[wpss_featured_services limit="8" columns="4"]
```

**Use Cases:**
- Homepage featured sections
- Premium service showcases
- Promotional pages
- Editor's picks sections

---

## Seller Card Block

Display vendor profile information.

**What It Does:**
Renders a vendor profile shortcode (`[wpss_vendor_profile]`) with vendor selection.

**Block Settings:**

Configuration includes:
- Vendor ID selection
- Auto-detect from context

**Equivalent Shortcode:**
```
[wpss_vendor_profile id="42"]
```

**Use Cases:**
- Vendor spotlight pages
- Team member profiles
- Featured vendor sections
- About pages

---

## Buyer Requests Block

Display active buyer requests with filtering.

**What It Does:**
Renders buyer requests shortcode (`[wpss_buyer_requests]`) with budget and category filters.

**Block Settings:**

Options include:
- Number of requests
- Category filter
- Budget min/max filters

**Equivalent Shortcode:**
```
[wpss_buyer_requests limit="10" category="5"]
```

**Use Cases:**
- Buyer request marketplace pages
- Project listing pages
- Vendor opportunity sections
- Dashboard pages

---

## Block Editor Assets

### JavaScript Bundle

**File:** `assets/js/blocks.js`

**Dependencies:**
- `wp-blocks` - Block registration
- `wp-element` - React components
- `wp-editor` - Editor utilities
- `wp-components` - UI components
- `wp-i18n` - Internationalization
- `wp-block-editor` - Block editor API

**Localized Data:**

The `wpssBlocks` JavaScript object provides:
- `pluginUrl` - Plugin directory URL
- `ajaxUrl` - WordPress AJAX URL
- `nonce` - Security nonce
- `categories` - Available service categories
- `i18n` - Translated strings

### Editor Styles

**File:** `assets/css/blocks-editor.css`

Styles for block appearance in the editor.

### Frontend Styles

**File:** `assets/css/blocks.css`

Styles for block appearance on the frontend. Only loaded when blocks are present on the page.

---

## Blocks vs Shortcodes

### When to Use Blocks

**Advantages:**
- Visual editing with preview
- No shortcode syntax to remember
- Sidebar configuration
- Better for content creators
- Theme integration

**Best For:**
- Content editors
- Marketing pages
- Visual page building
- Non-technical users

### When to Use Shortcodes

**Advantages:**
- Faster for experienced users
- Works in widgets
- Works in classic editor
- Dynamic content
- Programmatic insertion

**Best For:**
- Developers
- Widget areas
- Template files
- Legacy sites
- PHP code

**You Can Mix Both:** Use blocks in pages and shortcodes in widgets/templates.

---

## Block Configuration in Code

### Block Classes

Located in `src/Blocks/`:
- `ServiceGrid.php`
- `ServiceSearch.php`
- `ServiceCategories.php`
- `FeaturedServices.php`
- `SellerCard.php`
- `BuyerRequests.php`

Each block extends an `AbstractBlock` base class (assumed pattern).

### Registration

Blocks are registered in `BlocksManager.php`:

```php
$block_classes = [
    ServiceGrid::class,
    ServiceSearch::class,
    ServiceCategories::class,
    FeaturedServices::class,
    SellerCard::class,
    BuyerRequests::class,
];
```

### Block Category

All blocks appear under the **WP Sell Services** category:

```php
[
    'slug'  => 'wp-sell-services',
    'title' => __( 'WP Sell Services', 'wp-sell-services' ),
    'icon'  => 'store',
]
```

---

## Customizing Block Output

### Filter Block HTML

Blocks typically render shortcodes, so you can filter the shortcode output:

```php
// Filter service grid output
add_filter( 'wpss_services_shortcode_output', function( $output, $atts ) {
    // Modify HTML before display
    return $output;
}, 10, 2 );
```

### Custom Block Styles

Add custom CSS targeting block classes:

```css
/* Service Grid Block */
.wp-block-wpss-service-grid {
    /* Custom styles */
}

/* Service Search Block */
.wp-block-wpss-service-search {
    /* Custom styles */
}
```

---

## Block Assets Enqueueing

### Editor Assets

Enqueued via `enqueue_block_editor_assets` action:
- Only loads in block editor
- Includes localized data for categories and translations

### Frontend Assets

Enqueued via `enqueue_block_assets` action:
- Only loads on frontend (not admin)
- Only when blocks are used on page
- Includes jQuery dependency for frontend interactions

---

## Troubleshooting

### Block Not Appearing in Inserter

**Check:**
1. Plugin is active and up to date
2. Gutenberg/Block editor enabled (not Classic Editor)
3. Clear browser cache and refresh
4. Check JavaScript console for errors
5. Verify `assets/js/blocks.js` exists and loads

### Block Not Rendering on Frontend

**Verify:**
1. Page is published (not draft)
2. Block settings are complete
3. Data exists (services, vendors, etc.)
4. Cache cleared (site and browser)
5. Frontend CSS/JS files load correctly

### Block Settings Not Saving

**Troubleshoot:**
1. Update to latest plugin version
2. Check WordPress/user permissions
3. Disable other plugins temporarily
4. Try default WordPress theme
5. Check browser console for JavaScript errors

---

## Related Documentation

- [Shortcodes Reference](shortcodes-reference.md) - Shortcode alternatives
- [Template Overrides](template-overrides.md) - Custom templates
- [Pages Setup](../platform-settings/pages-setup.md) - Required pages
- [Search Filters](search-filters.md) - Search functionality

---

## Next Steps

1. Explore each block in the editor
2. Create page layouts with multiple blocks
3. Test responsive display on mobile
4. Customize block styles with your theme CSS
5. Consider creating reusable blocks for repeated sections
