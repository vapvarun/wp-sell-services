<?php
/**
 * Payout methods are one registry: a method added only through wpss_payout_method_registry is offered, collects
 * its own fields, validates through the same path, and a malformed entry is dropped.
 *
 * Run: wp eval-file tests/test-payout-method-registry.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Services\EarningsService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
$add = static fn( $m ) => $m + array(
	'upi'  => array(
		'label'  => 'UPI',
		'fields' => array( 'vpa' => array( 'label' => 'UPI id', 'type' => 'text', 'required' => true ) ),
	),
	'bare' => array( 'label' => 'Bare' ),
	'junk' => 'not an array',
	7      => array( 'label' => 'Numeric' ),
);
add_filter( 'wpss_payout_method_registry', $add );

$check( 'a method added only by the filter is offered', 'UPI' === ( EarningsService::get_withdrawal_methods()['upi'] ?? '' ) );
$check( 'it declares its own fields', array_keys( EarningsService::get_payout_fields( 'upi' ) ) === array( 'vpa' ) );
$check( 'a method with no fields gets one free-text field', array_keys( EarningsService::get_payout_fields( 'bare' ) ) === array( 'details' ) );
$check( 'a malformed entry is dropped', ! isset( EarningsService::get_withdrawal_methods()['junk'] ) && ! isset( EarningsService::get_withdrawal_methods()[7] ) );
$check( 'built-in paypal still collects an email', isset( EarningsService::get_payout_fields( 'paypal' )['email'] ) );
$check( 'an unknown method collects nothing', array() === EarningsService::get_payout_fields( 'nope' ) );

remove_filter( 'wpss_payout_method_registry', $add );
$check( 'without the filter the extra method is gone', ! isset( EarningsService::get_withdrawal_methods()['upi'] ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
