<?php
/**
 * Review lists page stably and match their filter; writes that change nothing
 * leave no trace; one member cannot flood the owner with report emails.
 *
 * - Reviews sharing one created_at (an import) page in a fixed order, newest id
 *   first, on the service list, the vendor list and the template query.
 * - Reviews load-more sizes its page with wpss_reviews_per_page, as page one does.
 * - The admin vendor Reviews tab names the service of an imported review and
 *   shows the review text.
 * - An imported date-only value is the site's local day, stored in UTC.
 * - Setting the level a vendor already has fires no level-changed hook.
 * - Saving an unchanged reviewer name writes no review.edited audit row.
 * - A member filing reports in a loop sends the owner a capped number of emails.
 *
 * Run: wp eval-file tests/test-review-lists-and-quiet-writes.php
 *
 * Throwaway users and a service, swept by exit-on-fail; reviews tagged QA-RVW
 * and the rate-limit counter removed on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Database\Repositories\ReviewRepository;
use WPSellServices\Services\ReviewService;
use WPSellServices\Services\SellerLevelService;

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

global $wpdb;
$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$reviews  = $wpdb->prefix . 'wpss_reviews';
$reporter = 0;
register_shutdown_function(
	static function () use ( $wpdb, $reviews, &$reporter ) {
		$wpdb->query( "DELETE FROM {$reviews} WHERE reviewer_name LIKE 'QA-RVW%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		delete_transient( 'wpss_rate_report_alert_user_' . $reporter );
	}
);

wp_set_current_user( 1 );
$vendor = (int) wp_create_user( 'qa_rvw_vendor_' . wp_rand(), wp_generate_password(), 'qa-rvw-vendor-' . wp_rand() . '@example.test' );
wpss_test_activate_vendor( $vendor );
$service = (int) wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'post_title'  => 'QA-RVW Service ' . wp_rand(),
		'post_author' => $vendor,
	)
);

// Five imported reviews with one timestamp, as a CSV import leaves them.
$ids = array();
for ( $i = 1; $i <= 5; $i++ ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert( $reviews, array( 'order_id' => null, 'reviewer_id' => 0, 'reviewer_name' => 'QA-RVW ' . $i, 'reviewee_id' => $vendor, 'service_id' => $service, 'customer_id' => 0, 'vendor_id' => $vendor, 'rating' => 5, 'review' => 'QA-RVW body ' . $i, 'status' => 'approved', 'created_at' => '2024-03-01 00:00:00' ) );
	$ids[] = (int) $wpdb->insert_id;
}
rsort( $ids );
update_post_meta( $service, '_wpss_rating_count', 5 );

// Stable pages: one at a time, newest id first, nothing repeated or skipped.
$walk = static function ( callable $page ): array {
	$seen = array();
	for ( $o = 0; $o < 5; $o++ ) {
		foreach ( $page( $o ) as $row ) {
			$seen[] = (int) $row->id;
		}
	}
	return $seen;
};
$svc_pages = $walk( static fn( $o ) => ( new ReviewService() )->get_service_reviews( $service, array( 'limit' => 1, 'offset' => $o ) ) );
$check( 'service reviews page by newest id when timestamps tie', $ids === $svc_pages, implode( ',', $svc_pages ) );
$vnd_pages = $walk( static fn( $o ) => ( new ReviewRepository() )->get_by_vendor( $vendor, array( 'limit' => 1, 'offset' => $o ) ) );
$check( 'vendor reviews page by newest id when timestamps tie', $ids === $vnd_pages, implode( ',', $vnd_pages ) );
$tpl_pages = $walk( static fn( $o ) => ( new ReviewRepository() )->get_by_service( $service, array( 'limit' => 1, 'offset' => $o ) ) );
$check( 'repository service reviews page by newest id when timestamps tie', $ids === $tpl_pages, implode( ',', $tpl_pages ) );

// Load-more follows wpss_reviews_per_page: page 2 at 2 per page is reviews 3 and 4.
$per_page = static fn() => 2;
add_filter( 'wpss_reviews_per_page', $per_page );
add_filter( 'wp_doing_ajax', '__return_true' );
$die = static function () {
	return static function () {
		throw new RuntimeException( 'wp_die' );
	};
};
add_filter( 'wp_die_ajax_handler', $die );
wp_set_current_user( 0 );
$_POST    = array( 'nonce' => wp_create_nonce( 'wpss_service_nonce' ), 'service_id' => $service, 'page' => 2 );
$_REQUEST = $_POST;
ob_start();
try {
	( new \WPSellServices\Frontend\AjaxHandlers() )->load_reviews();
} catch ( RuntimeException $e ) {
	unset( $e );
}
$json = json_decode( (string) ob_get_clean(), true );
wp_set_current_user( 1 );
remove_filter( 'wp_die_ajax_handler', $die );
remove_filter( 'wp_doing_ajax', '__return_true' );
remove_filter( 'wpss_reviews_per_page', $per_page );
$html  = (string) ( $json['data']['html'] ?? '' );
$shown = substr_count( $html, 'class="wpss-review"' );
$check( 'load-more page 2 at 2 per page shows 2 reviews', 2 === $shown, "shown $shown" );
$check( 'load-more page 2 starts at the third review', false !== strpos( $html, 'QA-RVW ' . ( 6 - 3 ) ) && false === strpos( $html, 'QA-RVW 5' ), substr( wp_strip_all_tags( $html ), 0, 120 ) );
$check( 'load-more page 2 of 3 says there is more', true === ( $json['data']['has_more'] ?? null ) );

// Admin vendor Reviews tab names the imported review's service.
$page = ( new ReflectionClass( \WPSellServices\Admin\Pages\VendorsPage::class ) )->newInstance();
ob_start();
( new ReflectionMethod( $page, 'render_tab_reviews' ) )->invoke( $page, $vendor );
$tab = (string) ob_get_clean();
$check( 'vendor Reviews tab shows the service of an imported review', false !== strpos( $tab, get_the_title( $service ) ) );
$check( 'vendor Reviews tab shows the review text', false !== strpos( $tab, 'QA-RVW body 1' ) );

// An imported date-only value is the site's local day, stored in UTC.
$tz = static fn() => 'Asia/Kolkata';
add_filter( 'pre_option_timezone_string', $tz );
$imported = ( new ReviewService() )->create_imported( $vendor, $service, array( 'reviewer_name' => 'QA-RVW Import', 'rating' => 4, 'content' => 'QA-RVW dated', 'date' => '2024-01-05' ) );
remove_filter( 'pre_option_timezone_string', $tz );
$stored = is_wp_error( $imported ) ? $imported->get_error_message() : (string) $wpdb->get_var( $wpdb->prepare( "SELECT created_at FROM {$reviews} WHERE id = %d", $imported->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$check( 'import date 2024-01-05 in Asia/Kolkata is stored as 2024-01-04 18:30 UTC', '2024-01-04 18:30:00' === $stored, $stored );

// Same level again: no hook, no audit row. A real change still fires.
$levels = new SellerLevelService();
$fired  = 0;
$count  = static function () use ( &$fired ) {
	++$fired;
};
add_action( 'wpss_vendor_level_updated', $count );
$current = $levels->get_current_level( $vendor );
$levels->update_vendor_level( $vendor, $current );
$check( 'setting the level a vendor already has fires nothing', 0 === $fired, "fired $fired" );
$other = array_values( array_diff( array_keys( SellerLevelService::get_level_labels() ), array( $current ) ) )[0];
$levels->update_vendor_level( $vendor, $other );
$check( 'a real level change still fires once', 1 === $fired, "fired $fired" );
remove_action( 'wpss_vendor_level_updated', $count );

// Unchanged reviewer name: no review.edited row. A changed one: one row.
$audit  = $wpdb->prefix . 'wpss_audit_log';
$edited = static fn( int $id ): int => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$audit} WHERE event_type = 'review.edited' AND object_id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$put    = static function ( int $id, string $name ) {
	$req = new WP_REST_Request( 'PUT', '/wpss/v1/reviews/' . $id );
	$req->set_param( 'reviewer_name', $name );
	return rest_do_request( $req );
};
$target = $ids[0];
$put( $target, 'QA-RVW 5' );
$check( 'saving the same reviewer name writes no audit row', 0 === $edited( $target ), (string) $edited( $target ) );
$put( $target, 'QA-RVW Renamed' );
$check( 'renaming the reviewer writes one audit row', 1 === $edited( $target ), (string) $edited( $target ) );

// Report alerts: a member filing in a loop does not get one email per report.
$reporter = (int) wp_create_user( 'qa_rvw_reporter_' . wp_rand(), wp_generate_password(), 'qa-rvw-reporter-' . wp_rand() . '@example.test' );
$mails    = 0;
$capture  = static function () use ( &$mails ) {
	++$mails;
	return true;
};
add_filter( 'pre_wp_mail', $capture );
wp_set_current_user( $reporter );
for ( $i = 1; $i <= 12; $i++ ) {
	do_action( 'wpss_report_filed', 990000 + $i, 'message', $i, 0 );
}
wp_set_current_user( 1 );
remove_filter( 'pre_wp_mail', $capture );
$check( 'twelve reports from one member send the owner at most 5 emails', $mails >= 1 && $mails <= 5, "emails $mails" );
