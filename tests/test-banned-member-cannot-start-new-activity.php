<?php
/**
 * A suspended or banned member posts no buyer request, sends no tip and leaves
 * no review; in good standing the same calls go through.
 *
 * Run: wp eval-file tests/test-banned-member-cannot-start-new-activity.php
 *
 * Sets `wpss_account_status` on one QA buyer and removes the request and review
 * it creates; both are put back on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\ReviewService;
use WPSellServices\Services\TippingService;

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
$reviews = $wpdb->prefix . 'wpss_reviews';
$orders  = $wpdb->prefix . 'wpss_orders';
$tips    = $wpdb->prefix . 'wpss_orders';

// A completed, reviewable order and its buyer.
$reviewer = new ReviewService();
$order_id = 0;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $wpdb->get_results( "SELECT id FROM {$orders} WHERE status = 'completed' AND (platform IS NULL OR platform NOT IN ('tip','milestone','extension')) ORDER BY id DESC LIMIT 40" ) as $row ) {
	$candidate = wpss_get_order( (int) $row->id );
	if ( $candidate && ! $reviewer->has_review( (int) $row->id ) && $reviewer->is_within_review_window( $candidate ) ) {
		$order_id = (int) $row->id;
		break;
	}
}

// None inside the review window (a freshly seeded site): stage one completed today.
if ( ! $order_id ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( $wpdb->get_col( "SELECT id FROM {$orders} WHERE status = 'completed' AND (platform IS NULL OR platform NOT IN ('tip','milestone','extension')) ORDER BY id DESC LIMIT 40" ) as $candidate_id ) {
		if ( ! $reviewer->has_review( (int) $candidate_id ) && wpss_test_patch_row( 'wpss_orders', (int) $candidate_id, array( 'completed_at' => current_time( 'mysql', true ) ) ) ) {
			$order_id = (int) $candidate_id;
			break;
		}
	}
}

if ( ! $order_id ) {
	echo "SKIP  no unreviewed completed order on this site\n";
	return;
}

$order    = wpss_get_order( $order_id );
$buyer    = (int) $order->customer_id;
$prior    = get_user_meta( $buyer, 'wpss_account_status', true );

// A completed order that has not been tipped, so the good-standing tip is a real contrast.
$tip_order_id = 0;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $wpdb->get_col( "SELECT id FROM {$orders} WHERE status = 'completed' AND customer_id = {$buyer} AND (platform IS NULL OR platform NOT IN ('tip','milestone','extension')) ORDER BY id DESC LIMIT 40" ) as $candidate_id ) {
	if ( ! ( new TippingService() )->has_tipped( (int) $candidate_id, $buyer ) ) {
		$tip_order_id = (int) $candidate_id;
		break;
	}
}
$check( 'an untipped completed order exists for the tip', $tip_order_id > 0 );
$request  = 0;
$tip_rows = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$tips}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$restore = static function () use ( $buyer, $prior, &$request, $wpdb, $reviews, $order_id, $orders, $tip_rows ) {
	if ( '' === $prior ) {
		delete_user_meta( $buyer, 'wpss_account_status' );
	} else {
		update_user_meta( $buyer, 'wpss_account_status', $prior );
	}
	if ( $request ) {
		wp_delete_post( $request, true );
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( $reviews, array( 'order_id' => $order_id ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$orders} WHERE id > %d AND platform = 'tip' AND customer_id = %d", $tip_rows, $buyer ) );
	// phpcs:enable
};

add_filter( 'wpss_tipping_enabled', '__return_true' );
wp_set_current_user( $buyer );

foreach ( array( 'banned', 'suspended' ) as $standing ) {
	update_user_meta( $buyer, 'wpss_account_status', $standing );

	$made = ( new BuyerRequestService() )->create( array( 'title' => 'QA standing request', 'description' => 'QA standing request body' ) );
	$check( "a {$standing} member cannot post a request", is_wp_error( $made ), is_wp_error( $made ) ? '' : (string) $made );
	if ( is_int( $made ) && $made > 0 ) {
		$request = $made;
	}

	$tip = ( new TippingService() )->create_pending_tip_order( $tip_order_id, 5.0, $buyer );
	$check( "a {$standing} member cannot send a tip", empty( $tip['success'] ), wp_json_encode( $tip ) );

	$review = $reviewer->create( $order_id, $buyer, array( 'rating' => 5, 'review' => 'QA standing review' ) );
	$check( "a {$standing} member cannot leave a review", null === $review );
}

delete_user_meta( $buyer, 'wpss_account_status' );
$tip = ( new TippingService() )->create_pending_tip_order( $tip_order_id, 5.0, $buyer );
$check( 'in good standing the tip is created', ! empty( $tip['success'] ), wp_json_encode( $tip ) );
$made = ( new BuyerRequestService() )->create( array( 'title' => 'QA standing request', 'description' => 'QA standing request body' ) );
$check( 'in good standing the request is posted', is_int( $made ) && $made > 0, is_wp_error( $made ) ? $made->get_error_code() : var_export( $made, true ) );
$request = is_int( $made ) ? $made : 0;

remove_filter( 'wpss_tipping_enabled', '__return_true' );
