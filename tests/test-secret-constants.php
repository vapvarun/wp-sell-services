<?php
/**
 * Gateway secrets can live in wp-config.php instead of wp_options (Basecamp 10421889741).
 *
 * Run: wp eval-file tests/test-secret-constants.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$check( 'the constant lookup exists', function_exists( 'wpss_secret_constant' ) );
if ( ! function_exists( 'wpss_secret_constant' ) ) {
	echo "FAILED: {$fails}\n";
	return;
}

global $wpdb;
$raw = static function () use ( $wpdb ) {
	wp_cache_delete( 'wpss_stripe_settings', 'options' );
	wp_cache_delete( 'alloptions', 'options' );
	$v = maybe_unserialize( $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'wpss_stripe_settings'" ) );
	return is_array( $v ) ? $v : array();
};

$backup = $raw();
$map    = static function ( $m ) {
	$m['wpss_stripe_settings']['test_secret_key'] = 'WPSS_QA_STRIPE_TEST_SECRET';
	return $m;
};

// Saved value, no constant.
update_option( 'wpss_stripe_settings', array_merge( $backup, array( 'test_secret_key' => 'sk_test_from_db' ) ) );
add_filter( 'wpss_secret_constants', $map );
$check( 'mapped but undefined: the saved value is used', 'sk_test_from_db' === get_option( 'wpss_stripe_settings' )['test_secret_key'] );

define( 'WPSS_QA_STRIPE_TEST_SECRET', 'sk_test_from_config' );
$check( 'constant defined: every get_option reader sees it', 'sk_test_from_config' === get_option( 'wpss_stripe_settings' )['test_secret_key'] );
$check( 'other fields are left alone', ( $backup['live_secret_key'] ?? '' ) === ( get_option( 'wpss_stripe_settings' )['live_secret_key'] ?? '' ) );

// The gateway itself, through its own private reader.
$gw = new \WPSellServices\Integrations\Stripe\StripeGateway();
$rs = new ReflectionProperty( $gw, 'settings' );
$rs->setAccessible( true );
$s              = (array) $rs->getValue( $gw );
$s['test_mode'] = '1';
$rs->setValue( $gw, $s );
$rm = new ReflectionMethod( $gw, 'get_secret_key' );
$rm->setAccessible( true );
$check( 'the Stripe gateway charges with the constant', 'sk_test_from_config' === $rm->invoke( $gw ) );

// Saving the settings form (masked field submits empty) must not write the constant.
update_option( 'wpss_stripe_settings', $gw->sanitize_settings( array_merge( $backup, array( 'test_secret_key' => '' ) ) ) );
$check( 'a save stores the covered field empty, never the constant', '' === ( $raw()['test_secret_key'] ?? 'missing' ) );
update_option( 'wpss_stripe_settings', $gw->sanitize_settings( array_merge( $backup, array( 'test_secret_key' => 'sk_typed' ) ) ) );
$check( 'a typed value is not stored while the constant rules', '' === ( $raw()['test_secret_key'] ?? 'missing' ) );
$check( 'readers still get the constant after the save', 'sk_test_from_config' === get_option( 'wpss_stripe_settings' )['test_secret_key'] );

// The field shows it is locked and never prints the value.
ob_start();
( new \WPSellServices\Admin\Settings() )->render_secret_field( array( 'option_name' => 'wpss_stripe_settings', 'field' => 'test_secret_key', 'label' => 'Test secret' ) );
$html = (string) ob_get_clean();
$check( 'field is disabled and names the constant', str_contains( $html, 'disabled' ) && str_contains( $html, 'WPSS_QA_STRIPE_TEST_SECRET' ) && ! str_contains( $html, 'name="wpss_stripe_settings' ) );
$check( 'field never prints the secret', ! str_contains( $html, 'sk_test_from_config' ) );

// Default map covers the card's secrets.
remove_filter( 'wpss_secret_constants', $map );
$def = wpss_secret_constants();
$check( 'default map: Stripe, PayPal and realtime secrets', isset( $def['wpss_stripe_settings']['live_secret_key'], $def['wpss_stripe_settings']['webhook_secret'], $def['wpss_paypal_settings']['live_client_secret'], $def['wpss_realtime_settings']['secret'] ) );

update_option( 'wpss_stripe_settings', $backup );
$check( 'cleanup restored the saved settings', $backup === $raw() );
echo $fails ? "FAILED: {$fails}\n" : "OK\n";
