<?php
/**
 * One breadcrumb helper: Home first, last crumb is the current page, same markup on service, request and vendors.
 *
 * Run: wp eval-file tests/test-breadcrumb-helper.php
 *
 * Seeds one service and one request post (prefix QA-RFT); removes them after.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$capture = static function ( callable $fn ): string {
	ob_start();
	$fn();
	return (string) ob_get_clean();
};
$crumbs = static function ( string $html ): array {
	preg_match_all( '#<li class="wpss-breadcrumb-item[^"]*"[^>]*>(.*?)</li>#s', $html, $m );
	return array_map( static fn( $c ) => trim( wp_strip_all_tags( $c ) ), $m[1] );
};

$html = $capture( static fn() => wpss_render_breadcrumb( array( array( 'Vendors', '' ) ) ) );
$check( 'helper: Home then the current page', array( 'Home', 'Vendors' ) === $crumbs( $html ) );
$check( 'helper: the current crumb carries aria-current and no link', 1 === substr_count( $html, 'aria-current="page"' ) && false === strpos( $html, '>Vendors</a>' ) );
$check( 'helper: nav is labelled Breadcrumb', false !== strpos( $html, 'aria-label="Breadcrumb"' ) );

$html = $capture( static fn() => wpss_render_breadcrumb( array( array( 'Vendors', 'http://example.test/vendors/' ), array( 'Maya', '' ) ) ) );
$check( 'helper: Home > Vendors (linked) > Maya', array( 'Home', 'Vendors', 'Maya' ) === $crumbs( $html ) && false !== strpos( $html, 'href="http://example.test/vendors/"' ) );

$rid  = wp_insert_post( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'post_title' => 'QA-RFT request crumb' ) );
$html = $capture( static fn() => wpss_request_breadcrumb( $rid ) );
$check( 'request page: Home > Buyer Requests > its title', array( 'Home', 'Buyer Requests', 'QA-RFT request crumb' ) === $crumbs( $html ) );

$sid  = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'QA-RFT service crumb', 'post_author' => 1 ) );
$html = $capture( static fn() => ( new \WPSellServices\Frontend\SingleServiceView() )->render_breadcrumb( wpss_get_service( $sid ) ) );
$got  = $crumbs( $html );
$check( 'service page: Home > Services > its title', 'Home' === $got[0] && 'Services' === $got[1] && 'QA-RFT service crumb' === end( $got ) );

// The real pages, as a guest: the directory and one vendor's profile.
$page = (int) wpss_get_page_id( 'vendors_page' );
$vend = get_users( array( 'role__in' => array( 'wpss_vendor', 'subscriber', 'author', 'customer' ), 'number' => 50, 'fields' => 'all' ) );
$slug = '';
foreach ( $vend as $u ) {
	if ( wpss_is_vendor( (int) $u->ID ) ) {
		$slug = $u->user_nicename;
		break;
	}
}
if ( $page && '' !== $slug ) {
	$res = wp_remote_get( get_permalink( $page ), array( 'timeout' => 20 ) );
	$check( 'vendors page: Home > Vendors', array( 'Home', get_the_title( $page ) ) === $crumbs( (string) wp_remote_retrieve_body( $res ) ) );
	$res = wp_remote_get( add_query_arg( 'vendor', $slug, get_permalink( $page ) ), array( 'timeout' => 20 ) );
	$got = $crumbs( (string) wp_remote_retrieve_body( $res ) );
	$check( 'vendor profile: Home > Vendors > the vendor', 3 === count( $got ) && get_the_title( $page ) === $got[1] );
} else {
	echo "SKIP  no vendors page or no vendor on this site\n";
}

wp_delete_post( $rid, true );
wp_delete_post( $sid, true );
echo $fails ? "FAILED: {$fails}\n" : "OK\n";
