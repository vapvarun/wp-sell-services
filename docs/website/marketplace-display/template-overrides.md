# Template Overrides

Customize the appearance of marketplace pages by overriding plugin templates in your theme.

## Template System Overview

WP Sell Services uses a template hierarchy similar to WordPress. You can override any template by copying it to your theme.

## How Template Overrides Work

### Template Priority

WordPress checks for templates in this order:

1. **Child Theme:** `child-theme/wp-sell-services/{template}.php`
2. **Parent Theme:** `theme/wp-sell-services/{template}.php`
3. **Plugin:** `wp-sell-services/templates/{template}.php` (fallback)

When you create a template in your theme, it takes priority over the plugin's default template.

### Update-Safe Customization

**Benefits:**
- Your customizations persist through plugin updates
- Plugin updates don't overwrite your changes
- Clean separation of plugin code and custom design
- Easy to track what you've customized

## Available Templates

### Service Templates

| Template | Purpose | Location |
|----------|---------|----------|
| `single-service.php` | Individual service page | `templates/single-service.php` |
| `archive-service.php` | Service catalog/archive | `templates/archive-service.php` |
| `content-service.php` | Service card in listings | `templates/partials/content-service.php` |
| `service-packages.php` | Pricing packages display | `templates/partials/service-packages.php` |
| `service-gallery.php` | Service image gallery | `templates/partials/service-gallery.php` |
| `service-reviews.php` | Service review section | `templates/partials/service-reviews.php` |
| `service-vendor.php` | Vendor info on service page | `templates/partials/service-vendor.php` |

### Buyer Request Templates

| Template | Purpose | Location |
|----------|---------|----------|
| `single-request.php` | Individual request page | `templates/single-request.php` |
| `archive-request.php` | Request listings | `templates/archive-request.php` |
| `content-request.php` | Request card in listings | `templates/partials/content-request.php` |
| `request-form.php` | Post request form | `templates/partials/request-form.php` |

### Dashboard Templates

| Template | Purpose | Location |
|----------|---------|----------|
| `dashboard/overview.php` | Dashboard overview page | `templates/dashboard/overview.php` |
| `dashboard/services.php` | Vendor service management | `templates/dashboard/services.php` |
| `dashboard/orders.php` | Order management | `templates/dashboard/orders.php` |
| `dashboard/earnings.php` | Vendor earnings page | `templates/dashboard/earnings.php` |
| `dashboard/profile.php` | Profile settings | `templates/dashboard/profile.php` |
| `dashboard/requests.php` | Buyer requests page | `templates/dashboard/requests.php` |
| `dashboard/messages.php` | Messaging interface | `templates/dashboard/messages.php` |
| `dashboard/nav.php` | Dashboard navigation | `templates/dashboard/nav.php` |

### Order Templates

| Template | Purpose | Location |
|----------|---------|----------|
| `order/order-details.php` | Order details page | `templates/order/order-details.php` |
| `order/order-requirements.php` | Order requirements form | `templates/order/order-requirements.php` |
| `order/order-delivery.php` | Delivery upload form | `templates/order/order-delivery.php` |
| `order/order-revision.php` | Revision request form | `templates/order/order-revision.php` |
| `order/order-messages.php` | Order messaging | `templates/order/order-messages.php` |

### Email Templates

| Template | Purpose | Location |
|----------|---------|----------|
| `emails/header.php` | Email header | `templates/emails/header.php` |
| `emails/footer.php` | Email footer | `templates/emails/footer.php` |
| `emails/new-order.php` | New order notification | `templates/emails/new-order.php` |
| `emails/order-delivered.php` | Delivery notification | `templates/emails/order-delivered.php` |
| `emails/order-completed.php` | Completion notification | `templates/emails/order-completed.php` |
| `emails/revision-requested.php` | Revision request email | `templates/emails/revision-requested.php` |

### Dispute Templates

