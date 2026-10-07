<?php
/**
 * A gateway webhook pays an order only when this site made the payment.
 *
 * Run: wp eval-file tests/test-gateway-site-ownership-contract.php
 *
 * A gateway sends every event on the account to every site that listens:
 * a live site, a staging copy, a second store. Stripe got the check in
 * 10375174172; PayPal carried no mark at all and Razorpay wrote one it never
 * read, so a payment made on another site that named a local order id, for
 * the same amount, marked that order paid. Makes its own orders and removes
 * them. Razorpay is checked only where Pro is active.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table    = $wpdb->prefix . 'wpss_orders';
$currency = wpss_get_currency();
$made     = array();

$seed = static function () use ( $wpdb, $table, $currency, &$made ): int {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->insert(
		$table,
		array(
			'order_number'   => 'WPSS-SITEMARK-' . wp_generate_password( 6, false, false ),
			'customer_id'    => 1,
			'vendor_id'      => 1,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'subtotal'       => 23.60,
			'total'          => 23.60,
			'currency'       => $currency,
			'status'         => 'pending_payment',
			'payment_status' => 'pending',
			'created_at'     => current_time( 'mysql', true ),
		)
	);
	$made[] = (int) $wpdb->insert_id;

	return (int) $wpdb->insert_id;
};

$state = static function ( int $id ) use ( $wpdb, $table ): string {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	return (string) $wpdb->get_var( $wpdb->prepare( "SELECT payment_status FROM {$table} WHERE id = %d", $id ) );
};

add_filter( 'pre_wp_mail', '__return_true' );

try {
	$check( 'this site recognises its own address, with or without scheme and slash', wpss_is_own_payment_site( home_url( '/' ) ) && wpss_is_own_payment_site( (string) preg_replace( '#^https?://#', '', (string) get_option( 'home' ) ) ) );
	$check( '  and not another site\'s, nor an empty one', ! wpss_is_own_payment_site( 'https://another-store.example' ) && ! wpss_is_own_payment_site( '' ) );

	// PayPal: custom_id is full, so the mark is the start of the invoice number.
	$paypal  = new \WPSellServices\Integrations\PayPal\PayPalGateway();
	$ours    = 'WPSS-' . wpss_payment_site_hash() . '-';
	$capture = static function ( int $order_id, ?string $invoice ) use ( $paypal, $currency ) {
		return $paypal->handle_webhook(
			array(
				'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
				'resource'   => array(
					'id'        => 'CAP' . strtoupper( wp_generate_password( 12, false, false ) ),
					'custom_id' => wp_json_encode( array( 'order_id' => $order_id ) ),
					'amount'    => array( 'value' => '23.60', 'currency_code' => $currency ),
				) + ( null === $invoice ? array() : array( 'invoice_id' => $invoice ) ),
			)
		);
	};

	$a = $seed();
	$capture( $a, 'WPSS-ffffffffff-ABCDEF123456' );
	$check( 'PayPal: a capture made on another site does not pay a local order', 'pending' === $state( $a ) );

	$capture( $a, null );
	$check( 'PayPal: nor does one carrying no mark at all', 'pending' === $state( $a ) );

	$capture( $a, 'X' . $ours . 'ABCDEF123456' );
	$check( 'PayPal: nor one that only contains our mark somewhere', 'pending' === $state( $a ) );

	$capture( $a, $ours . 'ABCDEF123456' );
	$check( 'PayPal: this site\'s own capture pays it', 'paid' === $state( $a ) );

	if ( class_exists( '\WPSellServicesPro\Integrations\Razorpay\RazorpayGateway' ) ) {
		$razorpay = new \WPSellServicesPro\Integrations\Razorpay\RazorpayGateway();
		$paid     = static function ( int $order_id, array $notes ) use ( $razorpay, $currency ) {
			return $razorpay->handle_webhook(
				array(
					'event'   => 'order.paid',
					'payload' => array(
						'order'   => array( 'entity' => array( 'id' => 'order_' . wp_generate_password( 10, false, false ), 'currency' => $currency, 'notes' => array( 'order_id' => $order_id ) + $notes ) ),
						'payment' => array( 'entity' => array( 'id' => 'pay_' . wp_generate_password( 10, false, false ), 'amount' => wpss_amount_to_minor_units( 23.60, $currency ), 'currency' => $currency ) ),
					),
				)
			);
		};

		$c = $seed();
		$paid( $c, array( 'site_url' => 'https://another-store.example', 'platform' => 'wp-sell-services' ) );
		$check( 'Razorpay: a payment made on another site does not pay a local order', 'pending' === $state( $c ) );

		$paid( $c, array() );
		$check( 'Razorpay: nor does one carrying no site at all', 'pending' === $state( $c ) );

		$paid( $c, array( 'site_url' => wpss_payment_site_mark(), 'platform' => 'wp-sell-services' ) );
		$check( 'Razorpay: this site\'s own payment pays it', 'paid' === $state( $c ) );
	} else {
		echo "SKIP  Razorpay checks: WP Sell Services Pro is not active\n";
	}
} finally {
	remove_filter( 'pre_wp_mail', '__return_true' );
	foreach ( $made as $id ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_audit_log', array( 'object_type' => 'order', 'object_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
