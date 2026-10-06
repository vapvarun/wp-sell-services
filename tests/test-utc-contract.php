<?php
/**
 * Stored datetimes are UTC (Basecamp 10351460106).
 *
 * Run: wp eval-file tests/test-utc-contract.php
 *
 * The site is forced to Asia/Kolkata (+05:30) for the run, so site time and
 * UTC differ; on a UTC site every check below would pass by accident. The
 * migration is exercised on a state limited to this script's own rows, never
 * on the site's data. Every row is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Database\UtcMigration;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$tz = static fn() => 'Asia/Kolkata';
add_filter( 'pre_option_timezone_string', $tz );
add_filter( 'pre_option_gmt_offset', static fn() => 5.5 );

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$ids    = array();
$near   = static fn( string $a, string $b ): bool => abs( strtotime( $a . ' UTC' ) - strtotime( $b . ' UTC' ) ) <= 5;

try {
	// The connection writes MySQL-filled columns in UTC.
	$check( 'the MySQL session clock is UTC', $near( (string) $wpdb->get_var( 'SELECT NOW()' ), gmdate( 'Y-m-d H:i:s' ) ) );

	// A writer stores UTC, not site time.
	$wpdb->insert(
		$orders,
		array(
			'order_number'      => 'WPSS-UTC-' . wp_generate_password( 6, false ),
			'customer_id'       => 999995,
			'vendor_id'         => 999994,
			'service_id'        => 0,
			'platform'          => 'standalone',
			'subtotal'          => 10,
			'total'             => 10,
			'currency'          => 'USD',
			'status'            => 'in_progress',
			'payment_status'    => 'paid',
			// Paid at 00:30 site time today = 19:00 UTC yesterday.
			'paid_at'           => get_gmt_from_date( current_time( 'Y-m-d' ) . ' 00:30:00' ),
			'delivery_deadline' => gmdate( 'Y-m-d H:i:s', time() - 60 ),
			'created_at'        => current_time( 'mysql', true ),
		)
	);
	$order_id = (int) $wpdb->insert_id;
	$ids[]    = $order_id;
	$check( 'current_time( mysql, true ) is UTC, not site time', $near( (string) $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM {$orders} WHERE id = %d", $order_id ) ), gmdate( 'Y-m-d H:i:s' ) ) );

	// Late: a deadline one minute ago in UTC is late now, on a +05:30 site.
	$check( 'a UTC deadline one minute ago reads late', wpss_is_order_late( (object) array( 'delivery_deadline' => gmdate( 'Y-m-d H:i:s', time() - 60 ), 'status' => 'in_progress' ) ) );
	$check( '  and one a minute ahead does not', ! wpss_is_order_late( (object) array( 'delivery_deadline' => gmdate( 'Y-m-d H:i:s', time() + 60 ), 'status' => 'in_progress' ) ) );

	// Ranges: "today" is the site's day, so the 00:30 site-time payment counts.
	$today = current_time( 'Y-m-d' );
	$rows  = wpss_get_revenue(
		array(
			'from'      => $today . ' 00:00:00',
			'to'        => $today . ' 23:59:59',
			'vendor_id' => 999994,
		)
	);
	$check( 'an order paid at 00:30 site time counts in today\'s revenue', 1 === (int) ( $rows[0]->orders ?? 0 ) );
	$series = wpss_get_revenue_series( $today, $today, array( 'vendor_id' => 999994 ) );
	$check( '  and in today\'s bucket of the daily series', 1 === (int) ( $series['orders'][0] ?? 0 ) );

	// Display: stored UTC shows in site time.
	$check( 'wp_date of a stored value shows site time', wp_date( 'H:i', strtotime( '2026-01-01 00:00:00 UTC' ) ) === '05:30' );

	// Migration: site-time rows convert, UTC-written rows do not, updated_at survives ON UPDATE.
	$plan = UtcMigration::plan();
	$check( 'a +05:30 site is planned for conversion', false === $plan['site_is_utc'] && 'pending' === $plan['status'] );

	$seed = static function ( string $platform, string $local ) use ( $wpdb, $orders, &$ids ): int {
		$wpdb->insert(
			$orders,
			array(
				'order_number' => 'WPSS-UTCM-' . wp_generate_password( 6, false ),
				'customer_id'  => 999995,
				'vendor_id'    => 999994,
				'service_id'   => 0,
				'platform'     => $platform,
				'total'        => 5,
				'currency'     => 'USD',
				'status'       => 'completed',
				'created_at'   => $local,
				'updated_at'   => $local,
				'started_at'   => $local,
				'meta'         => wp_json_encode( array( 'status_history' => array( array( 'status' => 'completed', 'timestamp' => $local ) ) ) ),
			)
		);
		$id    = (int) $wpdb->insert_id;
		$ids[] = $id;
		return $id;
	};
	$standalone = $seed( 'standalone', '2026-03-10 10:00:00' );
	$woo        = $seed( 'woocommerce', '2026-03-10 10:00:00' );
	$manual     = $seed( 'manual', '2026-03-10 10:00:00' );

	$state = array(
		'status'        => 'pending',
		'site_is_utc'   => false,
		'server_offset' => 0,
		'cutover'       => array( 'orders' => max( $standalone, $woo, $manual ) ),
		'cursor'        => array( 'orders' => min( $standalone, $woo, $manual ) - 1 ),
		'meta_done'     => true,
	);
	$samples = array();
	$dry     = $state;
	while ( ! UtcMigration::advance( $dry, true, $samples ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedWhile
	}
	$row = static fn( int $id ) => $wpdb->get_row( $wpdb->prepare( "SELECT created_at, updated_at, started_at, meta FROM {$orders} WHERE id = %d", $id ) );
	$check( 'a dry run writes nothing', '2026-03-10 10:00:00' === $row( $standalone )->created_at );

	while ( ! UtcMigration::advance( $state, false, $samples ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedWhile
	}
	$s = $row( $standalone );
	$w = $row( $woo );
	$m = $row( $manual );
	$check( 'a site-time order converts 10:00 IST to 04:30 UTC', '2026-03-10 04:30:00' === $s->created_at && '2026-03-10 04:30:00' === $s->started_at );
	$check( '  and updated_at is converted, not stamped with the run time', '2026-03-10 04:30:00' === $s->updated_at );
	$check( '  and its status_history timestamp converts too', false !== strpos( (string) $s->meta, '2026-03-10 04:30:00' ) );
	$check( 'a WooCommerce order keeps its UTC created_at and updated_at', '2026-03-10 10:00:00' === $w->created_at && '2026-03-10 10:00:00' === $w->updated_at );
	$check( '  but its site-time started_at converts', '2026-03-10 04:30:00' === $w->started_at );
	$check( 'a manual order converts created_at but keeps UTC started_at', '2026-03-10 04:30:00' === $m->created_at && '2026-03-10 10:00:00' === $m->started_at );

	// A second run converts nothing more: the cursor is past the cutover.
	$again = $state;
	$again['status'] = 'pending';
	while ( ! UtcMigration::advance( $again, false, $samples ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedWhile
	}
	$check( 'running again does not shift rows twice', '2026-03-10 04:30:00' === $row( $standalone )->created_at );
} finally {
	foreach ( $ids as $id ) {
		$wpdb->delete( $orders, array( 'id' => $id ) );
	}
	remove_all_filters( 'pre_option_timezone_string' );
	remove_all_filters( 'pre_option_gmt_offset' );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
