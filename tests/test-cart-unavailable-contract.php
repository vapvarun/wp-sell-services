<?php
/**
 * The cart never counts or totals a service that can no longer be bought.
 *
 * Run: wp eval-file tests/test-cart-unavailable-contract.php
 *
 * Checkout already dropped unavailable services, but GET /cart and the header
 * cart count read the raw cart: after a service was trashed or unpublished the
 * cart still listed it, totalled it and counted it (Basecamp 10330917388).
 * Makes its own buyer, seller and two services and removes them.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';
require_once __DIR__ . '/Factories/ServiceFactory.php';

use WPSellServices\Tests\Factories\ServiceFactory;
use WPSellServices\Tests\Factories\UserFactory;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$suffix = wp_generate_password( 6, false, false );
$buyer  = UserFactory::customer( array( 'user_login' => 'wpss_cart_buyer_' . $suffix, 'user_email' => 'wpss_cart_buyer_' . $suffix . '@example.test' ) );
$seller = UserFactory::vendor( array( 'user_login' => 'wpss_cart_seller_' . $suffix, 'user_email' => 'wpss_cart_seller_' . $suffix . '@example.test' ) );
$ids    = array();

foreach ( array( 'kept', 'gone' ) as $which ) {
	$made = ServiceFactory::single_plan( array( 'vendor_id' => $seller->ID ) );
	$id   = is_object( $made ) ? (int) $made->id : 0;

	if ( $id && 'publish' !== get_post_status( $id ) ) {
		wp_set_current_user( 1 );
		( new \WPSellServices\Services\ModerationService() )->approve( $id );
		clean_post_cache( $id );
	}
	$ids[ $which ] = $id;
}

$cart = static function () use ( $buyer ): array {
	wp_set_current_user( $buyer->ID );

	return (array) rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/cart' ) )->get_data();
};

try {
	if ( 'publish' !== get_post_status( $ids['kept'] ) || 'publish' !== get_post_status( $ids['gone'] ) ) {
		echo "FAIL  could not create two published fixture services\n";
		exit( 1 );
	}

	update_user_meta(
		$buyer->ID,
		'_wpss_cart',
		array(
			'a' => array( 'service_id' => $ids['kept'], 'package_id' => 0, 'quantity' => 1 ),
			'b' => array( 'service_id' => $ids['gone'], 'package_id' => 0, 'quantity' => 1 ),
		)
	);

	$both = $cart();
	$one  = (float) ( $both['items'][0]['total'] ?? 0 );
	$check( 'two buyable services: two lines, both in the total', 2 === count( $both['items'] ?? array() ) && $one > 0 && wpss_amounts_match( (float) $both['total'], $one + (float) $both['items'][1]['total'], wpss_get_currency() ) );
	$check( '  and the cart count is 2', 2 === wpss_get_cart_count( $buyer->ID ) );

	// Unpublished: the vendor may bring it back, so the line stays, flagged.
	wp_update_post( array( 'ID' => $ids['gone'], 'post_status' => 'draft' ) );
	$after = $cart();
	$line  = array_values( array_filter( (array) $after['items'], static fn( $i ) => (int) $i['service_id'] === $ids['gone'] ) )[0] ?? array();
	$check( 'an unpublished service is still listed, marked unavailable', ! empty( $line['unavailable'] ) && '' !== (string) ( $line['unavailable_reason'] ?? '' ) );
	$check( '  and is left out of the total', wpss_amounts_match( (float) $after['total'], $one, wpss_get_currency() ) );
	$check( '  and out of the cart count', 1 === wpss_get_cart_count( $buyer->ID ) );

	wp_trash_post( $ids['gone'] );
	$after = $cart();
	$check( 'a trashed service is left out of the total and the count too', wpss_amounts_match( (float) $after['total'], $one, wpss_get_currency() ) && 1 === wpss_get_cart_count( $buyer->ID ) );
} finally {
	wp_set_current_user( 0 );
	delete_user_meta( $buyer->ID, '_wpss_cart' );
	ServiceFactory::cleanup();
	UserFactory::cleanup();
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
