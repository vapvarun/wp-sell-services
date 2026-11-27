<?php
/**
 * An opened bank chargeback tells the owner and marks the order; nothing else moves (Basecamp 10553225092).
 *
 * Stripe charge.dispute.created, PayPal CUSTOMER.DISPUTE.CREATED and Razorpay payment.dispute.created
 * go through one door on the base gateway. One owner email per dispute however many orders the payment
 * paid for; a resent event adds nothing; an unknown payment is acknowledged and nothing is written;
 * the order's status and the vendor's ledger stay as they are; a won or closed dispute clears the mark.
 * The "Dispute opened" email switch silences the email, not the mark.
 *
 * Run: wp eval-file tests/test-chargeback-opened.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Services\ChargebackService;
use WPSellServices\Services\OrderTimelineService;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$buyer  = (int) ( get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
$vendor = (int) ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
if ( ! $buyer || ! $vendor ) {
	echo "SKIP  needs a subscriber and an administrator\n";
	return;
}

global $wpdb;
$orders_table = $wpdb->prefix . 'wpss_orders';
$ledger       = $wpdb->prefix . 'wpss_wallet_transactions';
$made         = array();
$order        = static function ( string $txn, string $method ) use ( $wpdb, $orders_table, $buyer, $vendor, &$made ): int {
	$wpdb->insert(
		$orders_table,
		array(
			'order_number'   => 'QA-CB-' . wp_rand( 10000, 99999 ),
			'customer_id'    => $buyer,
			'vendor_id'      => $vendor,
			'service_id'     => 0,
			'subtotal'       => 50,
			'total'          => 50,
			'currency'       => 'USD',
			'status'         => 'in_progress',
			'payment_status' => 'paid',
			'payment_method' => $method,
			'transaction_id' => $txn,
			'platform'       => 'standalone',
		)
	);
	$made[] = (int) $wpdb->insert_id;

	return (int) $wpdb->insert_id;
};
$row     = static fn( int $id ) => $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$orders_table} WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$open    = static fn( int $id ): array => ChargebackService::open_for_order( $row( $id ) );
// A closure by reference: an arrow function would freeze $made while it is still empty.
$ledgers = static function () use ( $wpdb, $ledger, &$made ): int {
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$ledger} WHERE reference_id IN (" . implode( ',', array_map( 'intval', $made ) ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};

$mails = array();
add_filter(
	'pre_wp_mail',
	$catch = static function ( $pre, $atts ) use ( &$mails ) {
		$mails[] = (string) $atts['subject'];
		return true;
	},
	10,
	2
);
$keep_notify = get_option( 'wpss_notifications', null );

$gateways = wpss()->get_payment_gateways();
$stripe   = $gateways['stripe'] ?? new \WPSellServices\Integrations\Stripe\StripeGateway();
$paypal   = $gateways['paypal'] ?? new \WPSellServices\Integrations\PayPal\PayPalGateway();

try {
	// 1. Stripe: one charge paid for two orders (a cart).
	$a      = $order( 'pi_qa_cb_one', 'stripe' );
	$b      = $order( 'pi_qa_cb_one', 'stripe' );
	$due    = time() + 7 * DAY_IN_SECONDS;
	$event  = array(
		'type' => 'charge.dispute.created',
		'data' => array(
			'object' => array(
				'id'               => 'dp_qa_one',
				'payment_intent'   => 'pi_qa_cb_one',
				'amount'           => 5000,
				'currency'         => 'usd',
				'evidence_details' => array( 'due_by' => $due ),
			),
		),
	);
	$ledger0 = $ledgers();
	$res     = $stripe->handle_webhook( $event );
	$check( 'Stripe opened: acknowledged', ! empty( $res['success'] ) );
	$check( '  both orders the charge paid for show "Chargeback open"', 1 === count( $open( $a ) ) && 1 === count( $open( $b ) ) );
	$check( '  the owner gets one email for the dispute', 1 === count( $mails ) && str_contains( $mails[0] ?? '', 'Chargeback opened' ), implode( ' | ', $mails ) );
	$check( '  the order status and payment stay as they were', 'in_progress' === $row( $a )->status && 'paid' === $row( $a )->payment_status );
	$check( '  no money moves', $ledger0 === $ledgers() );
	$check( '  the evidence date is kept', gmdate( 'Y-m-d H:i:s', $due ) === ( $open( $a )['dp_qa_one']['due_by'] ?? '' ) );
	$timeline = ( new OrderTimelineService() )->get_timeline( $a );
	$check( '  Activity shows when it opened and when evidence is due', (bool) array_filter( $timeline, static fn( $e ) => 'chargeback_opened' === $e['type'] && str_contains( $e['message'], 'Evidence due' ) ) );

	$stripe->handle_webhook( $event );
	$check( 'the same event resent: no second email', 1 === count( $mails ), (string) count( $mails ) );
	$check( '  and no second record', 1 === count( ChargebackService::for_order( $row( $a ) ) ) );

	$res = $stripe->handle_webhook( array_replace_recursive( $event, array( 'data' => array( 'object' => array( 'id' => 'dp_qa_x', 'payment_intent' => 'pi_qa_unknown' ) ) ) ) );
	$check( 'a payment we never took: acknowledged, nothing written, no email', ! empty( $res['success'] ) && 1 === count( $mails ) );

	$stripe->handle_webhook(
		array(
			'type' => 'charge.dispute.closed',
			'data' => array(
				'object' => array(
					'id'             => 'dp_qa_one',
					'payment_intent' => 'pi_qa_cb_one',
					'status'         => 'won',
					'amount'         => 5000,
					'currency'       => 'usd',
				),
			),
		)
	);
	$check( 'won: the mark clears on both orders', ! $open( $a ) && ! $open( $b ) );
	$check( '  nothing moves', $ledger0 === $ledgers() && 'paid' === $row( $a )->payment_status );
	$check( '  Activity says it was won', (bool) array_filter( ( new OrderTimelineService() )->get_timeline( $a ), static fn( $e ) => 'chargeback_closed' === $e['type'] && 'won' === ( $e['data']['outcome'] ?? '' ) ) );

	// 2. PayPal: opened on a capture, then resolved for the buyer (the money comes as PAYMENT.CAPTURE.REVERSED).
	$c = $order( 'CAPQA123', 'paypal' );
	$paypal->handle_webhook(
		array(
			'event_type' => 'CUSTOMER.DISPUTE.CREATED',
			'resource'   => array(
				'dispute_id'               => 'PP-D-QA',
				'disputed_transactions'    => array( array( 'seller_transaction_id' => 'CAPQA123' ) ),
				'dispute_amount'           => array( 'value' => '50.00', 'currency_code' => 'USD' ),
				'seller_response_due_date' => gmdate( 'Y-m-d\TH:i:s\Z', $due ),
			),
		)
	);
	$check( 'PayPal opened: the order is marked and the owner emailed', 1 === count( $open( $c ) ) && 2 === count( $mails ) );
	$paypal->handle_webhook(
		array(
			'event_type' => 'CUSTOMER.DISPUTE.RESOLVED',
			'resource'   => array(
				'dispute_id'            => 'PP-D-QA',
				'disputed_transactions' => array( array( 'seller_transaction_id' => 'CAPQA123' ) ),
				'dispute_outcome'       => array( 'outcome_code' => 'RESOLVED_BUYER_FAVOUR' ),
			),
		)
	);
	$check( '  resolved: the mark clears, recorded as lost', ! $open( $c ) && 'lost' === ( ChargebackService::for_order( $row( $c ) )['PP-D-QA']['status'] ?? '' ) );

	// 3. Razorpay (Pro).
	if ( isset( $gateways['razorpay'] ) || class_exists( \WPSellServicesPro\Integrations\Razorpay\RazorpayGateway::class ) ) {
		$rzp = $gateways['razorpay'] ?? new \WPSellServicesPro\Integrations\Razorpay\RazorpayGateway();
		$d   = $order( 'pay_qa_cb', 'razorpay' );
		$ent = array(
			'id'         => 'disp_qa',
			'payment_id' => 'pay_qa_cb',
			'amount'     => 5000,
			'currency'   => 'USD',
			'respond_by' => $due,
		);
		$rzp->handle_webhook( array( 'event' => 'payment.dispute.created', 'payload' => array( 'dispute' => array( 'entity' => $ent ) ) ) );
		$check( 'Razorpay opened: the order is marked and the owner emailed', 1 === count( $open( $d ) ) && 3 === count( $mails ) );
		$rzp->handle_webhook( array( 'event' => 'payment.dispute.won', 'payload' => array( 'dispute' => array( 'entity' => $ent ) ) ) );
		$check( '  won: the mark clears', ! $open( $d ) );
	}

	// 4. The email switch silences the email, never the mark.
	update_option( 'wpss_notifications', array_merge( (array) $keep_notify, array( 'notify_dispute_opened' => false ) ) );
	$e     = $order( 'pi_qa_cb_quiet', 'stripe' );
	$quiet = count( $mails );
	$stripe->handle_webhook( array_replace_recursive( $event, array( 'data' => array( 'object' => array( 'id' => 'dp_qa_quiet', 'payment_intent' => 'pi_qa_cb_quiet' ) ) ) ) );
	$check( '"Dispute opened" email off: no email, the order is still marked', $quiet === count( $mails ) && 1 === count( $open( $e ) ) );
} finally {
	remove_filter( 'pre_wp_mail', $catch, 10 );
	null === $keep_notify ? delete_option( 'wpss_notifications' ) : update_option( 'wpss_notifications', $keep_notify );
	foreach ( $made as $id ) {
		$wpdb->delete( $orders_table, array( 'id' => $id ) );
		$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
		$wpdb->delete( $ledger, array( 'reference_id' => $id ) );
	}
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
