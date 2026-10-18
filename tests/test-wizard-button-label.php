<?php
/**
 * The wizard's final button says "Publish Service" on a draft and "Update Service" only on a live
 * or pending listing (Basecamp 10415811135).
 *
 * Run: wp eval-file tests/test-wizard-button-label.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$user = get_users( array( 'role' => 'wpss_vendor', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( $user ? (int) $user[0] : 1 );
$id = wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => 'draft', 'post_title' => 'Label probe', 'post_author' => get_current_user_id() ) );

$render = static function ( string $status ) use ( $id ): string {
	global $wpdb;
	$wpdb->update( $wpdb->posts, array( 'post_status' => $status ), array( 'ID' => $id ) ); // Bypass settle hooks.
	clean_post_cache( $id );
	return do_shortcode( "[wpss_service_wizard id={$id}]" );
};

$draft = $render( 'draft' );
$check( 'draft says Publish Service', str_contains( $draft, 'Publish Service' ) && ! str_contains( $draft, 'Update Service' ) );
$live = $render( 'publish' );
$check( 'published says Update Service', str_contains( $live, 'Update Service' ) && ! str_contains( $live, 'Publish Service' ) );

wp_delete_post( $id, true );
exit( $fails ? 1 : 0 );
