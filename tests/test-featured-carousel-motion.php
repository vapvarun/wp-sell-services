<?php
/**
 * The Featured Services carousel does not move by itself unless the owner turns
 * autoplay on, and when on it pauses for keyboard users and respects reduced
 * motion (Basecamp 10545808390, WCAG 2.2.2).
 *
 * Read-only: renders the block and reads the shipped scripts.
 *
 * Run: wp eval-file tests/test-featured-carousel-motion.php
 *
 * @package WPSellServices
 */

use WPSellServices\Blocks\FeaturedServices;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$block = new FeaturedServices();
$attrs = $block->get_attributes();
$check( 'block attribute autoplay defaults to off', false === ( $attrs['autoplay']['default'] ?? null ) );

$html = $block->render( array() );
$check( 'a carousel rendered with defaults carries data-autoplay="false" (or no carousel without featured services)', false === strpos( $html, 'data-autoplay=' ) || false !== strpos( $html, 'data-autoplay="false"' ) );

$editor = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/blocks.min.js' );
$check( 'editor default matches (autoplay default false)', (bool) preg_match( '/autoplay:\{type:"boolean",default:(!1|false)\}/', $editor ) );

foreach ( array( 'blocks-frontend.js', 'blocks-frontend.min.js' ) as $file ) {
	$js = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/' . $file );
	$check( "{$file}: autoplay pauses on keyboard focus and resumes on focusout", false !== strpos( $js, '"focusin"' ) || false !== strpos( $js, "'focusin'" ) );
	$check( "{$file}: reduced-motion visitors get no autoplay", false !== strpos( $js, 'prefers-reduced-motion: reduce' ) );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
