<?php
/**
 * A banned or suspended member cannot start a new purchase, and a banned
 * seller's live services are unlisted and unbuyable.
 *
 * Run: wp eval-file tests/test-standing-blocks-buying-doors.php
 *
 * Temporarily sets `wpss_account_status` on two QA users and restores both on
 * any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Checkout\CheckoutIntentService;
use WPSellServices\Checkout\ServiceCart;

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$buyer = wpss_test_fixture_user( 'buyer' );
if ( ! $buyer ) {
	echo "SKIP  no seeded buyer on this site\n";
	return;
}
$seller = wpss_test_fixture_user( 'vendor' );
if ( ! $seller ) {
	echo "SKIP  no seeded vendor on this site\n";
	return;
}
$prior  = array(
	$buyer  => get_user_meta( $buyer, 'wpss_account_status', true ),
	$seller => get_user_meta( $seller, 'wpss_account_status', true ),
);

$restore = static function () use ( $prior ) {
	foreach ( $prior as $user_id => $value ) {
		if ( '' === $value ) {
			delete_user_meta( $user_id, 'wpss_account_status' );
		} else {
			update_user_meta( $user_id, 'wpss_account_status', $value );
		}
	}
};

$service_id = (int) get_posts(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'author'      => $seller,
		'numberposts' => 1,
		'fields'      => 'ids',
	)
)[0];

$check( 'a live service by the QA seller exists', $service_id > 0 );
$check( 'in good standing the service is buyable', '' === wpss_service_unavailable_reason( $service_id ), wpss_service_unavailable_reason( $service_id ) );
$check( 'in good standing the seller is listed', ! in_array( $seller, wpss_get_unlisted_seller_ids(), true ) );

// Buyer standing.
update_user_meta( $buyer, 'wpss_account_status', 'banned' );
$intent = ( new CheckoutIntentService() )->resolve( array( 'service_id' => $service_id ), $buyer );
$check( 'a banned buyer cannot resolve a new checkout', is_wp_error( $intent ) && 'wpss_account_banned' === $intent->get_error_code(), is_wp_error( $intent ) ? $intent->get_error_code() : 'resolved' );

$added = ServiceCart::add( $buyer, $service_id, array( 'package_id' => 0, 'package' => array(), 'addons' => array(), 'total' => 0 ) );
$check( 'a banned buyer cannot add to the cart', is_wp_error( $added ) && 'wpss_account_banned' === $added->get_error_code(), is_wp_error( $added ) ? $added->get_error_code() : 'added' );
update_user_meta( $buyer, 'wpss_account_status', 'suspended' );
$intent = ( new CheckoutIntentService() )->resolve( array( 'service_id' => $service_id ), $buyer );
$check( 'a suspended buyer cannot resolve a new checkout', is_wp_error( $intent ) && 'wpss_account_suspended' === $intent->get_error_code() );
delete_user_meta( $buyer, 'wpss_account_status' );

// Seller standing.
update_user_meta( $seller, 'wpss_account_status', 'banned' );
$check( 'a banned seller is unlisted', in_array( $seller, wpss_get_unlisted_seller_ids(), true ) );
$check( 'a banned seller\'s live service is not buyable', '' !== wpss_service_unavailable_reason( $service_id ) );
delete_user_meta( $seller, 'wpss_account_status' );
$check( 'restored seller is buyable again', '' === wpss_service_unavailable_reason( $service_id ) );
