# Theme Integration Guide

Customize WP Sell Services appearance to match your WordPress theme through template overrides, CSS customization, and template hooks.

## Template Override System

The plugin uses `TemplateLoader` (`src/Frontend/TemplateLoader.php`) to load templates with theme override support. When a template is requested, the loader checks locations in this order:

1. **Child theme**: `wp-content/themes/child-theme/wp-sell-services/{template}.php`
2. **Parent theme**: `wp-content/themes/parent-theme/wp-sell-services/{template}.php`
3. **Plugin default**: `wp-content/plugins/wp-sell-services/templates/{template}.php`

### Setting Up Overrides

1. Create `wp-sell-services/` directory in your theme
2. Copy the template you want to customize from `wp-sell-services/templates/`
3. Edit the copied file in your theme directory
4. Clear page cache and refresh

Only copy templates you need to change. Uncopied templates continue using plugin defaults.

## Available Templates

### Top-Level Templates

| Template | Purpose |
|----------|---------|
| `single-service.php` | Single service page |
| `archive-service.php` | Service archive/listing page |
| `single-request.php` | Single buyer request page |
| `archive-request.php` | Buyer request archive page |
| `content-service-card.php` | Service card in grids and listings |
| `content-request-card.php` | Buyer request card in listings |
| `content-no-services.php` | Empty state when no services found |
| `content-no-requests.php` | Empty state when no requests found |

### Partial Templates (`templates/partials/`)

| Template | Purpose |
|----------|---------|
| `partials/service-gallery.php` | Image gallery on single service page |
| `partials/service-packages.php` | Pricing packages (Basic/Standard/Premium) |
| `partials/service-reviews.php` | Reviews section on single service page |
| `partials/service-faqs.php` | FAQ accordion on single service page |
| `partials/vendor-card.php` | Vendor info card on service page sidebar |
| `partials/vendor-portfolio.php` | Public vendor portfolio lightbox and grid |

### Order Templates (`templates/order/`)

| Template | Purpose |
|----------|---------|
| `order/order-view.php` | Order details page |
| `order/order-requirements.php` | Requirements submission page |
| `order/order-confirmation.php` | Order confirmation/thank you page |
| `order/requirements-form.php` | Requirements form template |
| `order/conversation.php` | Order messaging/conversation view |

Order URLs route as: `/service-order/{id}/` (view), `/service-order/{id}/requirements/`, `/service-order/{id}/delivery/`, `/service-order/{id}/review/`.

### Dashboard Templates (`templates/dashboard/sections/`)

| Template | Purpose |
|----------|---------|
| `dashboard/sections/orders.php` | Orders section |
| `dashboard/sections/sales.php` | Sales section (vendor) |
| `dashboard/sections/services.php` | Services management |
| `dashboard/sections/earnings.php` | Earnings section |
| `dashboard/sections/messages.php` | Messages section |
| `dashboard/sections/profile.php` | Profile editing |
| `dashboard/sections/requests.php` | Buyer requests |
| `dashboard/sections/create.php` | Service creation |
| `dashboard/sections/create-request.php` | Buyer request creation |
| `dashboard/sections/edit-request.php` | Edit an existing buyer request |

### WooCommerce Account Templates (`templates/myaccount/`)

| Template | Purpose |
|----------|---------|
| `myaccount/service-orders.php` | Service orders in WooCommerce My Account |
| `myaccount/vendor-dashboard.php` | Vendor dashboard in My Account |
| `myaccount/vendor-services.php` | Vendor services list |
| `myaccount/service-disputes.php` | Disputes tab |
| `myaccount/notifications.php` | Notifications tab |

### Cart Templates (`templates/cart/`)

| Template | Purpose |
|----------|---------|
| `cart/cart.php` | Shopping cart page for standalone checkout mode |

### Other Templates

- **Vendor**: `vendor/profile.php` -- Public vendor profile page (served at `/provider/{username}/` by default, customizable via the `wpss_vendor_slug` filter)
- **Disputes**: `disputes/dispute-view.php` -- Dispute details view

### Email Templates (`templates/emails/`)

All email templates are theme-overridable at `yourtheme/wp-sell-services/emails/`.

**26 HTML templates:**

