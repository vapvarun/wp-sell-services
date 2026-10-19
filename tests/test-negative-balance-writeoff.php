<?php
/**
 * Negative balance: the gates hold, and the admin write-off nets to zero once.
 *
 * Owner decision (Basecamp 10244795017): carry the negative, explain it, and give the
 * admin a write-off. The write-off is a credit that is NOT an earning.
 *
 * Run: wp eval-file tests/test-negative-balance-writeoff.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$ledger  = $wpdb->prefix . 'wpss_wallet_transactions';
$service = new \WPSellServices\Services\EarningsService();
$admin   = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
$vendor  = (int) wp_insert_user(
	array(
		'user_login' => 'wpss_wo_' . wp_generate_password( 6, false ),
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'wo' . wp_generate_password( 6, false ) . '@example.test',
	)
);

// Earned 100 long ago, paid out 150: balance -50.
$old = gmdate( 'Y-m-d H:i:s', time() - 90 * DAY_IN_SECONDS );
wpss_insert_ledger_row( array( 'user_id' => $vendor, 'type' => 'order_earning', 'amount' => 100, 'reference_type' => 'wo_test', 'reference_id' => $vendor, 'created_at' => $old ) );
wpss_insert_ledger_row( array( 'user_id' => $vendor, 'type' => 'withdrawal', 'amount' => 150, 'reference_type' => 'wo_test', 'reference_id' => $vendor + 1, 'created_at' => $old ) );

$check( 'seed: balance is -50', -50.0 === wpss_get_ledger_balance( $vendor ) );

// Gates at a negative balance.
$req = $service->request_withdrawal( $vendor, 25.0, 'bank_transfer', array() );
$check( 'a withdrawal request is refused while negative', false === $req['success'] );
$summary = $service->get_summary( $vendor );
$check( 'available balance is negative and classified so', $summary['available_balance'] < 0 && 'negative' === \WPSellServices\Services\EarningsService::classify_balance( (float) $summary['available_balance'] ) );

wp_set_current_user( $vendor );
$rest_summary = ( new \WPSellServices\API\EarningsController() )->get_summary( new WP_REST_Request( 'GET', '/wpss/v1/earnings/summary' ) )->get_data();
wp_set_current_user( 0 );
$check( 'REST earnings summary carries balance_state "negative"', 'negative' === ( $rest_summary['balance_state'] ?? '' ) );

$wpdb->insert( $wpdb->prefix . 'wpss_withdrawals', array( 'vendor_id' => $vendor, 'amount' => 10, 'method' => 'bank_transfer', 'details' => '', 'status' => 'approved', 'created_at' => current_time( 'mysql', true ) ) );
$wid  = (int) $wpdb->insert_id;
$paid = $service->mark_paid( $wid );
$check( 'an approved request cannot be marked paid while negative', false === $paid['success'] );
$check( 'auto-withdrawal does not select a negative vendor', ! in_array( $vendor, wp_list_pluck( $service->get_eligible_vendors_for_auto_withdrawal(), 'vendor_id' ), true ) );
$wpdb->delete( $wpdb->prefix . 'wpss_withdrawals', array( 'id' => $wid ) );

// Write-off.
$earned_before = wpss_get_ledger_total_earned( $vendor );
$check( 'a reason is required', 'reason_required' === ( $service->write_off_negative_balance( $vendor, '  ', $admin )['code'] ?? '' ) );
$fired = array();
add_action(
	'wpss_balance_written_off',
	static function ( $v, $amount ) use ( &$fired ) {
		$fired[] = array( $v, $amount );
	},
	10,
	2
);
$first = $service->write_off_negative_balance( $vendor, 'Uncollectable after chargeback', $admin );
$check( 'write-off succeeds for 50.00', $first['success'] && 50.0 === (float) $first['amount'] );
$check( 'wpss_balance_written_off fires once with the amount', array( array( $vendor, 50.0 ) ) === $fired );
$check( 'balance is exactly zero afterwards', 0.0 === wpss_get_ledger_balance( $vendor ) );
$check( 'available balance is not negative afterwards', $service->get_summary( $vendor )['available_balance'] >= 0 );
$check( 'total earned is unchanged (the write-off is not an earning)', $earned_before === wpss_get_ledger_total_earned( $vendor ) );
$check( 'the write-off is absent from the clearance hold', 0.0 === (float) $service->get_summary( $vendor )['in_clearance'] );

$again = $service->write_off_negative_balance( $vendor, 'double click', $admin );
$rows  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ledger} WHERE user_id = %d AND type = 'balance_write_off'", $vendor ) );
$check( 'a second call writes nothing', false === $again['success'] && 'not_negative' === $again['code'] && 1 === $rows );

// The non-earning list is a filter: a rail can add its own credit type.
$extend = static function ( $types ) {
	$types[] = 'rail_goodwill';
	return $types;
};
add_filter( 'wpss_ledger_non_earning_types', $extend );
$check( 'wpss_ledger_non_earning_types extends the exclusion list', in_array( "'rail_goodwill'", explode( ',', wpss_get_ledger_not_earned_types_sql() ), true ) );
remove_filter( 'wpss_ledger_non_earning_types', $extend );

// REST door: admin only.
wp_set_current_user( $vendor );
$req1 = new WP_REST_Request( 'POST', '/wpss/v1/vendors/' . $vendor . '/balance/write-off' );
$req1->set_param( 'reason', 'x' );
$denied = rest_do_request( $req1 );
$check( 'REST refuses a non-admin', in_array( $denied->get_status(), array( 401, 403 ), true ) );
wp_set_current_user( $admin );
$req2 = new WP_REST_Request( 'POST', '/wpss/v1/vendors/' . $vendor . '/balance/write-off' );
$req2->set_param( 'reason', 'again' );
$res = rest_do_request( $req2 );
$check( 'REST answers 409 when nothing is owed', 409 === $res->get_status() );

// Clean up.
$wpdb->delete( $ledger, array( 'user_id' => $vendor ) );
$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor ) );
wp_delete_user( $vendor );
wp_set_current_user( 0 );

exit( $fails ? 1 : 0 );
