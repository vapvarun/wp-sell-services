<?php
/**
 * A script that creates a user and a service and then dies or returns early
 * leaves nothing behind: exit-on-fail.php removes what the script made.
 *
 * Run: wp eval-file tests/test-test-leftovers-are-swept.php
 *
 * Runs a child `wp eval-file` that leaves one user and one post, then reads the
 * database; the ids are removed here if the sweep failed.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$tag   = 'sweep' . wp_generate_password( 6, false );
$child = sys_get_temp_dir() . '/wpss-sweep-child-' . $tag . '.php';
file_put_contents(
	$child,
	"<?php\nrequire " . var_export( __DIR__ . '/exit-on-fail.php', true ) . ";\n"
	. 'wp_insert_user( array( "user_login" => ' . var_export( $tag, true ) . ', "user_pass" => "x", "user_email" => ' . var_export( $tag . '@example.test', true ) . " ) );\n"
	. 'wp_insert_post( array( "post_type" => "wpss_service", "post_status" => "publish", "post_title" => ' . var_export( $tag, true ) . " ) );\n"
	. "echo \"FAIL deliberate\\n\";\nreturn;\n"
);

$wp = (string) ( $_SERVER['argv'][0] ?? 'wp' );
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $wp ) . ' eval-file ' . escapeshellarg( $child ) . ' --path=' . escapeshellarg( ABSPATH ) . ' 2>&1', $out, $status );

global $wpdb;
$user = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE user_login = %s", $tag ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$post = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s", $tag ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$check( 'the child ran and failed on purpose', 1 === $status, $status . ' ' . implode( ' ', array_slice( $out, -2 ) ) );
$check( 'its user was removed', 0 === $user, (string) $user );
$check( 'its service was removed', 0 === $post, (string) $post );

if ( $user ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $user );
}
if ( $post ) {
	wp_delete_post( $post, true );
}
wp_delete_file( $child );
