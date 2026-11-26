<?php
/**
 * A buyer cannot rewrite a submitted review over REST (Basecamp 10546305098).
 *
 * PATCH /wpss/v1/reviews/{id} let the review's customer change rating and text,
 * so an approved 1-star could become a 5-star and move the vendor averages
 * without moderation. Owner decision: reviews are final. The buyer now gets 400
 * rest_review_immutable and the row is untouched; admin moderation is unchanged.
 *
 * Run: wp eval-file tests/test-review-is-final-for-the-buyer.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table = $wpdb->prefix . 'wpss_reviews';
$buyer = wp_insert_user( array( 'user_login' => 'qa_rft_rev_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'qa_rft_rev_buyer@example.test' ) );
$wpdb->insert(
	$table,
	array( 'order_id' => 990777, 'reviewer_id' => $buyer, 'reviewee_id' => 1, 'service_id' => 0, 'customer_id' => $buyer, 'vendor_id' => 1, 'rating' => 1, 'review' => 'QA-RFT original', 'status' => 'approved', 'created_at' => gmdate( 'Y-m-d H:i:s' ) )
);
$review = (int) $wpdb->insert_id;
$row    = static fn() => $wpdb->get_row( $wpdb->prepare( "SELECT rating, review, status FROM {$table} WHERE id = %d", $review ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$patch = static function ( array $body ) use ( $review ) {
	$req = new WP_REST_Request( 'PATCH', '/wpss/v1/reviews/' . $review );
	$req->set_body_params( $body );
	return rest_do_request( $req );
};

try {
	wp_set_current_user( $buyer );
	$res = $patch( array( 'rating' => 5 ) );
	$check( 'buyer PATCH of the rating is refused with 400 (got ' . $res->get_status() . ')', 400 === $res->get_status() );
	$check( 'the refusal says why (rest_review_immutable)', 'rest_review_immutable' === ( $res->get_data()['code'] ?? '' ) );
	$res = $patch( array( 'review' => 'QA-RFT rewritten' ) );
	$check( 'buyer PATCH of the text is refused with 400 (got ' . $res->get_status() . ')', 400 === $res->get_status() );
	$check( 'rating and text are unchanged', '1' === (string) $row()->rating && 'QA-RFT original' === $row()->review );

	wp_set_current_user( 1 );
	$res = $patch( array( 'status' => 'pending' ) );
	$check( 'admin can still moderate (got ' . $res->get_status() . ')', 200 === $res->get_status() && 'pending' === $row()->status );
} finally {
	wp_set_current_user( 1 );
	$wpdb->delete( $table, array( 'id' => $review ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
