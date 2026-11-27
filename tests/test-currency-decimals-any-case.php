<?php
/**
 * Currency decimals ignore the case of the code (Basecamp 10551462915).
 *
 * wpss_get_currency_decimals() matched uppercase keys only. Stripe reports 'jpy', so a 1000 JPY charge
 * converted with 2 decimals read as 10 and the order looked underpaid; the same for KRW, BHD, KWD and the rest.
 *
 * Run: wp eval-file tests/test-currency-decimals-any-case.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

foreach ( array( 'jpy' => 0, 'krw' => 0, 'bhd' => 3, 'kwd' => 3, 'usd' => 2, 'Jpy' => 0 ) as $code => $want ) {
	$got = wpss_get_currency_decimals( $code );
	$check( "{$code} has {$want} decimals, the same as " . strtoupper( $code ), $want === $got && wpss_get_currency_decimals( strtoupper( $code ) ) === $got, (string) $got );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
