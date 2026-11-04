<?php
/**
 * A vendor's level badge has one renderer, and every level shows on every surface.
 *
 * The service card hid "New vendor" once the profile was complete while the vendor
 * directory and the profile always showed it, so one vendor had a badge on two
 * surfaces and none on their own services. Each surface also built its own markup.
 *
 * Throwaway vendor + service; every row is removed at the end.
 *
 * Run: wp eval-file tests/test-vendor-level-badge-one-renderer.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

// 1. Every surface goes through the partial and none prints its own tier badge.
foreach ( array( 'content-service-card.php', 'partials/vendor-card.php', 'vendor/profile.php' ) as $tpl ) {
	$src = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/' . $tpl );
	$check( "$tpl renders the badge through partials/vendor-level-badge.php", str_contains( $src, "'partials/vendor-level-badge.php'" ) );
	$check( "$tpl prints no tier badge markup of its own", ! preg_match( '/wpss-seller-badge--<\?php|wpss-seller-badge--new/', $src ) );
}

// 2. The real service card shows New for a vendor whose profile is complete.
global $wpdb;
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$vendor   = wp_insert_user( array( 'user_login' => 'dev_badge_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_badge_vendor@example.test' ) );
$wpdb->insert( $profiles, array( 'user_id' => $vendor, 'display_name' => 'dev badge', 'tagline' => 'Logo designer', 'bio' => 'Ten years of logos.', 'country' => 'IN', 'status' => 'active', 'verification_tier' => 'new', 'created_at' => current_time( 'mysql' ) ) );
$service = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'dev badge service', 'post_author' => $vendor ) );

try {
	$check( 'the throwaway profile is complete', \WPSellServices\Models\VendorProfile::get_by_user_id( $vendor )->is_profile_complete() );

	$GLOBALS['post'] = get_post( $service ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $GLOBALS['post'] );
	ob_start();
	include wpss_locate_template( 'content-service-card.php' );
	$html = (string) ob_get_clean();
	wp_reset_postdata();

	$check( 'service card shows the New vendor badge', (bool) preg_match( '/class="wpss-seller-badge wpss-seller-badge--new"[^>]*>\s*New vendor/', $html ), substr( wp_strip_all_tags( $html ), 0, 120 ) );
} finally {
	wp_delete_post( $service, true );
	$wpdb->delete( $profiles, array( 'user_id' => $vendor ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $vendor );
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
