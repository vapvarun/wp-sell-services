<?php
/**
 * Order again: a finished order puts the same package, add-ons and quantity back in the buyer's cart
 * at today's price, and says what changed (Basecamp 9696986584).
 *
 * Run: wp eval-file tests/test-order-again.php
 *
 * Seeds a throwaway vendor, buyer, service and completed orders, stubs the rail's cart write, and removes them.
 *
 * @package WPSellServices
 */

use WPSellServices\Models\ServiceOrder;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$suffix = wp_generate_password( 6, false, false );
$vendor = \WPSellServices\Tests\Factories\UserFactory::vendor( array( 'user_login' => 'wpss_oa_vendor_' . $suffix, 'user_email' => 'wpss_oa_vendor_' . $suffix . '@example.test' ) );
$buyer  = \WPSellServices\Tests\Factories\UserFactory::customer( array( 'user_login' => 'wpss_oa_buyer_' . $suffix, 'user_email' => 'wpss_oa_buyer_' . $suffix . '@example.test' ) );
$other  = \WPSellServices\Tests\Factories\UserFactory::customer( array( 'user_login' => 'wpss_oa_other_' . $suffix, 'user_email' => 'wpss_oa_other_' . $suffix . '@example.test' ) );

$service = (int) wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'QA order again ' . $suffix, 'post_author' => $vendor->ID ) );
update_post_meta(
	$service,
	'_wpss_packages',
	array(
		array( 'name' => 'Basic', 'price' => 50, 'delivery_days' => 3, 'revisions' => 1 ),
		array( 'name' => 'Pro', 'price' => 90, 'delivery_days' => 5, 'revisions' => 2 ),
	)
);
$packages = wpss_assign_package_ids( $service );
wpss_save_service_addons( $service, array( array( 'title' => 'Source files', 'price' => 10, 'delivery_days_extra' => 1 ) ) );

$snapshot = $packages[1] + array( 'price' => 90 );
$order_ids = array();
$make      = static function ( array $over = array() ) use ( $wpdb, $vendor, $buyer, $service, $snapshot, &$order_ids ): ServiceOrder {
	$row = $over + array(
		'order_number'   => 'OA-' . wp_generate_password( 8, false, false ),
		'customer_id'    => $buyer->ID,
		'vendor_id'      => $vendor->ID,
		'service_id'     => $service,
		'package_id'     => 1,
		'subtotal'       => 90,
		'addons'         => wp_json_encode( array( array( 'id' => 0, 'title' => 'Source files', 'quantity' => 1, 'option' => '', 'text' => '', 'price' => 10 ) ) ),
		'addons_total'   => 10,
		'total'          => 100,
		'currency'       => wpss_get_currency(),
		'status'         => ServiceOrder::STATUS_COMPLETED,
		'payment_status' => 'paid',
		'platform'       => 'standalone',
		'meta'           => wp_json_encode( array( 'package_snapshot' => $snapshot ) ),
		'created_at'     => current_time( 'mysql', true ),
		'updated_at'     => current_time( 'mysql', true ),
	);
	$wpdb->insert( $wpdb->prefix . 'wpss_orders', $row );
	$order_ids[] = (int) $wpdb->insert_id;

	return ServiceOrder::find( (int) $wpdb->insert_id );
};
$code = static fn( $r ): string => is_wp_error( $r ) ? $r->get_error_code() : '';

// 1. The same choice, at today's price, nothing changed.
$order = $make();
$plan  = wpss_get_reorder_plan( $order, $buyer->ID );
$check( 'a completed order can be ordered again', is_array( $plan ) );
$check( 'it is the same package, by position', is_array( $plan ) && 1 === $plan['package_id'] );
$check( 'with its add-on and quantity 1', is_array( $plan ) && 1 === $plan['quantity'] && array( 0 ) === array_map( 'intval', array_column( $plan['line']['addons'], 'id' ) ) );
$check( 'with no warnings when nothing changed', is_array( $plan ) && array() === $plan['warnings'] && false === $plan['price_changed'] );

