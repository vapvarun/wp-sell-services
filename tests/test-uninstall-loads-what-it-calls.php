<?php
/**
 * Uninstall loads every helper it calls, and drops the tables last
 * (Basecamp 10545751792).
 *
 * uninstall.php runs with the plugin NOT loaded. It called
 * wpss_rmdir_recursive() after requiring src/functions/files.php, which does
 * not define it, so delete-data uninstall died after dropping the tables and
 * left posts, terms, options and user meta behind.
 *
 * Run plugin-less, so only what uninstall.php itself requires is loaded:
 *   wp eval-file tests/test-uninstall-loads-what-it-calls.php \
 *     --skip-plugins=wp-sell-services,wp-sell-services-pro
 *
 * Never runs the uninstall routine against the database.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$root = dirname( __DIR__ );
$src  = '';
// Code only, so a helper named in a comment is not mistaken for a call.
foreach ( token_get_all( (string) file_get_contents( $root . '/uninstall.php' ) ) as $token ) {
	if ( ! is_array( $token ) || ! in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
		$src .= is_array( $token ) ? $token[1] : $token;
	}
}

if ( function_exists( 'wpss_get_page_definitions' ) ) {
	echo "SKIP  the plugin is loaded; re-run with --skip-plugins=wp-sell-services,wp-sell-services-pro\n";
	return;
}

// Load exactly what uninstall.php requires, the way it does.
preg_match_all( "/require_once\s+__DIR__\s*\.\s*'([^']+)'/", $src, $m );
foreach ( $m[1] as $rel ) {
	if ( '/vendor/autoload.php' !== $rel ) {
		require_once $root . $rel;
	}
}

$check( 'SchemaManager is loaded without the Composer autoloader', class_exists( '\WPSellServices\Database\SchemaManager', false ) );

preg_match_all( '/\b(wpss_[a-z0-9_]+)\s*\(/', $src, $calls );
foreach ( array_unique( $calls[1] ) as $fn ) {
	$check( "{$fn}() is defined by a file uninstall.php requires", function_exists( $fn ) );
}

$drop = strpos( $src, '$schema->uninstall()' );
$check( 'tables are dropped', false !== $drop );
foreach ( array( 'wpss_rmdir_recursive(', 'wp_delete_post(', 'wp_delete_term(', 'delete_option(', 'remove_role(' ) as $step ) {
	$at = strrpos( $src, $step );
	$check( "tables are dropped after {$step}", false !== $at && false !== $drop && $drop > $at );
}

echo "\n" . ( $fails ? "{$fails} failed" : 'all passed' ) . "\n";
