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

	$seed = static function ( string $platform, string $local, array $over = array() ) use ( $wpdb, $orders, &$ids ): int {
		$wpdb->insert(
			$orders,
			$over + array(
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

	// updated_at on the ON UPDATE tables: MySQL stamped it with the database
	// server's clock whenever a writer left it out, PHP with site time when it
	// did not. Site +05:30, database on UTC.
	$by_mysql = $seed( 'standalone', '2026-03-10 10:00:00', array( 'updated_at' => '2026-03-10 06:00:00' ) ); // 11:30 site time, stored as UTC.
	$run      = static function ( array $state ) use ( &$samples ): array {
		$state['status'] = 'pending';
		$samples         = array();
		$dry     = $state;
		while ( ! UtcMigration::advance( $dry, true, $samples ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedWhile
		}
		$listed = $samples;
		while ( ! UtcMigration::advance( $state, false, $samples ) ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedWhile
		}
		return $listed;
	};
	$run( array( 'cutover' => array( 'orders' => $by_mysql ), 'cursor' => array( 'orders' => $by_mysql - 1 ) ) + $state );
	$check( 'site +05:30, database UTC: an updated_at MySQL stamped is already UTC and stays', '2026-03-10 06:00:00' === $row( $by_mysql )->updated_at && '2026-03-10 04:30:00' === $row( $by_mysql )->created_at );

	// Site on UTC, database server on +05:30: the setup the card was filed on.
	remove_all_filters( 'pre_option_timezone_string' );
	remove_all_filters( 'pre_option_gmt_offset' );
	add_filter( 'pre_option_timezone_string', static fn() => 'UTC' );
	add_filter( 'pre_option_gmt_offset', static fn() => 0 );

	$by_mysql  = $seed( 'standalone', '2026-03-10 10:00:00', array( 'updated_at' => '2026-03-12 15:30:00' ) ); // 10:00 UTC on the 12th, on the server's clock.
	$by_php    = $seed( 'standalone', '2026-03-10 10:00:00', array( 'updated_at' => '2026-03-11 09:00:00', 'completed_at' => '2026-03-11 09:00:00' ) );
	$too_early = $seed( 'standalone', '2026-03-10 10:00:00', array( 'updated_at' => '2026-03-10 12:00:00' ) ); // Server reading is 06:30, before it existed.
	$listed    = $run(
		array(
			'site_is_utc'   => true,
			'server_offset' => 19800,
			'cutover'       => array( 'orders' => $too_early ),
			'cursor'        => array( 'orders' => $by_mysql - 1 ),
			'started'       => gmdate( 'Y-m-d H:i:s' ),
		) + $state
	);
	$check( 'site UTC, database +05:30: the dry run lists orders.updated_at', 1 === (int) ( $listed['orders.updated_at#rows'] ?? 0 ) && '2026-03-12 15:30:00 -> 2026-03-12 10:00:00' === ( $listed['orders.updated_at'][0] ?? '' ) );
	$check( '  and lists nothing that would not change', ! isset( $listed['orders.created_at'] ) );
	$check( '  and the MySQL-stamped updated_at loses the server offset', '2026-03-12 10:00:00' === $row( $by_mysql )->updated_at && '2026-03-10 10:00:00' === $row( $by_mysql )->created_at );
	$check( '  an updated_at written with completed_at is PHP\'s and stays', '2026-03-11 09:00:00' === $row( $by_php )->updated_at );
	$check( '  one the server reading would put before the order existed stays', '2026-03-10 12:00:00' === $row( $too_early )->updated_at );

	// A chunk that dies midway is redone from its start, not from halfway:
	// rows and cursor commit together. The second row's write is made to throw.
	$first  = $seed( 'standalone', '2026-03-10 10:00:00', array( 'updated_at' => '2026-03-12 15:30:00' ) );
	$second = $seed( 'standalone', '2026-03-10 10:00:00', array( 'updated_at' => '2026-03-12 15:30:00' ) );
	$saved  = get_option( UtcMigration::OPTION );
	$die    = static function ( $query ) use ( $orders, $second ) {
		if ( 0 === strpos( $query, "UPDATE `{$orders}`" ) && preg_match( "/`id` = '?{$second}'?$/", $query ) ) {
			throw new RuntimeException( 'chunk died' );
		}
		return $query;
	};

	try {
		update_option(
			UtcMigration::OPTION,
			array(
				'status'        => 'pending',
				'site_is_utc'   => true,
				'server_offset' => 19800,
				'cutover'       => array( 'orders' => $second ),
				'cursor'        => array( 'orders' => $first - 1 ),
				'started'       => gmdate( 'Y-m-d H:i:s' ),
			) + $state,
			false
		);

		add_filter( 'query', $die );
		try {
			UtcMigration::step();
		} catch ( RuntimeException $e ) {
			// What MySQL does with the open transaction when the process is gone.
			$wpdb->query( 'ROLLBACK' );
		}
		remove_filter( 'query', $die );
		$check( 'a chunk that dies leaves its first row unconverted', '2026-03-12 15:30:00' === $row( $first )->updated_at );

		// A second runner waits for the first; it does not read the same cursor.
		$other = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$other->get_var( "SELECT GET_LOCK( 'wpss_utc_migration', 0 )" );
		$before = get_option( UtcMigration::OPTION );
		add_filter( 'query', static fn( $q ) => str_replace( "'wpss_utc_migration', 10", "'wpss_utc_migration', 0", $q ) );
		$check( 'a chunk does nothing while another runner holds the lock', false === UtcMigration::step() && '2026-03-12 15:30:00' === $row( $first )->updated_at && $before === get_option( UtcMigration::OPTION ) );
		$other->get_var( "SELECT RELEASE_LOCK( 'wpss_utc_migration' )" );
		$other->close();
		remove_all_filters( 'query' );

		while ( ! UtcMigration::step() ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedWhile
		}
		$check( '  and the retry converts each row once', '2026-03-12 10:00:00' === $row( $first )->updated_at && '2026-03-12 10:00:00' === $row( $second )->updated_at );
	} finally {
		remove_filter( 'query', $die );
		false === $saved ? delete_option( UtcMigration::OPTION ) : update_option( UtcMigration::OPTION, $saved, false );
	}
} finally {
	foreach ( $ids as $id ) {
		$wpdb->delete( $orders, array( 'id' => $id ) );
	}
	remove_all_filters( 'pre_option_timezone_string' );
	remove_all_filters( 'pre_option_gmt_offset' );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
