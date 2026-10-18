<?php
/**
 * A suspended seller cannot take on new work through the dashboard's own AJAX doors, the same as REST
 * (Basecamp 10384354109, QA bounce 2026-10-18).
 *
 * Run: wp eval-file tests/test-ajax-standing-gate.php
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
$vendor = $f->vendor;
$sid    = ( $f->service )( 'draft' );

delete_user_meta( $vendor, 'wpss_account_status' );
$check( 'good standing: refusal is about the publish bar, not standing', ! str_contains( wpss_service_publish_refusal( $sid, $vendor ), 'suspended' ) );

update_user_meta( $vendor, 'wpss_account_status', 'suspended' );
$check( 'suspended: publish is refused', '' !== wpss_service_publish_refusal( $sid, $vendor ) );
wp_update_post( array( 'ID' => $sid, 'post_status' => 'publish' ) );
$check( 'suspended: refused even for an already-live service', '' !== wpss_service_publish_refusal( $sid, $vendor ) );


// Every door that starts new work carries the gate.
$root  = dirname( __DIR__ ) . '/src/Frontend/';
$ajax  = (string) file_get_contents( $root . 'AjaxHandlers.php' );
$wiz   = (string) file_get_contents( $root . 'ServiceWizard.php' );
$body  = static function ( string $src, string $fn ): string {
	$i = strpos( $src, 'function ' . $fn . '(' );
	return false === $i ? '' : substr( $src, $i, 2500 );
};
$check( 'service_action publish uses the gate', str_contains( $body( $ajax, 'service_action' ), 'wpss_service_publish_refusal' ) );
$check( 'bulk_action publish uses the gate', str_contains( $body( $ajax, 'bulk_action' ), 'wpss_service_publish_refusal' ) );
foreach ( array( 'add_portfolio_item', 'update_portfolio_item' ) as $fn ) {
	$check( "$fn checks standing", str_contains( $body( $ajax, $fn ), 'wpss_vendor_status_block' ) );
}
foreach ( array( 'ajax_save_draft', 'ajax_publish_service' ) as $fn ) {
	$check( "wizard $fn checks standing", str_contains( $body( $wiz, $fn ), 'wpss_vendor_status_block' ) );
}

exit( $fails ? 1 : 0 );
