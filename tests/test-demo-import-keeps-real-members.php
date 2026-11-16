<?php
/**
 * The demo importer never takes over a real member's account.
 *
 * Run: wp eval-file tests/test-demo-import-keeps-real-members.php
 *
 * A member whose login matches a demo vendor (sarah_designer) was reused,
 * made an active listed vendor and given demo services. Only users the
 * importer itself created (_wpss_demo_content) are reused now.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$existing = username_exists( 'sarah_designer' );
if ( $existing && ! get_user_meta( (int) $existing, '_wpss_demo_content', true ) ) {
	echo "SKIP  a real sarah_designer account exists on this site\n";
	return;
}

$real = 0;
if ( ! $existing ) {
	$real = (int) wp_insert_user( array( 'user_login' => 'sarah_designer', 'user_pass' => wp_generate_password(), 'user_email' => 'real-sarah@example.test', 'role' => 'subscriber' ) );
}

$call = new ReflectionMethod( \WPSellServices\Admin\Admin::class, 'create_demo_vendors' );
$call->setAccessible( true );
$admin = ( new ReflectionClass( \WPSellServices\Admin\Admin::class ) )->newInstanceWithoutConstructor();
$ids   = $call->invoke( $admin );

$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$has_row  = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$profiles} WHERE user_id = %d", $real ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
$check( 'the real member got no vendor profile', ! $has_row );
$check( 'and was not handed demo content', ! get_user_meta( $real, '_wpss_demo_content', true ) && ! in_array( $real, array_map( 'intval', (array) $ids ), true ) );
$check( 'the other demo vendors were still created', count( (array) $ids ) >= 3, (string) wp_json_encode( $ids ) );

require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( (array) $ids as $id ) {
	$wpdb->delete( $profiles, array( 'user_id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	wp_delete_user( (int) $id );
}
wp_delete_user( $real );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
