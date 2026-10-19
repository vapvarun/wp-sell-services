<?php
/**
 * Payout integrity contract.
 *
 * Four invariants, one balance authority:
 *   1. Every checkout intent names its vendor, so Stripe Connect splits a
 *      catalog purchase the way it already split a pay-order.
 *   2. PayPal Payouts pays EarningsService::get_summary() available - pending
 *      manual withdrawals and the clearance window already subtracted - less
 *      in-flight payout items.
 *   3. TeraWallet, WooWallet and MyCred are not offered as balance providers
 *      (the plugin never writes to them); the filter still takes a third
 *      party's provider.
 *   4. A Stripe dashboard refund that did NOT reverse the transfer debits the
 *      vendor wallet exactly once (a replay adds nothing); one that did leaves
 *      the wallet alone.
 *
 * Seeds its own vendor, orders, ledger rows and withdrawal, and removes them.
 *
 * Run: wp eval-file tests/pro/test-payout-integrity.php
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

echo "\nPayout integrity contract\n\n";

$orders_t = $wpdb->prefix . 'wpss_orders';
$txn_t    = $wpdb->prefix . 'wpss_wallet_transactions';
$wd_t     = $wpdb->prefix . 'wpss_withdrawals';
$items_t  = $wpdb->prefix . 'wpss_pro_paypal_payout_items';

// A published service by a vendor who is not the admin, and the admin as buyer.
$service = $wpdb->get_row( "SELECT ID, post_author FROM {$wpdb->posts} WHERE post_type = 'wpss_service' AND post_status = 'publish' AND post_author <> 1 ORDER BY ID LIMIT 1" );
if ( ! $service ) {
	echo "  SKIP  no published service by a non-admin vendor on this site\n";
	return;
}
$service_id = (int) $service->ID;
$author_id  = (int) $service->post_author;
wp_set_current_user( 1 );

// ---------------------------------------------------------------------------
// 1. Intent metadata carries vendor_id; Connect splits on it.
// ---------------------------------------------------------------------------
echo "1. Intent metadata\n";

$intent = ( new \WPSellServices\Checkout\CheckoutIntentService() )->resolve(
	array(
		'service_id' => $service_id,
		'package_id' => 0,
	),
	1
);
wpss_t( ! is_wp_error( $intent ), 'single intent resolves' . ( is_wp_error( $intent ) ? ' (' . $intent->get_error_message() . ')' : '' ) );
wpss_t( ! is_wp_error( $intent ) && (int) ( $intent->metadata['vendor_id'] ?? 0 ) === $author_id, 'single intent metadata carries vendor_id = service author' );

// Single-vendor cart carries vendor_id; a cart spanning vendors carries vendor_ids.
$cart_backup = get_user_meta( 1, '_wpss_cart', true );
update_user_meta( 1, '_wpss_cart', array( 'a' => array( 'service_id' => $service_id, 'package_id' => 0, 'quantity' => 1 ) ) );
$cart_intent = ( new \WPSellServices\Checkout\CheckoutIntentService() )->resolve( array( 'is_multi_checkout' => 1 ), 1 );
wpss_t( ! is_wp_error( $cart_intent ) && (int) ( $cart_intent->metadata['vendor_id'] ?? 0 ) === $author_id, 'single-vendor cart intent carries vendor_id' );

$other = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpss_service' AND post_status = 'publish' AND post_author NOT IN (1, %d) ORDER BY ID LIMIT 1", $author_id ) );
if ( $other ) {
	update_user_meta(
		1,
		'_wpss_cart',
		array(
			'a' => array( 'service_id' => $service_id, 'package_id' => 0, 'quantity' => 1 ),
			'b' => array( 'service_id' => (int) $other, 'package_id' => 0, 'quantity' => 1 ),
		)
	);
	$multi = ( new \WPSellServices\Checkout\CheckoutIntentService() )->resolve( array( 'is_multi_checkout' => 1 ), 1 );
	wpss_t( ! is_wp_error( $multi ) && empty( $multi->metadata['vendor_id'] ) && ! empty( $multi->metadata['vendor_ids'] ), 'multi-vendor cart intent carries vendor_ids and no vendor_id (no split)' );
}
if ( '' === $cart_backup || array() === $cart_backup ) {
	delete_user_meta( 1, '_wpss_cart' );
} else {
	update_user_meta( 1, '_wpss_cart', $cart_backup );
}

// The REST create-intent path names the vendor too. Capture the args the
// gateway would send and short-circuit the HTTP call.
$captured = array();
$capture  = static function ( $params, $order_id, $vendor_id ) use ( &$captured ) {
	$captured = array( 'vendor_id' => $vendor_id, 'params' => $params );
	return $params;
};
add_filter( 'wpss_stripe_payment_intent_args', $capture, 99, 3 );
$no_http = static fn() => array( 'response' => array( 'code' => 401 ), 'body' => '{"error":{"message":"stubbed"}}' );
add_filter( 'pre_http_request', $no_http, 10 );
( new \WPSellServices\Integrations\Stripe\StripeGateway() )->create_payment_intent(
	array(
		'amount'     => 10.0,
		'currency'   => 'USD',
		'service_id' => $service_id,
		'package_id' => 0,
	)
);
remove_filter( 'pre_http_request', $no_http, 10 );
remove_filter( 'wpss_stripe_payment_intent_args', $capture, 99 );
wpss_t( (int) ( $captured['vendor_id'] ?? 0 ) === $author_id, 'REST create-intent path passes vendor_id to wpss_stripe_payment_intent_args' );
wpss_t( (int) ( $captured['params']['metadata']['vendor_id'] ?? 0 ) === $author_id, 'REST create-intent metadata carries vendor_id' );

// Connect splits on that vendor_id, with a stubbed connected account.
$connect_backup = get_option( 'wpss_pro_stripe_connect_enabled' );
update_option( 'wpss_pro_stripe_connect_enabled', 1 );
$accounts = new class() extends \WPSellServicesPro\StripeConnect\ConnectAccountService {
	public function can_accept_payments( int $vendor_id ): bool {
		return true;
	}
	public function get_by_vendor( int $vendor_id ): ?object {
		return (object) array( 'stripe_account_id' => 'acct_p8stub' );
	}
};
$processor = new \WPSellServicesPro\StripeConnect\ConnectPaymentProcessor( $accounts );
$args      = $processor->filter_payment_intent_args(
	array(
		'amount'   => 1000,
		'currency' => 'usd',
		'metadata' => is_wp_error( $intent ) ? array() : $intent->metadata,
	),
	0,
	is_wp_error( $intent ) ? 0 : (int) ( $intent->metadata['vendor_id'] ?? 0 )
);
wpss_t( 'acct_p8stub' === ( $args['transfer_data']['destination'] ?? '' ), 'Connect adds transfer_data for a catalog purchase' );
$args = $processor->filter_payment_intent_args( array( 'amount' => 1000, 'currency' => 'usd', 'metadata' => array( 'vendor_ids' => '3,4' ) ), 0, 0 );
wpss_t( empty( $args['transfer_data'] ), 'Connect keeps a multi-vendor cart on the platform' );
if ( false === $connect_backup ) {
	delete_option( 'wpss_pro_stripe_connect_enabled' );
} else {
	update_option( 'wpss_pro_stripe_connect_enabled', $connect_backup );
}

// ---------------------------------------------------------------------------
// 2. PayPal Payouts eligibility = EarningsService available - in-flight items.
// ---------------------------------------------------------------------------
echo "\n2. PayPal Payouts eligibility\n";

$vendor_id = wp_insert_user(
	array(
		'user_login' => 'p8_vendor_' . wp_rand( 1000, 9999 ),
		'user_pass'  => wp_generate_password(),
		'user_email' => 'p8_vendor_' . wp_rand( 1000, 9999 ) . '@example.test',
		'role'       => 'subscriber',
	)
);
$vendor_id = (int) $vendor_id;
$old       = gmdate( 'Y-m-d H:i:s', time() - 400 * DAY_IN_SECONDS );
$now       = current_time( 'mysql' );

$wpdb->insert( $txn_t, array( 'user_id' => $vendor_id, 'type' => 'order_earning', 'amount' => 200, 'balance_after' => 200, 'currency' => 'USD', 'description' => 'p8 seed', 'reference_type' => 'order', 'reference_id' => 0, 'status' => 'completed', 'created_at' => $old ) );
$wpdb->insert( $txn_t, array( 'user_id' => $vendor_id, 'type' => 'order_earning', 'amount' => 50, 'balance_after' => 250, 'currency' => 'USD', 'description' => 'p8 seed clearing', 'reference_type' => 'order', 'reference_id' => 0, 'status' => 'completed', 'created_at' => $now ) );
$wpdb->insert( $wd_t, array( 'vendor_id' => $vendor_id, 'amount' => 113.40, 'method' => 'paypal', 'status' => 'pending', 'created_at' => $now ) );
$wpdb->insert( $items_t, array( 'batch_id' => 0, 'vendor_id' => $vendor_id, 'paypal_email' => 'p8@example.test', 'amount' => 10, 'currency' => 'USD', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now ) );
$item_id = (int) $wpdb->insert_id;

$clearance_backup = get_option( 'wpss_payouts', array() );
update_option( 'wpss_payouts', array_merge( is_array( $clearance_backup ) ? $clearance_backup : array(), array( 'clearance_days' => 7 ) ) );

$summary  = ( new \WPSellServices\Services\EarningsService() )->get_summary( $vendor_id );
$eligible = ( new \WPSellServicesPro\PayPalPayouts\PayoutsBatchService() )->get_pending_payouts( $vendor_id );

wpss_t( abs( $summary['available_balance'] - 86.60 ) < 0.001, 'EarningsService available = 250 - 113.40 pending - 50 clearing = 86.60 (got ' . $summary['available_balance'] . ')' );
wpss_t( abs( $eligible - ( $summary['available_balance'] - 10 ) ) < 0.001, 'payouts eligible = available - 10 in flight (got ' . $eligible . ')' );

// record_payout_debit is idempotent and leaves no open transaction.
$svc = new \WPSellServicesPro\PayPalPayouts\PayoutsBatchService();
$r1  = $svc->record_payout_debit( $vendor_id, 10, $item_id, 'USD' );
$r2  = $svc->record_payout_debit( $vendor_id, 10, $item_id, 'USD' );
wpss_t( 'written' === $r1 && 'skipped' === $r2, "record_payout_debit written then skipped (got {$r1}, {$r2})" );
wpss_t( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$txn_t} WHERE user_id = %d AND reference_type = 'paypal_payout' AND reference_id = %d", $vendor_id, $item_id ) ), 'one paypal_payout debit row' );
// A transaction left open would be undone by this ROLLBACK, taking the debit with it.
$wpdb->query( 'ROLLBACK' );
wpss_t( 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$txn_t} WHERE user_id = %d AND reference_type = 'paypal_payout' AND reference_id = %d", $vendor_id, $item_id ) ), 'debit is committed, no transaction left open' );

update_option( 'wpss_payouts', $clearance_backup );

// ---------------------------------------------------------------------------
// 3. External wallets are not offered; the filter still takes a third party.
// ---------------------------------------------------------------------------
echo "\n3. Wallet providers\n";

// Make every external provider report its plugin as active.
if ( ! function_exists( 'woo_wallet' ) ) {
	function woo_wallet() {}
}
if ( ! defined( 'WOO_WALLET_ABSPATH' ) ) {
	define( 'WOO_WALLET_ABSPATH', '/p8' );
}
if ( ! function_exists( 'mycred' ) ) {
	function mycred() {}
}
if ( ! class_exists( 'myCRED_Core' ) ) {
	class myCRED_Core {} // phpcs:ignore
}

$third = new class() implements \WPSellServicesPro\Integrations\Wallets\WalletProviderInterface {
	public function get_id(): string {
		return 'p8_third_party'; }
	public function get_name(): string {
		return 'P8'; }
	public function is_active(): bool {
		return true; }
	public function get_balance( int $user_id ): float {
		return 1.0; }
	public function credit( int $user_id, float $amount, string $description = '', array $meta = array() ): bool {
		return false; }
	public function debit( int $user_id, float $amount, string $description = '', array $meta = array() ): bool {
		return false; }
	public function get_transactions( int $user_id, int $limit = 20, int $offset = 0 ): array {
		return array(); }
	public function init(): void {}
	public function has_sufficient_balance( int $user_id, float $amount ): bool {
		return false; }
};
$add_third = static function ( array $w ) use ( $third ) {
	$w[ $third->get_id() ] = $third;
	return $w;
};
add_filter( 'wpss_wallet_providers', $add_third, 20 );
$providers = apply_filters( 'wpss_wallet_providers', array() );
remove_filter( 'wpss_wallet_providers', $add_third, 20 );

wpss_t( isset( $providers['internal'] ), 'internal provider offered' );
wpss_t( ! isset( $providers['terawallet'] ) && ! isset( $providers['woowallet'] ) && ! isset( $providers['mycred'] ), 'terawallet, woowallet, mycred not offered even with their plugins present (got: ' . implode( ',', array_keys( $providers ) ) . ')' );
wpss_t( isset( $providers['p8_third_party'] ), 'a third-party provider added through the filter is offered' );
wpss_t( class_exists( '\WPSellServicesPro\Integrations\Wallets\TeraWalletProvider' ), 'TeraWalletProvider class kept for third parties' );

// ---------------------------------------------------------------------------
// 4. Dashboard refund: transfer not reversed -> wallet debited once.
// ---------------------------------------------------------------------------
echo "\n4. Stripe dashboard refund on a Connect order\n";

/**
 * Seed a completed, Connect-settled order with its ledger rows.
 *
 * @param int    $vendor_id  Vendor.
 * @param int    $service_id Service.
 * @param string $pi         PaymentIntent id.
 * @return int Order id.
 */
