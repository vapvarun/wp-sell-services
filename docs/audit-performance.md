# Performance Audit Report

**Date:** 2026-01-11
**Plugin:** WP Sell Services v1.0.0
**Scope:** N+1 queries, unbounded queries, caching, database indexes

---

## Executive Summary

The plugin has **significant performance issues** that will impact scalability. Key concerns:
- Multiple N+1 query patterns in API controllers
- 13 instances of unbounded queries (`posts_per_page => -1`)
- Nearly complete absence of caching (only 4 transient usages, no object cache)
- Missing WP_Query optimization flags

**Performance Grade: C-**

---

## N+1 Query Issues

| File:Line | Issue | Impact | Fix |
|-----------|-------|--------|-----|
| `VendorsController.php:481-494` | `get_vendor_reviews()` loops through reviews calling `get_userdata()` and `get_post()` per item | HIGH - O(n) queries for each review | Batch load user data with `WP_User_Query` and posts with single `get_posts()` call |
| `VendorsController.php:596-617` | `prepare_item_for_response()` calls `get_user_meta()` ~12 times per vendor | HIGH - 12 queries per vendor in list | Use `get_user_meta($user_id)` with empty key to get all meta in one query |
| `VendorsController.php:634-647` | `prepare_service_for_response()` calls `get_post_meta()` 3x and `wp_get_post_terms()` per service | MEDIUM - N+1 on service listings | Set `update_post_meta_cache => true` in initial query, batch load terms |
| `ServiceArchiveView.php:239-245` | Nested loop for categories, `get_terms()` per parent category | MEDIUM - O(n) on category count | Single query with `hierarchical => true` or cache result |
| `NotificationService.php:130-141` | `get_unread_count()` queries DB directly on every call | MEDIUM - Called on every page load | Cache with transient or object cache |

---

## Unbounded Queries (posts_per_page => -1)

| File:Line | Context | Risk | Recommendation |
|-----------|---------|------|----------------|
| `AnalyticsService.php:128` | Getting all orders for analytics | HIGH - Memory exhaustion | Paginate and aggregate in chunks |
| `AnalyticsService.php:314` | Getting all services for stats | HIGH | Use SQL aggregation instead |
| `BuyerRequestService.php:469` | Loading all requests | MEDIUM | Add pagination |
| `ModerationService.php:137` | Loading pending services | MEDIUM | Paginate with limits |
| `API.php:398` | Service listing | MEDIUM | Already has pagination in REST, review usage |
| `VendorsController.php:520` | `get_vendor_stats()` loading all services | HIGH | Use `SELECT COUNT(*)` instead |
| `MigrationManager.php:436` | Migration query | LOW - One-time operation | Acceptable but add batch processing |
| `CLI/ServiceCommands.php:171,236,502` | CLI commands | LOW - Admin only | Add `--limit` flag option |
| `ManualOrderPage.php:87` | Service dropdown | MEDIUM | Limit to 100 with search autocomplete |
| `WCAccountProvider.php:195` | WC integration | MEDIUM | Paginate |
| `WCProductProvider.php:187` | Product sync | MEDIUM | Batch process |

---

## Missing Caching

| Location | Data | Recommended Cache Type | TTL |
|----------|------|------------------------|-----|
| `NotificationService.php` | Unread notification count | Object cache or transient | 5 min |
| `ReviewService.php` | Vendor rating summary | Transient | 1 hour |
| `VendorService.php` | Vendor profile data | Object cache | 15 min |
| `OrderService.php` | Vendor/customer stats | Transient with invalidation | 1 hour |
| `ServiceArchiveView.php` | Category hierarchy | Transient | 6 hours |
| `AnalyticsService.php` | Dashboard analytics | Transient | 15 min |

**Current Caching Status:**
- `get_transient`/`set_transient`: Only 4 occurrences (minimal)
- `wp_cache_get`/`wp_cache_set`: 0 occurrences
- No caching strategy implemented

---

