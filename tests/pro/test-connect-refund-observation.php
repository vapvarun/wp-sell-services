<?php
/**
 * Connect refund observation contract.
 *
 * A refund issued from the Stripe dashboard arrives as charge.refunded. Whether
 * it also pulled the vendor's share back out of their connected account decides
 * whether the wallet must carry the debt — and that outcome must be OBSERVED,
 * never assumed from whatever the payload happened to serialise.
 *
 * Webhook bodies are serialised at the ACCOUNT's own default API version, which
 * the platform does not set. Charges on 2022-11-15 and later omit `refunds`
 * unless expanded, which is what every account created since defaults to.
 *
 *   1. refunds present, no transfer_reversal  -> wallet debited exactly once.
 *   2. refunds ABSENT                         -> fetched from Stripe, debited once.
 *   3. transfer_reversal present              -> wallet left alone.
 *   4. redelivery of any of the above         -> no second debit.
 *   5. the fetch fails                        -> debt recorded, and logged.
 *
 * Every Stripe read is stubbed through `pre_http_request`; nothing leaves the
 * machine. Seeds its own vendor, orders and ledger rows, and removes them.
 *
 * Run: wp eval-file tests/pro/test-connect-refund-observation.php
 *
 * @package WPSellServices
 */

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

global $wpdb;

echo "\nConnect refund observation contract\n\n";

$orders_t = $wpdb->prefix . 'wpss_orders';
$txn_t    = $wpdb->prefix . 'wpss_wallet_transactions';

$service = $wpdb->get_row( "SELECT ID, post_author FROM {$wpdb->posts} WHERE post_type = 'wpss_service' AND post_status = 'publish' AND post_author <> 1 ORDER BY ID LIMIT 1" );
if ( ! $service ) {
	echo "  SKIP  no published service by a non-admin vendor on this site\n";
	return;
}
$service_id = (int) $service->ID;
wp_set_current_user( 1 );

$vendor_id = (int) wp_insert_user(
	array(
		'user_login' => 'fu2_vendor_' . wp_rand( 1000, 9999 ),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'fu2_vendor_' . wp_rand( 1000, 9999 ) . '@example.test',
		'role'       => 'subscriber',
	)
);

// Stripe must look configured or StripeClient short-circuits before the stub.
$stripe_backup = get_option( 'wpss_stripe_settings', array() );
update_option(
	'wpss_stripe_settings',
	array_merge(
		is_array( $stripe_backup ) ? $stripe_backup : array(),
		array(
			'test_mode'       => 1,
			'test_secret_key' => 'sk_test_fu2stub',
		)
	)
);

// wpss_log() is a no-op unless debugging is on; case 5 asserts on its output.
$advanced_backup = get_option( 'wpss_advanced', array() );
update_option( 'wpss_advanced', array_merge( is_array( $advanced_backup ) ? $advanced_backup : array(), array( 'enable_debug_mode' => 1 ) ) );

$order_ids = array();

/**
 * Seed a completed, Connect-settled order with its credit and offset rows.
 *
 * @param int    $vendor_id  Vendor.
 * @param int    $service_id Service.
 * @param string $pi         PaymentIntent id.
 * @return int Order id.
 */
function wpss_fu2_seed_order( int $vendor_id, int $service_id, string $pi ): int {
	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert(
		$wpdb->prefix . 'wpss_orders',
		array(
			'order_number'        => 'FU2-' . wp_rand( 10000, 99999 ),
			'customer_id'         => 1,
			'vendor_id'           => $vendor_id,
			'service_id'          => $service_id,
			'package_id'          => 0,
			'subtotal'            => 100,
			'total'               => 100,
			'currency'            => 'USD',
			'status'              => 'completed',
			'payment_method'      => 'stripe',
			'payment_status'      => 'paid',
			'transaction_id'      => $pi,
			'connect_transfer_id' => 'acct_fu2stub',
			'commission_rate'     => 20,
			'platform_fee'        => 20,
			'vendor_earnings'     => 80,
			'created_at'          => $now,
			'updated_at'          => $now,
			'paid_at'             => $now,
			'completed_at'        => $now,
		)
	);
	$order_id = (int) $wpdb->insert_id;
	$txn      = $wpdb->prefix . 'wpss_wallet_transactions';
	$wpdb->insert( $txn, array( 'user_id' => $vendor_id, 'type' => 'order_earning', 'amount' => 80, 'balance_after' => 0, 'currency' => 'USD', 'description' => 'fu2 credit', 'reference_type' => 'order', 'reference_id' => $order_id, 'status' => 'completed', 'created_at' => $now ) );
	$wpdb->insert( $txn, array( 'user_id' => $vendor_id, 'type' => 'connect_transfer', 'amount' => 80, 'balance_after' => 0, 'currency' => 'USD', 'description' => 'fu2 offset', 'reference_type' => 'connect_transfer', 'reference_id' => $order_id, 'status' => 'completed', 'created_at' => $now ) );
	return $order_id;
}

