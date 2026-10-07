<?php
/**
 * A service waiting for review is in the moderation queue however it was made.
 *
 * Run: wp eval-file tests/test-moderation-state-contract.php
 *
 * WP-CLI, REST, imports and an Editor's "Submit for review" create services at
 * post_status=pending without the moderation meta, and every reader took a
 * missing meta as "approved": the queue said "No services awaiting moderation"
 * while 37 sat at Pending (Basecamp 10337188110). One rule now decides -
 * wpss_get_service_moderation_state() and its SQL twin - and this pins that the
 * queue, the counts, the list column, REST and the catalog all follow it.
 *
 * Throwaway users and services are created and removed.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Pages\ServiceModerationPage;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$page   = new ServiceModerationPage();
$counts = static function () use ( $page ): array {
	$m = new ReflectionMethod( $page, 'get_status_counts' );
	$m->setAccessible( true );
	return $m->invoke( $page );
};
$queue  = static function ( string $state ): array {
	$q = new WP_Query(
		array(
			'post_type'             => 'wpss_service',
			'post_status'           => array( 'pending', 'publish', 'draft' ),
			'posts_per_page'        => -1,
			'fields'                => 'ids',
			'wpss_moderation_state' => $state,
		)
	);
	return array_map( 'intval', $q->posts );
};

$admin  = get_users( array( 'role' => 'administrator', 'number' => 1 ) )[0];
$vendor = wp_insert_user( array( 'user_login' => 'dev_f1_mod_vendor', 'user_pass' => wp_generate_password(), 'role' => 'subscriber', 'user_email' => 'dev_f1_mod_vendor@example.test' ) );
$editor = wp_insert_user( array( 'user_login' => 'dev_f1_mod_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor', 'user_email' => 'dev_f1_mod_editor@example.test' ) );
$made   = array();

wpss_flush_pending_services_count();
$before       = $counts();
$badge_before = wpss_count_pending_services();

try {
	// 1a. Like WP-CLI or an import: pending, no meta.
	$made[] = $cli = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'pending', 'post_title' => 'dev_f1 cli pending', 'post_author' => $vendor ) );
	delete_post_meta( $cli, '_wpss_moderation_status' );

	// 1b. An Editor's "Submit for review" (no manage_options, so no meta is written).
	wp_set_current_user( $editor );
	$made[] = $ed = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'pending', 'post_title' => 'dev_f1 editor submit', 'post_author' => $vendor ) );
	delete_post_meta( $ed, '_wpss_moderation_status' );

	// 1c. REST create as the admin, then left at pending with no meta.
	wp_set_current_user( $admin->ID );
	$made[] = $rest = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'draft', 'post_title' => 'dev_f1 rest pending', 'post_author' => $vendor ) );
	$req = new WP_REST_Request( 'POST', '/wp/v2/wpss-services/' . $rest );
	$req->set_param( 'status', 'pending' );
	rest_do_request( $req );
	delete_post_meta( $rest, '_wpss_moderation_status' );
	wp_set_current_user( 0 );

	// 4. A live service with no meta, like the legacy ones on existing sites.
	// Set directly: the publish floors would (rightly) keep an empty service
	// from going live through wp_insert_post().
	$made[] = $live = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'draft', 'post_title' => 'dev_f1 live no meta', 'post_author' => $vendor ) );
	$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_status' => 'publish' ), array( 'ID' => $live ) );
	clean_post_cache( $live );
	delete_post_meta( $live, '_wpss_moderation_status' );

	// A draft that was never submitted, and one taken back from review.
	$made[] = $draft = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'draft', 'post_title' => 'dev_f1 draft', 'post_author' => $vendor ) );
	delete_post_meta( $draft, '_wpss_moderation_status' );
	$made[] = $back = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'draft', 'post_title' => 'dev_f1 taken back', 'post_author' => $vendor ) );
	update_post_meta( $back, '_wpss_moderation_status', 'pending' );

	wpss_flush_pending_services_count();
	$after   = $counts();
	$pending = $queue( 'pending' );

	$check( 'the three pending services all sit at pending', 'pending' === get_post_status( $cli ) && 'pending' === get_post_status( $ed ) && 'pending' === get_post_status( $rest ) );
	$check( '1. all three are in the Pending queue', ! array_diff( array( $cli, $ed, $rest ), $pending ) );
	$check( sprintf( '1. the page Pending count went up by 3 (%d -> %d)', $before['pending'], $after['pending'] ), 3 === $after['pending'] - $before['pending'] );
	wp_set_current_user( $admin->ID );
	$rest_count = (int) ( rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/moderation/count' ) )->get_data()['pending'] ?? -1 );
	$rest_req   = new WP_REST_Request( 'GET', '/wpss/v1/moderation/pending' );
	$rest_req->set_param( 'per_page', 100 );
	$rest_queue = wp_list_pluck( (array) rest_do_request( $rest_req )->get_data(), 'id' );
	wp_set_current_user( 0 );
	$check( sprintf( '1. the menu badge and REST count went up by 3 (badge %d, REST %d, was %d)', wpss_count_pending_services(), $rest_count, $badge_before ), 3 === wpss_count_pending_services() - $badge_before && $badge_before + 3 === $rest_count );
	$check( '1. REST /moderation/pending lists them', ! array_diff( array( $cli, $ed, $rest ), array_map( 'intval', $rest_queue ) ) );

	ob_start();
	$page->render_moderation_column( 'wpss_moderation', $cli );
	$column = wp_strip_all_tags( (string) ob_get_clean() );
	$check( "2. the list column reads Pending (got '" . trim( $column ) . "')", false !== stripos( $column, 'Pending' ) );

	$check( '4. a live service with no meta is approved and in the catalog query', 'approved' === wpss_get_service_moderation_state( $live ) && in_array( $live, $queue( 'approved' ), true ) );
	$check( '4. and approved grew by exactly that one service', 1 === $after['approved'] - $before['approved'] );
	$check( 'an unsubmitted draft and a draft taken back from review are not in moderation', '' === wpss_get_service_moderation_state( $draft ) && '' === wpss_get_service_moderation_state( $back ) && ! in_array( $back, $pending, true ) );

	update_post_meta( $draft, '_wpss_moderation_status', 'rejected' );
	$check( 'a rejected draft is rejected', 'rejected' === wpss_get_service_moderation_state( $draft ) && in_array( $draft, $queue( 'rejected' ), true ) );

	$check( 'the header search finds a live service with no meta', in_array( $live, $queue( 'approved' ), true ) );

	// 3. Approve and reject: one path, and approval refuses what cannot go live.
	$moderation = new \WPSellServices\Services\ModerationService();
	wp_set_current_user( $admin->ID );

	$result = $moderation->approve( $cli );
	$check( '3. approving an incomplete service is refused with the reason', is_wp_error( $result ) && 'wpss_service_incomplete' === $result->get_error_code() );
	$check( '3. and changes nothing: still pending, still in the queue', 'pending' === get_post_status( $cli ) && 'pending' === wpss_get_service_moderation_state( $cli ) );

	$term   = wp_insert_term( 'dev_f1 moderation cat', 'wpss_service_category' );
	$made_t = is_wp_error( $term ) ? 0 : (int) $term['term_id'];
	$made[] = $image = wp_insert_attachment( array( 'post_title' => 'dev_f1 image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit' ) );
	wp_update_post( array( 'ID' => $ed, 'post_title' => 'dev_f1 complete service title', 'post_content' => str_repeat( 'A complete service description. ', 6 ) ) );
	wp_set_object_terms( $ed, array( $made_t ), 'wpss_service_category' );
	update_post_meta( $ed, '_wpss_packages', array( array( 'name' => 'Basic', 'price' => 50, 'delivery_days' => 3, 'revisions' => 1 ) ) );
	update_post_meta( $ed, '_thumbnail_id', $image ); // set_post_thumbnail() wants a real file.

	$result = $moderation->approve( $ed, 'dev_f1 ok' );
	$check( '3. approving a complete service publishes it and it is approved', true === $result && 'publish' === get_post_status( $ed ) && 'approved' === wpss_get_service_moderation_state( $ed ) && in_array( $ed, $queue( 'approved' ), true ) );
	$history = get_post_meta( $ed, '_wpss_moderation_history', true );
	$check( '3. the approval is in the history REST serves', is_array( $history ) && 'approved' === ( end( $history )['action'] ?? '' ) );

	$result = $moderation->reject( $ed, 'dev_f1 needs a better image' );
	$check( '3. rejecting takes it to draft with the reason stored', true === $result && 'draft' === get_post_status( $ed ) && 'rejected' === wpss_get_service_moderation_state( $ed ) && 'dev_f1 needs a better image' === get_post_meta( $ed, '_wpss_rejection_reason', true ) );

	$req = new WP_REST_Request( 'POST', '/wpss/v1/moderation/' . $ed . '/approve' );
	$res = rest_do_request( $req );
	$check( sprintf( '3. REST approve goes through the same path (HTTP %d) and leaves it approved, not pending', $res->get_status() ), 200 === $res->get_status() && 'approved' === wpss_get_service_moderation_state( $ed ) && '' === get_post_meta( $ed, '_wpss_rejection_reason', true ) );

	$res = rest_do_request( new WP_REST_Request( 'POST', '/wpss/v1/moderation/' . $cli . '/approve' ) );
	$check( sprintf( '3. REST approve of an incomplete service answers 400 (got %d)', $res->get_status() ), 400 === $res->get_status() );
	wp_set_current_user( 0 );
} finally {
	wp_set_current_user( 0 );
	foreach ( $made as $id ) {
		wp_delete_post( $id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $vendor );
	wp_delete_user( $editor );
	if ( ! empty( $made_t ) ) {
		wp_delete_term( $made_t, 'wpss_service_category' );
	}
	wpss_flush_pending_services_count();
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
