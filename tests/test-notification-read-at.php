<?php
/**
 * Every way of marking a notification read stamps read_at and clears the
 * unread-badge cache (Basecamp 10403105565). The service wrote only is_read, so
 * REST left read_at NULL, and the AJAX handler wrote the table directly and left
 * the cached unread count stale.
 *
 * Run: wp eval-file tests/test-notification-read-at.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Services\NotificationService;

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$users = get_users( array( 'number' => 2, 'fields' => 'ID' ) );
if ( count( $users ) < 2 ) {
	echo "SKIP  needs two users\n";
	return;
}
list( $me, $other ) = array_map( 'intval', $users );

$table = $wpdb->prefix . 'wpss_notifications';
$svc   = new NotificationService();
$mk    = static function ( int $user ) use ( $svc ) {
	return (int) $svc->create( $user, 'order_new', 'QA-RFT notice', 'body', array() );
};
$row   = static fn( int $id ) => $wpdb->get_row( $wpdb->prepare( "SELECT is_read, read_at FROM {$table} WHERE id = %d", $id ) );

// Service: one, then all.
$a = $mk( $me );
$b = $mk( $me );
$c = $mk( $me );
$svc->mark_as_read( $a );
$check( 'service mark_as_read stamps read_at', 1 === (int) $row( $a )->is_read && ! empty( $row( $a )->read_at ) );
$check( 'read_at is UTC and now', abs( strtotime( $row( $a )->read_at . ' UTC' ) - time() ) < 120 );
$svc->mark_all_as_read( $me );
$check( 'service mark_all_as_read stamps read_at on the rest', ! empty( $row( $b )->read_at ) && ! empty( $row( $c )->read_at ) );

// Owner scope: another member's row is not touched.
$theirs = $mk( $other );
$svc->mark_as_read( $theirs, $me );
$check( 'scoped to its owner: someone else\'s notification stays unread', 0 === (int) $row( $theirs )->is_read );

// AJAX handlers go through the same service (stamp + cache).
$d = $mk( $me );
$e = $mk( $me );
wp_set_current_user( $me );
$_POST = array( 'nonce' => wp_create_nonce( 'wpss_notification_nonce' ), 'notification_id' => $d );
$_REQUEST = $_POST;
add_filter( 'wp_die_ajax_handler', static fn() => static function () {} );
add_filter( 'wp_doing_ajax', '__return_true' );
ob_start();
( new \WPSellServices\Frontend\AjaxHandlers() )->mark_notification_read();
ob_end_clean();
$check( 'AJAX mark one stamps read_at', 1 === (int) $row( $d )->is_read && ! empty( $row( $d )->read_at ) );
$before = $svc->get_unread_count( $me );
ob_start();
( new \WPSellServices\Frontend\AjaxHandlers() )->mark_all_notifications_read();
ob_end_clean();
$check( 'AJAX mark all stamps read_at', ! empty( $row( $e )->read_at ) );
$check( 'unread count is fresh after AJAX (cache cleared)', 0 === $svc->get_unread_count( $me ) && $before >= 1 );

$wpdb->query( "DELETE FROM {$table} WHERE title = 'QA-RFT notice'" );
wp_set_current_user( 0 );
$_POST = $_REQUEST = array();

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
