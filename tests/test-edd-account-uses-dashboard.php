<?php
/**
 * The EDD rail sends vendors and buyers to the plugin dashboard, like every other rail (Basecamp 10431833430).
 *
 * Pro's EDD account endpoints were built on the EDD 2.x account API that EDD 3
 * removed, so they never rendered. They now redirect to the matching dashboard
 * section, the provider's URLs point at the dashboard, and EDD's order history
 * carries a signpost. EDD need not be active: the provider only reads WP state.
 *
 * Run: wp eval-file tests/test-edd-account-uses-dashboard.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$class = '\\WPSellServicesPro\\Integrations\\EDD\\EDDAccountProvider';
if ( ! class_exists( $class ) ) {
	echo "SKIP  EDD adapter not loadable\n";
	exit( 0 );
}

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wp_query;
$provider = new $class();
$saved_qv = $wp_query->query_vars;

$vendor = 0;
foreach ( get_users( array( 'number' => 50 ) ) as $u ) {
	// Not an admin: an admin may open the dashboard anyway, which would hide the access check.
	if ( wpss_is_vendor( $u->ID ) && ! user_can( $u->ID, 'manage_options' ) ) {
		$vendor = $u->ID;
		break;
	}
}

$download = 0;
try {
	// 1. The provider's URLs are the dashboard's.
	$check( 'orders URL is the dashboard Orders section', wpss_get_dashboard_url( 'orders' ) === $provider->get_orders_url() );
	$check( 'vendor dashboard URL is the dashboard Services section', wpss_get_dashboard_url( 'services' ) === $provider->get_vendor_dashboard_url() );
	$check( 'an old endpoint name maps to its section', wpss_get_dashboard_url( 'notifications' ) === $provider->get_account_url( 'service-inbox' ) );

	// 2. Each retired endpoint redirects to its section.
	$has_target = method_exists( $provider, 'get_retired_endpoint_target' );
	$check( 'the provider resolves retired endpoints', $has_target );
	if ( $has_target ) {
		foreach ( array( 'service-orders' => 'orders', 'my-services' => 'services', 'service-inbox' => 'notifications' ) as $endpoint => $section ) {
			$wp_query->query_vars = array( $endpoint => '' );
			$check( "/{$endpoint}/ redirects to the {$section} section", wpss_get_dashboard_url( $section ) === $provider->get_retired_endpoint_target() );
		}

		$order_id             = (int) $GLOBALS['wpdb']->get_var( "SELECT id FROM {$GLOBALS['wpdb']->prefix}wpss_orders ORDER BY id LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wp_query->query_vars = array( 'service-order' => (string) $order_id );
		$check( '/service-order/<id>/ redirects to that order', $order_id > 0 && wpss_get_order_url( $order_id ) === $provider->get_retired_endpoint_target() );

		$wp_query->query_vars = array( 'pagename' => 'anything' );
		$check( 'any other page is left alone', '' === $provider->get_retired_endpoint_target() );
	}

	// 3. The redirect is wired, the dead EDD 2.x hooks are not.
	$provider->register_endpoints();
	$check( 'the redirect runs on template_redirect', false !== has_action( 'template_redirect', array( $provider, 'redirect_retired_endpoints' ) ) );
	$check( 'EDD\'s Order History block gets the signpost', false !== has_filter( 'render_block_edd/order-history', array( $provider, 'prepend_signpost' ) ) );
	$src = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services-pro/src/Integrations/EDD/EDDAccountProvider.php' );
	$check( 'no EDD 2.x account hooks remain', ! preg_match( "/'edd_account_|edd_profile_editor_after/", $src ) );

	// 4. Signpost: a buyer with service orders sees it, someone with none does not.
	$buyer = (int) $GLOBALS['wpdb']->get_var( "SELECT customer_id FROM {$GLOBALS['wpdb']->prefix}wpss_orders WHERE customer_id > 0 ORDER BY id LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	wp_set_current_user( $buyer );
	$html = $provider->prepend_signpost( '<div class="edd-order-history"></div>' );
	$check( 'a buyer with service orders is pointed at the dashboard', str_contains( $html, esc_url( wpss_get_dashboard_url( 'orders' ) ) ) && str_ends_with( $html, '<div class="edd-order-history"></div>' ) );
	$nobody = wp_insert_user( array( 'user_login' => 'qa_edd_nobody_' . wp_rand(), 'user_pass' => wp_generate_password(), 'user_email' => 'qa-edd-nobody-' . wp_rand() . '@example.test' ) );
	wp_set_current_user( (int) $nobody );
	$check( 'someone with no service orders sees no signpost', '<p></p>' === $provider->prepend_signpost( '<p></p>' ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( (int) $nobody );

	// 5. Access still keys on EDD's real download-type meta.
	wp_set_current_user( $vendor );
	$download = wp_insert_post( array( 'post_type' => 'download', 'post_status' => 'publish', 'post_title' => 'QA edd access', 'post_author' => $vendor ) );
	update_post_meta( $download, '_edd_product_type', 'service' );
	$check( 'the vendor who owns a service download may open the vendor dashboard', $vendor > 0 && $provider->can_access_vendor_dashboard() );
} finally {
	$wp_query->query_vars = $saved_qv;
	if ( $download ) {
		wp_delete_post( $download, true );
	}
	wp_set_current_user( 1 );
}

echo $fails ? "\n$fails FAILED\n" : "\nOK\n";
exit( $fails ? 1 : 0 );
