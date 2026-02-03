# Search and Filters

Comprehensive service search functionality with category filters, price ranges, ratings, delivery times, and keyword search.

## Search Overview

WP Sell Services provides powerful search and filtering to help buyers find the perfect service quickly.

![Service Search Filters](../images/frontend-search-filters.png)

## Service Search Features

### Keyword Search

Full-text search across service titles, descriptions, and tags.

**What Gets Searched:**
- Service title
- Service description
- Service tags
- Vendor name (optional)
- Category names (optional)

**Search Behavior:**
- **AND logic** - All keywords must match
- **Partial matching** - Finds "design" in "designer"
- **Relevance scoring** - Best matches first
- **Highlighting** - Search terms highlighted in results

**Using Keyword Search:**
1. Enter search term in search box
2. Click search or press Enter
3. Results display matching services
4. Refine with additional filters

### Autocomplete Suggestions

Live search suggestions as you type.

**Features:**
- Suggests services while typing
- Shows service thumbnails
- Displays starting price
- Shows vendor name
- Click suggestion to go directly to service

**Enable Autocomplete:**
```
[wpss_service_search autocomplete="yes"]
```

**Customization:**
- Minimum characters to trigger: 3 (configurable)
- Maximum suggestions shown: 10 (configurable)
- Delay before searching: 300ms (prevents excessive requests)

## Filter Options

### Category Filter

Filter services by one or multiple categories.

**Single Category:**
- Dropdown selector
- Shows all available categories
- Displays service count per category
- Updates results instantly

**Multiple Categories:**
- Checkbox list
- Select multiple categories
- Results match ANY selected category
- Clear all button

**Subcategory Filtering:**
- Select parent category first
- Subcategories appear below
- Drill down into specific niches

**Example URL:**
```
/services/?category=logo-design
/services/?category=graphic-design&subcategory=logo-design
```

### Tag Filter

Filter by service tags for more specific results.

**Tag Display:**
- Popular tags shown as buttons
- Click to filter by tag
- Multiple tags = AND logic (must have all tags)
- Tag cloud shows tag popularity

**Example URL:**
```
/services/?tag=minimalist
/services/?tag=minimalist,modern,corporate
```

### Price Range Filter

Filter services by budget using a dual-handle slider.

**Price Slider:**
- Minimum price handle
- Maximum price handle
- Live price display
- Currency-aware formatting

**Configuration:**
1. Drag minimum price handle
2. Drag maximum price handle
3. Results update automatically
4. Clear filter to reset

**Example:**
- Minimum: $50
- Maximum: $200
- Shows all services priced $50-$200 (starting price)

**Example URL:**
```
/services/?min_price=50&max_price=200
```

**Considerations:**
- Filters based on starting price (lowest package)
- Services with multiple packages show if ANY package matches
- Free services: $0 minimum

### Rating Filter

Filter by minimum service rating.

**Rating Options:**
- 5 stars only
- 4 stars and up
- 3 stars and up
- 2 stars and up
- Any rating

**Display:**
- Star icons for visual clarity
- Review count shown
- Filters services with sufficient reviews

**Minimum Review Requirement:**
Services need at least 5 reviews to appear in rating filters (configurable).

**Example URL:**
```
/services/?min_rating=4
```

### Delivery Time Filter

Filter by maximum delivery time.

**Time Options:**
- Express: 24 hours
- Fast: 1-3 days
- Standard: 4-7 days
- Flexible: 7+ days

**Custom Time Range:**
- Minimum delivery days
- Maximum delivery days
- Slider control

**Example URL:**
```
/services/?delivery=express
/services/?max_delivery=3
```

**How It Works:**
- Checks fastest package delivery time
- Service appears if ANY package meets criteria
- Excludes services without delivery time specified

### Vendor Filter (Pro)

**[PRO]** Filter by vendor attributes.

**Vendor Filters:**
- Verified vendors only
- Top-rated vendors (4.5+ stars)
- Fast responders (responds within 1 hour)
- Pro level vendors
- New vendors (joined within 30 days)

