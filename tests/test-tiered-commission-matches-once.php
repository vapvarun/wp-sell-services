<?php
/**
 * One commission computation evaluates the tiered rules once.
 *
 * CommissionService::compute_breakdown() fires wpss_commission_rate and then
 * wpss_commission_fee with the same order. Pro's tiered commission answered
 * both by running every rule's matches() again, so a volume rule summed the
 * vendor's completed orders twice for every commission. The fee filter now
 * takes the rule the rate filter matched.
 *
 * Run: wp eval-file tests/test-tiered-commission-matches-once.php
 *
 * Creates two volume rules (QA-TIER-*) and deletes them.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\CommissionService;
use WPSellServicesPro\TieredCommission\CommissionRuleRepository;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( CommissionRuleRepository::class ) || ! has_filter( 'wpss_commission_fee' ) ) {
	echo "SKIP  Pro tiered commission is not active\n";
	return;
}

global $wpdb;
$vendor = (int) $wpdb->get_var( "SELECT vendor_id FROM {$wpdb->prefix}wpss_orders WHERE status = 'completed' AND total > 0 GROUP BY vendor_id ORDER BY SUM( total ) DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( 'found a vendor with completed sales', $vendor > 0 );
if ( ! $vendor ) {
	echo "\n$fails FAILED\n";
	return;
}

$repo  = new CommissionRuleRepository();
$order = static fn() => (object) array( 'id' => 0, 'vendor_id' => $vendor, 'service_id' => 0, 'total' => 100, 'currency' => '' );

// Count the volume rule's earnings query.
$sums = 0;
$spy  = static function ( $sql ) use ( &$sums ) {
	if ( false !== stripos( $sql, 'SUM( total )' ) && false !== stripos( $sql, "status = 'completed'" ) ) {
		++$sums;
	}
	return $sql;
};

$run = static function ( array $rule ) use ( $repo, $order, $spy, &$sums ) {
	$id = $repo->create( $rule );
	$sums = 0;
	add_filter( 'query', $spy );
	$breakdown = CommissionService::compute_breakdown( 100.0, $order() );
	remove_filter( 'query', $spy );
	$repo->delete( $id );
	return array( $breakdown, $sums, $id );
};

$base = array(
	'rule_type'  => 'volume',
	'priority'   => -1000,
	'is_active'  => 1,
	'conditions' => array( 'min_earnings' => 0.01 ),
);

[ $flat, $flat_sums, $flat_id ] = $run( $base + array( 'name' => 'QA-TIER flat', 'rate_type' => 'flat', 'rate' => 7 ) );
$check( 'the flat volume rule was created', $flat_id > 0 );
$check( 'a flat volume rule sets the fee', abs( (float) $flat['platform_fee'] - 7.0 ) < 0.001, wp_json_encode( $flat ) );
$check( 'its earnings were summed once, not once per filter', 1 === $flat_sums, "{$flat_sums} queries" );

[ $pct, $pct_sums ] = $run( $base + array( 'name' => 'QA-TIER pct', 'rate_type' => 'percentage', 'rate' => 12 ) );
$check( 'a percentage volume rule sets the rate', abs( (float) $pct['platform_fee'] - 12.0 ) < 0.001, wp_json_encode( $pct ) );
$check( 'its earnings were summed once too', 1 === $pct_sums, "{$pct_sums} queries" );

// A second computation for the same order object matches afresh, not from a leftover.
$id    = $repo->create( $base + array( 'name' => 'QA-TIER again', 'rate_type' => 'flat', 'rate' => 5 ) );
$same  = $order();
CommissionService::compute_breakdown( 100.0, $same );
$sums = 0;
add_filter( 'query', $spy );
$again = CommissionService::compute_breakdown( 100.0, $same );
remove_filter( 'query', $spy );
$repo->delete( $id );
$check( 'a repeat computation re-checks the rules', 1 === $sums && abs( (float) $again['platform_fee'] - 5.0 ) < 0.001, "{$sums} queries, fee {$again['platform_fee']}" );

$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_pro_commission_rules WHERE name LIKE %s", 'QA-TIER%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$check( 'test rules removed', 0 === $left, "{$left} left" );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
