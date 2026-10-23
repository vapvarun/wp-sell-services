<?php
/**
 * Order counts count orders, not their tips, extensions or milestone phases.
 *
 * One real order plus three sub-orders for a throwaway buyer and vendor, read
 * through every order count the review found counting sub-orders: the
 * [wpss_my_orders] helpers, the admin dashboard tiles, the revenue rows' order
 * count (revenue itself still sums every paid row), the vendor stats on-time
 * rate, the /dashboard buyer stats and the service page queue.
 *
 * Run: wp eval-file tests/test-order-counts-skip-sub-orders.php
 *
 * Removes its orders on any exit; the users are swept by exit-on-fail.
 *
 * @package WPSellServices
 */

use WPSellServices\Database\Repositories\OrderRepository;
use WPSellServices\Models\ServiceOrder;

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

global $wpdb;
$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$orders = $wpdb->prefix . 'wpss_orders';
$made   = array();
register_shutdown_function(
	static function () use ( $wpdb, $orders, &$made ) {
		foreach ( $made as $id ) {
			$wpdb->delete( $orders, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		delete_transient( 'wpss_order_aggregates' );
	}
);

$buyer  = (int) wp_create_user( 'qa_cnt_buyer_' . wp_rand(), wp_generate_password(), 'qa-cnt-buyer-' . wp_rand() . '@example.test' );
$vendor = (int) wp_create_user( 'qa_cnt_vendor_' . wp_rand(), wp_generate_password(), 'qa-cnt-vendor-' . wp_rand() . '@example.test' );

$repo           = new OrderRepository();
$total_before   = (int) $repo->get_aggregates()->total;
$deadline       = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
$done           = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
$rows           = array(
	// [platform, status, total].
	array( 'standalone', ServiceOrder::STATUS_COMPLETED, 100 ),
	array( ServiceOrder::SUB_ORDER_TYPE_TIP, ServiceOrder::STATUS_COMPLETED, 5 ),
	array( ServiceOrder::SUB_ORDER_TYPE_EXTENSION, ServiceOrder::STATUS_COMPLETED, 20 ),
	array( ServiceOrder::SUB_ORDER_TYPE_MILESTONE, ServiceOrder::STATUS_IN_PROGRESS, 30 ),
);
foreach ( $rows as list( $platform, $status, $total ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		$orders,
		array(
			'order_number'      => 'QA-CNT-' . wp_rand(),
			'customer_id'       => $buyer,
			'vendor_id'         => $vendor,
			'service_id'        => 0,
			'platform'          => $platform,
			'subtotal'          => $total,
			'total'             => $total,
			'vendor_earnings'   => $total * 0.9,
			'platform_fee'      => $total * 0.1,
			'currency'          => 'USD',
			'status'            => $status,
			'payment_status'    => 'paid',
			'paid_at'           => $done,
			'delivery_deadline' => $deadline,
			'completed_at'      => ServiceOrder::STATUS_COMPLETED === $status ? $done : null,
			'created_at'        => $done,
		)
	);
	$made[] = (int) $wpdb->insert_id;
}

// [wpss_my_orders] helpers.
$check( 'buyer order count is 1', 1 === wpss_count_user_orders( $buyer ), (string) wpss_count_user_orders( $buyer ) );
$check( 'vendor order count is 1', 1 === wpss_count_vendor_orders( $vendor ), (string) wpss_count_vendor_orders( $vendor ) );
$check( 'vendor completed count is 1', 1 === wpss_count_vendor_orders( $vendor, 'completed' ), (string) wpss_count_vendor_orders( $vendor, 'completed' ) );
$check( 'buyer order list has 1 row', 1 === count( wpss_get_user_orders( $buyer ) ), (string) count( wpss_get_user_orders( $buyer ) ) );
$check( 'vendor order list has 1 row', 1 === count( wpss_get_vendor_orders( $vendor ) ), (string) count( wpss_get_vendor_orders( $vendor ) ) );

// Admin dashboard tiles.
$total_after = (int) $repo->get_aggregates()->total;
$check( 'dashboard order total grows by 1', 1 === $total_after - $total_before, (string) ( $total_after - $total_before ) );

// Revenue rows: orders counted once, money from every paid row.
$money = wpss_get_revenue( array( 'vendor_id' => $vendor ) )[0] ?? null;
$check( 'revenue row counts 1 order', $money && 1 === (int) $money->orders, $money ? (string) $money->orders : 'none' );
$check( 'revenue still sums the tip and extension', $money && abs( (float) $money->gross - 155.0 ) < 0.01, $money ? (string) $money->gross : 'none' );

// Vendor stats: on-time cannot pass 100%.
$stats = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/vendors/' . $vendor . '/stats' ) )->get_data();
$check( 'vendor on-time rate is 100, not above', 100.0 === (float) ( $stats['on_time_rate'] ?? -1 ), wp_json_encode( $stats['on_time_rate'] ?? null ) );

// /dashboard buyer stats.
wp_set_current_user( $buyer );
$dash = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/dashboard' ) )->get_data();
wp_set_current_user( 0 );
$check( 'dashboard buyer total_orders is 1', 1 === (int) ( $dash['as_customer']['total_orders'] ?? -1 ), wp_json_encode( $dash['as_customer'] ?? null ) );

// Service page queue: the in-progress milestone is not a queued order.
$view  = ( new ReflectionClass( \WPSellServices\Frontend\SingleServiceView::class ) )->newInstanceWithoutConstructor();
$queue = ( new ReflectionMethod( $view, 'get_queue_count' ) )->invoke( $view, $vendor );
$check( 'service page queue is 0', 0 === $queue, (string) $queue );
