<?php
/**
 * Every class in src/Services is used somewhere (Basecamp 10553424333).
 *
 * SearchService (594 lines) was referenced only by an unused `use` line, so the three
 * search filters it fired were documented and never ran. A service that nothing
 * references outside its own file, ignoring `use` imports, fails here. Pro counts.
 *
 * Run: wp eval-file tests/test-no-orphan-services.php
 *
 * Reads files only; writes nothing.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$root  = dirname( __DIR__ );
$files = array();
foreach ( array( $root . '/src', $root . '/templates', dirname( $root ) . '/wp-sell-services-pro/src' ) as $dir ) {
	if ( ! is_dir( $dir ) ) {
		continue;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( 'php' === $file->getExtension() ) {
			$files[ $file->getPathname() ] = preg_replace( '/^\s*use\s+[^;]+;\s*$/m', '', (string) file_get_contents( $file->getPathname() ) );
		}
	}
}
$files += array_map( 'file_get_contents', array_combine( glob( $root . '/*.php' ), glob( $root . '/*.php' ) ) );

$orphans = array();
foreach ( glob( $root . '/src/Services/*.php' ) as $service ) {
	$class = basename( $service, '.php' );
	$used  = false;
	foreach ( $files as $path => $code ) {
		if ( $path !== $service && preg_match( '/\b' . preg_quote( $class, '/' ) . '\b/', $code ) ) {
			$used = true;
			break;
		}
	}
	if ( ! $used ) {
		$orphans[] = $class;
	}
}

$check( 'every service class is referenced outside its own file (use imports ignored)', array() === $orphans, implode( ', ', $orphans ) );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
