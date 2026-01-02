# Meta Keys Standardization

## Standardized Meta Keys (Service Post Type)

All service post meta keys have been standardized to use consistent naming.

### Pricing & Stats

| Meta Key | Purpose | Type | Computed From |
|----------|---------|------|---------------|
| `_wpss_starting_price` | Lowest package price | float | `min(packages[*].price)` |
| `_wpss_rating_average` | Average rating | float | Reviews table |
| `_wpss_rating_count` | Total reviews | int | Reviews table |
| `_wpss_review_count` | Alias for rating_count | int | Reviews table |
| `_wpss_order_count` | Total completed orders | int | Orders table |
| `_wpss_view_count` | Page views | int | Incremented on view |

### Delivery & Revisions

| Meta Key | Purpose | Type | Computed From |
|----------|---------|------|---------------|
| `_wpss_fastest_delivery` | Minimum delivery days | int | `min(packages[*].delivery_days)` |
| `_wpss_max_revisions` | Maximum revisions | int | `max(packages[*].revisions)` |
| `_wpss_packages` | Package data (source of truth) | array | User input |

### Content

| Meta Key | Purpose | Type |
|----------|---------|------|
| `_wpss_gallery` | Gallery image IDs | array |
| `_wpss_requirements` | Buyer requirements | array |
| `_wpss_faqs` | FAQ items | array |
| `_wpss_addons` | Service addons | array |
| `_wpss_platform_ids` | E-commerce product IDs | array |
| `_wpss_featured` | Is featured service | bool |

## Deprecated Meta Keys (DO NOT USE)

| Old Key | Replaced By | Notes |
|---------|-------------|-------|
| `_wpss_rating` | `_wpss_rating_average` | Inconsistent naming |
| `_wpss_average_rating` | `_wpss_rating_average` | Inconsistent naming |
| `_wpss_orders` | `_wpss_order_count` | Inconsistent naming |
| `_wpss_orders_count` | `_wpss_order_count` | Inconsistent naming |
| `_wpss_orders_completed` | `_wpss_order_count` | Inconsistent naming |
| `_wpss_base_price` | `_wpss_starting_price` | Inconsistent naming |
| `_wpss_delivery_time` | `_wpss_fastest_delivery` | Now computed from packages |
| `_wpss_revision_limit` | `_wpss_max_revisions` | Now computed from packages |
| `_wpss_revisions` | `_wpss_max_revisions` | Now computed from packages |
| `_wpss_delivery_days` | `_wpss_fastest_delivery` | For services (valid for Buyer Requests) |

## Field Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│ SOURCE OF TRUTH: Package                                        │
│                                                                 │
│ packages[*].price         → _wpss_starting_price (min)          │
│ packages[*].delivery_days → _wpss_fastest_delivery (min)        │
│ packages[*].revisions     → _wpss_max_revisions (max)           │
│                                                                 │
│ + Addon modifiers:                                              │
│   addon.delivery_days_extra → Added to package delivery         │
└─────────────────────────────────────────────────────────────────┘
```

## Computed Fields Auto-Update

Computed fields are auto-updated when packages are saved:

**In ServiceMetabox.php (Admin):**
```php
// Update computed meta values from packages.
$prices        = array_filter( wp_list_pluck( $packages, 'price' ) );
$delivery_days = array_filter( wp_list_pluck( $packages, 'delivery_days' ) );
$revisions     = wp_list_pluck( $packages, 'revisions' );

update_post_meta( $post_id, '_wpss_starting_price', min( $prices ) ?: 0 );
update_post_meta( $post_id, '_wpss_fastest_delivery', min( $delivery_days ) ?: 7 );
update_post_meta( $post_id, '_wpss_max_revisions', max( $revisions ) ?: 0 );
```

**In ServicesController.php (REST API):**
Same logic applied when packages are saved via API.

## Files Updated

### Rating Fields
- [x] `src/Services/ReviewService.php` - Uses `_wpss_rating_average`
- [x] `src/Services/SearchService.php` - Uses `_wpss_rating_average`
- [x] `src/Services/ServiceManager.php` - Uses `_wpss_rating_average`
- [x] `src/Blocks/SellerCard.php` - Uses `_wpss_rating_average`
- [x] `src/Blocks/FeaturedServices.php` - Uses `_wpss_rating_average`
- [x] `src/Blocks/ServiceGrid.php` - Uses `_wpss_rating_average`
- [x] `src/Admin/Metaboxes/ServiceMetabox.php` - Uses `_wpss_rating_average`
- [x] `src/SEO/RankMathIntegration.php` - Uses `_wpss_rating_average`
- [x] `src/SEO/SchemaMarkup.php` - Uses `_wpss_rating_average`
- [x] `src/SEO/ServiceSchemaPiece.php` - Uses `_wpss_rating_average`
- [x] `src/SEO/YoastIntegration.php` - Uses `_wpss_rating_average`
- [x] `src/Models/Service.php` - Uses `_wpss_rating_average`

### Order Count Fields
- [x] `src/SEO/SchemaMarkup.php` - Uses `_wpss_order_count`
- [x] `src/Models/Service.php` - Uses `_wpss_order_count`
- [x] `src/Frontend/SingleServiceView.php` - Uses `_wpss_order_count`

### Delivery/Revision Fields (SEO)
- [x] `src/SEO/RankMathIntegration.php` - Uses `_wpss_fastest_delivery`
- [x] `src/SEO/SchemaMarkup.php` - Uses `_wpss_fastest_delivery`
- [x] `src/SEO/ServiceSchemaPiece.php` - Uses `_wpss_fastest_delivery`
- [x] `src/SEO/YoastIntegration.php` - Uses `_wpss_fastest_delivery`

### API Fields
- [x] `src/API/ServicesController.php`
  - Uses `_wpss_starting_price` for pricing
  - Uses `_wpss_fastest_delivery` for delivery time
  - Uses `_wpss_max_revisions` for revisions
  - Computes values from packages on save

## Valid Uses of `_wpss_delivery_days` (Different Context)

These use `_wpss_delivery_days` on **Buyer Requests** CPT, not services:

| File | Context | Notes |
|------|---------|-------|
| `src/Admin/Metaboxes/BuyerRequestMetabox.php` | Buyer Requests CPT | Valid |
| `src/Services/BuyerRequestService.php` | Buyer Requests CPT | Valid |
| `templates/single-request.php` | Buyer Requests CPT | Valid |
| `templates/content-request-card.php` | Buyer Requests CPT | Valid |

These use it on **WC Products** as fallback:

| File | Context | Notes |
|------|---------|-------|
| `src/Integrations/WooCommerce/WCProductProvider.php` | WC Products | Valid - product level |
| `src/Integrations/WooCommerce/WCOrderProvider.php` | WC Products | Valid - uses package first |

## Order Deadline Calculation

```
Final Deadline = Package delivery_days + Sum(Addon delivery_days_extra)
```

Files that calculate deadlines:
- `src/Integrations/WooCommerce/WCOrderProvider.php`
- `src/Services/OrderService.php`

Both include addon `delivery_days_extra` in the calculation (can be negative for rush delivery).
