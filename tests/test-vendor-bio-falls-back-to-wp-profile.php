<?php
/**
 * The Vendor Card block and the service page's About box show the vendor's bio
 * (Basecamp 10545805738).
 *
 * The Vendor Card read only the WordPress Biographical Info, and the About box
 * used `$profile->bio ?? description`, which never falls back because the
 * profile row stores '' rather than null. Both now read wpss_get_vendor_bio():
 * the vendor profile bio, else the WordPress bio.
 *
 * Run: wp eval-file tests/test-vendor-bio-falls-back-to-wp-profile.php
 *
 * @package WPSellServices
 */

use WPSellServices\Blocks\SellerCard;
use WPSellServices\Frontend\SingleServiceView;
use WPSellServices\Models\Service;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$f       = wpss_test_fixtures();
$vendor  = $f->vendor;
$service = ( $f->service )( 'publish' );
$set_bio = static function ( string $bio ) use ( $wpdb, $vendor ) {
	$wpdb->update( $wpdb->prefix . 'wpss_vendor_profiles', array( 'bio' => $bio ), array( 'user_id' => $vendor ) );
	wp_cache_flush();
	\WPSellServices\Models\VendorProfile::flush_memo();
};
$card  = static fn() => ( new SellerCard() )->render( array( 'userId' => $vendor, 'showBio' => true ) );
$about = static function () use ( $service ): string {
	ob_start();
	( new ReflectionClass( SingleServiceView::class ) )->newInstanceWithoutConstructor()->render_about_vendor( Service::from_post( get_post( $service ) ) );
	return (string) ob_get_clean();
};

update_user_meta( $vendor, 'description', 'QA-RFT WordPress bio' );

$set_bio( '' );
$check( 'Vendor Card: no profile bio falls back to the WordPress bio', false !== strpos( $card(), 'QA-RFT WordPress bio' ) );
$check( 'About the Vendor: no profile bio falls back to the WordPress bio', false !== strpos( $about(), 'QA-RFT WordPress bio' ) );

$set_bio( 'QA-RFT profile bio' );
$check( 'Vendor Card: the vendor profile bio wins', false !== strpos( $card(), 'QA-RFT profile bio' ) && false === strpos( $card(), 'QA-RFT WordPress bio' ) );
$check( 'About the Vendor: the vendor profile bio wins', false !== strpos( $about(), 'QA-RFT profile bio' ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
