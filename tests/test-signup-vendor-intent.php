<?php
/**
 * A vendor intent is promoted by the one account path, per registration mode.
 *
 * Run: wp eval-file tests/test-signup-vendor-intent.php
 *
 * Basecamp (REST register): POST /auth/register role=vendor created a plain
 * subscriber because only the AJAX handler promoted. Throwaway users; the
 * vendor_registration option is restored.
 *
 * @package WPSellServices
 */

use WPSellServices\Frontend\PublicSignup;
use WPSellServices\Services\VendorService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

add_filter( 'pre_wp_mail', '__return_true' );
$opts   = get_option( 'wpss_vendor', array() );
$vs     = new VendorService();
$made   = array();
$signup = static function ( string $mode, string $intent ) use ( &$made, $opts ): int {
	update_option( 'wpss_vendor', array_merge( (array) $opts, array( 'vendor_registration' => $mode ) ) );
	$key  = 'dev_intent_' . $mode . '_' . $intent;
	$made[] = $key;
	$id   = PublicSignup::create_account( array( 'email' => $key . '@example.test', 'password' => wp_generate_password(), 'display_name' => $key, 'intent' => $intent ) );
	return is_wp_error( $id ) ? 0 : (int) $id;
};

$open = $signup( 'open', 'vendor' );
$check( 'open mode: vendor intent becomes a vendor', $open && $vs->is_vendor( $open ) );

$appr = $signup( 'approval', 'vendor' );
$check( 'approval mode: vendor intent is pending', $appr && ! $vs->is_vendor( $appr ) && $vs->has_pending_application( $appr ) );

$shut = $signup( 'closed', 'vendor' );
$check( 'closed mode: a vendor signup is refused, no account made', 0 === $shut && ! email_exists( 'dev_intent_closed_vendor@example.test' ) );

$buyer = $signup( 'open', 'buyer' );
$check( 'buyer intent stays a buyer', $buyer && ! $vs->is_vendor( $buyer ) );

update_option( 'wpss_vendor', $opts );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $open, $appr, $shut, $buyer ) as $id ) {
	if ( $id ) {
		wp_delete_user( $id );
	}
}
wp_set_current_user( 0 );

exit( $fails ? 1 : 0 );
