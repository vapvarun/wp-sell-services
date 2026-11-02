<?php
/**
 * The Vendors directory renders like the other discovery pages (Basecamp 10240180000).
 *
 * Run: wp eval-file tests/test-vendors-directory-layout.php
 *
 * /services/ and the Buyer Requests archive render without the theme sidebar; /vendors/ got
 * Recent Posts beside the grid. The tier badge wrapped beside short names and under long ones.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$root   = WP_PLUGIN_DIR . '/wp-sell-services/';
$loader = (string) file_get_contents( $root . 'src/Frontend/TemplateLoader.php' );
$check( 'the Vendors page is a default full-width page', 1 === preg_match( "/'wpss_fullwidth_page_keys',\s*array\([^)]*'vendors_page'/", $loader ) );

foreach ( array( 'frontend.css', 'frontend.min.css' ) as $file ) {
	$css = (string) preg_replace( '/\s+/', '', (string) file_get_contents( $root . 'assets/css/' . $file ) );
	$css = (string) preg_replace( '#/\*.*?\*/#', '', $css );
	preg_match( '/\.wpss-vendors-grid\.wpss-vendor-name\{([^}]*)\}/', $css, $m );
	$check( "{$file}: the tier badge always stacks under the name", str_contains( $m[1] ?? '', 'flex-direction:column' ) );
}

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
