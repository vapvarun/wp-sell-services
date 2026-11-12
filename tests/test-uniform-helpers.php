<?php
/**
 * Callers use the shared helper, not a private copy (1.9.3 uniformity cards).
 *
 * Run: wp eval-file tests/test-uniform-helpers.php
 *
 * - 10504408045: RestController::format_datetime() is wpss_rest_date(); a zero
 *   MySQL date is null, not year -0001.
 * - 10504406880: the rail-mismatch admin warning reads the real setting group.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$controller = new class() extends \WPSellServices\API\RestController {
	public function fmt( $value ) {
		return $this->format_datetime( $value );
	}
};

$check( 'a zero MySQL date is null', null === $controller->fmt( '0000-00-00 00:00:00' ) );
$check( 'a MySQL date is read as UTC', '2026-01-02T03:04:05+00:00' === $controller->fmt( '2026-01-02 03:04:05' ) );
$check( 'controller and helper agree', $controller->fmt( '2026-05-06 07:08:09' ) === wpss_rest_date( '2026-05-06 07:08:09' ) );

$source = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Admin/Admin.php' );
$check( 'no wpss_get_option() call double-prefixes its group', 0 === preg_match( "/wpss_get_option\(\s*'wpss_/", $source ) );

// 10504407233: closing an unpaid sub-order drops the cached order counts.
set_transient( 'wpss_order_aggregates', array( 'stale' => 1 ), 300 );
do_action( 'wpss_unpaid_sub_order_closed', 0 );
$check( 'closing an unpaid sub-order flushes the cached order aggregates', false === get_transient( 'wpss_order_aggregates' ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
