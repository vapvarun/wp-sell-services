<?php
/**
 * No wp-admin menu entry may lead a vendor to a 403 (Basecamp 10379867128).
 *
 * The plugin menu needs manage_options, and WordPress refuses every child of a parent the viewer
 * cannot open, so post types parked under it dead-ended for a vendor who also holds edit_posts.
 *
 * Run: wp eval-file tests/test-menu-no-dead-ends.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$f = wpss_test_fixtures();

wp_set_current_user( $f->admin );
$check( 'admin: post types stay under the plugin menu', 'wp-sell-services' === wpss_cpt_menu_parent() );

wp_set_current_user( $f->vendor );
$check( 'vendor: post types get their own entry, not a parent they cannot open', true === wpss_cpt_menu_parent() );

foreach ( array( 'ServicePostType', 'BuyerRequestPostType' ) as $class ) {
	$src = (string) file_get_contents( WPSS_PLUGIN_DIR . "src/PostTypes/{$class}.php" );
	$check( "{$class} takes its menu parent from the helper", str_contains( $src, 'wpss_cpt_menu_parent()' ) && ! str_contains( $src, "'show_in_menu'       => 'wp-sell-services'" ) );
}

wp_set_current_user( 0 );
exit( $fails ? 1 : 0 );