**Example:**
```
[wpss_services vendor_verified="yes" vendor_level="pro"]
```

## Combined Filtering

Use multiple filters together for precise results.

### Filter Combinations

**Example 1: Logo Design under $100, Fast Delivery**
- Category: Logo Design
- Max Price: $100
- Delivery: 1-3 days
- Results: Affordable quick logo designers

**Example 2: Top-Rated Web Developers**
- Category: Web Development
- Rating: 4+ stars
- Vendor: Verified
- Results: Trusted web development experts

**Example 3: Premium Design Services**
- Category: Graphic Design
- Min Price: $500
- Rating: 5 stars
- Results: Premium design professionals

### Filter Logic

**AND Logic (Default):**
All selected filters must match:
- Category = Logo Design
- AND Price <= $100
- AND Delivery <= 3 days

**OR Logic (Categories/Tags):**
Multiple categories/tags use OR:
- Category = Logo Design OR Branding
- Tag = Modern OR Minimalist

## Search Results Display

### Results Layout

**Grid View:**
- 2-4 columns (responsive)
- Service card with image
- Title, price, rating, vendor
- Hover effects

**List View:**
- Full-width rows
- Larger images
- Extended description preview
- More details visible

**Toggle View:**
Users can switch between Grid and List views with icon buttons.

### Sorting Options

Order search results by:

| Sort Option | Description |
|-------------|-------------|
| **Relevance** | Best match to search query (default for keyword search) |
| **Newest** | Most recently published services |
| **Popular** | Most orders/views |
| **Price: Low to High** | Cheapest services first |
| **Price: High to Low** | Most expensive services first |
| **Rating: High to Low** | Highest rated services first |
| **Delivery: Fast to Slow** | Quickest delivery first |

**Default Sort:**
- Keyword search: Relevance
- Category browse: Newest
- No search: Popular

### Results Count

Display shows:
- Total matching services
- Current page range
- Filter summary

**Example:**
```
Showing 1-12 of 47 services in "Logo Design" under $100
```

### Pagination

Navigate through results:
- Previous/Next buttons
- Page numbers (1, 2, 3...)
- Jump to specific page
- "Load More" button option (AJAX)

**URL-Based Pagination:**
```
/services/?category=logo-design&page=2
```

## AJAX-Powered Live Search

Search and filtering without page reload.

### AJAX Features

**Instant Updates:**
- Filter changes update results immediately
- No page refresh required
- Smooth transitions
- Loading indicators

**URL Updates:**
- Browser URL changes with filters
- Shareable filter URLs
- Back button works correctly
- Bookmark filtered results

**Performance:**
- Debounced search (waits for user to stop typing)
- Cached results
- Lazy loading images
- Progressive loading for large result sets

### Enable/Disable AJAX

Control AJAX behavior:

**Enable AJAX Search:**
```php
// In functions.php
add_filter( 'wpss_enable_ajax_search', '__return_true' );
```

**Disable AJAX Search:**
```php
add_filter( 'wpss_enable_ajax_search', '__return_false' );
```

## Search Form Shortcodes

### Basic Search Form

```
[wpss_service_search]
```

### Search with All Filters

```
[wpss_service_search show_filters="yes"]
```

### Minimal Search Box

```
[wpss_service_search show_filters="no" show_button="yes"]
```

### Custom Placeholder

```
[wpss_service_search placeholder="What service do you need today?"]
```

### Search with Custom Results Page

```
[wpss_service_search redirect="/search-results/"]
```

## Customizing Search Behavior

### Search Configuration

Go to **WP Sell Services → Settings → Search**:

**Search Options:**
- Enable/disable autocomplete
- Minimum characters for autocomplete
- Maximum autocomplete suggestions
- Search delay (milliseconds)
- Include vendor names in search
- Include category names in search

**Filter Options:**
- Default filters shown
- Filter collapse on mobile
- Filter position (sidebar or top)
- Sticky filters on scroll

