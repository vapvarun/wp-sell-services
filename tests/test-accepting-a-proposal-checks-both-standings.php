<?php
/**
 * Accepting a proposal is a new purchase and new work: it is refused when the
 * buyer is suspended or banned, or the seller who bid has since been suspended,
 * through the one door every caller uses.
 *
 * Run: wp eval-file tests/test-accepting-a-proposal-checks-both-standings.php
 *
 * Creates one QA request and proposal (and removes any order made); sets
 * `wpss_account_status` on two QA users. Everything is put back on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\ProposalService;

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

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
$buyer  = 7844;
$seller = 7831;
$prior  = array(
	$buyer  => get_user_meta( $buyer, 'wpss_account_status', true ),
	$seller => get_user_meta( $seller, 'wpss_account_status', true ),
);
$mark   = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$orders}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$request_id = 0;

$restore = static function () use ( $prior, $wpdb, $orders, $mark, &$request_id, $buyer ) {
	foreach ( $prior as $user_id => $value ) {
		if ( '' === $value ) {
			delete_user_meta( $user_id, 'wpss_account_status' );
		} else {
			update_user_meta( $user_id, 'wpss_account_status', $value );
		}
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$orders} WHERE id > %d AND customer_id = %d", $mark, $buyer ) );
	if ( $request_id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'request_id' => $request_id ) );
		wp_delete_post( $request_id, true );
	}
	// phpcs:enable
};

wp_set_current_user( $buyer );
$request_id = (int) ( new BuyerRequestService() )->create( array( 'title' => 'QA standing accept', 'description' => 'QA standing accept body', 'budget_min' => 50, 'budget_max' => 80 ) );
$proposal   = ( new ProposalService() )->submit( $request_id, $seller, array( 'description' => 'QA bid', 'price' => 60, 'delivery_days' => 3 ) );
$check( 'a QA request and proposal exist', $request_id > 0 && is_int( $proposal ) && $proposal > 0, is_wp_error( $proposal ) ? $proposal->get_error_message() : var_export( $proposal, true ) );

if ( ! is_int( $proposal ) || $proposal <= 0 ) {
	return;
}

$service = new BuyerRequestService();

update_user_meta( $buyer, 'wpss_account_status', 'banned' );
$res = $service->convert_to_order( $request_id, $proposal );
$check( 'a banned buyer cannot accept', empty( $res['success'] ), wp_json_encode( $res ) );
delete_user_meta( $buyer, 'wpss_account_status' );

update_user_meta( $seller, 'wpss_account_status', 'suspended' );
$res = $service->convert_to_order( $request_id, $proposal );
$check( 'a proposal from a since-suspended seller cannot be accepted', empty( $res['success'] ), wp_json_encode( $res ) );
delete_user_meta( $seller, 'wpss_account_status' );

$orders_made = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders} WHERE id > %d AND customer_id = %d", $mark, $buyer ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$check( 'no order was made by either refusal', 0 === $orders_made, (string) $orders_made );
