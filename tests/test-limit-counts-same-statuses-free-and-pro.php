<?php
/**
 * Free's per-vendor maximum and Pro's plan limit count the same services: a
 * service waiting for review takes a slot in both. With moderation on, a vendor
 * at their plan's maximum could otherwise queue unlimited pending services.
 *
 * Run: wp eval-file tests/test-limit-counts-same-statuses-free-and-pro.php
 *
 * Read-only.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$statuses = wpss_get_limit_counted_statuses();
$check( 'a service in review or scheduled takes a slot, a draft does not', array( 'publish', 'pending', 'future' ) === $statuses, wp_json_encode( $statuses ) );

$qa_vendor = wpss_test_fixture_user( 'vendor' );
if ( ! $qa_vendor ) {
	echo "SKIP  no seeded vendor on this site\n";
	return;
}
$profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $qa_vendor );
$check( 'the profile count uses the shared list', $profile && $profile->get_service_count() === wpss_count_vendor_services( $qa_vendor, $statuses ) );

$pro = dirname( dirname( __DIR__ ) ) . '/wp-sell-services-pro/src/VendorSubscriptions/VendorSubscriptionService.php';
if ( is_readable( $pro ) ) {
	$source = (string) file_get_contents( $pro );
	$check( 'the plan limit counts through the same list', str_contains( $source, 'wpss_count_vendor_services( $vendor_id, wpss_get_limit_counted_statuses() )' ) );
}
