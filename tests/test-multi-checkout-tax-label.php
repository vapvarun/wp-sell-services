<?php
/**
 * The multi-item checkout summary shows the tax that is charged, and a cleared
 * tax label never prints as " (20%)" (Basecamp 10403205865, 10403205035).
 *
 * Run: wp eval-file tests/test-multi-checkout-tax-label.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// A service with a price: a free one is never taxed, whatever the rate.
$ids = array();
foreach ( get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'posts_per_page' => 20, 'fields' => 'ids' ) ) as $candidate ) {
	$priced = \WPSellServices\Checkout\CheckoutIntentService::price_service_line( (int) $candidate, 0, 1, array() );
	if ( ! is_wp_error( $priced ) && (float) $priced['subtotal'] > 0 ) {
		$ids = array( $candidate );
		break;
	}
}
if ( ! $ids ) {
	echo "SKIP  no priced published service\n";
	return;
}

// The summary only renders for a signed-in buyer who is not the seller.
$author = (int) get_post_field( 'post_author', $ids[0] );
$buyers = get_users( array( 'exclude' => array( $author ), 'number' => 1, 'fields' => 'ID' ) );
if ( ! $buyers ) {
	echo "SKIP  no second user to buy as\n";
	return;
}
$prev_user = get_current_user_id();
wp_set_current_user( (int) $buyers[0] );

$saved    = get_option( 'wpss_tax', array() );
$provider = new \WPSellServices\Integrations\Standalone\StandaloneCheckoutProvider();
$render   = new ReflectionMethod( $provider, 'render_multi_checkout_form' );
$cart     = array( 'k1' => array( 'service_id' => (int) $ids[0], 'package_id' => 0, 'quantity' => 1, 'addons' => array() ) );
$html     = static function () use ( $render, $provider, $cart ) {
	return (string) $render->invoke( $provider, $cart );
};

update_option( 'wpss_tax', array( 'enable_tax' => true, 'tax_label' => 'VAT', 'tax_rate' => 20, 'tax_included' => false ) );

$out = $html();
$check( 'taxed: summary shows VAT (20%)', str_contains( $out, 'VAT (20%)' ) );

$zero = static fn() => 0.0;
add_filter( 'wpss_checkout_tax_rate', $zero );
$out = $html();
$check( 'filter sets 0%: no tax line at all', ! str_contains( $out, 'VAT (20%)' ) && ! str_contains( $out, 'VAT (0%)' ) );
remove_filter( 'wpss_checkout_tax_rate', $zero );

$ten = static fn() => 10.0;
add_filter( 'wpss_checkout_tax_rate', $ten );
$out = $html();
$check( 'filter sets 10%: summary says 10%, not the option\'s 20%', str_contains( $out, 'VAT (10%)' ) && ! str_contains( $out, 'VAT (20%)' ) );
remove_filter( 'wpss_checkout_tax_rate', $ten );

update_option( 'wpss_tax', array( 'enable_tax' => true, 'tax_label' => '', 'tax_rate' => 20, 'tax_included' => false ) );
$check( 'calc: a blank stored label falls back to Tax', 'Tax' === wpss_calculate_tax( 100 )['label'] );
$out = $html();
$check( 'blank label: summary shows Tax (20%), never a bare (20%)', str_contains( $out, 'Tax (20%)' ) && ! preg_match( '/>\s*\(20%\)/', $out ) );

$sanitized = ( new \WPSellServices\Admin\Settings() )->sanitize_tax_settings( array( 'enable_tax' => 1, 'tax_label' => '   ', 'tax_rate' => 20 ) );
$check( 'settings save: a cleared label is stored as Tax', 'Tax' === $sanitized['tax_label'] );

update_option( 'wpss_tax', $saved );
wp_set_current_user( $prev_user );
echo $fails ? "{$fails} FAILED\n" : "All passed\n";
