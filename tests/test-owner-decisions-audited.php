<?php
/**
 * Owner decisions that change another member's standing leave an audit row:
 * resolving a member report, assigning a dispute, setting an account standing.
 *
 * Run: wp eval-file tests/test-owner-decisions-audited.php
 *
 * Creates one QA report and reassigns one dispute; both are restored and the
 * audit rows the test wrote are deleted, on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\API\ReportsController;
use WPSellServices\Services\DisputeWorkflowManager;

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
$audit    = $wpdb->prefix . 'wpss_audit_log';
$reports  = $wpdb->prefix . 'wpss_reports';
$disputes = $wpdb->prefix . 'wpss_disputes';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$audit_before = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$audit}" );
// An escalated dispute: an existing one, or one staged into that status and restored.
$dispute_id = wpss_test_dispute_in( 'escalated' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$dispute = $dispute_id ? $wpdb->get_row( $wpdb->prepare( "SELECT id, meta FROM {$disputes} WHERE id = %d", $dispute_id ) ) : null;
$report_id = 0;

$restore = static function () use ( $wpdb, $audit, $audit_before, $reports, &$report_id, $disputes, $dispute ) {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$audit} WHERE id > %d AND event_type IN ('report.resolve','dispute.assign','member.account_status')", $audit_before ) );
	if ( $report_id ) {
		$wpdb->delete( $reports, array( 'id' => $report_id ) );
	}
	if ( $dispute ) {
		$wpdb->update( $disputes, array( 'meta' => $dispute->meta ), array( 'id' => $dispute->id ) );
	}
	// phpcs:enable
};

wp_set_current_user( 1 );

// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$wpdb->insert(
	$reports,
	array(
		'target_type'      => 'user',
		'target_id'        => 1,
		'reported_user_id' => 1,
		'reporter_id'      => 1,
		'reason'           => 'spam',
		'details'          => 'QA-AUDIT test report',
		'status'           => 'open',
	)
);
$report_id = (int) $wpdb->insert_id;
ReportsController::resolve_report( $report_id, 'upheld' );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$audit} WHERE id > %d AND event_type = 'report.resolve' AND object_id = %d", $audit_before, $report_id ) );
$check( 'resolving a report writes one audit row', 1 === $n, (string) $n );

if ( $dispute ) {
	// An open dispute nobody holds yet, so the assignment is a real change.
	$unassigned                = json_decode( (string) $dispute->meta, true ) ?: array();
	$unassigned['assigned_to'] = 0;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update( $disputes, array( 'meta' => wp_json_encode( $unassigned ) ), array( 'id' => $dispute->id ) );
	( new DisputeWorkflowManager() )->assign_to_admin( (int) $dispute->id, 1 );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$audit} WHERE id > %d AND event_type = 'dispute.assign' AND object_id = %d", $audit_before, $dispute->id ) );
	$check( 'assigning a dispute writes one audit row', 1 === $n, (string) $n );
} else {
	echo "SKIP  no dispute on this install (seed one with wp wpss demo marketplace)\n";
}

// The queue row action and the profile field share one standing setter, so both are audited.
$qa_user = wp_insert_user( array( 'user_login' => 'qa-audit-standing', 'user_pass' => wp_generate_password(), 'user_email' => 'qa-audit-standing@example.test' ) );
if ( ! is_wp_error( $qa_user ) ) {
	$method = method_exists( \WPSellServices\Admin\Pages\ReportsPage::class, 'set_account_status' ) ? new ReflectionMethod( \WPSellServices\Admin\Pages\ReportsPage::class, 'set_account_status' ) : null;
	if ( $method ) {
		$method->invoke( null, (int) $qa_user, 'suspended' );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$audit} WHERE id > %d AND event_type = 'member.account_status' AND object_id = %d", $audit_before, $qa_user ) );
	$check( 'changing a standing writes one audit row', 1 === $n, (string) $n );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( (int) $qa_user );
}

exit( $fails ? 1 : 0 );
