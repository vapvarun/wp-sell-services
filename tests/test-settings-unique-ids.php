<?php
/**
 * Every Settings form has its own submit id (Basecamp 10475418881): the page
 * rendered 16 buttons with id="submit", so #submit always hit the General form.
 *
 * Run: wp eval-file tests/test-settings-unique-ids.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// Render the whole Settings page on the standalone rail (all tabs are in the DOM) in a fresh process.
$general = get_option( 'wpss_general', array() );
update_option( 'wpss_general', array_merge( (array) $general, array( 'ecommerce_platform' => 'standalone' ) ) );
$html = (string) WP_CLI::runcommand(
	'eval \'wp_set_current_user( 1 ); require_once ABSPATH . "wp-admin/includes/template.php"; set_current_screen( "dashboard" ); $s = new \\WPSellServices\\Admin\\Settings(); $s->register_settings(); do_action( "admin_init" ); $s->render();\'',
	array( 'launch' => true, 'return' => true, 'exit_error' => false )
);
update_option( 'wpss_general', $general );

preg_match_all( '/<(?:input|button)[^>]*type="submit"[^>]*>/', $html, $buttons );
$check( 'the page renders submit buttons (' . count( $buttons[0] ) . ')', count( $buttons[0] ) > 1 );

$submit_ids = array();
foreach ( $buttons[0] as $button ) {
	$submit_ids[] = preg_match( '/\sid="([^"]+)"/', $button, $m ) ? $m[1] : '(none)';
}
$dupes = array_keys( array_filter( array_count_values( $submit_ids ), static fn( $n ) => $n > 1 ) );
$check( 'no submit button id repeats (' . implode( ',', $dupes ) . ')', ! $dupes );
$check( 'no button keeps the default id="submit"', 0 === substr_count( $html, 'id="submit"' ) );

// The order screen holds up to four forms; each button has its own id.
$admin = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Admin/Admin.php' );
foreach ( array( 'wpss-order-update-status', 'wpss-order-assign-service', 'wpss-order-mark-refund-sent', 'wpss-order-retry-refund' ) as $id ) {
	$check( "order screen button {$id} is unique", 1 === substr_count( $admin, "'{$id}'" ) );
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
