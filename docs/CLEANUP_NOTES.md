# Cleanup Notes: Redundant Delivery/Revision Fields

## Problem Summary

Service-level `_wpss_delivery_time` and `_wpss_revision_limit` are redundant because every service now has at least 1 package with its own `delivery_days` and `revisions` fields.

## Field Hierarchy (After Cleanup)

```
┌─────────────────────────────────────────────────────────────────┐
│ SOURCE OF TRUTH: Package                                        │
│                                                                 │
│ packages[0].delivery_days  → Primary delivery time              │
│ packages[0].revisions      → Primary revision count             │
│                                                                 │
│ + Addon modifiers:                                              │
│   addon.delivery_days_extra → Added to package delivery         │
└─────────────────────────────────────────────────────────────────┘
```

## Removed Fields (Service Level)

| Meta Key | Was Used For | Replaced By |
|----------|--------------|-------------|
| `_wpss_delivery_time` | Default delivery | `_wpss_fastest_delivery` (computed) |
| `_wpss_revision_limit` | Default revisions | `_wpss_max_revisions` (computed) |

## New Computed Fields (Auto-generated on save)

These are computed from packages and stored for quick access by SEO/display code:

| Meta Key | Computed From | Value |
|----------|---------------|-------|
| `_wpss_starting_price` | `min(packages[*].price)` | Lowest package price |
| `_wpss_fastest_delivery` | `min(packages[*].delivery_days)` | Fastest delivery option |
| `_wpss_max_revisions` | `max(packages[*].revisions)` | Maximum revisions offered |

## Files Needing Updates

### Already Cleaned (This PR)

- [x] `src/Admin/Metaboxes/ServiceMetabox.php`
  - Removed delivery_time and revision_limit from render_details_metabox()
  - Removed save logic for these fields

### Need Future Refactoring

| File | Line | Current Code | Should Change To |
|------|------|--------------|------------------|
| `src/Frontend/AjaxHandlers.php` | 1265 | `get_post_meta($service_id, '_wpss_delivery_time', true)` | Get from first package |
| `src/API/ServicesController.php` | 578-579 | Returns `_wpss_delivery_time` and `_wpss_revisions` | Return from packages |
| `src/API/ServicesController.php` | 681, 685 | Saves `_wpss_delivery_time` and `_wpss_revisions` | Remove or deprecate |

### Valid Uses (Different Context - DO NOT CHANGE)

These use delivery fields on **different post types**, not services:

| File | Context | Meta Key | Notes |
|------|---------|----------|-------|
| `src/Admin/Metaboxes/BuyerRequestMetabox.php` | Buyer Requests CPT | `_wpss_delivery_days` | Valid - different CPT |
| `src/Services/BuyerRequestService.php` | Buyer Requests CPT | `_wpss_delivery_days` | Valid - different CPT |
| `src/Integrations/WooCommerce/WCProductProvider.php` | WC Products | `_wpss_delivery_days` | Valid - product fallback |
| `src/Integrations/WooCommerce/WCOrderProvider.php` | WC Products | `_wpss_delivery_days` | Valid - uses package first |

### SEO Files (Need Key Update)

These files use wrong meta key. Should use `_wpss_fastest_delivery`:

| File | Line | Current | Should Be |
|------|------|---------|-----------|
| `src/SEO/RankMathIntegration.php` | 152, 204 | `_wpss_delivery_days` | `_wpss_fastest_delivery` |
| `src/SEO/SchemaMarkup.php` | 98 | `_wpss_delivery_days` | `_wpss_fastest_delivery` |
| `src/SEO/ServiceSchemaPiece.php` | 50 | `_wpss_delivery_days` | `_wpss_fastest_delivery` |
| `src/SEO/YoastIntegration.php` | 183 | `_wpss_delivery_days` | `_wpss_fastest_delivery` |

## Helper Functions to Add

```php
/**
 * Get fastest delivery time from service packages.
 *
 * @param int $service_id Service ID.
 * @return int Fastest delivery days (minimum from all packages).
 */
function wpss_get_service_fastest_delivery( int $service_id ): int {
    $packages = get_post_meta( $service_id, '_wpss_packages', true );

    if ( empty( $packages ) || ! is_array( $packages ) ) {
        return 7; // Default fallback
    }

    $days = array_map( fn( $p ) => (int) ( $p['delivery_days'] ?? 7 ), $packages );
    return min( $days ) ?: 7;
}

/**
 * Get maximum revisions from service packages.
 *
 * @param int $service_id Service ID.
 * @return int Maximum revisions from all packages.
 */
function wpss_get_service_max_revisions( int $service_id ): int {
    $packages = get_post_meta( $service_id, '_wpss_packages', true );

    if ( empty( $packages ) || ! is_array( $packages ) ) {
        return 0; // Default fallback
    }

    $revisions = array_map( fn( $p ) => (int) ( $p['revisions'] ?? 0 ), $packages );
    return max( $revisions ) ?: 0;
}
```

## Migration Notes

- Old services with `_wpss_delivery_time` set: Values ignored, package values used
- No database migration needed - package data already exists
- Old meta values can be cleaned up in future version

## Order Deadline Calculation (Fixed)

```
Final Deadline = Package delivery_days + Sum(Addon delivery_days_extra)
```

Files updated to include addon days:
- `src/Integrations/WooCommerce/WCOrderProvider.php`
- `src/Services/OrderService.php`