/**
 * Build a charge.refunded payload.
 *
 * @param string      $pi       PaymentIntent id.
 * @param string|null $reversal Transfer reversal id, or null when not reversed.
 * @param bool        $expanded Whether the charge carries its `refunds` list.
 * @return array
 */
function wpss_fu2_payload( string $pi, ?string $reversal, bool $expanded ): array {
	$charge = array(
		'id'              => 'ch_' . substr( $pi, 3 ),
		'payment_intent'  => $pi,
		'currency'        => 'usd',
		'amount_refunded' => 10000,
	);

	// Accounts on Stripe API 2022-11-15+ serialise the charge WITHOUT this key.
	if ( $expanded ) {
		$charge['refunds'] = array( 'data' => array( array( 'id' => 're_fu2', 'transfer_reversal' => $reversal ) ) );
	}

	return array(
		'type' => 'charge.refunded',
		'data' => array( 'object' => $charge ),
	);
}

/**
 * Stub GET /v1/refunds. Returns a WP_Error when $reversal is false.
 *
 * @param string|null|false $reversal Reversal id, null for none, false to fail the request.
 * @return callable Filter callback, for remove_filter().
 */
function wpss_fu2_stub_refunds( $reversal ): callable {
	$stub = static function ( $pre, $args, $url ) use ( $reversal ) {
		if ( false === strpos( (string) $url, 'api.stripe.com/v1/refunds' ) ) {
			return $pre;
		}
		if ( false === $reversal ) {
			return new WP_Error( 'http_request_failed', 'fu2 stubbed network failure' );
		}
		return array(
			'headers'  => array(),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'body'     => wp_json_encode( array( 'object' => 'list', 'data' => array( array( 'id' => 're_fu2', 'transfer_reversal' => $reversal ) ) ) ),
		);
	};
	add_filter( 'pre_http_request', $stub, 10, 3 );
	return $stub;
}

$gateway    = new \WPSellServices\Integrations\Stripe\StripeGateway();
$reversals  = static fn( int $order_id ) => (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}wpss_wallet_transactions WHERE reference_id = %d AND type = 'order_reversal'", $order_id ) );
$status_of  = static fn( int $order_id ) => (string) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT status FROM {$GLOBALS['wpdb']->prefix}wpss_orders WHERE id = %d", $order_id ) );

// Any Stripe call other than the refunds read must not reach the network.
$deny_other = static function ( $pre, $args, $url ) {
	if ( false !== strpos( (string) $url, 'api.stripe.com' ) && false === strpos( (string) $url, '/v1/refunds' ) ) {
		return new WP_Error( 'http_request_failed', 'fu2: blocked an unexpected Stripe call to ' . $url );
	}
	return $pre;
};
add_filter( 'pre_http_request', $deny_other, 5, 3 );

// ---------------------------------------------------------------------------
// 1. refunds present, no reversal -> debit once, replay adds nothing.
// ---------------------------------------------------------------------------
echo "1. Payload carries its refunds\n";

$pi              = 'pi_fu2present' . wp_rand( 1000, 9999 );
$order_ids[]     = $present = wpss_fu2_seed_order( $vendor_id, $service_id, $pi );
$gateway->handle_webhook( wpss_fu2_payload( $pi, null, true ) );
$after_present   = $reversals( $present );
$gateway->handle_webhook( wpss_fu2_payload( $pi, null, true ) );

wpss_t( 'refunded' === $status_of( $present ), 'order moved to refunded (got ' . $status_of( $present ) . ')' );
wpss_t( 1 === $after_present, 'no transfer_reversal in the payload debits the wallet once (got ' . $after_present . ')' );
wpss_t( 1 === $reversals( $present ), 'redelivery writes no second reversal row' );