// 2. Who may, and what may not be repeated.
$check( 'another member cannot', 'wpss_reorder_not_owner' === $code( wpss_get_reorder_plan( $order, $other->ID ) ) );
$check( 'neither can the seller', 'wpss_reorder_not_owner' === $code( wpss_get_reorder_plan( $order, $vendor->ID ) ) );
$check( 'neither can a signed-out visitor', 'wpss_reorder_not_owner' === $code( wpss_get_reorder_plan( $order, 0 ) ) );
$check( 'an order still in progress cannot', 'wpss_reorder_not_available' === $code( wpss_get_reorder_plan( $make( array( 'status' => ServiceOrder::STATUS_IN_PROGRESS ) ), $buyer->ID ) ) );
$check( 'a tip cannot', 'wpss_reorder_not_available' === $code( wpss_get_reorder_plan( $make( array( 'platform' => ServiceOrder::SUB_ORDER_TYPE_TIP ) ), $buyer->ID ) ) );
$check( 'a custom quote cannot', 'wpss_reorder_not_available' === $code( wpss_get_reorder_plan( $make( array( 'package_id' => null, 'meta' => wp_json_encode( array( 'proposal_snapshot' => array( 'price' => 40 ) ) ) ) ), $buyer->ID ) ) );

// 3. Quantity is read back from the money.
$plan = wpss_get_reorder_plan( $make( array( 'subtotal' => 180, 'addons_total' => 10, 'total' => 190 ) ), $buyer->ID );
$check( 'two of the package orders two again', is_array( $plan ) && 2 === $plan['quantity'] );

// 4. A changed price is said, with both figures.
$live              = wpss_get_service_packages( $service );
$live[1]['price']  = 120;
update_post_meta( $service, '_wpss_packages', $live );
$plan = wpss_get_reorder_plan( $order, $buyer->ID );
$check( 'a changed price is reported', is_array( $plan ) && $plan['price_changed'] && abs( $plan['previous_total'] - 100 ) < 0.01 && abs( $plan['current_total'] - 130 ) < 0.01 );
$check( 'the warning names both prices', is_array( $plan ) && 1 === count( $plan['warnings'] ) && false !== strpos( $plan['warnings'][0], wpss_format_price( 100.0 ) ) && false !== strpos( $plan['warnings'][0], wpss_format_price( 130.0 ) ) );
$live[1]['price'] = 90;
update_post_meta( $service, '_wpss_packages', $live );

// 5. An add-on that is gone is dropped and named; the order is still offered.
$addons = get_post_meta( $service, '_wpss_addons', true );
delete_post_meta( $service, '_wpss_addons' );
$plan = wpss_get_reorder_plan( $order, $buyer->ID );
$check( 'a removed add-on is left out and named', is_array( $plan ) && array() === $plan['line']['addons'] && 1 === count( array_filter( $plan['warnings'], static fn( $w ) => false !== strpos( $w, 'Source files' ) ) ) );
update_post_meta( $service, '_wpss_addons', $addons );

// 6. A removed package is refused, never swapped for another tier.
$kept = wpss_get_service_packages( $service );
update_post_meta( $service, '_wpss_packages', array( $kept[0] ) );
$check( 'a removed package is refused', 'wpss_reorder_package_gone' === $code( wpss_get_reorder_plan( $order, $buyer->ID ) ) );
update_post_meta( $service, '_wpss_packages', $kept );

// 7. A legacy snapshot with no id: same position and name still works, a renamed tier does not.
$legacy_snapshot = $snapshot;
unset( $legacy_snapshot['id'] );
$legacy = $make( array( 'meta' => wp_json_encode( array( 'package_snapshot' => $legacy_snapshot ) ) ) );
$check( 'a legacy order resolves by position and name', is_array( wpss_get_reorder_plan( $legacy, $buyer->ID ) ) );
$renamed         = $kept;
$renamed[1]['name'] = 'Something else';
update_post_meta( $service, '_wpss_packages', $renamed );
$check( 'a renamed tier is not "the same order"', 'wpss_reorder_package_gone' === $code( wpss_get_reorder_plan( $legacy, $buyer->ID ) ) );
update_post_meta( $service, '_wpss_packages', $kept );

