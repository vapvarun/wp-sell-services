<?php
/**
 * Admin-imported reviews: no order, no duplicates, no email, ratings recounted.
 *
 * Run: wp eval-file tests/test-imported-reviews.php
 *
 * Basecamp 10421729953. Throwaway rows tagged QA-IMPORT; all removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Database\SchemaManager;
use WPSellServices\Services\ReviewService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$t = $wpdb->prefix . 'wpss_reviews';

// Legacy shape: an order-0 import, as the old migration gist wrote it.
$wpdb->query( "ALTER TABLE {$t} DROP INDEX uniq_order_review" ); // phpcs:ignore WordPress.DB
$wpdb->query( "ALTER TABLE {$t} MODIFY order_id bigint(20) unsigned NOT NULL" ); // phpcs:ignore WordPress.DB
$wpdb->insert( $t, array( 'order_id' => 0, 'reviewer_id' => 0, 'reviewer_name' => 'QA-IMPORT legacy', 'reviewee_id' => 1, 'service_id' => 0, 'customer_id' => 0, 'vendor_id' => 1, 'rating' => 4, 'review' => 'QA-IMPORT legacy', 'review_type' => 'customer_to_vendor' ) );
$legacy = (int) $wpdb->insert_id;

( new ReflectionMethod( SchemaManager::class, 'dedupe_for_unique_keys' ) )->invoke( new SchemaManager() );
$wpdb->query( "ALTER TABLE {$t} ADD UNIQUE KEY uniq_order_review (order_id,review_type)" ); // phpcs:ignore WordPress.DB

$nullable = $wpdb->get_var( "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = 'order_id'" ); // phpcs:ignore WordPress.DB
$check( 'order_id is nullable after the migration', 'YES' === $nullable );
$check( 'an old order-0 review moves to NULL and survives', null === $wpdb->get_var( $wpdb->prepare( "SELECT order_id FROM {$t} WHERE id = %d", $legacy ) ) && $legacy ); // phpcs:ignore WordPress.DB

$services = get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'numberposts' => 50 ) );
$svc      = null;
foreach ( $services as $p ) {
	if ( wpss_is_vendor( (int) $p->post_author ) ) {
		$svc = $p;
		break;
	}
}
$vendor = (int) $svc->post_author;
$rs     = new ReviewService();
$sent   = 0;
add_action( 'wpss_review_created', static function () use ( &$sent ) { ++$sent; } );

$before = wpss_recount_service_rating( $svc->ID );
$a      = $rs->create_imported( $vendor, $svc->ID, array( 'reviewer_name' => 'QA-IMPORT Ann', 'rating' => 5, 'content' => 'Great work', 'date' => '2024-01-05' ) );
$check( 'an import is saved with no order and reads as imported', ! is_wp_error( $a ) && $a->is_imported() && '2024-01-05' === $a->created_at->format( 'Y-m-d' ) );
$check( 'importing does not fire wpss_review_created (no email)', 0 === $sent );

$dry = $rs->create_imported( $vendor, $svc->ID, array( 'reviewer_name' => 'QA-IMPORT Dry', 'rating' => 5, 'content' => 'x' ), true );
$check( 'a dry run passes every check and saves nothing', true === $dry && ! $wpdb->get_var( "SELECT id FROM {$t} WHERE reviewer_name = 'QA-IMPORT Dry'" ) ); // phpcs:ignore WordPress.DB

$dup = $rs->create_imported( $vendor, $svc->ID, array( 'reviewer_name' => 'QA-IMPORT Ann', 'rating' => 5, 'content' => 'Great work' ) );
$check( 'the same reviewer and text is refused as a duplicate', is_wp_error( $dup ) && 'wpss_import_review_duplicate' === $dup->get_error_code() );

$b = $rs->create_imported( $vendor, $svc->ID, array( 'reviewer_name' => 'QA-IMPORT Ben', 'rating' => 3, 'content' => 'Fine' ) );
$check( 'a second import on the same service is allowed (NULL order)', ! is_wp_error( $b ) );

$after = wpss_recount_service_rating( $svc->ID );
$check( 'the service rating count grew by the two imports', (int) $after['count'] === (int) $before['count'] + 2 );

$other = $rs->create_imported( $vendor + 999999, $svc->ID, array( 'reviewer_name' => 'QA-IMPORT X', 'rating' => 5 ) );
$check( 'a service that is not the vendor\'s is refused', is_wp_error( $other ) && 'wpss_import_review_service' === $other->get_error_code() );
$bad = $rs->create_imported( $vendor, $svc->ID, array( 'reviewer_name' => 'QA-IMPORT Y', 'rating' => 9 ) );
$check( 'a rating outside 1-5 is refused', is_wp_error( $bad ) && 'wpss_import_review_data' === $bad->get_error_code() );

$wpdb->query( "DELETE FROM {$t} WHERE reviewer_name LIKE 'QA-IMPORT%'" ); // phpcs:ignore WordPress.DB
wpss_recount_service_rating( $svc->ID );
( new \WPSellServices\Database\Repositories\VendorProfileRepository() )->update_stats( $vendor );

exit( $fails ? 1 : 0 );
