<?php
/**
 * Buyer-request deadlines are UTC through every door, and the dates typed before 1.8.0 are re-read
 * as the site's calendar day (Basecamp 10415811038).
 *
 * Run: wp eval-file tests/test-request-deadline-utc.php
 *
 * @package WPSellServices
 */

use WPSellServices\Database\UtcMigration;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$tz = static fn() => 'America/New_York';
add_filter( 'pre_option_timezone_string', $tz );
add_filter( 'pre_option_gmt_offset', '__return_zero' );

$mk = static function ( string $expires ): int {
	$id = wp_insert_post( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'post_title' => 'QA deadline probe' ) );
	update_post_meta( $id, '_wpss_expires_at', $expires );
	return $id;
};

// 1. Repair: typed midnight -> site midnight in UTC; a real instant is left alone; second run is a no-op.
$legacy  = $mk( '2026-11-30 00:00:00' );
$instant = $mk( '2026-11-30 13:45:10' );
delete_option( 'wpss_request_deadlines_utc' );
UtcMigration::repair_request_deadlines();
$check( 'typed date becomes site midnight in UTC', '2026-11-30 05:00:00' === get_post_meta( $legacy, '_wpss_expires_at', true ) );
$check( 'a stored instant is untouched', '2026-11-30 13:45:10' === get_post_meta( $instant, '_wpss_expires_at', true ) );
$check( 'edit form shows the typed day again', '2026-11-30' === substr( wpss_datetime_local_input( get_post_meta( $legacy, '_wpss_expires_at', true ) ), 0, 10 ) );
$check( 'second run changes nothing', 0 === UtcMigration::repair_request_deadlines() );
wp_delete_post( $legacy, true );
wp_delete_post( $instant, true );

// 2. REST create stores UTC for a date-only and an offset value, and refuses garbage.
$buyer = get_users( array( 'search' => 'wpss_buyer_ella', 'search_columns' => array( 'user_login' ), 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( $buyer ? (int) $buyer[0] : 1 );
$post = static function ( string $deadline ) {
	$req = new WP_REST_Request( 'POST', '/wpss/v1/buyer-requests' );
	$req->set_body_params( array( 'title' => 'QA deadline probe request', 'description' => str_repeat( 'Deadline probe. ', 12 ), 'budget_min' => 10, 'budget_max' => 50, 'deadline' => $deadline ) );
	return rest_do_request( $req );
};
$r = $post( '2026-11-30' );
$id = (int) ( $r->get_data()['data']['id'] ?? 0 );
$check( 'REST date-only deadline stored as site midnight in UTC', $id > 0 && '2026-11-30 05:00:00' === get_post_meta( $id, '_wpss_expires_at', true ) );
$check( 'REST echoes an ISO 8601 deadline', '2026-11-30T05:00:00+00:00' === ( $r->get_data()['data']['deadline'] ?? '' ) );
$id and wp_delete_post( $id, true );
$r2  = $post( '2026-11-30T09:00:00+05:30' );
$id2 = (int) ( $r2->get_data()['data']['id'] ?? 0 );
$check( 'REST offset deadline converted to UTC', $id2 > 0 && '2026-11-30 03:30:00' === get_post_meta( $id2, '_wpss_expires_at', true ) );
$id2 and wp_delete_post( $id2, true );
$check( 'REST refuses an unreadable deadline', 400 === $post( 'next tuesday-ish??' )->get_status() );

remove_filter( 'pre_option_timezone_string', $tz );
remove_filter( 'pre_option_gmt_offset', '__return_zero' );
exit( $fails ? 1 : 0 );
