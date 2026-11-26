<?php
/**
 * The welcome tour starts by itself only on the dashboard root (Basecamp 10550842456).
 *
 * It opened over the service wizard a new vendor was filling in. The tour still loads on
 * every dashboard section so Replay tour works there, but only the root auto-starts it.
 *
 * Run: wp eval-file tests/test-tour-waits-on-dashboard-sections.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$tour      = new \WPSellServices\Frontend\Tour();
$autostart = static function ( string $pretty, string $query ) use ( $tour ) {
	global $wp_query;
	$wp_query->set( 'wpss_section', $pretty );
	$_GET = '' === $query ? array() : array( 'section' => $query );
	$data = $tour->localize_tour_data();
	$wp_query->set( 'wpss_section', '' );
	$_GET = array();
	return $data['autostart'] ?? 'missing';
};

$check( 'Dashboard root: the tour starts by itself', true === $autostart( '', '' ) );
$check( 'Create service (/dashboard/create/): it waits for Replay', false === $autostart( 'create', '' ) );
$check( 'Create service (?section=create): it waits too', false === $autostart( '', 'create' ) );
$check( 'Any other section waits as well', false === $autostart( 'services', '' ) );

$root = dirname( __DIR__ ) . '/assets/js/';
foreach ( array( 'wpss-tour.js', 'wpss-tour.min.js' ) as $file ) {
	$check( "{$file} honours autostart and still exposes start() for Replay", false !== strpos( (string) file_get_contents( $root . $file ), 'autostart' ) );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
