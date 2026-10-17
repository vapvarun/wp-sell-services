<?php
/**
 * The time helpers show one moment one way, on a site that is not UTC.
 *
 * Run: wp eval-file tests/test-time-helpers.php
 *
 * Timezone is set to Asia/Kolkata (+05:30, no DST) and America/New_York so a
 * helper that quietly uses UTC or the PHP default zone cannot pass.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$saved = array( get_option( 'timezone_string' ), get_option( 'gmt_offset' ) );
$set   = static function ( string $tz ) {
	update_option( 'timezone_string', $tz );
	update_option( 'gmt_offset', '' );
};

$set( 'Asia/Kolkata' );
$check( 'label shows the site day, past UTC midnight', '2026-03-02 00:00' === wpss_date_label( '2026-03-01 18:30:00', '-', 'Y-m-d H:i' ) );
$check( 'label: null, empty and zero date give the fallback', '-' === wpss_date_label( null, '-' ) && '-' === wpss_date_label( '', '-' ) && '-' === wpss_date_label( '0000-00-00 00:00:00', '-' ) );
$check( 'label: junk gives the fallback', '-' === wpss_date_label( 'not a date', '-' ) );
$check( 'label: a Unix timestamp is the same moment', wpss_date_label( 1772389800, '-', 'c' ) === wpss_date_label( '2026-03-01 18:30:00', '-', 'c' ) );
$check( 'label: a string that carries its own zone is not read as UTC twice', '2026-03-02 00:00' === wpss_date_label( '2026-03-01T18:30:00Z', '-', 'Y-m-d H:i' ) );
$check( 'input to UTC', '2026-03-01 18:30:00' === wpss_datetime_input_to_utc( '2026-03-02T00:00' ) );
$check( 'blank input is null, not a wrong instant', null === wpss_datetime_input_to_utc( '' ) && null === wpss_datetime_input_to_utc( null ) );
$check( 'UTC to input', '2026-03-02T00:00' === wpss_datetime_local_input( '2026-03-01 18:30:00' ) && '' === wpss_datetime_local_input( null ) );
$check( 'edit field round-trips without walking', '2026-03-01 18:30:00' === wpss_datetime_input_to_utc( wpss_datetime_local_input( '2026-03-01 18:30:00' ) ) );
$check( 'report SQL uses the +05:30 offset', str_contains( wpss_display_time_sql( 'c' ), "'+05:30'" ) );
$check( 'site day SQL wraps it in DATE()', 'DATE( ' . wpss_display_time_sql( 'c' ) . ' )' === wpss_site_day_sql( 'c' ) );

$set( 'America/New_York' );
$check( 'New York label (EST, UTC-5)', '2026-01-15 13:00' === wpss_date_label( '2026-01-15 18:00:00', '-', 'Y-m-d H:i' ) );
$check( 'New York input round trip', '2026-01-15 18:00:00' === wpss_datetime_input_to_utc( wpss_datetime_local_input( '2026-01-15 18:00:00' ) ) );

$filter = static fn() => new DateTimeZone( 'Asia/Tokyo' );
add_filter( 'wpss_display_timezone', $filter );
$check( 'the filter moves every helper (member timezone)', '2026-01-16 03:00' === wpss_date_label( '2026-01-15 18:00:00', '-', 'Y-m-d H:i' ) );
remove_filter( 'wpss_display_timezone', $filter );
add_filter( 'wpss_display_timezone', static fn() => 'garbage' );
$check( 'a filter returning junk falls back to the site zone', 'America/New_York' === wpss_display_timezone()->getName() );
remove_all_filters( 'wpss_display_timezone' );

update_option( 'timezone_string', (string) $saved[0] );
update_option( 'gmt_offset', $saved[1] );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
