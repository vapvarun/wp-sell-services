<?php
/**
 * PayPal gets each currency's own decimals on create and refund.
 *
 * Run: wp eval-file tests/test-paypal-currency-decimals.php
 *
 * Every amount went out as number_format( $amount, 2 ), and PayPal refuses
 * "1000.00" in JPY, HUF and TWD (DECIMAL_PRECISION), so those stores could not
 * be paid or refunded. PayPal is stubbed; the live sandbox is not exercised.
 *
 * @package WPSellServices
 */

use WPSellServices\Integrations\PayPal\PayPalGateway;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$original = get_option( 'wpss_paypal_settings' );
update_option( 'wpss_paypal_settings', array( 'enabled' => 1, 'sandbox_mode' => 1, 'sandbox_client_id' => 'decimals-client', 'sandbox_client_secret' => 'decimals-secret' ) );

$sent            = array();
$capture_currency = 'JPY';
$stub            = static function ( $pre, $args, $url ) use ( &$sent, &$capture_currency ) {
	if ( false === strpos( (string) $url, 'paypal.com' ) ) {
		return $pre;
	}
	$resp = static fn( array $body ) => array( 'response' => array( 'code' => 201, 'message' => '' ), 'body' => wp_json_encode( $body ), 'headers' => array(), 'cookies' => array() );
	if ( false !== strpos( $url, 'oauth2/token' ) ) {
		return $resp( array( 'access_token' => 'tok', 'expires_in' => 3600 ) );
	}
	if ( 'GET' === ( $args['method'] ?? '' ) ) {
		return $resp( array( 'amount' => array( 'currency_code' => $capture_currency, 'value' => '1' ) ) );
	}
	$sent[] = json_decode( (string) $args['body'], true );
	return $resp( array( 'id' => 'X1', 'status' => 'CREATED', 'links' => array() ) );
};
add_filter( 'pre_http_request', $stub, 10, 3 );

try {
	$cases = array(
		'JPY' => array( 1500, '1500' ),
		'HUF' => array( 2500, '2500' ),
		'TWD' => array( 300, '300' ),
		'USD' => array( 12.5, '12.50' ),
		'KWD' => array( 1.234, '1.234' ),
	);
	foreach ( $cases as $code => list( $amount, $want ) ) {
		$sent = array();
		( new PayPalGateway() )->create_payment( (float) $amount, $code );
		$got = $sent[0]['purchase_units'][0]['amount']['value'] ?? '(none)';
		$check( "create sends {$code} {$amount} as {$want}", $want === $got, "sent {$got}" );

		$sent             = array();
		$capture_currency = $code;
		( new PayPalGateway() )->process_refund( 'CAP1', (float) $amount );
		$got = $sent[0]['amount']['value'] ?? '(none)';
		$check( "refund sends {$code} {$amount} as {$want}", $want === $got, "sent {$got}" );
	}

	// A fraction PayPal cannot take is refused, never rounded into a different charge.
	$sent = array();
	$r    = ( new PayPalGateway() )->create_payment( 1234.5, 'HUF' );
	$check( 'a HUF amount with a fraction is refused, not rounded', empty( $r['success'] ) && array() === $sent );
} finally {
	remove_filter( 'pre_http_request', $stub, 10 );
	false === $original ? delete_option( 'wpss_paypal_settings' ) : update_option( 'wpss_paypal_settings', $original );
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
