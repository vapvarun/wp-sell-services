# Template Overrides

Customize the appearance of marketplace pages by overriding plugin templates in your theme.

## Template System Overview

WP Sell Services uses a template hierarchy similar to WordPress themes. Copy any template to your theme to customize it without losing changes during plugin updates.

---

## How Template Overrides Work

### Template Priority

WordPress checks for templates in this order:

1. **Child Theme:** `child-theme/wp-sell-services/{template}.php`
2. **Parent Theme:** `theme/wp-sell-services/{template}.php`
3. **Plugin:** `wp-sell-services/templates/{template}.php` (fallback)

When you create a template in your theme, it takes priority over the plugin's default.

### Update-Safe Customization

**Benefits:**
- Changes persist through plugin updates
- Clean separation of plugin code and custom design
- Easy to track customizations
- Version control friendly

---

## Available Templates

### Core Templates

Template files available for override:

**Service Templates:**
- `single-service.php` - Individual service page
- `archive-service.php` - Service archive/catalog
- `content-service-card.php` - Service card in listings

**Vendor Templates:**
- `vendor/profile.php` - Vendor profile page

**Order Templates:**
- `order/details.php` - Order details page

### Template Location

**Plugin Default:**
```
wp-sell-services/templates/{template}.php
```

**Theme Override:**
```
your-theme/wp-sell-services/{template}.php
```

---

## Creating Template Overrides

### Step-by-Step Process

**1. Create Directory Structure**

In your theme, create:
```
your-theme/
└── wp-sell-services/
    ├── single-service.php
    ├── archive-service.php
    ├── content-service-card.php
    ├── vendor/
    │   └── profile.php
    └── order/
        └── details.php
```

**2. Copy Template from Plugin**

Copy the file you want to customize:

**From:**
```
/wp-content/plugins/wp-sell-services/templates/single-service.php
```

**To:**
```
/wp-content/themes/your-theme/wp-sell-services/single-service.php
```

**3. Edit Template**

Open the theme version and make your changes. Your customizations are now update-safe.

**4. Test Changes**

1. Clear all caches
2. View the relevant page
3. Verify changes appear
4. Test on different devices

---

## Template Functions

Use these functions within templates to display data.

### Service Data Functions

```php
// Service information
$service_id = get_the_ID();
$price = get_post_meta( $service_id, '_wpss_starting_price', true );
$rating = get_post_meta( $service_id, '_wpss_rating_average', true );
$delivery = get_post_meta( $service_id, '_wpss_fastest_delivery', true );
$featured = get_post_meta( $service_id, '_wpss_is_featured', true );

// Display formatted price
if ( function_exists( 'wpss_format_currency' ) ) {
    echo wpss_format_currency( $price );
}
```

### Vendor Data Functions

```php
// Get vendor information
$vendor_id = get_the_author_meta( 'ID' );
$vendor = get_userdata( $vendor_id );

// Display avatar
echo get_avatar( $vendor_id, 80 );

// Display vendor name
echo esc_html( $vendor->display_name );
```

### Template Helper Functions

```php
// Check if template exists
$template = locate_template( 'wp-sell-services/single-service.php' );

// Get template part
wpss_get_template_part( 'content', 'service-card' );
```

---

## Example: Service Card Override

**Plugin Default:**
`templates/content-service-card.php`

**Theme Override:**
`your-theme/wp-sell-services/content-service-card.php`

```php
<?php
/**
 * Service Card Template Override
 */

$service_id = get_the_ID();
$price = get_post_meta( $service_id, '_wpss_starting_price', true );
$rating = get_post_meta( $service_id, '_wpss_rating_average', true );
$vendor = get_userdata( get_post_field( 'post_author', $service_id ) );
?>

<div class="custom-service-card">
    <?php if ( has_post_thumbnail() ) : ?>
        <a href="<?php the_permalink(); ?>" class="custom-thumbnail">
            <?php the_post_thumbnail( 'medium' ); ?>
        </a>
    <?php endif; ?>

    <div class="custom-service-info">
        <div class="custom-vendor">
            <?php echo get_avatar( $vendor->ID, 32 ); ?>
            <span><?php echo esc_html( $vendor->display_name ); ?></span>
        </div>

        <h3 class="custom-title">
            <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
        </h3>

        <div class="custom-meta">
            <?php if ( $rating ) : ?>
                <span class="rating"><?php echo esc_html( number_format( (float) $rating, 1 ) ); ?> ★</span>
            <?php endif; ?>

            <?php if ( $price ) : ?>
                <span class="price">
                    <?php esc_html_e( 'From', 'wp-sell-services' ); ?>
                    <?php echo esc_html( '$' . number_format( (float) $price, 2 ) ); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>
```

---

## Template Hooks

Add custom content via action hooks without overriding entire templates.

### Available Hooks

```php
// Service single page hooks
do_action( 'wpss_before_service_content' );
do_action( 'wpss_after_service_title' );
do_action( 'wpss_after_service_content' );
```

### Example Hook Usage

Add custom content after service title:

```php
// In your theme's functions.php
add_action( 'wpss_after_service_title', function() {
    echo '<div class="custom-badge">Verified Service</div>';
} );
```

---

## Loading Template Parts

### Using locate_template()

```php
// Check if template exists in theme
$template = locate_template( 'wp-sell-services/vendor/profile.php' );

if ( $template ) {
    include $template;
} else {
    // Use plugin default
    include WPSS_PLUGIN_DIR . 'templates/vendor/profile.php';
}
```

### Using wpss_get_template_part()

If the plugin provides this helper:

```php
// Load template part
wpss_get_template_part( 'content', 'service-card' );
```

---

## Troubleshooting

### Template Not Loading

**Check:**
1. File path is exactly `your-theme/wp-sell-services/{template}.php`
2. File name matches plugin template (case-sensitive)
3. Clear all caches (object cache, page cache, browser)
4. File permissions are correct (644 for files)
5. No PHP errors in debug log

### Changes Not Appearing

**Solutions:**
1. Clear cache (plugin, theme, hosting, CDN)
2. Hard refresh browser (Ctrl+Shift+R or Cmd+Shift+R)
3. Check correct template is loaded (add test comment)
4. Verify no inline styles overriding changes
5. Test in incognito/private browsing mode

### Missing Data or Errors

**Verify:**
1. Using correct WordPress functions
2. Variables exist before using (`isset()`, `empty()`)
3. No PHP syntax errors
4. Required plugin functions available
5. Check error log for specific messages

---

## Best Practices

### Do's

- Always copy entire template (don't edit plugin files)
- Keep templates updated with plugin changes
- Use hooks when possible (less maintenance)
- Test on different screen sizes
- Comment your changes
- Use child themes

### Don'ts

- Don't edit plugin templates directly
- Don't remove essential functionality
- Don't hardcode values (use functions)
- Don't skip testing
- Don't ignore error messages

---

## Related Documentation

- [Shortcodes Reference](shortcodes-reference.md) - Alternative to templates
- [Gutenberg Blocks](gutenberg-blocks.md) - Block-based customization
- [SEO Schema](seo-schema.md) - Schema markup
- [Search Filters](search-filters.md) - Search functionality

---

## Next Steps

1. Identify templates you want to customize
2. Copy templates to your theme
3. Make design changes
4. Test on frontend
5. Document your customizations
6. Check for updates after plugin updates
