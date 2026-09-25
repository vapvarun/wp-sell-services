<?php
/**
 * Seller levels follow stats unless an admin chose them.
 *
 * Run: wp eval-file tests/test-seller-level-override.php
 *
 * Basecamp 10337212282 / 10337169556 (owner decision 2026-09-25): a level is
 * computed from real stats, an admin override shows as "set by admin" and is
 * never overwritten. The weekly sweep used to overwrite every level, admin
 * choices included, and a seeded "Pro Seller" sat on a 3-order account.
 *
 * Throwaway vendor; every row is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\SellerLevelService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
add_filter( 'pre_wp_mail', '__return_true' );

$vendor = wp_insert_user( array( 'user_login' => 'dev_f1_lvl_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_lvl_vendor@example.test' ) );
$wpdb->insert( $profiles, array( 'user_id' => $vendor, 'display_name' => 'dev_f1 lvl', 'status' => 'active', 'verification_tier' => 'top_rated', 'created_at' => current_time( 'mysql' ) ) );
$service = new SellerLevelService();
$tier    = static fn() => (string) $wpdb->get_var( $wpdb->prepare( "SELECT verification_tier FROM {$profiles} WHERE user_id = %d", $vendor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

try {
	// A computed level that disagrees with the stats is corrected.
	$check( 'no stats, not admin-set: recalculate() gives new', 'new' === $service->recalculate( $vendor ) && 'new' === $tier() );

	// Admin chooses a level: it sticks through recalculation.
	$service->set_admin_level( $vendor, 'top_rated' );
	$service->recalculate( $vendor );
	$check( sprintf( 'admin-set top_rated survives recalculate (%s)', $tier() ), 'top_rated' === $tier() && $service->is_admin_set( $vendor ) );
	$service->recalculate_all_levels();
	$check( sprintf( 'and survives the weekly sweep (%s)', $tier() ), 'top_rated' === $tier() );

	// Automatic hands it back.
	$service->set_admin_level( $vendor, '' );
	$check( sprintf( 'Automatic clears the override and recalculates (%s)', $tier() ), 'new' === $tier() && ! $service->is_admin_set( $vendor ) );

	// Pro is admin-only: counts as admin-set even without the flag.
	$wpdb->update( $profiles, array( 'verification_tier' => 'pro' ), array( 'user_id' => $vendor ) );
	$service->recalculate( $vendor );
	$check( sprintf( 'Pro without the flag is not recalculated away (%s)', $tier() ), 'pro' === $tier() );
	$check( 'the public note says who set it', 'Awarded by the marketplace team' === wpss_seller_level_note( $vendor, 'pro' ) );
} finally {
	remove_filter( 'pre_wp_mail', '__return_true' );
	$wpdb->delete( $profiles, array( 'user_id' => $vendor ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d", $vendor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
