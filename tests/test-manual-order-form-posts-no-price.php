<?php
/**
 * The Create Order form posts no price of its own, so a package order stays a package order.
 *
 * Run: wp eval-file tests/test-manual-order-form-posts-no-price.php
 *
 * The form carried a hidden "subtotal" the script filled with the package price; the server
 * read any positive subtotal as a price the admin typed, so every order became "Custom order"
 * and, with an override total, showed a $60 line against a $137.50 total.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Pages\ManualOrderPage;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

wp_set_current_user( 1 );
ob_start();
( new ManualOrderPage() )->render_page();
$html = ob_get_clean();
$check( 'the form has no subtotal field', ! preg_match( '/name=["\']subtotal["\']/', $html ) );
$check( 'the form script does not write one', ! str_contains( (string) file_get_contents( WPSS_PLUGIN_DIR . 'assets/js/admin-manual-order.js' ), 'calculated-subtotal' ) );

$service = 0;
$vendor  = 0;
foreach ( get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'numberposts' => 50 ) ) as $p ) {
	if ( wpss_is_vendor( (int) $p->post_author ) && get_post_meta( $p->ID, '_wpss_packages', true ) ) {
		$service = $p->ID;
		$vendor  = (int) $p->post_author;
		break;
	}
}
$price = new ReflectionMethod( ManualOrderPage::class, 'price_manual_order' );
$run   = static fn( float $override ) => $price->invoke( new ManualOrderPage(), $service, '0', $vendor, 0.0, array(), $override, 10.0 );

$pkg = $run( 0.0 );
$check( 'a package with nothing typed keeps the package', ! is_wp_error( $pkg ) && null !== $pkg['package_id'] );
$ovr = $run( 137.5 );
$check( 'an override total is the whole total', ! is_wp_error( $ovr ) && 137.5 === (float) $ovr['total'] && 137.5 === (float) $ovr['subtotal'] + (float) $ovr['addons_total'] );

exit( $fails ? 1 : 0 );
