<?php
/**
 * The Featured Services carousel follows the block's Columns (Basecamp 10549241541).
 *
 * Slides were sized from the viewport only and the script paged by its own
 * viewport table, so with Columns below 4 the cards clipped and pages stepped
 * wrong. The stylesheet now sizes slides from wpss-grid-cols-N (capped per
 * breakpoint) and the script measures how many are in view. A card's content
 * can no longer widen its column, and a long seller name wraps.
 *
 * Shipped files are checked (source and .min, LTR and RTL); the browser proof
 * is on the card.
 *
 * Run: wp eval-file tests/test-featured-carousel-columns.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

foreach ( array( 'blocks.css', 'blocks.min.css', 'blocks-rtl.css', 'blocks-rtl.min.css' ) as $file ) {
	$css = preg_replace( '/\s+/', '', (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/' . $file ) );
	$check( "{$file}: Columns 2, 3 and 5 size the carousel slides", false !== strpos( $css, '.wpss-grid-cols-2.wpss-featured-slide{flex-basis:50%' ) && false !== strpos( $css, '.wpss-grid-cols-3.wpss-featured-slide{flex-basis:33.333%' ) && false !== strpos( $css, '.wpss-grid-cols-5.wpss-featured-slide{flex-basis:20%' ) );
	$check( "{$file}: a slide's content cannot widen its column", (bool) preg_match( '/\.wpss-featured-slide\{flex:0025%;min-width:0/', $css ) );
	$check( "{$file}: a long seller name wraps inside the card", false !== strpos( $css, '.wpss-featured-sellerspan{min-width:0;overflow-wrap:anywhere' ) );
	$check( "{$file}: the grid layout has a 5-column rule", false !== strpos( $css, '.wpss-grid-cols-5.wpss-featured-slides' ) );
}

foreach ( array( 'blocks-frontend.js', 'blocks-frontend.min.js' ) as $file ) {
	$js = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/' . $file );
	$check( "{$file}: slides per view is measured, not read from the window width", false === strpos( $js, 'innerWidth' ) && false !== strpos( $js, '$track.width()' ) );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
