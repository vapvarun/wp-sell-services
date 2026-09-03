<?php
/**
 * The review window means the same thing to the API and the website, and a
 * review reply has one writer.
 *
 * Run: wp eval-file tests/test-review-window-and-reply.php
 *
 * Guards Basecamp 10267994010 items 3 and 7. The REST create path read
 * wpss_orders[review_window_days] straight from the option, so a site that
 * widened the window with wpss_review_window_days had the website accept the
 * review and the API answer 400 rest_review_window_expired quoting the stored
 * number. Replies had two writers - an inline $wpdb->update in the controller
 * gating on reviews.vendor_id, and ReviewService::add_response gating on
 * Review::reviewed_id - which allowed a different user on a row where the two
 * columns disagree. Every option and row touched here is restored.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\ReviewService;

defined( 'ABSPATH' ) || exit;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders_table  = $wpdb->prefix . 'wpss_orders';
$reviews_table = $wpdb->prefix . 'wpss_reviews';
$fixture_text  = 'Review window contract fixture.';

// ---------------------------------------------------------------------------
// Fixtures.
// ---------------------------------------------------------------------------

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$order = $wpdb->get_row(
	"SELECT * FROM {$orders_table} o
	WHERE o.status = 'completed' AND o.service_id > 0
	AND NOT EXISTS ( SELECT 1 FROM {$reviews_table} r WHERE r.order_id = o.id )
	ORDER BY o.id DESC LIMIT 1"
);

if ( ! $order ) {
	/*
	 * SKIP, not FAIL. A missing fixture is not a broken contract, and calling it
	 * one made this script the reason CI's contract job was red on a bare
	 * WordPress while the same 58 scripts passed on a seeded site. A gate that
	 * cries wolf on an empty database gets ignored, which is exactly how a real
	 * failure would have slipped past. Seed an order and it asserts again.
	 */
	echo "SKIP  no completed order without a review on this install; nothing to assert\n";
	exit( 0 );
}

$order_id    = (int) $order->id;
$customer_id = (int) $order->customer_id;
$vendor_id   = (int) $order->vendor_id;

// A second vendor, so the reply row can carry an owner in reviewee_id that is
// not the one in vendor_id. Administrators are excluded: manage_options is a
// documented bypass, so an admin here would prove nothing.
$pick_user = static function ( array $exclude ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	foreach ( (array) $wpdb->get_col( "SELECT ID FROM {$wpdb->users} ORDER BY ID DESC LIMIT 200" ) as $candidate ) {
		$candidate = (int) $candidate;
		if ( ! in_array( $candidate, $exclude, true ) && ! user_can( $candidate, 'manage_options' ) ) {
			return $candidate;
		}
	}
	return 0;
};

$other_vendor_id = $pick_user( array( $vendor_id, $customer_id ) );
$stranger_id     = $pick_user( array( $vendor_id, $customer_id, $other_vendor_id ) );

$orders_option = get_option( 'wpss_orders', array() );
$orders_option = is_array( $orders_option ) ? $orders_option : array();
$stored_window = (int) ( $orders_option['review_window_days'] ?? 30 );
$original_done = $order->completed_at;

$review_ids = array();
$restore    = static function () use ( $orders_option, $orders_table, $original_done, $order_id, $reviews_table, &$review_ids ) {
	global $wpdb;
	update_option( 'wpss_orders', $orders_option );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->update( $orders_table, array( 'completed_at' => $original_done ), array( 'id' => $order_id ) );
	foreach ( array_unique( $review_ids ) as $rid ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $reviews_table, array( 'id' => (int) $rid ) );
	}
	remove_all_filters( 'wpss_review_window_days' );
	wp_set_current_user( 0 );
};

// A stored window of 30 days and a completion 90 days ago: expired on the
// stored number, inside anything the filter widens it to.
$orders_option['review_window_days'] = 30;
update_option( 'wpss_orders', $orders_option );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->update( $orders_table, array( 'completed_at' => gmdate( 'Y-m-d H:i:s', time() - ( 90 * DAY_IN_SECONDS ) ) ), array( 'id' => $order_id ) );

$post_review = static function ( int $order_id, int $user_id ) {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( 'POST', '/wpss/v1/reviews' );
	$request->set_param( 'order_id', $order_id );
	$request->set_param( 'rating', 5 );
	$request->set_param( 'review', 'Review window contract fixture.' );
	return rest_do_request( $request );
};

$drop_review = static function ( $response ) use ( $reviews_table, &$review_ids ) {
	global $wpdb;
	$id = (int) ( $response->get_data()['id'] ?? 0 );
	if ( $id ) {
		$review_ids[] = $id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $reviews_table, array( 'id' => $id ) );
	}
	return $id;
};

// ---------------------------------------------------------------------------
// 1. The window filter is honoured by the API.
// ---------------------------------------------------------------------------

$response = $post_review( $order_id, $customer_id );
$data     = $response->get_data();
$check(
	'unfiltered: REST refuses an order outside the stored window',
	400 === $response->get_status() && 'rest_review_window_expired' === ( $data['code'] ?? '' ),
	$response->get_status() . ' ' . ( $data['code'] ?? '' )
);
$drop_review( $response );

add_filter( 'wpss_review_window_days', static fn() => 3650 );
$response = $post_review( $order_id, $customer_id );
$check(
	'filter 3650: REST creates the review the website would accept',
	201 === $response->get_status(),
	$response->get_status() . ' ' . ( $response->get_data()['code'] ?? '' )
);
$drop_review( $response );
$check(
	'filter 3650: the website agrees the order can be reviewed',
	true === ( new ReviewService() )->can_review( $order_id, $customer_id )['can_review']
);
remove_all_filters( 'wpss_review_window_days' );

