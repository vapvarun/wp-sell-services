<?php
/**
 * A service's gallery and its limit belong to the service's seller.
 *
 * Gallery: a seller may use their own uploads and images attached to a service
 * they may edit, nothing else. Every seller can upload_files, so that cap alone
 * let a seller put another member's images (a buyer's private contact
 * attachment included) in their public gallery.
 *
 * Limit: an existing service is judged against its author, not whoever presses
 * Publish. The site owner is the one exception: an admin may knowingly publish
 * past a vendor's limit (the settled rule in test-service-limit-on-core-insert).
 *
 * Run: wp eval-file tests/test-service-gallery-and-limit-owner.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$vendor_id = wpss_test_fixture_user( 'vendor' );
$buyer_id  = wpss_test_fixture_user( 'buyer' );
if ( ! $vendor_id || ! $buyer_id ) {
	echo "SKIP  needs a seeded vendor and buyer\n";
	return;
}

$created = array();
register_shutdown_function(
	static function () use ( &$created ) {
		wp_set_current_user( 0 );
		foreach ( $created as $id ) {
			wp_delete_post( $id, true );
		}
	}
);

$image = static function ( int $author, int $parent = 0 ) use ( &$created ): int {
	$id        = (int) wp_insert_attachment(
		array(
			'post_title'     => 'QA gallery owner',
			'post_mime_type' => 'image/jpeg',
			'post_status'    => 'inherit',
			'post_author'    => $author,
		),
		'qa-gallery-owner.jpg',
		$parent
	);
	$created[] = $id;
	return $id;
};

wp_set_current_user( 0 );
$draft_id  = (int) wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_title'  => 'QA owner draft',
		'post_status' => 'draft',
		'post_author' => $vendor_id,
	)
);
$created[] = $draft_id;

$own      = $image( $vendor_id );
$foreign  = $image( $buyer_id );
$attached = $image( $buyer_id, $draft_id );

// Gallery ownership.
wp_set_current_user( $vendor_id );
$check( 'the fixture seller can upload_files (the old fallback applied)', current_user_can( 'upload_files' ) );
$check( 'a seller may use their own upload', wpss_user_can_use_attachment( $own ) );
$check( 'a seller may not use another member\'s image', ! wpss_user_can_use_attachment( $foreign ) );
$check( 'a seller may use an image attached to their own service', wpss_user_can_use_attachment( $attached ) );
wpss_save_service_gallery( $draft_id, array( $own, $foreign ) );
$saved = wpss_get_gallery_ids( get_post_meta( $draft_id, '_wpss_gallery', true ) );
$check( 'the shared gallery writer drops the foreign image', array( $own ) === array_map( 'intval', $saved ), wp_json_encode( $saved ) );
wp_set_current_user( $buyer_id );
$check( 'another member may not use an image attached to the seller\'s service', ! wpss_user_can_use_attachment( $image( $vendor_id, $draft_id ) ) );
wp_set_current_user( 1 );
$check( 'an admin may use any image', wpss_user_can_use_attachment( $foreign ) );

// Limit judged against the author.
$at_limit = static function ( $ok, $user_id ) use ( $vendor_id ) {
	return (int) $user_id === $vendor_id ? false : $ok;
};
add_filter( 'wpss_vendor_can_create_service', $at_limit, 99, 2 );

wp_set_current_user( $buyer_id );
$check( 'a non-admin saving a vendor\'s draft meets the vendor\'s limit', '' !== wpss_service_limit_error( $draft_id, $buyer_id ) );
wp_set_current_user( 1 );
$check( 'an admin publishing a vendor\'s draft is not limited', '' === wpss_service_limit_error( $draft_id, 1 ) );
$held = wpss_enforce_service_limit_on_insert(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'post_author' => $vendor_id,
	),
	array()
);
$check( 'an admin creating a live service for a vendor at the limit is not held', 'publish' === $held['post_status'], (string) $held['post_status'] );
$check( 'an admin\'s own new service is not limited', '' === wpss_service_limit_error( 0, 1 ) );

add_filter( 'wpss_member_bypasses_limits', '__return_true' );
$check( 'the bypass filter lifts the author\'s limit', '' === wpss_service_limit_error( $draft_id, $buyer_id ) );
remove_filter( 'wpss_member_bypasses_limits', '__return_true' );

remove_filter( 'wpss_vendor_can_create_service', $at_limit, 99 );
