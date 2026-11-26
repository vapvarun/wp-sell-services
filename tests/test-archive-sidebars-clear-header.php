<?php
/**
 * Archive filter columns pin below the theme's sticky header (Basecamp 10550835232).
 *
 * frontend.js measures the admin bar plus the theme's sticky header into --wpss-sticky-top.
 * The services archive column ignored it (a fixed 2rem), and on both archives an unscoped
 * `.admin-bar <sidebar> { top: 32px }` drawer rule outranked the desktop rule, so the column
 * tucked under the header. Checks every built copy: the desktop column reads the variable, and
 * no .admin-bar sidebar offset applies outside a max-width (drawer) media query.
 *
 * Run: wp eval-file tests/test-archive-sidebars-clear-header.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$sheets = array(
	'archive-service' => '.wpss-archive-sidebar',
	'buyer-request'   => '.wpss-request-sidebar',
);
foreach ( $sheets as $base => $sidebar ) {
	foreach ( array( '.css', '.min.css', '-rtl.css', '-rtl.min.css' ) as $suffix ) {
		$file = $base . $suffix;
		$css  = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/' . $file );
		$css  = preg_replace( array( '#/\*.*?\*/#s', '/\s+/' ), '', $css );

		// Blank out every max-width media block (balanced braces), leaving the rules that apply on desktop.
		$desktop = $css;
		while ( preg_match( '/@media[^{]*max-width[^{]*\{/', $desktop, $m, PREG_OFFSET_CAPTURE ) ) {
			$start = $m[0][1];
			$depth = 0;
			for ( $i = $start + strlen( $m[0][0] ) - 1, $n = strlen( $desktop ); $i < $n; $i++ ) {
				$depth += '{' === $desktop[ $i ] ? 1 : ( '}' === $desktop[ $i ] ? -1 : 0 );
				if ( 0 === $depth ) {
					break;
				}
			}
			$desktop = substr( $desktop, 0, $start ) . substr( $desktop, $i + 1 );
		}

		$check( "{$file}: no .admin-bar sidebar offset outside the drawer breakpoints", ! preg_match( '/\.admin-bar' . preg_quote( $sidebar, '/' ) . '[^{]*\{[^}]*top:/', $desktop ) );
		$check( "{$file}: the sticky column pins at --wpss-sticky-top", (bool) preg_match( '/' . preg_quote( $sidebar, '/' ) . '[^{}]*\{[^}]*position:sticky;[^}]*top:var\(--wpss-sticky-top/', $css ) );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
