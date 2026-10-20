<?php
/**
 * "View all reviews" pages come from the server with the shared review partial.
 *
 * Run: wp eval-file tests/test-load-reviews-vendor.php
 *
 * Basecamp 10422086065. Calls the wpss_load_reviews handler for a vendor: page 2
 * must carry the Imported label and the service line, and stop at the last page.
 * Seeds 12 QA-IMPORT reviews, removes them after.
 *
 * @package WPSellServices
 */

use WPSellServices\Frontend\AjaxHandlers;
use WPSellServices\Services\ReviewService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$svc = null;
foreach ( get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'numberposts' => 50 ) ) as $p ) {
	if ( wpss_is_vendor( (int) $p->post_author ) ) {
		$svc = $p;
		break;
	}
}
$vendor = (int) $svc->post_author;
$rs     = new ReviewService();
for ( $i = 1; $i <= 12; $i++ ) {
	$rs->create_imported( $vendor, $svc->ID, array( 'reviewer_name' => "QA-IMPORT L{$i}", 'rating' => 5, 'content' => "QA-IMPORT load {$i}", 'date' => gmdate( 'Y-m-d', strtotime( "-{$i} day" ) ) ) );
}

add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function () { throw new RuntimeException( 'done' ); } );

$call = static function ( array $post ) {
	$_POST    = array_merge( array( 'nonce' => wp_create_nonce( 'wpss_service_nonce' ) ), $post );
	$_REQUEST = $_POST;
	ob_start();
	try {
		( new AjaxHandlers() )->load_reviews();
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	return json_decode( ob_get_clean(), true );
};

$page2 = $call( array( 'vendor_id' => $vendor, 'page' => 2 ) );
$html  = $page2['data']['html'] ?? '';
$check( 'vendor page 2 is served', ! empty( $page2['success'] ) && '' !== $html );
$check( 'page 2 carries the Imported label', str_contains( $html, 'wpss-review-imported' ) );
$check( 'page 2 names the service', str_contains( $html, 'wpss-review-service' ) );
$check( 'the vendor list has no Helpful control', ! str_contains( $html, 'wpss-review-actions' ) );
$check( 'a page past the end says no more', false === ( $call( array( 'vendor_id' => $vendor, 'page' => 99 ) )['data']['has_more'] ?? true ) );
$check( 'neither id is refused', empty( $call( array( 'page' => 2 ) )['success'] ) );

$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_reviews WHERE reviewer_name LIKE 'QA-IMPORT L%'" ); // phpcs:ignore WordPress.DB
wpss_recount_service_rating( $svc->ID );
( new \WPSellServices\Services\VendorService() )->update_stats( $vendor );

exit( $fails ? 1 : 0 );
