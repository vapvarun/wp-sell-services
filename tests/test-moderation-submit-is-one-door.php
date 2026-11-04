<?php
/**
 * Every way into the review queue goes through ModerationService::submit():
 * a second submit is a no-op (one owner notice, one audit row), a vendor who
 * edits or re-publishes a live service over REST goes back to review, and an
 * admin is not held up by the queue. A new service's first submit announces
 * (insert must not pre-stamp the queue meta), a wp-admin publish held at
 * pending announces too, and an admin's own save is never a review.
 *
 * Run: wp eval-file tests/test-moderation-submit-is-one-door.php
 *
 * Edits one live QA-owned service and forces moderation on through a filter
 * (nothing persists); the service is put back on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Pages\ServiceModerationPage;
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

$vendor_id  = wpss_test_fixture_user( 'vendor' );
if ( ! $vendor_id ) {
	echo "SKIP  no seeded vendor on this site\n";
	return;
}
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

// The guards register at boot only when moderation is on; switch them on for this run if it was off.
$guards = ModerationService::is_enabled() ? null : new ServiceModerationPage();

$restore = static function () use ( $service_id, $post_before, $meta_before, $guards ) {
	// As nobody, so neither the publish rules nor the moderation guards re-judge the put-back.
	wp_set_current_user( 0 );
	foreach ( $meta_before as $key => $value ) {
		if ( '' === $value ) {
			delete_post_meta( $service_id, $key );
		} else {
			update_post_meta( $service_id, $key, $value );
		}
	}
	wp_update_post(
		array(
			'ID'          => $service_id,
			'post_status' => $post_before->post_status,
			'post_title'  => $post_before->post_title,
		)
	);
	remove_filter( 'wpss_require_service_moderation', '__return_true' );
	if ( $guards ) {
		remove_action( 'save_post_wpss_service', array( $guards, 'set_default_moderation_status' ), 10 );
		remove_action( 'pre_get_posts', array( $guards, 'filter_frontend_queries' ) );
		remove_action( 'save_post_wpss_service', array( $guards, 'approve_admin_publish' ), 20 );
		remove_filter( 'wp_insert_post_data', array( $guards, 'intercept_publish' ), 10 );
	}
};

add_filter( 'wpss_require_service_moderation', '__return_true' );
if ( $guards ) {
	$guards->register_guards();
}

/*
 * Put the fixture live as nobody (user 0). As the vendor, a reset to publish ran
 * ServiceMetabox::enforce_publish_rules, which demoted a seeded service without
 * a main image to draft (CI seeds --no-images), so the test judged the publish
 * rules instead of moderation.
 */
$make_live = static function () use ( $service_id ) {
	$acting = get_current_user_id();
	wp_set_current_user( 0 );
	update_post_meta( $service_id, ModerationService::META_MODERATION_STATUS, ModerationService::STATUS_APPROVED );
	wp_update_post( array( 'ID' => $service_id, 'post_status' => 'publish' ) );
	clean_post_cache( $service_id );
	wp_set_current_user( $acting );
};

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
$make_live();
$check( 'the fixture is live before the edit', 'publish' === get_post_status( $service_id ), (string) get_post_status( $service_id ) );
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

// Any content field counts, not only the title: a category swap on a live service is reviewed too.
$make_live();
wp_set_current_user( $vendor_id );
$request = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $service_id );
$request->set_param( 'tags', array( 'qa-review-tag' ) );
rest_do_request( $request );
clean_post_cache( $service_id );
$check( 'a tag-only edit of a live service goes back to review', 'pending' === get_post_status( $service_id ), (string) get_post_status( $service_id ) );
wp_set_object_terms( $service_id, array(), 'wpss_service_tag' );
$qa_tag = get_term_by( 'slug', 'qa-review-tag', 'wpss_service_tag' );
if ( $qa_tag ) {
	wp_delete_term( $qa_tag->term_id, 'wpss_service_tag' );
}

// A vendor cannot publish straight past the queue.
$fired   = 0;
$request = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $service_id );
$request->set_param( 'status', 'publish' );
rest_do_request( $request );
clean_post_cache( $service_id );
$check( 'status=publish with moderation on does not go live', 'publish' !== get_post_status( $service_id ), (string) get_post_status( $service_id ) );
$check( 'a service already queued is not re-announced', 0 === $fired, (string) $fired );

// An admin is not held up.
$make_live();
wp_set_current_user( 1 );
$request = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $service_id );
$request->set_param( 'title', $post_before->post_title );
rest_do_request( $request );
clean_post_cache( $service_id );
$check( 'an admin edit stays live', 'publish' === get_post_status( $service_id ), (string) get_post_status( $service_id ) );

// An admin's save is the approval on every door: the wizard and REST ask one rule.
$check( 'an admin save is not a review', method_exists( ModerationService::class, 'requires_review' ) && ! ModerationService::requires_review( 1 ) );
$check( 'a vendor save is a review', method_exists( ModerationService::class, 'requires_review' ) && ModerationService::requires_review( $vendor_id ) );
$wizard_src = (string) file_get_contents( dirname( __DIR__ ) . '/src/Frontend/ServiceWizard.php' );
$check( 'the wizard asks the shared rule, not is_enabled() alone', ! str_contains( $wizard_src, "ModerationService::is_enabled() ? 'pending'" ) );

/*
 * A new service: the wizard and REST create insert a draft, settle it at
 * pending and submit. Insert used to stamp the queue meta, so that submit was a
 * no-op and the owner was never told. Room under the limit, so the limit gate
 * does not hold the fixture at draft.
 */
add_filter( 'wpss_vendor_can_create_service', '__return_true', 99 );
wp_set_current_user( $vendor_id );
$fired  = 0;
$new_id = (int) wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_title'  => 'QA moderation first submit',
		'post_status' => 'draft',
		'post_author' => $vendor_id,
	)
);
$check( 'a new draft is not queued on insert', '' === get_post_meta( $new_id, ModerationService::META_MODERATION_STATUS, true ), (string) get_post_meta( $new_id, ModerationService::META_MODERATION_STATUS, true ) );
wpss_settle_service_status( $new_id, 'pending' );
$moderation->submit( $new_id );
$check( 'the first submit of a new service is pending', 'pending' === get_post_status( $new_id ), (string) get_post_status( $new_id ) );
$check( 'the first submit of a new service announces once', 1 === $fired, (string) $fired );
wp_update_post( array( 'ID' => $new_id, 'post_title' => 'QA moderation first submit, edited' ) );
$moderation->submit( $new_id );
$check( 'saving it again while queued announces nothing', 1 === $fired, (string) $fired );

// wp-admin: a vendor pressing Publish is held at pending, and that is a submit.
$fired    = 0;
$admin_id = (int) wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_title'  => 'QA moderation wp-admin publish',
		'post_status' => 'draft',
		'post_author' => $vendor_id,
	)
);
wp_update_post( array( 'ID' => $admin_id, 'post_status' => 'publish' ) );
clean_post_cache( $admin_id );
$check( 'a vendor publish from wp-admin is held at pending', 'pending' === get_post_status( $admin_id ), (string) get_post_status( $admin_id ) );
$check( 'the held publish announces once', 1 === $fired, (string) $fired );
remove_filter( 'wpss_vendor_can_create_service', '__return_true', 99 );

wp_set_current_user( 0 );
wp_delete_post( $new_id, true );
wp_delete_post( $admin_id, true );

remove_action( 'wpss_service_pending_moderation', $count );
