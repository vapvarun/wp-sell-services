<?php
/**
 * The EDD and FluentCart carts refuse a suspended seller's service, as standalone and Woo do (Basecamp 10435884968).
 *
 * One rule, wpss_service_unavailable_reason(), and its seller half
 * wpss_seller_unavailable_reason() for an EDD download that is the service itself.
 * EDD is exercised for real when active (run with EDD active); FluentCart's cart
 * cannot be faked, so its wiring is checked here and its checkout walked in the
 * browser.
 *
 * Run: wp eval-file tests/test-store-carts-refuse-blocked-seller.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Services\VendorService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$seller = get_user_by( 'login', 'wpss_vendor_maya' );
$buyer  = get_user_by( 'login', 'wpss_buyer_ava' );
if ( ! $seller || ! $buyer ) {
	echo "FAIL  fixture: wpss_vendor_maya and wpss_buyer_ava\n";
	exit( 1 );
}

$service_id = (int) ( get_posts(
	array(
		'post_type'   => 'wpss_service',
		'author'      => $seller->ID,
		'post_status' => 'publish',
		'numberposts' => 1,
		'fields'      => 'ids',
	)
)[0] ?? 0 );
$made       = array();
$vendors    = new VendorService();
$status_was = (string) ( \WPSellServices\Models\VendorProfile::get_by_user_id( $seller->ID )->status ?? 'active' );

try {
	// 1. Free: the seller half is its own rule and the service rule uses it.
	$check( 'an active seller can sell', '' === wpss_seller_unavailable_reason( $seller->ID ) && '' === wpss_service_unavailable_reason( $service_id ) );

	$vendors->set_status( $seller->ID, 'suspended' );
	$seller_reason = wpss_seller_unavailable_reason( $seller->ID );
	$check( 'a suspended seller cannot (' . $seller_reason . ')', '' !== $seller_reason );
	$check( 'and their service gives the same reason', $seller_reason === wpss_service_unavailable_reason( $service_id ) );

	// 2. EDD, for real when it is active.
	if ( function_exists( 'edd_get_download_type' ) && class_exists( '\\WPSellServicesPro\\Integrations\\EDD\\EDDCheckoutProvider' ) ) {
		wp_set_current_user( $buyer->ID );
		$provider = new \WPSellServicesPro\Integrations\EDD\EDDCheckoutProvider();

		$native = wp_insert_post(
			array(
				'post_type'   => 'download',
				'post_status' => 'publish',
				'post_title'  => 'QA blocked-seller native download',
				'post_author' => $seller->ID,
			)
		);
		$linked = wp_insert_post(
			array(
				'post_type'   => 'download',
				'post_status' => 'publish',
				'post_title'  => 'QA blocked-seller linked download',
				'post_author' => 1,
			)
		);
		$made = array( $native, $linked );
		update_post_meta( $native, '_edd_product_type', 'service' );
		update_post_meta( $linked, '_edd_product_type', 'service' );
		update_post_meta( $linked, '_wpss_linked_service_id', $service_id );

		edd_clear_errors();
		$check( 'EDD refuses a suspended seller\'s own service download', false === $provider->refuse_unbuyable_service( true, new EDD_Download( $native ) ) );
		$check( 'EDD refuses a download linked to a suspended seller\'s service', false === $provider->refuse_unbuyable_service( true, new EDD_Download( $linked ) ) );
		$errors = (array) edd_get_errors();
		$check( 'EDD tells the buyer why (' . ( $errors['service_unavailable'] ?? 'no message' ) . ')', ( $errors['service_unavailable'] ?? '' ) === $seller_reason );

		$vendors->set_status( $seller->ID, 'active' );
		edd_clear_errors();
		$check( 'EDD sells again once the seller is active', true === $provider->refuse_unbuyable_service( true, new EDD_Download( $native ) ) && true === $provider->refuse_unbuyable_service( true, new EDD_Download( $linked ) ) );
	} else {
		echo "SKIP  EDD inactive: run with Easy Digital Downloads active for the EDD checks\n";
	}

	// 3. FluentCart: its checkout validator asks the same rule for every service line.
	$fluent = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services-pro/src/Integrations/FluentCart/FluentCartCheckoutProvider.php' );
	$check( 'FluentCart checkout validation asks wpss_service_unavailable_reason() per service line', 1 === preg_match( '/function validate_service_requirements\(.*?resolve_service_id\( \$item \);\s*\$reason\s*=\s*\$service_id \? wpss_service_unavailable_reason\( \$service_id \)/s', $fluent ) );
} finally {
	$vendors->set_status( $seller->ID, '' !== $status_was ? $status_was : 'active' );
	foreach ( $made as $id ) {
		wp_delete_post( $id, true );
	}
	wp_set_current_user( 1 );
}

echo $fails ? "\n$fails FAILED\n" : "\nOK\n";
exit( $fails ? 1 : 0 );
