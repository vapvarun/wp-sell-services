<?php
/**
 * Razorpay secrets can live in wp-config.php, the same way as Stripe and PayPal.
 *
 * Pro adds wpss_razorpay_settings to the wpss_secret_constants map. Defined, the
 * constant wins at every reader and a save stores the field empty.
 *
 * Run: wp eval-file tests/test-razorpay-secret-constants.php
 *
 * Restores the saved Razorpay settings afterwards.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( \WPSellServicesPro\Integrations\Razorpay\RazorpayGateway::class ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

global $wpdb;
$option = 'wpss_razorpay_settings';
$raw    = static function () use ( $wpdb, $option ) {
	wp_cache_delete( $option, 'options' );
	wp_cache_delete( 'alloptions', 'options' );
	$v = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) ) );
	return is_array( $v ) ? $v : array();
};

$map = wpss_secret_constants()[ $option ] ?? array();
foreach ( array( 'test_key_secret', 'live_key_secret', 'webhook_secret' ) as $field ) {
	$check( "the map covers Razorpay $field", isset( $map[ $field ] ) );
}

$before = get_option( $option, null );

// A saved value first, so the constant is seen replacing it.
update_option( $option, array_merge( $raw(), array( 'live_key_secret' => 'saved-in-db' ) ) );

define( 'WPSS_RAZORPAY_LIVE_KEY_SECRET', 'from-wp-config' );

$check( 'the constant wins at get_option', 'from-wp-config' === ( get_option( $option )['live_key_secret'] ?? '' ) );

$gateway = new \WPSellServicesPro\Integrations\Razorpay\RazorpayGateway();
$settings = ( new ReflectionMethod( $gateway, 'get_settings' ) )->invoke( $gateway );
$check( "the gateway's own settings read the constant", 'from-wp-config' === ( $settings['live_key_secret'] ?? '' ) );

update_option( $option, array_merge( get_option( $option ), array( 'live_key_secret' => 'typed-on-save', 'test_key_secret' => 'test-typed' ) ) );
$check( 'a save stores the covered field empty', '' === ( $raw()['live_key_secret'] ?? 'missing' ), (string) ( $raw()['live_key_secret'] ?? 'missing' ) );
$check( 'a field with no constant still saves', 'test-typed' === ( $raw()['test_key_secret'] ?? '' ) );

ob_start();
( new \WPSellServices\Admin\Settings() )->render_secret_field( array( 'option_name' => $option, 'field' => 'live_key_secret', 'label' => 'Live Key Secret' ) );
$html = (string) ob_get_clean();
$check( 'the settings field locks and names the constant', false !== strpos( $html, 'disabled' ) && false !== strpos( $html, 'WPSS_RAZORPAY_LIVE_KEY_SECRET' ) );
$check( 'the field never prints the secret', false === strpos( $html, 'from-wp-config' ) );

// Put back what was there, past the save filter.
if ( null === $before ) {
	delete_option( $option );
} else {
	$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $before ) ), array( 'option_name' => $option ) );
	wp_cache_delete( $option, 'options' );
	wp_cache_delete( 'alloptions', 'options' );
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
