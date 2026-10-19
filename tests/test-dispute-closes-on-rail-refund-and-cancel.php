<?php
/**
 * A rail refund closes the dispute naming the rail, not "admin", and cancelling a disputed paid
 * order closes its dispute once the buyer's money is back (Basecamp 10415811050).
 *
 * Run: wp eval-file tests/test-dispute-closes-on-rail-refund-and-cancel.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\DisputeService;
use WPSellServices\Services\OrderService;
use WPSellServices\Services\OrderWorkflowManager;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$buyer  = 999997;
$vendor = 999996;

$seed = static function () use ( $wpdb, $orders, $buyer, $vendor ): int {
	$wpdb->insert(
		$orders,
		array(
			'order_number'      => 'WPSS-DSP-' . wp_generate_password( 6, false ),
			'customer_id'       => $buyer,
			'vendor_id'         => $vendor,
			'service_id'        => 0,
			'platform'          => 'standalone',
			'subtotal'          => 30,
			'total'             => 30,
			'currency'          => 'USD',
			'status'            => 'in_progress',
			'payment_status'    => 'paid',
			'payment_method'    => 'stripe',
			'transaction_id'    => 'txn_dsp_' . wp_generate_password( 8, false ),
			'created_at'        => current_time( 'mysql' ),
		)
	);
	return (int) $wpdb->insert_id;
};

$mock = static function ( $handled, $order ) use ( $vendor ) {
	return (int) $order->vendor_id === $vendor ? array( 'success' => true, 'refund_id' => 'REF-' . $order->id ) : $handled;
};
add_filter( 'wpss_pre_process_gateway_refund', $mock, 10, 2 );

$disputes = new DisputeService();
$ids      = array();
$dids     = array();
$row      = static fn( int $did ) => $wpdb->get_row( $wpdb->prepare( "SELECT status, resolution, resolution_notes FROM {$wpdb->prefix}wpss_disputes WHERE id = %d", $did ) );

try {
	// Rail refund.
	$id    = $seed();
	$ids[] = $id;
	$dids[] = $did = (int) $disputes->open( $id, $buyer, 'other', 'rail refund dispute' );
	$txn   = (string) $wpdb->get_var( $wpdb->prepare( "SELECT transaction_id FROM {$orders} WHERE id = %d", $id ) );
	( new OrderWorkflowManager() )->handle_gateway_refund( 'stripe', $txn, 30.0, array( 'order_id' => $id, 'currency' => 'USD', 'cumulative' => true, 'refund_id' => 're_dsp_' . $id ) );
	$state = $row( $did );
	$check( 'rail refund resolves the dispute', $state && 'resolved' === $state->status && 'full_refund' === $state->resolution );
	$check( 'closing note names the rail, not admin', $state && str_contains( (string) $state->resolution_notes, '(stripe)' ) && ! str_contains( (string) $state->resolution_notes, '(admin)' ) );

	// Cancel a disputed paid order.
	$id    = $seed();
	$ids[] = $id;
	$dids[] = $did = (int) $disputes->open( $id, $buyer, 'other', 'cancel dispute' );
	( new OrderService() )->update_status( $id, 'cancelled' );
	$state = $row( $did );
	$check( 'cancelling a disputed paid order resolves the dispute', $state && 'resolved' === $state->status );
	$check( 'closing note names the cancel', $state && str_contains( (string) $state->resolution_notes, '(cancel)' ) );
} finally {
	remove_filter( 'wpss_pre_process_gateway_refund', $mock, 10 );
	foreach ( $dids as $did ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_disputes', array( 'id' => $did ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_dispute_messages', array( 'dispute_id' => $did ) );
	}
	foreach ( $ids as $oid ) {
		$wpdb->delete( $orders, array( 'id' => $oid ) );
	}
}

exit( $fails ? 1 : 0 );
