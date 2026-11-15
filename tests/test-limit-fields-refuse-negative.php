<?php
/**
 * Limit fields where 0 means "unlimited" or "off" refuse a negative instead of storing 0.
 *
 * Run: wp eval-file tests/test-limit-fields-refuse-negative.php
 *
 * Max services per vendor (Settings and Setup Wizard) and the Offline gateway
 * auto-cancel hours share wpss_clamp_limit(): too big clamps to the screen's
 * max, negative or non-numeric keeps what is stored.
 *
 * @package WPSellServices
 */

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$check( 'helper: -4 keeps the stored 20', 20 === wpss_clamp_limit( '-4', 0, 100, 20 ) );
$check( 'helper: abc keeps the stored 20', 20 === wpss_clamp_limit( 'abc', 0, 100, 20 ) );
$check( 'helper: 99999 clamps to 100', 100 === wpss_clamp_limit( '99999', 0, 100, 20 ) );
$check( 'helper: 12 stays 12, 0 stays 0', 12 === wpss_clamp_limit( '12', 0, 100, 20 ) && 0 === wpss_clamp_limit( '0', 0, 100, 20 ) );

$was = get_option( 'wpss_vendor', array() );
update_option( 'wpss_vendor', array_merge( (array) $was, array( 'max_services_per_vendor' => 20 ) ) );
$settings = new \WPSellServices\Admin\Settings();
$out      = static fn( $v ) => $settings->sanitize_vendor_settings( array( 'max_services_per_vendor' => $v ) )['max_services_per_vendor'];
$check( 'Settings door: -4 keeps 20, 99999 becomes 100', 20 === $out( '-4' ) && 100 === $out( '99999' ) && 7 === $out( '7' ) );
update_option( 'wpss_vendor', $was );

// Setup wizard door: capture the JSON the AJAX handler sends.
wp_set_current_user( (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] );
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function () {
	throw new \RuntimeException( 'wp_die' );
} );
$wizard = static function ( $value ) {
	$_POST    = array( 'nonce' => wp_create_nonce( 'wpss_wizard_nonce' ), 'step' => 4, 'max_services_per_vendor' => $value );
	$_REQUEST = $_POST;
	ob_start();
	try {
		( new \WPSellServices\Admin\Pages\SetupWizardPage() )->ajax_save_step();
	} catch ( \RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
	}
	return (array) json_decode( (string) ob_get_clean(), true );
};
$was = get_option( 'wpss_vendor', array() );
update_option( 'wpss_vendor', array_merge( (array) $was, array( 'max_services_per_vendor' => 20 ) ) );
$neg = $wizard( '-4' );
$check( 'Wizard door: -4 is refused with a message and nothing is stored', false === ( $neg['success'] ?? null ) && 20 === (int) ( get_option( 'wpss_vendor', array() )['max_services_per_vendor'] ?? -1 ), wp_json_encode( $neg ) );
$big = $wizard( '99999' );
$check( 'Wizard door: 99999 clamps to 100', true === ( $big['success'] ?? null ) && 100 === (int) ( get_option( 'wpss_vendor', array() )['max_services_per_vendor'] ?? -1 ), wp_json_encode( $big ) );
update_option( 'wpss_vendor', $was );

$offline = new \WPSellServices\Integrations\Gateways\OfflineGateway();
$opt     = 'wpss_offline_settings';
$had     = get_option( $opt, null );
update_option( $opt, array( 'auto_hold_hours' => 24 ) );
$hours = static fn( $v ) => $offline->sanitize_settings( array( 'auto_hold_hours' => $v ) )['auto_hold_hours'];
$check( 'Offline auto-cancel: -4 keeps 24, 99999 becomes 720, 0 stays 0', 24 === $hours( '-4' ) && 720 === $hours( '99999' ) && 0 === $hours( '0' ) );
null === $had ? delete_option( $opt ) : update_option( $opt, $had );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
