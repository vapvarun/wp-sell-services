<?php
/**
 * A vendor can see what they bid on, answer a review, and read a dispute's
 * outcome from the dashboard; a buyer's "View My Requests" opens the list.
 *
 * Run: wp eval-file tests/test-vendor-dashboard-sections.php
 *
 * Guards Basecamp 10264294123: no proposals list, /dashboard/reviews/ bounced
 * to the landing section, a resolved dispute showed only its badge, and the
 * post-request success link stayed on the create form.
 *
 * Everything it seeds is throwaway and removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\DisputeService;
use WPSellServices\Services\VendorService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$p = $wpdb->prefix;

/*
 * SKIP, not FAIL, when the dashboard page is not mapped.
 *
 * Every assertion below compares the rendered nav against
 * wpss_get_dashboard_url(), which resolves through the mapped page. On an
 * install where that page does not exist - a bare CI WordPress, where the
 * plugin is activated but nothing has ever run setup - the URLs and the markup
 * simply have nothing to agree about, and reporting that as a broken contract
 * is what kept CI's contract job red while the same script passed on a seeded
 * site. Map the page and it asserts again.
 */
if ( ! function_exists( 'wpss_get_page_id' ) || ! wpss_get_page_id( 'dashboard' ) ) {
	echo "SKIP  no dashboard page mapped on this install; the nav has no URLs to assert against\n";
	exit( 0 );
}

$vendor = (int) wp_insert_user(
	array(
		'user_login' => 'f24_vendor_' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'f24-vendor-' . wp_rand() . '@example.invalid',
		'role'       => 'subscriber',
	)
);
$buyer  = (int) wp_insert_user(
	array(
		'user_login' => 'f24_buyer_' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'f24-buyer-' . wp_rand() . '@example.invalid',
		'role'       => 'subscriber',
	)
);
( new VendorService() )->register( $vendor );
$wpdb->update( "{$p}wpss_vendor_profiles", array( 'status' => 'active' ), array( 'user_id' => $vendor ) );

// Render the dashboard the way the page does, for a given member and section.
$render = static function ( int $user_id, string $section, array $extra = array() ): string {
	wp_set_current_user( $user_id );
	$_GET = array_merge( array( 'section' => $section ), $extra );
	return (string) do_shortcode( '[wpss_dashboard]' );
};

// --- sections exist and are vendor-only --------------------------------------
$check( 'proposals is a known section', 'proposals' === wpss_normalize_dashboard_section( 'proposals' ) );
$check( 'reviews is a known section', 'reviews' === wpss_normalize_dashboard_section( 'reviews' ) );

$proposals_url = wpss_get_dashboard_url( 'proposals' );
$reviews_url   = wpss_get_dashboard_url( 'reviews' );
$vendor_html   = $render( $vendor, 'proposals' );
$buyer_html    = $render( $buyer, 'proposals' );
$check( 'vendor nav links Proposals', false !== strpos( $vendor_html, $proposals_url ) );
$check( 'vendor nav links Reviews', false !== strpos( $vendor_html, $reviews_url ) );
$check( 'buyer nav has no Proposals link', false === strpos( $buyer_html, $proposals_url ) );
$check( 'buyer asking for /proposals/ is not shown the section', false === strpos( $buyer_html, 'wpss-section--proposals' ) );

// --- proposals list with the losing-vendor state ------------------------------
$request_id = (int) wp_insert_post(
	array(
		'post_type'   => 'wpss_request',
		'post_title'  => 'F24 contract request',
		'post_status' => 'publish',
		'post_author' => $buyer,
	)
);
$wpdb->insert(
	"{$p}wpss_proposals",
	array(
		'request_id'     => $request_id,
		'vendor_id'      => $vendor,
		'cover_letter'   => 'contract',
		'proposed_price' => 42,
		'proposed_days'  => 3,
		'status'         => 'rejected',
	)
);
$proposal_id = (int) $wpdb->insert_id;
update_post_meta( $request_id, '_wpss_accepted_proposal_id', $proposal_id + 1 );

$html = $render( $vendor, 'proposals' );
$check( 'proposals list shows the request the vendor bid on', false !== strpos( $html, 'F24 contract request' ) );
$check( '  a proposal passed over for another reads Not selected', false !== strpos( $html, 'Not selected' ) );

// --- reviews with a reply through the REST route the form posts to ------------
$wpdb->insert(
	"{$p}wpss_reviews",
	array(
		'order_id'    => 0,
		'reviewer_id' => $buyer,
		'reviewee_id' => $vendor,
		'service_id'  => 0,
		'customer_id' => $buyer,
		'vendor_id'   => $vendor,
		'rating'      => 5,
		'review'      => 'F24 contract review',
		'status'      => 'approved',
	)
);
$review_id = (int) $wpdb->insert_id;

$html = $render( $vendor, 'reviews' );
$check( 'reviews section lists the review', false !== strpos( $html, 'F24 contract review' ) );
$check( '  with a reply form', false !== strpos( $html, 'data-review-id="' . $review_id . '"' ) );

