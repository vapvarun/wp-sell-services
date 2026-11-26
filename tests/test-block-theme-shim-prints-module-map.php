<?php
/**
 * Plugin pages on a block theme keep the theme's script modules working (Basecamp 10549276571).
 *
 * On a block theme core prints the script-module import map in <head>, because
 * its canvas renders the template first. wpss_get_header() calls wp_head() and
 * only then renders the header template part, so the navigation block's module
 * missed the map and failed ("@wordpress/interactivity" did not resolve) on the
 * services archive, single service and request pages in Twenty Twenty-Five.
 * The shim now prints the map in the footer, as core does for classic rendering.
 *
 * Run: wp eval-file tests/test-block-theme-shim-prints-module-map.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! wp_get_theme( 'twentytwentyfive' )->exists() ) {
	echo "SKIP  Twenty Twenty-Five is not installed\n";
	return;
}

$modules = wp_script_modules();
$methods = array( 'print_import_map', 'print_script_module_preloads', 'print_head_enqueued_script_modules' );

// The hooks core's add_hooks() sets for a block theme.
foreach ( $methods as $method ) {
	remove_action( 'wp_footer', array( $modules, $method ) );
	add_action( 'wp_head', array( $modules, $method ) );
}
$tt5 = static fn() => 'twentytwentyfive';
add_filter( 'template', $tt5 );
add_filter( 'stylesheet', $tt5 );

ob_start();
wpss_get_header();
ob_end_clean();

remove_filter( 'template', $tt5 );
remove_filter( 'stylesheet', $tt5 );

$check( 'the import map is no longer printed in <head>', false === has_action( 'wp_head', array( $modules, 'print_import_map' ) ) );
$check( 'it is printed in the footer, before the enqueued modules (10)', 9 === has_action( 'wp_footer', array( $modules, 'print_import_map' ) ) );
$check( 'preloads follow it to the footer', 9 === has_action( 'wp_footer', array( $modules, 'print_script_module_preloads' ) ) );
$check( 'no module is printed in <head> before the map exists', false === has_action( 'wp_head', array( $modules, 'print_head_enqueued_script_modules' ) ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
