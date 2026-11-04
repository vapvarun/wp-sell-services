<?php
/**
 * A vendor who cancels a paid plan keeps it until the period they paid for ends.
 *
 * Cancelling told the vendor "will remain active until the end of the current
 * billing period" and asked Stripe for exactly that (cancel_at_period_end), then
 * marked the local row cancelled at once - so the plan gate refused new
 * services the same second, for a period already paid. The cancel is now
 * recorded and the plan stays live; Stripe's subscription.deleted ends it, and
 * the period end ends it if that webhook never comes. Every gate reads one
 * answer, VendorSubscriptionService::is_live().
 *
 * Run: wp eval-file tests/pro/test-subscription-cancel-keeps-paid-period.php
 *
 * Uses a throwaway vendor and plan; no request leaves the machine.
 *
 * @package WPSellServices
 */

use WPSellServicesPro\VendorSubscriptions\PlanRepository;
use WPSellServicesPro\VendorSubscriptions\SubscriptionBillingHandler;
use WPSellServicesPro\VendorSubscriptions\VendorSubscriptionService;

require_once dirname( __DIR__ ) . '/exit-on-fail.php';
require_once dirname( __DIR__ ) . '/fixtures.php';

if ( ! class_exists( VendorSubscriptionService::class ) ) {
	echo "SKIP  Pro is not active.\n";
	return;
}

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table   = $wpdb->prefix . 'wpss_pro_vendor_subscriptions';
$vendor  = wpss_test_fixtures()->vendor;
$service = new VendorSubscriptionService();
$billing = new SubscriptionBillingHandler();

// Plans on and required; a Stripe key so the cancel call is attempted; Stripe answered locally.
foreach ( array( 'wpss_pro_vendor_subscriptions_enabled', 'wpss_pro_require_vendor_subscription' ) as $opt ) {
	add_filter( "pre_option_{$opt}", '__return_true' );
}
$stripe = static fn( $v ) => array( 'test_mode' => 1, 'test_secret_key' => 'sk_test_qa' ) + (array) $v;
add_filter( 'option_wpss_stripe_settings', $stripe );
$sent = array();
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( &$sent ) {
		$sent[] = $url . ' ' . ( $args['body'] ?? '' );
		return array( 'headers' => array(), 'body' => '{"id":"sub_qa","status":"active","cancel_at_period_end":true}', 'response' => array( 'code' => 200, 'message' => 'OK' ), 'cookies' => array() );
	},
	10,
	3
);

$plan = ( new PlanRepository() )->create( array( 'name' => 'QA plan', 'slug' => 'qa-plan-' . wp_generate_password( 4, false, false ), 'price' => 10, 'billing_period' => 'monthly', 'max_services' => 50, 'stripe_price_id' => 'price_qa', 'is_active' => 1 ) );
$check( 'made a plan', $plan > 0 );

$seed = static function ( string $stripe_id, string $period_end, ?string $cancelled_at = null ) use ( $wpdb, $table, $vendor, $plan, $service ) {
	$wpdb->delete( $table, array( 'vendor_id' => $vendor ) );
	$wpdb->insert( $table, array( 'vendor_id' => $vendor, 'plan_id' => $plan, 'stripe_subscription_id' => $stripe_id ?: null, 'status' => 'active', 'current_period_start' => gmdate( 'Y-m-d H:i:s', time() - 10 * DAY_IN_SECONDS ), 'current_period_end' => '' === $period_end ? null : $period_end, 'cancelled_at' => $cancelled_at ) );
	( new ReflectionMethod( $service, 'bust_vendor_cache' ) )->invoke( $service, $vendor );
};
$can_create = static fn() => (bool) apply_filters( 'wpss_vendor_can_create_service', true, $vendor );
$future     = gmdate( 'Y-m-d H:i:s', time() + 20 * DAY_IN_SECONDS );

// 1. Control: an active paid plan lets the vendor create.
$seed( 'sub_qa', $future );
$check( 'an active plan lets the vendor create a service (control)', $can_create() );

// 2. The vendor cancels: Stripe is asked to stop at the period end, and the plan keeps working.
$result = $billing->cancel_subscription( $vendor );
$row    = $service->get_vendor_subscription( $vendor );
$check( 'the cancel succeeds', ! empty( $result['success'] ), wp_json_encode( $result ) );
$check( 'Stripe is asked to cancel at the period end', 1 === count( array_filter( $sent, static fn( $s ) => false !== strpos( $s, 'subscriptions/sub_qa' ) && false !== strpos( $s, 'cancel_at_period_end=true' ) ) ) );
$check( 'the cancel is recorded', $row && ! empty( $row->cancelled_at ) );
$check( 'the paid period is not cut short: the vendor can still create', $can_create() );
$check( 'the dashboard can tell it is ending', VendorSubscriptionService::is_ending( $row ) );

// 3. The period runs out and the webhook never came: the plan ends anyway.
$seed( 'sub_qa', gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() - 5 * DAY_IN_SECONDS ) );
$check( 'a cancelled plan past its period end no longer lets the vendor create', ! $can_create() );

// 4. Stripe ends it at the period end.
$seed( 'sub_qa', $future, gmdate( 'Y-m-d H:i:s' ) );
$billing->handle_webhook( 'customer.subscription.deleted', array( 'id' => 'sub_qa' ), array() );
$check( "Stripe's subscription.deleted ends it", ! $can_create() && 'cancelled' === (string) $service->get_vendor_subscription( $vendor )->status );

// 5. A plan with no Stripe billing has no paid period to honour: it ends on cancel.
$seed( '', $future );
$service->cancel( $vendor );
$check( 'a local plan ends when cancelled', ! $can_create() );

// 5b. A cancel with no recorded period end has no paid time to honour: it ends at once.
$seed( 'sub_qa', '', gmdate( 'Y-m-d H:i:s' ) );
$check( 'a cancelled plan with no period end no longer lets the vendor create', ! $can_create() && ! VendorSubscriptionService::is_live( $service->get_vendor_subscription( $vendor ) ) );

// 6. An active plan with no cancel and a period end in the past (renewal pending) stays live.
$seed( 'sub_qa', gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ) );
$check( 'an uncancelled plan is not ended by its period date alone', $can_create() );

$wpdb->delete( $table, array( 'vendor_id' => $vendor ) );
$wpdb->delete( $wpdb->prefix . 'wpss_pro_subscription_plans', array( 'id' => $plan ) );
( new ReflectionMethod( $service, 'bust_vendor_cache' ) )->invoke( $service, $vendor );

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
