<?php
/**
 * Optional Communication / Quality / Value ratings reach the database from
 * both the website form (ReviewService) and REST, and REST no longer writes
 * its own row.
 *
 * Run: wp eval-file tests/test-review-sub-ratings.php
 *
 * The docs promise the three sub-ratings; the review form, the AJAX handler
 * and REST dropped them, and REST inserted a second copy of the review logic
 * that skipped them and the wpss_auto_approve_reviews rule. Every row and
 * timestamp touched here is restored.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\ReviewService;

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders_table  = $wpdb->prefix . 'wpss_orders';
$reviews_table = $wpdb->prefix . 'wpss_reviews';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$order = $wpdb->get_row(
	"SELECT * FROM {$orders_table} o
	WHERE o.status = 'completed' AND o.service_id > 0
	AND NOT EXISTS ( SELECT 1 FROM {$reviews_table} r WHERE r.order_id = o.id )
	ORDER BY o.id DESC LIMIT 1"
);

if ( ! $order ) {
	echo "SKIP  no completed order without a review on this install; nothing to assert\n";
	exit( 0 );
}

$order_id     = (int) $order->id;
$orig_done_at = $order->completed_at;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->update( $orders_table, array( 'completed_at' => current_time( 'mysql', true ) ), array( 'id' => $order_id ) );
wp_set_current_user( (int) $order->customer_id );

$cleanup = static function () use ( $wpdb, $reviews_table, $order_id ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( $reviews_table, array( 'order_id' => $order_id ) );
};

// 1. REST: sub-ratings stored, returned, and the response carries them.
$request = new WP_REST_Request( 'POST', '/wpss/v1/reviews' );
$request->set_body_params(
	array(
		'order_id'             => $order_id,
		'rating'               => 4,
		'review'               => 'Sub-rating fixture.',
		'rating_communication' => 5,
		'rating_quality'       => 3,
		'rating_value'         => 4,
	)
);
$response = rest_do_request( $request );
$data     = $response->get_data();
$check( 'REST create answers 201', 201 === $response->get_status(), 'status ' . $response->get_status() );
$check( 'REST response returns the sub-ratings', 5 === ( $data['rating_communication'] ?? null ) && 3 === ( $data['rating_quality'] ?? null ) && 4 === ( $data['rating_value'] ?? null ), wp_json_encode( $data ) );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$reviews_table} WHERE order_id = %d", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( 'REST stores communication / quality / delivery columns', $row && 5 === (int) $row->communication_rating && 3 === (int) $row->quality_rating && 4 === (int) $row->delivery_rating );
$check( 'overall rating is not averaged with the sub-ratings', $row && 4 === (int) $row->rating );

// 2. REST rejects an out-of-range sub-rating.
$cleanup();
$request->set_body_params( array( 'order_id' => $order_id, 'rating' => 4, 'review' => 'x', 'rating_quality' => 9 ) );
$check( 'REST rejects a sub-rating of 9', 400 === rest_do_request( $request )->get_status() );

// 3. REST honours wpss_auto_approve_reviews (one moderation rule, in the service).
$cleanup();
add_filter( 'wpss_auto_approve_reviews', '__return_false' );
$request->set_body_params( array( 'order_id' => $order_id, 'rating' => 5, 'review' => 'Held for moderation.' ) );
rest_do_request( $request );
remove_filter( 'wpss_auto_approve_reviews', '__return_false' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$reviews_table} WHERE order_id = %d", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( 'filter returning false holds the review as pending', 'pending' === $status, (string) $status );

// 4. The website path: the service clamps junk and leaves blanks null.
$cleanup();
$created = ( new ReviewService() )->create(
	$order_id,
	(int) $order->customer_id,
	array(
		'rating'               => 5,
		'content'              => 'Website path.',
		'rating_communication' => 99,
		'rating_quality'       => '',
		'rating_value'         => 2,
	)
);
$check( 'service creates the review', (bool) $created );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$reviews_table} WHERE order_id = %d", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( 'out-of-range and blank sub-ratings are stored as NULL', $row && null === $row->communication_rating && null === $row->quality_rating && 2 === (int) $row->delivery_rating );

// 5. Template: the form offers the fields and the card shows what was given.
$form = (string) file_get_contents( dirname( __DIR__ ) . '/templates/order/order-view.php' );
foreach ( array( 'rating_communication', 'rating_quality', 'rating_value' ) as $field ) {
	$check( "review form has the {$field} field", false !== strpos( $form, $field ) );
}
$ajax = (string) file_get_contents( dirname( __DIR__ ) . '/src/Frontend/AjaxHandlers.php' );
$check( 'AJAX handler forwards the sub-ratings', false !== strpos( $ajax, "\$_POST['rating_quality']" ) );

$cleanup();
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->update( $orders_table, array( 'completed_at' => $orig_done_at ), array( 'id' => $order_id ) );
wp_set_current_user( 0 );
if ( function_exists( 'wpss_recount_service_rating' ) ) {
	wpss_recount_service_rating( (int) $order->service_id );
}

exit( $fails ? 1 : 0 );
