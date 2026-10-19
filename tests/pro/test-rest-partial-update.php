<?php
/**
 * A PUT that names one field changes that field and nothing else, on the Pro
 * subscription-plan and commission-rule routes (Basecamp 10408191220,
 * 10407786815, 10407787331, 10407787480).
 *
 * Both routes validated the whole object on every PUT, so {"is_active":false}
 * answered 400 for a missing name; skipping the validation would have reset
 * every unnamed field to its default.
 *
 * Run: wp eval-file tests/pro/test-rest-partial-update.php
 *
 * @package WPSellServices
 */

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $admin || ! class_exists( '\WPSellServicesPro\TieredCommission\CommissionRuleRepository' ) ) {
	echo "SKIP  needs an administrator and Pro active\n";
	return;
}
wp_set_current_user( (int) $admin[0] );

$call = static function ( string $method, string $path, array $body = array() ) {
	$req = new WP_REST_Request( $method, '/wpss/v1' . $path );
	$req->set_header( 'content-type', 'application/json' );
	$req->set_body( wp_json_encode( $body ) );
	return rest_do_request( $req );
};

// ---- Subscription plans ---------------------------------------------------
$res  = $call( 'POST', '/subscription-plans', array( 'name' => 'QA-RFT plan', 'slug' => 'qa-rft-plan', 'price' => 19, 'billing_period' => 'yearly', 'max_services' => 7, 'max_featured' => 2, 'commission_override' => 12.5, 'features' => array( 'one', 'two' ), 'is_active' => true ) );
$plan = (int) ( $res->get_data()['id'] ?? 0 );
$check( 'plan created', 201 === $res->get_status() && $plan > 0 );

$res = $call( 'PUT', "/subscription-plans/{$plan}", array( 'is_active' => false ) );
$d   = $res->get_data();
$check( 'plan: PUT {is_active:false} is 200 (was 400 missing name)', 200 === $res->get_status() && false === $d['is_active'] );
$check( 'plan: nothing else reset', 'QA-RFT plan' === $d['name'] && 'qa-rft-plan' === $d['slug'] && 'yearly' === $d['billing_period'] && 7 === $d['max_services'] && 2 === $d['max_featured'] && 12.5 === $d['commission_override'] && array( 'one', 'two' ) === $d['features'] );

$res = $call( 'PUT', "/subscription-plans/{$plan}", array( 'name' => 'QA-RFT renamed' ) );
$check( 'plan: PUT name alone is 200 and keeps is_active false', 200 === $res->get_status() && 'QA-RFT renamed' === $res->get_data()['name'] && false === $res->get_data()['is_active'] );

$res = $call( 'PUT', "/subscription-plans/{$plan}", array( 'price' => 12 ) );
$check( 'plan: PUT price alone is 200 and keeps the name', 200 === $res->get_status() && 12.0 === (float) $res->get_data()['price'] && 'QA-RFT renamed' === $res->get_data()['name'] );

$res = $call( 'PUT', "/subscription-plans/{$plan}", array( 'commission_override' => null ) );
$check( 'plan: commission override can still be cleared', null === $res->get_data()['commission_override'] );

$res = $call( 'PUT', "/subscription-plans/{$plan}", array( 'name' => '' ) );
$check( 'plan: naming a blank name is still refused', 400 === $res->get_status() );

$call( 'DELETE', "/subscription-plans/{$plan}" );

// ---- Commission rules -----------------------------------------------------
$res  = $call( 'POST', '/commission-rules', array( 'rule_type' => 'category', 'name' => 'QA-RFT rule', 'rate' => 7, 'rate_type' => 'percentage', 'priority' => 4, 'conditions' => array( 'category_ids' => array( '3' ) ) ) );
$rule = (int) ( $res->get_data()['id'] ?? 0 );
$check( 'rule created, response carries is_active', 201 === $res->get_status() && true === ( $res->get_data()['is_active'] ?? null ) );

$res = $call( 'GET', "/commission-rules/{$rule}" );
$check( 'rule: GET carries is_active', array_key_exists( 'is_active', $res->get_data() ) );

