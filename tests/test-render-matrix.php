<?php
/**
 * Every page the plugin renders loads completely, for every role that sees it.
 *
 * Run: wp eval-file tests/test-render-matrix.php
 *
 * The 1.8.0 build fataled halfway through the order page and every check
 * still passed, because none loaded that page with a buyer's file on it. This
 * fetches each surface over real HTTP, with real sessions, using the
 * busiest buyer and vendor on the site so lists, orders and briefs render with
 * data where the site has any:
 *   - logged out: every mapped page, a service, a vendor profile, the request
 *     archive and a request, a category;
 *   - buyer and vendor: every dashboard section and their latest order page;
 *   - administrator: every plugin admin page and the admin order view.
 * A 500, a PHP error in the body, or a page that stops before </html> (a
 * fatal mid-render) fails.
 *
 * Needs the site served at home_url(); prints SKIP when nothing listens.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/SessionCookies.php';

use WPSellServices\Frontend\UnifiedDashboard;
use WPSellServices\Tests\Factories\SessionCookies;

$probe = wp_remote_get( home_url( '/' ), array( 'timeout' => 5, 'sslverify' => false ) );
if ( is_wp_error( $probe ) ) {
	echo 'SKIP  nothing is serving ' . home_url( '/' ) . ': ' . $probe->get_error_message() . "\n";
	return;
}

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	if ( ! $ok ) {
		echo 'FAIL  ' . $label . "\n";
		++$fails;
	}
};

global $wpdb;
$orders = $wpdb->prefix . 'wpss_orders';
// phpcs:disable WordPress.DB.DirectDatabaseQuery
$buyer_id  = (int) $wpdb->get_var( "SELECT customer_id FROM {$orders} WHERE customer_id > 1 GROUP BY customer_id ORDER BY COUNT(*) DESC LIMIT 1" );
$vendor_id = (int) $wpdb->get_var( "SELECT vendor_id FROM {$orders} WHERE vendor_id > 1 GROUP BY vendor_id ORDER BY COUNT(*) DESC LIMIT 1" );
$buy_order = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$orders} WHERE customer_id = %d ORDER BY id DESC LIMIT 1", $buyer_id ) );
$sell_ord  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$orders} WHERE vendor_id = %d ORDER BY id DESC LIMIT 1", $vendor_id ) );
// phpcs:enable

$first = static fn( string $type ) => (int) ( get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => 1, 'fields' => 'ids' ) )[0] ?? 0 );
$term  = get_terms( array( 'taxonomy' => 'wpss_service_category', 'number' => 1, 'hide_empty' => true ) );

$urls = array( 0 => array() );
foreach ( array( 'services_page', 'vendors_page', 'become_vendor', 'cart', 'checkout', 'registration', 'login' ) as $key ) {
	$urls[0][] = wpss_get_page_url( $key );
}
$urls[0][] = $first( 'wpss_service' ) ? get_permalink( $first( 'wpss_service' ) ) : '';
$urls[0][] = $first( 'wpss_request' ) ? get_permalink( $first( 'wpss_request' ) ) : '';
$urls[0][] = (string) get_post_type_archive_link( 'wpss_request' );
$urls[0][] = $vendor_id ? wpss_get_vendor_url( $vendor_id ) : '';
$urls[0][] = $term && ! is_wp_error( $term ) ? (string) get_term_link( $term[0] ) : '';

// Every dashboard section, for both sides of the marketplace.
$sections = ( new ReflectionMethod( UnifiedDashboard::class, 'get_sections' ) );
$sections->setAccessible( true );
foreach ( array( $buyer_id, $vendor_id ) as $uid ) {
	if ( ! $uid ) {
		continue;
	}
	wp_set_current_user( $uid );
	foreach ( (array) $sections->invoke( new UnifiedDashboard() ) as $group ) {
		foreach ( array_keys( (array) ( $group['items'] ?? array() ) ) as $section ) {
			$urls[ $uid ][] = wpss_get_dashboard_url( $section );
		}
	}
}
wp_set_current_user( 0 );
if ( $buy_order ) {
	$urls[ $buyer_id ][] = wpss_get_dashboard_url( 'orders' ) . $buy_order . '/';
}
if ( $sell_ord ) {
	$urls[ $vendor_id ][] = wpss_get_dashboard_url( 'sales' ) . $sell_ord . '/';
}

// Every plugin admin page as an administrator sees it.
$admin_pages = (array) ( json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/audit/coverage.json' ), true )['artefacts']['admin_page'] ?? array() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
foreach ( array_keys( $admin_pages ) as $slug ) {
	$urls[1][] = admin_url( 'admin.php?page=' . $slug );
}
if ( $buy_order ) {
	$urls[1][] = admin_url( 'admin.php?page=wpss-orders&action=view&order_id=' . $buy_order );
}

$fetched = 0;
foreach ( $urls as $uid => $list ) {
	$cookies = SessionCookies::for_user( (int) $uid );
	foreach ( array_unique( array_filter( $list ) ) as $url ) {
		$res = wp_remote_get( $url, array( 'timeout' => 30, 'sslverify' => false, 'cookies' => $cookies, 'redirection' => 3 ) );
		++$fetched;
		$who = $uid ? 'user ' . $uid : 'logged out';

		if ( is_wp_error( $res ) ) {
			$check( "{$url} ({$who}) request failed: " . $res->get_error_message(), false );
			continue;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = (string) wp_remote_retrieve_body( $res );

		$check( "{$url} ({$who}) -> {$code}", $code < 500 );
		$check( "{$url} ({$who}) printed a PHP error", ! preg_match( '/(Fatal error|Uncaught |critical error on this website|<b>Warning<\/b>|<b>Notice<\/b>|<b>Deprecated<\/b>)/', $body ) );
		$check( "{$url} ({$who}) stopped before </html> (a fatal mid-render)", $code >= 300 || false !== stripos( $body, '</html>' ) );
	}
	SessionCookies::end( (int) $uid );
}

echo "{$fetched} pages\n";
echo $fails ? "{$fails} FAILED\n" : "ALL PASS\n";
