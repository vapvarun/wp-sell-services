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

$ds = $read( 'assets/css/design-system.css' );
$check( 'shared form controls (.wpss-form-input/-textarea/-label/-help) are in the design system, border-box', (bool) preg_match( '/\.wpss-form-input,\s*\.wpss-form-select,\s*\.wpss-form-textarea \{[^}]*box-sizing: border-box/s', $ds ) && false !== strpos( $ds, '.wpss-form-help {' ) && false !== strpos( $ds, '.wpss-form-label {' ) );
$check( 'service-wizard.css no longer owns the shared control base', ! preg_match( '/^\.wpss-form-input,\s*\.wpss-form-select,/m', $read( 'assets/css/service-wizard.css' ) ) );
$check( 'dashboard pager link pins its colour against a theme content-link hover', (bool) preg_match( '/a\.wpss-pagination__link:hover,[^{]*\{[^}]*color: var\(--wpss-text[^}]*!important/s', $read( 'assets/css/unified-dashboard.css' ) ) );

$check( 'the package widget gives the add-on note and the order actions the Continue button inset', (bool) preg_match( '/\.wpss-packages-widget > \.wpss-package-extras-note \{[^}]*var\(--wpss-package-inset\)/s', $read( 'assets/css/single-service.css' ) ) && (bool) preg_match( '/\.wpss-package-actions \{[^}]*var\(--wpss-package-inset\)/s', $read( 'assets/css/single-service.css' ) ) );

$admin_css = $read( 'assets/css/admin.css' );
$settings  = $read( 'src/Admin/Settings.php' );
$check( 'settings card and sub-heading titles share one tab heading style (18px, not small caps)', (bool) preg_match( '/\.wpss-settings-content h2\.wpss-card__title,[^{]*\{[^}]*font-size: 18px;[^}]*text-transform: none/s', $admin_css ) );
$check( 'settings card titles are not upper-cased in PHP', false === strpos( $settings, 'strtoupper( $title )' ) );
$check( 'the first sub-heading on a tab has no divider band above it', (bool) preg_match( '/\.wpss-settings-section > \.wpss-settings-subhead:first-child \{[^}]*border-top: 0/', $admin_css ) );

$co = $read( 'src/Integrations/Standalone/StandaloneCheckoutProvider.php' );
$check( 'checkout aside sticks as one column, not card by card', (bool) preg_match( '/\.wpss-co-aside \{[^}]*position: sticky/', $co ) && ! preg_match( '/\.wpss-co-aside > :not\(\.wpss-co-card--summary\) \{[^}]*z-index/', $co ) );

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
