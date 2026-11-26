<?php
/**
 * Every surface shows when a buyer request closes (Basecamp 10511341034).
 *
 * The admin Requests list ("Open until"), the BuyerRequest model and the
 * proposals API read _wpss_deadline, which only the demo seeder writes, so
 * every real request showed an em dash. They now share
 * BuyerRequestService::get_expires_at(): _wpss_expires_at first, the old
 * key as fallback.
 *
 * Run: wp eval-file tests/test-request-open-until-one-reader.php
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\RequestListScreen;
use WPSellServices\API\ProposalsController;
use WPSellServices\Models\BuyerRequest;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$expires = gmdate( 'Y-m-d H:i:s', strtotime( '+12 days' ) );
$request = wp_insert_post( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'post_title' => 'QA-RFT open until', 'post_author' => 1 ) );
update_post_meta( $request, '_wpss_status', 'open' );
update_post_meta( $request, '_wpss_expires_at', $expires );
$legacy = wp_insert_post( array( 'post_type' => 'wpss_request', 'post_status' => 'publish', 'post_title' => 'QA-RFT legacy deadline', 'post_author' => 1 ) );
update_post_meta( $legacy, '_wpss_deadline', '2031-05-04' );

try {
	$label = wpss_date_label( $expires );

	ob_start();
	( new ReflectionClass( RequestListScreen::class ) )->newInstanceWithoutConstructor()->render_column( 'wpss_deadline', $request );
	$cell = trim( (string) ob_get_clean() );
	$check( 'admin Requests list shows the expiry date', $label === $cell, "cell '{$cell}', expected '{$label}'" );

	$check( 'BuyerRequest model carries the expiry', $expires === BuyerRequest::from_post( get_post( $request ) )->deadline );
	$check( 'model falls back to the old key for legacy data', '2031-05-04' === BuyerRequest::from_post( get_post( $legacy ) )->deadline );

	$controller = ( new ReflectionClass( ProposalsController::class ) )->newInstanceWithoutConstructor();
	$prepare    = new ReflectionMethod( ProposalsController::class, 'prepare_proposal_for_response' );
	$prepare->setAccessible( true );
	$pp = new ReflectionProperty( ProposalsController::class, 'proposal_service' );
	$pp->setAccessible( true );
	$pp->setValue( $controller, new \WPSellServices\Services\ProposalService() );
	$data = $prepare->invoke( $controller, (object) array( 'id' => 0, 'request_id' => $request, 'vendor_id' => 1, 'service_id' => 0, 'proposed_price' => 10, 'proposed_days' => 3, 'status' => 'pending', 'cover_letter' => '', 'created_at' => $expires ), true );
	$check( 'proposals API request.deadline is the expiry (ISO 8601)', wpss_rest_date( $expires ) === ( $data['request']['deadline'] ?? null ), wp_json_encode( $data['request']['deadline'] ?? null ) );
} finally {
	wp_delete_post( $request, true );
	wp_delete_post( $legacy, true );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
