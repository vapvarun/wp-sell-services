<?php
/**
 * An empty Services page gives each viewer a next step (Basecamp 10550848421).
 *
 * "Check back soon" was all a visitor, buyer or the owner saw on a new marketplace; only a vendor
 * got Create a Service. Now a non-vendor gets Start selling, the owner gets Add your first service,
 * and Start selling reaches the Become a Vendor page, which signs a visitor up as a seller.
 * wpss_get_become_vendor_url() read a page key no page carries, so it always fell back to the
 * dashboard's log-in prompt.
 *
 * Run: wp eval-file tests/test-empty-services-next-step.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/fixtures.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$f       = wpss_test_fixtures();
$actions = static function ( int $user_id ): array {
	wp_set_current_user( $user_id );
	ob_start();
	include dirname( __DIR__ ) . '/templates/content-no-services.php';
	$html = (string) ob_get_clean();
	preg_match_all( '#<a href="([^"]+)"\s+class="wpss-btn[^"]*">\s*([^<]+?)\s*</a>#', $html, $m, PREG_SET_ORDER );
	return array_map( static fn( $x ) => array( trim( $x[2] ), html_entity_decode( $x[1] ) ), $m );
};
$page     = wpss_get_page_url( 'become_vendor' );
$vendor_w = get_option( 'wpss_vendor' );

try {
	$check( 'Become a Vendor links reach the mapped page that signs visitors up', '' !== $page && wpss_get_become_vendor_url() === $page, wpss_get_become_vendor_url() );

	$check( 'Visitor: Start selling, to that page', array( array( 'Start selling', $page ) ) === $actions( 0 ), wp_json_encode( $actions( 0 ) ) );
	$check( 'Buyer: Start selling too', array( array( 'Start selling', $page ) ) === $actions( $f->buyer ), wp_json_encode( $actions( $f->buyer ) ) );
	$check( 'Vendor: Create a Service, as before', array( 'Create a Service' ) === array_column( $actions( $f->vendor ), 0 ) );

	$admin_is_vendor = wpss_is_vendor( $f->admin );
	$check( 'Owner who does not sell: Add your first service', $admin_is_vendor || array( 'Add your first service' ) === array_column( $actions( $f->admin ), 0 ), wp_json_encode( $actions( $f->admin ) ) );

	update_option( 'wpss_vendor', array_merge( (array) $vendor_w, array( 'vendor_registration' => 'closed' ) ) );
	$check( 'Vendor registration closed: no Start selling', array() === $actions( 0 ) );
} finally {
	update_option( 'wpss_vendor', $vendor_w );
	wp_set_current_user( 0 );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
