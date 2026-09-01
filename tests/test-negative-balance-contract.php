<?php
/**
 * Negative ledger balance contract.
 *
 * Two vendors were paid more than they earned: the pre-1.7.0 ledger summed
 * `-amount` for debits, one withdrawal row had been written negative, and
 * `-(-50)` credited the vendor fifty dollars. The -ABS() fix made the sum
 * correct and the correct sum shows the hole.
 *
 * The decision was to CARRY the negative rather than write it off, so the
 * properties that matter are: they cannot withdraw, they are told why, and the
 * owner can find them without a support ticket.
 *
 * Run: wp eval-file tests/test-negative-balance-contract.php
 *
 * @package WPSellServices
 */

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

echo "\nNegative ledger balance contract\n\n";

global $wpdb;

$negative = wpss_get_negative_ledger_balances();

// 1. The lookup agrees with the balance helper. Two definitions of a balance
//    is how this whole class of bug started.
$agree = true;
foreach ( $negative as $row ) {
	if ( abs( $row['balance'] - wpss_get_ledger_balance( $row['user_id'] ) ) > 0.001 ) {
		$agree = false;
	}
}
wpss_t( $agree, sprintf( 'the negative-balance lookup agrees with wpss_get_ledger_balance() for all %d', count( $negative ) ) );

// 2. It is ONE query, not one per vendor. The N+1 version would look identical
//    on this install and fall over on a real one.
$before = $wpdb->num_queries;
wpss_get_negative_ledger_balances();
$used = $wpdb->num_queries - $before;
wpss_t( $used <= 1, sprintf( 'the lookup costs one query regardless of vendor count (used %d)', $used ) );

// 3. Nothing positive leaks into the list.
$only_negative = true;
foreach ( $negative as $row ) {
	if ( $row['balance'] >= 0 ) {
		$only_negative = false;
	}
}
wpss_t( $only_negative, 'only genuinely negative balances are reported' );

// 4. THE SAFETY PROPERTY. A vendor in debt must not be able to withdraw, and
//    must not be quoted a negative figure as if it were spendable.
$svc = new \WPSellServices\Services\EarningsService();

if ( $negative ) {
	$victim  = (int) $negative[0]['user_id'];
	$summary = $svc->get_summary( $victim );

	$original = get_current_user_id();
	wp_set_current_user( $victim );

	$req = new WP_REST_Request( 'POST', '/wpss/v1/wallet/withdraw' );
	$req->set_param( 'amount', 50 );
	$req->set_param( 'method', 'bank' );
	$res = rest_do_request( $req );

	wpss_t( $res->is_error(), 'a vendor in debt cannot request a withdrawal' );

	if ( $res->is_error() ) {
		$msg = $res->as_error()->get_error_message();
		wpss_t(
			false === strpos( $msg, '-' ),
			'the refusal does not quote a negative figure as a withdrawable amount'
		);
	}

	wp_set_current_user( $original );

	// The ledger is allowed to be negative; what must never happen is that
	// figure being offered as spendable.
	wpss_t(
		(float) ( $summary['available_balance'] ?? 0 ) <= 0,
		'available_balance is never positive while the ledger is in debt'
	);
}

// 5. The vendor is told what it means, and the label matches the number.
$tpl = file_get_contents( dirname( __DIR__ ) . '/templates/dashboard/sections/earnings.php' );
wpss_t( false !== strpos( $tpl, 'wpss-earnings-debt' ), 'the earnings screen explains a negative balance' );
wpss_t(
	false !== strpos( $tpl, "esc_html_e( 'Balance to clear'" ),
	'the stat label stops saying "Available for Withdrawal" over a minus figure'
);

// 6. The owner is told, on the screen where they manage vendors.
$admin = file_get_contents( dirname( __DIR__ ) . '/src/Admin/Pages/VendorsPage.php' );
wpss_t( false !== strpos( $admin, 'wpss_get_negative_ledger_balances' ), 'the Vendors screen surfaces them to the owner' );

// 7. Nothing here writes. Carrying the balance means carrying it - a test run
//    must not quietly adjust anyone's money.
$sum_before = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}wpss_wallet_transactions" );
wpss_get_negative_ledger_balances();
$sum_after = (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount),0) FROM {$wpdb->prefix}wpss_wallet_transactions" );
wpss_t( abs( $sum_before - $sum_after ) < 0.001, 'reading the report does not alter the ledger' );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