function wpss_p8_seed_connect_order( int $vendor_id, int $service_id, string $pi ): int {
	global $wpdb;
	$now = current_time( 'mysql' );
	$wpdb->insert(
		$wpdb->prefix . 'wpss_orders',
		array(
			'order_number'        => 'P8-' . wp_rand( 10000, 99999 ),
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
			'connect_transfer_id' => 'acct_p8stub',
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
	$wpdb->insert( $txn, array( 'user_id' => $vendor_id, 'type' => 'order_earning', 'amount' => 80, 'balance_after' => 0, 'currency' => 'USD', 'description' => 'p8 credit', 'reference_type' => 'order', 'reference_id' => $order_id, 'status' => 'completed', 'created_at' => $now ) );
	$wpdb->insert( $txn, array( 'user_id' => $vendor_id, 'type' => 'connect_transfer', 'amount' => 80, 'balance_after' => 0, 'currency' => 'USD', 'description' => 'p8 offset', 'reference_type' => 'connect_transfer', 'reference_id' => $order_id, 'status' => 'completed', 'created_at' => $now ) );
	return $order_id;
}

/**
 * Build a charge.refunded payload.
 *
 * @param string      $pi       PaymentIntent id.
 * @param string|null $reversal Transfer reversal id, or null when not reversed.
 * @return array
 */
function wpss_p8_refund_payload( string $pi, ?string $reversal ): array {
	return array(
		'type' => 'charge.refunded',
		'data' => array(
			'object' => array(
				'id'              => 'ch_' . substr( $pi, 3 ),
				'payment_intent'  => $pi,
				'currency'        => 'usd',
				'amount_refunded' => 10000,
				'refunds'         => array( 'data' => array( array( 'id' => 're_p8', 'transfer_reversal' => $reversal ) ) ),
			),
		),
	);
}

$reversal_count = static fn( int $order_id ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$txn_t} WHERE reference_type = 'order' AND reference_id = %d AND type = 'order_reversal'", $order_id ) );

$gateway     = new \WPSellServices\Integrations\Stripe\StripeGateway();
$pi_kept     = 'pi_p8kept' . wp_rand( 1000, 9999 );
$order_kept  = wpss_p8_seed_connect_order( $vendor_id, $service_id, $pi_kept );
$gateway->handle_webhook( wpss_p8_refund_payload( $pi_kept, null ) );
$after_first = $reversal_count( $order_kept );
$gateway->handle_webhook( wpss_p8_refund_payload( $pi_kept, null ) );
$status_kept = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders_t} WHERE id = %d", $order_kept ) );

wpss_t( 'refunded' === $status_kept, "order moved to refunded (got {$status_kept})" );
wpss_t( 1 === $after_first, 'refund without transfer reversal debits the vendor wallet once (got ' . $after_first . ')' );
wpss_t( 1 === $reversal_count( $order_kept ), 'replaying the webhook adds no second debit' );

$pi_rev    = 'pi_p8rev' . wp_rand( 1000, 9999 );
$order_rev = wpss_p8_seed_connect_order( $vendor_id, $service_id, $pi_rev );
$gateway->handle_webhook( wpss_p8_refund_payload( $pi_rev, 'trr_p8' ) );
wpss_t( 0 === $reversal_count( $order_rev ), 'refund WITH transfer reversal leaves the wallet alone (Stripe reclaimed the share)' );

// ---------------------------------------------------------------------------
// Cleanup.
// ---------------------------------------------------------------------------
$wpdb->delete( $orders_t, array( 'id' => $order_kept ) );
$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $order_kept ) );
$wpdb->delete( $orders_t, array( 'id' => $order_rev ) );
$wpdb->delete( $wpdb->prefix . 'wpss_order_meta', array( 'order_id' => $order_rev ) );
$wpdb->delete( $txn_t, array( 'user_id' => $vendor_id ) );
$wpdb->delete( $wd_t, array( 'vendor_id' => $vendor_id ) );
$wpdb->delete( $items_t, array( 'vendor_id' => $vendor_id ) );
$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor_id ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vendor_id );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