$res = $call( 'PUT', "/commission-rules/{$rule}", array( 'is_active' => false ) );
$d   = $res->get_data();
$check( 'rule: PUT {is_active:false} is 200 (was 400 invalid type)', 200 === $res->get_status() && false === $d['is_active'] );
$check( 'rule: nothing else reset', 'QA-RFT rule' === $d['name'] && 7.0 === (float) $d['rate'] && 'percentage' === $d['rate_type'] && 4 === $d['priority'] && 'category' === $d['type'] && array( '3' ) === ( $d['conditions']['category_ids'] ?? null ) );

$res = $call( 'PUT', "/commission-rules/{$rule}", array( 'rate' => 9 ) );
$check( 'rule: PUT rate alone is 200 and stays inactive', 200 === $res->get_status() && 9.0 === (float) $res->get_data()['rate'] && false === $res->get_data()['is_active'] );

$res = $call( 'PUT', "/commission-rules/{$rule}", array( 'rate' => 150 ) );
$check( 'rule: an invalid rate is still refused', 400 === $res->get_status() );

$res = $call( 'PUT', "/commission-rules/{$rule}", array( 'conditions' => array() ) );
$check( 'rule: clearing a category rule\'s conditions is refused', 400 === $res->get_status() && 'wpss_missing_conditions' === $res->get_data()['code'] );

foreach (
	array(
		'category'     => array(),
		'volume'       => array( 'min_earnings' => 0 ),
		'seller_level' => array(),
	) as $type => $conditions
) {
	$res = $call( 'POST', '/commission-rules', array( 'rule_type' => $type, 'name' => 'QA-RFT bare', 'rate' => 5, 'rate_type' => 'percentage', 'conditions' => $conditions ) );
	$check( "rule: creating a {$type} rule with no condition is refused", 400 === $res->get_status() );
	if ( 201 === $res->get_status() ) {
		$call( 'DELETE', '/commission-rules/' . (int) $res->get_data()['id'] );
	}
}

$res = $call( 'POST', '/commission-rules', array( 'rule_type' => 'volume', 'name' => 'QA-RFT volume', 'rate' => 5, 'rate_type' => 'percentage', 'conditions' => array( 'min_earnings' => 1000 ) ) );
$check( 'rule: a volume rule with a threshold is accepted', 201 === $res->get_status() );
$vol = (int) ( $res->get_data()['id'] ?? 0 );

// ---- Stripe Connect settings ----------------------------------------------
$saved = array( get_option( 'wpss_pro_stripe_connect_enabled', false ), get_option( 'wpss_pro_connect_fee_percent', 20.0 ) );
update_option( 'wpss_pro_stripe_connect_enabled', 1 );
update_option( 'wpss_pro_connect_fee_percent', 7.5 );

$res = $call( 'PUT', '/stripe-connect/settings', array( 'fee' => 5 ) );
$check( 'connect: PUT fee alone keeps Connect switched on', 200 === $res->get_status() && 1 === (int) get_option( 'wpss_pro_stripe_connect_enabled' ) && 5.0 === (float) get_option( 'wpss_pro_connect_fee_percent' ) );
$res = $call( 'PUT', '/stripe-connect/settings', array( 'enabled' => true ) );
$check( 'connect: PUT enabled alone keeps the fee (not reset to 20)', 5.0 === (float) get_option( 'wpss_pro_connect_fee_percent' ) && true === $res->get_data()['enabled'] );
$call( 'PUT', '/stripe-connect/settings', array( 'enabled' => false ) );
$check( 'connect: PUT enabled:false switches it off', 0 === (int) get_option( 'wpss_pro_stripe_connect_enabled' ) );

update_option( 'wpss_pro_stripe_connect_enabled', (int) $saved[0] );
update_option( 'wpss_pro_connect_fee_percent', $saved[1] );

$call( 'DELETE', "/commission-rules/{$rule}" );
$call( 'DELETE', "/commission-rules/{$vol}" );
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_commission_rules WHERE name LIKE 'QA-RFT%'" );
$wpdb->query( "DELETE FROM {$wpdb->prefix}wpss_subscription_plans WHERE name LIKE 'QA-RFT%'" );
wp_set_current_user( 0 );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
