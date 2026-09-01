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

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
