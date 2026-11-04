<?php
/**
 * Every Namespace.method() our JS calls is defined somewhere.
 *
 * Pro's admin.js init() kept calling WPSSPro.initStorageSettings() after the
 * function was deleted as unreachable. The TypeError ended document-ready, so
 * no later init ran and Save did nothing on the plan, subscription, commission
 * and White Label screens. Nothing failed at build time; only a browser saw it.
 *
 * Scans both plugins' source JS for namespaces declared as
 * `window.X = window.X || {}` and fails on a call with no `X.name =` anywhere.
 *
 * Run: wp eval-file tests/test-js-namespace-calls-defined.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$roots = array( WPSS_PLUGIN_DIR . 'assets/js' );
if ( defined( 'WPSS_PRO_PLUGIN_DIR' ) ) {
	$roots[] = WPSS_PRO_PLUGIN_DIR . 'assets/js';
}

$sources = array();
foreach ( $roots as $root ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		$path = $f->getPathname();
		if ( str_ends_with( $path, '.js' ) && ! str_contains( $path, '.min.' ) && ! str_contains( $path, '/vendor/' ) ) {
			// Comments out: a name in prose is neither a call nor a definition.
			$sources[ $path ] = (string) preg_replace( '#/\*.*?\*/|(?<![:\'"])//[^\n]*#s', '', (string) file_get_contents( $path ) );
		}
	}
}
$check( 'found the plugins\' source JS', count( $sources ) > 10 );

$namespaces = array();
foreach ( $sources as $code ) {
	if ( preg_match_all( '/window\.(\w+)\s*=\s*window\.\1\s*\|\|\s*\{/', $code, $m ) ) {
		foreach ( $m[1] as $ns ) {
			$namespaces[ $ns ] = true;
		}
	}
}
$check( 'found the namespaces the JS declares (WPSSPro among them)', isset( $namespaces['WPSSPro'] ) || ! defined( 'WPSS_PRO_PLUGIN_DIR' ) );

$missing = array();
foreach ( array_keys( $namespaces ) as $ns ) {
	$defined = array();
	$called  = array();
	foreach ( $sources as $path => $code ) {
		if ( preg_match_all( '/\b' . $ns . '\.(\w+)\s*=(?!=)/', $code, $m ) ) {
			$defined += array_fill_keys( $m[1], true );
		}
		if ( preg_match_all( '/\b' . $ns . '\.(\w+)\s*\(/', $code, $m ) ) {
			foreach ( $m[1] as $method ) {
				$called[ $method ][] = basename( dirname( $path, 3 ) ) . '/' . basename( $path );
			}
		}
	}
	foreach ( array_diff_key( $called, $defined ) as $method => $where ) {
		$missing[] = $ns . '.' . $method . '() in ' . implode( ', ', array_unique( $where ) );
	}
}
$check( 'every namespaced method the JS calls is defined', empty( $missing ), implode( ' | ', $missing ) );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
