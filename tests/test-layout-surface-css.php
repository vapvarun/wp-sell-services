<?php
/**
 * Layout fixes that CSS/markup source can prove: checkout aside cards stack above the
 * sticky summary, breadcrumb CSS ships with the design system, the report modal starts
 * hidden, modals clear a sticky theme header, milestone inputs stay in their grid,
 * the empty-state categories block is centred, vacation fields are stacked, and the
 * review search field is wide enough (Basecamp 10411948180, 10384469084, 10398652904,
 * 10384248570, 10379783717, 10380369422, 10384895523, 10384766482).
 *
 * Run: wp eval-file tests/test-layout-surface-css.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$read = static fn( string $rel ): string => (string) file_get_contents( WPSS_PLUGIN_DIR . $rel );

$co = $read( 'src/Integrations/Standalone/StandaloneCheckoutProvider.php' );
$check( 'checkout aside cards other than the summary stack above the sticky card', (bool) preg_match( '/\.wpss-co-aside > :not\(\.wpss-co-card--summary\) \{[^}]*z-index: 3/', $co ) );

foreach ( array( 'design-system.css', 'design-system-rtl.css' ) as $f ) {
	$check( "{$f} carries the breadcrumb list rules", false !== strpos( $read( "assets/css/{$f}" ), '.wpss-breadcrumb-list {' ) );
}
$check( 'single-service.css no longer duplicates the breadcrumb rules', false === strpos( $read( 'assets/css/single-service.css' ), '.wpss-breadcrumb-list {' ) );

$check( 'report modal starts hidden so it cannot flash before CSS loads', (bool) preg_match( '/id="wpss-report-modal" hidden/', $read( 'templates/partials/report-modal.php' ) ) );
$js = $read( 'assets/js/wpss-ui.js' );
$check( 'wpssModal unhides and offsets from a sticky header', false !== strpos( $js, 'wpssModal' ) && false !== strpos( $js, '--wpss-modal-offset' ) && false !== strpos( $js, "removeAttribute( 'hidden' )" ) );

foreach ( array( 'design-system.css', 'design-system-rtl.css' ) as $f ) {
	$ds = $read( "assets/css/{$f}" );
	$check( "{$f} modal clears --wpss-modal-offset", false !== strpos( $ds, 'margin-top: var(--wpss-modal-offset' ) && false !== strpos( $ds, 'max-height: calc(100vh - var(--wpss-modal-offset' ) );
}

$br = $read( 'assets/css/buyer-request.css' );
$check( 'milestone meta grid cannot be widened by its inputs', false !== strpos( $br, 'repeat(2, minmax(0, 1fr))' ) && (bool) preg_match( '/\.wpss-milestone-row__meta input \{[^}]*min-width: 0/', $br ) );

$ud = $read( 'assets/css/unified-dashboard.css' );
$check( 'empty-state categories block is centred', (bool) preg_match( '/\.wpss-empty-state__categories \{[^}]*margin-inline: auto/', $ud ) );

$ad = $read( 'assets/css/admin.css' );
$check( 'vacation fields stack label over control', false !== strpos( $ad, '.wpss-vacation-return-date label {' ) );
$check( 'review search field is wide enough for its placeholder', (bool) preg_match( '/\.wpss-review-search input\[type="search"\] \{[^}]*min-width: 240px/', $ad ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
