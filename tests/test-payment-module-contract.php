<?php
/**
 * Payment-module contract: every registered gateway meets the same rules (Basecamp 10551488242).
 *
 * Callers ask a gateway what it can do (supports()), never its name. A gateway that is off is
 * hidden from new checkouts and still serves its existing orders. Checked here, per gateway:
 * the registry key is its id; the checkout list (form, cart, app) is enabled + takes the currency
 * + in the owner's order; a confirm leg on an off gateway is served only when it asks the provider;
 * manual methods (Offline, or a slug no gateway registers) are never swept as abandoned, take Mark
 * as paid and refuse a gateway slug; every rail writes orders through the shared path.
 *
 * Run: wp eval-file tests/test-payment-module-contract.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Integrations\Gateways\AbstractPaymentGateway;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders   = $wpdb->prefix . 'wpss_orders';
$gateways = wpss()->get_payment_gateways();
$made     = array();

// 1. Registry: every gateway on the base, keyed by its own id; a mis-keyed entry is refused.
foreach ( $gateways as $key => $gateway ) {
	$check( "{$key}: on the base class, keyed by its own get_id()", $gateway instanceof AbstractPaymentGateway && $gateway->get_id() === $key );
}
$wrong = new class() extends \WPSellServices\Integrations\Gateways\OfflineGateway {};
add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
$kept = wpss_validate_registry( array( 'bank' => $wrong ), \WPSellServices\Integrations\Contracts\PaymentGatewayInterface::class, 'wpss_payment_gateways' );
remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
$check( 'a gateway registered under another id is refused', array() === $kept );

// 2. Features are declared, not inferred from names.
$expect = array(
	'offline'  => array( 'manual_confirmation' ),
	'stripe'   => array( 'redirect_return', 'verified_confirmation' ),
	'paypal'   => array( 'verified_confirmation' ),
	'razorpay' => array( 'verified_confirmation' ),
	'test'     => array(),
);
foreach ( $expect as $id => $features ) {
	if ( ! isset( $gateways[ $id ] ) ) {
		echo "SKIP  {$id} is not registered here\n";
		continue;
	}
	$have = array_values( array_filter( array( 'manual_confirmation', 'redirect_return', 'verified_confirmation' ), static fn( $f ) => wpss_gateway_supports( $gateways[ $id ], $f ) ) );
	$check( "{$id} supports exactly " . ( $features ? implode( ', ', $features ) : 'nothing' ), $features === $have, implode( ',', $have ) );
}

// 3. Manual methods: Offline and unregistered slugs; never a gateway that takes money itself.
$check( 'offline and bank_transfer are manual, stripe / test / none are not', wpss_is_manual_payment_method( 'offline' ) && wpss_is_manual_payment_method( 'bank_transfer' ) && ! wpss_is_manual_payment_method( 'stripe' ) && ! wpss_is_manual_payment_method( 'test' ) && ! wpss_is_manual_payment_method( '' ) );

// 4. Checkout list: enabled + takes the currency, owner's order first, the same list for web and app.
$on = static fn() => array( 'enabled' => '1' );
add_filter( 'pre_option_wpss_offline_settings', $on );
// Test is on under WP_DEBUG with its own switch; set it on the instance, restored below.
$test_settings = new ReflectionProperty( $gateways['test'], 'settings' );
$test_before   = $test_settings->getValue( $gateways['test'] );
$test_settings->setValue( $gateways['test'], array_merge( (array) $test_before, array( 'enabled' => '1' ) ) );
$general = (array) get_option( 'wpss_general', array() );
add_filter( 'pre_option_wpss_general', static fn() => array_merge( $general, array( 'payment_methods' => array( 'test', 'offline' ) ) ) );
$list = array_keys( wpss_get_checkout_gateways( 'USD' ) );
$check( 'the owner\'s order wins: test before offline', array_slice( $list, 0, 2 ) === array( 'test', 'offline' ), implode( ',', $list ) );
$check( 'every listed method is on and takes the currency', array() === array_filter( $list, static fn( $id ) => ! $gateways[ $id ]->is_enabled() || ! $gateways[ $id ]->supports_currency( 'USD' ) ) );
$check( 'a method that cannot take the currency is left out', ! in_array( 'paypal', array_keys( wpss_get_checkout_gateways( 'INR' ) ), true ) );
$check( 'Stripe takes ZAR and KWD now, not IRR', $gateways['stripe']->supports_currency( 'ZAR' ) && $gateways['stripe']->supports_currency( 'kwd' ) && ! $gateways['stripe']->supports_currency( 'IRR' ) );
wp_set_current_user( 1 );
$rest = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/payments/methods' ) );
$check( 'GET /payments/methods returns the same list', ! $rest->is_error() && array_column( (array) ( $rest->get_data()['methods'] ?? array() ), 'id' ) === array_keys( wpss_get_checkout_gateways() ), wp_json_encode( $rest->get_data() ) );

// 5. A confirm leg on an off gateway: served only when it asks the provider.
$test_settings->setValue( $gateways['test'], $test_before );
$get = new ReflectionMethod( \WPSellServices\API\PaymentController::class, 'get_gateway' );
$pc  = new \WPSellServices\API\PaymentController();
$check( 'Test switched off cannot confirm (it settles on the caller\'s word)', ! $gateways['test']->is_enabled() && null === $get->invoke( $pc, 'test', false ) );
$check( 'Stripe switched off still finishes a taken payment', ! $gateways['stripe']->is_enabled() && null !== $get->invoke( $pc, 'stripe', false ) );
$check( 'Stripe switched off cannot start one', null === $get->invoke( $pc, 'stripe', true ) );

// 6. Return from a provider page: generic, by capability.
$ret = new ReflectionMethod( \WPSellServices\Integrations\Standalone\StandaloneCheckoutProvider::class, 'render_payment_return' );
$out = (string) $ret->invoke( new \WPSellServices\Integrations\Standalone\StandaloneCheckoutProvider(), 'test', array() );
$check( 'a gateway without redirect_return gets the "we are confirming" notice', str_contains( $out, 'wpss-notice--info' ) );

// 7. Abandoned sweeps leave manual sub-orders alone, close the rest.
$row = static function ( ?string $method ) use ( $wpdb, $orders, &$made ): int {
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$orders,
		array(
			'order_number'   => 'QA-PMC-' . wp_generate_password( 6, false ),
			'customer_id'    => 1,
			'vendor_id'      => 1,
			'service_id'     => 0,
			'platform'       => \WPSellServices\Models\ServiceOrder::SUB_ORDER_TYPE_TIP,
			'status'         => 'pending_payment',
			'payment_method' => $method,
			'total'          => 5,
			'currency'       => 'USD',
			'created_at'     => gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ),
		)
	);
	$made[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};
$bank  = $row( 'bank_transfer' );
$off   = $row( 'offline' );
$card  = $row( 'stripe' );
$none  = $row( null );
( new \WPSellServices\Services\TippingService() )->cleanup_abandoned_tips();
$st = static fn( int $id ) => (string) $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( 'sweep keeps the offline and bank_transfer tips waiting', 'pending_payment' === $st( $off ) && 'pending_payment' === $st( $bank ), $st( $off ) . '/' . $st( $bank ) );
$check( 'sweep closes the unpaid card and no-method tips', 'pending_payment' !== $st( $card ) && 'pending_payment' !== $st( $none ), $st( $card ) . '/' . $st( $none ) );
$check( 'the to-confirm count includes the bank_transfer order', wpss_count_payments_to_confirm() >= 2 );

// 8. Mark as paid: any manual method, refuses a gateway slug, keeps the order's own method.
$manual = new \WPSellServices\Services\ManualPaymentService();
$refuse = $manual->settle( $bank, 'qa-ref', 'stripe' );
$check( 'Mark as paid refuses a gateway that records its own charges', is_wp_error( $refuse ) && 'wpss_method_is_a_gateway' === $refuse->get_error_code() );
$check( 'an unpaid bank_transfer tip is a Mark as paid door', $manual->is_unpaid_sub_order( wpss_get_order( $bank ) ) );
$manual->settle( $bank, 'qa-ref' );
$paid = wpss_get_order( $bank );
$check( 'settled as bank_transfer, not rewritten to offline', 'paid' === ( $paid->payment_status ?? '' ) && 'bank_transfer' === ( $paid->payment_method ?? '' ), ( $paid->payment_status ?? '' ) . '/' . ( $paid->payment_method ?? '' ) );

// 9. One writer: no rail builds or prices its own order rows.
$src = static fn( string $f ) => (string) file_get_contents( WPSS_PLUGIN_DIR . $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$check( 'Offline checkout writes through open_pending_order()', str_contains( $src( 'src/Integrations/Gateways/OfflineGateway.php' ), '$this->open_pending_order(' ) && ! str_contains( $src( 'src/Integrations/Gateways/OfflineGateway.php' ), 'price_service_line(' ) );
$check( 'Test settles through resolve + settle, never mark_as_paid or create_order', str_contains( $src( 'src/Integrations/Gateways/TestGateway.php' ), '$checkout->settle(' ) && ! preg_match( '/mark_as_paid\(|create_order\(/', $src( 'src/Integrations/Gateways/TestGateway.php' ) ) );
$check( 'Mark as paid lives on the order screen, not a gateway', str_contains( $src( 'src/Admin/OrderScreen.php' ), 'wp_ajax_wpss_admin_mark_order_paid' ) && ! str_contains( $src( 'src/Integrations/Gateways/OfflineGateway.php' ), 'wpss_admin_mark_order_paid' ) );

// Cleanup.
foreach ( $made as $id ) {
	$wpdb->delete( $orders, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