// 8. A service that cannot be sold says why.
update_post_meta( $service, '_wpss_status', 'paused' );
$err = wpss_get_reorder_plan( $order, $buyer->ID );
$check( 'a paused service is refused with its own reason', is_wp_error( $err ) && '' !== $err->get_error_message() );
delete_post_meta( $service, '_wpss_status' );

// 9. REST: lands in the rail's cart through the one door, and says the same thing.
$captured = array();
$stub     = static function ( $result, $item ) use ( &$captured ) {
	$captured = $item;

	return array( 'handled' => true, 'cart_count' => 1, 'checkout_url' => home_url( '/qa-checkout/' ) );
};
add_filter( 'wpss_add_service_to_cart', $stub, 10, 2 );
$rest = static function ( int $order_id ) {
	return rest_do_request( new WP_REST_Request( 'POST', '/wpss/v1/orders/' . $order_id . '/reorder' ) );
};
wp_set_current_user( $buyer->ID );
$res  = $rest( $order->id );
$data = $res->get_data();
$check( 'REST answers 201 with the checkout URL', 201 === $res->get_status() && ! empty( $data['checkout_url'] ) );
$check( 'the cart got the same service, package and add-on', ( $captured['service_id'] ?? 0 ) === $service && 1 === ( $captured['package_id'] ?? -1 ) && array( 0 ) === array_map( 'intval', array_column( $captured['addons'] ?? array(), 'id' ) ) );
$check( 'REST reports the stable package id and totals', isset( $data['package_id'], $data['current_total'] ) && (int) $packages[1]['id'] === $data['package_id'] );
wp_set_current_user( $other->ID );
$check( 'REST refuses another member', in_array( $rest( $order->id )->get_status(), array( 403 ), true ) );
wp_set_current_user( 1 );
$check( 'REST refuses an admin who is not the buyer', 403 === $rest( $order->id )->get_status() );
wp_set_current_user( 0 );
$check( 'REST refuses a signed-out caller', in_array( $rest( $order->id )->get_status(), array( 401, 403 ), true ) );
wp_set_current_user( $buyer->ID );
$check( 'REST says 404 for an order that is not there', 404 === $rest( 987654321 )->get_status() );

// 10. The standalone rail keeps the selection in the buyer's own cart.
// WooCommerce's own handler is hooked in this process whichever adapter is set; it is the rail under test elsewhere.
remove_all_filters( 'wpss_add_service_to_cart' );
$manager  = wpss()->get_integration_manager();
$prop     = new ReflectionProperty( $manager, 'active_adapter' );
$prop->setAccessible( true );
$original = $prop->getValue( $manager );
$prop->setValue( $manager, $manager->get_adapter( 'standalone' ) );
delete_user_meta( $buyer->ID, '_wpss_cart' );
wp_set_current_user( $buyer->ID );
$res  = $rest( $order->id );
$cart = (array) get_user_meta( $buyer->ID, '_wpss_cart', true );
$item = reset( $cart );
$check( 'on the standalone rail REST answers 201', 201 === $res->get_status() );
$check( 'the buyer\'s cart holds the service, package and add-on', 1 === count( $cart ) && (int) $item['service_id'] === $service && 1 === (int) $item['package_id'] && array( 0 ) === array_map( 'intval', array_column( $item['addons'], 'id' ) ) );
$check( 'the checkout URL is the standalone checkout for that service', false !== strpos( (string) ( $res->get_data()['checkout_url'] ?? '' ), '/' . $service . '/' ) );
$res = $rest( $order->id );
$check( 'ordering again twice does not stack a duplicate line', 1 === count( (array) get_user_meta( $buyer->ID, '_wpss_cart', true ) ) );
$prop->setValue( $manager, $original );
delete_user_meta( $buyer->ID, '_wpss_cart' );

// Cleanup.
wp_set_current_user( 0 );
foreach ( $order_ids as $id ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $id ) );
}
wp_delete_post( $service, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vendor->ID );
wp_delete_user( $buyer->ID );
wp_delete_user( $other->ID );

exit( $fails ? 1 : 0 );