add_filter( 'wpss_review_window_days', static fn() => 0 );
$response = $post_review( $order_id, $customer_id );
$check(
	'filter 0 (unlimited): REST creates the review',
	201 === $response->get_status(),
	$response->get_status() . ' ' . ( $response->get_data()['code'] ?? '' )
);
$drop_review( $response );
remove_all_filters( 'wpss_review_window_days' );

// The refusal has to quote the number that was actually enforced, not the
// stored one - a 7 in the setting and a 5 from the filter must read "5 days".
add_filter( 'wpss_review_window_days', static fn() => 5 );
$response = $post_review( $order_id, $customer_id );
$data     = $response->get_data();
$check(
	'filter 5: refusal quotes the filtered number, not the stored ' . $stored_window,
	400 === $response->get_status()
		&& 'rest_review_window_expired' === ( $data['code'] ?? '' )
		&& false !== strpos( (string) ( $data['message'] ?? '' ), '5 days' ),
	(string) ( $data['message'] ?? $response->get_status() )
);
$drop_review( $response );
remove_all_filters( 'wpss_review_window_days' );

// ---------------------------------------------------------------------------
// 2. A reply has one writer.
// ---------------------------------------------------------------------------

$controller_src = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/API/ReviewsController.php' );
$start          = strpos( $controller_src, 'public function create_reply(' );
$end            = strpos( $controller_src, 'public function mark_helpful(' );
$create_reply   = false !== $start && false !== $end ? substr( $controller_src, $start, $end - $start ) : '';

$check( 'create_reply body located in the controller', '' !== $create_reply );
$check( 'create_reply does not write the review row itself', false === strpos( $create_reply, 'wpdb->update' ) );
$check( 'create_reply routes the write through ReviewService::add_response', false !== strpos( $create_reply, 'add_response(' ) );

// ReviewRepository::add_vendor_reply was a third writer of the same column
// with no ownership check and no callers.
$repo_src = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Database/Repositories/ReviewRepository.php' );
$check( 'no second reply writer left in the repository', false === strpos( $repo_src, 'add_vendor_reply' ) );

// A row whose ownership columns disagree: reviewee_id (the model's
// reviewed_id) says one vendor, vendor_id says another.
$fake_order_id = (int) $wpdb->get_var( "SELECT MAX(id) + 100000 FROM {$orders_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->insert(
	$reviews_table,
	array(
		'order_id'    => $fake_order_id,
		'service_id'  => (int) $order->service_id,
		'reviewer_id' => $customer_id,
		'reviewee_id' => $vendor_id,
		'vendor_id'   => $other_vendor_id,
		'customer_id' => $customer_id,
		'review_type' => 'customer_to_vendor',
		'rating'      => 5,
		'review'      => $fixture_text,
		'status'      => 'approved',
		'created_at'  => current_time( 'mysql' ),
	)
);
$split_review_id = (int) $wpdb->insert_id;
$review_ids[]    = $split_review_id;

$post_reply = static function ( int $review_id, int $user_id, string $text ) {
	wp_set_current_user( $user_id );
	$request = new WP_REST_Request( 'POST', '/wpss/v1/reviews/' . $review_id . '/reply' );
	$request->set_param( 'id', $review_id );
	$request->set_param( 'reply', $text );
	return rest_do_request( $request );
};

$reply_of = static function ( int $review_id ) use ( $reviews_table ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT vendor_reply FROM {$reviews_table} WHERE id = %d", $review_id ) );
};

if ( ! $split_review_id ) {
	$check( 'throwaway review row with disagreeing ownership columns created', false );
} else {
	foreach ( array(
		'the reviewer'    => $customer_id,
		'another vendor'  => $other_vendor_id,
		'an unrelated user' => $stranger_id,
		'a logged-out caller' => 0,
	) as $persona => $uid ) {
		$response = $post_reply( $split_review_id, $uid, 'Reply from ' . $persona . '.' );
		$check(
			$persona . ' is refused',
			in_array( $response->get_status(), array( 401, 403 ), true ) && '' === $reply_of( $split_review_id ),
			$response->get_status() . ' ' . ( $response->get_data()['code'] ?? '' )
		);
		// Each persona is asked on a clean row, so one that gets through does
		// not turn the rest into "already replied".
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $reviews_table, array( 'vendor_reply' => null ), array( 'id' => $split_review_id ) );
	}

	// The gate and the write resolve the same owner: the model's reviewed_id,
	// which is reviewee_id with the vendor_id fallback for old rows.
	$response = $post_reply( $split_review_id, $vendor_id, 'Thanks for the review.' );
	$check(
		'the reviewed_id owner replies, and the reply lands',
		200 === $response->get_status() && 'Thanks for the review.' === $reply_of( $split_review_id ),
		$response->get_status() . ' ' . ( $response->get_data()['code'] ?? '' ) . ' stored=' . $reply_of( $split_review_id )
	);
	$check(
		'the response still carries the reply',
		'Thanks for the review.' === (string) ( $response->get_data()['vendor_reply'] ?? '' )
	);
}

$restore();

if ( $fails ) {
	echo "\nFAIL ({$fails})\n";
	exit( 1 );
}

echo "PASS - the API honours wpss_review_window_days and replies have one writer.\n";
