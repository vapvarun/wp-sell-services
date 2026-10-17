<?php
/**
 * Auto-withdrawal runs on the date the setting promises and stays there
 * (Basecamp 10403479953).
 *
 * A recurring 14/30-day Action Scheduler action adds N seconds to the previous
 * run, so "1st of month" drifted to the 31st, 30th and on. The schedule is now a
 * single action re-armed after every run, always landing on the next anchor.
 *
 * Run: wp eval-file tests/test-auto-withdrawal-schedule.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Services\EarningsService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
	echo "SKIP  Action Scheduler not loaded\n";
	return;
}

$hook  = 'wpss_process_auto_withdrawals';
$saved = array( get_option( 'wpss_payouts' ), get_option( 'wpss_auto_withdrawal_schedule_type' ), get_option( 'wpss_auto_withdrawal_chain' ) );

$pending = static function () use ( $hook ): array {
	return as_get_scheduled_actions( array( 'hook' => $hook, 'status' => ActionScheduler_Store::STATUS_PENDING, 'per_page' => 10 ) );
};
$set = static function ( string $schedule ) {
	// A threshold nobody reaches, so running the payouts here creates no withdrawals.
	update_option( 'wpss_payouts', array( 'auto_withdrawal_enabled' => true, 'auto_withdrawal_schedule' => $schedule, 'auto_withdrawal_threshold' => 99999999 ) );
	delete_option( 'wpss_auto_withdrawal_schedule_type' );
	delete_option( 'wpss_auto_withdrawal_chain' );
	EarningsService::schedule_auto_withdrawal_cron();
};

foreach ( array( 'monthly', 'biweekly', 'weekly' ) as $schedule ) {
	$set( $schedule );
	$actions = $pending();
	$check( "{$schedule}: exactly one pending action", 1 === count( $actions ) );
	$action = reset( $actions );
	$when   = $action->get_schedule()->get_date()->setTimezone( wp_timezone() );
	$check( "{$schedule}: a single action, not an interval that drifts", ! ( $action->get_schedule() instanceof ActionScheduler_IntervalSchedule ) );
	$check( "{$schedule}: runs at 02:00 site time", '02:00' === $when->format( 'H:i' ) );
	$on = match ( $schedule ) {
		'monthly'  => '1' === $when->format( 'j' ),
		'biweekly' => in_array( $when->format( 'j' ), array( '1', '15' ), true ),
		default    => '1' === $when->format( 'N' ),
	};
	$check( "{$schedule}: lands on its anchor day ({$when->format( 'D j M' )})", $on );

	// The action ran: the next one is armed on the anchor again, not N seconds later.
	as_unschedule_all_actions( $hook );
	EarningsService::run_scheduled_auto_withdrawals();
	$next = $pending();
	$check( "{$schedule}: a run arms exactly one next action", 1 === count( $next ) );
	$when2 = reset( $next )->get_schedule()->get_date()->setTimezone( wp_timezone() );
	$check( "{$schedule}: the next date is still on the anchor", $when2->format( 'H:i' ) === '02:00' );
}

// A site upgraded with the old recurring action moves onto the chain.
as_unschedule_all_actions( $hook );
as_schedule_recurring_action( time() + 3600, 30 * DAY_IN_SECONDS, $hook, array(), 'wpss' );
update_option( 'wpss_auto_withdrawal_schedule_type', 'monthly', false );
delete_option( 'wpss_auto_withdrawal_chain' );
update_option( 'wpss_payouts', array( 'auto_withdrawal_enabled' => true, 'auto_withdrawal_schedule' => 'monthly', 'auto_withdrawal_threshold' => 99999999 ) );
EarningsService::schedule_auto_withdrawal_cron();
$after = $pending();
$check( 'legacy recurring action is replaced by one single action', 1 === count( $after ) && ! ( reset( $after )->get_schedule() instanceof ActionScheduler_IntervalSchedule ) );

// Switching it off clears everything.
update_option( 'wpss_payouts', array( 'auto_withdrawal_enabled' => false ) );
EarningsService::schedule_auto_withdrawal_cron();
$check( 'switched off: nothing pending', 0 === count( $pending() ) );

as_unschedule_all_actions( $hook );
update_option( 'wpss_payouts', $saved[0] );
$saved[1] ? update_option( 'wpss_auto_withdrawal_schedule_type', $saved[1], false ) : delete_option( 'wpss_auto_withdrawal_schedule_type' );
$saved[2] ? update_option( 'wpss_auto_withdrawal_chain', $saved[2], false ) : delete_option( 'wpss_auto_withdrawal_chain' );
if ( is_array( $saved[0] ) && ! empty( $saved[0]['auto_withdrawal_enabled'] ) ) {
	EarningsService::schedule_auto_withdrawal_cron();
}

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
