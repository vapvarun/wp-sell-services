<?php
/**
 * Contract totals for a milestone parent, and the offline sweep exemptions.
 *
 * Run: wp eval-file tests/test-milestone-contract-totals.php
 *
 * Basecamp 10421776346: a milestone parent carries total 0, so the admin
 * Financial Summary showed $0.00. MilestoneService::get_contract_totals() sums
 * the phases; cancelled and rejected phases are out, only paid phases count as
 * paid. Offline sub-orders must survive the abandoned-order sweeps.
 *
 * Throwaway rows; every one is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\MilestoneService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$made   = array();

$row = static function ( array $over ) use ( $wpdb, $orders, &$made ): int {
	$wpdb->insert(
		$orders,
		array_merge(
			array(
				'order_number'    => 'DEVTOTALS-' . wp_generate_password( 6, false ),
				'customer_id'     => 1,
				'vendor_id'       => 1,
				'service_id'      => 0,
				'status'          => 'in_progress',
				'payment_status'  => 'pending',
				'subtotal'        => 0,
				'total'           => 0,
				'platform_fee'    => 0,
				'vendor_earnings' => 0,
				'currency'        => 'USD',
				'created_at'      => current_time( 'mysql' ),
			),
			$over
		)
	);
	$made[] = (int) $wpdb->insert_id;

	return (int) $wpdb->insert_id;
};

$parent = $row( array() );
$phase  = static fn( float $amt, string $pay, string $status, float $fee ) => $row(
	array(
		'platform'          => 'milestone',
		'platform_order_id' => $parent,
		'status'            => $status,
		'payment_status'    => $pay,
		'paid_at'           => 'paid' === $pay ? current_time( 'mysql' ) : null,
		'subtotal'          => $amt,
		'total'             => $amt,
		'platform_fee'      => $fee,
		'vendor_earnings'   => $amt - $fee,
	)
);

$phase( 100, 'paid', 'in_progress', 10 );
$phase( 200, 'pending', 'pending_payment', 20 );
$phase( 50, 'pending', 'cancelled', 5 );
$phase( 40, 'refunded', 'refunded', 4 );

$t = ( new MilestoneService() )->get_contract_totals( $parent );
$check( 'contract total skips cancelled phases', 340.0 === (float) $t['total'] );
$check( 'paid counts only paid, non-refunded phases', 100.0 === (float) $t['paid'] );
$check( 'commission is on paid phases only', 10.0 === (float) $t['commission'] );
$check( 'earnings are on paid phases only', 90.0 === (float) $t['earnings'] );
$check( 'phase count skips cancelled', 3 === (int) $t['count'] );

// Offline sub-order abandoned for 3 days: the sweep must leave it alone.
$old = $row(
	array(
		'platform'          => 'milestone',
		'platform_order_id' => $parent,
		'status'            => 'pending_payment',
		'payment_method'    => 'offline',
		'subtotal'          => 10,
		'total'             => 10,
		'created_at'        => gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS ),
	)
);
( new MilestoneService() )->cleanup_abandoned_milestones();
$check( 'offline milestone survives the abandoned sweep', (bool) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d AND status <> 'cancelled'", $old ) ) ); // phpcs:ignore WordPress.DB

foreach ( $made as $id ) {
	$wpdb->delete( $orders, array( 'id' => $id ) );
}

exit( $fails ? 1 : 0 );
