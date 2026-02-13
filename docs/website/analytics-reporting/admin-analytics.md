# Admin Analytics Dashboard

Platform-wide insights for marketplace administrators. Monitor revenue, track vendor performance, and analyze marketplace health using the built-in analytics system.

## Overview

Admin analytics provides comprehensive metrics for:

- Platform revenue and commission earnings
- Top-performing vendors and services
- Order volume and status distribution
- User counts (vendors and buyers)
- Basic marketplace health indicators

## Accessing Admin Analytics

1. Log in as administrator
2. Go to **WordPress Admin → WP Sell Services → Analytics**
3. View platform overview dashboard
4. Navigate to specific metric sections

![Admin analytics dashboard](../images/pro-admin-analytics-dashboard.png)

## Platform Overview

### Dashboard Statistics

The `get_admin_dashboard_stats()` method provides:

```php
[
    'total_vendors' => 150,
    'total_services' => 450,
    'total_orders' => 2340,
    'total_revenue' => 125000.00,
    'order_stats' => [
        'pending' => 10,
        'in_progress' => 45,
        'completed' => 2200,
        'cancelled' => 85,
    ],
    'recent_orders' => [...], // 10 most recent
    'top_vendors' => [...],   // Top rated vendors
    'top_categories' => [...], // By service count
]
```

## Revenue Metrics

### Total Revenue

```php
$total_revenue = $analytics_service->get_total_revenue();
```

Calculates sum of all completed order totals:

```sql
SELECT SUM(total) FROM wpss_orders WHERE status = 'completed'
```

### Revenue Breakdown

The free version provides:

**Total Revenue:** Gross sales amount
**By Period:** Use date filters to see period revenue

**Not included in free:**
- Commission calculations by admin
- Revenue by category
- Revenue trends/charts
- Payment method distribution

Use order reports for detailed commission tracking.

## Order Statistics

### Order Counts

Total orders across all statuses:

```php
$total_orders = $analytics_service->get_total_orders();
```

Returns COUNT from `wpss_orders` table.

### Order by Status

```php
$order_stats = $analytics_service->get_order_stats_by_status();
```

Returns array:

```php
[
    'pending' => 10,
    'pending_requirements' => 5,
    'in_progress' => 45,
    'delivered' => 15,
    'completed' => 2200,
    'cancelled' => 50,
    'disputed' => 2,
    'refunded' => 15,
]
```

Groups by status column, counts each.

### Recent Orders

```php
$recent = $analytics_service->get_recent_orders( 10 );
```

Returns 10 most recent orders, all data from `wpss_orders` table, sorted by `created_at DESC`.

## Vendor Performance

### Total Vendor Count

```php
$total = $analytics_service->get_total_vendors();
```

Counts users with `wpss_vendor` role.

Uses WP_User_Query with role filter.

### Top Vendors

```php
$top_vendors = $analytics_service->get_top_vendors( 10 );
```

Returns vendors from VendorProfileRepository:

```php
$vendor_repo->get_top_rated( $limit );
```

Sorted by rating and review count.

**Returns:**
- Vendor ID
- Name
- Rating
- Total reviews
- Profile data

### Vendor Activity

**Not automated.** To track vendor activity:

- Recent orders by vendor
- Services published by vendor
- Last login timestamp

Must query manually or build custom reports.

## Service Statistics

### Total Services

```php
$total = $analytics_service->get_total_services();
```

Counts published `wpss_service` posts:

```php
$counts = wp_count_posts( 'wpss_service' );
return $counts->publish;
```

### Top Categories

```php
$top_categories = $analytics_service->get_top_categories( 10 );
```

Returns service categories by count:

```php
get_terms( [
    'taxonomy' => 'wpss_service_category',
    'orderby' => 'count',
    'order' => 'DESC',
    'number' => $limit,
] );
```

**Returns:** WP_Term objects with:
- term_id
- name
- slug
- count (services in category)

## Limitations of Free Version

### What's Included

- Total counts (vendors, services, orders, revenue)
- Order status breakdown
- Top vendors by rating
- Top categories by service count
- Recent orders list

### What Requires Custom Development

**Time-based Filtering:**
- Date range queries
- Period comparisons
- Trend charts

**Advanced Metrics:**
- Revenue by category
- Commission tracking by vendor
- Growth rates
- Conversion funnels
- User retention

