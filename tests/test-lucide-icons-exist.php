<?php
/**
 * Every icon the templates, scripts and notification registry name exists in the bundled Lucide.
 *
 * Run: wp eval-file tests/test-lucide-icons-exist.php
 *
 * An unknown name renders nothing and logs "icon name was not found" in the console; the empty-search
 * state shipped with such a name (text-align-left, added in a later Lucide than the bundled 0.460).
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$root   = dirname( __DIR__ );
$bundle = (string) file_get_contents( $root . '/assets/js/vendor/lucide.min.js' );
$pascal = static fn( string $kebab ): string => str_replace( ' ', '', ucwords( str_replace( '-', ' ', $kebab ) ) );
$known  = static fn( string $name ): bool => 1 === preg_match( '/\b' . preg_quote( $pascal( $name ), '/' ) . '\b/', $bundle ) || false !== strpos( $bundle, '"' . $name . '"' );

$names = array();
foreach ( array( 'templates', 'src', 'assets/js' ) as $dir ) {
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "{$root}/{$dir}", FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		$path = $file->getPathname();
		if ( ! preg_match( '/\.(php|js)$/', $path ) || preg_match( '/\.min\.js$|\/vendor\//', $path ) ) {
			continue;
		}
		if ( preg_match_all( '/data-lucide=["\']([a-z0-9-]+)["\']/', (string) file_get_contents( $path ), $m ) ) {
			$names = array_merge( $names, $m[1] );
		}
	}
}
$missing = array_filter( array_unique( $names ), static fn( $n ) => ! $known( $n ) );
$check( 'every data-lucide name in templates, src and scripts is in the bundle (' . implode( ',', $missing ) . ')', ! $missing );

$icons   = array_unique( array_column( wpss_notification_types(), 'icon' ) );
$missing = array_filter( $icons, static fn( $n ) => ! $known( (string) $n ) );
$check( 'every notification registry icon is in the bundle (' . implode( ',', $missing ) . ')', ! $missing );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
