<?php
/**
 * Every "completed orders" number counts real orders only: a completed tip or
 * extension never raises the seller card, the REST stats, the seller level,
 * the reviews summary or the profile row, and the reviews summary response rate
 * never passes 100.
 *
 * Run: wp eval-file tests/test-order-counts-ignore-sub-orders.php
 *
 * Clones the latest order into one completed tip and one completed extension
 * for its vendor, then deletes them and restores the vendor profile row on any
 * exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Database\Repositories\OrderRepository;

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

global $wpdb;
$orders   = $wpdb->prefix . 'wpss_orders';
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$cloned   = array();
$reviews  = $wpdb->prefix . 'wpss_reviews';
$review_ids = array();
$profile  = null;

$restore = static function () use ( $wpdb, $orders, $profiles, $reviews, &$cloned, &$review_ids, &$profile ) {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	foreach ( $cloned as $id ) {
		$wpdb->delete( $orders, array( 'id' => $id ) );
	}
	foreach ( $review_ids as $id ) {
		$wpdb->delete( $reviews, array( 'id' => $id ) );
	}
	if ( $profile ) {
		$wpdb->update( $profiles, $profile, array( 'id' => $profile['id'] ) );
	}
	// phpcs:enable
};

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$base = $wpdb->get_row( "SELECT * FROM {$orders} WHERE platform NOT IN ('tip','extension','milestone') AND vendor_id > 0 ORDER BY id DESC LIMIT 1", ARRAY_A );
if ( ! $base ) {
	echo "SKIP  needs one catalog order on this site\n";
	return;
}
$vendor = (int) $base['vendor_id'];

foreach ( array( 'tip', 'extension' ) as $platform ) {
	$row                   = $base;
	$row['order_number']   = 'QA-CNT-' . wp_generate_password( 6, false );
	$row['platform']       = $platform;
	$row['status']         = 'completed';
	$row['payment_status'] = 'paid';
	unset( $row['id'] );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert( $orders, $row );
	$cloned[] = (int) $wpdb->insert_id;
}
$check( 'a completed tip and extension were added', 2 === count( array_filter( $cloned ) ) );

$canon = ( new OrderRepository() )->get_vendor_stats( $vendor );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$raw = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders} WHERE vendor_id = %d AND status = 'completed'", $vendor ) );
$check( 'the fixture bites: the raw completed count is above the real one', $raw > $canon['completed_orders'], $raw . ' vs ' . $canon['completed_orders'] );

// Approved reviews with no order (imported or marketplace): more reviews than completed orders.
for ( $i = 0; $i <= $canon['completed_orders']; $i++ ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		$reviews,
		array(
			'order_id'    => null,
			'reviewer_id' => 1,
			'reviewee_id' => $vendor,
			'service_id'  => (int) $base['service_id'],
			'customer_id' => 1,
			'vendor_id'   => $vendor,
			'rating'      => 5,
			'status'      => 'approved',
			'review_type' => 'customer_to_vendor',
		)
	);
	$review_ids[] = (int) $wpdb->insert_id;
}

wp_set_current_user( 1 );

// 1. Seller card.
$card  = new ReflectionMethod( \WPSellServices\Blocks\SellerCard::class, 'get_seller_stats' );
$stats = $card->invoke( new \WPSellServices\Blocks\SellerCard(), $vendor );
$check( 'seller card counts real orders only', $canon['completed_orders'] === (int) $stats['orders_completed'], (string) $stats['orders_completed'] );

// 2. REST vendor stats.
$res = rest_do_request( new WP_REST_Request( 'GET', "/wpss/v1/vendors/{$vendor}/stats" ) )->get_data();
$check( 'REST stats count real orders only', $canon['completed_orders'] === (int) ( $res['completed_orders'] ?? -1 ) && $canon['total_orders'] === (int) ( $res['total_orders'] ?? -1 ), wp_json_encode( array( $res['total_orders'] ?? null, $res['completed_orders'] ?? null ) ) );

// 3. Seller level.
$level = ( new \WPSellServices\Services\SellerLevelService() )->get_vendor_stats( $vendor );
if ( $level ) {
	$check( 'seller level counts real orders only', $canon['completed_orders'] === (int) $level->completed_orders, (string) $level->completed_orders );
}

// 4. Reviews summary: same count, response rate within 0-100.
$sum = rest_do_request( new WP_REST_Request( 'GET', "/wpss/v1/vendors/{$vendor}/reviews/summary" ) )->get_data();
$check( 'reviews summary counts real orders only', $canon['completed_orders'] === (int) ( $sum['completed_orders'] ?? -1 ), (string) ( $sum['completed_orders'] ?? '' ) );
$check( 'response rate stays between 0 and 100', isset( $sum['response_rate'] ) && $sum['response_rate'] >= 0 && $sum['response_rate'] <= 100, (string) ( $sum['response_rate'] ?? '' ) );

// 5. The profile row written after an order completes.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$profile = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$profiles} WHERE user_id = %d", $vendor ), ARRAY_A );
if ( $profile ) {
	$write = new ReflectionMethod( \WPSellServices\Services\OrderWorkflowManager::class, 'update_single_vendor_stats' );
	$write->invoke( new \WPSellServices\Services\OrderWorkflowManager(), $vendor );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$written = (int) $wpdb->get_var( $wpdb->prepare( "SELECT completed_orders FROM {$profiles} WHERE user_id = %d", $vendor ) );
	$check( 'the profile row is written with real orders only', $canon['completed_orders'] === $written, (string) $written );
}
