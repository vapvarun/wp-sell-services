<?php
/**
 * Auto-complete counts from the latest delivery, and a revision retires the old one.
 *
 * Run: wp eval-file tests/test-auto-complete-latest-delivery.php
 *
 * Basecamp 10336731604: the sweep matched ANY pending delivery older than
 * auto_complete_days, and the REST revision action never retired the earlier
 * delivery, so an order re-delivered after a revision completed - and paid
 * out - on the next run, with no review window on the revised work.
 *
 * Throwaway buyer, vendor and orders; every row is removed at the end.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders     = $wpdb->prefix . 'wpss_orders';
$deliveries = $wpdb->prefix . 'wpss_deliveries';

$days = static fn( $settings ) => array_merge( is_array( $settings ) ? $settings : array(), array( 'auto_complete_days' => 3 ) );
add_filter( 'option_wpss_orders', $days );

$buyer  = wp_insert_user( array( 'user_login' => 'dev_f1_ac_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_ac_buyer@example.test' ) );
$vendor = wp_insert_user( array( 'user_login' => 'dev_f1_ac_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_ac_vendor@example.test' ) );
$made   = array();

$order = static function ( string $status ) use ( $wpdb, $orders, $buyer, $vendor, &$made ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'   => 'DEVF1-AC-' . wp_generate_password( 6, false ),
			'customer_id'    => $buyer,
			'vendor_id'      => $vendor,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'status'         => $status,
			'payment_status' => 'paid',
			'subtotal'       => 50,
			'total'          => 50,
			'currency'       => wpss_get_currency(),
			'revisions_included' => 2,
			'paid_at'        => current_time( 'mysql' ),
			'created_at'     => current_time( 'mysql' ),
		)
	);
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};

$deliver = static function ( int $order_id, int $days_ago ) use ( $wpdb, $deliveries, $vendor ): int {
	$wpdb->insert(
		$deliveries,
		array(
			'order_id'   => $order_id,
			'vendor_id'  => $vendor,
			'message'    => 'dev_f1 delivery',
			'status'     => 'pending',
			'created_at' => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $days_ago * DAY_IN_SECONDS ),
		)
	);
	return (int) $wpdb->insert_id;
};

$status = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

try {
	// A: delivered 5 days ago, revised, re-delivered today.
	$revised = $order( 'pending_approval' );
	$deliver( $revised, 5 );
	$deliver( $revised, 0 );

	// B (control): one delivery, 5 days old.
	$stale = $order( 'pending_approval' );
	$deliver( $stale, 5 );

	( new \WPSellServices\Services\OrderWorkflowManager() )->auto_complete_orders();

	$check( sprintf( 'A: re-delivered today is NOT auto-completed (status %s)', $status( $revised ) ), 'pending_approval' === $status( $revised ) );
	$check( sprintf( 'B: latest delivery 5 days old IS auto-completed (status %s)', $status( $stale ) ), 'completed' === $status( $stale ) );

	// C: a revision asked for over REST retires the open delivery and fires the hook once.
	$via_rest = $order( 'pending_approval' );
	$first    = $deliver( $via_rest, 1 );
	$fired    = 0;
	$count    = static function () use ( &$fired ) {
		++$fired;
	};
	add_action( 'wpss_revision_requested', $count );

	wp_set_current_user( $buyer );
	$request = new WP_REST_Request( 'POST', '/wpss/v1/orders/' . $via_rest . '/revision' );
	$request->set_param( 'reason', 'dev_f1: tweak the colours' );
	$response = rest_do_request( $request );
	wp_set_current_user( 0 );
	remove_action( 'wpss_revision_requested', $count );

	$row = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$deliveries} WHERE id = %d", $first ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( sprintf( 'C: REST revision -> order revision_requested (HTTP %d, %s)', $response->get_status(), $status( $via_rest ) ), 'revision_requested' === $status( $via_rest ) );
	$check( sprintf( 'C: the earlier delivery is retired (%s)', $row ), 'revision_requested' === $row );
	$check( sprintf( 'C: wpss_revision_requested fired once (%d)', $fired ), 1 === $fired );
} finally {
	foreach ( $made as $id ) {
		$wpdb->delete( $deliveries, array( 'order_id' => $id ) );
		$wpdb->delete( $orders, array( 'id' => $id ) );
		// Rows other services write for these orders.
		foreach ( array( 'wpss_notifications', 'wpss_audit_log' ) as $t ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}{$t} WHERE JSON_EXTRACT(data, '$.order_id') = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
	remove_filter( 'option_wpss_orders', $days );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
