<?php
/**
 * The vendor's Bio field and the admin vendor screen show the bio buyers read.
 *
 * Every public surface reads wpss_get_vendor_bio(), which falls back to the WordPress profile's
 * Biographical Info. The vendor's own Dashboard > Profile form and the admin vendor Overview still
 * read the vendor-profile column only, so a vendor whose bio came from WordPress saw an empty field
 * (and the admin saw "Not provided") while buyers saw the bio.
 *
 * Run: wp eval-file tests/test-vendor-bio-edit-shows-public-bio.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$f   = wpss_test_fixtures();
$bio = 'QA bio from the WordPress profile: brand identity for small food businesses.';
$wpdb->update( $wpdb->prefix . 'wpss_vendor_profiles', array( 'bio' => '' ), array( 'user_id' => $f->vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
update_user_meta( $f->vendor, 'description', $bio );
wp_cache_flush();

$check( 'buyers read the WordPress bio', $bio === wpss_get_vendor_bio( $f->vendor ) );

// The vendor's own Dashboard > Profile form.
wp_set_current_user( $f->vendor );
$user_id        = $f->vendor;
$vendor_service = new \WPSellServices\Services\VendorService();
$is_vendor      = true;
ob_start();
include dirname( __DIR__ ) . '/templates/dashboard/sections/profile.php';
$form = (string) ob_get_clean();
$check( 'Dashboard > Profile: the Bio field holds that bio', (bool) preg_match( '#<textarea id="bio"[^>]*>' . preg_quote( esc_textarea( $bio ), '#' ) . '</textarea>#', $form ) );

// The admin vendor Overview tab.
wp_set_current_user( (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] );
$page   = new \WPSellServices\Admin\Pages\VendorsPage();
$method = new ReflectionMethod( $page, 'render_tab_overview' );
$method->setAccessible( true );
ob_start();
$method->invoke( $page, $f->vendor );
$admin = (string) ob_get_clean();
$check( 'admin vendor Overview shows that bio, not "Not provided"', str_contains( $admin, $bio ) );
wp_set_current_user( 0 );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