| Template | Purpose | Location |
|----------|---------|----------|
| `disputes/dispute-form.php` | Open dispute form | `templates/disputes/dispute-form.php` |
| `disputes/dispute-details.php` | Dispute details page | `templates/disputes/dispute-details.php` |
| `disputes/dispute-messages.php` | Dispute messaging | `templates/disputes/dispute-messages.php` |

### Account Templates

| Template | Purpose | Location |
|----------|---------|----------|
| `myaccount/login.php` | Login form | `templates/myaccount/login.php` |
| `myaccount/register.php` | Registration form | `templates/myaccount/register.php` |
| `myaccount/forgot-password.php` | Password reset | `templates/myaccount/forgot-password.php` |
| `myaccount/vendor-profile.php` | Public vendor profile | `templates/myaccount/vendor-profile.php` |

### Partial Templates

| Template | Purpose | Location |
|----------|---------|----------|
| `partials/breadcrumbs.php` | Breadcrumb navigation | `templates/partials/breadcrumbs.php` |
| `partials/pagination.php` | Pagination links | `templates/partials/pagination.php` |
| `partials/rating-stars.php` | Star rating display | `templates/partials/rating-stars.php` |
| `partials/price.php` | Price formatting | `templates/partials/price.php` |
| `partials/vendor-badge.php` | Vendor verification badge | `templates/partials/vendor-badge.php` |

## Creating Template Overrides

### Step-by-Step Process

**1. Create Directory Structure**

In your theme, create:
```
your-theme/
└── wp-sell-services/
    ├── single-service.php
    ├── archive-service.php
    ├── dashboard/
    ├── order/
    ├── emails/
    └── partials/
```

**2. Copy Template from Plugin**

Copy the template you want to customize:

**From Plugin:**
```
wp-sell-services/templates/single-service.php
```

**To Theme:**
```
your-theme/wp-sell-services/single-service.php
```

**3. Edit Template in Theme**

Open the theme version and make your changes. Your customizations are now update-safe.

**4. Test Your Changes**

1. Clear cache (site and browser)
2. View the relevant page
3. Verify your changes appear
4. Test on different devices

### Example: Customizing Service Page

**Original Plugin Template:**
```php
<?php
/**
 * Single Service Template
 * Location: wp-sell-services/templates/single-service.php
 */

get_header();

while ( have_posts() ) : the_post();
    wpss_get_template_part( 'content', 'single-service' );
endwhile;

get_footer();
```

**Customized Theme Template:**
```php
<?php
/**
 * Custom Single Service Template
 * Location: your-theme/wp-sell-services/single-service.php
 */

get_header();
?>

<div class="custom-service-wrapper">
    <div class="custom-sidebar">
        <?php wpss_get_template_part( 'partials/service-vendor' ); ?>
        <?php wpss_get_template_part( 'partials/service-packages' ); ?>
    </div>

    <div class="custom-main-content">
        <?php
        while ( have_posts() ) : the_post();
            ?>
            <h1 class="custom-service-title"><?php the_title(); ?></h1>

            <div class="custom-service-meta">
                <?php wpss_service_rating(); ?>
                <?php wpss_service_order_count(); ?>
            </div>

            <div class="custom-service-gallery">
                <?php wpss_get_template_part( 'partials/service-gallery' ); ?>
            </div>

            <div class="custom-service-description">
                <?php the_content(); ?>
            </div>

            <div class="custom-service-reviews">
                <?php wpss_get_template_part( 'partials/service-reviews' ); ?>
            </div>
            <?php
        endwhile;
        ?>
    </div>
</div>

<?php get_footer(); ?>
```

## Template Functions

Use these functions within templates to display marketplace data.

### Service Functions

