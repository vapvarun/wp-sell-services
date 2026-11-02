<?php
/**
 * wpss_gallery_video_first: image first by default, video first when a site opts in.
 *
 * Run: wp eval-file tests/test-gallery-video-first-filter.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$svc = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'Video first test' ) );
$img = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'x', 'post_status' => 'inherit' ), '', $svc );
update_post_meta( $svc, '_wpss_gallery', array( 'images' => array( $img ), 'video' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' ) );
// No network in a test: hand back a fixed player.
add_filter( 'pre_oembed_result', static fn() => '<iframe src="https://example.test/player"></iframe>' );
set_post_thumbnail( $svc, $img );
$GLOBALS['post'] = get_post( $svc );
setup_postdata( $GLOBALS['post'] );

$render = static function () {
	ob_start();
	include WP_PLUGIN_DIR . '/wp-sell-services/templates/partials/service-gallery.php';
	return (string) ob_get_clean();
};

$default = $render();
$check( 'default: image button is shown', 0 === preg_match( '/class="wpss-gallery-zoom"\s+hidden/', $default ) );
$check( 'default: video box starts hidden', 1 === preg_match( '/<div class="wpss-gallery-video"\s+hidden/', $default ) );
$check( 'default: embed waits in the template', str_contains( $default, 'wpss-gallery-video-embed' ) );
$check( 'default: video thumb is not active', ! str_contains( $default, 'wpss-gallery-thumb--video active' ) );

add_filter( 'wpss_gallery_video_first', '__return_true' );
$first = $render();
$check( 'on: video box is not hidden', 1 === preg_match( '/<div class="wpss-gallery-video"\s*>/', $first ) );
$check( 'on: image button is hidden', 1 === preg_match( '/class="wpss-gallery-zoom"\s+hidden/', $first ) );
$check( 'on: embed is printed in place, no template', ! str_contains( $first, 'wpss-gallery-video-embed' ) && str_contains( $first, 'iframe' ) );
$check( 'on: video thumb is the active one', str_contains( $first, 'wpss-gallery-thumb--video active' ) );
$check( 'on: first image thumb is not active', 0 === preg_match( '/wpss-gallery-thumb active"\s+data-index="0"/', $first ) );
remove_all_filters( 'wpss_gallery_video_first' );

// Per-service: the filter gets the service id.
add_filter( 'wpss_gallery_video_first', static fn( $v, $id ) => (int) $id === (int) $svc, 10, 2 );
$check( 'per service: receives the id', str_contains( $render(), 'wpss-gallery-thumb--video active' ) );
remove_all_filters( 'wpss_gallery_video_first' );

wp_delete_post( $svc, true );
wp_delete_attachment( $img, true );
echo $fails ? "FAILED: {$fails}\n" : "OK\n";
