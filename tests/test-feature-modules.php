<?php
/**
 * Feature modules: one registry, one switch per feature (Basecamp 10551496924).
 *
 * Free owns the registry (filter wpss_modules), the switches (option wpss_modules, id => on), the
 * Features screen and GET|POST /modules; Pro only registers. A module needs every module it
 * requires; switching one off switches its dependants off. An upgrade keeps each feature's old
 * switch, and the old switch has no reader left. Off hides new use only.
 *
 * Run: wp eval-file tests/test-feature-modules.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$check( 'the registry exists', function_exists( 'wpss_modules' ) && function_exists( 'wpss_module_enabled' ) && function_exists( 'wpss_set_modules' ) );
if ( ! function_exists( 'wpss_modules' ) ) {
	echo "\n1 FAILED\n";
	return;
}

$old_keys = array( 'wpss_modules', 'wpss_pro_tipping_enabled', 'wpss_pro_vendor_subscriptions_enabled', 'wpss_white_label', 'wpss_pro_currency', 'wpss_pro_push_settings' );
$keep     = array();
foreach ( $old_keys as $opt ) {
	$keep[ $opt ] = get_option( $opt, null );
}
$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
$pro   = class_exists( \WPSellServicesPro\Core\Pro::class );
$rest  = static function ( string $method, string $route, array $body = array() ) {
	$request = new WP_REST_Request( $method, $route );
	if ( $body ) {
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}
	return rest_do_request( $request );
};
$routes = static function (): array {
	$GLOBALS['wp_rest_server'] = null;
	return rest_get_server()->get_routes();
};

try {
	// 1. Requirements and the cascade, on fixture modules.
	add_filter(
		'wpss_modules',
		$fixture = static fn( $m ) => $m + array(
			'qa_base'  => array( 'name' => 'QA Base' ),
			'qa_child' => array(
				'name'     => 'QA Child',
				'requires' => array( 'qa_base' ),
			),
			'qa_grand' => array(
				'name'     => 'QA Grand',
				'requires' => array( 'qa_child' ),
			),
			'qa_later' => array(
				'name'      => 'QA Later',
				'available' => false,
			),
			'Bad Id'   => array( 'name' => 'x' ),
		)
	);
	wpss_modules( true );
	$check( 'a bad id is refused at registration', ! isset( wpss_modules()['Bad Id'] ) );
	$check( 'a module defaults to off unless it says otherwise', ! wpss_module_enabled( 'qa_base' ) );

	$refused = wpss_set_modules( array( 'qa_child' => true ) );
	$check( 'turning a module on while what it needs is off is refused, naming it', is_wp_error( $refused ) && 'wpss_module_needs' === $refused->get_error_code() && str_contains( $refused->get_error_message(), 'QA Base' ) );
	$check( '  and nothing was saved', ! wpss_module_enabled( 'qa_child' ) );

	wpss_set_modules( array( 'qa_base' => true ) );
	wpss_set_modules( array( 'qa_child' => true ) );
	wpss_set_modules( array( 'qa_grand' => true ) );
	$check( 'on in order: all three on', wpss_module_enabled( 'qa_base' ) && wpss_module_enabled( 'qa_child' ) && wpss_module_enabled( 'qa_grand' ) );

	$changed = wpss_set_modules( array( 'qa_base' => false ) );
	$check( 'turning the base off turns its dependants off too', ! is_wp_error( $changed ) && array( 'qa_base' => false, 'qa_child' => false, 'qa_grand' => false ) == $changed, wp_json_encode( $changed ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- key order is not the point.
	$check( '  and they read as off', ! wpss_module_enabled( 'qa_child' ) && ! wpss_module_enabled( 'qa_grand' ) );
	$check( 'an unknown module is refused', is_wp_error( wpss_set_modules( array( 'qa_nope' => true ) ) ) );
	$check( 'a module that is not available yet cannot be turned on', is_wp_error( wpss_set_modules( array( 'qa_later' => true ) ) ) && ! wpss_module_enabled( 'qa_later' ) );

	// 2. The screen's REST route: admin only, saves, refuses with the reason.
	wp_set_current_user( 0 );
	$check( 'GET /modules: a visitor is refused', 401 === $rest( 'GET', '/wpss/v1/modules' )->get_status() );
	wp_set_current_user( $admin );
	$got = $rest( 'GET', '/wpss/v1/modules' );
	$check( 'GET /modules: the admin gets the groups', 200 === $got->get_status() && isset( $got->get_data()['groups'] ) );
	$res = $rest( 'POST', '/wpss/v1/modules', array( 'enabled' => array( 'qa_child' => true ) ) );
	$check( 'POST /modules: a missing requirement answers 400 with the reason', 400 === $res->get_status() && 'wpss_module_needs' === ( $res->get_data()['code'] ?? '' ) );
	$res = $rest( 'POST', '/wpss/v1/modules', array( 'enabled' => array( 'qa_base' => true ) ) );
	$check( 'POST /modules: saves and reports the change', 200 === $res->get_status() && array( 'qa_base' => true ) === $res->get_data()['changed'] && wpss_module_enabled( 'qa_base' ) );
	$app = $rest( 'GET', '/wpss/v1/settings' )->get_data();
	$check( 'the app reads every module\'s state', true === ( $app['app']['features']['modules']['qa_base'] ?? null ) && false === ( $app['app']['features']['modules']['qa_child'] ?? null ) );
	remove_filter( 'wpss_modules', $fixture );
	wpss_modules( true );

	if ( $pro ) {
		// 3. Pro registers, Free owns the switch; every Pro feature that had a switch is a module.
		$mods = wpss_modules();
		foreach ( array( 'tipping', 'vendor_subscriptions', 'tiered_commission', 'white_label', 'display_currency', 'push_notifications', 'analytics', 'recurring_services' ) as $id ) {
			$check( "Pro registers {$id}", isset( $mods[ $id ] ) && 'pro' === $mods[ $id ]['tier'] );
		}
		$check( 'recurring services stay unavailable (owner, 2026-11-27)', ! $mods['recurring_services']['available'] && ! wpss_module_enabled( 'recurring_services' ) );

		// 4. Fresh install: no old switch, no saved modules -> each default.
		foreach ( $old_keys as $opt ) {
			delete_option( $opt );
		}
		wpss_seed_modules();
		$state = array_map( 'wpss_module_enabled', array_column( wpss_modules(), 'id', 'id' ) );
		$check(
			'fresh install: tips, tiered commission and analytics on; the rest off',
			array(
				'tipping'              => true,
				'vendor_subscriptions' => false,
				'tiered_commission'    => true,
				'white_label'          => false,
				'display_currency'     => false,
				'push_notifications'   => false,
				'analytics'            => true,
				'recurring_services'   => false,
			) === array_intersect_key( $state, array_flip( array( 'tipping', 'vendor_subscriptions', 'tiered_commission', 'white_label', 'display_currency', 'push_notifications', 'analytics', 'recurring_services' ) ) ),
			wp_json_encode( $state )
		);
		$check( '  and nothing was written for them', array() === (array) get_option( 'wpss_modules', array() ) );

		// 5. Upgrade: every old switch is kept, on or off.
		delete_option( 'wpss_modules' );
		update_option( 'wpss_pro_tipping_enabled', '0' );
		update_option( 'wpss_pro_vendor_subscriptions_enabled', 1 );
		update_option( 'wpss_white_label', array( 'enabled' => true, 'brand_name' => 'QA' ) );
		update_option( 'wpss_pro_currency', array( 'enabled' => true ) );
		update_option( 'wpss_pro_push_settings', array( 'enabled' => 1, 'credentials' => '' ) );
		wpss_seed_modules();
		$check(
			'upgrade: tips that were off stay off, every feature that was on stays on',
			! wpss_module_enabled( 'tipping' ) && wpss_module_enabled( 'vendor_subscriptions' ) && wpss_module_enabled( 'white_label' ) && wpss_module_enabled( 'display_currency' ) && wpss_module_enabled( 'push_notifications' )
		);
		wpss_set_modules( array( 'white_label' => false ) );
		wpss_seed_modules();
		$check( '  a later seed never overrides the owner\'s choice', ! wpss_module_enabled( 'white_label' ) );

		// 6. Each feature reads its module, not its old switch.
		update_option( 'wpss_white_label', array( 'enabled' => true ) );
		update_option( 'wpss_pro_push_settings', array( 'enabled' => 1, 'credentials' => '{"x":1}' ) );
		update_option( 'wpss_pro_currency', array( 'enabled' => true, 'app_id' => wpss_encrypt_secret( 'qa-app', '' ) ) );
		update_option( 'wpss_pro_vendor_subscriptions_enabled', 1 );
		update_option( 'wpss_pro_tipping_enabled', '1' );
		wpss_set_modules(
			array(
				'tipping'              => false,
				'vendor_subscriptions' => false,
				'white_label'          => false,
				'display_currency'     => false,
				'push_notifications'   => false,
			)
		);
		$check( 'tips off: the old switch on does nothing', ! wpss_tipping_enabled() );
		$check( 'white label off: the old switch on does nothing', ! ( new \WPSellServicesPro\WhiteLabel\WhiteLabelManager() )->is_enabled() );
		$check( 'push off: the old switch on does nothing', ! ( new \WPSellServicesPro\Push\PushNotificationService() )->is_enabled() );
		$check( 'display currency off: a saved app id and the old switch do nothing', ! ( new \WPSellServicesPro\Currency\RateService() )->is_enabled() );
		add_filter( 'pre_option_wpss_pro_require_vendor_subscription', '__return_true' );
		$check( 'vendor subscriptions off: a required plan does not block a vendor', ( new \WPSellServicesPro\VendorSubscriptions\PlanEnforcer() )->can_create_service( true, 999999 ) );
		wpss_set_modules( array( 'vendor_subscriptions' => true ) );
		$check( '  on: it does', ! ( new \WPSellServicesPro\VendorSubscriptions\PlanEnforcer() )->can_create_service( true, 999999 ) );
		remove_filter( 'pre_option_wpss_pro_require_vendor_subscription', '__return_true' );

		wpss_set_modules( array( 'tipping' => true, 'display_currency' => true ) );
		$check( 'tips on: tipping is offered', wpss_tipping_enabled() );
		$check( 'display currency on with an app id: shown', ( new \WPSellServicesPro\Currency\RateService() )->is_enabled() );

		// 7. Off hides new use: the routes go.
		wpss_set_modules( array( 'tipping' => false, 'analytics' => false ) );
		$r = $routes();
		$check( 'tips off: no tip route', ! isset( $r['/wpss/v1/orders/(?P<order_id>[\d]+)/tip'] ) );
		$check( 'analytics off: no /analytics routes', ! isset( $r['/wpss/v1/analytics/overview'] ) && ! isset( $r['/wpss/v1/analytics/revenue'] ) );
		wpss_set_modules( array( 'tipping' => true, 'analytics' => true ) );
		$r = $routes();
		$check( 'back on: both return', isset( $r['/wpss/v1/orders/(?P<order_id>[\d]+)/tip'] ) && isset( $r['/wpss/v1/analytics/overview'] ) );

		// 8. The white-label API keeps its field and drives the one switch.
		$res = $rest( 'POST', '/wpss/v1/white-label', array( 'enabled' => true ) );
		$check( 'POST /white-label enabled: switches the module', 200 === $res->get_status() && wpss_module_enabled( 'white_label' ) && true === ( $rest( 'GET', '/wpss/v1/white-label' )->get_data()['enabled'] ?? null ) );

		// 9. The old switches have no reader left (outside the one-time seed).
		$pro_src = WPSS_PRO_PLUGIN_DIR . 'src';
		$readers = array();
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $pro_src, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
			$code = (string) file_get_contents( $file->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( str_ends_with( $file->getPathname(), 'Core/Pro.php' ) ) {
				continue;
			}
			if ( preg_match( "/wpss_pro_tipping_enabled|wpss_pro_vendor_subscriptions_enabled|\\[ ?'enabled' ?\\]/", $code ) && preg_match( '#/(WhiteLabel|Push|Currency|VendorSubscriptions|Tipping)/#', $file->getPathname() ) ) {
				$readers[] = str_replace( $pro_src, '', $file->getPathname() );
			}
		}
		$check( 'no Pro feature reads its old on/off key', ! $readers, implode( ', ', $readers ) );
		$pro_php = (string) file_get_contents( WPSS_PRO_PLUGIN_DIR . 'src/Core/Pro.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		preg_match( '/public function init\(\): void \{(.*?)\n\t\}\n/s', $pro_php, $init_body );
		$check( 'Pro::init() boots no module itself; Free boots them', isset( $init_body[1] ) && ! preg_match( '/init_(white_label|vendor_subscriptions|recurring_services|push_notifications|tiered_commission|analytics)\(|TippingSettings/', $init_body[1] ) );

		// 10. Free alone: without Pro's registration there is no Pro module and no Features tab.
		global $wp_filter;
		$pro_hooks = array();
		foreach ( $wp_filter['wpss_modules']->callbacks ?? array() as $priority => $callbacks ) {
			foreach ( $callbacks as $cb ) {
				if ( is_array( $cb['function'] ) && is_object( $cb['function'][0] ) && str_starts_with( get_class( $cb['function'][0] ), 'WPSellServicesPro\\' ) ) {
					$pro_hooks[] = array( $cb['function'], $priority );
				}
			}
		}
		foreach ( $pro_hooks as $hook ) {
			remove_filter( 'wpss_modules', $hook[0], $hook[1] );
		}
		$alone = wpss_modules( true );
		$check( 'Free alone: no Pro module is listed', ! array_filter( $alone, static fn( $m ) => 'pro' === $m['tier'] ) );
		foreach ( $pro_hooks as $hook ) {
			add_filter( 'wpss_modules', $hook[0], $hook[1] );
		}
		wpss_modules( true );
	}
} finally {
	foreach ( $keep as $opt => $value ) {
		null === $value ? delete_option( $opt ) : update_option( $opt, $value );
	}
	wpss_modules( true );
	$GLOBALS['wp_rest_server'] = null;
	wp_set_current_user( 0 );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
