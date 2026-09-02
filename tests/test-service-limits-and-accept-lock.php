<?php
/**
 * Service content limits apply on every save path, and a proposal is accepted once.
 *
 * Before F12 the wizard was the only surface that capped packages, gallery,
 * extras, FAQs and requirements: REST PUT /services and wp-admin stored any
 * number, and the wizard's own gallery cap lived in the browser. Accepting a
 * proposal checked "still pending" on a stale read before the transaction, so
 * two accepts on one request could each create an order.
 *
 * Run: wp eval-file tests/test-service-limits-and-accept-lock.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

use WPSellServices\Frontend\ServiceWizard;
use WPSellServices\Services\BuyerRequestService;
use WPSellServices\Services\ProposalService;

global $wpdb;

$failures = array();
$posts    = array();

wp_set_current_user( 1 );
add_filter( 'pre_wp_mail', '__return_false' );

// Pin the free caps so the script means the same thing with Pro active.
foreach ( array( 'packages' => 3, 'gallery' => 4, 'extras' => 3, 'faq' => 5, 'requirements' => 5 ) as $limit_key => $limit_value ) {
	add_filter( "wpss_service_max_{$limit_key}", static fn() => $limit_value, 999 );
}

$category = wp_insert_term( 'F12 limit test ' . wp_rand(), 'wpss_service_category' );
$rows     = static fn( int $n, string $prefix ) => array_map( static fn( $i ) => array( 'question' => "{$prefix} {$i}", 'answer' => 'a', 'label' => "{$prefix} {$i}", 'title' => "{$prefix} {$i}", 'name' => "{$prefix} {$i}", 'price' => 10 * $i, 'delivery_days' => 2 ), range( 1, $n ) );

// 1. REST: six packages and nine gallery IDs are stored capped and refused with the limit named.
$request = new WP_REST_Request( 'POST', '/wpss/v1/services' );
$request->set_body_params(
	array(
		'title'        => 'F12 limit test',
		'description'  => 'Limit test',
		'categories'   => array( (int) $category['term_id'] ),
		'packages'     => $rows( 6, 'Package' ),
		'gallery'      => range( 1, 9 ),
		'requirements' => $rows( 7, 'Requirement' ),
	)
);
$response = rest_do_request( $request );
$data     = $response->get_data();
$rest_id  = (int) ( $data['data']['service_id'] ?? $data['id'] ?? 0 );
if ( $rest_id ) {
	$posts[] = $rest_id;
}

if ( 400 !== $response->get_status() || 'wpss_service_limit' !== ( $data['code'] ?? '' ) ) {
	$failures[] = 'REST create with 6 packages answered ' . $response->get_status() . ' ' . ( $data['code'] ?? '' ) . ' - expected 400 wpss_service_limit.';
} elseif ( false === strpos( $data['message'], '3 packages' ) ) {
	$failures[] = 'REST limit error does not name the package limit: ' . $data['message'];
}
if ( 3 !== count( (array) get_post_meta( $rest_id, '_wpss_packages', true ) ) ) {
	$failures[] = 'REST stored ' . count( (array) get_post_meta( $rest_id, '_wpss_packages', true ) ) . ' packages - expected 3.';
}
if ( 5 !== count( (array) get_post_meta( $rest_id, '_wpss_gallery', true ) ) ) {
	$failures[] = 'REST stored ' . count( (array) get_post_meta( $rest_id, '_wpss_gallery', true ) ) . ' gallery IDs - expected 5 (main + 4).';
}
if ( 5 !== count( (array) get_post_meta( $rest_id, '_wpss_requirements', true ) ) ) {
	$failures[] = 'REST stored ' . count( (array) get_post_meta( $rest_id, '_wpss_requirements', true ) ) . ' requirements - expected 5.';
}

// 2. Wizard: the server truncates gallery, extras, FAQs and requirements.
$wizard_id = wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_title'  => 'F12 wizard limit test',
		'post_status' => 'draft',
		'post_author' => 1,
	)
);
$posts[]   = $wizard_id;
$image_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} ORDER BY ID DESC LIMIT 9" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wizard    = new ServiceWizard();
$save      = new ReflectionMethod( $wizard, 'save_service_meta' );
$save->setAccessible( true );
$save->invoke(
	$wizard,
	$wizard_id,
	array(
		'packages'     => array( 'basic' => array( 'enabled' => true, 'name' => 'Basic', 'price' => 10, 'delivery_time' => 2, 'revisions' => 1, 'features' => array() ) ),
		'gallery'      => array( 'main' => null, 'images' => array_map( static fn( $id ) => array( 'id' => (int) $id, 'url' => '' ), $image_ids ), 'video' => '' ),
		'requirements' => $rows( 7, 'Requirement' ),
		'extras'       => $rows( 5, 'Extra' ),
		'faqs'         => $rows( 7, 'FAQ' ),
	)
);
$wizard_gallery = get_post_meta( $wizard_id, '_wpss_gallery', true );
foreach ( array( '_wpss_faqs' => 5, '_wpss_requirements' => 5, '_wpss_extras' => 3 ) as $meta_key => $expected ) {
	$stored = count( (array) get_post_meta( $wizard_id, $meta_key, true ) );
	if ( $expected !== $stored ) {
		$failures[] = "Wizard stored {$stored} {$meta_key} - expected {$expected}.";
	}
}
if ( 5 !== count( $wizard_gallery['images'] ?? array() ) ) {
	$failures[] = 'Wizard stored ' . count( $wizard_gallery['images'] ?? array() ) . ' gallery IDs - expected 5 (main + 4).';
}

// 3. Accept: the second accept of one request is refused and one order exists.
require_once ABSPATH . 'wp-admin/includes/user.php';
$vendor_id = wp_insert_user(
	array(
		'user_login' => 'f12vendor' . wp_rand(),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$requests  = new BuyerRequestService();
$proposals = new ProposalService();
$req_id    = $requests->create(
	array(
		'title'         => 'F12 accept lock',
		'description'   => 'Accept lock test',
		'delivery_days' => 3,
	)
);
$posts[]   = $req_id;
$prop_id   = $proposals->submit(
	$req_id,
	$vendor_id,
	array(
		'description'   => 'Proposal',
		'price'         => 50,
		'delivery_days' => 3,
	)
);

$first  = $requests->convert_to_order( (int) $req_id, (int) $prop_id );
$second = $requests->convert_to_order( (int) $req_id, (int) $prop_id );
$orders = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wpss_orders WHERE platform = 'request' AND platform_order_id = %d", $req_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

if ( empty( $first['success'] ) ) {
	$failures[] = 'First accept failed: ' . ( $first['message'] ?? '' );
}
if ( ! empty( $second['success'] ) ) {
	$failures[] = 'Second accept of the same request succeeded.';
}
if ( 1 !== count( $orders ) ) {
	$failures[] = 'Two accepts left ' . count( $orders ) . ' orders - expected 1.';
}
if ( ProposalService::STATUS_ACCEPTED !== ( $proposals->get( (int) $prop_id )->status ?? '' ) ) {
	$failures[] = 'Proposal is not accepted after convert_to_order.';
}

// Cleanup.
foreach ( $orders as $order_id ) {
	foreach ( array( 'wpss_orders' => 'id', 'wpss_order_requirements' => 'order_id', 'wpss_conversations' => 'order_id' ) as $table => $column ) {
		$wpdb->delete( $wpdb->prefix . $table, array( $column => (int) $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
$wpdb->delete( $wpdb->prefix . 'wpss_proposals', array( 'request_id' => (int) $req_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( array_filter( $posts ) as $post_id ) {
	wp_delete_post( (int) $post_id, true );
}
wp_delete_user( (int) $vendor_id );
wp_delete_term( (int) $category['term_id'], 'wpss_service_category' );

if ( $failures ) {
	echo "FAIL\n - " . implode( "\n - ", $failures ) . "\n";
	exit( 1 );
}

echo "PASS: limits enforced on REST and wizard saves; proposal accepted once, one order.\n";
