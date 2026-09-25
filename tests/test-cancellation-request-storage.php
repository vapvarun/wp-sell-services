<?php
/**
 * The cancellation request lives in its own place, not in vendor_notes.
 *
 * Run: wp eval-file tests/test-cancellation-request-storage.php
 *
 * The request - reason, note, who asked and WHEN - was JSON in vendor_notes,
 * the same column REST let the buyer PATCH. The 48-hour auto-cancel timer reads
 * requested_at from it, so a buyer who wrote {"requested_at":"2020-01-01"}
 * could have the order cancelled on the next cron run, skipping the vendor's
 * window (Basecamp 10336370631). It now lives in the order's meta.
 *
 * Every row this script creates is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;
use WPSellServices\Services\OrderWorkflowManager;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders      = $wpdb->prefix . 'wpss_orders';
$buyer       = get_user_by( 'login', 'wpss_buyer_ella' );
$vendor      = get_user_by( 'login', 'wpss_vendor_maya' );
$notif_floor = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}wpss_notifications" );

if ( ! $buyer || ! $vendor ) {
	echo "SKIP  needs the QA personas (bin/qa-fixtures.sh)\n";
	return;
}

$seed = static function ( string $status, ?string $vendor_notes = null ) use ( $wpdb, $orders, $buyer, $vendor ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'   => 'WPSS-CANCEL-STORAGE-' . wp_generate_password( 6, false ),
			'customer_id'    => $buyer->ID,
			'vendor_id'      => $vendor->ID,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'total'          => 40.000,
			'currency'       => 'USD',
			'status'         => $status,
			'payment_status' => 'paid',
			'vendor_notes'   => $vendor_notes,
			'started_at'     => current_time( 'mysql' ),
			'created_at'     => current_time( 'mysql' ),
		)
	);
	return (int) $wpdb->insert_id;
};
$row = static fn( int $id ) => $wpdb->get_row( $wpdb->prepare( "SELECT status, vendor_notes, meta FROM {$orders} WHERE id = %d", $id ) );

$ids = array();

try {
	// --- 1. A new request is stored in meta, not vendor_notes ------------------
	$a     = $seed( 'in_progress' );
	$ids[] = $a;
	wp_set_current_user( $buyer->ID );
	$res = ( new OrderService() )->request_cancellation( $a, $buyer->ID, 'changed_mind', 'Contract storage test.' );
	$r   = $row( $a );
	$meta = json_decode( (string) $r->meta, true );

	$check( 'the buyer can request cancellation', ! empty( $res['success'] ) && 'cancellation_requested' === $r->status );
	$check( '  the request is stored in meta', ! empty( $meta['cancellation_request']['requested_at'] ) );
	$check( '  and vendor_notes is left alone', empty( $r->vendor_notes ) );

	$order = ServiceOrder::find( $a );
	$check( '  and the model reads it back', method_exists( $order, 'get_cancellation_request' ) && 'changed_mind' === ( $order->get_cancellation_request()['reason'] ?? '' ) );

	// --- 2. Forging a date in vendor_notes no longer drives the timer ----------
	$wpdb->update( $orders, array( 'vendor_notes' => wp_json_encode( array( 'requested_at' => '2020-01-01 00:00:00' ) ) ), array( 'id' => $a ) );
	( new OrderWorkflowManager() )->process_cancellation_timeouts();
	$check( 'a 2020 requested_at written into vendor_notes does NOT auto-cancel the order', 'cancellation_requested' === $row( $a )->status );

	// --- 3. The real timer still works from meta -------------------------------
	$meta['cancellation_request']['requested_at'] = gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	$wpdb->update( $orders, array( 'meta' => wp_json_encode( $meta ) ), array( 'id' => $a ) );
	( new OrderWorkflowManager() )->process_cancellation_timeouts();
	$check( 'a request older than 48 hours in meta does auto-cancel', 'cancelled' === $row( $a )->status );

	// --- 4. The 1.8.0 migration moves an in-flight request ---------------------
	$legacy = wp_json_encode(
		array(
			'reason'       => 'taking_too_long',
			'note'         => 'legacy',
			'requested_by' => $buyer->ID,
			'requested_at' => current_time( 'mysql' ),
		)
	);
	$b     = $seed( 'cancellation_requested', $legacy );
	$ids[] = $b;

	$migrate = new ReflectionMethod( \WPSellServices\Database\SchemaManager::class, 'run_1_8_0_data_migrations' );
	$migrate->setAccessible( true );
	$migrate->invoke( new \WPSellServices\Database\SchemaManager() );

	$r    = $row( $b );
	$meta = json_decode( (string) $r->meta, true );
	$check( 'the migration moves an in-flight request into meta', 'taking_too_long' === ( $meta['cancellation_request']['reason'] ?? '' ) );
	$check( '  and clears it from vendor_notes', empty( $r->vendor_notes ) );

	$migrate->invoke( new \WPSellServices\Database\SchemaManager() );
	$check( '  and running it again changes nothing', 'taking_too_long' === ( json_decode( (string) $row( $b )->meta, true )['cancellation_request']['reason'] ?? '' ) );
} catch ( ReflectionException $e ) {
	echo 'FAIL  the 1.8.0 migration step does not exist: ' . $e->getMessage() . "\n";
	++$fails;
} finally {
	wp_set_current_user( 0 );
	foreach ( $ids as $id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_conversations', array( 'order_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $orders, array( 'id' => $id ) );
	}
	// Only the notifications about this script's orders: other sessions share the table.
	foreach ( $ids as $id ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE id > %d AND JSON_EXTRACT( data, '$.order_id' ) = %d", $notif_floor, $id ) );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
