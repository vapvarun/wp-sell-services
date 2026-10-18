<?php
/**
 * The Services list has Feature / Unfeature bulk actions for the owner, and only the owner
 * (Basecamp 10371266136).
 *
 * Run: wp eval-file tests/test-service-bulk-feature.php
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\ServiceListScreen;

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$suffix = wp_generate_password( 6, false, false );
$vendor = \WPSellServices\Tests\Factories\UserFactory::vendor( array( 'user_login' => 'wpss_bf_vendor_' . $suffix, 'user_email' => 'wpss_bf_vendor_' . $suffix . '@example.test' ) );
$ids    = array();
foreach ( array( 'a', 'b' ) as $n ) {
	$ids[] = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'post_title' => 'QA bulk ' . $n . $suffix, 'post_author' => $vendor->ID ) );
}
$screen = new ServiceListScreen();

wp_set_current_user( 1 );
$menu = $screen->bulk_actions( array() );
$check( 'the owner sees both bulk actions', isset( $menu['wpss_feature'], $menu['wpss_unfeature'] ) );

$url = $screen->handle_bulk_action( 'edit.php', 'wpss_feature', $ids );
$check( 'Mark as featured sets the flag on every selected service', '1' === (string) get_post_meta( $ids[0], '_wpss_featured', true ) && '1' === (string) get_post_meta( $ids[1], '_wpss_featured', true ) );
$check( 'the redirect carries the count', false !== strpos( $url, 'wpss_featured_done=2' ) );

$screen->handle_bulk_action( 'edit.php', 'wpss_unfeature', array( $ids[0] ) );
$check( 'Remove from featured clears it and leaves the other', '' === (string) get_post_meta( $ids[0], '_wpss_featured', true ) && '1' === (string) get_post_meta( $ids[1], '_wpss_featured', true ) );

$force = static fn() => false;
add_filter( 'wpss_service_is_featured', $force );
$screen->handle_bulk_action( 'edit.php', 'wpss_feature', array( $ids[0] ) );
remove_filter( 'wpss_service_is_featured', $force );
$check( 'the wpss_service_is_featured filter still decides', '' === (string) get_post_meta( $ids[0], '_wpss_featured', true ) );

wp_set_current_user( $vendor->ID );
$check( 'a vendor sees no feature action', array() === $screen->bulk_actions( array() ) );
$screen->handle_bulk_action( 'edit.php', 'wpss_feature', array( $ids[0] ) );
$check( 'a vendor forcing the action changes nothing', '' === (string) get_post_meta( $ids[0], '_wpss_featured', true ) );

foreach ( $ids as $id ) {
	wp_delete_post( $id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vendor->ID );

exit( $fails ? 1 : 0 );
