<?php
/**
 * A closed (cancelled or refunded) order is never paid: not by a late gateway callback, and no store
 * pay link is handed out for it. A declined milestone phase was revived to paid under a completed
 * parent through a stale WooCommerce link (combo walk, 1.9.0).
 *
 * Run: wp eval-file tests/test-closed-order-cannot-be-paid.php
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

global $wpdb;
$f      = wpss_test_fixtures();
$parent = ( $f->order )( array( 'status' => 'in_progress', 'platform' => 'request', 'customer_id' => $f->buyer, 'vendor_id' => $f->vendor, 'service_id' => ( $f->service )( 'publish' ), 'total' => 100 ) );

$milestones = new \WPSellServices\Services\MilestoneService();
$milestones->propose( $parent, $f->vendor, 'Phase one', 'First part', 20, 3 );
$ids = array_map( static fn( $r ) => (int) $r['id'], $milestones->get_for_parent( $parent ) );
foreach ( $ids as $id ) {
	$f->orders[] = $id;
}
$phase = $ids[0] ?? 0;
$check( 'a phase exists to decline', $phase > 0 );

$declined = $milestones->decline( $phase, $f->buyer );
$check( 'the buyer declined the phase', ! empty( $declined['success'] ) );

$closed_event = 0;
$spy          = static function () use ( &$closed_event ) {
	++$closed_event;
};
add_action( 'wpss_payment_for_closed_order', $spy );

$paid = ( new \WPSellServices\Integrations\Standalone\StandaloneOrderProvider() )->mark_as_paid( $phase, 'qa_late_callback', 'stripe' );
$row  = wpss_get_order( $phase );
$check( 'a late payment does not mark a cancelled phase paid', false === $paid );
$check( 'the phase stays cancelled and unpaid', 'cancelled' === $row->status && 'paid' !== ( $row->payment_status ?? '' ) );
$check( 'the late payment is announced so the owner can refund it', 1 === $closed_event );
remove_action( 'wpss_payment_for_closed_order', $spy );

$reached = 0;
$rail    = static function ( $url ) use ( &$reached ) {
	++$reached;
	return $url;
};
add_filter( 'wpss_ensure_pay_order', $rail );
$url = wpss_ensure_pay_order( $phase );
remove_filter( 'wpss_ensure_pay_order', $rail );
$check( 'no store pay link is made for a cancelled order', 0 === $reached && false === strpos( $url, 'order-pay' ) );

exit( $fails ? 1 : 0 );
