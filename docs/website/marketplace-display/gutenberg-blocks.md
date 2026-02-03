# Gutenberg Blocks

WP Sell Services provides 6 custom Gutenberg blocks for building marketplace pages with the WordPress block editor.

## Block Overview

Use blocks instead of shortcodes for visual page building with live previews.

### Available Blocks

| Block Name | Description | Shortcode Equivalent |
|------------|-------------|---------------------|
| **Service Grid** | Display services in a grid | `[wpss_services]` |
| **Service Search** | Search form with filters | `[wpss_service_search]` |
| **Featured Services** | Carousel of featured services | `[wpss_featured_services]` |
| **Service Categories** | Category directory | `[wpss_service_categories]` |
| **Seller Card** | Vendor profile display | `[wpss_vendor_profile]` |
| **Buyer Requests** | Request listings | `[wpss_buyer_requests]` |

## Accessing Blocks

### In Block Editor

1. Edit a page or post
2. Click the **+** button to add a block
3. Search for "WP Sell Services" or specific block name
4. Click to insert block
5. Configure block settings in the sidebar

**Block Category:**
All blocks are grouped under **WP Sell Services** category in the block inserter.

![Block Inserter](../images/frontend-block-inserter.png)

## Service Grid Block

Display services in a customizable grid layout.

### Block Settings

**Layout Tab:**
- **Columns:** 1-4 columns (default: 3)
- **Items per page:** Number of services (default: 12)
- **Pagination:** Enable/disable page navigation

**Filter Tab:**
- **Category:** Select specific category or "All"
- **Tag:** Filter by tag
- **Vendor:** Show services from specific vendor
- **Featured only:** Toggle to show only featured services

**Sorting Tab:**
- **Order by:** Date, title, price, rating, popularity
- **Order:** Descending or ascending

**Display Options:**
- **Show price:** Toggle price display
- **Show rating:** Toggle rating stars
- **Show vendor:** Toggle vendor name
- **Show excerpt:** Toggle service description

### Style Options

**Block Styles:**
- **Grid (default)** - Traditional grid layout
- **List** - Vertical list layout
- **Masonry** - Pinterest-style layout (Pro)

**Color Settings:**
- Background color
- Text color
- Link color
- Button color

**Spacing:**
- Padding (top, right, bottom, left)
- Margin (top, right, bottom, left)
- Gap between items

### Example Configuration

**Featured Design Services:**
1. Insert Service Grid block
2. **Columns:** 3
3. **Items per page:** 9
4. **Category:** Graphic Design
5. **Featured only:** Yes
6. **Order by:** Rating
7. **Show vendor:** Yes

![Service Grid Block](../images/frontend-block-service-grid.png)

---

## Service Search Block

Add a search form with filters and autocomplete.

### Block Settings

**Search Options:**
- **Placeholder text:** Customize input placeholder
- **Enable autocomplete:** Live search suggestions
- **Show search button:** Display submit button
- **Button text:** Customize button label

**Filter Options:**
- **Show category filter:** Dropdown category selector
- **Show price filter:** Min/max price range
- **Show rating filter:** Filter by star rating
- **Show delivery time filter:** Filter by turnaround time

**Display Options:**
- **Inline layout:** Horizontal form layout
- **Stacked layout:** Vertical form layout
- **Full width:** Expand to container width

### Style Options

**Colors:**
- Input background
- Input text color
- Button background
- Button text color
- Border color

**Dimensions:**
- Input height
- Border radius
- Button width

### Example Configuration

**Homepage Hero Search:**
1. Insert Service Search block
2. **Placeholder:** "What service do you need?"
3. **Enable autocomplete:** Yes
4. **Show filters:** Yes (category, price, rating)
5. **Layout:** Inline
6. **Full width:** Yes
7. **Button text:** "Search Services"

![Service Search Block](../images/frontend-block-search.png)

---

## Featured Services Block

Display featured services in an auto-rotating carousel.

### Block Settings

**Carousel Options:**
- **Number of items:** How many services to feature (default: 8)
- **Items to show:** Visible slides at once (1-4)
- **Autoplay:** Enable auto-rotation
- **Autoplay speed:** Milliseconds between slides (default: 3000)

**Navigation:**
- **Show arrows:** Prev/next navigation
- **Arrow position:** Inside or outside slides
- **Show dots:** Pagination dots
- **Dot position:** Top, bottom, left, right

**Display Options:**
- **Show service title:** Toggle title display
- **Show price:** Toggle price display
- **Show vendor:** Toggle vendor name
- **Show rating:** Toggle rating stars
- **Show excerpt:** Toggle description

### Style Options

