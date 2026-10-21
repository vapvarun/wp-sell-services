<?php
/**
 * The admin "Paid so far" line counts the phases that are paid, not the phases in the contract.
 *
 * Run: wp eval-file tests/test-contract-totals-paid-count.php
 *
 * Reads every milestone contract on the site; skips when there is none.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\MilestoneService;

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$svc     = new MilestoneService();
$parents = $wpdb->get_col( "SELECT DISTINCT platform_order_id FROM {$wpdb->prefix}wpss_orders WHERE platform = 'milestone' AND platform_order_id > 0 LIMIT 50" );
$seen    = 0;
foreach ( $parents as $parent ) {
	$t = $svc->get_contract_totals( (int) $parent );
	if ( ! $t['count'] ) {
		continue;
	}
	++$seen;
	$paid = 0;
	foreach ( $svc->get_for_parent( (int) $parent ) as $phase ) {
		$paid += ( 'paid' === ( $phase['payment_status'] ?? '' ) && ! in_array( $phase['status'] ?? '', array( 'cancelled', 'rejected', 'refunded' ), true ) ) ? 1 : 0;
	}
	$check( "contract {$parent}: paid_count {$paid} of {$t['count']}", isset( $t['paid_count'] ) && $paid === $t['paid_count'] && $t['paid_count'] <= $t['count'] );
}
if ( ! $seen ) {
	echo "SKIP  no milestone contract on this site\n";
}
exit( $fails ? 1 : 0 );
