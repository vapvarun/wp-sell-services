<?php
/**
 * The 1.7.1 review dedupe never touches order-less reviews and keeps the row.
 *
 * Run: wp eval-file tests/test-review-dedupe-guard.php
 *
 * Reviews with order_id 0 (admin-imported) must survive; a true duplicate on a
 * real order is removed with an identifiers-only audit row. The unique key is
 * dropped for the duration so duplicates can be seeded, then restored.
 *
 * @package WPSellServices
 */

use WPSellServices\Database\SchemaManager;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$t = $wpdb->prefix . 'wpss_reviews';
$wpdb->query( "ALTER TABLE {$t} DROP INDEX uniq_order_review" ); // phpcs:ignore WordPress.DB
$made = array();
$add  = static function ( int $order, string $text ) use ( $wpdb, $t, &$made ): int {
	$wpdb->insert( $t, array( 'order_id' => $order, 'reviewer_id' => 1, 'reviewee_id' => 1, 'service_id' => 0, 'customer_id' => 1, 'vendor_id' => 1, 'rating' => 5, 'review' => $text, 'review_type' => 'customer_to_vendor' ) );
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};

$o1 = $add( 0, 'QA imported one' );
$o2 = $add( 0, 'QA imported two' );
$k  = $add( 987654, 'QA real order' );
$d  = $add( 987654, 'QA real order dup' );

$m = new ReflectionMethod( SchemaManager::class, 'dedupe_for_unique_keys' );
$m->invoke( new SchemaManager() );

$left = static fn( int $id ): bool => (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB
$check( 'imported reviews (order 0) both survive', $left( $o1 ) && $left( $o2 ) );
$check( 'real duplicate is removed', $left( $k ) && ! $left( $d ) );

$audit = $wpdb->get_var( $wpdb->prepare( "SELECT context FROM {$wpdb->prefix}wpss_audit_log WHERE event_type = 'review.duplicate_removed' AND object_id = %d", $d ) ); // phpcs:ignore WordPress.DB
$check( 'audit row names the kept review and the ratings', $audit && str_contains( (string) $audit, '"kept_id":' . $k ) && str_contains( (string) $audit, 'rating' ) );
$check( 'audit row holds no review text', $audit && ! str_contains( (string) $audit, 'QA real order dup' ) );

foreach ( $made as $id ) {
	$wpdb->delete( $t, array( 'id' => $id ) );
}
$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_audit_log WHERE event_type = 'review.duplicate_removed' AND object_id = {$d}" ); // phpcs:ignore WordPress.DB
$wpdb->query( "ALTER TABLE {$t} ADD UNIQUE KEY uniq_order_review (order_id,review_type)" ); // phpcs:ignore WordPress.DB

exit( $fails ? 1 : 0 );
