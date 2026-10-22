<?php
/**
 * The number a vendor sees as their service allowance is the one enforced:
 * get_max_services() applies `wpss_vendor_max_services` like the limit check does.
 *
 * Run: wp eval-file tests/test-vendor-max-services-filter.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\VendorProfile;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$user_id = (int) $wpdb->get_var( "SELECT user_id FROM {$wpdb->prefix}wpss_vendor_profiles LIMIT 1" );
$profile = $user_id ? VendorProfile::get_by_user_id( $user_id ) : null;

if ( ! $profile ) {
	echo "SKIP  no vendor profile on this install\n";
	exit( 0 );
}

$plan = static function () {
	return 77;
};
add_filter( 'wpss_vendor_max_services', $plan );
$check( 'allowance shown follows the plan filter', 77 === $profile->get_max_services(), (string) $profile->get_max_services() );
remove_filter( 'wpss_vendor_max_services', $plan );

$unlimited = static function () {
	return -1;
};
add_filter( 'wpss_vendor_max_services', $unlimited );
$check( 'a negative unlimited sentinel reads as 0', 0 === $profile->get_max_services() );
$check( 'and never holds the vendor back', false === $profile->has_reached_service_limit() );
remove_filter( 'wpss_vendor_max_services', $unlimited );

exit( $fails ? 1 : 0 );
