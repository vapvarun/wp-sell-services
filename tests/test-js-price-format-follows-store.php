<?php
/**
 * Prices drawn in JavaScript follow the store's symbol and position (Basecamp 10504406520).
 *
 * Run: wp eval-file tests/test-js-price-format-follows-store.php
 *
 * The proposal milestone total always showed "$" because the script read a
 * key the server never sent, and the format string ignored the owner's
 * "currency position" setting.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$general = get_option( 'wpss_general', null );
$set     = static function ( string $position ) use ( $general ) {
	update_option( 'wpss_general', array_merge( (array) $general, array( 'currency_position' => $position ) ) );
};

$set( 'before' );
$check( 'position before: symbol then number', 0 === strpos( wpss_get_currency_format( 'EUR' ), '€' ) && '%s' === substr( wpss_get_currency_format( 'EUR' ), -2 ), wpss_get_currency_format( 'EUR' ) );
$set( 'after' );
$check( 'position after: number then symbol', '%s€' === wpss_get_currency_format( 'EUR' ), wpss_get_currency_format( 'EUR' ) );

if ( null === $general ) {
	delete_option( 'wpss_general' );
} else {
	update_option( 'wpss_general', $general );
}

$js = (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/frontend.js' );
$check( 'frontend.js formats with the localized currencyFormat', false !== strpos( $js, 'wpssData.currencyFormat' ) && false === strpos( $js, 'wpssData.currencySymbol' ) );
$check( 'the admin script gets the same format as the storefront', false !== strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Admin/Admin.php' ), "'currencyFormat'   => wpss_get_currency_format()" ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