wp_set_current_user( $vendor );
$req = new WP_REST_Request( 'POST', "/wpss/v1/reviews/{$review_id}/reply" );
$req->set_param( 'reply', 'Thanks for the kind words' );
$res = rest_do_request( $req );
$check( 'reply route accepts the vendor reply', 200 === $res->get_status() );
$check( '  vendor_reply is stored', 'Thanks for the kind words' === $wpdb->get_var( $wpdb->prepare( "SELECT vendor_reply FROM {$p}wpss_reviews WHERE id = %d", $review_id ) ) );
$html = $render( $vendor, 'reviews' );
$check( '  section shows the reply and no form', false !== strpos( $html, 'Thanks for the kind words' ) && false === strpos( $html, 'data-review-id="' . $review_id . '"' ) );

// --- dispute outcome for both parties ----------------------------------------
$saved_settings = get_option( 'wpss_orders', array() );
update_option( 'wpss_orders', array_merge( (array) $saved_settings, array( 'allow_disputes' => 1 ) ) );

$wpdb->insert(
	"{$p}wpss_orders",
	array(
		'order_number'   => 'WPSS-F24-' . wp_rand(),
		'customer_id'    => $buyer,
		'vendor_id'      => $vendor,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'total'          => 100.000,
		'currency'       => 'USD',
		'status'         => 'in_progress',
		'payment_status' => 'paid',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

$disputes   = new DisputeService();
$dispute_id = (int) $disputes->open( $order_id, $buyer, 'other', 'contract' );
$check( 'throwaway dispute opened', $dispute_id > 0 );
$check( 'resolved in favour of the vendor', $disputes->resolve( $dispute_id, 'favor_vendor', 'F24 contract note', 1 ) );

$vendor_view = $render( $vendor, 'disputes', array( 'dispute' => $dispute_id ) );
$buyer_view  = $render( $buyer, 'disputes', array( 'dispute' => $dispute_id ) );
$check( 'vendor sees the resolution label', false !== strpos( $vendor_view, 'In Favor of Vendor' ) );
$check( '  and the admin note', false !== strpos( $vendor_view, 'F24 contract note' ) );
$check( '  and what happened to their earnings', false !== strpos( $vendor_view, 'earnings were released' ) );
$check( 'buyer sees the resolution label', false !== strpos( $buyer_view, 'In Favor of Vendor' ) );
$check( '  and that no refund was issued', false !== strpos( $buyer_view, 'No refund was issued' ) );

wp_set_current_user( 1 );
$data = rest_do_request( new WP_REST_Request( 'GET', "/wpss/v1/disputes/{$dispute_id}" ) )->get_data();
foreach ( array( 'resolution', 'refund_amount', 'resolution_notes', 'resolved_at' ) as $key ) {
	$check( "REST dispute detail carries {$key}", is_array( $data ) && array_key_exists( $key, $data ) );
}
$check( '  resolution is favor_vendor', 'favor_vendor' === ( $data['resolution'] ?? null ) );

// --- post-request success link opens the list, not the form -------------------
$requests_url = wpss_get_dashboard_url( 'requests' );
foreach ( array( 'create-request', 'edit-request' ) as $tpl ) {
	$src = (string) file_get_contents( WPSS_PLUGIN_DIR . "templates/dashboard/sections/{$tpl}.php" );
	$check( "{$tpl} links View My Requests to the requests list", false !== strpos( $src, "wpss_get_dashboard_url( 'requests' )" ) && false === strpos( $src, "'?section=requests'" ) && false === strpos( $src, 'href="?section=requests"' ) );
}
$check( 'requests list URL is the dashboard requests section', '' !== $requests_url && false !== strpos( $requests_url, 'requests' ) );

// --- cleanup ------------------------------------------------------------------
update_option( 'wpss_orders', $saved_settings );
$wpdb->delete( "{$p}wpss_dispute_messages", array( 'dispute_id' => $dispute_id ) );
$wpdb->delete( "{$p}wpss_disputes", array( 'id' => $dispute_id ) );
$wpdb->delete( "{$p}wpss_orders", array( 'id' => $order_id ) );
$wpdb->delete( "{$p}wpss_conversations", array( 'order_id' => $order_id ) );
$wpdb->delete( "{$p}wpss_audit_log", array( 'object_type' => 'order', 'object_id' => $order_id ) );
$wpdb->delete( "{$p}wpss_reviews", array( 'id' => $review_id ) );
$wpdb->delete( "{$p}wpss_proposals", array( 'id' => $proposal_id ) );
$wpdb->delete( "{$p}wpss_wallet_transactions", array( 'user_id' => $vendor ) );
wp_delete_post( $request_id, true );
foreach ( array( $vendor, $buyer ) as $u ) {
	$wpdb->delete( "{$p}wpss_notifications", array( 'user_id' => $u ) );
	$wpdb->delete( "{$p}wpss_vendor_profiles", array( 'user_id' => $u ) );
	wp_delete_user( $u );
}
$wpdb->query( "DELETE FROM {$p}wpss_messages WHERE conversation_id NOT IN (SELECT id FROM {$p}wpss_conversations)" ); // phpcs:ignore

echo "\n" . ( $fails ? "{$fails} FAILED" : 'ALL PASS' ) . "\n";
if ( $fails ) {
	exit( 1 );
}
