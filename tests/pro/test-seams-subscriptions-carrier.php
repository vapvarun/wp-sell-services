<?php
/**
 * Pro seams: a plan's max_featured is enforced, subscribe() is quiet when the vendor already
 * has a row, the limit message links to the canonical subscription URL, and the Woo carrier
 * product redirects instead of 404ing and stays out of the sitemap (Basecamp 10408191922,
 * 10408190581, 10407726798).
 *
 * Run: wp eval-file tests/pro/test-seams-subscriptions-carrier.php
 *
 * @package WPSellServices
 */

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

use WPSellServicesPro\VendorSubscriptions\PlanEnforcer;
use WPSellServicesPro\VendorSubscriptions\PlanRepository;
use WPSellServicesPro\VendorSubscriptions\SubscriptionManager;
use WPSellServicesPro\VendorSubscriptions\VendorSubscriptionService;

if ( ! class_exists( PlanEnforcer::class ) ) {
	echo "SKIP  Pro vendor subscriptions not loaded\n";
	return;
}

$was_enabled = get_option( 'wpss_pro_vendor_subscriptions_enabled', null );
update_option( 'wpss_pro_vendor_subscriptions_enabled', 1 );

$vendor = wp_insert_user( array( 'user_login' => 'qa_seam_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$plan   = ( new PlanRepository() )->create( array( 'name' => 'QA seam plan', 'slug' => 'qa-seam-' . wp_rand(), 'price' => 5, 'billing_period' => 'monthly', 'max_services' => 5, 'max_featured' => 1 ) );
$subs   = new VendorSubscriptionService();
$mine   = array();
for ( $i = 0; $i < 3; $i++ ) {
	$mine[] = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_title' => "QA seam {$i}", 'post_status' => 'publish', 'post_author' => $vendor ) );
}
$other = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_title' => 'QA seam other', 'post_status' => 'publish', 'post_author' => 1 ) );

// --- subscribe(): the second call must not raise a DB error --------------------
$wpdb->suppress_errors( false );
$wpdb->last_error = '';
$first            = $subs->subscribe( $vendor, $plan );
$second           = $subs->subscribe( $vendor, $plan );
$check( 'subscribe() twice leaves no database error', '' === $wpdb->last_error );
$check( 'subscribe() twice returns the same row', $first > 0 && $first === $second );

// --- max_featured ----------------------------------------------------------------
$enforcer = new PlanEnforcer();
wp_set_current_user( $vendor );
$check( 'plan allows featuring the first own service', true === $enforcer->filter_can_feature( false, $mine[0] ) );
$check( 'the wpss_user_can_feature_service filter is wired', has_filter( 'wpss_user_can_feature_service' ) !== false );
update_post_meta( $mine[0], '_wpss_featured', 1 );
$check( 'at the plan limit a second service cannot be featured', false === $enforcer->filter_can_feature( false, $mine[1] ) );
$check( 'an already-featured service can always be switched off', true === $enforcer->filter_can_feature( false, $mine[0] ) );
$check( 'someone else\'s service is never featurable', false === $enforcer->filter_can_feature( false, $other ) );
$check( 'an administrator\'s yes is passed through', true === $enforcer->filter_can_feature( true, $other ) );
$wpdb->update( $wpdb->prefix . 'wpss_pro_subscription_plans', array( 'max_featured' => 0 ), array( 'id' => $plan ) );
delete_post_meta( $mine[0], '_wpss_featured' );
$check( 'max_featured 0 means none', false === $enforcer->filter_can_feature( false, $mine[1] ) );
$wpdb->delete( $wpdb->prefix . 'wpss_pro_vendor_subscriptions', array( 'vendor_id' => $vendor ) );
$check( 'no subscription, no featuring', false === $enforcer->filter_can_feature( false, $mine[1] ) );

// --- Limit message link -----------------------------------------------------------
$msg = ( new SubscriptionManager() )->enrich_service_limit_message( 'x' );
$check( 'service-limit message carries an Upgrade link', false !== strpos( $msg, '<a href=' ) );
$check( 'the link is the canonical subscription URL', false !== strpos( $msg, wpss_get_dashboard_url( 'subscription' ) ) );

// --- Carrier product ----------------------------------------------------------------
if ( class_exists( 'WooCommerce' ) && class_exists( '\WPSellServicesPro\Integrations\WooCommerce\WCServiceCarrier' ) ) {
	$carrier = new \WPSellServicesPro\Integrations\WooCommerce\WCServiceCarrier();
	$cid     = $carrier->get_carrier_id();
	if ( $cid ) {
		wp_set_current_user( 0 );
		$single               = new WP_Query();
		$single->query( array( 'p' => $cid, 'post_type' => 'product' ) );
		$GLOBALS['wp_the_query'] = $single;
		$carrier->hide_carrier_from_shop( $single );
		$check( 'the carrier\'s own URL is not excluded from its main query', ! in_array( $cid, (array) $single->get( 'post__not_in' ), true ) );
		$shop                    = new WP_Query( array( 'post_type' => 'product' ) );
		$GLOBALS['wp_the_query'] = $shop;
		$carrier->hide_carrier_from_shop( $shop );
		$check( 'the shop listing still hides the carrier', in_array( $cid, (array) $shop->get( 'post__not_in' ), true ) );
		$check( 'the sitemap leaves the carrier out', in_array( $cid, $carrier->hide_carrier_from_sitemap( array(), 'product' )['post__not_in'], true ) );
	} else {
		echo "SKIP  no carrier product on this site\n";
	}
}

// --- Cleanup --------------------------------------------------------------------------
foreach ( array_merge( $mine, array( $other ) ) as $pid ) {
	wp_delete_post( $pid, true );
}
( new PlanRepository() )->delete( $plan );
$wpdb->delete( $wpdb->prefix . 'wpss_pro_vendor_subscriptions', array( 'vendor_id' => $vendor ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vendor );
null === $was_enabled ? delete_option( 'wpss_pro_vendor_subscriptions_enabled' ) : update_option( 'wpss_pro_vendor_subscriptions_enabled', $was_enabled );
wp_set_current_user( 0 );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