```php
// Get service data
$service_id = get_the_ID();
$service = wpss_get_service( $service_id );

// Display service price (starting from)
wpss_service_price();

// Display service rating
wpss_service_rating();

// Display vendor information
wpss_service_vendor();

// Display order count
wpss_service_order_count();

// Display delivery time
wpss_service_delivery_time();

// Check if service is featured
if ( wpss_is_featured_service() ) {
    echo '<span class="featured-badge">Featured</span>';
}

// Display add to favorites button
wpss_favorite_button();

// Display share buttons
wpss_share_buttons();
```

### Vendor Functions

```php
// Get vendor data
$vendor_id = get_the_author_meta( 'ID' );
$vendor = wpss_get_vendor( $vendor_id );

// Display vendor name
echo wpss_get_vendor_name( $vendor_id );

// Display vendor avatar
echo wpss_get_vendor_avatar( $vendor_id, 100 );

// Display vendor rating
wpss_vendor_rating( $vendor_id );

// Display vendor badge
wpss_vendor_badge( $vendor_id );

// Get vendor stats
$stats = wpss_get_vendor_stats( $vendor_id );
echo 'Orders: ' . $stats['orders'];
echo 'Rating: ' . $stats['rating'];
echo 'Response Time: ' . $stats['response_time'];
```

### Order Functions

```php
// Get order data
$order_id = $_GET['order_id'];
$order = wpss_get_order( $order_id );

// Display order status
wpss_order_status( $order_id );

// Display order timeline
wpss_order_timeline( $order_id );

// Display delivery countdown
wpss_delivery_countdown( $order_id );

// Check if user can review
if ( wpss_can_review_order( $order_id ) ) {
    wpss_review_form( $order_id );
}
```

### Helper Functions

```php
// Format price
echo wpss_format_price( 150.00 ); // Outputs: $150.00

// Format date
echo wpss_format_date( '2026-01-15' ); // Outputs: January 15, 2026

// Get service categories
$categories = wpss_get_service_categories();

// Get service tags
$tags = wpss_get_service_tags();

// Check user permissions
if ( wpss_is_vendor() ) {
    // User is a vendor
}

if ( wpss_is_buyer() ) {
    // User is a buyer
}
```

## Template Hooks

Add custom content at specific points using action hooks.

### Service Page Hooks

```php
// Before service content
do_action( 'wpss_before_service_content' );

// After service title
do_action( 'wpss_after_service_title' );

// Before service description
do_action( 'wpss_before_service_description' );

// After service description
do_action( 'wpss_after_service_description' );

// Before service packages
do_action( 'wpss_before_service_packages' );

// After service packages
do_action( 'wpss_after_service_packages' );

// Before service reviews
do_action( 'wpss_before_service_reviews' );

// After service reviews
do_action( 'wpss_after_service_reviews' );

// After service content
do_action( 'wpss_after_service_content' );
```

### Example Hook Usage

**Add Trust Badge After Service Title:**

```php
// In your theme's functions.php
add_action( 'wpss_after_service_title', 'custom_add_trust_badge' );

function custom_add_trust_badge() {
    if ( wpss_is_featured_service() ) {
        echo '<div class="trust-badge">
                <img src="' . get_stylesheet_directory_uri() . '/images/verified.svg" alt="Verified">
                <span>Verified Service</span>
              </div>';
    }
}
```

**Add Custom CTA Before Packages:**

```php
add_action( 'wpss_before_service_packages', 'custom_add_cta' );

function custom_add_cta() {
    echo '<div class="custom-cta">
            <h3>Ready to get started?</h3>
            <p>Choose a package below that fits your needs.</p>
          </div>';
}
```

## Template Loading Function

Load template parts programmatically.

### wpss_get_template_part()

```php
/**
 * Load a template part
 *
 * @param string $slug Template slug (e.g., 'partials/service-vendor')
 * @param string $name Template variation (optional)
 * @param array  $args Variables to pass to template
 */
wpss_get_template_part( $slug, $name = '', $args = array() );
```

**Examples:**

