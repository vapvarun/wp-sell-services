<?php
/**
 * A review with no words is refused with a reason on every door
 * (Basecamp 10546305005).
 *
 * ReviewService::create() stored blank or whitespace-only text as a review.
 * Owner decision: a review must say something, no character minimum. create()
 * now returns WP_Error wpss_review_empty, which REST answers as 400 and the
 * AJAX form shows as data.message. The check runs after wpss_pre_create_review,
 * so a site filter that fills the text is honoured; imported reviews keep their
 * own door and are not affected.
 *
 * Run: wp eval-file tests/test-empty-review-refused-with-reason.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\ReviewService;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$reviews = $wpdb->prefix . 'wpss_reviews';
$f       = wpss_test_fixtures();
$order   = ( $f->order )(
	array(
		'service_id'   => ( $f->service )( 'draft' ),
		'status'       => 'completed',
		'completed_at' => current_time( 'mysql', true ),
	)
);
$buyer   = (int) wpss_get_order( $order )->customer_id;
$stored  = static fn() => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$reviews} WHERE order_id = %d", $order ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
register_shutdown_function( static fn() => $wpdb->delete( $reviews, array( 'order_id' => $order ) ) );
add_filter( 'pre_wp_mail', '__return_true' );
wp_set_current_user( $buyer );

// REST door.
$req = new WP_REST_Request( 'POST', '/wpss/v1/reviews' );
$req->set_body_params( array( 'order_id' => $order, 'rating' => 4, 'review' => "  \n\t " ) );
$res = rest_do_request( $req );
$check( 'REST: whitespace-only review answers 400 (got ' . $res->get_status() . ')', 400 === $res->get_status() );
$check( 'REST: the error is wpss_review_empty with a message', 'wpss_review_empty' === ( $res->get_data()['code'] ?? '' ) && '' !== (string) ( $res->get_data()['message'] ?? '' ) );
$check( 'REST: nothing is stored', 0 === $stored() );

// Service (the AJAX form's door): blank and tag-only text.
$svc = new ReviewService();
$r   = $svc->create( $order, $buyer, array( 'rating' => 4, 'content' => '' ) );
$check( 'service: empty text returns WP_Error wpss_review_empty', is_wp_error( $r ) && 'wpss_review_empty' === $r->get_error_code() );
$r = $svc->create( $order, $buyer, array( 'rating' => 4, 'content' => '<p> </p>' ) );
$check( 'service: markup with no words is refused too', is_wp_error( $r ) );
$check( 'service: nothing is stored', 0 === $stored() );

// A filter that fills the text runs first.
$fill = static fn( $data ) => array_merge( $data, array( 'content' => 'QA-RFT filled by a site filter' ) );
add_filter( 'wpss_pre_create_review', $fill );
$r = $svc->create( $order, $buyer, array( 'rating' => 4, 'content' => '' ) );
remove_filter( 'wpss_pre_create_review', $fill );
$check( 'text filled by wpss_pre_create_review is accepted', $r instanceof \WPSellServices\Models\Review && 1 === $stored() );

// One word is enough: no character minimum.
$wpdb->delete( $reviews, array( 'order_id' => $order ) );
$r = $svc->create( $order, $buyer, array( 'rating' => 5, 'content' => 'Great' ) );
$check( 'a one-word review is accepted', $r instanceof \WPSellServices\Models\Review );

wp_set_current_user( 1 );
echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
