# Performance Audit: WP Sell Services

**Audit Date:** 2026-01-11
**Plugin Version:** 1.0.0
**Scope:** Database queries, caching, N+1 patterns, unbounded queries

---

## Executive Summary

| Category | Count |
|----------|-------|
| N+1 Query Patterns | 4 |
| Unbounded Queries | 13 |
| Missing Caching | 7 |
| Missing WP_Query Optimizations | 4 |

---

## Critical: N+1 Query Patterns

### 1. VendorsController::get_vendor_reviews()

**File:** `src/API/VendorsController.php:481-494`

```php
// Current - O(n*2) queries
foreach ( $reviews as $review ) {
    $customer = get_userdata( (int) $review->customer_id ); // N+1!
    $service  = get_post( (int) $review->service_id );       // N+1!
}
```

**Fix:**
```php
// Batch load before loop
$customer_ids = array_unique( array_column( $reviews, 'customer_id' ) );
$service_ids  = array_unique( array_column( $reviews, 'service_id' ) );

if ( ! empty( $customer_ids ) ) {
    get_users( [ 'include' => $customer_ids, 'fields' => 'all' ] );
}
if ( ! empty( $service_ids ) ) {
    _prime_post_caches( $service_ids, true, false );
}

foreach ( $reviews as $review ) {
    $customer = get_userdata( (int) $review->customer_id ); // Now cached
    $service  = get_post( (int) $review->service_id );       // Now cached
}
```

### 2. VendorsController::prepare_item_for_response()

**File:** `src/API/VendorsController.php:596-617`

```php
// Current - 12+ queries per vendor
$data = [
    'tagline'   => get_user_meta( $vendor_id, '_wpss_vendor_tagline', true ),
    'bio'       => get_user_meta( $vendor_id, '_wpss_vendor_bio', true ),
    'skills'    => get_user_meta( $vendor_id, '_wpss_vendor_skills', true ),
    // ... 9 more get_user_meta calls
];
```

**Fix:**
```php
// Get all meta at once (single query)
$all_meta = get_user_meta( $vendor_id );

$data = [
    'tagline'   => $all_meta['_wpss_vendor_tagline'][0] ?? '',
    'bio'       => $all_meta['_wpss_vendor_bio'][0] ?? '',
    'skills'    => maybe_unserialize( $all_meta['_wpss_vendor_skills'][0] ?? '' ) ?: [],
];
```

### 3. VendorsController::prepare_service_for_response()

**File:** `src/API/VendorsController.php:634-647`

- Calls `get_post_meta()` 3x per service
- Calls `wp_get_post_terms()` per service

**Fix:** Use `update_postmeta_cache()` and `update_object_term_cache()` before loop.

### 4. ServiceArchiveView::render_sidebar()

**File:** `src/Frontend/ServiceArchiveView.php:239-245`

- Nested loop calls `get_terms()` for each parent category

**Fix:** Single query with all terms, then group by parent in PHP.

---

## High: Unbounded Queries (posts_per_page = -1)

| File | Line | Context | Risk |
|------|------|---------|------|
| `src/Services/AnalyticsService.php` | 123 | `get_vendor_stats()` | Memory exhaustion |
| `src/Services/AnalyticsService.php` | 310 | `get_active_service_count()` | Unnecessary |
| `src/Services/BuyerRequestService.php` | 469 | Export all requests | Memory issues |
| `src/Services/ModerationService.php` | 137 | All pending items | Could load thousands |
| `src/API/VendorsController.php` | 514 | `get_vendor_stats()` | Unnecessary |
| `src/Admin/Pages/ManualOrderPage.php` | 87 | Service dropdown | UI freeze |
| `src/Integrations/WooCommerce/WCAccountProvider.php` | 195 | All WC orders | Memory exhaustion |
| `src/Integrations/WooCommerce/WCProductProvider.php` | 187 | Product sync | Memory issues |

**Acceptable (CLI only):**
- `src/Database/MigrationManager.php:436` - Migration
- `src/CLI/ServiceCommands.php:171, 236, 502` - CLI bulk ops

---

## High: Missing Caching

| File | Function | Recommendation |
|------|----------|----------------|
| `src/Services/NotificationService.php:130` | `get_unread_count()` | Object cache 60s TTL |
| `src/Database/Repositories/ReviewRepository.php:168` | `get_service_rating_summary()` | Transient 1hr TTL |
| `src/Database/Repositories/ReviewRepository.php:218` | `get_vendor_rating_summary()` | Transient 1hr TTL |
| `src/Database/Repositories/VendorProfileRepository.php:174` | `update_stats()` | Cache, recalc on order complete |
| `src/Services/AnalyticsService.php:62` | `get_vendor_dashboard_stats()` | Object cache 5min TTL |
| `src/Services/AnalyticsService.php:96` | `get_admin_dashboard_stats()` | Transient 15min TTL |

### Example Fix: NotificationService

```php
public function get_unread_count( int $user_id ): int {
    $cache_key = "wpss_unread_notifications_{$user_id}";
    $count = wp_cache_get( $cache_key, 'wpss' );

    if ( false === $count ) {
        global $wpdb;
        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND is_read = 0",
                $user_id
            )
        );
        wp_cache_set( $cache_key, $count, 'wpss', 60 );
    }

    return $count;
}
```

---

## Medium: Missing WP_Query Optimizations

| Pattern | Recommendation | Impact |
|---------|----------------|--------|
| `no_found_rows` | Add `'no_found_rows' => true` when pagination not needed | 30-50% faster |
| `update_post_meta_cache` | Add `false` when meta not needed | Skips meta query |
| `update_post_term_cache` | Add `false` when terms not needed | Skips term query |
| `fields => ids` | Use when only IDs needed | Reduces memory |

---

## Database Schema Assessment

**Good indexes exist on:**
- `wpss_orders`: customer_id, vendor_id, service_id, status, delivery_deadline
- `wpss_reviews`: order_id, service_id, customer_id, vendor_id
- `wpss_notifications`: user_id+is_read (composite)
- `wpss_vendor_profiles`: user_id (unique), verification_tier, avg_rating

**Missing indexes:**
| Table | Column | Reason |
|-------|--------|--------|
| `wpss_reviews` | `status` | Frequent filtering by `status = 'approved'` |
| `wpss_orders` | `created_at` | Date range queries in analytics |

---

## Estimated Impact After Fixes

| Fix Category | Improvement |
|--------------|-------------|
| N+1 query fixes | 50-90% query reduction |
| Object caching | 10-30 queries saved per page |
| Transient caching | 60-80% database load reduction |
| WP_Query optimizations | 20-40% faster archives |

---

## Implementation Priority

### Phase 1 - Critical (Immediate)
1. Fix N+1 in `VendorsController::get_vendor_reviews()`
2. Fix N+1 in `VendorsController::prepare_item_for_response()`
3. Add object caching to `NotificationService::get_unread_count()`

### Phase 2 - High (This Week)
4. Add transient caching to `ReviewRepository` summary methods
5. Fix N+1 in `ServiceArchiveView::render_sidebar()`
6. Add `no_found_rows => true` to non-paginated queries

### Phase 3 - Medium (Next Week)
7. Fix unbounded queries in non-CLI contexts
8. Add missing database indexes
