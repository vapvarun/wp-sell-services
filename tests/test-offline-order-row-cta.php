<?php
/**
 * My Orders does not ask an offline-payment buyer to "Complete payment"
 * (Basecamp 10546216275).
 *
 * The order page hides Pay for an order waiting on the owner to confirm a
 * bank transfer (wpss_order_awaits_payment_confirmation()); the list row
 * still labelled it "Complete payment". Both now read the same rule.
 *
 * Run: wp eval-file tests/test-offline-order-row-cta.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$f   = wpss_test_fixtures();
$row = static function ( array $overrides, string $side = 'buyer' ) use ( $f ): string {
	$id = ( $f->order )(
		$overrides + array(
			'status'         => 'pending_payment',
			'payment_status' => 'pending',
			'paid_at'        => null,
		)
	);
	// The raw row, as the My Orders list passes it.
	$order_item    = $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( "SELECT * FROM {$f->orders_t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpss_side     = $side;
	$status_labels = wpss_get_order_status_labels();
	ob_start();
	wpss_get_template( 'dashboard/partials/order-row.php', compact( 'order_item', 'wpss_side', 'status_labels' ) );
	return (string) ob_get_clean();
};

$offline = $row( array( 'payment_method' => 'offline' ) );
$check( 'an offline order waiting on the owner says "View payment instructions"', false !== strpos( $offline, 'View payment instructions' ) );
$check( '  and not "Complete payment"', false === strpos( $offline, 'Complete payment' ) );

$unpaid = $row( array( 'payment_method' => '' ) );
$check( 'an order with no payment started still says "Complete payment"', false !== strpos( $unpaid, 'Complete payment' ) );

$seller = $row( array( 'payment_method' => 'offline' ), 'seller' );
$check( 'the seller row offers neither', false === strpos( $seller, 'View payment instructions' ) && false === strpos( $seller, 'Complete payment' ) );

exit( $fails ? 1 : 0 );
