<?php
/**
 * A standing refusal answers 403 with the reason, not a 500 or a 400:
 * - POST /wpss/v1/reviews by a banned buyer on their completed order;
 * - ReviewService::can_review() gives the reason, so the AJAX form says it instead of "try again";
 * - POST /wpss/v1/buyer-requests/{id}/proposals/{pid}/accept by a banned buyer.
 *
 * Run: wp eval-file tests/test-standing-refusals-are-403.php
 *
 * Throwaway users (swept by the harness); the order, request and proposal it makes are deleted on exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\ProposalService;
use WPSellServices\Services\ReviewService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$orders   = $wpdb->prefix . 'wpss_orders';
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$mk       = static fn( string $n ) => (int) wp_insert_user( array( 'user_login' => "qa_403_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-403-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$vendor   = $mk( 'v' );
$buyer    = $mk( 'b' );
$request  = 0;
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$wpdb->insert( $profiles, array( 'user_id' => $vendor, 'display_name' => 'QA 403', 'status' => 'active' ) );
$wpdb->insert( $orders, array( 'order_number' => 'QA-403-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => 0, 'platform' => 'standalone', 'status' => 'completed', 'payment_status' => 'paid', 'subtotal' => 50, 'total' => 50, 'currency' => wpss_get_currency(), 'created_at' => current_time( 'mysql', true ), 'completed_at' => current_time( 'mysql', true ) ) );
$done = (int) $wpdb->insert_id;
register_shutdown_function(
	static function () use ( $wpdb, $orders, $profiles, $vendor, $buyer, &$request ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_reviews', array( 'customer_id' => $buyer ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$orders} WHERE customer_id = %d", $buyer ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $request ) {
			$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'request_id' => $request ) );
			wp_delete_post( $request, true );
		}
		$wpdb->delete( $profiles, array( 'user_id' => $vendor ) );
	}
);
// phpcs:enable

update_user_meta( $buyer, 'wpss_account_status', 'banned' );

// Reviews.
$why = ( new ReviewService() )->can_review( $done, $buyer );
$check( 'can_review(): refused with the standing reason (the AJAX form shows it)', empty( $why['can_review'] ) && false !== stripos( (string) $why['reason'], 'account' ), (string) ( $why['reason'] ?? '' ) );

wp_set_current_user( $buyer );
$req = new WP_REST_Request( 'POST', '/wpss/v1/reviews' );
$req->set_body_params( array( 'order_id' => $done, 'rating' => 5, 'review' => 'QA standing review' ) );
$res = rest_do_request( $req );
$check( 'POST /reviews by a banned buyer answers 403', 403 === $res->get_status(), $res->get_status() . ' ' . wp_json_encode( $res->get_data() ) );
$check( 'POST /reviews says why', false !== stripos( (string) ( $res->get_data()['message'] ?? '' ), 'account' ), (string) ( $res->get_data()['message'] ?? '' ) );

// Accepting a proposal.
delete_user_meta( $buyer, 'wpss_account_status' );
$request  = (int) ( new BuyerRequestService() )->create( array( 'title' => 'QA 403 accept', 'description' => 'QA 403 accept body', 'budget_min' => 50, 'budget_max' => 80 ) );
$proposal = ( new ProposalService() )->submit( $request, $vendor, array( 'description' => 'QA bid', 'price' => 60, 'delivery_days' => 3 ) );
if ( ! $request || ! is_int( $proposal ) || $proposal <= 0 ) {
	echo 'SKIP  accept: could not stage a request and proposal (' . ( is_wp_error( $proposal ) ? esc_html( $proposal->get_error_message() ) : 'none' ) . ")\n";
} else {
	update_user_meta( $buyer, 'wpss_account_status', 'banned' );
	$res = rest_do_request( new WP_REST_Request( 'POST', "/wpss/v1/buyer-requests/{$request}/proposals/{$proposal}/accept" ) );
	$check( 'accept by a banned buyer answers 403', 403 === $res->get_status(), $res->get_status() . ' ' . wp_json_encode( $res->get_data() ) );
	delete_user_meta( $buyer, 'wpss_account_status' );

	update_user_meta( $vendor, 'wpss_account_status', 'suspended' );
	$res = rest_do_request( new WP_REST_Request( 'POST', "/wpss/v1/buyer-requests/{$request}/proposals/{$proposal}/accept" ) );
	$check( 'accept of a suspended seller\'s proposal answers 403', 403 === $res->get_status(), $res->get_status() . ' ' . wp_json_encode( $res->get_data() ) );
	delete_user_meta( $vendor, 'wpss_account_status' );
}
wp_set_current_user( 0 );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
