<?php
/**
 * Notification center and retention: one query for list, count and REST, stable
 * paging when rows share a second, the 90-day cleanup, UTC audit cutoff, and the
 * indexes that make them cheap (Basecamp 10403104873, 10403104468, 10403104232,
 * 10403105211, 10402964139, 10402964236).
 *
 * Run: wp eval-file tests/test-notification-center-retention.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// --- Indexes (dbDelta adds them to an existing table) ----------------------
( new \WPSellServices\Database\SchemaManager() )->sync();
$has_key = static function ( string $table, string $key ) use ( $wpdb ): bool {
	return (bool) $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$wpdb->prefix}wpss_{$table} WHERE Key_name = %s", $key ) );
};
$check( 'audit_log has idx_created', $has_key( 'audit_log', 'idx_created' ) );
$check( 'notifications has idx_user_created', $has_key( 'notifications', 'idx_user_created' ) );
$check( 'notifications has idx_retention', $has_key( 'notifications', 'idx_retention' ) );

// --- Paging and filters over 60 rows sharing one second ---------------------
$notif = $wpdb->prefix . 'wpss_notifications';
$uid   = wp_insert_user( array( 'user_login' => 'qa_notif_' . wp_rand(), 'user_pass' => wp_generate_password() ) );
$now   = gmdate( 'Y-m-d H:i:s' );
$types = array( 'order_status', 'new_message', 'dispute_opened' );
for ( $i = 0; $i < 60; $i++ ) {
	$wpdb->insert( $notif, array( 'user_id' => $uid, 'type' => $types[ $i % 3 ], 'title' => "QA {$i}", 'is_read' => $i % 4 ? 1 : 0, 'created_at' => $now ) );
}
$seen = array();
for ( $page = 0; $page < 3; $page++ ) {
	foreach ( wpss_get_user_notifications( $uid, array( 'limit' => 20, 'offset' => $page * 20 ) ) as $row ) {
		$seen[] = (int) $row->id;
	}
}
$check( '60 same-second rows page 3 x 20 with no repeat and no gap', 60 === count( $seen ) && 60 === count( array_unique( $seen ) ) );
$check( 'unread filter counts 15', 15 === wpss_count_user_notifications( $uid, array( 'unread_only' => true ) ) );
$check( 'type filter counts 20', 20 === wpss_count_user_notifications( $uid, array( 'type' => 'new_message' ) ) );
$check( 'type + unread together are a subset', wpss_count_user_notifications( $uid, array( 'type' => 'new_message', 'unread_only' => true ) ) < 20 );
$check( 'the list honours the same filter as the count', 15 === count( wpss_get_user_notifications( $uid, array( 'unread_only' => true, 'limit' => 100 ) ) ) );

wp_set_current_user( $uid );
$req = new WP_REST_Request( 'GET', '/wpss/v1/notifications' );
$req->set_query_params( array( 'type' => 'new_message', 'per_page' => 100 ) );
$check( 'REST type filter agrees with the dashboard', 20 === count( rest_do_request( $req )->get_data() ) );
$req = new WP_REST_Request( 'GET', '/wpss/v1/notifications' );
$req->set_query_params( array( 'is_read' => 1, 'per_page' => 100 ) );
$check( 'REST is_read=1 still returns read rows only', 45 === count( rest_do_request( $req )->get_data() ) );
$res = rest_do_request( new WP_REST_Request( 'GET', '/wpss/v1/notifications/unread-count' ) );
$check( 'REST unread-count agrees with the badge count', 15 === (int) ( $res->get_data()['unread_count'] ?? -1 ) );
wp_set_current_user( 0 );
$wpdb->delete( $notif, array( 'user_id' => $uid ) );

// --- Retention ---------------------------------------------------------------
$old = static fn( int $days ) => gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
$mk  = static function ( string $title, string $type, int $read, int $days ) use ( $wpdb, $notif, $uid, $old ): int {
	$wpdb->insert( $notif, array( 'user_id' => $uid, 'type' => $type, 'title' => $title, 'is_read' => $read, 'created_at' => $old( $days ) ) );
	return (int) $wpdb->insert_id;
};
$read_old     = $mk( 'read 100d', 'order_status', 1, 100 );
$read_recent  = $mk( 'read 10d', 'order_status', 1, 10 );
$unread_old   = $mk( 'unread 100d', 'order_status', 0, 100 );
$dispute_100  = $mk( 'dispute read 100d', 'dispute_opened', 1, 100 );
$dispute_400  = $mk( 'dispute read 400d', 'dispute_opened', 1, 400 );
$deleted      = ( new \WPSellServices\Services\NotificationService() )->cleanup_expired();
$exists       = static fn( int $id ): bool => (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$notif} WHERE id = %d", $id ) );
$check( 'read notification older than 90 days is deleted', ! $exists( $read_old ) );
$check( 'read notification newer than 90 days stays', $exists( $read_recent ) );
$check( 'unread notification is never deleted, however old', $exists( $unread_old ) );
$check( 'dispute notice read 100 days ago stays (kept a year)', $exists( $dispute_100 ) );
$check( 'dispute notice read 400 days ago is deleted', ! $exists( $dispute_400 ) );
$check( 'cleanup reports what it deleted', $deleted >= 2 );
$wpdb->delete( $notif, array( 'user_id' => $uid ) );
wp_delete_user( $uid );

// --- Audit retention cutoff is UTC, whatever the DB session says -------------
$audit = $wpdb->prefix . 'wpss_audit_log';
update_option( \WPSellServices\Services\AuditLogService::RETENTION_OPTION, 365 );
$wpdb->insert( $audit, array( 'event_type' => 'qa.keep', 'object_type' => 'qa', 'created_at' => gmdate( 'Y-m-d H:i:s', time() - (int) ( 364.7 * DAY_IN_SECONDS ) ) ) );
$keep = (int) $wpdb->insert_id;
$wpdb->insert( $audit, array( 'event_type' => 'qa.drop', 'object_type' => 'qa', 'created_at' => gmdate( 'Y-m-d H:i:s', time() - 366 * DAY_IN_SECONDS ) ) );
$drop = (int) $wpdb->insert_id;
$wpdb->query( "SET time_zone = '+10:00'" );
( new \WPSellServices\Services\AuditLogService() )->cleanup_expired();
$wpdb->query( "SET time_zone = '+00:00'" );
$check( 'audit row 364.7 days old survives a +10:00 session', (bool) $wpdb->get_var( "SELECT id FROM {$audit} WHERE id = {$keep}" ) );
$check( 'audit row 366 days old is deleted', ! $wpdb->get_var( "SELECT id FROM {$audit} WHERE id = {$drop}" ) );
$wpdb->delete( $audit, array( 'object_type' => 'qa' ) );
delete_option( \WPSellServices\Services\AuditLogService::RETENTION_OPTION );

// --- Sort tiebreaks ------------------------------------------------------------
$svc = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/functions/services.php' );
$ven = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/API/VendorsController.php' );
$check( 'services sort ends on a unique key', false !== strpos( $svc, 'post_date DESC, {$wpdb->posts}.ID DESC' ) );
$check( 'vendors rating sort ends on a unique key', false !== strpos( $ven, '{$wpdb->users}.ID DESC' ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
