<?php
/**
 * A charge that did not pay its order is never reported as a success.
 *
 * mark_as_paid() refuses a cancelled order (and can fail on a lost race), but
 * settle ignored its answer: the buyer was charged, sent to the requirements
 * page and the order stayed unpaid. Settle now asks the order as stored: a
 * recorded payment counts, anything else is an honest "contact support".
 *
 * Run: wp eval-file tests/test-settle-confirms-order-paid.php
 *
 * Inserts two QA orders and deletes them on any exit; nothing is charged.
 *
 * @package WPSellServices
 */

use WPSellServices\Checkout\CheckoutIntent;
use WPSellServices\Checkout\CheckoutIntentService;

$order_ids = array();
$restore   = null;
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
$orders = $wpdb->prefix . 'wpss_orders';
$buyer  = 7844;

$restore = static function () use ( $wpdb, $orders, &$order_ids ) {
	foreach ( $order_ids as $id ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $orders, array( 'id' => $id ) );
	}
};

$make = static function ( string $status, string $payment ) use ( $wpdb, $orders, $buyer, &$order_ids ): int {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		$orders,
		array(
			'order_number'   => 'QA-SETTLE-' . wp_generate_password( 6, false ),
			'customer_id'    => $buyer,
			'vendor_id'      => 7831,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'status'         => $status,
			'payment_status' => $payment,
			'subtotal'       => 10,
			'total'          => 10,
			'currency'       => 'USD',
			'created_at'     => current_time( 'mysql' ),
		)
	);
	$order_ids[] = (int) $wpdb->insert_id;

	return (int) $wpdb->insert_id;
};

$settle = static function ( int $order_id ) use ( $buyer ): array {
	$service = new CheckoutIntentService();
	$method  = new ReflectionMethod( $service, 'settle_order' );
	$intent  = CheckoutIntent::order( $order_id, 10.0, 'USD', $buyer, array() );

	return $method->invoke( $service, $intent, wpss_get_order_provider(), 'stripe', 'pi_qa_settle' );
};

// The closed-order notice would mail the owner; nothing here is a real payment.
remove_all_actions( 'wpss_payment_for_closed_order' );

$cancelled = $make( 'cancelled', 'pending' );
$result    = $settle( $cancelled );
$check( 'a charge that paid nothing is not a success', empty( $result['success'] ), wp_json_encode( $result ) );
$check( 'the buyer is given the payment reference', str_contains( (string) ( $result['error'] ?? '' ), 'pi_qa_settle' ), (string) ( $result['error'] ?? '' ) );

$paid   = $make( 'in_progress', 'paid' );
$result = $settle( $paid );
$check( 'a payment already recorded is a success', ! empty( $result['success'] ), wp_json_encode( $result ) );
