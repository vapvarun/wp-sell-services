<?php
/**
 * The payment-proof file picker offers exactly the types the server accepts.
 *
 * Run: wp eval-file tests/test-receipt-picker-matches-server.php
 *
 * The picker was hard-coded to image/*,.pdf while the server checks the owner's Allowed File Types,
 * so a WebP or HEIC screenshot was offered and refused after it was chosen.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$accept = wpss_upload_accept( 'receipt' );
$list   = array_filter( explode( ',', $accept ) );
$check( 'picker lists the owner types', in_array( '.pdf', $list, true ) && in_array( '.jpg', $list, true ), $accept );

// The picker and the server check read the same list.
add_filter( 'wpss_allowed_file_types', static fn() => array( 'heic' ) );
$check( 'picker follows the allowed list', '.heic' === wpss_upload_accept( 'receipt' ) );
remove_all_filters( 'wpss_allowed_file_types' );

$src = (string) file_get_contents( dirname( __DIR__ ) . '/src/Integrations/Gateways/OfflineGateway.php' );
$check( 'the receipt input is built from wpss_upload_accept', str_contains( $src, "wpss_upload_accept( 'receipt' )" ) && ! str_contains( $src, 'accept="image/*,.pdf"' ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
