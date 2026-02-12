# Data Export Guide **[PRO]**

**Status:** Data export features are planned for a future Pro release.

## Planned Feature

Comprehensive data export capabilities for generating reports, analyzing trends, and meeting compliance requirements are planned for WP Sell Services Pro.

## Current Alternatives

### Manual CSV Export

Create custom CSV exports using PHP:

```php
// Example: Export orders to CSV
function wpss_export_orders_to_csv() {
    global $wpdb;
    $orders_table = $wpdb->prefix . 'wpss_orders';

    // Set headers
    header( 'Content-Type: text/csv' );
    header( 'Content-Disposition: attachment; filename="orders-' . date('Y-m-d') . '.csv"' );

    $output = fopen( 'php://output', 'w' );

    // Headers
    fputcsv( $output, [
        'Order ID',
        'Order Number',
        'Date',
        'Customer ID',
        'Vendor ID',
        'Service ID',
        'Total',
        'Status',
    ] );

    // Data
    $orders = $wpdb->get_results( "SELECT * FROM {$orders_table} ORDER BY created_at DESC" );

    foreach ( $orders as $order ) {
        fputcsv( $output, [
            $order->id,
            $order->order_number,
            $order->created_at,
            $order->customer_id,
            $order->vendor_id,
            $order->service_id,
            $order->total,
            $order->status,
        ] );
    }

    fclose( $output );
    exit;
}
```

### WordPress Admin Export

Export data using WordPress tools:

**User Data Export:**
1. Go to **Tools → Export Personal Data**
2. Enter user email address
3. Click **Send Request**
4. User receives email with download link

Exports include:
- User profile information
- Order history (as buyer or vendor)
- Messages and conversations
- Reviews written/received
- Earnings and withdrawals

### Database Query Results

Export query results using phpMyAdmin or database tools:

1. Access your database via phpMyAdmin
2. Navigate to the relevant table
3. Run SELECT query with filters
4. Click **Export** tab
5. Choose format (CSV, SQL, JSON)
6. Download results

## Common Export Needs

### Order Data

**Tables to Export:**
- `wp_wpss_orders` - Order records
- `wp_wpss_order_requirements` - Order requirements
- `wp_wpss_deliveries` - Final deliverables

**Useful Query:**
```sql
SELECT
    o.id,
    o.order_number,
    o.created_at,
    o.customer_id,
    o.vendor_id,
    o.total,
    o.vendor_earnings,
    o.platform_fee,
    o.status
FROM wp_wpss_orders o
WHERE o.created_at >= '2026-01-01'
ORDER BY o.created_at DESC
```

### Earnings Data

**For Accounting:**
```sql
SELECT
    vendor_id,
    COUNT(*) as order_count,
    SUM(total) as total_revenue,
    SUM(vendor_earnings) as vendor_earnings,
    SUM(platform_fee) as commission
FROM wp_wpss_orders
WHERE status = 'completed'
AND created_at BETWEEN '2026-01-01' AND '2026-01-31'
GROUP BY vendor_id
ORDER BY vendor_earnings DESC
```

### Vendor Performance

```sql
SELECT
    o.vendor_id,
    u.display_name,
    COUNT(o.id) as total_orders,
    SUM(CASE WHEN o.status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
    AVG(r.rating) as average_rating,
    COUNT(DISTINCT r.id) as total_reviews
FROM wp_wpss_orders o
LEFT JOIN wp_users u ON o.vendor_id = u.ID
LEFT JOIN wp_wpss_reviews r ON o.vendor_id = r.vendor_id
GROUP BY o.vendor_id
ORDER BY total_orders DESC
```

## Planned Pro Features

When data export is released, it will include:

### Export Formats
- CSV (Excel/Google Sheets compatible)
- PDF (formatted reports)
- JSON (API integration)

### Exportable Data
- Orders (all fields)
- Earnings summaries
- Vendor performance
- Service statistics
- Commission reports
- User activity

### Export Options
- Date range filtering
- Status filtering
- Custom field selection
- Automated scheduling
- Email delivery

### Cloud Integration
- Direct upload to cloud storage
- FTP/SFTP delivery
- Webhook notifications

## GDPR Compliance

### Data Export Requests

Handle GDPR export requests using WordPress:

1. **Tools → Export Personal Data**
2. Enter user email
3. WordPress generates ZIP file with:
   - Profile data
   - Order history
   - Messages
   - Reviews
   - Attachments

### Anonymizing Data

For analytics purposes, anonymize before export:

```php
function wpss_anonymize_order_data( $orders ) {
    foreach ( $orders as &$order ) {
        $order->customer_id = 0;
        $order->vendor_id = 0;
        $order->customer_name = 'Buyer #' . substr( md5( $order->customer_id ), 0, 8 );
        $order->vendor_name = 'Vendor #' . substr( md5( $order->vendor_id ), 0, 8 );
    }
    return $orders;
}
```

## Third-Party Export Plugins

Consider these WordPress plugins for export functionality:

**WP All Export Pro:**
- Export any custom post type or custom field
- Advanced filtering
- Scheduled exports
- Cloud storage integration

**Export WP Page to Static HTML:**
- Export pages to HTML
- Useful for archiving

**Advanced Database Cleaner:**
- Database export/backup
- Table optimization
- Cleanup old data

**Note:** These plugins work with WordPress data but require configuration for WP Sell Services custom tables.

## When Will Export Be Available?

Data export is on the Pro roadmap. Priority depends on:
- User demand and feedback
- Development capacity
- Feature completeness

**Want this feature?**
- Contact support to express interest
- Share your use case
- Help prioritize development

## Related Documentation

- [Admin Analytics](admin-analytics.md) - View analytics data
- [Vendor Analytics](vendor-analytics.md) - Vendor reports
- [Order Management](../order-management/order-lifecycle.md) - Understanding orders
- [Platform Settings](../platform-settings/advanced-settings.md) - System configuration

---

**Key Points:**
- No built-in export in current version
- Use custom PHP code or database queries
- WordPress privacy tools for user data exports
- Third-party plugins available for enhanced functionality
- Full export suite planned for Pro version