```php
// Load service vendor partial
wpss_get_template_part( 'partials/service-vendor' );

// Load with variation
wpss_get_template_part( 'content', 'service-grid' );

// Load with variables
wpss_get_template_part( 'partials/service-card', '', array(
    'service_id' => 123,
    'show_price' => true,
    'show_rating' => true,
) );
```

**Accessing Variables in Template:**

```php
<?php
// In your template file
extract( $args ); // Extracts variables from $args array

echo $service_id; // 123
echo $show_price; // true
?>
```

## Conditional Template Loading

Load different templates based on conditions.

### Context-Based Templates

```php
<?php
// In single-service.php

if ( wpss_is_digital_service() ) {
    wpss_get_template_part( 'partials/digital-service-info' );
} else {
    wpss_get_template_part( 'partials/service-info' );
}

// User-specific content
if ( wpss_is_vendor() && wpss_is_own_service() ) {
    wpss_get_template_part( 'partials/service-edit-link' );
}
?>
```

## Template Debugging

Enable template debugging to see which templates are loaded.

### Enable Debug Mode

Add to `wp-config.php`:

```php
define( 'WPSS_TEMPLATE_DEBUG', true );
```

**What It Shows:**
- Which template file is being loaded
- Template hierarchy checked
- Override location (if customized)
- Template variables available

**Debug Output Example:**
```
<!-- Template: single-service.php -->
<!-- Location: /wp-content/themes/your-theme/wp-sell-services/single-service.php -->
<!-- Type: Override (from theme) -->
```

## Best Practices

### Do's

✓ **Always copy entire template** - Don't edit plugin files directly
✓ **Keep templates updated** - Check for changes in plugin updates
✓ **Use hooks when possible** - Less code to maintain
✓ **Test thoroughly** - Verify on different screen sizes
✓ **Comment your changes** - Document why you customized
✓ **Use child themes** - Protect from theme updates too

### Don'ts

✗ **Don't edit plugin templates** - Updates will overwrite changes
✗ **Don't remove essential functions** - May break functionality
✗ **Don't hardcode values** - Use template functions instead
✗ **Don't skip testing** - Broken templates affect user experience
✗ **Don't ignore errors** - Check debug log for issues

## Troubleshooting

### Template Not Loading

**Check:**
1. File path is exactly `your-theme/wp-sell-services/{template}.php`
2. File name matches plugin template exactly (case-sensitive)
3. Clear all caches (site cache, browser cache, object cache)
4. Verify file permissions (644 for files, 755 for directories)
5. Check for PHP errors in debug log

### Changes Not Appearing

**Solutions:**
1. Clear cache (especially if using caching plugin)
2. Hard refresh browser (Ctrl+Shift+R or Cmd+Shift+R)
3. Check if correct template is being loaded (enable debug mode)
4. Verify no inline CSS overriding changes
5. Test in different browser/incognito mode

### Missing Data or Errors

**Verify:**
1. You're using correct template functions
2. Variables exist before using them (check with `isset()`)
3. No PHP syntax errors in custom template
4. Required plugin functions are available
5. Check error log for specific error messages

## Version Compatibility

### Checking for Template Changes

After plugin updates, check if templates changed:

1. Go to **WP Sell Services → System Status**
2. View **Outdated Templates** section
3. See list of templates with updates
4. Compare your customized version with new version
5. Update your template if needed

**Safe Update Process:**
1. Backup your customized template
2. Copy new template from plugin
3. Re-apply your customizations
4. Test thoroughly

## Related Documentation

- [Shortcodes Reference](shortcodes-reference.md) - Alternative to templates
- [Gutenberg Blocks](gutenberg-blocks.md) - Block-based customization
- [Advanced Settings](../admin-settings/advanced-settings.md) - Feature toggles
- [WooCommerce Setup](../integrations/woocommerce-setup.md) - E-commerce templates

## Next Steps

1. Identify templates you want to customize
2. Copy templates to your theme
3. Make design changes
4. Test on frontend
5. Document your customizations
6. Check for updates after plugin updates
