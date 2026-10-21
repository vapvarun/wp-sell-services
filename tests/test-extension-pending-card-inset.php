<?php
/**
 * The buyer and vendor "extra work" pending cards sit in the inset section, not flush with its edge.
 *
 * Run: wp eval-file tests/test-extension-pending-card-inset.php
 *
 * order-view.css pads .wpss-order-section--extension-cta; a section holding .wpss-extension-pending-card
 * without that class renders the card against the section border.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$src   = (string) file_get_contents( dirname( __DIR__ ) . '/templates/order/order-view.php' );
$cards = preg_match_all( '/<section class="([^"]*)">\s*<div class="wpss-extension-pending-card">/', $src, $m );
$flush = array_filter( $m[1], static fn( $c ) => false === strpos( $c, 'wpss-order-section--extension-cta' ) );

$fails = 0;
foreach ( array( 'two pending cards found (buyer, vendor)' => 2 === $cards, 'every pending card section carries the inset class' => ! $flush ) as $label => $ok ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
}
exit( $fails ? 1 : 0 );
