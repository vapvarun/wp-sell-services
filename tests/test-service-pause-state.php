<?php
/**
 * Pause means one thing: published and listed, not taking orders (Basecamp 10432412187).
 *
 * Run: wp eval-file tests/test-service-pause-state.php
 *
 * The dashboard Pause unpublished the service (losing its listing) while the admin metabox wrote
 * _wpss_status=paused. Both now go through wpss_set_service_paused().
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$service = get_posts(
	array(
		'post_type'      => 'wpss_service',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			array(
				'key'     => '_wpss_status',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'   => '_wpss_status',
				'value' => 'active',
			),
		),
	)
);

if ( ! $service ) {
	echo "SKIP  no active published service\n";
	return;
}

$service_id = (int) $service[0]->ID;
$vendor_id  = (int) $service[0]->post_author;
$before     = get_post_meta( $service_id, '_wpss_status', true );
$put        = static function ( array $body ) use ( $service_id ) {
	$request = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $service_id );
	$request->set_body_params( $body );
	return rest_do_request( $request );
};

try {
	$check( 'one writer exists for the pause state', function_exists( 'wpss_set_service_paused' ) );

	wp_set_current_user( $vendor_id );
	$response = $put( array( 'paused' => true ) );
	$check( 'the vendor can pause through REST', 200 === $response->get_status() );
	$check( 'a paused service stays published (still listed)', 'publish' === get_post_status( $service_id ) );
	$check( 'a paused service is not purchasable', '' !== wpss_service_unavailable_reason( $service_id ) );
	$check( 'the REST card says it is paused', true === ( $response->get_data()['paused'] ?? null ) );

	$response = $put( array( 'paused' => false ) );
	$check( 'resuming makes it purchasable again', 200 === $response->get_status() && '' === wpss_service_unavailable_reason( $service_id ) && false === ( $response->get_data()['paused'] ?? null ) );

	$dashboard = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/assets/js/unified-dashboard.min.js' );
	$check( 'the dashboard toggle pauses instead of unpublishing (served .min)', str_contains( $dashboard, 'paused:' ) && ! str_contains( $dashboard, '"publish"===' . 'e?"draft"' ) );

	$metabox = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Admin/Metaboxes/ServiceMetabox.php' );
	$check( 'the admin metabox writes through the same helper', str_contains( $metabox, 'wpss_set_service_paused(' ) );
} finally {
	wp_set_current_user( 0 );
	if ( '' === $before ) {
		delete_post_meta( $service_id, '_wpss_status' );
	} else {
		update_post_meta( $service_id, '_wpss_status', $before );
	}
}

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
