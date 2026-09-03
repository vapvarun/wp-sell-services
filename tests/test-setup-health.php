<?php
/**
 * Setup-health contract (Basecamp 10264290487).
 *
 * A trashed mapped page turned every link into ?page_id=N with no notice, an
 * install with every gateway off said "demo payments are on" while buyers
 * saw no payment methods, tracking consent was written by the plugin itself,
 * and uninstall left scheduled jobs and plain-prefixed user meta behind.
 *
 * Run: wp eval-file tests/test-setup-health.php
 *
 * Restores the cart page, the gateway options and the demo flag on the way out.
 *
 * @package WPSellServices
 */

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

echo "\nSetup-health contract\n\n";

$root  = dirname( __DIR__ );
$admin = new \WPSellServices\Admin\Admin();
wpss_t( method_exists( $admin, 'get_setup_health_problems' ), 'one setup-health builder exists' );
wpss_t( ! method_exists( $admin, 'demo_payments_notice' ) && ! method_exists( $admin, 'check_page_setup_notice' ), 'the demo and pages notices are folded into it' );

// ---------------------------------------------------------------- trashed page

$cart_id = (int) ( get_option( 'wpss_pages', array() )['cart'] ?? 0 );

if ( ! $cart_id || 'publish' !== get_post_status( $cart_id ) ) {
	echo "  SKIP  no published cart page mapped on this site\n";
} else {
	wp_trash_post( $cart_id );

	wpss_t( 0 === wpss_get_page_id( 'cart' ), 'a trashed mapped page resolves to 0' );
	wpss_t( false === strpos( wpss_get_page_url( 'cart' ), 'page_id=' ), 'its URL falls back to the default slug rather than ?page_id=N' );

	$problems = method_exists( $admin, 'get_setup_health_problems' ) ? $admin->get_setup_health_problems() : array();
	wpss_t(
		isset( $problems['pages'] ) && false !== strpos( $problems['pages']['text'], 'Optional page Service Cart is missing or unpublished; links to it are hidden.' ),
		'the setup notice names the optional page and says its links are hidden'
	);

	wp_untrash_post( $cart_id );
	wp_update_post(
		array(
			'ID'          => $cart_id,
			'post_status' => 'publish',
		)
	);
	wpss_t( $cart_id === wpss_get_page_id( 'cart' ), 'restored: the cart page is published and mapped again' );
}

// ---------------------------------------------------------------- no gateway

/*
 * Gateway objects read their settings once per request, so the probe runs in
 * a subprocess - the same shape as a real admin page load.
 */
$gateway_options = array( 'wpss_stripe_settings', 'wpss_paypal_settings', 'wpss_offline_settings', 'wpss_test_gateway_settings' );
$restore         = array();

foreach ( $gateway_options as $name ) {
	$restore[ $name ] = get_option( $name );
	$off              = is_array( $restore[ $name ] ) ? $restore[ $name ] : array();
	$off['enabled']   = '';
	update_option( $name, $off );
}

$restore_demo = get_option( 'wpss_demo_payments', false );
update_option( 'wpss_demo_payments', 'no' );

$probe = 'wp eval \'$a = new \\WPSellServices\\Admin\\Admin(); echo method_exists( $a, "get_setup_health_problems" ) ? wp_json_encode( $a->get_setup_health_problems() ) : "{}";\' --path=' . escapeshellarg( ABSPATH ) . ' 2>/dev/null';
$out   = trim( (string) shell_exec( $probe ) );

foreach ( $restore as $name => $value ) {
	if ( false === $value ) {
		delete_option( $name );
	} else {
		update_option( $name, $value );
	}
}

if ( false === $restore_demo ) {
	delete_option( 'wpss_demo_payments' );
} else {
	update_option( 'wpss_demo_payments', $restore_demo );
}

if ( '' === $out ) {
	echo "  SKIP  could not run the subprocess check (wp-cli unavailable in this context)\n";
} else {
	wpss_t( false !== strpos( $out, 'No payment method is enabled. Buyers cannot check out.' ), 'with every gateway off the notice says no payment method is enabled' );
}

// ---------------------------------------------------------------- tracking consent

$main = (string) file_get_contents( $root . '/wp-sell-services.php' );
wpss_t(
	false === strpos( $main, "'allowed'   => true" ),
	'the preset-key flow no longer writes tracking consent on the owner\'s behalf'
);

// ---------------------------------------------------------------- uninstall

$uninstall = (string) file_get_contents( $root . '/uninstall.php' );
wpss_t( false !== strpos( $uninstall, "as_unschedule_all_actions( '', array(), 'wpss' )" ), 'uninstall sweeps the wpss Action Scheduler group' );
wpss_t( false !== strpos( $uninstall, "meta_key LIKE 'wpss\\_%'" ), 'uninstall deletes plain-prefixed user meta (wpss_payout_details, wpss_blocked_users)' );
wpss_t( false !== strpos( $uninstall, 'wpss_rmdir_recursive' ), 'uninstall removes the order-files directory' );

// The dismiss must reach PHP, or the pages notice comes back on every load.
$min = (string) file_get_contents( $root . '/assets/js/admin.min.js' );
wpss_t( false === strpos( $min, 'wpss_dismiss_pages_notice' ) && false !== strpos( $min, 'wpss_dismiss_notice' ), 'the minified admin bundle uses the one generic dismiss binding' );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
