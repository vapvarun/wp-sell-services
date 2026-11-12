<?php
/**
 * A buyer request changes status through one path (Basecamp 10504406785).
 *
 * Run: wp eval-file tests/test-request-status-one-path.php
 *
 * PUT /buyer-requests/{id} wrote status straight into meta: a buyer could set
 * "hired" with no order behind it (which then stops every vendor proposing),
 * and the status-changed hook never fired.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$buyer = (int) wp_insert_user( array( 'user_login' => 'qa_rs_' . wp_generate_password( 5, false, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
wp_set_current_user( $buyer );
$request_id = (int) wp_insert_post( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'post_title' => 'QA status request', 'post_author' => $buyer ) );

$put = static function ( string $status ) use ( $request_id ) {
	$request = new WP_REST_Request( 'PUT', '/wpss/v1/buyer-requests/' . $request_id );
	$request->set_param( 'status', $status );

	return rest_do_request( $request );
};

$fired = array();
add_action( 'wpss_buyer_request_status_changed', static function ( $id, $new, $old ) use ( &$fired ) { $fired[] = array( $new, $old ); }, 10, 3 );

$check( 'PUT status=hired is refused', 400 === $put( 'hired' )->get_status() );
$check( 'and the request is not hired', 'hired' !== get_post_meta( $request_id, '_wpss_status', true ) );
$check( 'PUT status=closed is accepted', 200 === $put( 'closed' )->get_status() && 'closed' === get_post_meta( $request_id, '_wpss_status', true ) );
$check( 'and fires the status-changed hook once', 1 === count( $fired ) && 'closed' === $fired[0][0], wp_json_encode( $fired ) );
$check( 'PUT status=open reopens', 200 === $put( 'open' )->get_status() && 'open' === get_post_meta( $request_id, '_wpss_status', true ) );

wp_delete_post( $request_id, true );
wp_set_current_user( 0 );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $buyer );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
