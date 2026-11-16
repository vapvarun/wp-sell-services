<?php
/**
 * A REST publish that cannot go live says why (Basecamp 10475574103).
 *
 * Run: wp eval-file tests/test-rest-publish-refusal-is-explained.php
 *
 * PUT /services/{id} {status: publish} on a draft with no main image wrote the
 * status first, the publish guard demoted it, and the client got 200 with status
 * draft and no reason. The dashboard Publish button rides this route. Now the
 * edits are saved, the status change is refused with 400 and the guard's own
 * sentences, and a PUT carrying the missing image in the same body publishes.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f   = wpss_test_fixtures();
$put = static function ( int $id, array $body ) {
	$request = new WP_REST_Request( 'PUT', '/wpss/v1/services/' . $id );
	foreach ( $body as $key => $value ) {
		$request->set_param( $key, $value );
	}

	return rest_do_request( $request );
};
// A complete service, put back to draft without its main image.
$draft = static function (): int {
	$id = wpss_test_live_service();
	wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) );
	delete_post_thumbnail( $id );
	update_post_meta( $id, '_wpss_gallery', array( 'images' => array(), 'video' => '' ) );
	clean_post_cache( $id );

	return $id;
};

wp_set_current_user( $f->vendor );
add_filter( 'wpss_require_service_moderation', '__return_false', 99 );

$id       = $draft();
$response = $put( $id, array( 'status' => 'publish', 'title' => 'QA publish refusal renamed' ) );
$data     = $response->get_data();
$check( 'publishing without a main image answers 400', 400 === $response->get_status(), (string) $response->get_status() );
$check( 'with the code wpss_not_publishable', 'wpss_not_publishable' === ( $data['code'] ?? '' ), wp_json_encode( $data ) );
$check( 'and data.errors names the image', (bool) array_filter( (array) ( $data['data']['errors'] ?? array() ), static fn( $e ) => false !== stripos( $e, 'image' ) ), wp_json_encode( $data ) );
$check( 'the service stays a draft', 'draft' === get_post_status( $id ) );
$check( 'the other edits in the same PUT are saved', 'QA publish refusal renamed' === get_the_title( $id ) );

$image    = wpss_test_image( $f->vendor );
$response = $put( $id, array( 'status' => 'publish', 'gallery' => array( $image ), 'thumbnail_id' => $image ) );
$check( 'the same PUT carrying the image publishes', 200 === $response->get_status() && 'publish' === get_post_status( $id ), $response->get_status() . ' ' . get_post_status( $id ) . ' ' . wp_json_encode( $response->get_data() ) );
wp_delete_attachment( $image, true );

// With moderation on, a complete service goes to review, not live.
remove_filter( 'wpss_require_service_moderation', '__return_false', 99 );
add_filter( 'wpss_require_service_moderation', '__return_true', 99 );
$id    = $draft();
$image = wpss_test_image( $f->vendor );
$response = $put( $id, array( 'status' => 'publish', 'gallery' => array( $image ), 'thumbnail_id' => $image ) );
$check( 'with moderation on it goes to pending', 200 === $response->get_status() && 'pending' === get_post_status( $id ), $response->get_status() . ' ' . get_post_status( $id ) );
wp_delete_attachment( $image, true );
remove_filter( 'wpss_require_service_moderation', '__return_true', 99 );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
