<?php
/**
 * Generated payment references are never shown as a buyer-quotable "Reference".
 *
 * Run: wp eval-file tests/test-generated-transaction-id-hidden.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Integrations\Gateways\OfflineGateway;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

foreach ( array( 'offline_manual_abc', 'offline_multi_abc', 'edd_123', 'fluentcart_9', 'test_77' ) as $id ) {
	$check( "{$id} is generated", OfflineGateway::is_generated_transaction_id( $id ) );
}
foreach ( array( 'pi_3Abc', 'PAYID-XYZ', 'bank-ref-5521', '' ) as $id ) {
	$check( "'{$id}' is a real reference", ! OfflineGateway::is_generated_transaction_id( $id ) );
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
