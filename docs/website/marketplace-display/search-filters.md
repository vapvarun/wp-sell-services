# Search and Filters

Service search functionality with category filters, keyword search, and result pagination.

## Search Overview

WP Sell Services provides search and filtering to help buyers find services quickly.

---

## Service Search Form

### Using the Search Shortcode

```
[wpss_service_search]
```

**Features:**
- Keyword search input
- Category dropdown filter
- Submits to service archive page
- Clean, accessible HTML

### Search Form Attributes

| Attribute | Default | Description |
|-----------|---------|-------------|
| `placeholder` | "Search services..." | Input placeholder text |
| `show_categories` | true | Show category dropdown |
| `button_text` | "Search" | Submit button text |
| `action` | (services archive) | Form submission URL |

---

## Search Form Components

### Keyword Search Input

**What Gets Searched:**
- Service title
- Service content
- Service excerpt

Uses WordPress core search (`?s=query&post_type=wpss_service`).

### Category Filter

**Dropdown Options:**
- "All Categories" (default)
- Top-level categories only (parent = 0)
- Only shows categories with services (hide_empty = true)

Submits as `?service_category=slug` parameter.

### Search Button

Submits the form to the archive page with search parameters.

---

## Search Implementation

### Form Structure

```html
<form class="wpss-search-form" action="https://example.com/services/" method="get">
    <input type="text" name="s" value="" placeholder="Search services...">
    <input type="hidden" name="post_type" value="wpss_service">
    <select name="service_category">
        <option value="">All Categories</option>
        <!-- Categories populated from taxonomy -->
    </select>
    <button type="submit">Search</button>
</form>
```

### URL Parameters

**Keyword Search:**
```
/services/?s=logo&post_type=wpss_service
```

**Category Filter:**
```
/services/?service_category=graphic-design
```

**Combined:**
```
/services/?s=logo&post_type=wpss_service&service_category=graphic-design
```

---

## Service Archive Filtering

WordPress handles filtering via:
- `is_search()` for keyword queries
- `is_tax('wpss_service_category')` for category archives
- Standard WP_Query parameters

### Archive Query Arguments

Services are queried with:
- `post_type` = `wpss_service`
- `post_status` = `publish`
- `posts_per_page` = from settings or default (12)
- Taxonomy queries for categories/tags

---

## Pagination

WordPress core pagination on archive pages.

**URL Structure:**
```
/services/page/2/
/services/?s=logo&paged=2
```

**Display:**
- Previous/Next links
- Page numbers
- Respects permalink structure

---

## Custom Search Results

### Redirect to Custom Page

```
[wpss_service_search action="/custom-search-results/"]
```

On custom page, create your own WP_Query:

```php
$args = array(
    'post_type' => 'wpss_service',
    's' => get_query_var('s'),
    'posts_per_page' => 12,
);

$query = new WP_Query($args);
```

---

## Search Widget

The `[wpss_service_search]` shortcode works in widgets.

**Setup:**
1. Go to **Appearance → Widgets**
2. Add **Custom HTML** widget
3. Paste `[wpss_service_search show_categories="false"]`
4. Save

---

## Customizing Search

### Filter Search Form

```php
// Modify search form HTML before output
add_filter( 'wpss_search_form_html', function( $html ) {
    // Customize HTML
    return $html;
} );
```

### Modify Search Query

```php
// Adjust service search query
add_action( 'pre_get_posts', function( $query ) {
    if ( !is_admin() && $query->is_search() && $query->get('post_type') === 'wpss_service' ) {
        // Modify query
        $query->set( 'posts_per_page', 24 );
    }
} );
```

---

## Related Documentation

- [Shortcodes Reference](shortcodes-reference.md) - Search shortcode details
- [Gutenberg Blocks](gutenberg-blocks.md) - Search block
- [SEO Schema](seo-schema.md) - Search result SEO
- [Template Overrides](template-overrides.md) - Custom search templates

---

## Next Steps

1. Add search form to your homepage
2. Test keyword and category searches
3. Customize search results per page
4. Style search form with your theme CSS
5. Consider adding search to header/navigation
