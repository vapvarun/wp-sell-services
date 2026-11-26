<?php
/**
 * A Stripe confirm settles what this site stored on the charge, for the member
 * who started it (Basecamp 10545750765, 10545751632).
 *
 * Run: wp eval-file tests/test-confirm-trusts-stored-metadata.php
 *
 * Buyer B holding buyer A's payment id is refused on REST and AJAX before
 * anything is looked up, created or refunded, and A's order number is never
 * shown to B. A quantity-3 purchase confirmed with a POST that carries no
 * quantity (what stripe.js sent) settles at the charged total instead of
 * being refused and refunded. Stripe is stubbed.
 *
 * @package WPSellServices
 */

use WPSellServices\Checkout\CheckoutIntentService;
use WPSellServices\Integrations\Stripe\StripeGateway;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table   = $wpdb->prefix . 'wpss_orders';
$suffix  = wp_generate_password( 6, false, false );
$buyer_a = \WPSellServices\Tests\Factories\UserFactory::customer( array( 'user_login' => 'qa-rft-buyer-a-' . $suffix, 'user_email' => 'qa-rft-buyer-a-' . $suffix . '@example.test' ) );
$buyer_b = \WPSellServices\Tests\Factories\UserFactory::customer( array( 'user_login' => 'qa-rft-buyer-b-' . $suffix, 'user_email' => 'qa-rft-buyer-b-' . $suffix . '@example.test' ) );
$seller  = \WPSellServices\Tests\Factories\UserFactory::vendor( array( 'user_login' => 'qa-rft-seller-' . $suffix, 'user_email' => 'qa-rft-seller-' . $suffix . '@example.test' ) );

$service_id = wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'post_title'  => 'QA-RFT confirm metadata',
		'post_author' => $seller->ID,
	)
);
update_post_meta( $service_id, '_wpss_packages', array( array( 'name' => 'Standard', 'price' => 50, 'delivery_days' => 3 ) ) );

$max_qty  = static fn() => 5;
$settings = static fn() => array(
	'enabled'              => 1,
	'test_mode'            => 1,
	'test_secret_key'      => 'sk_test_qa',
	'test_publishable_key' => 'pk_test_qa',
);
add_filter( 'wpss_max_order_quantity', $max_qty );
add_filter( 'pre_option_wpss_stripe_settings', $settings );
add_filter( 'pre_wp_mail', '__return_true' );

// What create-intent stored: the server-priced quantity-3 purchase, for A.
wp_set_current_user( $buyer_a->ID );
$priced = ( new CheckoutIntentService() )->resolve(
	array(
		'service_id' => $service_id,
		'quantity'   => 3,
	)
);
wp_set_current_user( 0 );

$charges = array();
$refunds = array();
$charge  = static function ( string $id ) use ( $priced, &$charges ): void {
	$charges[ $id ] = array(
		'id'       => $id,
		'status'   => 'succeeded',
		'amount'   => wpss_amount_to_minor_units( $priced->amount, $priced->currency ),
		'currency' => strtolower( $priced->currency ),
		'metadata' => array_merge( $priced->metadata, array( 'site_url' => wpss_payment_site_mark(), 'platform' => 'wp-sell-services' ) ),
	);
};
$stub    = static function ( $pre, $args, $url ) use ( &$charges, &$refunds ) {
	if ( false === strpos( (string) $url, 'api.stripe.com' ) ) {
		return $pre;
	}

	$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );

	if ( false !== strpos( $path, '/refunds' ) ) {
		$refunds[] = $path;
		$body      = array( 'id' => 're_qa', 'status' => 'succeeded' );
	} else {
		$body = $charges[ basename( $path ) ] ?? array( 'error' => array( 'code' => 'resource_missing' ) );
	}

	return array(
		'response' => array( 'code' => 200, 'message' => 'OK' ),
		'body'     => wp_json_encode( $body ),
		'headers'  => array(),
		'cookies'  => array(),
	);
};
add_filter( 'pre_http_request', $stub, 10, 3 );
add_filter( 'wp_doing_ajax', '__return_true' );
$die = static fn() => static function () {
	throw new RuntimeException( 'done' );
};
add_filter( 'wp_die_ajax_handler', $die );

// stripe.js posted service, package and add-ons - never quantity.
$ajax = static function ( string $pi ) use ( $service_id ): array {
	$_POST    = array(
		'nonce'             => wp_create_nonce( 'wpss_stripe' ),
		'payment_intent_id' => $pi,
		'service_id'        => $service_id,
		'package_id'        => 0,
	);
	$_REQUEST = $_POST;
	ob_start();
	try {
		( new StripeGateway() )->ajax_confirm_payment();
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}

	return (array) json_decode( (string) ob_get_clean(), true );
};
$rest = static fn( string $pi ) => ( new StripeGateway() )->rest_confirm_payment( array( 'payment_id' => $pi, 'service_id' => $service_id ) );

$orders_of = static function ( int $user_id ) use ( $wpdb, $table ): array {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return $wpdb->get_results( $wpdb->prepare( "SELECT id, order_number, total, subtotal FROM {$table} WHERE customer_id = %d", $user_id ) );
};

