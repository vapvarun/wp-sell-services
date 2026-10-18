<?php
/**
 * Dark mode and icon fixes that CSS/JS source can prove: the favorites remove control
 * follows the surface token, the pager hover sets its own colour, native form chrome
 * follows the theme on .wpss-textarea / select.wpss-input, JS-inserted icons are
 * hydrated, and text buttons in modals no longer carry the corner-X class (Basecamp
 * 10384321636, 10399252183, 10411858243, 10384209485, 10408125397).
 *
 * Run: wp eval-file tests/test-dark-mode-and-icon-hooks.php
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

$ud = $read( 'assets/css/unified-dashboard.css' );
preg_match( '/\.wpss-favorites__remove \{(.*?)\}/s', $ud, $fav );
$check( 'favorites remove button background is the surface token, not literal white', ! empty( $fav[1] ) && false !== strpos( $fav[1], 'var(--wpss-surface' ) && false === strpos( $fav[1], 'rgba(255, 255, 255' ) );
preg_match( '/\.wpss-dashboard \.wpss-pagination__link:hover,[^{]*\{(.*?)\}/s', $ud, $pg );
$check( 'dashboard pager hover and focus set a text colour', ! empty( $pg[1] ) && false !== strpos( $pg[1], 'color: var(--wpss-text' ) );

$ds = $read( 'assets/css/design-system.css' );
$check( 'textarea.wpss-textarea declares color-scheme', false !== strpos( $ds, 'textarea.wpss-textarea,' ) && false !== strpos( $ds, ':root[data-theme="dark"] textarea.wpss-textarea' ) );
$check( 'select.wpss-input declares color-scheme', false !== strpos( $ds, 'select.wpss-input,' ) && false !== strpos( $ds, '.wpss-dark select.wpss-input' ) );

$conv = $read( 'templates/order/conversation.php' );
preg_match( '/function updateAttachmentsPreview\(\) \{(.*?)\n\t\}/s', $conv, $fn );
$check( 'attachment preview hydrates Lucide icons after inserting them', ! empty( $fn[1] ) && false !== strpos( $fn[1], 'createIcons' ) );
$check( 'attachment remove button has an accessible name', ! empty( $fn[1] ) && false !== strpos( $fn[1], 'aria-label' ) );
$check( 'attachment names are inserted as text, not HTML', ! empty( $fn[1] ) && false === strpos( $fn[1], "+ file.name +" ) );

// A text button must not use the corner-X hook (it carries absolute positioning and padding:0 !important).
$offenders = array();
$roots     = array( WPSS_PLUGIN_DIR . 'templates', WPSS_PLUGIN_DIR . 'src' );
if ( defined( 'WPSS_PRO_PLUGIN_DIR' ) ) {
	$roots[] = WPSS_PRO_PLUGIN_DIR . 'templates';
}
foreach ( $roots as $root ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
		if ( 'php' === $f->getExtension() && preg_match( '/class="wpss-btn[^"]*wpss-modal-close(?![-\w])/', (string) file_get_contents( $f->getPathname() ) ) ) {
			$offenders[] = basename( $f->getPathname() );
		}
	}
}
$check( 'no .wpss-btn carries wpss-modal-close (' . implode( ',', $offenders ) . ')', ! $offenders );
foreach ( array( 'assets/js/single-service.js', 'assets/js/frontend.js' ) as $js ) {
	$check( "{$js} closes modals through wpssModal", false !== strpos( $read( $js ), 'wpssModal' ) );
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
