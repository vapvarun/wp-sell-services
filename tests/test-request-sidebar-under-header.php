<?php
/**
 * The desktop request sidebar sits under the theme's sticky header (Basecamp 10550832206).
 *
 * The mobile drawer rule gives .wpss-request-sidebar z-index 9991; the desktop sticky block kept
 * it, so on /buyer-request/ the filters column painted over Reign's sticky header. The desktop
 * block resets it to auto, as archive-service.css does. Checks every built copy.
 *
 * Run: wp eval-file tests/test-request-sidebar-under-header.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
foreach ( array( 'buyer-request.css', 'buyer-request.min.css', 'buyer-request-rtl.css', 'buyer-request-rtl.min.css' ) as $file ) {
	$css = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/' . $file );
	$css = preg_replace( array( '#/\*.*?\*/#s', '/\s+/' ), '', $css );
	// The sticky (desktop) sidebar rule: find it and read its z-index.
	$ok = (bool) preg_match( '/\.wpss-request-sidebar[^{}]*\{[^}]*position:sticky[^}]*\}/', $css, $m ) && false !== strpos( $m[0], 'z-index:auto' );
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . "{$file}: the sticky sidebar does not keep the drawer's z-index\n";
	$fails += $ok ? 0 : 1;
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
