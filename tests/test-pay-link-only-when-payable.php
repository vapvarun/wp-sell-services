<?php
/**
 * No pay link is offered for an order the pay page would refuse
 * (Basecamp 10545751953).
 *
 * REST checkout_url excluded only payment_status paid, so a cancelled or
 * refunded unpaid order, a locked milestone phase and an extension on hold all
 * carried a link to a page that then said no. The extension view showed
 * "Accept & Pay" on a quote that was on hold. Both now ask
 * wpss_get_order_payment_refusal(), the rule the pay page applies.
 * wpss_get_pay_order_url() itself is unchanged: it also builds links for the
 * login return and store-rail fallback.
 *
 * Run: wp eval-file tests/test-pay-link-only-when-payable.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$f      = wpss_test_fixtures();
$unpaid = array(
	'status'         => 'pending_payment',
	'payment_status' => 'pending',
	'paid_at'        => null,
);
$url    = static function ( int $id ): ?string {
	$res = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/orders/' . $id ) );
	return 200 === $res->get_status() ? ( $res->get_data()['checkout_url'] ?? null ) : 'HTTP ' . $res->get_status();
};

wp_set_current_user( $f->buyer );

$payable = ( $f->order )( $unpaid );
$check( 'an order that can be paid carries checkout_url', is_string( $url( $payable ) ) && 0 !== strpos( (string) $url( $payable ), 'HTTP' ) );

foreach ( array( 'cancelled', 'refunded' ) as $status ) {
	$id = ( $f->order )( array( 'status' => $status ) + $unpaid );
	$check( "an unpaid {$status} order carries no checkout_url", null === $url( $id ) );
}

// Two phases of one contract: the second is locked until the first closes.
$contract = ( $f->order )( array( 'platform' => 'request' ) );
$phase    = array(
	'platform'          => \WPSellServices\Services\MilestoneService::ORDER_TYPE,
	'platform_order_id' => $contract,
) + $unpaid;
$first    = ( $f->order )( $phase );
$second   = ( $f->order )( $phase );
$check( 'fixture: the second phase is locked', wpss_is_order_phase_locked( wpss_get_order( $second ) ) );
$check( 'a locked milestone phase carries no checkout_url', null === $url( $second ) );
$check( '  the first phase still does', is_string( $url( $first ) ) );

// An extension whose order went into dispute is on hold.
$disputed  = ( $f->order )( array( 'status' => 'disputed' ) );
$extension = ( $f->order )(
	array(
		'platform'          => \WPSellServices\Services\ExtensionOrderService::ORDER_TYPE,
		'platform_order_id' => $disputed,
	) + $unpaid
);
$check( 'an extension on hold carries no checkout_url', null === $url( $extension ) );

$current_order = wpss_get_order( $extension );
$user_id       = (int) $f->buyer;
ob_start();
include wpss_locate_template( 'order/extension-view.php' );
$html = (string) ob_get_clean();
$check( 'the extension view shows no Accept & Pay while on hold', false === strpos( $html, 'Accept &amp; Pay' ) && false === strpos( $html, 'Accept & Pay' ) );
$check( '  it says why', false !== strpos( $html, esc_html( wpss_get_order_payment_refusal( $current_order ) ) ) );
$check( '  and Decline stays available', false !== strpos( $html, 'wpss-extension-decline-btn' ) );

wp_set_current_user( 0 );

exit( $fails ? 1 : 0 );