**Carousel Styling:**
- **Slide height:** Fixed or auto height
- **Slide gap:** Space between slides
- **Border radius:** Rounded corners on slides

**Navigation Styling:**
- Arrow color
- Arrow background
- Dot color (active/inactive)

### Example Configuration

**Homepage Featured Carousel:**
1. Insert Featured Services block
2. **Number of items:** 10
3. **Items to show:** 3
4. **Autoplay:** Yes
5. **Autoplay speed:** 5000ms
6. **Show arrows:** Yes
7. **Show dots:** Yes
8. **Slide gap:** 20px

![Featured Services Block](../images/frontend-block-featured-carousel.png)

---

## Service Categories Block

Display service categories with icons and counts.

### Block Settings

**Layout Options:**
- **Display style:** Grid or List
- **Columns:** 2-6 columns (grid only)
- **Show icons:** Category icon display
- **Show count:** Service count per category
- **Show description:** Category description

**Filter Options:**
- **Parent category:** Show subcategories of specific parent
- **Hide empty:** Hide categories with no services
- **Exclude categories:** Comma-separated category IDs

**Sorting:**
- **Order by:** Name, count, custom order
- **Order:** Ascending or descending

### Style Options

**Category Card:**
- Background color
- Border color
- Border radius
- Padding

**Typography:**
- Title font size
- Title color
- Count font size
- Count color

**Icon Styling:**
- Icon size
- Icon color
- Icon background

### Example Configuration

**Category Directory:**
1. Insert Service Categories block
2. **Display style:** Grid
3. **Columns:** 4
4. **Show icons:** Yes
5. **Show count:** Yes
6. **Order by:** Count (most popular first)
7. **Hide empty:** Yes

![Service Categories Block](../images/frontend-block-categories.png)

---

## Seller Card Block

Display vendor profile information and services.

### Block Settings

**Vendor Selection:**
- **Vendor ID:** Select vendor from dropdown
- **Auto-detect:** Use current author on author pages

**Display Options:**
- **Show avatar:** Vendor profile picture
- **Show rating:** Average rating with stars
- **Show stats:** Order count, reviews, response time
- **Show bio:** About/description section
- **Show services:** List of vendor's services
- **Service count:** Number of services to display

**Layout:**
- **Card style:** Compact or expanded
- **Alignment:** Left, center, right

### Style Options

**Card Design:**
- Background color
- Border style
- Shadow depth
- Border radius

**Avatar:**
- Size (small, medium, large)
- Border radius (circle or rounded)

**Button:**
- "View Profile" button color
- Button text color
- Button hover effects

### Example Configuration

**Featured Vendor Widget:**
1. Insert Seller Card block
2. **Vendor ID:** Select top vendor
3. **Show avatar:** Yes
4. **Show rating:** Yes
5. **Show stats:** Yes
6. **Show services:** Yes (5 services)
7. **Card style:** Expanded
8. **Alignment:** Center

![Seller Card Block](../images/frontend-block-seller-card.png)

---

## Buyer Requests Block

Display active buyer requests with filtering.

### Block Settings

**Request Options:**
- **Requests per page:** Number to display (default: 20)
- **Show pagination:** Enable page navigation
- **Show filters:** Category and budget filters

**Filter Options:**
- **Category filter:** Filter by service category
- **Budget filter:** Min/max budget range
- **Show search:** Keyword search box

**Display Options:**
- **Show budget:** Display request budget
- **Show deadline:** Display project deadline
- **Show offer count:** Number of vendor offers
- **Show posted date:** When request was created

**Sorting:**
- **Order by:** Date, budget, offers
- **Order:** Newest or oldest first

### Style Options

**Request Card:**
- Background color
- Border color
- Hover effects
- Card padding

**Typography:**
- Title font size
- Meta font size
- Text color

**Button Styling:**
- "Send Offer" button color
- Button text color

### Example Configuration

**Requests Marketplace:**
1. Insert Buyer Requests block
2. **Requests per page:** 15
3. **Show pagination:** Yes
4. **Show filters:** Yes (category and budget)
5. **Show search:** Yes
6. **Order by:** Date (newest first)
7. **Display:** Budget, deadline, offer count

![Buyer Requests Block](../images/frontend-block-requests.png)

---

## Block Patterns

**[PRO]** Pre-designed block combinations for common layouts.

### Available Patterns

**Homepage Hero:**
- Large heading
- Service Search block
- Featured Services carousel

**Services Showcase:**
- Section heading
- Service Categories block
- Service Grid block

**Vendor Directory:**
- Directory heading
- Search/filter options
- Seller Card blocks in grid

**Request Marketplace:**
- Call-to-action heading
- Post Request button
- Buyer Requests block