| Template | Purpose |
|----------|---------|
| `emails/new-order.php` | New order notification |
| `emails/order-in-progress.php` | Vendor started work |
| `emails/delivery-ready.php` | Delivery submitted for review |
| `emails/order-completed.php` | Order completed |
| `emails/order-cancelled.php` | Order cancelled |
| `emails/requirements-submitted.php` | Buyer submitted requirements |
| `emails/requirements-reminder.php` | Reminder to submit requirements |
| `emails/revision-requested.php` | Buyer requested revision |
| `emails/cancellation-requested.php` | Cancellation request filed |
| `emails/new-message.php` | New order message |
| `emails/dispute-opened.php` | Dispute opened |
| `emails/dispute-escalated.php` | Dispute escalated to admin |
| `emails/seller-level-promotion.php` | Vendor promoted to new seller level |
| `emails/moderation-pending.php` | Service submitted for moderation |
| `emails/moderation-approved.php` | Service approved |
| `emails/moderation-rejected.php` | Service rejected |
| `emails/moderation-response.php` | Vendor responded to moderation feedback |
| `emails/vendor-contact.php` | Vendor contact form submission |
| `emails/withdrawal-requested.php` | Vendor requested withdrawal |
| `emails/withdrawal-approved.php` | Withdrawal approved |
| `emails/withdrawal-rejected.php` | Withdrawal rejected |
| `emails/withdrawal-auto.php` | Automatic withdrawal processed |
| `emails/generic.php` | Generic notification template |
| `emails/test-email.php` | Test email (from settings) |
| `emails/email-header.php` | Shared email header with logo and branding |
| `emails/email-footer.php` | Shared email footer |

**10 plain text variants** at `emails/plain/`:

| Template | Purpose |
|----------|---------|
| `emails/plain/new-order.php` | Plain text new order |
| `emails/plain/order-in-progress.php` | Plain text order started |
| `emails/plain/order-completed.php` | Plain text order completed |
| `emails/plain/order-cancelled.php` | Plain text order cancelled |
| `emails/plain/delivery-ready.php` | Plain text delivery submitted |
| `emails/plain/requirements-submitted.php` | Plain text requirements submitted |
| `emails/plain/requirements-reminder.php` | Plain text requirements reminder |
| `emails/plain/revision-requested.php` | Plain text revision requested |
| `emails/plain/new-message.php` | Plain text new message |
| `emails/plain/dispute-opened.php` | Plain text dispute opened |

See [Email Customization](email-customization.md) for how to override email content, headers, footers, and sender details using filters.

## Template Functions

```php
// Load a template part (like WP get_template_part but with plugin fallback)
wpss_get_template_part( 'content', 'service-card' );
// Searches: theme/wp-sell-services/content-service-card.php then plugin/templates/

// With arguments
wpss_get_template_part( 'partials/vendor-card', '', [ 'vendor_id' => 42 ] );

// Load a specific template file
wpss_get_template( 'order/order-view.php', [ 'order' => $order ] );
```

### Template Filters

```php
// Redirect template loading without copying files
add_filter( 'wpss_locate_template', function( $template, $template_name, $template_path ) {
    if ( 'single-service.php' === $template_name ) {
        return '/path/to/my/custom-single-service.php';
    }
    return $template;
}, 10, 3 );

// Override a dashboard section template
add_filter( 'wpss_dashboard_section_template', function( $template_path, $section ) {
    if ( 'earnings' === $section ) {
        return get_stylesheet_directory() . '/my-earnings-template.php';
    }
    return $template_path;
}, 10, 2 );
```

## Single Service Page Hooks

The `SingleServiceView` class (`src/Frontend/SingleServiceView.php`) renders each section via action hooks. You can add, remove, or reorder sections without overriding the entire template.

### Default Hook Registration

| Hook | Callback | Priority |
|------|----------|----------|
| `wpss_single_service_header` | `render_breadcrumb` | 5 |
| `wpss_single_service_header` | `render_title` | 10 |
| `wpss_single_service_header` | `render_meta` | 15 |
| `wpss_single_service_gallery` | `render_gallery` | 10 |
| `wpss_single_service_content` | `render_description` | 10 |
| `wpss_single_service_content` | `render_about_vendor` | 20 |
| `wpss_single_service_faqs` | `render_faqs` | 10 |
| `wpss_single_service_reviews` | `render_reviews` | 10 |
| `wpss_single_service_sidebar` | `render_packages` | 10 |
| `wpss_single_service_sidebar` | `render_vendor_card` | 20 |
| `wpss_single_service_related` | `render_related_services` | 10 |
| `wpss_after_single_service` | `render_order_modal` | 10 |
| `wpss_after_single_service` | `render_contact_modal` | 20 |

### Customizing Sections

```php
// Add content after service title (priority 12 = after title at 10, before meta at 15)
add_action( 'wpss_single_service_header', function( $service ) {
    if ( get_post_meta( $service->id, '_wpss_featured', true ) ) {
        echo '<span class="wpss-featured-badge">Featured</span>';
    }
}, 12 );

// Remove FAQ section
remove_action( 'wpss_single_service_faqs', [ wpss()->get_single_service_view(), 'render_faqs' ], 10 );

// Show vendor card before packages (move from priority 20 to 5)
$view = wpss()->get_single_service_view();
remove_action( 'wpss_single_service_sidebar', [ $view, 'render_vendor_card' ], 20 );
add_action( 'wpss_single_service_sidebar', [ $view, 'render_vendor_card' ], 5 );
```

