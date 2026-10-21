<?php
/**
 * A service with no reviews shows no Reviews section; one with reviews still shows it.
 *
 * Run: wp eval-file tests/test-service-reviews-empty-section.php
 *
 * Seeds one QA-RFT service and one review row; removes both after.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$sid = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'QA-RFT empty reviews', 'post_author' => 1 ) );
$render = static function () use ( $sid ) {
	$GLOBALS['post'] = get_post( $sid ); // The partial reads get_the_ID().
	setup_postdata( $GLOBALS['post'] );
	ob_start();
	wpss_get_template_part( 'partials/service', 'reviews', array( 'service' => wpss_get_service( $sid ) ) );
	return (string) ob_get_clean();
};

$html = $render();
$check( 'no reviews: no Reviews section or heading', false === strpos( $html, 'id="reviews"' ) && false === strpos( $html, 'No reviews yet' ) );

$table = $wpdb->prefix . 'wpss_reviews';
$cols  = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
$row   = array_intersect_key(
	array( 'order_id' => 0, 'service_id' => $sid, 'reviewer_id' => 1, 'customer_id' => 1, 'vendor_id' => 1, 'reviewee_id' => 1, 'rating' => 5, 'review' => 'QA-RFT great', 'status' => 'approved', 'is_public' => 1, 'review_type' => 'customer_to_vendor', 'reviewer_name' => 'QA-RFT' ),
	array_flip( $cols )
);
$wpdb->insert( $table, $row );
$rid = (int) $wpdb->insert_id;
wpss_recount_service_rating( $sid );
$html = $render();
$check( 'with a review: the section renders', false !== strpos( $html, 'id="reviews"' ) );

$wpdb->delete( $table, array( 'id' => $rid ) );
wp_delete_post( $sid, true );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
