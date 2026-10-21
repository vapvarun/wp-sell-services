<?php
/**
 * A draft cannot go live past the vendor's service limit through any door.
 *
 * Run: wp eval-file tests/test-service-limit-every-publish-door.php
 *
 * The dashboard Publish toggle calls PUT /services/{id} {status: publish}. That
 * route never asked the limit question the wizard and POST /services ask, so a
 * vendor at 28 of 20 published a draft from My Services. The question is now
 * one helper, wpss_service_limit_error(), used by all three. Every option and
 * post touched is restored.
 *
 * @package WPSellServices
 */

// Registered before exit-on-fail.php: its shutdown handler exits on a FAIL, which
// would skip any handler registered after it and leave the options changed.
$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;

// A missing helper (the old code) must fail an assertion, not fatal and skip the restore.
$limit = static function ( int $service_id, int $user_id ) {
	return function_exists( 'wpss_service_limit_error' ) ? wpss_service_limit_error( $service_id, $user_id ) : 'MISSING';
};
$check( 'one helper answers the limit question', function_exists( 'wpss_service_limit_error' ) );

// A non-admin vendor who already owns at least one live service.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$vendor_id = (int) $wpdb->get_var(
	"SELECT p.post_author FROM {$wpdb->posts} p
	JOIN {$wpdb->prefix}wpss_vendor_profiles v ON v.user_id = p.post_author AND v.status = 'active'
	WHERE p.post_type = 'wpss_service' AND p.post_status = 'publish'
	GROUP BY p.post_author ORDER BY p.post_author DESC LIMIT 1"
);

if ( ! $vendor_id || user_can( $vendor_id, 'manage_options' ) ) {
	echo "SKIP  no active non-admin vendor with a live service; nothing to assert\n";
	exit( 0 );
}

$orig = get_option( 'wpss_vendor' );
$opts = is_array( $orig ) ? $orig : array();
update_option( 'wpss_vendor', array_merge( $opts, array( 'max_services_per_vendor' => 1 ) ) );

$draft_id = wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'draft',
		'post_title'  => 'QA-SMOKE limit door draft',
		'post_author' => $vendor_id,
	)
);

$restore = static function () use ( $orig, $draft_id ) {
	update_option( 'wpss_vendor', $orig );
	if ( $draft_id ) {
		wp_delete_post( $draft_id, true );
	}
};

wp_set_current_user( $vendor_id );

$put = static function ( string $status ) use ( $draft_id ) {
	$request = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $draft_id );
	$request->set_body_params( array( 'status' => $status ) );
	return rest_do_request( $request );
};

$res = $put( 'publish' );
$check( 'REST publish of a draft at the limit answers 403', 403 === $res->get_status(), 'status ' . $res->get_status() );
$data = $res->get_data();
$check( 'and says wpss_service_limit_reached', 'wpss_service_limit_reached' === ( $data['code'] ?? '' ), wp_json_encode( $data ) );
$check( 'the draft stays a draft', 'draft' === get_post_status( $draft_id ) );

$res = $put( 'draft' );
$check( 'saving it as a draft is still allowed', 200 === $res->get_status(), 'status ' . $res->get_status() );

$check( 'helper holds a draft at the limit', '' !== $limit( $draft_id, $vendor_id ) );
$check( 'helper holds a not-yet-created service at the limit', '' !== $limit( 0, $vendor_id ) );

// A service that is already live counts already: re-publishing is not blocked.
$live_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpss_service' AND post_status = 'publish' AND post_author = %d LIMIT 1", $vendor_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$check( 'helper does not hold a service that is already live', '' === $limit( $live_id, $vendor_id ) );

// Only publish/pending are already counted; a trashed or paused service taking a live slot is a new slot.
$wpdb->update( $wpdb->posts, array( 'post_status' => 'trash' ), array( 'ID' => $draft_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
clean_post_cache( $draft_id );
$check( 'helper holds a trashed service at the limit', '' !== $limit( $draft_id, $vendor_id ) );
$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $draft_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
clean_post_cache( $draft_id );

update_option( 'wpss_vendor', array_merge( $opts, array( 'max_services_per_vendor' => 500 ) ) );
$check( 'with room under the limit the helper lets a draft through', '' === $limit( $draft_id, $vendor_id ) );

wp_set_current_user( 1 );
update_option( 'wpss_vendor', array_merge( $opts, array( 'max_services_per_vendor' => 1 ) ) );
$check( 'the site owner is not bound by the limit', '' === $limit( $draft_id, 1 ) );

wp_set_current_user( 0 );

exit( $fails ? 1 : 0 );
