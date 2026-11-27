<?php
/**
 * Payout rails on one registry (Basecamp 10551488386, payment modules phase 3, T11).
 *
 * Free's wpss_payout_method_registry carries an optional `render` card and `withdrawal` => false;
 * Pro registers Stripe Connect and PayPal Payouts there instead of hard-coding two rails. Connect is
 * offered only while Stripe checkout is on; PayPal Payouts has one switch; the features screen reads
 * the same rule; the vendor's chosen payout method decides which PayPal email, if any, gets paid.
 *
 * Run: wp eval-file tests/pro/test-payout-registry.php
 *
 * @package WPSellServices
 */

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

use WPSellServices\Services\EarningsService;
use WPSellServicesPro\Payouts\PayoutMethodsCoordinator;
use WPSellServicesPro\PayPalPayouts\PayPalPayoutsApiClient;
use WPSellServicesPro\PayPalPayouts\VendorPayoutProfileService;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( PayoutMethodsCoordinator::class ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

$keep = array();
foreach ( array( 'wpss_pro_stripe_connect_enabled', 'wpss_pro_paypal_payouts_enabled', PayPalPayoutsApiClient::OPTION_KEY, 'wpss_stripe_settings' ) as $opt ) {
	$keep[ $opt ] = get_option( $opt, null );
}
$put = static fn( string $opt, $value ) => null === $value ? delete_option( $opt ) : update_option( $opt, $value );

// 1. Free: the two optional keys.
add_filter(
	'wpss_payout_method_registry',
	$qa = static fn( $m ) => $m + array(
		'qa_rail' => array(
			'label'      => 'QA rail',
			'withdrawal' => false,
			'render'     => static function ( int $user_id ) {
				echo 'qa-card-' . (int) $user_id;
			},
		),
	)
);
$check( 'a rail-only method is not a withdrawal choice', ! isset( EarningsService::get_withdrawal_methods()['qa_rail'] ) && isset( EarningsService::get_payout_methods()['qa_rail'] ) );
$check( '  a withdrawal cannot be saved against it', is_wp_error( EarningsService::validate_payout_details( 'qa_rail', array( 'details' => 'x' ) ) ) );
$check( '  its card is listed for the vendor', isset( EarningsService::get_payout_rails()['qa_rail'] ) );
remove_filter( 'wpss_payout_method_registry', $qa );

// 2. Stripe Connect needs Stripe checkout on.
$swap = static function ( bool $stripe_on ) {
	update_option( 'wpss_stripe_settings', array_merge( (array) get_option( 'wpss_stripe_settings', array() ), array( 'enabled' => $stripe_on ? '1' : '', 'test_mode' => '1', 'test_secret_key' => 'sk_test_qa', 'test_publishable_key' => 'pk_test_qa' ) ) );
	$list = new ReflectionProperty( wpss(), 'payment_gateways' );
	$list->setValue( wpss(), array_merge( $list->getValue( wpss() ), array( 'stripe' => new \WPSellServices\Integrations\Stripe\StripeGateway() ) ) );
};
$list = new ReflectionProperty( wpss(), 'payment_gateways' );
$was = $list->getValue( wpss() );
update_option( 'wpss_pro_stripe_connect_enabled', 1 );
$swap( false );
$check( 'Connect switched on but Stripe checkout off: not offered', ! PayoutMethodsCoordinator::connect_active() && ! isset( EarningsService::get_payout_methods()['stripe_connect'] ) );
$swap( true );
$methods = EarningsService::get_payout_methods();
$check( '  Stripe checkout on: Connect registers a card', PayoutMethodsCoordinator::connect_active() && isset( EarningsService::get_payout_rails()['stripe_connect'] ) );
$check( '  and it is not a withdrawal choice', false === ( $methods['stripe_connect']['withdrawal'] ?? null ) && ! isset( EarningsService::get_withdrawal_methods()['stripe_connect'] ) );
update_option( 'wpss_pro_stripe_connect_enabled', 0 );
$check( 'Connect switched off: not offered', ! isset( EarningsService::get_payout_methods()['stripe_connect'] ) );
$list->setValue( wpss(), $was );

// 3. PayPal Payouts: one switch, the old second switch folded in once.
update_option( PayPalPayoutsApiClient::OPTION_KEY, array( 'enabled' => false ) );
update_option( 'wpss_pro_paypal_payouts_enabled', 1 );
$check( 'a site that had the old "show to vendors" switch on keeps PayPal Payouts on', PayoutMethodsCoordinator::paypal_active() && ! empty( get_option( PayPalPayoutsApiClient::OPTION_KEY )['enabled'] ) );
$check( '  and the old switch is gone', null === get_option( 'wpss_pro_paypal_payouts_enabled', null ) );
$check( '  PayPal carries the email card', isset( EarningsService::get_payout_rails()['paypal'] ) && isset( EarningsService::get_withdrawal_methods()['paypal'] ) );
update_option( PayPalPayoutsApiClient::OPTION_KEY, array( 'enabled' => false ) );
$check( 'PayPal Payouts off: no card', ! PayoutMethodsCoordinator::paypal_active() && ! isset( EarningsService::get_payout_rails()['paypal'] ) );

$pro = static fn( string $f ) => (string) file_get_contents( WPSS_PRO_PLUGIN_DIR . $f ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$check( 'the settings screen has one PayPal Payouts switch', ! str_contains( $pro( 'src/PayPalPayouts/PayoutsSettingsRenderer.php' ), 'rail_enabled' ) && ! str_contains( $pro( 'assets/js/admin.js' ), 'rail_enabled' ) );
$check( 'the features screen uses the same "active" rule', str_contains( $pro( 'src/Core/Pro.php' ), 'PayoutMethodsCoordinator::connect_active()' ) && str_contains( $pro( 'src/Core/Pro.php' ), 'PayoutMethodsCoordinator::paypal_active()' ) );
$check( 'the coordinator hard-codes no rail and the unused interface is gone', ! preg_match( '/is_stripe_rail_enabled|is_paypal_rail_enabled|any_rail_enabled/', $pro( 'src/Payouts/PayoutMethodsCoordinator.php' ) ) && ! file_exists( WPSS_PRO_PLUGIN_DIR . 'src/PayPalPayouts/Contracts/PayoutsProviderInterface.php' ) );

// 4. One PayPal email: the vendor's chosen method decides.
$vendor = (int) ( get_users( array( 'role' => 'subscriber', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 0 );
if ( $vendor ) {
	$meta = array();
	foreach ( array( 'wpss_payout_method', 'wpss_payout_details', VendorPayoutProfileService::META_KEY ) as $k ) {
		$meta[ $k ] = get_user_meta( $vendor, $k, true );
	}
	$service = new VendorPayoutProfileService();
	EarningsService::save_payout_profile( $vendor, 'bank_transfer', array( 'account_name' => 'QA', 'bank_name' => 'QA Bank', 'account_number' => '123' ) );
	update_user_meta( $vendor, VendorPayoutProfileService::META_KEY, 'old@example.com' );
	$listed = array_column( $service->get_vendors_with_paypal( 1, 500 ), 'vendor_id' );
	$check( 'a vendor who chose bank transfer is not paid at an old PayPal email', '' === $service->get_paypal_email( $vendor ) && ! in_array( $vendor, array_map( 'intval', $listed ), true ) );

	delete_user_meta( $vendor, 'wpss_payout_method' );
	delete_user_meta( $vendor, 'wpss_payout_details' );
	$listed = array_column( $service->get_vendors_with_paypal( 1, 500 ), 'vendor_id' );
	$check( 'a vendor with only a pre-1.8.0 email is still listed', in_array( $vendor, array_map( 'intval', $listed ), true ) );
	$check( '  their email is read', 'old@example.com' === $service->get_paypal_email( $vendor ) );
	$profile = EarningsService::get_payout_profile( $vendor );
	$check( '  and moved into the one payout profile', 'paypal' === $profile['method'] && 'old@example.com' === ( $profile['details']['email'] ?? '' ) && '' === get_user_meta( $vendor, VendorPayoutProfileService::META_KEY, true ) );

	foreach ( $meta as $k => $v ) {
		'' === $v ? delete_user_meta( $vendor, $k ) : update_user_meta( $vendor, $k, $v );
	}
}

foreach ( $keep as $opt => $value ) {
	$put( $opt, $value );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