try {
	$check( 'fixture: a quantity-3 purchase is priced at three packages', ! is_wp_error( $priced ) && 3 === (int) ( $priced->metadata['quantity'] ?? 0 ) );

	// 1. A pays for three; the confirm POST carries no quantity.
	$pi_paid = 'pi_qa_paid_' . $suffix;
	$charge( $pi_paid );
	wp_set_current_user( $buyer_a->ID );
	$r       = $ajax( $pi_paid );
	$a_order = $orders_of( $buyer_a->ID );
	$check( 'AJAX confirm of a quantity-3 charge settles from the stored metadata', ! empty( $r['success'] ) && 1 === count( $a_order ) );
	$check( '  the order is priced for three (subtotal 150)', isset( $a_order[0] ) && wpss_amounts_match( (float) $a_order[0]->subtotal, 150.0, $priced->currency ) );
	$check( '  and nothing is refunded', array() === $refunds );

	// 2. B replays A's settled payment id.
	wp_set_current_user( $buyer_b->ID );
	$r = $rest( $pi_paid );
	$check( 'REST: B confirming A\'s settled payment is refused with 403', is_wp_error( $r ) && 'wpss_payment_not_yours' === $r->get_error_code() && 403 === ( $r->get_error_data()['status'] ?? 0 ) );
	$check( '  and A\'s order number is not shown to B', ! isset( $a_order[0] ) || false === strpos( (string) wp_json_encode( is_wp_error( $r ) ? $r->get_error_data() : $r->get_data() ), (string) $a_order[0]->order_number ) );
	$r = $ajax( $pi_paid );
	$check( 'AJAX: B confirming A\'s settled payment is refused', empty( $r['success'] ) && ( ! isset( $a_order[0] ) || false === strpos( (string) wp_json_encode( $r ), (string) $a_order[0]->order_number ) ) );

	// 3. B confirms A's paid but not yet settled payment.
	$pi_open = 'pi_qa_open_' . $suffix;
	$charge( $pi_open );
	$r = $ajax( $pi_open );
	$check( 'AJAX: B confirming A\'s unsettled payment is refused', empty( $r['success'] ) );
	$r = $rest( $pi_open );
	$check( 'REST: B confirming A\'s unsettled payment is refused with 403', is_wp_error( $r ) && 'wpss_payment_not_yours' === $r->get_error_code() );
	$check( '  no order was created for B', array() === $orders_of( $buyer_b->ID ) );
	$check( '  and A\'s charges were not refunded', array() === $refunds );

	// 4. A can still confirm their own open payment afterwards.
	wp_set_current_user( $buyer_a->ID );
	$r = $rest( $pi_open );
	$check( 'REST: A confirming their own payment settles it', ! is_wp_error( $r ) && 2 === count( $orders_of( $buyer_a->ID ) ) );

	// 5. The create-payment request carries what the page priced: quantity 3 and a dropdown add-on.
	update_post_meta( $service_id, '_wpss_addons', array( array( 'title' => 'Size', 'price' => 7, 'field_type' => 'dropdown', 'options' => 'S, M, L' ) ) );
	$page = CheckoutIntentService::price_service_line( $service_id, 0, 3, array( array( 'id' => 0, 'option' => 'M' ) ) );
	$sent = array();
	$seen = static function ( $pre, $args, $url ) use ( &$sent ) {
		if ( false !== strpos( (string) $url, 'api.stripe.com/v1/payment_intents' ) && 'POST' === ( $args['method'] ?? '' ) ) {
			parse_str( (string) $args['body'], $body );
			$sent[] = $body;
		}
		return $pre;
	};
	add_filter( 'pre_http_request', $seen, 9, 3 );
	$charges['pi_qa_create'] = array( 'id' => 'pi_qa_create', 'client_secret' => 'cs', 'status' => 'requires_payment_method' );
	// The fields the checkout form renders for that line, as wpssCheckoutSelection() reads them.
	$_POST    = array(
		'nonce'      => wp_create_nonce( 'wpss_stripe' ),
		'service_id' => $service_id,
		'package_id' => 0,
		'quantity'   => 3,
	) + CheckoutIntentService::selection_metadata( $page['addons'] );
	$_REQUEST = $_POST;
	ob_start();
	try {
		( new StripeGateway() )->ajax_create_payment_intent();
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	ob_end_clean();
	remove_filter( 'pre_http_request', $seen, 9 );
	$check( 'with quantity and addon_sel posted, the Stripe intent is the page total', isset( $sent[0]['amount'] ) && (int) $sent[0]['amount'] === wpss_amount_to_minor_units( (float) $page['total'], $priced->currency ) );

	$root = dirname( __DIR__ );
	$pro  = dirname( $root ) . '/wp-sell-services-pro/assets/js/razorpay';
	$helper = (string) file_get_contents( $root . '/assets/js/checkout.js' );
	$check( 'checkout.js has one selection collector reading quantity and addon_sel only', false !== strpos( $helper, "window.wpssCheckoutSelection" ) && false !== strpos( $helper, "['quantity', 'addon_sel']" ) );
	foreach ( array( $root . '/assets/js/stripe', $root . '/assets/js/paypal', $pro ) as $js ) {
		$name = basename( $js );
		$check( "{$name}.js sends the selection with its create-payment request", false !== strpos( (string) @file_get_contents( $js . '.js' ), 'wpssCheckoutSelection()' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$check( "  and {$name}.min.js is rebuilt with it", false !== strpos( (string) @file_get_contents( $js . '.min.js' ), 'wpssCheckoutSelection' ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
} finally {
	wp_set_current_user( 0 );
	remove_filter( 'pre_http_request', $stub, 10 );
	remove_filter( 'wpss_max_order_quantity', $max_qty );
	remove_filter( 'pre_option_wpss_stripe_settings', $settings );
	remove_filter( 'pre_wp_mail', '__return_true' );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	remove_filter( 'wp_die_ajax_handler', $die );
	$_POST    = array();
	$_REQUEST = array();

	foreach ( array( $buyer_a->ID, $buyer_b->ID ) as $uid ) {
		foreach ( $orders_of( $uid ) as $row ) {
			$wpdb->delete( $table, array( 'id' => (int) $row->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}
	wp_delete_post( $service_id, true );
	\WPSellServices\Tests\Factories\UserFactory::cleanup();
}

exit( $fails ? 1 : 0 );
