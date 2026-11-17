<?php
/**
 * A pending review has no "respond" link; the About the vendor box names the country.
 *
 * Run: wp eval-file tests/test-pending-review-hint-and-country-name.php
 *
 * Basecamp 10513126688: the vendor order page linked to a Reviews section that lists
 * approved reviews only, so a pending review sent the vendor to a page without it.
 * Basecamp 10516698806: the service page printed the stored code ("PT").
 *
 * These are source guards over two templates that need a full request to render;
 * the walk in docs/qa/walks covers the rendered result.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$root  = dirname( __DIR__ );
$order = (string) file_get_contents( $root . '/templates/order/order-view.php' );
$check(
	'respond hint needs an approved review',
	1 === preg_match( '/if \( \$is_vendor && \$wpss_order_review->is_approved\(\) && \'\' === \$wpss_order_review->response \)/', $order )
);

$view = (string) file_get_contents( $root . '/src/Frontend/SingleServiceView.php' );
$check( 'About the vendor resolves the country name', false !== strpos( $view, 'wpss_get_country_name( (string) $country )' ) );
$check( 'no raw country echo is left in the view', false === strpos( $view, 'esc_html( $country )' ) );

$check( 'helper turns PT into Portugal', 'Portugal' === wpss_get_country_name( 'PT' ) );
$check( 'helper keeps a free-text value', 'Atlantis' === wpss_get_country_name( 'Atlantis' ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nAll passed\n";
exit( $fails ? 1 : 0 );
