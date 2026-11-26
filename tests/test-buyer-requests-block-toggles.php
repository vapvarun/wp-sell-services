<?php
/**
 * The Buyer Requests block's Show budget / deadline / proposals toggles work
 * (Basecamp 10545808114).
 *
 * The block rendered the shared request card without passing its toggles, so
 * switching them off in the editor changed nothing. The card now takes
 * wpss_show_budget / wpss_show_deadline / wpss_show_offers (absent = show, so
 * the archive and the shortcode are unchanged).
 *
 * Run: wp eval-file tests/test-buyer-requests-block-toggles.php
 *
 * @package WPSellServices
 */

use WPSellServices\Blocks\BuyerRequests;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$request = wp_insert_post( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'post_title' => 'QA-RFT block toggles', 'post_content' => 'QA-RFT', 'post_author' => 1 ) );
update_post_meta( $request, '_wpss_status', 'open' );
update_post_meta( $request, '_wpss_budget_min', 50 );
update_post_meta( $request, '_wpss_budget_max', 90 );
update_post_meta( $request, '_wpss_expires_at', gmdate( 'Y-m-d H:i:s', strtotime( '+9 days' ) ) );

// Only this request's card: the block lists newest first and this is the newest.
$card = static function ( array $attrs ): string {
	$html = ( new BuyerRequests() )->render( array_merge( array( 'perPage' => 1 ), $attrs ) );
	return false !== strpos( $html, 'QA-RFT block toggles' ) ? $html : '';
};

try {
	$on = $card( array() );
	$check( 'defaults: the card renders', '' !== $on );
	$check( 'defaults: budget, open-until and proposals show', false !== strpos( $on, '>Budget<' ) && false !== strpos( $on, '>Open until<' ) && false !== strpos( $on, '>Proposals<' ) );

	$off = $card( array( 'showBudget' => false, 'showDeadline' => false, 'showOffers' => false ) );
	$check( 'toggles off: the card still renders', '' !== $off );
	$check( 'showBudget off hides the budget', false === strpos( $off, '>Budget<' ) );
	$check( 'showDeadline off hides open-until and the countdown', false === strpos( $off, '>Open until<' ) && false === strpos( $off, 'wpss-request-card__expires' ) );
	$check( 'showOffers off hides the proposal count', false === strpos( $off, '>Proposals<' ) );
} finally {
	wp_delete_post( $request, true );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
