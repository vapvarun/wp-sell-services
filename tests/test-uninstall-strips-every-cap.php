<?php
/**
 * Uninstall leaves no wpss capability behind and no vendor without a role (Basecamp 10545751792).
 *
 * uninstall.php removed a fixed list of 7 caps while Activator grants about thirty, so a
 * delete-data uninstall left edit_wpss_services and friends on administrator; and members
 * whose only role was wpss_vendor were left with no role at all (could not even read).
 *
 * Runs only the capability section of uninstall.php, inside a transaction that is rolled
 * back, so the site's roles and members are unchanged afterwards (checked at the end).
 *
 * Run: wp eval-file tests/test-uninstall-strips-every-cap.php
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

$src   = (string) file_get_contents( getenv( 'WPSS_UNINSTALL_SRC' ) ?: dirname( __DIR__ ) . '/uninstall.php' ); // Env override: prove it fails on an older copy.
$start = strpos( $src, '// Remove capabilities.' );
$end   = strpos( $src, '// Clear any remaining transients.' );
$check( 'uninstall.php has its capability section', false !== $start && false !== $end );
$section = substr( $src, (int) $start, (int) $end - (int) $start );

$wpss_caps = static function ( array $caps ): array {
	return array_values( array_filter( array_keys( array_filter( $caps ) ), static fn( $c ) => false !== strpos( $c, 'wpss_' ) ) );
};

$roles_before = get_option( $wpdb->get_blog_prefix() . 'user_roles' );
$admin_before = $wpss_caps( get_role( 'administrator' )->capabilities );
$check( 'administrator holds wpss caps before (fixture sanity)', count( $admin_before ) > 7, implode( ',', $admin_before ) );

$default_before = get_option( 'default_role' );

// Eleanor's bounce: a site whose New User Default Role is Vendor must not hand vendors the removed role.
foreach ( array( 'subscriber', 'wpss_vendor' ) as $default_role ) {
	echo "-- New User Default Role: {$default_role}\n";
	$wpdb->query( 'START TRANSACTION' );
	update_option( 'default_role', $default_role );

	$vendor = wp_insert_user( array( 'user_login' => 'qa_rft_uninstall_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'role' => 'wpss_vendor' ) );
	( new WP_User( $vendor ) )->add_cap( 'wpss_vendor_orders' );

	eval( $section ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- runs the shipped section itself, not a copy.

	wp_roles()->for_site(); // Re-read roles the section changed.
	$left = array();
	foreach ( wp_roles()->roles as $name => $role ) {
		foreach ( $wpss_caps( $role['capabilities'] ) as $cap ) {
			$left[] = "$name:$cap";
		}
	}
	$check( 'no role keeps a wpss cap', array() === $left, implode( ',', $left ) );

	clean_user_cache( $vendor );
	$member = new WP_User( $vendor );
	$check( 'the vendor keeps no wpss role or cap', array() === $wpss_caps( $member->caps ), wp_json_encode( $member->caps ) );
	$check( 'the vendor has a real role, so they keep their account', array() !== $member->roles && user_can( $member, 'read' ), wp_json_encode( $member->caps ) );

	$wpdb->query( 'ROLLBACK' );
	wp_cache_flush();
	wp_roles()->for_site();
}

$check( 'rollback restored the site roles', get_option( $wpdb->get_blog_prefix() . 'user_roles' ) === $roles_before && $wpss_caps( get_role( 'administrator' )->capabilities ) === $admin_before );
$check( 'rollback removed the fixture users and kept the default role', false === get_userdata( $vendor ) && get_option( 'default_role' ) === $default_before );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
