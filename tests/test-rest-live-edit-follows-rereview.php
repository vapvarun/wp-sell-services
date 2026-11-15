<?php
/**
 * A REST edit of a live service sending status=publish follows the Re-review setting like every other door.
 *
 * Run: wp eval-file tests/test-rest-live-edit-follows-rereview.php
 *
 * With moderation on and Re-review live edits OFF, a live edit stays live; ON, it goes to review.
 * Sending status=publish for an already-live listing used to be read as "going live" and queued it either way.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f   = wpss_test_fixtures();
$sid = (int) ( $f->service )( 'publish' );
$was = get_option( 'wpss_vendor', array() );

$edit = static function ( bool $re_review, array $body ) use ( $f, $sid, $was ): string {
	update_option( 'wpss_vendor', array_merge( (array) $was, array( 'require_service_moderation' => true, 're_review_live_edits' => $re_review ) ) );
	// Straight to the row: wp_update_post() runs the publish guards, which bounce this bare fixture to draft after a pending round.
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $sid ) );
	clean_post_cache( $sid );
	wp_set_current_user( (int) $f->vendor );
	$request = new WP_REST_Request( 'PUT', "/wpss/v1/services/{$sid}" );
	$request->set_body_params( $body );
	$response = rest_do_request( $request );
	clean_post_cache( $sid );
	$GLOBALS['wpss_last_status'] = $response->get_status();
	$GLOBALS['wpss_last_body']   = wp_json_encode( $response->get_data() );
	return (string) get_post_status( $sid );
};

$edit_body = array( 'title' => 'QA live edit ' . wp_generate_password( 4, false ) );

$check( 're-review OFF: a plain live edit stays live', 'publish' === $edit( false, $edit_body ) && 200 === $GLOBALS['wpss_last_status'], (string) $GLOBALS['wpss_last_status'] );
$st = $edit( false, $edit_body + array( 'status' => 'publish' ) );
$check( 're-review OFF: the same edit with status=publish stays live', 'publish' === $st && 200 === $GLOBALS['wpss_last_status'], $st . ' http ' . $GLOBALS['wpss_last_status'] );
$check( 're-review ON: a live edit goes to review', 'pending' === $edit( true, $edit_body ) );
$st = $edit( true, $edit_body + array( 'status' => 'publish' ) );
$check( 're-review ON: the same edit with status=publish goes to review', 'pending' === $st && 200 === $GLOBALS['wpss_last_status'], $st . ' http ' . $GLOBALS['wpss_last_status'] . ' ' . $GLOBALS['wpss_last_body'] );

update_option( 'wpss_vendor', $was );
echo $fails ? "{$fails} FAILED\n" : "All passed\n";
