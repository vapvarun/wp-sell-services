# Block Editor Elements

WP Sell Services includes 6 drag-and-drop blocks for the WordPress block editor. Build your marketplace pages visually -- no shortcode syntax needed.

---

## Available Blocks

All blocks appear under the **WP Sell Services** category in the block inserter. Click the **+** button in the editor, search for any block by name, and drop it onto your page.

---

### Service Grid

Display a grid of services with visual controls for layout, filtering, and sorting.

**What you can configure:**
- Services per page (3 to 24, default 9)
- Grid columns (2 to 5, default 3)
- Filter by category, or show featured only
- Sort by date, title, menu order, or random
- Show or hide pagination, rating, price, and seller

**Best for:** Service showcase pages, category-specific displays, homepage service sections.

To filter by **tag** or by a specific **vendor**, use the
[`[wpss_services]` shortcode](shortcodes-reference.md#wpss_services----services-catalog)
instead -- the block exposes category only.

---

### Service Search Bar

Add a search form with keyword input and category dropdown.

**What you can configure:**
- Placeholder text
- Show or hide category filter
- Button text
- Custom results page URL

**Best for:** Homepage hero sections, top of service directory pages, sidebar widgets.

---

### Service Categories

Show your service categories in a visual grid with icons and service counts.

**What you can configure:**
- Grid columns
- Show or hide service count
- Filter by parent category
- Hide empty categories
- Maximum categories to display

**Best for:** Homepage category sections, browse-by-category pages, landing pages.

---

### Featured Services

Highlight services you have marked as featured in a grid layout.

**What you can configure:**
- Number of featured services
- Grid columns
- Category filter
- Sort order

**Best for:** Homepage spotlights, promotional sections, editor's picks.

---

### Seller Card

Display a vendor's profile information -- name, avatar, rating, and bio.

**What you can configure:**
- Select a specific vendor by ID
- Or auto-detect from the page context (URL parameter)

**Best for:** Vendor spotlight pages, team profiles, featured vendor sections.

---

### Buyer Requests

Show active buyer requests with filtering options.

**What you can configure:**
- Number of requests to display
- Category filter
- Budget range filters

**Best for:** Buyer request marketplace pages, vendor opportunity sections, project listing pages.

---

## How to Add a Block

1. Edit any page or post in the WordPress block editor
2. Click the **+** button to open the block inserter
3. Search for "WP Sell Services" or the specific block name (e.g., "Service Grid")
4. Click the block to insert it
5. Use the sidebar panel to configure settings
6. Preview your page to see the result

---

## Blocks vs Page Elements

Blocks and the page elements described in the [shortcodes reference](shortcodes-reference.md) produce the same output. The difference is how you add them:

- **Blocks** give you a visual editing experience with a settings panel in the sidebar. Great for content editors and non-technical users.
- **Page element tags** are faster to type for experienced users and work in widgets, the classic editor, and template files.

You can mix both on the same site -- use blocks on some pages and tags on others.

---

## Troubleshooting

**Block not appearing in the inserter?**
Make sure the plugin is active and you are using the block editor (not the Classic Editor plugin). Try clearing your browser cache and refreshing the page.

**Block shows nothing on the frontend?**
Check that matching content exists (published services, registered vendors, etc.) and that all block settings are filled in. Clear your site cache.

**Block settings not saving?**
Update to the latest plugin version, disable other plugins temporarily to check for conflicts, and check the browser console for JavaScript errors.

---

## Attribute reference (for developers)

Block names and their attributes, as registered. Useful when inserting blocks
programmatically, building patterns, or setting defaults in `theme.json`.

### `wpss/service-grid`

| Attribute | Type | Default | Range / values |
|-----------|------|---------|----------------|
| `columns` | number | `3` | 2-5 |
| `perPage` | number | `9` | 3-24 |
| `category` | number | `0` | term id, `0` = all |
| `orderBy` | string | `date` | `date`, `title`, `menu_order`, `rand` |
| `order` | string | `DESC` | `ASC`, `DESC` |
| `featured` | boolean | `false` | |
| `showPagination` | boolean | `true` | |
| `showRating` | boolean | `true` | |
| `showPrice` | boolean | `true` | |
| `showSeller` | boolean | `true` | |

### `wpss/service-search`

| Attribute | Type | Default | Values |
|-----------|------|---------|--------|
| `placeholder` | string | *(empty)* | |
| `buttonText` | string | *(empty)* | |
| `showCategoryFilter` | boolean | `true` | |
| `style` | string | `default` | `default`, `hero`, `minimal` |

### `wpss/service-categories`

| Attribute | Type | Default | Range / values |
|-----------|------|---------|----------------|
| `layout` | string | `grid` | `grid`, `list` |
| `columns` | number | `4` | 2-6 |
| `maxItems` | number | `8` | 2-20 |
| `orderBy` | string | `name` | |
| `order` | string | `ASC` | `ASC`, `DESC` |
| `showCount` | boolean | `true` | |
| `showIcon` | boolean | `true` | |
| `showImage` | boolean | `false` | |
| `hideEmpty` | boolean | `true` | |
| `parentOnly` | boolean | `false` | |

### `wpss/featured-services`

| Attribute | Type | Default | Range / values |
|-----------|------|---------|----------------|
| `layout` | string | `carousel` | `carousel`, `grid` |
| `columns` | number | `4` | 2-5 |
| `limit` | number | `8` | 2-16 |
| `title` | string | *(empty)* | |
| `autoplay` | boolean | `true` | carousel only |
| `interval` | number | `5000` | 2000-10000, step 500 |
| `showDots` | boolean | `true` | carousel only |
| `showArrows` | boolean | `true` | carousel only |
| `showRating` | boolean | `true` | |
| `showPrice` | boolean | `true` | |

### `wpss/seller-card`

| Attribute | Type | Default | Values |
|-----------|------|---------|--------|
| `userId` | number | `0` | `0` = current user |
| `layout` | string | `vertical` | `vertical`, `horizontal` |
| `showBio` | boolean | `true` | |
| `showStats` | boolean | `true` | |
| `showRating` | boolean | `true` | |
| `showServices` | boolean | `true` | |
| `showButton` | boolean | `true` | |

### `wpss/buyer-requests`

| Attribute | Type | Default | Range / values |
|-----------|------|---------|----------------|
| `perPage` | number | `10` | 3-20 |
| `category` | number | `0` | term id, `0` = all |
| `orderBy` | string | `date` | `date`, `title` |
| `order` | string | `DESC` | `ASC`, `DESC` |
| `layout` | string | `list` | |
| `showPagination` | boolean | `true` | |
| `showBudget` | boolean | `true` | |
| `showDeadline` | boolean | `true` | |
| `showOffers` | boolean | `true` | |

All six blocks render server-side, so the front end always reflects current data
rather than what was saved into the post content.

## Related

- [Shortcodes Reference](shortcodes-reference.md) -- the equivalent shortcodes, with extra options
- [Template Overrides](template-overrides.md)
