<?php
/**
 * Owners are told when background jobs stop (delayed message email, Zoho #41859).
 *
 * Run: wp eval-file tests/test-stalled-jobs-warning.php
 *
 * @package WPSellServices
 */

use WPSellServices\Services\Scheduler;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$check( 'the stalled-queue check exists', method_exists( Scheduler::class, 'stalled_since' ) );
if ( ! method_exists( Scheduler::class, 'stalled_since' ) ) {
	echo "FAILED: {$fails}\n";
	return;
}

$admin = new \WPSellServices\Admin\Admin();
delete_option( 'wpss_jobs_stall_seen' );

// Healthy queue (other overdue wpss jobs on this playground would make this a false red, so skip if any).
$baseline = as_get_scheduled_actions( array( 'group' => 'wpss', 'status' => 'pending', 'date' => gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS ), 'date_compare' => '<=', 'per_page' => 1 ), 'ids' );
if ( ! $baseline ) {
	$check( 'healthy queue: no warning', 0 === Scheduler::stalled_since() && ! isset( $admin->get_setup_health_problems()['jobs'] ) );
	$check( 'healthy queue: nothing written', false === get_option( 'wpss_jobs_stall_seen' ) );
}

// A job three hours late.
$late = time() - 3 * HOUR_IN_SECONDS;
$id   = as_schedule_single_action( $late, 'wpss_qa_stall_probe', array( 'n' => wp_rand() ), 'wpss' );

$check( 'first sighting: no warning yet (could be a quiet site waking up)', 0 === Scheduler::stalled_since() );
$check( 'first sighting is remembered', (int) get_option( 'wpss_jobs_stall_seen' ) > 0 );
$check( 'seen again within ten minutes: still no warning', 0 === Scheduler::stalled_since() );

update_option( 'wpss_jobs_stall_seen', time() - 11 * MINUTE_IN_SECONDS, false );
$since = Scheduler::stalled_since();
$check( 'still stalled ten minutes later: reports the oldest overdue time', $since > 0 && $since <= $late + 1 );

$problems = $admin->get_setup_health_problems();
$check( 'setup health lists the jobs problem', isset( $problems['jobs'] ) );
$check( 'the notice says how long', isset( $problems['jobs'] ) && str_contains( $problems['jobs']['text'], human_time_diff( $since ) ) );
$check( 'the notice links to the overdue jobs', isset( $problems['jobs'] ) && str_contains( $problems['jobs']['url'], 'page=action-scheduler' ) && str_contains( $problems['jobs']['url'], 'past-due' ) );

// The queue runs again.
as_unschedule_action( 'wpss_qa_stall_probe', array(), 'wpss' );
ActionScheduler::store()->delete_action( $id );
if ( ! $baseline ) {
	$check( 'jobs run again: warning clears', 0 === Scheduler::stalled_since() );
	$check( 'jobs run again: the remembered sighting is removed', false === get_option( 'wpss_jobs_stall_seen' ) );
}

delete_option( 'wpss_jobs_stall_seen' );
echo $fails ? "FAILED: {$fails}\n" : "OK\n";
