<?php
/**
 * A public (nopriv) admin-ajax action is a door anyone can knock on, so each
 * one must still be used by the plugin. An action no script or template calls
 * is removed, not left open (live_search shipped that way until 1.9.2).
 *
 * Run: wp eval-file tests/test-public-ajax-has-a-caller.php
 *
 * @package WPSellServices\Tests
 */

require __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( bool $ok, string $label ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	if ( ! $ok ) {
		++$fails;
	}
};

$roots = array( dirname( __DIR__ ) );
$pro   = dirname( __DIR__, 2 ) . '/wp-sell-services-pro';
if ( is_dir( $pro ) ) {
	$roots[] = $pro;
}

// Read every source file once: PHP under src/ and templates/, and unminified JS under assets/.
$files = array();
foreach ( $roots as $root ) {
	foreach ( array( 'src', 'templates', 'assets' ) as $dir ) {
		if ( ! is_dir( "$root/$dir" ) ) {
			continue;
		}
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( "$root/$dir", FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			$path = $f->getPathname();
			if ( preg_match( '/\.(php|js)$/', $path ) && ! str_ends_with( $path, '.min.js' ) && false === strpos( $path, '/vendor/' ) ) {
				$files[ $path ] = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			}
		}
	}
}

$actions = array();
foreach ( $files as $path => $src ) {
	if ( preg_match_all( "/['\"]wp_ajax_nopriv_([a-z0-9_]+)['\"]/", $src, $m ) ) {
		foreach ( $m[1] as $action ) {
			$actions[ $action ] = $path;
		}
	}
}

$check( count( $actions ) > 0, 'found public ajax actions to check (' . count( $actions ) . ')' );

foreach ( $actions as $action => $registered_in ) {
	$called = false;
	foreach ( $files as $path => $src ) {
		// A caller is any other file naming the action, or the registering file naming it outside a hook name.
		$hits = substr_count( $src, $action ) - ( $path === $registered_in ? preg_match_all( "/wp_ajax_(nopriv_)?$action\b/", $src ) : 0 );
		if ( $hits > 0 ) {
			$called = true;
			break;
		}
	}
	$check( $called, "public ajax action $action has a caller" );
}

$check( false === has_action( 'wp_ajax_nopriv_wpss_live_search' ) && false === has_action( 'wp_ajax_wpss_live_search' ), 'the uncalled live_search door is closed' );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
