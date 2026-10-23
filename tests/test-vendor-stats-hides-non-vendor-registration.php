<?php
/**
 * The public vendor-stats route never reveals when a non-vendor registered.
 *
 * Run: wp eval-file tests/test-vendor-stats-hides-non-vendor-registration.php
 *
 * Read-only.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$member_since = static function ( int $id ) {
	$data = rest_do_request( new WP_REST_Request( 'GET', "/wpss/v1/vendors/{$id}/stats" ) )->get_data();
	return $data['member_since'] ?? $data['data']['member_since'] ?? null;
};

$non_vendor = 0;
foreach ( get_users( array( 'fields' => 'ID', 'number' => 50 ) ) as $uid ) {
	if ( ! wpss_is_vendor( (int) $uid ) ) {
		$non_vendor = (int) $uid;
		break;
	}
}

$check( 'a non-vendor user exists to test against', $non_vendor > 0 );
if ( $non_vendor ) {
	$check( 'a non-vendor registration date is not returned', null === $member_since( $non_vendor ), (string) $member_since( $non_vendor ) );
}
