<?php
/**
 * Every way into the review queue goes through ModerationService::submit():
 * a second submit is a no-op (one owner notice, one audit row), a vendor who
 * edits or re-publishes a live service over REST goes back to review, and an
 * admin is not held up by the queue.
 *
 * Run: wp eval-file tests/test-moderation-submit-is-one-door.php
 *
 * Edits one live QA-owned service and forces moderation on through a filter
 * (nothing persists); the service is put back on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\ModerationService;

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

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$vendor_id  = 7831;
$service_id = (int) ( get_posts(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'author'      => $vendor_id,
		'numberposts' => 1,
		'fields'      => 'ids',
	)
)[0] ?? 0 );

if ( ! $service_id ) {
	$check( 'a live service by the QA vendor exists', false );
	return;
}

$post_before = get_post( $service_id );
$meta_before = array(
	ModerationService::META_MODERATION_STATUS => get_post_meta( $service_id, ModerationService::META_MODERATION_STATUS, true ),
	ModerationService::META_REJECTION_REASON  => get_post_meta( $service_id, ModerationService::META_REJECTION_REASON, true ),
);

$restore = static function () use ( $service_id, $post_before, $meta_before ) {
	remove_filter( 'wpss_require_service_moderation', '__return_true' );
	wp_update_post(
		array(
			'ID'          => $service_id,
			'post_status' => $post_before->post_status,
			'post_title'  => $post_before->post_title,
		)
	);
	foreach ( $meta_before as $key => $value ) {
		if ( '' === $value ) {
			delete_post_meta( $service_id, $key );
		} else {
			update_post_meta( $service_id, $key, $value );
		}
	}
};

add_filter( 'wpss_require_service_moderation', '__return_true' );

$fired = 0;
$count = static function () use ( &$fired ) {
	++$fired;
};
add_action( 'wpss_service_pending_moderation', $count );

$moderation = new ModerationService();
delete_post_meta( $service_id, ModerationService::META_MODERATION_STATUS );
update_post_meta( $service_id, ModerationService::META_REJECTION_REASON, 'old reason' );

$check( 'first submit queues the service', true === $moderation->submit( $service_id ) );
$check( 'first submit fires the pending hook once', 1 === $fired, (string) $fired );
$check( 'submit drops a stale rejection reason', '' === get_post_meta( $service_id, ModerationService::META_REJECTION_REASON, true ) );
$check( 'second submit is a no-op', false === $moderation->submit( $service_id ) );
$check( 'second submit fires no hook', 1 === $fired, (string) $fired );

// Vendor edits the live listing over REST.
update_post_meta( $service_id, ModerationService::META_MODERATION_STATUS, ModerationService::STATUS_APPROVED );
wp_update_post( array( 'ID' => $service_id, 'post_status' => 'publish' ) );
$fired = 0;
wp_set_current_user( $vendor_id );
$request = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $service_id );
$request->set_param( 'title', $post_before->post_title . ' ' );
$response = rest_do_request( $request );
clean_post_cache( $service_id );
$check( 'a vendor edit of a live service is accepted', ! $response->is_error(), $response->is_error() ? wp_json_encode( $response->get_data() ) : '' );
$check( 'the edited live service goes back to review', 'pending' === get_post_status( $service_id ), (string) get_post_status( $service_id ) );
$check( 'the queue meta says pending', ModerationService::STATUS_PENDING === get_post_meta( $service_id, ModerationService::META_MODERATION_STATUS, true ) );
$check( 'the edit fired the pending hook once', 1 === $fired, (string) $fired );

// A vendor cannot publish straight past the queue.
$fired   = 0;
$request = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $service_id );
$request->set_param( 'status', 'publish' );
rest_do_request( $request );
clean_post_cache( $service_id );
$check( 'status=publish with moderation on does not go live', 'publish' !== get_post_status( $service_id ), (string) get_post_status( $service_id ) );
$check( 'a service already queued is not re-announced', 0 === $fired, (string) $fired );

// An admin is not held up.
wp_update_post( array( 'ID' => $service_id, 'post_status' => 'publish' ) );
wp_set_current_user( 1 );
$request = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $service_id );
$request->set_param( 'title', $post_before->post_title );
rest_do_request( $request );
clean_post_cache( $service_id );
$check( 'an admin edit stays live', 'publish' === get_post_status( $service_id ), (string) get_post_status( $service_id ) );

remove_action( 'wpss_service_pending_moderation', $count );
