<?php
/**
 * Every door that saves a vendor subscription plan applies the same rules.
 *
 * The settings screen checked a Stripe Price ID with Stripe before saving; the
 * REST route did not, so POST /subscription-plans could store an archived or
 * one-time price that checkout then refused. The check now lives in
 * PlanRepository::validate(), which both doors call.
 *
 * Run: wp eval-file tests/test-plan-save-one-rule.php
 *
 * Stripe is stubbed; any plan the test creates is deleted.
 *
 * @package WPSellServices
 */

use WPSellServicesPro\VendorSubscriptions\PlanRepository;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! class_exists( PlanRepository::class ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

// Stripe keys present, and Stripe answers that the price is archived.
add_filter(
	'option_wpss_stripe_settings',
	static fn( $v ) => array_merge( is_array( $v ) ? $v : array(), array( 'test_mode' => '1', 'test_secret_key' => 'sk_test_qa_plan_rule' ) ),
	99
);
$stripe_calls = 0;
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) use ( &$stripe_calls ) {
		if ( false === strpos( $url, 'api.stripe.com' ) ) {
			return $pre;
		}
		++$stripe_calls;
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'id' => 'price_qa_archived', 'active' => false, 'type' => 'recurring', 'recurring' => array( 'interval' => 'month', 'interval_count' => 1 ) ) ),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);

wp_set_current_user( (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] );

$slug    = 'qa-plan-rule-' . strtolower( wp_generate_password( 6, false ) );
$request = new WP_REST_Request( 'POST', '/wpss/v1/subscription-plans' );
$request->set_body_params(
	array(
		'name'            => 'QA plan rule',
		'slug'            => $slug,
		'price'           => 9,
		'billing_period'  => 'monthly',
		'max_services'    => 5,
		'stripe_price_id' => 'price_qa_archived',
	)
);
$response = rest_do_request( $request );
$data     = (array) $response->get_data();

$check( 'REST asked Stripe about the price', $stripe_calls > 0, "{$stripe_calls} calls" );
$check( 'REST refuses an archived Stripe price', 400 === $response->get_status(), $response->get_status() . ' ' . wp_json_encode( $data ) );
$check( 'with the reason the settings screen gives', 'wpss_invalid_stripe_price' === ( $data['code'] ?? '' ) && false !== stripos( (string) ( $data['message'] ?? '' ), 'archived' ) );

$repo = new PlanRepository();
$made = $repo->get_by_slug( $slug );
$check( 'no plan was stored', null === $made );

$direct = PlanRepository::validate(
	array(
		'name'            => 'QA',
		'slug'            => 'qa',
		'price'           => 1,
		'billing_period'  => 'monthly',
		'stripe_price_id' => 'price_qa_archived',
	)
);
$check( 'the shared rule refuses it for every writer', is_wp_error( $direct ) && 'wpss_invalid_stripe_price' === $direct->get_error_code() );
$check( 'a plan with no Stripe price is still allowed', true === PlanRepository::validate( array( 'name' => 'QA', 'slug' => 'qa', 'price' => 1, 'billing_period' => 'monthly', 'stripe_price_id' => '' ) ) );

if ( $made ) {
	$repo->delete( (int) $made->id );
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
