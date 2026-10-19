<?php
/**
 * Filtering the audit log by object_id returns that object's rows, with or
 * without object_type, and never the whole log (Basecamp 10402964352).
 *
 * Run: wp eval-file tests/test-audit-log-object-filter.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Services\AuditLogService;

global $wpdb;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
if ( ! $admin ) {
	echo "SKIP  needs an administrator\n";
	return;
}

$svc = new AuditLogService();
$oid = 987654;
$svc->log( 'qa.rft.a', 'order', $oid, array( 'action' => 'test' ) );
$svc->log( 'qa.rft.b', 'order', $oid, array( 'action' => 'test' ) );
$svc->log( 'qa.rft.c', 'dispute', $oid + 1, array( 'action' => 'test' ) );

$all = $svc->query( array( 'per_page' => 100 ) )['total'];
$id  = $svc->query( array( 'object_id' => $oid, 'per_page' => 100 ) );
$both = $svc->query( array( 'object_type' => 'order', 'object_id' => $oid, 'per_page' => 100 ) );

$check( 'object_id alone does not return the whole log', $id['total'] < $all );
$check( 'object_id alone returns that object\'s two rows', 2 === $id['total'] );
$check( 'object_type + object_id still agree', 2 === $both['total'] );
$check( 'a wrong type with the id returns none', 0 === $svc->query( array( 'object_type' => 'dispute', 'object_id' => $oid ) )['total'] );

wp_set_current_user( (int) $admin[0] );
$req = new WP_REST_Request( 'GET', '/wpss/v1/audit-log' );
$req->set_query_params( array( 'object_id' => $oid ) );
$res = rest_do_request( $req );
$check( 'REST ?object_id= returns 200 with only those rows', 200 === $res->get_status() && 2 === (int) ( $res->get_headers()['X-WP-Total'] ?? -1 ) );
wp_set_current_user( 0 );

$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_audit_log WHERE event_type LIKE %s", 'qa.rft.%' ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