### Using Block Patterns

1. Click **+** to add block
2. Select **Patterns** tab
3. Choose **WP Sell Services** category
4. Click pattern to insert
5. Customize blocks as needed

---

## Blocks vs Shortcodes

### When to Use Blocks

**Advantages:**
- Visual editing with live preview
- No shortcode syntax to remember
- Easy configuration via sidebar
- Better for non-technical users
- Integration with theme styles

**Best For:**
- Content creators
- Page builders
- Marketing pages
- Custom layouts

### When to Use Shortcodes

**Advantages:**
- Faster for experienced users
- Works in widgets
- Works in classic editor
- More control via attributes
- Better for dynamic content

**Best For:**
- Developers
- Widget areas
- Template files
- Legacy sites
- Programmatic insertion

**You Can Mix Both:** Use blocks in pages and shortcodes in widgets/templates.

---

## Block Settings Reference

### Common Settings (All Blocks)

**Advanced Tab:**
- **HTML Anchor:** Custom ID for links
- **Additional CSS Class:** Custom CSS classes
- **Hide on mobile:** Responsive visibility
- **Hide on tablet:** Responsive visibility
- **Hide on desktop:** Responsive visibility

**Typography (Block Styles):**
- Font family (if theme supports)
- Font size
- Line height
- Letter spacing
- Text transform

**Spacing:**
- Padding (per side or uniform)
- Margin (per side or uniform)

**Border:**
- Border width
- Border style (solid, dashed, dotted)
- Border color
- Border radius (rounded corners)

---

## Reusable Blocks

Create reusable marketplace sections.

### Creating Reusable Block

1. Configure a block with desired settings
2. Click **⋮** (more options) on block
3. Select **Add to Reusable blocks**
4. Name the block (e.g., "Featured Design Services")
5. Click **Save**

### Using Reusable Blocks

1. Click **+** to add block
2. Search for your reusable block name
3. Insert block
4. Changes to original affect all instances

**Use Cases:**
- Consistent service sections across pages
- Standardized vendor displays
- Repeated category showcases

---

## Block Customization with CSS

Target blocks with custom CSS for advanced styling.

### Block CSS Classes

Each block has a unique class:

```css
/* Service Grid Block */
.wpss-block-service-grid { }

/* Service Search Block */
.wpss-block-service-search { }

/* Featured Services Block */
.wpss-block-featured-services { }

/* Service Categories Block */
.wpss-block-service-categories { }

/* Seller Card Block */
.wpss-block-seller-card { }

/* Buyer Requests Block */
.wpss-block-buyer-requests { }
```

### Example Custom Styling

**Customizing Service Grid Cards:**

```css
.wpss-block-service-grid .service-card {
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: transform 0.3s ease;
}

.wpss-block-service-grid .service-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}
```

Add custom CSS in:
- **Appearance → Customize → Additional CSS**
- Theme stylesheet
- Custom CSS plugin

---

## Block Performance

### Optimization Tips

1. **Limit items displayed** - Don't show 100 services on one page
2. **Use pagination** - Enable pagination for large datasets
3. **Lazy load images** - Enable in [Advanced Settings](../admin-settings/advanced-settings.md)
4. **Cache blocks** - Use caching plugin
5. **Optimize images** - Compress service images

### Performance Monitoring

**[PRO]** Track block load times:
1. Go to **WP Sell Services → Performance**
2. View **Block Analytics** tab
3. See load times per block type
4. Identify slow-loading blocks
5. Optimize accordingly

---

## Troubleshooting

### Block Not Appearing in Inserter

**Check:**
1. Plugin is active and up to date
2. Gutenberg editor enabled (not Classic Editor)
3. Clear browser cache
4. Refresh page editor
5. Check JavaScript console for errors

### Block Not Rendering on Frontend

**Verify:**
1. Page is published (not draft)
2. Block settings are complete
3. Data exists (services, vendors, etc.)
4. No conflicting CSS hiding content
5. Cache cleared (site and browser)

### Block Settings Not Saving

**Troubleshoot:**
1. Update to latest plugin version
2. Check WordPress permissions
3. Disable other plugins temporarily
4. Try in default theme
5. Check browser console for errors

---

## Related Documentation

- [Shortcodes Reference](shortcodes-reference.md) - Shortcode alternatives
- [Template Overrides](template-overrides.md) - Custom templates
- [Pages Setup](../admin-settings/pages-setup.md) - Required pages
- [Search Filters](search-filters.md) - Search functionality

---

## Next Steps

1. Explore each block in the editor
2. Create page layouts with multiple blocks
3. Test responsive display on mobile
4. Customize block styles with your theme
5. Create reusable blocks for efficiency