## Dashboard Customization

```php
// Control section access
add_filter( 'wpss_can_access_dashboard_section', function( $allowed, $section, $user_id ) {
    if ( 'earnings' === $section ) {
        return (bool) get_user_meta( $user_id, '_wpss_vendor_verified', true );
    }
    return $allowed;
}, 10, 3 );

// Add custom dashboard sections
add_filter( 'wpss_dashboard_sections', function( $sections, $user_id, $is_vendor ) {
    if ( $is_vendor ) {
        $sections['analytics'] = [ 'label' => 'Analytics', 'icon' => 'chart' ];
    }
    return $sections;
}, 10, 3 );

// Rename section titles
add_filter( 'wpss_dashboard_section_titles', function( $titles ) {
    $titles['orders'] = 'My Purchases';
    $titles['sales']  = 'My Sales';
    return $titles;
} );
```

## CSS Classes Reference

The plugin uses `wpss-` prefixed CSS classes. Verified classes from `SingleServiceView.php`:

```
.wpss-breadcrumb / .wpss-breadcrumb-list / .wpss-breadcrumb-item / .wpss-breadcrumb-current
.wpss-service-title / .wpss-service-meta / .wpss-meta-item
.wpss-meta-vendor / .wpss-meta-rating / .wpss-meta-orders / .wpss-meta-queue
.wpss-vendor-mini-avatar / .wpss-vendor-name / .wpss-verified-badge
.wpss-rating-link / .wpss-rating-value / .wpss-rating-count
.wpss-star / .wpss-star.filled
```

### Enqueued Stylesheets

| Handle | File | Purpose |
|--------|------|---------|
| `wpss-design-system` | `assets/css/design-system.css` | CSS custom properties and tokens |
| `wpss-frontend` | `assets/css/frontend.css` | Base frontend styles |
| `wpss-single-service` | `assets/css/single-service.css` | Single service page |
| `wpss-unified-dashboard` | `assets/css/unified-dashboard.css` | Dashboard styles |

### Overriding Styles

```php
// Enqueue a custom stylesheet after plugin styles
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'mytheme-wpss', get_stylesheet_directory_uri() . '/css/wpss-custom.css', [ 'wpss-frontend' ], '1.0.0' );
}, 20 );

// Or dequeue plugin styles entirely
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'wpss-frontend' );
    wp_deregister_style( 'wpss-frontend' );
}, 100 );
```

## JavaScript Integration

| Handle | File | Dependencies |
|--------|------|-------------|
| `alpinejs` | `assets/js/vendor/alpine.min.js` | None (defer) |
| `wpss-frontend` | `assets/js/frontend.js` | `alpinejs` |
| `wpss-single-service` | `assets/js/single-service.js` | `jquery`, `wpss-frontend` |
| `wpss-unified-dashboard` | `assets/js/unified-dashboard.js` | `jquery` |

### JavaScript Data Objects

**`wpss`**: `ajaxUrl`, `restUrl`, `nonce`

**`wpssData`**: `ajaxUrl`, `apiUrl`, `nonce`, `orderNonce`, `restNonce`, `pollingInterval` (10000ms), `currencyFormat`, `i18n`

**`wpssService`** (single service page only): `serviceId`, `ajaxUrl`, `nonce`, `checkoutUrl`, `cartUrl`, `i18n`

```php
// Add custom scripts after plugin JS
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_script( 'mytheme-wpss-js', get_stylesheet_directory_uri() . '/js/wpss-custom.js', [ 'wpss-frontend' ], '1.0.0', true );
}, 20 );
```

## URL Structure

| Pattern | Template | Filter |
|---------|----------|--------|
| `/provider/{username}/` | `vendor/profile.php` | `wpss_vendor_slug` |
| `/service-order/{id}/` | `order/order-view.php` | `wpss_service_order_slug` |
| `/service-order/{id}/{action}/` | `order/order-{action}.php` | `wpss_service_order_slug` |
| `/service/` (CPT slug) | `archive-service.php` | `wpss_service_slug` |
| `/buyer-request/` (CPT slug) | `archive-request.php` | `wpss_buyer_request_slug` |
| `/service-checkout/{id}/` | Checkout shortcode | `wpss_checkout_slug` |

All URL slugs are filterable to avoid conflicts with other plugins. After changing slugs, flush rewrite rules by visiting **Settings > Permalinks** and clicking Save.

## Related Documentation

- [Hooks and Filters](hooks-filters.md) - Complete hooks reference with parameters
- [Custom Integrations](custom-integrations.md) - Building adapters and extending the API
- [REST API](rest-api.md) - API endpoints for frontend integration
