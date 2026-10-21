<?php
/**
 * A suspended vendor finishes paid work and takes nothing new, and every door
 * agrees.
 *
 * Run: wp eval-file tests/test-suspended-vendor-doors.php
 *
 * The menu offered Sales Orders and the route let a suspended vendor in, but
 * render_section() kept its own stricter copy of the rule and printed "Vendor
 * Access Required", leaving the buyer's paid order stuck. And a buyer could
 * still start a new pre-sale thread with a suspended vendor. The profile row's
 * status is restored on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Frontend\UnifiedDashboard;

// Registered before exit-on-fail.php: its handler exits on a FAIL and would skip
// anything registered after it.
$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$row = $wpdb->get_row( "SELECT user_id, status FROM {$profiles} WHERE status = 'active' ORDER BY user_id DESC LIMIT 1" );
if ( ! $row || user_can( (int) $row->user_id, 'manage_options' ) ) {
	echo "SKIP  no active non-admin vendor; nothing to assert\n";
	exit( 0 );
}

$vendor_id = (int) $row->user_id;
$restore   = static function () use ( $wpdb, $profiles, $vendor_id ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update( $profiles, array( 'status' => 'active' ), array( 'user_id' => $vendor_id ) );
	wp_cache_flush();
};

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->update( $profiles, array( 'status' => 'suspended' ), array( 'user_id' => $vendor_id ) );
wp_cache_flush();
wp_set_current_user( $vendor_id );

$render = static function ( string $section ) {
	$dashboard = new UnifiedDashboard();
	$method    = new ReflectionMethod( $dashboard, 'render_section' );
	ob_start();
	$method->invoke( $dashboard, $section );
	return (string) ob_get_clean();
};

$sales = $render( 'sales' );
$check( 'suspended vendor opens Sales Orders, not the access wall', false === strpos( $sales, 'Vendor Access Required' ), substr( wp_strip_all_tags( $sales ), 0, 120 ) );
$check( 'Sales Orders actually renders', '' !== trim( $sales ) );

foreach ( array( 'services', 'earnings', 'proposals', 'create' ) as $section ) {
	$check( "suspended vendor is still refused {$section}", false !== strpos( $render( $section ), 'Vendor Access Required' ) || '' === trim( $render( $section ) ) );
}

// A buyer starting a new pre-sale thread with the suspended vendor is refused.
$buyer_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} WHERE ID <> {$vendor_id} AND ID > 1 ORDER BY ID DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
wp_set_current_user( $buyer_id );
$_POST = array(
	'nonce'     => wp_create_nonce( 'wpss_service_nonce' ),
	'vendor_id' => $vendor_id,
	'message'   => 'Hello, are you free to start next week?',
);
$_REQUEST = $_POST;
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter(
	'wp_die_ajax_handler',
	static function () {
		return static function () {
			throw new RuntimeException( 'ajax-done' );
		};
	}
);

ob_start();
try {
	( new \WPSellServices\Frontend\AjaxHandlers() )->contact_vendor();
} catch ( RuntimeException $e ) {
	unset( $e );
}
$raw = (string) ob_get_clean();
$out = json_decode( $raw, true );
$check( 'a new enquiry to a suspended vendor is refused', is_array( $out ) && false === ( $out['success'] ?? null ), wp_json_encode( $out ) . ' raw=' . substr( $raw, 0, 160 ) );
$check( 'with a plain message', is_array( $out ) && false !== stripos( (string) ( $out['data']['message'] ?? '' ), 'not taking new messages' ) );

$_POST    = array();
$_REQUEST = array();
wp_set_current_user( 0 );
exit( $fails ? 1 : 0 );
