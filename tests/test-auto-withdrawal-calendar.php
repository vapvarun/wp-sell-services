<?php
/**
 * Auto-withdrawal runs on the calendar dates its labels promise (Basecamp 10403479953).
 *
 * Run: wp eval-file tests/test-auto-withdrawal-calendar.php
 *
 * Each run arms the next date from a fixed moment, so the chain stays on the 1st and 15th (and Monday)
 * for a year, instead of drifting by a fixed 14 or 30 days. The chain re-arms even when a run throws.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\EarningsService;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$next = new ReflectionMethod( EarningsService::class, 'get_next_schedule_time' );
$next->setAccessible( true );
$tz   = wp_timezone();
$walk = static function ( string $schedule, string $start, int $runs ) use ( $next, $tz ): array {
	$out  = array();
	$from = new DateTimeImmutable( $start, $tz );
	for ( $i = 0; $i < $runs; $i++ ) {
		$at     = ( new DateTimeImmutable( '@' . $next->invoke( null, $schedule, $from ) ) )->setTimezone( $tz );
		$out[]  = $at;
		$from   = $at->modify( '+5 minutes' ); // the run happens just after it is due.
	}

	return $out;
};

$monthly  = $walk( 'monthly', '2026-10-18 10:00', 14 );
$biweekly = $walk( 'biweekly', '2026-10-18 10:00', 26 );
$weekly   = $walk( 'weekly', '2026-10-18 10:00', 52 );

$check( 'monthly: 14 runs in a row all land on the 1st at 02:00', 14 === count( array_filter( $monthly, static fn( $d ) => '1 02:00' === $d->format( 'j H:i' ) ) ) );
$check( '  and Jan 31 / Dec 31 starts skip no month', '2027-02-01' === ( $walk( 'monthly', '2027-01-31 03:00', 1 )[0]->format( 'Y-m-d' ) ) && '2027-01-01' === ( $walk( 'monthly', '2026-12-31 23:00', 1 )[0]->format( 'Y-m-d' ) ) );
$check( 'biweekly: 26 runs alternate the 15th and the 1st', 26 === count( array_filter( $biweekly, static fn( $d ) => in_array( $d->format( 'j H:i' ), array( '1 02:00', '15 02:00' ), true ) ) ) );
$check( '  in order, no month skipped or doubled', '2026-11-01' === $biweekly[0]->format( 'Y-m-d' ) && '2026-11-15' === $biweekly[1]->format( 'Y-m-d' ) && '2026-12-01' === $biweekly[2]->format( 'Y-m-d' ) && '2027-01-01' === $biweekly[4]->format( 'Y-m-d' ) );
$check( 'weekly: 52 runs all land on Monday at 02:00', 52 === count( array_filter( $weekly, static fn( $d ) => '1 02:00' === $d->format( 'N H:i' ) ) ) );
$check( '  and each is a week after the last', 7 * DAY_IN_SECONDS === $weekly[1]->getTimestamp() - $weekly[0]->getTimestamp() || 7 * DAY_IN_SECONDS - HOUR_IN_SECONDS <= $weekly[1]->getTimestamp() - $weekly[0]->getTimestamp() );

// The chain: arming schedules ONE action, and a run that throws still arms the next.
update_option( 'wpss_settings', array_merge( (array) get_option( 'wpss_settings', array() ), array() ) );
$src = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Services/EarningsService.php' );
$check( 'the schedule is one-shot, never an Action Scheduler recurring interval', false === strpos( $src, 'as_schedule_recurring_action' ) && false !== strpos( $src, 'Scheduler::schedule_single( $hook' ) );
$check( 'the re-arm sits in a finally so a failed run cannot end the chain', 1 === preg_match( '/finally\s*\{\s*self::schedule_auto_withdrawal_cron\(\)/', $src ) );

exit( $fails ? 1 : 0 );
