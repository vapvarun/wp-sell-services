<?php
/**
 * Razorpay webhook dedupe: two partial refunds on one payment are two events.
 *
 * Run: wp eval-file tests/pro/test-razorpay-webhook-dedup-key.php
 *
 * @package WPSellServices
 */

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

$class = '\\WPSellServicesPro\\Integrations\\Razorpay\\RazorpayGateway';
if ( ! class_exists( $class ) ) {
	echo "SKIP  Razorpay gateway not loadable\n";
	return;
}

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$id = static function ( array $event ) use ( $class ): string {
	$m = new ReflectionMethod( $class, 'webhook_entity_id' );
	return (string) $m->invoke( ( new ReflectionClass( $class ) )->newInstanceWithoutConstructor(), $event );
};

$refund = static fn( string $refund_id ) => array( 'event' => 'refund.created', 'payload' => array( 'payment' => array( 'entity' => array( 'id' => 'pay_1' ) ), 'refund' => array( 'entity' => array( 'id' => $refund_id, 'payment_id' => 'pay_1' ) ) ) );

$check( 'two partial refunds on one payment get different keys', $id( $refund( 'rfnd_a' ) ) !== $id( $refund( 'rfnd_b' ) ) );
$check( 'a replayed refund event keeps its key', $id( $refund( 'rfnd_a' ) ) === $id( $refund( 'rfnd_a' ) ) );
$check( 'a payment event is keyed on the payment', 'pay_1' === $id( array( 'event' => 'payment.captured', 'payload' => array( 'payment' => array( 'entity' => array( 'id' => 'pay_1' ) ) ) ) ) );
$check( 'an order event is keyed on the order', 'order_1' === $id( array( 'event' => 'order.paid', 'payload' => array( 'order' => array( 'entity' => array( 'id' => 'order_1' ) ) ) ) ) );

exit( $fails ? 1 : 0 );