**Results Options:**
- Services per page (default: 12)
- Default view (grid or list)
- Default sort order
- Show result count
- Show active filters summary

## Search Widget

Add search to any widget area.

### Widget Setup

1. Go to **Appearance → Widgets**
2. Add **WP Sell Services Search** widget
3. Configure options:
   - Title
   - Show filters
   - Compact mode
4. Save widget

**Widget Options:**
- Show/hide category filter
- Show/hide price filter
- Compact/full layout

## Advanced Search (Pro)

**[PRO]** Enhanced search features for power users.

### Saved Searches

**[PRO]** Save favorite search criteria:

1. Configure filters
2. Click "Save Search"
3. Name your search
4. Access from dashboard
5. Receive notifications for new matches

**Example Saved Searches:**
- "Affordable Logo Designers"
- "Premium Web Developers"
- "Quick Turnaround Writers"

### Search Alerts

**[PRO]** Get notified of new services matching your criteria:

1. Create search
2. Enable "Alert Me"
3. Choose frequency (instant, daily, weekly)
4. Receive email notifications
5. Manage alerts in dashboard

### Location-Based Search

**[PRO]** Filter by vendor location:

- Country filter
- State/Province filter
- City filter
- Radius search (within X miles)

**Example:**
```
[wpss_services vendor_country="US" vendor_state="CA"]
```

### Availability Filter

**[PRO]** Filter by vendor availability:

- Available now
- Available this week
- Busy (long queue)

## SEO Considerations

### Search-Friendly URLs

Clean, readable URLs for search results:

**Good:**
```
/services/logo-design/?max_price=100&delivery=express
```

**Not This:**
```
/?s=logo&cat=5&p1=0&p2=100
```

### Meta Tags

Search result pages include proper meta tags:
- Dynamic title: "Logo Design Services Under $100 | [Site Name]"
- Dynamic description: "Browse X affordable logo design services..."
- Canonical URL for filtered pages
- No-index for empty results

### Sitemap Integration

Category and tag archives included in XML sitemap:
- Service category pages
- Popular tag pages
- Excludes filtered/paginated duplicates

## Search Analytics (Pro)

**[PRO]** Track what buyers search for.

### Search Reports

View in **WP Sell Services → Analytics → Search**:

**Metrics:**
- Top search keywords
- Most used filters
- Search-to-order conversion
- No-results searches (gaps in offerings)
- Average results per search

**Use Insights To:**
- Identify missing service categories
- Understand buyer needs
- Optimize service titles/descriptions
- Guide vendor recruitment

## Troubleshooting

### Search Returns No Results

**Check:**
1. Services are published (not drafts)
2. Services meet filter criteria
3. Search index is up to date
4. No conflicting filters (impossible combination)
5. Clear cache

### Autocomplete Not Working

**Verify:**
1. Autocomplete is enabled
2. JavaScript loads without errors (check console)
3. AJAX endpoint accessible
4. No JavaScript conflicts with other plugins
5. Try disabling other plugins

### Filters Not Updating Results

**Troubleshoot:**
1. Clear site cache
2. Check if AJAX is working (network tab)
3. Verify jQuery is loaded
4. Check for JavaScript errors
5. Test in different browser

### Slow Search Performance

**Optimize:**
1. Enable object caching (Redis, Memcached)
2. Reduce services per page
3. Enable lazy loading for images
4. Implement full-text search engine (Elasticsearch) **[PRO]**
5. Optimize database indexes

## Related Documentation

- [Shortcodes Reference](shortcodes-reference.md) - Search shortcode options
- [Gutenberg Blocks](gutenberg-blocks.md) - Search block
- [SEO Integration](../integrations/seo-integration.md) - Search SEO
- [Advanced Settings](../admin-settings/advanced-settings.md) - Search configuration

## Next Steps

1. Configure search settings for your needs
2. Test search with various keyword combinations
3. Set up saved searches (Pro)
4. Monitor search analytics to understand buyer behavior
5. Optimize service titles/tags based on popular searches
