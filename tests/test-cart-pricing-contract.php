<?php
/**
 * The cart and the quote show what checkout will charge, for every add-on type.
 *
 * Run: wp eval-file tests/test-cart-pricing-contract.php
 *
 * Checkout was fixed to price add-ons by type, but the cart (web and REST)
 * still added every add-on as a flat amount, without tax - a 10% add-on on a
 * $50 package read $10 in the cart and charged $5 plus tax (Basecamp
 * 10336467507, 10336467589). Cart items now store the selection only and are
 * priced on every read by wpss_price_cart_item(); GET /services/{id}/quote is
 * the same pricer for screens that show a price before anything is in a cart.
 *
 * A throwaway vendor, buyer and service are created and removed. Tax is
 * pinned to 18% exclusive for exact numbers.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$money = static fn( $a, $b ) => abs( (float) $a - (float) $b ) < 0.01;

$tax = static fn() => array(
	'enable_tax'   => true,
	'tax_rate'     => 18,
	'tax_label'    => 'Tax',
	'tax_included' => false,
);
add_filter( 'pre_option_wpss_tax', $tax );

$vendor  = wp_insert_user( array( 'user_login' => 'dev_f1_cart_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_cart_vendor@example.test' ) );
$buyer   = wp_insert_user( array( 'user_login' => 'dev_f1_cart_buyer', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_cart_buyer@example.test' ) );
$service = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'draft', 'post_title' => 'dev_f1 cart pricing', 'post_author' => $vendor ) );
$GLOBALS['wpdb']->update( $GLOBALS['wpdb']->posts, array( 'post_status' => 'publish' ), array( 'ID' => $service ) );
clean_post_cache( $service );
update_post_meta( $service, '_wpss_moderation_status', 'approved' );
update_post_meta( $service, '_wpss_packages', array( array( 'name' => 'Standard', 'price' => 50, 'delivery_days' => 3 ) ) );
update_post_meta(
	$service,
	'_wpss_addons',
	array(
		array( 'title' => 'Flat', 'price' => 20 ),
		array( 'title' => 'Pct', 'price' => 10, 'price_type' => 'percentage' ),
		array( 'title' => 'PerQty', 'price' => 5, 'price_type' => 'quantity_based', 'max_quantity' => 4 ),
		array( 'title' => 'Size', 'price' => 7, 'field_type' => 'dropdown', 'options' => 'S, M, L' ),
	)
);

try {
	// Selection: flat + 10% + 3 x $5 + dropdown "M" = 50 + 20 + 5 + 15 + 7 = 97, tax 17.46, total 114.46.
	$selection = array(
		array( 'id' => 0 ),
		array( 'id' => 1 ),
		array( 'id' => 2, 'quantity' => 3 ),
		array( 'id' => 3, 'option' => 'M' ),
	);

	$quote_req = new WP_REST_Request( 'GET', '/wpss/v1/services/' . $service . '/quote' );
	$quote_req->set_param( 'addon_sel', wp_json_encode( $selection ) );
	$quote = rest_do_request( $quote_req )->get_data();
	$check( sprintf( 'quote: 97 + 17.46 tax = 114.46 (got %s)', $quote['total'] ?? 'none' ), $money( $quote['subtotal'] + $quote['addons_total'], 97 ) && $money( $quote['tax'] ?? 0, 17.46 ) && $money( $quote['total'] ?? 0, 114.46 ) );
	$check( 'quote: prices never come from the request', $money( $quote['addons'][1]['price'] ?? 0, 5 ) && 'M' === ( $quote['addons'][3]['option'] ?? '' ) );

	wp_set_current_user( $buyer );
	$add = new WP_REST_Request( 'POST', '/wpss/v1/cart/add' );
	$add->set_param( 'service_id', $service );
	$add->set_param( 'package_id', 0 );
	$add->set_param( 'addons', array_merge( $selection, array( array( 'id' => 1, 'price' => -500 ) ) ) );
	$added = rest_do_request( $add );
	$check( sprintf( 'REST add: 201 and the line total is 114.46 (got %s %s)', $added->get_status(), $added->get_data()['total'] ?? '' ), 201 === $added->get_status() && $money( $added->get_data()['total'] ?? 0, 114.46 ) );

	$stored = get_user_meta( $buyer, '_wpss_cart', true );
	$item   = is_array( $stored ) ? reset( $stored ) : array();
	$check( 'the cart stores the selection, not add-on prices', ! empty( $item['addons'] ) && ! array_filter( $item['addons'], static fn( $a ) => isset( $a['price'] ) ) && 3 === (int) ( $item['addons'][2]['quantity'] ?? 0 ) );

	$cart = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/cart' ) )->get_data();
	$check( sprintf( 'GET /cart: subtotal 97, tax 17.46, total 114.46 - what checkout charges (got %s / %s / %s)', $cart['subtotal'] ?? '', $cart['tax'] ?? '', $cart['total'] ?? '' ), $money( $cart['subtotal'] ?? 0, 97 ) && $money( $cart['tax'] ?? 0, 17.46 ) && $money( $cart['total'] ?? 0, 114.46 ) );

	// A cart saved before 1.8.0 holds flat add-on prices; it is repriced.
	$legacy = array(
		'service_id' => $service,
		'package_id' => 0,
		'quantity'   => 1,
		'addons'     => array( array( 'id' => 1, 'title' => 'Pct', 'price' => 10 ) ),
		'total'      => 60,
	);
	$line   = wpss_price_cart_item( $legacy );
	$check( sprintf( 'a pre-1.8.0 cart item with a stale $10 price reads 55 + tax (got %s)', is_wp_error( $line ) ? 'error' : $line['total'] ), ! is_wp_error( $line ) && $money( $line['total'], 64.9 ) );

	// A required text add-on left empty is refused at the door, not at checkout.
	$addons                   = get_post_meta( $service, '_wpss_addons', true );
	$addons[]                 = array( 'title' => 'Brief', 'price' => 3, 'field_type' => 'text', 'is_required' => true );
	update_post_meta( $service, '_wpss_addons', $addons );
	$refused = rest_do_request( $add );
	$check( sprintf( 'a required text add-on left empty is refused by cart add (HTTP %d)', $refused->get_status() ), $refused->get_status() >= 400 );
} finally {
	wp_set_current_user( 0 );
	remove_filter( 'pre_option_wpss_tax', $tax );
	delete_user_meta( $buyer, '_wpss_cart' );
	wp_delete_post( $service, true );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