## Missing WP_Query Optimizations

| Optimization | Files Missing It | Impact |
|--------------|------------------|--------|
| `no_found_rows => true` | All service/order queries | Extra `SQL_CALC_FOUND_ROWS` on every query |
| `update_post_meta_cache => false` | Queries not using meta | Unnecessary meta cache population |
| `update_post_term_cache => false` | Queries not using terms | Unnecessary term cache population |
| `fields => 'ids'` | Count queries using full objects | Fetching unneeded data |

---

## Database Index Recommendations

| Table | Column(s) | Recommended Index | Reason |
|-------|-----------|-------------------|--------|
| `wpss_orders` | `customer_id` | `KEY idx_customer (customer_id)` | Customer order lookups |
| `wpss_orders` | `vendor_id` | `KEY idx_vendor (vendor_id)` | Vendor order lookups |
| `wpss_orders` | `status, created_at` | `KEY idx_status_date (status, created_at)` | Status filtering with date sort |
| `wpss_reviews` | `service_id` | `KEY idx_service (service_id)` | Service review lookups |
| `wpss_reviews` | `vendor_id, status` | `KEY idx_vendor_status (vendor_id, status)` | Vendor review aggregation |
| `wpss_notifications` | `user_id, is_read` | `KEY idx_user_unread (user_id, is_read)` | Unread count queries |
| `wpss_conversations` | `order_id` | Already exists | Good |
| `wpss_messages` | `conversation_id` | Already exists | Good |

---

## Expensive Hook Operations

| Hook | File | Issue | Recommendation |
|------|------|-------|----------------|
| `init` | `Plugin.php` | 6+ components initializing on init | Lazy load where possible, defer to `wp_loaded` |
| `template_redirect` | `SingleServiceView.php` | Database queries on every service page | Cache view data |
| `wp_enqueue_scripts` | `Frontend/Assets.php` | Conditional logic runs on every page | Early return if not plugin page |

---

## Priority Fixes

### Critical (Fix Before Launch)
1. Add caching to `NotificationService::get_unread_count()` - called on every admin page
2. Fix N+1 in `VendorsController::get_vendor_reviews()` - API performance
3. Add `no_found_rows => true` to all listing queries not using pagination total

### High (Fix Soon)
4. Add transient caching to vendor rating summaries
5. Replace unbounded queries in `AnalyticsService.php` with aggregation
6. Add indexes to `wpss_notifications` table

### Medium (Technical Debt)
7. Batch user meta loading in `VendorsController::prepare_item_for_response()`
8. Add object caching for vendor profiles
9. Paginate admin dropdowns (services, vendors)

### Low (Nice to Have)
10. CLI command pagination flags
11. Migration batching

---

## Quick Wins

1. **Add `no_found_rows`** to all WP_Query that doesn't need total count:
```php
$query = new WP_Query([
    'post_type'      => 'wpss_service',
    'no_found_rows'  => true, // Add this
    // ...
]);
```

2. **Cache notification count** (5 min impact):
```php
public function get_unread_count( int $user_id ): int {
    $cache_key = "wpss_unread_notifications_{$user_id}";
    $count = wp_cache_get( $cache_key );

    if ( false === $count ) {
        $count = (int) $this->wpdb->get_var( /* existing query */ );
        wp_cache_set( $cache_key, $count, '', 300 ); // 5 min
    }

    return $count;
}
```

3. **Batch user meta loading**:
```php
// Instead of 12 separate get_user_meta calls:
$all_meta = get_user_meta( $vendor_id ); // Get all meta in one query
$tagline = $all_meta['_wpss_vendor_tagline'][0] ?? '';
$bio = $all_meta['_wpss_vendor_bio'][0] ?? '';
```

---

## Conclusion

The plugin requires performance optimization before production deployment with significant traffic. The N+1 query patterns and missing caching will cause database overload at scale.

**Estimated Performance Improvement:** 60-70% reduction in database queries with recommended fixes.
