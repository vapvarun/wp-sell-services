<?php
/**
 * A withdrawal always knows where the money goes: one payout profile per vendor.
 *
 * Run: wp eval-file tests/test-payout-profile-contract.php
 *
 * Basecamp 10336467884 (owner decision 2026-09-24: one canonical payout
 * profile; a withdrawal is refused without a complete one). POST /withdrawals
 * accepted no details, empty details and a method "bogus", so the admin was
 * handed payouts with nowhere to send them.
 *
 * Throwaway vendor; every row is removed at the end.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\EarningsService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
add_filter( 'pre_wp_mail', '__return_true' );

$vendor = wp_insert_user( array( 'user_login' => 'dev_f1_pp_vendor', 'user_pass' => wp_generate_password(), 'user_email' => 'dev_f1_pp_vendor@example.test', 'role' => 'wpss_vendor' ) );
$wpdb->insert( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor, 'display_name' => 'dev_f1', 'status' => 'active' ) );
wpss_insert_ledger_row( array( 'user_id' => $vendor, 'type' => 'order_earning', 'amount' => 500, 'balance_after' => 500, 'currency' => wpss_get_currency(), 'description' => 'dev_f1 seed', 'reference_type' => 'dev_f1_seed', 'reference_id' => $vendor, 'status' => 'completed', 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS ) ) );

$post = static function ( array $body ) {
	$request = new WP_REST_Request( 'POST', '/wpss/v1/withdrawals' );
	foreach ( $body as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
};
$code = static fn( $response ) => $response->get_status() . ' ' . ( $response->get_data()['code'] ?? '' );

try {
	wp_set_current_user( $vendor );
	$min = max( 10, EarningsService::get_min_withdrawal_amount() );

	$r = $post( array( 'amount' => $min, 'method' => 'paypal' ) );
	$check( sprintf( 'no details and no profile is refused (%s)', $code( $r ) ), 400 === $r->get_status() && 'wpss_payout_details_missing' === ( $r->get_data()['code'] ?? '' ) );

	$r = $post( array( 'amount' => $min, 'method' => 'bogus', 'details' => array( 'email' => 'a@b.test' ) ) );
	$check( sprintf( 'an unknown method is refused (%s)', $code( $r ) ), 400 === $r->get_status() && 'wpss_invalid_payout_method' === ( $r->get_data()['code'] ?? '' ) );

	$r = $post( array( 'amount' => $min, 'method' => 'paypal', 'details' => array( 'email' => 'not-an-email' ) ) );
	$check( sprintf( 'an invalid PayPal email is refused (%s)', $code( $r ) ), 400 === $r->get_status() );

	$r = $post( array( 'amount' => $min, 'method' => 'bank_transfer', 'details' => array( 'bank_name' => 'dev_f1 bank' ) ) );
	$check( sprintf( 'a bank transfer without holder and account is refused (%s)', $code( $r ) ), 400 === $r->get_status() && 'wpss_payout_details_missing' === ( $r->get_data()['code'] ?? '' ) );

	$r = $post( array( 'amount' => $min, 'method' => 'paypal', 'details' => array( 'email' => 'dev_f1_payout@example.test', 'junk' => 'x' ) ) );
	$check( sprintf( 'complete PayPal details are accepted (%s)', $code( $r ) ), 201 === $r->get_status() );
	$first = (int) ( $r->get_data()['id'] ?? 0 );

	$stored = get_user_meta( $vendor, 'wpss_payout_details', true );
	$check( 'the profile is stored encrypted, not as a plain array', is_string( $stored ) && false === strpos( $stored, 'dev_f1_payout' ) );
	$profile = EarningsService::get_payout_profile( $vendor );
	$check( 'the profile reads back cleaned (email only, junk dropped)', 'paypal' === $profile['method'] && array( 'email' => 'dev_f1_payout@example.test' ) === $profile['details'] );

	$row = json_decode( wpss_decrypt_secret( (string) $wpdb->get_var( $wpdb->prepare( "SELECT details FROM {$wpdb->prefix}wpss_withdrawals WHERE id = %d", $first ) ) ), true ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( 'the withdrawal carries the destination the admin sees', 'PayPal · dev_f1_payout@example.test' === EarningsService::format_payout_destination( 'paypal', (array) $row, false ) );

	( new EarningsService() )->cancel_withdrawal( $first, $vendor );
	$r = $post( array( 'amount' => $min, 'method' => 'paypal' ) );
	$check( sprintf( 'with a saved profile, a request without details uses it (%s)', $code( $r ) ), 201 === $r->get_status() );

	$check( 'vendor-facing destination masks the account number', 'Bank Transfer · Jo · Big Bank · ***6789' === EarningsService::format_payout_destination( 'bank_transfer', array( 'account_name' => 'Jo', 'bank_name' => 'Big Bank', 'account_number' => '123456789' ) ) );

	// A profile saved as a plain array before 1.8.0 still reads.
	update_user_meta( $vendor, 'wpss_payout_details', array( 'email' => 'legacy@example.test' ) );
	$check( 'a pre-1.8.0 plain profile still reads', 'legacy@example.test' === ( EarningsService::get_payout_profile( $vendor )['details']['email'] ?? '' ) );

	// Pro's PayPal payouts read and write the same profile.
	if ( class_exists( '\WPSellServicesPro\PayPalPayouts\VendorPayoutProfileService' ) ) {
		$pro = new \WPSellServicesPro\PayPalPayouts\VendorPayoutProfileService();
		$pro->set_paypal_email( $vendor, 'pro_set@example.test' );
		$check( 'Pro set_paypal_email saves the one profile', 'pro_set@example.test' === ( EarningsService::get_payout_profile( $vendor )['details']['email'] ?? '' ) && '' === get_user_meta( $vendor, '_wpss_paypal_email', true ) );
		$check( 'Pro get_paypal_email reads it', 'pro_set@example.test' === $pro->get_paypal_email( $vendor ) );
		$check( 'Pro lists the vendor for PayPal payouts', in_array( $vendor, array_column( $pro->get_vendors_with_paypal(), 'vendor_id' ), true ) );
	} else {
		echo "SKIP  Pro PayPal profile (Pro not active)\n";
	}
} finally {
	wp_set_current_user( 0 );
	remove_filter( 'pre_wp_mail', '__return_true' );
	$wpdb->delete( $wpdb->prefix . 'wpss_withdrawals', array( 'vendor_id' => $vendor ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_wallet_transactions', array( 'user_id' => $vendor ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE user_id = %d", $vendor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
