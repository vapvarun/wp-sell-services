<?php
/**
 * A service saved through REST gets a main image and a structured gallery with
 * only usable images, and keeps the video the wizard stored; ids that are not
 * images are dropped. One writer, wpss_save_service_gallery(), for every door.
 *
 * Run: wp eval-file tests/test-service-gallery-saver-sets-main-image.php
 *
 * Creates one QA draft service and deletes it on any exit.
 *
 * @package WPSellServices
 */

$service_id = 0;
$restore    = null;
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

$vendor = wpss_test_fixture_user( 'vendor' );
if ( ! $vendor ) {
	echo "SKIP  no seeded vendor on this site\n";
	return;
}
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$image = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%%' AND post_author = %d LIMIT 1", $vendor ) );
// A site seeded without images: upload a 1x1 PNG the vendor owns (swept with the script's posts).
if ( ! $image ) {
	$bits = wp_upload_bits( 'qa-gallery-' . wp_generate_password( 6, false, false ) . '.png', null, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
	if ( empty( $bits['error'] ) ) {
		$image = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'QA-GALLERY image',
				'post_status'    => 'inherit',
				'post_author'    => $vendor,
			),
			$bits['file']
		);
	}
}
$check( 'the QA vendor has an image to use', $image > 0 );

$service_id = (int) wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'draft',
		'post_title'  => 'QA-GALLERY saver',
		'post_author' => $vendor,
	)
);
$restore    = static function () use ( &$service_id ) {
	if ( $service_id ) {
		wp_set_current_user( 1 );
		wp_delete_post( $service_id, true );
	}
};

update_post_meta( $service_id, '_wpss_gallery', array( 'images' => array(), 'video' => 'https://example.com/v.mp4' ) );

wp_set_current_user( $vendor );
$request = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $service_id );
$request->set_param( 'gallery', array( $image, 999999, $service_id ) );
$response = rest_do_request( $request );
$check( 'the REST save succeeds', 200 === $response->get_status(), (string) $response->get_status() . ' ' . wp_json_encode( $response->get_data() ) );

$gallery = get_post_meta( $service_id, '_wpss_gallery', true );
$check( 'the main image is set', (int) get_post_thumbnail_id( $service_id ) === $image, (string) get_post_thumbnail_id( $service_id ) );
$check( 'the gallery keeps only usable images', array( $image ) === array_map( 'intval', (array) ( $gallery['images'] ?? array() ) ), wp_json_encode( $gallery ) );
$check( 'the stored video is kept', 'https://example.com/v.mp4' === ( $gallery['video'] ?? '' ), wp_json_encode( $gallery ) );
