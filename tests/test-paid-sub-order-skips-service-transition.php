<?php
/**
 * A paid tip, extension or milestone phase is not pushed through the service
 * order's "payment received" transition. Its own wpss_order_paid handler owns
 * its status; the generic handler used to move it first and fire status
 * hooks and history for a step that never applies to it.
 *
 * Run: wp eval-file tests/test-paid-sub-order-skips-service-transition.php
 *
 * Inserts one QA tip order; status listeners are removed for the run so
 * nothing is mailed; the order is deleted on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\OrderWorkflowManager;

$order_id = 0;
$restore  = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$f      = wpss_test_fixtures();

$restore = static function () use ( $wpdb, $orders, &$order_id ) {
	if ( $order_id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $orders, array( 'id' => $order_id ) );
	}
};

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->insert(
	$orders,
	array(
		'order_number'   => 'QA-SUB-' . wp_generate_password( 6, false ),
		'customer_id'    => $f->buyer,
		'vendor_id'      => $f->vendor,
		'service_id'     => 0,
		'platform'       => 'tip',
		'status'         => 'pending_payment',
		'payment_status' => 'paid',
		'subtotal'       => 5,
		'total'          => 5,
		'currency'       => 'USD',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

remove_all_actions( 'wpss_order_status_changed' );
$fired = 0;
add_action(
	'wpss_order_status_changed',
	static function () use ( &$fired ) {
		++$fired;
	}
);

( new OrderWorkflowManager() )->handle_payment_complete( $order_id, 'txn_qa_sub' );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$status = (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $order_id ) );
$check( 'the paid tip keeps the status its own handler sets', 'pending_payment' === $status, $status );
$check( 'no status change is announced for it', 0 === $fired, (string) $fired );
