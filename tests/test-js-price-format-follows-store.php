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
// 2.0.0: one browser formatter, wpssMoney.format(), localized once on the shared UI bundle (Basecamp 10551474179).
$check( 'frontend.js formats with the shared wpssMoney.format', false !== strpos( $js, 'wpssMoney.format(' ) && false === strpos( $js, 'wpssData.currencySymbol' ) && false === strpos( $js, 'currencyFormat' ) );
$check( 'the admin and the storefront get the same config, from the one shared bundle', false !== strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Assets/ScriptRegistry.php' ), "'wpssMoneyConfig', wpss_money_js_config()" ) && false !== strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/admin.js' ), 'wpssMoney.format(' ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