**Visualization:**
- Charts and graphs
- Dashboard widgets
- Exportable reports

### Pro Features (Planned)

**[PRO]** Advanced analytics may include:
- Custom date range filtering
- Visual charts and trends
- Automated report scheduling
- Commission analytics
- Export to CSV/PDF
- Real-time dashboards

## Custom Analytics Queries

### Example: Revenue by Month

```php
global $wpdb;
$orders_table = $wpdb->prefix . 'wpss_orders';

$monthly_revenue = $wpdb->get_results(
    "SELECT
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(total) as revenue,
        COUNT(*) as orders
    FROM {$orders_table}
    WHERE status = 'completed'
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12"
);
```

### Example: Commission Earned

```php
$commission_earned = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT SUM(platform_fee)
        FROM {$orders_table}
        WHERE status = 'completed'
        AND created_at >= %s",
        $date_from
    )
);
```

### Example: Top Vendors by Revenue

```php
$top_vendors = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT
            vendor_id,
            COUNT(*) as order_count,
            SUM(vendor_earnings) as total_earnings
        FROM {$orders_table}
        WHERE status = 'completed'
        GROUP BY vendor_id
        ORDER BY total_earnings DESC
        LIMIT %d",
        10
    )
);
```

## Caching Analytics

Analytics queries can be expensive. Cache results:

```php
$cache_key = 'wpss_admin_dashboard_stats';
$stats = get_transient( $cache_key );

if ( false === $stats ) {
    $analytics = new AnalyticsService();
    $stats = $analytics->get_admin_dashboard_stats();
    set_transient( $cache_key, $stats, 15 * MINUTE_IN_SECONDS );
}
```

Clear cache on order status changes:

```php
add_action( 'wpss_order_status_changed', function() {
    delete_transient( 'wpss_admin_dashboard_stats' );
} );
```

## Performance Optimization

### Database Indexes

Ensure indexes on frequently queried columns:

```sql
-- wpss_orders table
ALTER TABLE wpss_orders ADD INDEX idx_status (status);
ALTER TABLE wpss_orders ADD INDEX idx_vendor (vendor_id);
ALTER TABLE wpss_orders ADD INDEX idx_created (created_at);
ALTER TABLE wpss_orders ADD INDEX idx_status_created (status, created_at);
```

### Query Limits

Always limit result sets:

```php
$analytics->get_recent_orders( 20 ); // Don't fetch all orders
$analytics->get_top_vendors( 10 );   // Limit to top 10
```

### Avoid N+1 Queries

When displaying vendor or service data:

```php
// Bad: Queries inside loop
foreach ( $orders as $order ) {
    $vendor = get_user_by( 'ID', $order->vendor_id );
    $service = get_post( $order->service_id );
}

// Good: Prefetch data
$vendor_ids = wp_list_pluck( $orders, 'vendor_id' );
$vendors = get_users( [ 'include' => $vendor_ids ] );

$service_ids = wp_list_pluck( $orders, 'service_id' );
$services = get_posts( [ 'include' => $service_ids, 'post_type' => 'wpss_service' ] );
```

## Exporting Data

For data export capabilities, see:

**[Data Export Guide](data-export.md)** - Detailed export instructions

Basic PHP export example:

```php
// Set headers for CSV download
header( 'Content-Type: text/csv' );
header( 'Content-Disposition: attachment; filename="orders-export.csv"' );

$output = fopen( 'php://output', 'w' );

// CSV headers
fputcsv( $output, [ 'Order ID', 'Date', 'Vendor', 'Total', 'Status' ] );

// Data rows
foreach ( $orders as $order ) {
    fputcsv( $output, [
        $order->id,
        $order->created_at,
        get_userdata( $order->vendor_id )->display_name,
        $order->total,
        $order->status,
    ] );
}

fclose( $output );
exit;
```

## Related Documentation

- [Vendor Analytics](vendor-analytics.md) - Vendor dashboard analytics
- [Data Export](data-export.md) - Exporting reports
- [Order Management](../order-management/order-lifecycle.md) - Understanding orders
- [Vendor Management](../admin-tools/vendor-management.md) - Managing vendors

---

**Key Points:**
- Basic platform statistics included in free version
- Use AnalyticsService methods for standard queries
- Custom reports require direct database queries
- Cache results for performance
- Pro version will include advanced features
- Export functionality requires custom development or Pro
