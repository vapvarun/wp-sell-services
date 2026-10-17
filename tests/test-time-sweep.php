<?php
/**
 * The time sweep: deadlines name their zone, calendar dates do not move, report and input
 * boundaries follow the display timezone, every wpss REST response carries ISO 8601, the
 * wallet ledger no longer formats dates in the browser, and nothing outside src/functions/time.php
 * formats a date by itself (Time Standard decisions 13-15).
 *
 * Run: wp eval-file tests/test-time-sweep.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$read  = static fn( string $rel ): string => (string) file_get_contents( WPSS_PLUGIN_DIR . $rel );
$saved = array( get_option( 'timezone_string' ), get_option( 'gmt_offset' ) );
$set   = static function ( string $tz ) {
	update_option( 'timezone_string', $tz );
	update_option( 'gmt_offset', '' );
};

// --- deadlines print the zone ------------------------------------------------
$set( 'Asia/Kolkata' );
$check( 'deadline names UTC+5:30', str_ends_with( wpss_deadline_label( '2026-10-21 09:30:00' ), '(UTC+5:30)' ) );
$check( 'deadline shows the display-time hour, not UTC', false !== strpos( wpss_deadline_label( '2026-10-21 09:30:00' ), '3:00' ) || false !== strpos( wpss_deadline_label( '2026-10-21 09:30:00' ), '15:00' ) );
$check( 'deadline with no time gives the fallback', '-' === wpss_deadline_label( null, '-' ) );
$set( 'America/New_York' );
$check( 'deadline names the offset in force then: winter UTC-5', str_ends_with( wpss_deadline_label( '2026-01-15 18:00:00' ), '(UTC-5)' ) );
$check( 'deadline names the offset in force then: summer UTC-4', str_ends_with( wpss_deadline_label( '2026-07-15 18:00:00' ), '(UTC-4)' ) );
$set( 'UTC' );
$check( 'deadline on a UTC site says UTC', str_ends_with( wpss_deadline_label( '2026-10-21 09:30:00' ), '(UTC)' ) );

// --- a calendar date is not an instant ---------------------------------------
$set( 'America/New_York' );
$check( 'calendar date does not move west of UTC', 'Oct 21, 2026' === wpss_calendar_date_label( '2026-10-21', '-', 'M j, Y' ) );
$check( 'the same string as an instant does move (why the two helpers exist)', 'Oct 20, 2026' === wpss_date_label( '2026-10-21 00:00:00', '-', 'M j, Y' ) );
$check( 'calendar date: empty and zero date give the fallback', '-' === wpss_calendar_date_label( '', '-' ) && '-' === wpss_calendar_date_label( '0000-00-00', '-' ) && '-' === wpss_calendar_date_label( null, '-' ) );

// --- REST: ISO on every wpss route -------------------------------------------
$api      = new \WPSellServices\API\API();
$respond  = static function ( string $route, array $data ) use ( $api ) {
	return $api->normalise_response_dates( new WP_REST_Response( $data ), rest_get_server(), new WP_REST_Request( 'GET', $route ) )->get_data();
};
$out = $respond( '/wpss/v1/anything', array( 'created_at' => '2026-10-21 09:30:00', 'nested' => array( array( 'paid_at' => '2026-10-21 09:30:00' ) ), 'start_date' => '2026-10-21 09:30:00', 'note' => '2026-10-21 09:30:00' ) );
$check( 'a MySQL datetime in a *_at key becomes ISO 8601', '2026-10-21T09:30:00+00:00' === $out['created_at'] );
$check( 'nested rows are normalised too', '2026-10-21T09:30:00+00:00' === $out['nested'][0]['paid_at'] );
$check( 'a filter echoed back (start_date) keeps what the caller sent', '2026-10-21 09:30:00' === $out['start_date'] && '2026-10-21 09:30:00' === $out['note'] );
$check( 'another plugin\'s route is left alone', '2026-10-21 09:30:00' === $respond( '/other/v1/x', array( 'created_at' => '2026-10-21 09:30:00' ) )['created_at'] );
$check( 'an already formatted date is not touched twice', '2026-10-21T09:30:00+00:00' === $respond( '/wpss/v1/x', array( 'created_at' => '2026-10-21T09:30:00+00:00' ) )['created_at'] );

// --- input boundaries follow the display timezone ----------------------------
$set( 'Asia/Kolkata' );
$check( 'a range start typed as a day converts at the display midnight', '2026-10-20 18:30:00' === wpss_datetime_input_to_utc( '2026-10-21 00:00:00' ) );
foreach ( array( 'src/Database/Repositories/OrderRepository.php', 'src/Services/EarningsService.php', 'src/Services/AuditLogService.php', 'src/Frontend/AjaxHandlers.php', 'src/Admin/Metaboxes/BuyerRequestMetabox.php' ) as $rel ) {
	$check( "{$rel} converts typed times with wpss_datetime_input_to_utc", false === strpos( $read( $rel ), 'get_gmt_from_date' ) && false === strpos( $read( $rel ), 'get_date_from_gmt' ) );
}
$check( 'edit-request shows the stored deadline in the display zone', false === strpos( $read( 'templates/dashboard/sections/edit-request.php' ), 'get_date_from_gmt' ) );

// --- surfaces ----------------------------------------------------------------
$check( 'cancellation email gives each recipient the deadline in their own zone', 1 < substr_count( $read( 'src/Services/EmailService.php' ), 'wpss_deadline_label( $deadline_ts' ) );
$check( 'wallet ledger prints the server label, not the browser zone', false === strpos( $read( 'assets/js/unified-dashboard.js' ), 'toLocaleDateString' ) && false !== strpos( $read( 'src/API/EarningsController.php' ), "'created_at_label'" ) );
$check( 'Pro report labels are formatted as UTC calendar days', false !== strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . '../wp-sell-services-pro/assets/js/analytics.js' ), "timeZone: 'UTC'" ) );

// --- nothing formats a date by itself ----------------------------------------
$gate = (string) shell_exec( 'php ' . escapeshellarg( WPSS_PLUGIN_DIR . 'bin/check-time-formatter.php' ) . ' 2>&1' );
$check( 'time formatter gate is green', false !== strpos( $gate, 'OK  time formatter gate' ) );
$check( 'gate baseline is empty', array() === json_decode( $read( 'bin/time-formatter-baseline.json' ), true ) );

update_option( 'timezone_string', (string) $saved[0] );
update_option( 'gmt_offset', $saved[1] );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
