<?php
/**
 * PayPal payout batch: store currency only, and a failed submit releases the vendors' balances.
 *
 * Run: wp eval-file tests/pro/test-paypal-batch-failure.php
 *
 * Stubs the PayPal client, the vendor profile and the payable amount; seeds nothing else. Removes the
 * batch rows it creates.
 *
 * @package WPSellServices
 */

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

use WPSellServicesPro\PayPalPayouts\PayoutsBatchService;
use WPSellServicesPro\PayPalPayouts\PayPalPayoutsApiClient;
use WPSellServicesPro\PayPalPayouts\VendorPayoutProfileService;

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$api = new class() extends PayPalPayoutsApiClient {
	public array $sent = array();
	public bool $ok    = false;
	public function create_payout( array $items, string $sender_batch_id, string $currency = 'USD' ): array {
		$this->sent[] = $currency;
		return $this->ok ? array( 'success' => true, 'data' => array( 'batch_header' => array( 'payout_batch_id' => 'QA-PB', 'batch_status' => 'pending' ) ) ) : array( 'success' => false, 'message' => 'stubbed refusal' );
	}
};
$profile = new class() extends VendorPayoutProfileService {
	public function has_paypal_email( int $vendor_id ): bool {
		return true;
	}
	public function get_paypal_email( int $vendor_id ): string {
		return 'qa-payout@example.test';
	}
};
$service = new class( $api, $profile ) extends PayoutsBatchService {
	public function get_pending_payouts( int $vendor_id ): float {
		return 25.0;
	}
};

$store   = strtoupper( wpss_get_currency() );
$other   = 'USD' === $store ? 'EUR' : 'USD';
$batches = $wpdb->prefix . 'wpss_pro_paypal_payout_batches';
$items   = $wpdb->prefix . 'wpss_pro_paypal_payout_items';
$before  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$batches}" ); // phpcs:ignore WordPress.DB

$refused = $service->create_batch( array( 1 ), $other );
$check( 'a currency other than the store currency is refused', ! $refused['success'] && str_contains( $refused['message'], $store ) );
$check( 'a refused currency creates no batch and calls no PayPal', $before === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$batches}" ) && ! $api->sent ); // phpcs:ignore WordPress.DB

$failed = $service->create_batch( array( 1 ) );
$check( 'no currency means the store currency', array( $store ) === $api->sent );
$check( 'a failed PayPal submit fails the batch', ! $failed['success'] && 'failed' === $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$batches} WHERE id = %d", $failed['batch_id'] ) ) ); // phpcs:ignore WordPress.DB
$check( 'a failed submit leaves no pending item reserving the balance', 0 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$items} WHERE batch_id = %d AND status <> 'failed'", $failed['batch_id'] ) ) ); // phpcs:ignore WordPress.DB

$wpdb->delete( $items, array( 'batch_id' => $failed['batch_id'] ) ); // phpcs:ignore WordPress.DB
$wpdb->delete( $batches, array( 'id' => $failed['batch_id'] ) ); // phpcs:ignore WordPress.DB

exit( $fails ? 1 : 0 );
