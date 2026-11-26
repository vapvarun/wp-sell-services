<?php
/**
 * The pay page's "Back to order" link always leads to an order
 * (Basecamp 10511340927).
 *
 * The refusal branch (order cancelled, already paid, phase locked) read
 * platform_order_id as the parent order. On a proposal order that column is
 * the buyer request, so the link was wpss_get_order_url( <request id> ) - empty.
 * Both the refusal link and the header link now share one rule.
 *
 * Run: wp eval-file tests/test-pay-page-back-link.php
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

$f       = wpss_test_fixtures();
$request = wp_insert_post(
	array(
		'post_type'   => 'wpss_request',
		'post_status' => 'publish',
		'post_title'  => 'QA-RFT back link request',
		'post_author' => $f->buyer,
	)
);
$render  = static function ( int $order_id ): string {
	$method = new ReflectionMethod( \WPSellServices\Integrations\Standalone\StandaloneCheckoutProvider::class, 'render_pay_order_checkout' );
	$method->setAccessible( true );
	return (string) $method->invoke( new \WPSellServices\Integrations\Standalone\StandaloneCheckoutProvider(), $order_id );
};
$href    = static function ( string $html ): string {
	return preg_match( '/<a class="wpss-btn wpss-btn--secondary" href="([^"]*)"/', $html, $m ) ? html_entity_decode( $m[1] ) : '';
};

try {
	wp_set_current_user( $f->buyer );

	// A proposal order that can no longer be paid (cancelled): the refusal page.
	$proposal = ( $f->order )(
		array(
			'platform'          => 'request',
			'platform_order_id' => $request,
			'status'            => 'cancelled',
			'payment_status'    => 'pending',
			'paid_at'           => null,
		)
	);
	$html     = $render( $proposal );
	$check( 'a refused proposal order shows Back to order', false !== strpos( $html, 'Back to order' ) );
	$check( '  linking to the order itself, not empty', '' !== $href( $html ) && wpss_get_order_url( $proposal ) === $href( $html ) );

	// A paid milestone phase is refused too, and goes back to its parent order.
	$parent = ( $f->order )( array() );
	$phase  = ( $f->order )(
		array(
			'platform'          => \WPSellServices\Services\MilestoneService::ORDER_TYPE,
			'platform_order_id' => $parent,
		)
	);
	$html   = $render( $phase );
	$check( 'a refused milestone phase links back to its parent order', '' !== $href( $html ) && wpss_get_order_url( $parent ) === $href( $html ) );
} finally {
	wp_set_current_user( 0 );
	wp_delete_post( $request, true );
}

exit( $fails ? 1 : 0 );
