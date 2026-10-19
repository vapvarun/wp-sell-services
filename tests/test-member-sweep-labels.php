<?php
/**
 * Words a member reads: a Woo gateway names itself ("Cash on delivery", not "Cod"), and a
 * dispute reason key in a notification is its label (member sweep, 1.9.0).
 *
 * Run: wp eval-file tests/test-member-sweep-labels.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\NotificationService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

if ( function_exists( 'WC' ) && WC()->payment_gateways() && isset( WC()->payment_gateways()->payment_gateways()['cod'] ) ) {
	$check( 'a Woo cod order reads as its gateway title', 'Cod' !== wpss_get_payment_method_label( 'cod' ) && '' !== wpss_get_payment_method_label( 'cod' ) );
} else {
	echo "SKIP  Woo cod gateway not available\n";
}
$check( 'an unknown slug still reads as words', 'Some Thing' === wpss_get_payment_method_label( 'some_thing' ) );

$method = new ReflectionMethod( NotificationService::class, 'dispute_reason_label' );
$method->setAccessible( true );
$service = new NotificationService();
$key     = (string) array_key_first( wpss_get_dispute_reasons() );
$check( 'a dispute reason key becomes its label', wpss_get_dispute_reasons()[ $key ] === $method->invoke( $service, $key ) );
$check( 'an unknown reason key is shown as it is', 'zz_unknown' === $method->invoke( $service, 'zz_unknown' ) );

exit( $fails ? 1 : 0 );
