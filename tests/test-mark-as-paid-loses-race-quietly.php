<?php
/**
 * Two requests recording the same payment (webhook plus return page) fire the
 * paid hooks once: the loser's write names the payment status it read, matches
 * nothing, stays silent and reports that the order is paid.
 *
 * Run: wp eval-file tests/test-mark-as-paid-loses-race-quietly.php
 *
 * Inserts one QA order, removes the paid listeners for the run so nothing is
 * mailed or credited, and deletes the order on any exit.
 *
 * @package WPSellServices
 */

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

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';

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
		'order_number'   => 'QA-RACE-' . wp_generate_password( 6, false ),
		'customer_id'    => 7844,
		'vendor_id'      => 7831,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'status'         => 'pending_payment',
		'payment_status' => 'pending',
		'subtotal'       => 10,
		'total'          => 10,
		'currency'       => 'USD',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

foreach ( array( 'wpss_order_paid', 'wpss_order_status_changed', 'wpss_order_status_pending_requirements', 'wpss_order_status_in_progress' ) as $hook ) {
	remove_all_actions( $hook );
}

$fired = 0;
$count = static function () use ( &$fired ) {
	++$fired;
};
add_action( 'wpss_order_paid', $count );
add_action( 'wpss_order_status_changed', $count );

// The other request records the payment just before this one's UPDATE runs.
$raced  = false;
$racing = static function ( $query ) use ( &$racing, &$raced, $wpdb, $orders, $order_id ) {
	if ( ! $raced && 0 === strpos( ltrim( $query ), 'UPDATE `' . $orders . '`' ) && false !== strpos( $query, 'transaction_id' ) ) {
		$raced = true;
		remove_filter( 'query', $racing );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$orders} SET payment_status = 'paid', status = 'in_progress' WHERE id = %d", $order_id ) );
	}

	return $query;
};
add_filter( 'query', $racing );

$answer = ( new \WPSellServices\Integrations\Standalone\StandaloneOrderProvider() )->mark_as_paid( $order_id, 'txn_qa_race', 'stripe' );
remove_filter( 'query', $racing );

$check( 'the race was staged', $raced );
$check( 'the loser reports the order is paid', true === $answer );
$check( 'the loser fires no paid or status hook', 0 === $fired, (string) $fired );
