<?php
/**
 * The vendor level badge stays inside the service card (Basecamp 10549262537).
 *
 * The vendor row was a one-line flex row and the badge is nowrap, so in the
 * 3-column archive a long name pushed "Pro Vendor" 43px past the card edge.
 * The row now wraps and the name can break, so the badge moves to its own line.
 * Shipped files are checked (source and .min, LTR and RTL); the browser proof is on the card.
 *
 * Run: wp eval-file tests/test-service-card-badge-wraps.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$rule = static function ( string $css, string $selector ): string {
	return preg_match( '/' . preg_quote( $selector, '/' ) . '\{([^}]*)\}/', $css, $m ) ? $m[1] : '';
};

foreach ( array( 'frontend.css', 'frontend.min.css', 'frontend-rtl.css', 'frontend-rtl.min.css' ) as $file ) {
	$css = preg_replace( '/\s+|\/\*.*?\*\//s', '', (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/css/' . $file ) );
	$row = $rule( $css, '.wpss-service-card__vendor' );
	$name = $rule( $css, '.wpss-service-card__vendor-name' );
	$check( "{$file}: the vendor row wraps", false !== strpos( $row, 'flex-wrap:wrap' ) );
	$check( "{$file}: a long vendor name can break", false !== strpos( $name, 'min-width:0' ) && false !== strpos( $name, 'overflow-wrap:anywhere' ) );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
