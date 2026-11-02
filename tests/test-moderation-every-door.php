<?php
/**
 * With moderation on, no save door puts a member's change live without review (Basecamp 10435884968).
 *
 * Doors: core /wp/v2/wpss-services edit and schedule, the add-on sub-routes, and
 * this plugin's REST update, which must also say the save went to review.
 * Negative cases: a status-only save and a pause stay live.
 *
 * Run: wp eval-file tests/test-moderation-every-door.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Admin\Pages\ServiceModerationPage;
use WPSellServices\Services\ModerationService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;

$vendor = get_user_by( 'login', 'wpss_vendor_maya' );
$sid    = $vendor ? (int) ( get_posts(
	array(
		'post_type'   => 'wpss_service',
		'author'      => $vendor->ID,
		'post_status' => 'publish',
		'numberposts' => 1,
		'fields'      => 'ids',
	)
)[0] ?? 0 ) : 0;

if ( ! $sid ) {
	echo "FAIL  fixture: wpss_vendor_maya has no live service\n";
	exit( 1 );
}

$vendor_opts = get_option( 'wpss_vendor' );
$orig        = get_post( $sid );
$orig_addons = wpss_get_service_extras( $sid );
$orig_mod    = get_post_meta( $sid, ModerationService::META_MODERATION_STATUS, true );
$created     = array();
$review_line = __( 'Service submitted for review. You will be notified once it is approved.', 'wp-sell-services' );

$live = static function () use ( $sid, $wpdb ) {
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $sid ) );
	update_post_meta( $sid, ModerationService::META_MODERATION_STATUS, ModerationService::STATUS_APPROVED );
	clean_post_cache( $sid );
};
$rest = static function ( string $method, string $route, array $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
};
$queued = static function () use ( $sid ) {
	clean_post_cache( $sid );
	return 'pending' === get_post_status( $sid ) && ModerationService::STATUS_PENDING === get_post_meta( $sid, ModerationService::META_MODERATION_STATUS, true );
};

try {
	// Moderation on, guards registered as at boot.
	$opts                               = is_array( $vendor_opts ) ? $vendor_opts : array();
	$opts['require_service_moderation'] = 1;
	update_option( 'wpss_vendor', $opts );
	if ( ! ModerationService::is_enabled() ) {
		echo "FAIL  fixture: moderation did not switch on\n";
		exit( 1 );
	}
	// Booted with moderation off, so the guards are registered here exactly as Plugin.php does at boot.
	( new ServiceModerationPage() )->register_guards();
	add_filter( 'pre_wp_mail', '__return_true' );

	wp_set_current_user( $vendor->ID );

	// 1. Core /wp/v2 edit of a live, approved service.
	$live();
	$res = $rest( 'POST', "/wp/v2/wpss-services/$sid", array( 'title' => $orig->post_title . ' (edited)' ) );
	$check( 'core /wp/v2 title edit on a live service goes to review (http ' . $res->get_status() . ', ' . get_post_status( $sid ) . ')', $queued() );
	$wpdb->update( $wpdb->posts, array( 'post_title' => $orig->post_title ), array( 'ID' => $sid ) );

	// 2. Status-only save stays live: nothing buyers read changed.
	$live();
	wp_update_post(
		array(
			'ID'          => $sid,
			'post_status' => 'publish',
		)
	);
	clean_post_cache( $sid );
	$check( 'a status-only save of a live service stays live', 'publish' === get_post_status( $sid ) );

	// 3. Pause over this plugin's REST stays live (pausing is not an edit).
	$live();
	$res = $rest( 'PUT', "/wpss/v1/services/$sid", array( 'paused' => true ) );
	clean_post_cache( $sid );
	$check( 'pausing over REST keeps the service published (http ' . $res->get_status() . ', ' . get_post_status( $sid ) . ')', 'publish' === get_post_status( $sid ) );
	wpss_set_service_paused( $sid, false );

	// 4. Scheduling through core /wp/v2 is held for review.
	$res = $rest(
		'POST',
		'/wp/v2/wpss-services',
		array(
			'title'  => 'Moderation door test (future)',
			'status' => 'future',
			'date'   => gmdate( 'Y-m-d\TH:i:s', time() + DAY_IN_SECONDS ),
		)
	);
	$fid = (int) ( $res->get_data()['id'] ?? 0 );
	if ( $fid ) {
		$created[] = $fid;
	}
	$check( 'a scheduled service is held at pending (' . ( $fid ? get_post_status( $fid ) : 'not created' ) . ')', $fid && 'pending' === get_post_status( $fid ) );

	// 5. Add-on routes on a live service.
	$live();
	$res  = $rest(
		'POST',
		"/wpss/v1/services/$sid/addons",
		array(
			'title' => 'Moderation door add-on',
			'price' => 5,
		)
	);
	$data = (array) $res->get_data();
	$check( 'adding an add-on to a live service sends it to review', $queued() );
	$check( 'the add-on response says it went to review', in_array( $review_line, (array) ( $data['notices'] ?? array() ), true ) );
	$aid = (int) ( $data['id'] ?? -1 );

	$live();
	$rest( 'PUT', "/wpss/v1/services/$sid/addons/$aid", array( 'price' => 6 ) );
	$check( 'editing an add-on on a live service sends it to review', $queued() );

	$live();
	$rest( 'DELETE', "/wpss/v1/services/$sid/addons/$aid" );
	$check( 'deleting an add-on on a live service sends it to review', $queued() );

	// 6. This plugin's REST update says it went to review.
	$live();
	$res  = $rest( 'PUT', "/wpss/v1/services/$sid", array( 'title' => $orig->post_title ) );
	$data = (array) $res->get_data();
	$check( 'REST edit of a live service goes to review (http ' . $res->get_status() . ')', $queued() );
	$check( 'the REST update response says it went to review', in_array( $review_line, (array) ( $data['notices'] ?? array() ), true ) );

	// 6b. Approved, moved to draft, edited there, published again: reviewed again.
	$live();
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'draft' ), array( 'ID' => $sid ) );
	clean_post_cache( $sid );
	$res = $rest( 'POST', "/wp/v2/wpss-services/$sid", array( 'status' => 'publish' ) );
	$check( 'republishing an approved service from draft goes to review (http ' . $res->get_status() . ')', $queued() );

	// 6c. Core /wp/v2 edit of categories only (no post field changes).
	$live();
	$cats = wp_get_post_terms( $sid, 'wpss_service_category', array( 'fields' => 'ids' ) );
	$res  = $rest( 'POST', "/wp/v2/wpss-services/$sid", array( 'service-categories' => $cats ) );
	$check( 'core /wp/v2 category edit on a live service goes to review (http ' . $res->get_status() . ')', $queued() );

	// 7. An admin's edit is the approval: stays live.
	wp_set_current_user( 1 );
	$live();
	$res = $rest( 'POST', "/wp/v2/wpss-services/$sid", array( 'title' => $orig->post_title ) );
	clean_post_cache( $sid );
	$check( 'an admin edit of a live service stays live', 'publish' === get_post_status( $sid ) );
} finally {
	wp_set_current_user( 1 );
	foreach ( $created as $id ) {
		wp_delete_post( $id, true );
	}
	$wpdb->update(
		$wpdb->posts,
		array(
			'post_status' => $orig->post_status,
			'post_title'  => $orig->post_title,
		),
		array( 'ID' => $sid )
	);
	wpss_save_service_addons( $sid, $orig_addons );
	'' === $orig_mod ? delete_post_meta( $sid, ModerationService::META_MODERATION_STATUS ) : update_post_meta( $sid, ModerationService::META_MODERATION_STATUS, $orig_mod );
	delete_post_meta( $sid, ModerationService::META_REJECTION_REASON );
	clean_post_cache( $sid );
	update_option( 'wpss_vendor', $vendor_opts );
}

echo $fails ? "\n$fails FAILED\n" : "\nOK\n";
exit( $fails ? 1 : 0 );
