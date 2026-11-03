<?php
/**
 * A service's gallery video is YouTube or Vimeo, so a page view never fetches a vendor-chosen host.
 *
 * The wizard saved the gallery video with esc_url_raw() only, while the page
 * renders it through oEmbed on every anonymous view, uncached. oEmbed fetches
 * an unknown URL to discover its embed, so any approved vendor could make the
 * site's server call a host of their choosing on each page view. The field
 * already said "YouTube or Vimeo URL"; the reader now enforces it (covering
 * rows saved before) and the wizard refuses anything else with a message.
 *
 * Run: wp eval-file tests/test-gallery-video-only-youtube-vimeo.php
 *
 * Creates one draft service and deletes it. Makes no real HTTP call.
 *
 * @package WPSellServices
 */

use WPSellServices\Frontend\ServiceWizard;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$youtube = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
$hostile = 'http://attacker.example/probe';

// 1. The reader, both stored shapes.
$check( 'a YouTube video is returned', $youtube === wpss_get_gallery_video_url( array( 'video' => $youtube ) ) );
$check( 'a Vimeo video is returned', 'https://vimeo.com/76979871' === wpss_get_gallery_video_url( array( 'video' => 'https://vimeo.com/76979871' ) ) );
$check( 'any other host is not', '' === wpss_get_gallery_video_url( array( 'video' => $hostile ) ) );
$check( 'nor in the list-of-items shape', '' === wpss_get_gallery_video_url( array( array( 'type' => 'video', 'url' => $hostile ) ) ) );

// 2. The page itself: render the real gallery partial and count outbound calls.
$calls = array();
$spy   = static function ( $pre, $args, $url ) use ( &$calls ) {
	$calls[] = $url;
	// Answer a YouTube oEmbed lookup so the allowed path renders without the network.
	if ( false !== strpos( $url, 'youtube.com/oembed' ) ) {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'type' => 'video', 'version' => '1.0', 'html' => '<iframe data-qa="yt"></iframe>', 'thumbnail_url' => 'https://i.ytimg.com/vi/x/hq.jpg' ) ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
		);
	}
	return new WP_Error( 'qa_blocked', 'blocked by test' );
};
add_filter( 'pre_http_request', $spy, 10, 3 );

$image   = (int) get_posts( array( 'post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'inherit' ) )[0] ?? 0;
$service = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'draft', 'post_title' => 'QA gallery video host' ) );
$render  = static function ( string $video ) use ( $service, $image ): string {
	update_post_meta( $service, '_wpss_gallery', array( 'images' => $image ? array( $image ) : array(), 'video' => $video ) );
	delete_post_meta( $service, '_oembed_' . md5( $video ) );
	global $post;
	$post = get_post( $service ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );
	ob_start();
	include WPSS_PLUGIN_DIR . 'templates/partials/service-gallery.php';
	wp_reset_postdata();
	return (string) ob_get_clean();
};

$check( 'found an image to build the gallery with', $image > 0 );

$calls = array();
$html  = $render( $hostile );
$check( 'a page with a hostile video URL makes no outbound call', array() === $calls, implode( ', ', $calls ) );
$check( 'and shows no video', false === strpos( $html, 'wpss-gallery-video' ) );

$calls = array();
$html  = $render( $youtube );
$check( 'a YouTube video still renders', false !== strpos( $html, 'data-qa="yt"' ) );
$check( 'and only YouTube is contacted', array() !== $calls && array() === array_filter( $calls, static fn( $u ) => ! preg_match( '#^https://(www\.)?youtube\.com/#', $u ) ), implode( ', ', $calls ) );

remove_filter( 'pre_http_request', $spy, 10 );
delete_transient( 'wpss_video_thumb_' . md5( $youtube ) );
wp_delete_post( $service, true );

// 3. The wizard refuses it instead of losing it silently.
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function () {
	throw new RuntimeException( 'done' );
} );
$refuse = static function ( $video ): array {
	ob_start();
	try {
		( new ReflectionMethod( ServiceWizard::class, 'refuse_unsupported_video' ) )->invoke( new ServiceWizard(), array( 'gallery' => array( 'video' => $video ) ) );
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
	}
	return (array) json_decode( (string) ob_get_clean(), true );
};
$refused = $refuse( $hostile );
$check( 'the wizard refuses a non-YouTube/Vimeo video', isset( $refused['success'] ) && false === $refused['success'] && false !== stripos( (string) ( $refused['data']['message'] ?? '' ), 'YouTube' ) );
$check( 'and lets a YouTube video through', array() === $refuse( $youtube ) );
$check( 'and an empty field', array() === $refuse( '' ) );
$check( 'and ignores a non-string without a warning', array() === $refuse( array( 'x' ) ) );

$source = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Frontend/ServiceWizard.php' );
$check( 'both save draft and publish run the check', 2 === substr_count( $source, '$this->refuse_unsupported_video( $data );' ) );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
