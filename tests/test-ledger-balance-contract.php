<?php
/**
 * A debit can never increase a vendor's balance.
 *
 * Run: wp eval-file tests/test-ledger-balance-contract.php
 *
 * The convention is that a debit row (withdrawal, debit, dispute_refund) stores
 * a POSITIVE amount and wpss_get_ledger_balance() applies the sign. One legacy
 * row was written negative, and `-amount` turned it into `+amount` - a
 * withdrawal that ADDED fifty dollars, overstating that vendor by 100.00.
 *
 * Money path, so the check stays.
 *
 * @package WPSellServices
 */

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$table = $wpdb->prefix . 'wpss_wallet_transactions';
$user  = 999999; // Nobody. Rows are removed at the end.

$wpdb->delete( $table, array( 'user_id' => $user ) );

$row = static function ( string $type, float $amount ) use ( $wpdb, $table, $user ) {
	$wpdb->insert(
		$table,
		array(
			'user_id'    => $user,
			'type'       => $type,
			'amount'     => $amount,
			'currency'   => 'USD',
			'status'     => 'completed',
			'created_at' => current_time( 'mysql' ),
		)
	);
};

// 100 earned, 40 withdrawn, stored the documented way.
$row( 'order_earning', 100.00 );
$row( 'withdrawal', 40.00 );
$check( 'positive debit subtracts', 60.00 === round( wpss_get_ledger_balance( $user ), 2 ) );

// The same withdrawal stored the wrong way round must not become a credit.
$wpdb->delete( $table, array( 'user_id' => $user ) );
$row( 'order_earning', 100.00 );
$row( 'withdrawal', -40.00 );
$check( 'NEGATIVE debit still subtracts', 60.00 === round( wpss_get_ledger_balance( $user ), 2 ) );

// Reversals are a different shape: not a debit type, negative amount, and they
// must keep subtracting rather than being flipped.
$wpdb->delete( $table, array( 'user_id' => $user ) );
$row( 'order_earning', 100.00 );
$row( 'order_reversal', -30.00 );
$check( 'negative reversal subtracts', 70.00 === round( wpss_get_ledger_balance( $user ), 2 ) );

// A vendor can legitimately owe money.
$wpdb->delete( $table, array( 'user_id' => $user ) );
$row( 'order_earning', 10.00 );
$row( 'withdrawal', 50.00 );
$check( 'balance may go negative', -40.00 === round( wpss_get_ledger_balance( $user ), 2 ) );

$wpdb->delete( $table, array( 'user_id' => $user ) );

// And no real row currently violates the convention.
$bad = (int) $wpdb->get_var(
	"SELECT COUNT(*) FROM {$table}
	 WHERE amount < 0 AND type IN ( 'withdrawal', 'debit', 'dispute_refund' )"
);
$check( sprintf( 'no debit row is stored negative (found %d)', $bad ), 0 === $bad );

// --- 1.7.1 (F10 / F23): the database enforces idempotency; the profile follows the ledger ---
$index_names = static fn( string $t ): array => array_unique( array_column( (array) $wpdb->get_results( "SHOW INDEX FROM {$wpdb->prefix}wpss_{$t}", ARRAY_A ), 'Key_name' ) );
$check( 'wallet_transactions has UNIQUE uniq_reference (reference_type, reference_id, type)', in_array( 'uniq_reference', $index_names( 'wallet_transactions' ), true ) );
$check( 'reviews has UNIQUE uniq_order_review (order_id, review_type)', in_array( 'uniq_order_review', $index_names( 'reviews' ), true ) );
$check( 'orders has KEY idx_transaction (transaction_id)', in_array( 'idx_transaction', $index_names( 'orders' ), true ) );

$insert = static fn(): bool => function_exists( 'wpss_insert_ledger_row' ) && wpss_insert_ledger_row(
	array(
		'user_id'        => $user,
		'type'           => 'order_earning',
		'amount'         => 25.0,
		'reference_type' => 'order',
		'reference_id'   => 987654321,
	)
);
$check( 'ledger insert helper writes a row', $insert() );
$check( 'the same row inserted again is idempotent success', $insert() );
$check( '  and there is exactly one row', 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND reference_id = 987654321", $user ) ) );

// The vendor profile's cached money columns follow every ledger write.
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$wpdb->delete( $profiles, array( 'user_id' => $user ) );
$wpdb->insert( $profiles, array( 'user_id' => $user, 'display_name' => 'contract', 'total_earnings' => 12345.0, 'net_earnings' => 12345.0, 'created_at' => current_time( 'mysql' ), 'updated_at' => current_time( 'mysql' ) ) );

if ( function_exists( 'wpss_insert_ledger_row' ) ) {
	wpss_insert_ledger_row( array( 'user_id' => $user, 'type' => 'withdrawal', 'amount' => 5.0, 'reference_type' => 'withdrawal', 'reference_id' => 987654321 ) );
}
$profile = $wpdb->get_row( $wpdb->prepare( "SELECT total_earnings, net_earnings FROM {$profiles} WHERE user_id = %d", $user ) );
$earned  = function_exists( 'wpss_get_ledger_total_earned' ) ? wpss_get_ledger_total_earned( $user ) : -1.0;
$check( 'profile total_earnings equals the ledger total earned after a write (25.00)', $profile && abs( (float) $profile->total_earnings - $earned ) < 0.0001 && abs( $earned - 25.0 ) < 0.0001 );
$check( 'profile net_earnings equals the ledger balance after a write (20.00)', $profile && abs( (float) $profile->net_earnings - wpss_get_ledger_balance( $user ) ) < 0.0001 && abs( wpss_get_ledger_balance( $user ) - 20.0 ) < 0.0001 );

$wpdb->delete( $profiles, array( 'user_id' => $user ) );
$wpdb->delete( $table, array( 'user_id' => $user ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
if ( $fails ) {
	exit( 1 );
}
