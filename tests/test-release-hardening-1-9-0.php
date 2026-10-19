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

// 3. Only an approved review can be voted on.
$source = $read( 'src/Database/Repositories/ReviewRepository.php' );
$check( 'helpful vote looks up approved reviews only', false !== strpos( $source, "WHERE id = %d AND status = 'approved'" ) );

// 4. The notification list page size is capped.
$check( 'AJAX notification limit is capped at 50', false !== strpos( $read( 'src/Frontend/AjaxHandlers.php' ), "min( 50, max( 1, absint( \$_POST['limit'] ?? 10 ) ) )" ) );

// 5. A refused file name is sanitised before it is shown.
$check( 'delivery refusal message sanitises the file name', false !== strpos( $read( 'src/Services/DeliveryService.php' ), 'sanitize_file_name(' ) );

exit( $fails ? 1 : 0 );
