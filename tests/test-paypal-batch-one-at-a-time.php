<?php
/**
 * Two PayPal payout batches started at once cannot both pay the same balance.
 *
 * A vendor's batch amount is the available balance minus items already in
 * flight. Without a lock, a second run reading the balance before the first
 * wrote its items paid the full balance again. The test starts the second run
 * on its own database connection inside that window.
 *
 * Run: wp eval-file tests/test-paypal-batch-one-at-a-time.php
 *
 * PayPal is stubbed; the batch and item rows it writes are deleted.
 *
 * @package WPSellServices
 */

use WPSellServicesPro\PayPalPayouts\PayoutsBatchService;
use WPSellServicesPro\PayPalPayouts\PayPalPayoutsApiClient;
use WPSellServicesPro\PayPalPayouts\VendorPayoutProfileService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( PayoutsBatchService::class ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

global $wpdb;
$batches = $wpdb->prefix . 'wpss_pro_paypal_payout_batches';
$items   = $wpdb->prefix . 'wpss_pro_paypal_payout_items';

// A vendor with money to pay out.
$vendor  = 0;
$service = new PayoutsBatchService();
foreach ( get_users( array( 'role__in' => array( 'wpss_vendor' ), 'fields' => 'ID', 'number' => 50 ) ) as $uid ) {
	if ( $service->get_pending_payouts( (int) $uid ) > 0 ) {
		$vendor = (int) $uid;
		break;
	}
}
$check( 'found a vendor with a balance to pay out', $vendor > 0 );
if ( ! $vendor ) {
	echo "\n$fails FAILED\n";
	return;
}
$balance = $service->get_pending_payouts( $vendor );

$api = new class() extends PayPalPayoutsApiClient {
	public function create_payout( array $items, string $sender_batch_id, string $currency = 'USD' ): array {
		return array(
			'success' => true,
			'data'    => array( 'batch_header' => array( 'payout_batch_id' => 'QA-' . $sender_batch_id, 'batch_status' => 'PENDING' ) ),
		);
	}
};

$inner_result = null;
$profile      = new class() extends VendorPayoutProfileService {
	/** @var callable|null Runs once, after the outer batch has read the balance. */
	public $in_window = null;
	public function has_paypal_email( int $vendor_id ): bool {
		return true;
	}
	public function get_paypal_email( int $vendor_id ): string {
		if ( $this->in_window ) {
			$run             = $this->in_window;
			$this->in_window = null;
			$run();
		}
		return 'qa-payout@example.com';
	}
};

$before_max = (int) $wpdb->get_var( "SELECT COALESCE( MAX( id ), 0 ) FROM {$batches}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// The second admin's request: its own connection, so a lock held by the first is really held.
$profile->in_window = static function () use ( &$inner_result, $api, $vendor ) {
	global $wpdb;
	$outer = $wpdb;
	$wpdb  = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$wpdb->set_prefix( $outer->prefix );
	$inner_profile = new class() extends VendorPayoutProfileService {
		public function has_paypal_email( int $vendor_id ): bool {
			return true;
		}
		public function get_paypal_email( int $vendor_id ): string {
			return 'qa-payout@example.com';
		}
	};
	try {
		$inner_result = ( new PayoutsBatchService( $api, $inner_profile ) )->create_batch( array( $vendor ) );
	} finally {
		$wpdb->close();
		$wpdb = $outer; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}
};

$outer_result = ( new PayoutsBatchService( $api, $profile ) )->create_batch( array( $vendor ) );

$new_batches = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$batches} WHERE id > %d", $before_max ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$reserved    = $new_batches ? (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE( SUM( amount ), 0 ) FROM {$items} WHERE vendor_id = %d AND batch_id IN (" . implode( ',', $new_batches ) . ')', $vendor ) ) : 0.0; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

$check( 'the first batch went through', ! empty( $outer_result['success'] ), (string) ( $outer_result['message'] ?? '' ) );
$check( 'the batch started meanwhile was refused', is_array( $inner_result ) && empty( $inner_result['success'] ) && null === $inner_result['batch_id'], wp_json_encode( $inner_result ) );
$check( 'one batch was written', 1 === count( $new_batches ), count( $new_batches ) . ' batches' );
$check( 'the vendor is reserved at most their balance', $reserved <= $balance + 0.001, "reserved {$reserved} of {$balance}" );
$check( 'nothing is left to pay right after', $service->get_pending_payouts( $vendor ) < 0.01 );

foreach ( $new_batches as $id ) {
	$wpdb->delete( $items, array( 'batch_id' => $id ) );
	$wpdb->delete( $batches, array( 'id' => $id ) );
}
$check( 'cleanup restored the balance', abs( $service->get_pending_payouts( $vendor ) - $balance ) < 0.001 );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
