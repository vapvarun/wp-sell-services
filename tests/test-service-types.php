<?php
/**
 * Service types: fixed (buy a package) or custom (quote first), Basecamp 10551449478.
 *
 * Run: wp eval-file tests/test-service-types.php
 *
 * Existing services read as fixed with no migration. A custom service sells no package on
 * any door (the shared purchase gate, checkout pricer, cart, REST cart), its buy box asks
 * for a quote, and the type is chosen and shown in the wizard, admin metabox, REST and the
 * archive filter.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$check( 'a service type registry exists', function_exists( 'wpss_get_service_types' ) && function_exists( 'wpss_get_service_type' ) );

$buyer = (int) ( get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
$admin = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
$found = get_posts(
	array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_wpss_service_type',
				'compare' => 'NOT EXISTS',
			),
		),
	)
);
$found = array_values( array_filter( $found, static fn( $s ) => '' === wpss_service_unavailable_reason( (int) $s->ID ) && (int) $s->post_author !== $buyer ) );

if ( count( $found ) < 2 || ! $buyer || ! $admin ) {
	echo "SKIP  needs two purchasable services with no type saved, a subscriber and an admin\n";
	return;
}

$sid      = (int) $found[0]->ID;
$other    = (int) $found[1]->ID;
$vendor   = (int) $found[0]->post_author;
$packages = wpss_get_service_packages( $sid );
$pkg_ref  = (int) ( $packages[0]['id'] ?? 0 );
$card     = static fn( int $id ) => wpss_rest_service_card( get_post( $id ) );
$render   = static function ( string $template, int $id ): string {
	global $post;
	$post = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );
	ob_start();
	include wpss_locate_template( $template );
	$html = (string) ob_get_clean();
	wp_reset_postdata();
	return $html;
};
$types_filter = static function ( array $types ): array {
	$types['qa_booking'] = array(
		'label'       => 'QA booking',
		'description' => 'Added by another plugin.',
		'purchasable' => true,
		'steps'       => array( 'basic', 'review' ),
		'addons'      => false,
	);
	return $types;
};

try {
	$types = wpss_get_service_types();
	$check( 'fixed and custom are registered', isset( $types['fixed'], $types['custom'] ) );
	$check( '  custom sells no package and skips requirements and add-ons', false === $types['custom']['purchasable'] && ! in_array( 'requirements', $types['custom']['steps'], true ) && false === $types['custom']['addons'] );

	// 1. Existing services are unchanged.
	$check( 'a service with no type saved reads as fixed', 'fixed' === wpss_get_service_type( $sid ) );
	$check( '  and stays purchasable', '' === wpss_service_unavailable_reason( $sid ) );
	$check( '  REST says fixed', 'fixed' === ( $card( $sid )['service_type'] ?? '' ) );
	wp_set_current_user( $buyer );
	$fixed_box = $render( 'partials/service-packages.php', $sid );
	$check( '  its buy box still sells the package', str_contains( $fixed_box, 'wpss-order-btn"' ) && ! str_contains( $fixed_box, 'Ask for a quote' ) );
	update_post_meta( $sid, '_wpss_service_type', 'gone_plugin_type' );
	$check( 'a type whose plugin is gone reads as fixed', 'fixed' === wpss_get_service_type( $sid ) && '' === wpss_service_unavailable_reason( $sid ) );
	delete_post_meta( $sid, '_wpss_service_type' );

	// 2. The vendor switches to custom through REST.
	wp_set_current_user( $vendor );
	$req = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $sid );
	$req->set_body_params( array( 'service_type' => 'custom' ) );
	$res = rest_do_request( $req );
	$check( 'the vendor sets custom through REST', 200 === $res->get_status() && 'custom' === ( $res->get_data()['service_type'] ?? '' ), (string) $res->get_status() );
	$req = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $sid );
	$req->set_body_params( array( 'service_type' => 'nonsense' ) );
	$check( '  an unknown type is refused', 400 === rest_do_request( $req )->get_status() && 'custom' === wpss_get_service_type( $sid ) );

	// 3. Every buy door refuses a custom service.
	$check( 'custom: the shared purchase gate refuses it', str_contains( wpss_service_unavailable_reason( $sid ), 'quote' ) );
	$line = \WPSellServices\Checkout\CheckoutIntentService::price_service_line( $sid, $pkg_ref, 1, array() );
	$check( '  the checkout pricer refuses it', is_wp_error( $line ) );
	$check( '  the cart refuses it', is_wp_error( \WPSellServices\Checkout\ServiceCart::add( $buyer, $sid, array( 'package_id' => $pkg_ref ) ) ) );
	wp_set_current_user( $buyer );
	$req = new WP_REST_Request( 'POST', '/wpss/v1/cart/add' );
	$req->set_body_params(
		array(
			'service_id' => $sid,
			'package_id' => $pkg_ref,
		)
	);
	$res = rest_do_request( $req );
	$check( '  the REST cart refuses it', $res->get_status() >= 400, (string) $res->get_status() );

	// 4. The page asks for a quote; the card names the type.
	$box = $render( 'partials/service-packages.php', $sid );
	$check( 'custom buy box: Ask for a quote and Post a request, no package button', str_contains( $box, 'Ask for a quote' ) && str_contains( $box, 'Post a request' ) && ! str_contains( $box, 'wpss-order-btn"' ) );
	$check( '  the quote button opens the contact form', (bool) preg_match( '/wpss-contact-btn"\s+data-vendor="' . $vendor . '"/', $box ) );
	$check( 'the service card says Custom quote', str_contains( $render( 'content-service-card.php', $sid ), 'Custom quote, from' ) );
	$check( '  a fixed card still says Starting at', str_contains( $render( 'content-service-card.php', $other ), 'Starting at' ) );

	// 5. Listing filters: fixed includes services with no type saved.
	$ids = static fn( string $type ) => get_posts(
		array(
			'post_type'      => 'wpss_service',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array( wpss_service_type_meta_query( $type ) ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		)
	);
	$custom_ids = array_map( 'intval', (array) $ids( 'custom' ) );
	$fixed_ids  = array_map( 'intval', (array) $ids( 'fixed' ) );
	$check( 'filter custom: finds it', in_array( $sid, $custom_ids, true ) && ! in_array( $other, $custom_ids, true ) );
	$check( 'filter fixed: finds the untyped service, not the custom one', in_array( $other, $fixed_ids, true ) && ! in_array( $sid, $fixed_ids, true ) );
	$req = new WP_REST_Request( 'GET', '/wpss/v1/services' );
	$req->set_query_params(
		array(
			'service_type' => 'custom',
			'per_page'     => 100,
		)
	);
	$rest_ids = array_map( 'intval', wp_list_pluck( (array) rest_do_request( $req )->get_data(), 'id' ) );
	$check( 'GET /services?service_type=custom lists it and not the fixed one', in_array( $sid, $rest_ids, true ) && ! in_array( $other, $rest_ids, true ), wp_json_encode( array_slice( $rest_ids, 0, 10 ) ) );
	$archive = new \WPSellServices\Frontend\ServiceArchiveView();
	$_GET['service_type'] = 'custom';
	ob_start();
	$archive->render_sidebar();
	$sidebar = (string) ob_get_clean();
	unset( $_GET['service_type'] );
	$check( 'the archive sidebar offers the type filter, custom ticked', (bool) preg_match( '/name="service_type" value="custom"\s+checked/', $sidebar ) );

	// 6. Wizard and admin.
	wp_set_current_user( $vendor );
	$wizard = new \WPSellServices\Frontend\ServiceWizard();
	$call   = static function ( string $method, array $args ) use ( $wizard ) {
		$m = new ReflectionMethod( $wizard, $method );
		$m->setAccessible( true );
		return $m->invokeArgs( $wizard, $args );
	};
	$check( 'wizard loads the saved type', 'custom' === ( $call( 'get_service_data', array( $sid ) )['service_type'] ?? '' ) );
	$check( '  an unknown type from the browser is saved as fixed', 'fixed' === $call( 'sanitize_service_data', array( array( 'service_type' => '<b>x' ) ) )['service_type'] );
	$js = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/service-wizard.min.js' );
	$check( '  the served wizard JS follows the type (steps + add-ons)', str_contains( $js, 'serviceTypes' ) && str_contains( $js, 'typeHasAddons' ) );
	ob_start();
	$call( 'render_step_basic', array( get_post( $sid ) ) );
	$basic = (string) ob_get_clean();
	$check( '  step 1 offers the type choice', str_contains( $basic, 'name="service_type" value="custom"' ) );

	wp_set_current_user( $admin );
	$_POST = array(
		'wpss_service_nonce' => wp_create_nonce( 'wpss_service_meta' ),
		'wpss_service_type'  => 'fixed',
	);
	( new \WPSellServices\Admin\Metaboxes\ServiceMetabox() )->save_meta( $sid, get_post( $sid ) );
	$_POST = array();
	$check( 'the admin metabox saves the type', 'fixed' === wpss_get_service_type( $sid ) && '' === wpss_service_unavailable_reason( $sid ) );

	// 7. Another plugin adds a type without editing ours.
	add_filter( 'wpss_service_types', $types_filter );
	wpss_set_service_type( $other, 'qa_booking' );
	$check( 'a type added by filter can be saved and read', 'qa_booking' === wpss_get_service_type( $other ) );
	remove_filter( 'wpss_service_types', $types_filter );
	$check( '  and reads as fixed once that plugin is gone', 'fixed' === wpss_get_service_type( $other ) );
	$strip = static fn( array $t ): array => array( 'custom' => $t['custom'] );
	add_filter( 'wpss_service_types', $strip );
	$check( 'fixed cannot be filtered away', isset( wpss_get_service_types()['fixed'] ) );
	remove_filter( 'wpss_service_types', $strip );
} finally {
	wp_set_current_user( 0 );
	$_POST = array();
	delete_post_meta( $sid, '_wpss_service_type' );
	delete_post_meta( $other, '_wpss_service_type' );
	\WPSellServices\Checkout\ServiceCart::remove_line( $buyer, $sid, $pkg_ref );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