// ---------------------------------------------------------------------------
// 2. refunds ABSENT -> fetched, debit once, replay adds nothing.
// ---------------------------------------------------------------------------
echo "\n2. Payload omits its refunds (Stripe API 2022-11-15+ default)\n";

$stub          = wpss_fu2_stub_refunds( null );
$pi            = 'pi_fu2absent' . wp_rand( 1000, 9999 );
$order_ids[]   = $absent = wpss_fu2_seed_order( $vendor_id, $service_id, $pi );
$gateway->handle_webhook( wpss_fu2_payload( $pi, null, false ) );
$after_absent  = $reversals( $absent );
$gateway->handle_webhook( wpss_fu2_payload( $pi, null, false ) );
remove_filter( 'pre_http_request', $stub, 10 );

wpss_t( 'refunded' === $status_of( $absent ), 'order moved to refunded (got ' . $status_of( $absent ) . ')' );
wpss_t( 1 === $after_absent, 'outcome fetched from Stripe; wallet debited once (got ' . $after_absent . ')' );
wpss_t( 1 === $reversals( $absent ), 'redelivery writes no second reversal row' );

// ---------------------------------------------------------------------------
// 3. transfer_reversal present -> wallet untouched, both payload shapes.
// ---------------------------------------------------------------------------
echo "\n3. Stripe reclaimed the vendor share\n";

$pi              = 'pi_fu2rev' . wp_rand( 1000, 9999 );
$order_ids[]     = $reversed = wpss_fu2_seed_order( $vendor_id, $service_id, $pi );
$gateway->handle_webhook( wpss_fu2_payload( $pi, 'trr_fu2', true ) );
wpss_t( 0 === $reversals( $reversed ), 'transfer_reversal in the payload leaves the wallet alone' );

$stub               = wpss_fu2_stub_refunds( 'trr_fu2' );
$pi                 = 'pi_fu2revfetch' . wp_rand( 1000, 9999 );
$order_ids[]        = $reversed_fetched = wpss_fu2_seed_order( $vendor_id, $service_id, $pi );
$gateway->handle_webhook( wpss_fu2_payload( $pi, null, false ) );
remove_filter( 'pre_http_request', $stub, 10 );
wpss_t( 0 === $reversals( $reversed_fetched ), 'transfer_reversal found by the fetch leaves the wallet alone' );

// ---------------------------------------------------------------------------
// 4. The fetch fails -> debt recorded, and said so in the log.
// ---------------------------------------------------------------------------
echo "\n4. The refunds fetch fails\n";

$log_file    = wp_tempnam( 'fu2-log' );
$log_backup  = ini_get( 'error_log' );
ini_set( 'error_log', $log_file ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- test harness captures wpss_log() output.

$stub        = wpss_fu2_stub_refunds( false );
$pi          = 'pi_fu2fail' . wp_rand( 1000, 9999 );
$order_ids[] = $failed = wpss_fu2_seed_order( $vendor_id, $service_id, $pi );
$gateway->handle_webhook( wpss_fu2_payload( $pi, null, false ) );
remove_filter( 'pre_http_request', $stub, 10 );

ini_set( 'error_log', is_string( $log_backup ) ? $log_backup : '' ); // phpcs:ignore WordPress.PHP.IniSet.Risky -- restores the harness setting.
$log = (string) file_get_contents( $log_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- LOCAL temp file.
@unlink( $log_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

wpss_t( 1 === $reversals( $failed ), 'an unobservable outcome records the debt rather than skipping (got ' . $reversals( $failed ) . ')' );
wpss_t( false !== strpos( $log, 'could not read the refunds for charge' ), 'the failed fetch is logged' );
wpss_t( false !== strpos( $log, 'clawback outcome could not be observed' ), 'the recorded debt is logged with its reason' );

remove_filter( 'pre_http_request', $deny_other, 5 );

// ---------------------------------------------------------------------------
// Cleanup.
// ---------------------------------------------------------------------------
foreach ( $order_ids as $id ) {
	$wpdb->delete( $orders_t, array( 'id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $id ) );
}
$wpdb->delete( $txn_t, array( 'user_id' => $vendor_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor_id ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vendor_id );

update_option( 'wpss_stripe_settings', $stripe_backup );
update_option( 'wpss_advanced', $advanced_backup );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
