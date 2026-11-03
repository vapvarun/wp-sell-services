<?php
/**
 * A review belongs to the main order: a tip, extension or milestone phase takes none.
 *
 * Before 1.9.2 POST /reviews accepted a completed extension row, so every paid
 * extension, tip or phase could add another rating to the vendor.
 *
 * Run: wp eval-file tests/test-review-main-order-only.php
 *
 * Seeds one buyer, one vendor and four QA-RFT orders; removes them after.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\ReviewService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$mk      = static fn( string $n ) => wp_insert_user( array( 'user_login' => "qa_rft_rv_{$n}" . wp_rand( 1000, 9999 ), 'user_pass' => wp_generate_password(), 'user_email' => "qa-rft-rv-{$n}" . wp_rand( 1000, 9999 ) . '@example.test', 'role' => 'subscriber' ) );
$buyer   = $mk( 'b' );
$vendor  = $mk( 'v' );
$orders  = $wpdb->prefix . 'wpss_orders';
$reviews = $wpdb->prefix . 'wpss_reviews';
$service = (int) get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0];
$row     = static function ( string $platform ) use ( $wpdb, $orders, $buyer, $vendor, $service ) {
	$wpdb->insert( $orders, array( 'order_number' => 'QA-RFT-RV-' . wp_generate_password( 6, false ), 'customer_id' => $buyer, 'vendor_id' => $vendor, 'service_id' => $service, 'platform' => $platform, 'total' => 10, 'currency' => 'USD', 'status' => 'completed', 'payment_status' => 'paid', 'created_at' => current_time( 'mysql', true ), 'completed_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB
	return (int) $wpdb->insert_id;
};
$main = $row( 'standalone' );
$subs = array(
	'extension' => $row( 'extension' ),
	'tip'       => $row( 'tip' ),
	'milestone' => $row( 'milestone' ),
);
$svc  = new ReviewService();
$post = static function ( int $order_id ) use ( $buyer ) {
	wp_set_current_user( $buyer );
	$r = new WP_REST_Request( 'POST', '/wpss/v1/reviews' );
	$r->set_body_params( array( 'order_id' => $order_id, 'rating' => 5, 'review' => 'QA-RFT review' ) );
	return rest_do_request( $r );
};

try {
	foreach ( $subs as $type => $id ) {
		$check( "{$type}: can_review() refuses it", false === $svc->can_review( $id, $buyer )['can_review'] );
		$res = $post( $id );
		$check( "{$type}: POST /reviews answers 400 wpss_review_not_allowed", 400 === $res->get_status() && 'wpss_review_not_allowed' === ( $res->get_data()['code'] ?? '' ) );
		$check( "{$type}: ReviewService::create() writes nothing", null === $svc->create( $id, $buyer, array( 'rating' => 5 ) ) && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$reviews} WHERE order_id = %d", $id ) ) ); // phpcs:ignore WordPress.DB
	}

	// Control: the main order still takes its one review.
	$res = $post( $main );
	$check( 'main order: POST /reviews answers 201', 201 === $res->get_status() );
} finally {
	wp_set_current_user( 0 );
	foreach ( array_merge( array( $main ), array_values( $subs ) ) as $id ) {
		$wpdb->delete( $reviews, array( 'order_id' => $id ) );
		$wpdb->delete( $orders, array( 'id' => $id ) );
	}
	$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $vendor ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_notifications', array( 'user_id' => $buyer ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
}

echo $fails ? "FAILED {$fails}\n" : "OK\n";
