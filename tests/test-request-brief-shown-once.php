<?php
/**
 * A request order shows its brief once, and as a brief to confirm until the buyer confirms it (Basecamp 10553847193).
 *
 * Accepting a proposal stores request_description with a submitted_at stamp; the confirm form is
 * pre-filled from the same request and saved as description. The shared requirements partial
 * printed both, and headed the unconfirmed brief "Submitted" beside a "Submit requirements" button.
 *
 * Run: wp eval-file tests/test-request-brief-shown-once.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f     = wpss_test_fixtures();
$sid   = ( $f->service )( 'publish' );
$oid   = ( $f->order )( array( 'service_id' => $sid, 'status' => 'pending_requirements' ) );
$brief = 'QA brief: a logo and label set for a small bakery, warm colours.';
$table = $wpdb->prefix . 'wpss_order_requirements';

$store = static function ( array $data ) use ( $wpdb, $table, $oid ) {
	$wpdb->delete( $table, array( 'order_id' => $oid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert( $table, array( 'order_id' => $oid, 'field_data' => wp_json_encode( $data ), 'attachments' => '[]', 'submitted_at' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};
$render = static function ( string $viewer, string $status ) use ( $oid, $wpdb ): string {
	$wpdb->update( $wpdb->prefix . 'wpss_orders', array( 'status' => $status ), array( 'id' => $oid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	wp_cache_flush();
	ob_start();
	wpss_get_template_part(
		'order/requirements',
		'',
		array(
			'wpss_order'                => wpss_get_order( $oid ),
			'wpss_viewer'               => $viewer,
			'wpss_service_requirements' => array(),
		)
	);
	return wp_strip_all_tags( (string) ob_get_clean() );
};

try {
	// 1. Accepted, not yet confirmed.
	$store( array( 'request_title' => 'Bakery labels', 'request_description' => $brief, 'proposal_cover' => 'QA cover' ) );
	$out = $render( 'buyer', 'pending_requirements' );
	$check( 'unconfirmed: headed as a brief to confirm', str_contains( $out, 'Brief to confirm' ), $out );
	$check( 'unconfirmed: not called Submitted', ! preg_match( '/Submitted\s/', $out ), $out );
	$check( 'unconfirmed: the buyer is told to confirm it', str_contains( $out, 'Confirm it to start the order' ) );
	$check( 'unconfirmed: the vendor is told it waits for the buyer', str_contains( $render( 'vendor', 'pending_requirements' ), 'Waiting for the buyer to confirm' ) );

	// 2. Confirmed unchanged: the pre-filled description equals the request text.
	$store( array( 'request_title' => 'Bakery labels', 'request_description' => $brief, 'proposal_cover' => 'QA cover', 'description' => "  {$brief}\n" ) );
	$out = $render( 'vendor', 'in_progress' );
	$check( 'confirmed: the brief appears once', 1 === substr_count( $out, $brief ), (string) substr_count( $out, $brief ) );
	$check( 'confirmed: no "Request description" row', ! str_contains( $out, 'Request description' ) );
	$check( 'confirmed: headed Order Requirements with its date', str_contains( $out, 'Order Requirements' ) && (bool) preg_match( '/Submitted\s/', $out ) );

	// 3. The buyer edited the brief when confirming: both texts are real and both show.
	$store( array( 'request_description' => $brief, 'description' => 'QA edited brief, add a menu board.' ) );
	$out = $render( 'vendor', 'in_progress' );
	$check( 'edited: the original request and the confirmed brief both show', str_contains( $out, $brief ) && str_contains( $out, 'QA edited brief' ) );
} finally {
	$wpdb->delete( $table, array( 'order_id' => $oid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
