<?php
/**
 * A lost card dispute is a refund on the ledger; a won one changes nothing (Basecamp 10550988459).
 *
 * No gateway handled dispute events, so a chargeback took the money from the platform while the order
 * stayed completed and the vendor kept (and could withdraw) the credit. Stripe charge.dispute.closed
 * (lost), PayPal PAYMENT.CAPTURE.REVERSED and Razorpay payment.dispute.lost now enter the one refund
 * seam with the disputed amount (not a running total) and the dispute id as refund id.
 *
 * Run: wp eval-file tests/test-chargeback-lost-is-refund.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f        = wpss_test_fixtures();
$gateways = wpss()->get_payment_gateways();
$cur      = wpss_get_currency();
$minor    = static fn( float $v ): int => wpss_amount_to_minor_units( $v, $cur );
$row      = static fn( int $id ) => $wpdb->get_row( $wpdb->prepare( "SELECT status, refunded_amount FROM {$f->orders_t} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$made     = array();
$paid     = static function ( string $method, string $txn ) use ( $f, &$made ): int {
	$id     = ( $f->order )( array( 'status' => 'completed', 'total' => 100, 'subtotal' => 100, 'payment_method' => $method, 'transaction_id' => $txn, 'vendor_earnings' => 90.0, 'platform_fee' => 10.0, 'commission_rate' => 10.0 ) );
	$made[] = $id;
	// The vendor was credited at completion; a reversal claws back only what the ledger holds.
	wpss_insert_ledger_row( array( 'user_id' => $f->vendor, 'type' => 'order_earning', 'amount' => 90.0, 'currency' => wpss_get_currency(), 'reference_type' => 'order', 'reference_id' => $id, 'status' => 'completed' ) );
	return $id;
};

try {
	// 1. Stripe: 30 refunded earlier, then a lost dispute for the remaining 70.
	$pi = 'pi_qa_988459_' . wp_generate_password( 6, false );
	$a  = $paid( 'stripe', $pi );
	do_action( 'wpss_gateway_refund_received', 'stripe', $pi, 30.0, array( 'cumulative' => true, 'refund_id' => 're_qa_' . $a ) );
	$lost = array(
		'id'             => 'dp_qa_' . $a,
		'status'         => 'lost',
		'amount'         => $minor( 70.0 ),
		'currency'       => strtolower( $cur ),
		'payment_intent' => $pi,
	);
	$res  = $gateways['stripe']->handle_webhook( array( 'type' => 'charge.dispute.closed', 'data' => array( 'object' => $lost ) ) );
	$r    = $row( $a );
	$check( 'Stripe lost dispute after a 30 refund: the other 70 is refunded (100 total, refunded)', 100.0 === (float) $r->refunded_amount && 'refunded' === $r->status, wp_json_encode( $r ) . ' ' . wp_json_encode( $res ) );
	$gateways['stripe']->handle_webhook( array( 'type' => 'charge.dispute.closed', 'data' => array( 'object' => $lost ) ) );
	$check( '  the same event again adds nothing', 100.0 === (float) $row( $a )->refunded_amount );
	$rev = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_wallet_transactions WHERE type = 'order_reversal' AND reference_id = %d", $a ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$check( '  vendor earnings reversed twice in all (refund + dispute), once each', 2 === $rev, (string) $rev );

	// 2. Stripe: a won dispute changes nothing.
	$pi2 = 'pi_qa_988459w_' . wp_generate_password( 6, false );
	$b   = $paid( 'stripe', $pi2 );
	$gateways['stripe']->handle_webhook( array( 'type' => 'charge.dispute.closed', 'data' => array( 'object' => array( 'status' => 'won', 'payment_intent' => $pi2, 'amount' => $minor( 100.0 ), 'currency' => strtolower( $cur ), 'id' => 'dp_qa_w_' . $b ) ) ) );
	$check( 'Stripe won dispute: order untouched', 0.0 === (float) $row( $b )->refunded_amount && 'completed' === $row( $b )->status, wp_json_encode( $row( $b ) ) );

	// 3. PayPal: PAYMENT.CAPTURE.REVERSED is a refund resource pointing at the capture.
	if ( isset( $gateways['paypal'] ) ) {
		$cap    = 'QACAP' . strtoupper( wp_generate_password( 8, false ) );
		$c      = $paid( 'paypal', $cap );
		$prefix = new ReflectionMethod( $gateways['paypal'], 'invoice_prefix' );
		$prefix->setAccessible( true );
		$gateways['paypal']->handle_webhook(
			array(
				'event_type' => 'PAYMENT.CAPTURE.REVERSED',
				'resource'   => array(
					'id'         => 'QAREV' . $c,
					'amount'     => array( 'value' => '100.00', 'currency_code' => $cur ),
					'invoice_id' => $prefix->invoke( $gateways['paypal'] ) . $c,
					'links'      => array( array( 'rel' => 'up', 'href' => "https://api.paypal.com/v2/payments/captures/{$cap}" ) ),
				),
			)
		);
		$check( 'PayPal capture reversed: order refunded in full', 100.0 === (float) $row( $c )->refunded_amount, wp_json_encode( $row( $c ) ) );
	}

	// 4. Razorpay (Pro): payment.dispute.lost. Built directly when the licence gate kept it out of the list.
	if ( ! isset( $gateways['razorpay'] ) && class_exists( '\\WPSellServicesPro\\Integrations\\Razorpay\\RazorpayGateway' ) ) {
		$gateways['razorpay'] = new \WPSellServicesPro\Integrations\Razorpay\RazorpayGateway();
	}
	if ( isset( $gateways['razorpay'] ) ) {
		$pay = 'pay_qa' . wp_generate_password( 8, false );
		$d   = $paid( 'razorpay', $pay );
		$gateways['razorpay']->handle_webhook(
			array(
				'event'   => 'payment.dispute.lost',
				'payload' => array(
					'dispute' => array( 'entity' => array( 'id' => 'disp_qa_' . $d, 'payment_id' => $pay, 'amount' => $minor( 100.0 ), 'currency' => $cur ) ),
				),
			)
		);
		$check( 'Razorpay dispute lost: order refunded in full', 100.0 === (float) $row( $d )->refunded_amount, wp_json_encode( $row( $d ) ) );
	} else {
		echo "SKIP  Razorpay (Pro gateway not registered)\n";
	}
} finally {
	foreach ( $made as $oid ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'reference_id' => $oid, 'user_id' => $f->vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
