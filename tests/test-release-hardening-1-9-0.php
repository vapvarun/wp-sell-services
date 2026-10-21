<?php
/**
 * 1.9.0 release security review: five hardening points found by the independent diff review.
 *
 * Run: wp eval-file tests/test-release-hardening-1-9-0.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$read = static fn( string $rel ): string => (string) file_get_contents( WPSS_PLUGIN_DIR . $rel );

// 1. The pending-refund assign lock is a unique options row, not a get-then-set transient.
$refund = $read( 'src/Services/PendingRefundService.php' );
$check( 'assign lock is claimed atomically', false !== strpos( $refund, 'INSERT IGNORE' ) && false === strpos( $refund, 'set_transient( $lock' ) );
$service = new \WPSellServices\Services\PendingRefundService();
$claim   = new ReflectionMethod( $service, 'claim_lock' );
$claim->setAccessible( true );
$name = 'wpss_test_lock_' . wp_generate_password( 6, false );
$check( 'first claim wins', true === $claim->invoke( $service, $name ) );
$check( 'second claim is refused', false === $claim->invoke( $service, $name ) );
delete_option( $name );
$check( 'a released lock can be claimed again', true === $claim->invoke( $service, $name ) );
delete_option( $name );

// 2. A PayPal order id is checked before it reaches an API path.
$paypal = $read( 'src/Integrations/PayPal/PayPalGateway.php' );
$check( 'PayPal order id must match the id shape', false !== strpos( $paypal, "preg_match( '/^[A-Za-z0-9_-]{5,64}$/', \$paypal_order_id )" ) );

// 4. The notification list page size is capped.
$check( 'AJAX notification limit is capped at 50', false !== strpos( $read( 'src/Frontend/AjaxHandlers.php' ), "min( 50, max( 1, absint( \$_POST['limit'] ?? 10 ) ) )" ) );

// 5. A refused file name is sanitised before it is shown.
$check( 'delivery refusal message sanitises the file name', false !== strpos( $read( 'src/Services/DeliveryService.php' ), 'sanitize_file_name(' ) );

// 6. Smoke walk findings: a paid-out withdrawal reads Paid, a COD receipt does not claim payment,
//    a request order is named by its request, and the filter button is a 44px target.
$check( 'a completed withdrawal is labelled Paid', 'Paid' === \WPSellServices\Services\EarningsService::get_withdrawal_statuses()[ \WPSellServices\Services\EarningsService::WITHDRAWAL_COMPLETED ] );
$pro_dir = WPSS_PLUGIN_DIR . '../wp-sell-services-pro/src/Integrations/WooCommerce/';
if ( is_readable( $pro_dir . 'WCOrderBridge.php' ) ) {
	$check( 'the Woo receipt does not say a COD order is paid', false !== strpos( (string) file_get_contents( $pro_dir . 'WCOrderBridge.php' ), "'cod' === (string) ( \$order->payment_method" ) );
	$check( 'a request order line is named by its request', false !== strpos( (string) file_get_contents( $pro_dir . 'WCPayOrderResolver.php' ), "'request' === \$platform && '' !== \$subject" ) );
}
$check( 'a pay-on-delivery order shows no Paid On date', false !== strpos( $read( 'templates/order/payment.php' ), "'cod' !== (string) \$wpss_order->payment_method" ) );
$check( 'the notification filter button is a 44px target', false !== strpos( $read( 'assets/css/notifications.css' ), '.wpss-notif-filter button { min-height: 44px; }' ) );

exit( $fails ? 1 : 0 );
