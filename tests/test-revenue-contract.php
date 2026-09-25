<?php
/**
 * Revenue is one number: paid GMV minus refunds, the same on every surface.
 *
 * Run: wp eval-file tests/test-revenue-contract.php
 *
 * Owner decision (Basecamp 10337156274): revenue is what buyers paid, net of
 * refunds; unpaid, cancelled and pending-payment orders never count; platform
 * commission is a second figure. The admin Dashboard summed completed orders,
 * Pro Analytics summed everything not cancelled (pending payment included, so
 * "This Month" read 3x the all-time figure), and the vendor screens each had
 * their own copy. wpss_get_revenue() is now the one definition.
 *
 * This is also the seed script QA's step 1 asks for: it builds a known month
 * in March 2031 (so no real row falls in it) for a throwaway vendor -
 *   A  paid $100, completed            fee 10  vendor 90
 *   B  paid $50, in progress           fee 5   vendor 45
 *   C  paid $80, then fully refunded   fee 8   vendor 72
 *   D  paid $60, $20 refunded          fee 6   vendor 54
 *   E  $70 pending payment
 *   F  $30 pending payment
 *   G  $40 cancelled
 * - so revenue is 100 + 50 + 0 + 40 = 190, commission 10 + 5 + 0 + 4 = 19 and
 * the vendor's share 90 + 45 + 0 + 36 = 171. Every row is removed at the end.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$money = static fn( $a, $b ) => abs( (float) $a - (float) $b ) < 0.01;

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$buyer  = wp_insert_user( array( 'user_login' => 'dev_f1_rev_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_rev_buyer@example.test' ) );
$vendor = wp_insert_user( array( 'user_login' => 'dev_f1_rev_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_rev_vendor@example.test' ) );
$made   = array();

$seed = static function ( string $label, array $row ) use ( $wpdb, $orders, $buyer, $vendor, &$made ): int {
	$wpdb->insert(
		$orders,
		$row + array(
			'order_number'   => 'DEVF1-REV-' . $label . '-' . wp_generate_password( 4, false ),
			'customer_id'    => $buyer,
			'vendor_id'      => $vendor,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'currency'       => wpss_get_currency(),
			'subtotal'       => $row['total'],
			'payment_method' => 'offline',
		)
	);
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};

try {
	$seed( 'A', array( 'total' => 100, 'status' => 'completed', 'payment_status' => 'paid', 'paid_at' => '2031-03-05 10:00:00', 'created_at' => '2031-03-05 09:00:00', 'platform_fee' => 10, 'vendor_earnings' => 90 ) );
	$seed( 'B', array( 'total' => 50, 'status' => 'in_progress', 'payment_status' => 'paid', 'paid_at' => '2031-03-06 10:00:00', 'created_at' => '2031-03-06 09:00:00', 'platform_fee' => 5, 'vendor_earnings' => 45 ) );
	$seed( 'C', array( 'total' => 80, 'status' => 'refunded', 'payment_status' => 'refunded', 'paid_at' => '2031-03-07 10:00:00', 'created_at' => '2031-03-07 09:00:00', 'refunded_amount' => 80, 'platform_fee' => 8, 'vendor_earnings' => 72 ) );
	$seed( 'D', array( 'total' => 60, 'status' => 'partially_refunded', 'payment_status' => 'paid', 'paid_at' => '2031-03-08 10:00:00', 'created_at' => '2031-03-08 09:00:00', 'refunded_amount' => 20, 'platform_fee' => 6, 'vendor_earnings' => 54 ) );
	$e = $seed( 'E', array( 'total' => 70, 'status' => 'pending_payment', 'payment_status' => 'pending', 'created_at' => '2031-03-09 09:00:00' ) );
	$seed( 'F', array( 'total' => 30, 'status' => 'pending_payment', 'payment_status' => 'pending', 'created_at' => '2031-03-09 10:00:00' ) );
	$seed( 'G', array( 'total' => 40, 'status' => 'cancelled', 'payment_status' => 'paid', 'paid_at' => '2031-03-10 10:00:00', 'created_at' => '2031-03-10 09:00:00', 'platform_fee' => 4, 'vendor_earnings' => 36 ) );

	$month = array(
		'from' => '2031-03-01 00:00:00',
		'to'   => '2031-03-31 23:59:59',
	);

	// 1. The math matches the hand count.
	$r = wpss_get_revenue( $month )[0];
	$check( sprintf( '1. revenue for the month is 190 (got %s)', $r->revenue ), $money( $r->revenue, 190 ) );
	$check( sprintf( '1. refunds 100, gross 290 (got %s, %s)', $r->refunded, $r->gross ), $money( $r->refunded, 100 ) && $money( $r->gross, 290 ) );
	$check( sprintf( '1. commission is 19 (got %s)', $r->commission ), $money( $r->commission, 19 ) );
	$check( sprintf( '1. the vendor share is 171 (got %s)', $r->vendor_earnings ), $money( $r->vendor_earnings, 171 ) );

	// 2. A period is never more than all time.
	$check( '2. the month is not more than all time', wpss_get_revenue()[0]->revenue >= $r->revenue );

	// 6. The chart: every day present, never negative, and it sums to the month.
	$series = wpss_get_revenue_series( '2031-03-01', '2031-03-31' );
	$check( '6. the chart has all 31 days, none negative, summing to 190', 31 === count( $series['labels'] ) && min( $series['revenue'] ) >= 0 && $money( array_sum( $series['revenue'] ), 190 ) );

	// 8. Vendor side.
	$stats = ( new \WPSellServices\Database\Repositories\OrderRepository() )->get_vendor_stats( $vendor );
	$check( sprintf( '8. Sales "Earnings" is the vendor share, 171 (got %s)', $stats['total_earnings'] ), $money( $stats['total_earnings'], 171 ) );
	$summary = ( new \WPSellServices\Services\CommissionService() )->get_vendor_summary( $vendor );
	$check( '8. admin vendor Earnings tab: revenue 190, commission 19, net 171', $money( $summary['total_revenue'], 190 ) && $money( $summary['total_commission'], 19 ) && $money( $summary['net_earnings'], 171 ) );
	$pending = wpss_get_order_revenue( $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$orders} WHERE id = %d", $e ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( '8. a pending-payment row earns nothing', ! $pending->counts && 0.0 === $pending->vendor_earnings );

	// Pro: every Analytics surface reads the same numbers.
	if ( class_exists( '\WPSellServicesPro\Analytics\AnalyticsManager' ) ) {
		\WPSellServicesPro\Analytics\AnalyticsManager::flush_cache();
		$manager = \WPSellServicesPro\Analytics\AnalyticsManager::instance();
		if ( ! $manager->get_data( '2031-03-01', '2031-03-31' ) ) {
			$manager->init(); // Collectors register when the admin page boots; not on the CLI.
		}
		$data = $manager->get_data( '2031-03-01', '2031-03-31' );

		$check( sprintf( '1+7. Pro Revenue Breakdown (and so the CSV) net is 190 (got %s)', $data['revenue']['net'] ?? 'none' ), $money( $data['revenue']['net'] ?? -1, 190 ) );
		$check( '1+7. its gross and refunds are 290 and 100, not refunds taken twice', $money( $data['revenue']['total'] ?? -1, 290 ) && $money( $data['revenue']['refunded'] ?? -1, 100 ) );

		$top = $data['vendors']['top_vendors'][0] ?? array();
		$check( '5. Top Vendors shows this vendor at 190 paid revenue', (int) ( $top['vendor_id'] ?? 0 ) === $vendor && $money( $top['revenue'] ?? 0, 190 ) );

		$widget = new \WPSellServicesPro\Analytics\Widgets\OrdersWidget();
		$m      = new ReflectionMethod( $widget, 'organize_by_status' );
		$m->setAccessible( true );
		$groups = $m->invoke( $widget, $data['orders']['by_status'] );
		$check( sprintf( '3. the Orders Breakdown adds up to Total Orders (%d = %d)', array_sum( array_column( $groups, 'count' ) ), $data['orders']['total'] ), array_sum( array_column( $groups, 'count' ) ) === (int) $data['orders']['total'] && 7 === (int) $data['orders']['total'] );
	} else {
		echo "SKIP  Pro analytics (Pro not active)\n";
	}
} finally {
	foreach ( $made as $id ) {
		$wpdb->delete( $orders, array( 'id' => $id ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
	if ( class_exists( '\WPSellServicesPro\Analytics\AnalyticsManager' ) ) {
		\WPSellServicesPro\Analytics\AnalyticsManager::flush_cache();
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
