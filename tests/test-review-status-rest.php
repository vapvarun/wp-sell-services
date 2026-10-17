<?php
/**
 * Changing a review's status over REST updates the star averages, writes the
 * audit row and fires the moderation hook, exactly like the admin queue
 * (Basecamp 10399042982). The recompute used to live in a listener that only
 * loads on admin screens, so a REST or mobile moderator left stale stars.
 *
 * Run: wp eval-file tests/test-review-status-rest.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
$ids   = get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) );
if ( ! $admin || ! $ids ) {
	echo "SKIP  needs an administrator and a service\n";
	return;
}

$service = (int) $ids[0];
$vendor  = (int) get_post_field( 'post_author', $service );
$table   = $wpdb->prefix . 'wpss_reviews';
$old_avg = get_post_meta( $service, '_wpss_rating_average', true );
$old_cnt = get_post_meta( $service, '_wpss_rating_count', true );

$mk = static function ( int $rating ) use ( $wpdb, $table, $service, $vendor ) {
	$wpdb->insert(
		$table,
		array( 'order_id' => 990000 + $rating, 'reviewer_id' => 1, 'reviewee_id' => $vendor, 'service_id' => $service, 'customer_id' => 1, 'vendor_id' => $vendor, 'rating' => $rating, 'review' => 'QA-RFT test', 'status' => 'approved', 'created_at' => gmdate( 'Y-m-d H:i:s' ) )
	);
	return (int) $wpdb->insert_id;
};

// The service may already have approved reviews; expect averages relative to them.
$base  = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS n, COALESCE(SUM(rating),0) AS sum FROM {$table} WHERE service_id = %d AND status = 'approved'", $service ) );
$avg   = static fn( int $extra_sum, int $extra_n ) => round( ( (int) $base->sum + $extra_sum ) / ( (int) $base->n + $extra_n ), 2 );
$cnt   = static fn() => (int) get_post_meta( $service, '_wpss_rating_count', true );
$now   = static fn() => (float) get_post_meta( $service, '_wpss_rating_average', true );

$five = $mk( 5 );
$one  = $mk( 1 );
wpss_recount_service_rating( $service );
$check( 'start: average counts both new reviews', $avg( 6, 2 ) === $now() && (int) $base->n + 2 === $cnt() );

$hooked = array();
$listen = static function ( $id, $status ) use ( &$hooked ) {
	$hooked[] = array( $id, $status );
};
add_action( 'wpss_review_moderated', $listen, 10, 2 );

wp_set_current_user( (int) $admin[0] );
$put = static function ( int $id, array $body ) {
	$req = new WP_REST_Request( 'PUT', '/wpss/v1/reviews/' . $id );
	$req->set_body_params( $body );
	return rest_do_request( $req );
};

$res = $put( $one, array( 'status' => 'rejected' ) );
$check( 'REST reject returns 200 (got ' . $res->get_status() . ')', 200 === $res->get_status() );
$check( 'rejected row is stored rejected', 'rejected' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $one ) ) );
$check( 'stars recount at once without the rejected 1-star', $avg( 5, 1 ) === $now() && (int) $base->n + 1 === $cnt() );
$check( 'the moderation hook fired once for it', array( array( $one, 'rejected' ) ) === $hooked );
$audit = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_audit_log WHERE object_type = 'review' AND object_id = %d", $one ) );
$check( 'an audit row was written', (int) $audit >= 1 );

$res = $put( $one, array( 'status' => 'approved' ) );
$check( 'REST approve brings it back', $avg( 6, 2 ) === $now() && (int) $base->n + 2 === $cnt() );

$before = count( $hooked );
$put( $one, array( 'status' => 'approved' ) );
$check( 'same status again is a no-op (no hook, no audit noise)', count( $hooked ) === $before );

$res = $put( $one, array( 'status' => 'pending' ) );
$check( 'pending (unapprove) is accepted and leaves the averages', 200 === $res->get_status() && $avg( 5, 1 ) === $now() );

remove_action( 'wpss_review_moderated', $listen, 10 );
$wpdb->query( "DELETE FROM {$table} WHERE id IN ({$five}, {$one})" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_audit_log WHERE object_type = 'review' AND object_id IN ({$five}, {$one})" );
update_post_meta( $service, '_wpss_rating_average', $old_avg );
update_post_meta( $service, '_wpss_rating_count', $old_cnt );
wpss_recount_service_rating( $service );
wp_set_current_user( 0 );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
