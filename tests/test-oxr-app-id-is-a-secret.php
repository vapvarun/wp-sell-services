<?php
/**
 * The OpenExchangeRates App ID is kept like every other credential.
 *
 * It was the one key printed into the settings page in clear text and stored as
 * plaintext. It now renders through the masked secret field, is stored encrypted,
 * an empty submit keeps it, and the rate service still reads the plain key.
 *
 * Run: wp eval-file tests/test-oxr-app-id-is-a-secret.php (Pro active).
 *
 * Restores the option it changes.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

if ( ! class_exists( '\WPSellServicesPro\Currency\DisplayCurrencyManager' ) ) {
	echo "SKIP  Pro display currency not loaded\nOK\n";
	return;
}

use WPSellServicesPro\Currency\DisplayCurrencyManager;
use WPSellServicesPro\Currency\RateService;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$before  = get_option( RateService::OPTION, null );
$key     = 'qa' . wp_generate_password( 24, false );
$manager = new DisplayCurrencyManager();
// Through the real option write, so the registered sanitize callback runs.
add_filter( 'sanitize_option_' . RateService::OPTION, array( $manager, 'sanitize' ) );

try {
	update_option( RateService::OPTION, array( 'enabled' => '1', 'app_id' => $key, 'currencies' => 'EUR' ) );
	$stored = (string) ( get_option( RateService::OPTION )['app_id'] ?? '' );
	$check( 'the key is stored encrypted, not as typed', '' !== $stored && $key !== $stored && 0 === strpos( $stored, 'enc:' ), $stored );
	$check( 'the rate service reads the plain key', $key === ( new RateService() )->get_app_id() );

	update_option( RateService::OPTION, array( 'enabled' => '1', 'app_id' => '', 'currencies' => 'EUR,GBP' ) );
	$check( 'an empty submit keeps the saved key', $key === ( new RateService() )->get_app_id() );

	wp_set_current_user( (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] );
	ob_start();
	$manager->render_settings_ui();
	$html = (string) ob_get_clean();
	$check( 'the settings card renders the App ID field', false !== strpos( $html, 'wpss-oxr-app-id' ) );
	$check( 'the settings page never prints the key', false === strpos( $html, $key ) && false === strpos( $html, $stored ) );

	// A key saved before 1.9.2 is plaintext and keeps working.
	remove_filter( 'sanitize_option_' . RateService::OPTION, array( $manager, 'sanitize' ) );
	update_option( RateService::OPTION, array( 'enabled' => '1', 'app_id' => 'legacyplain123', 'currencies' => array( 'EUR' ) ) );
	$check( 'a legacy plaintext key still reads', 'legacyplain123' === ( new RateService() )->get_app_id() );
} finally {
	remove_filter( 'sanitize_option_' . RateService::OPTION, array( $manager, 'sanitize' ) );
	null === $before ? delete_option( RateService::OPTION ) : update_option( RateService::OPTION, $before );
	wp_set_current_user( 0 );
}

echo $fails ? "FAILED {$fails}\n" : "OK\n";
