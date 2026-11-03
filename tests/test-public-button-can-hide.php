<?php
/**
 * The shared public-page button can still be hidden.
 *
 * The one theme-proof button for the services archive, single service and
 * request pages held display with !important, so the desktop Filters toggle
 * stayed on screen beside the sidebar and jQuery's .hide() on Load more reviews
 * did nothing. display must stay overridable, and [hidden] must hide.
 *
 * Run: wp eval-file tests/test-public-button-can-hide.php
 *
 * Reads files; writes nothing.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$scope = ':is(.wpss-services-archive, .wpss-single-service, .wpss-requests-archive, .wpss-single-request) .wpss-btn';
foreach ( array( 'design-system.css', 'design-system.min.css', 'design-system-rtl.css', 'design-system-rtl.min.css' ) as $sheet ) {
	$css  = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/' . $sheet );
	$flat = (string) preg_replace( '/\s+/', '', $css );
	$sel  = (string) preg_replace( '/\s+/', '', $scope );
	$base = preg_match( '/' . preg_quote( $sel, '/' ) . '\{([^}]*)\}/', $flat, $m ) ? $m[1] : '';
	$check( "{$sheet}: the shared button is there", '' !== $base );
	$check( "{$sheet}:   its display is not !important", '' !== $base && 1 === preg_match( '/display:inline-flex(;|$)/', $base ) );
	$check( "{$sheet}:   [hidden] hides it", 1 === preg_match( '/' . preg_quote( $sel, '/' ) . '\[hidden\]\{display:none/', $flat ) );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nALL PASS\n";
