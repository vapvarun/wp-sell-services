<?php
/**
 * Shared UI primitives have ONE owner: design-system.css. A surface sheet adds scoped deltas
 * (".wpss-wizard .wpss-btn"), never a second base definition, and the legacy single-dash
 * button names no longer exist anywhere (1.9.0 consolidation).
 *
 * Run: wp eval-file tests/test-primitive-single-source.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$plugins = array( dirname( __DIR__ ), dirname( __DIR__, 2 ) . '/wp-sell-services-pro' );

// 1. No legacy single-dash button modifiers in source (PHP, JS, CSS).
$legacy = array();
foreach ( $plugins as $root ) {
	if ( ! is_dir( $root ) ) {
		continue;
	}
	foreach ( array( 'src', 'templates', 'assets/js', 'assets/css' ) as $dir ) {
		if ( ! is_dir( "$root/$dir" ) ) {
			continue;
		}
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$root/$dir", FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			$p = $f->getPathname();
			if ( ! preg_match( '/\.(php|js|css)$/', $p ) || preg_match( '/\.min\.|-rtl\.css$|\/vendor\//', $p ) ) {
				continue;
			}
			if ( preg_match( '/wpss-btn-(primary|secondary|outline|ghost|danger|success|sm|lg|block|icon)\b/', (string) file_get_contents( $p ) ) ) {
				$legacy[] = str_replace( dirname( __DIR__, 2 ) . '/', '', $p );
			}
		}
	}
}
$check( 'no legacy single-dash button class remains' . ( $legacy ? ': ' . implode( ', ', $legacy ) : '' ), ! $legacy );

// 2. Base primitive selectors are declared in design-system.css only (admin.css is the wp-admin chrome surface).
$owned = array( '.wpss-btn' );
$found = array();
foreach ( glob( dirname( __DIR__ ) . '/assets/css/*.css' ) as $css ) {
	$name = basename( $css );
	if ( in_array( $name, array( 'design-system.css', 'admin.css' ), true ) || str_contains( $name, '.min.' ) || str_contains( $name, '-rtl' ) ) {
		continue;
	}
	$src = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( $css ) );
	foreach ( $owned as $sel ) {
		if ( preg_match( '/(^|\})\s*' . preg_quote( $sel, '/' ) . '(--(?:primary|secondary|outline|success|danger|sm|lg|block|icon|ghost|disabled|full|danger-outline))?\s*[,{]/m', $src ) ) {
			$found[] = "$name declares $sel";
		}
	}
}
$check( 'no surface sheet redeclares the button base or its variants' . ( $found ? ': ' . implode( '; ', $found ) : '' ), ! $found );

$ds = (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/design-system.css' );
foreach ( array( '--block', '--disabled', '--danger-outline', '--link' ) as $variant ) {
	$check( "design-system.css owns .wpss-btn{$variant}", str_contains( $ds, ".wpss-btn{$variant}" ) );
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
