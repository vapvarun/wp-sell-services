<?php
/**
 * An admin wallet adjustment says who made it and why, and is audited.
 *
 * Pro's internal wallet wrote its own ledger rows: "Admin adjustment", no
 * reference (so no replay guard), no audit row, and the vendor's cached
 * totals left stale. It now writes through wpss_insert_ledger_row(), so the
 * row names the admin and the reason, carries a wallet_adjustment reference,
 * and Free's ledger listener records the acting admin in the audit log.
 *
 * Run: wp eval-file tests/test-admin-wallet-adjustment-leaves-trail.php
 *
 * Writes ledger and audit rows for one vendor and deletes them.
 *
 * @package WPSellServices
 */

use WPSellServicesPro\Integrations\Wallets\InternalWalletProvider;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( InternalWalletProvider::class ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

global $wpdb;
$ledger   = $wpdb->prefix . 'wpss_wallet_transactions';
$audit    = $wpdb->prefix . 'wpss_audit_log';
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$vendor   = (int) $wpdb->get_var( "SELECT user_id FROM {$profiles} ORDER BY user_id LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$admin    = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
$check( 'found a vendor', $vendor > 0 );
wp_set_current_user( $admin );

$from_ledger = (int) $wpdb->get_var( "SELECT COALESCE( MAX( id ), 0 ) FROM {$ledger}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$from_audit  = (int) $wpdb->get_var( "SELECT COALESCE( MAX( id ), 0 ) FROM {$audit}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$balance     = wpss_get_ledger_balance( $vendor );

// Drive the real admin AJAX handler.
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function () {
	throw new RuntimeException( 'done' );
} );
$ajax = static function ( string $action, float $amount, string $reason ) use ( $vendor ): array {
	$_POST = $_REQUEST = array(
		'action'  => 'wpss_admin_wallet_' . $action,
		'nonce'   => wp_create_nonce( 'wpss_pro_admin' ),
		'user_id' => $vendor,
		'amount'  => (string) $amount,
		'reason'  => $reason,
	);
	ob_start();
	try {
		do_action( 'wp_ajax_wpss_admin_wallet_' . $action );
	} catch ( RuntimeException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
	}
	return (array) json_decode( (string) ob_get_clean(), true );
};

$first  = $ajax( 'credit', 4.25, 'Goodwill for late payout' );
$second = $ajax( 'credit', 4.25, 'Goodwill for late payout' );
$debit  = $ajax( 'debit', 1.5, '' );
$check( 'the credit succeeded', ! empty( $first['success'] ), wp_json_encode( $first ) );

$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$ledger} WHERE id > %d AND user_id = %d ORDER BY id", $from_ledger, $vendor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( 'three ledger rows were written', 3 === count( $rows ), count( $rows ) . ' rows' );
$name = wp_get_current_user()->display_name;
$check( 'the row names the admin and the reason', isset( $rows[0] ) && false !== strpos( $rows[0]->description, $name ) && false !== strpos( $rows[0]->description, 'Goodwill for late payout' ), (string) ( $rows[0]->description ?? '' ) );
$check( 'it carries a wallet_adjustment reference', isset( $rows[0] ) && 'wallet_adjustment' === $rows[0]->reference_type && (int) $rows[0]->reference_id > 0 );
$check( 'two identical adjustments are two rows, not one deduped', isset( $rows[1] ) && (int) $rows[1]->reference_id !== (int) $rows[0]->reference_id );
$check( 'a debit without a reason still names the admin', isset( $rows[2] ) && 'debit' === $rows[2]->type && false !== strpos( $rows[2]->description, $name ) );
$check( 'the balance moved by the net amount', abs( wpss_get_ledger_balance( $vendor ) - ( $balance + 7.0 ) ) < 0.001 );

$audited = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$audit} WHERE id > %d AND event_type = 'ledger.insert'", $from_audit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( 'each adjustment has an audit row', 3 === count( $audited ), count( $audited ) . ' audit rows' );
$check( 'the audit row records the acting admin', isset( $audited[0] ) && $admin === (int) $audited[0]->actor_id );

$over = $ajax( 'debit', $balance + 1000, 'too much' );
$check( 'a debit past the balance is refused', empty( $over['success'] ) );

$profile_total = (float) $wpdb->get_var( $wpdb->prepare( "SELECT total_earnings FROM {$profiles} WHERE user_id = %d", $vendor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( 'the vendor profile total follows the ledger', abs( $profile_total - wpss_get_ledger_total_earned( $vendor ) ) < 0.001, "profile {$profile_total} ledger " . wpss_get_ledger_total_earned( $vendor ) );

// Clean up.
$wpdb->query( $wpdb->prepare( "DELETE FROM {$ledger} WHERE id > %d AND user_id = %d", $from_ledger, $vendor ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( $wpdb->prepare( "DELETE FROM {$audit} WHERE id > %d", $from_audit ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
( new \WPSellServices\Database\Repositories\VendorProfileRepository() )->sync_ledger_totals( $vendor );
$check( 'cleanup restored the balance', abs( wpss_get_ledger_balance( $vendor ) - $balance ) < 0.001 );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
