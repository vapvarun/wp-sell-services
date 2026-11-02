<?php
/**
 * Scheduler
 *
 * Thin wrapper around Action Scheduler. Exists so every scheduling call in
 * the plugin goes through one entry point — easier to stub in tests, easier
 * to grep, and the single place that enforces the `wpss` / `wpss-pro`
 * group convention used by the deactivator to sweep everything in one call.
 *
 * @package WPSellServices\Services
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Action Scheduler facade.
 *
 * @since 1.1.0
 */
class Scheduler {

	public const GROUP_FREE = 'wpss';
	public const GROUP_PRO  = 'wpss-pro';

	/**
	 * Whether Action Scheduler's data store is initialized.
	 *
	 * AS functions (as_schedule_*, as_next_scheduled_action, etc.) require
	 * the data store — which comes up on the `action_scheduler_init` action
	 * (fired during `init`). Calling them before that logs the notice:
	 *   "... was called before the Action Scheduler data store was
	 *    initialized".
	 *
	 * Call sites that fire during plugins_loaded (e.g. Pro bootstrap via
	 * `wpss_loaded`) must either use {@see self::on_ready()} to defer, or
	 * guard with this check.
	 *
	 * @return bool
	 */
	public static function is_ready(): bool {
		if ( ! function_exists( 'as_schedule_single_action' ) ) {
			return false;
		}
		// did_action() returns the number of times the action has fired —
		// zero before AS has fully bootstrapped. AS fires it inside
		// ActionScheduler::init() after the store + runner + logger are up.
		return did_action( 'action_scheduler_init' ) > 0;
	}

	/**
	 * Run a callback once Action Scheduler is ready to receive calls.
	 *
	 * If AS is already up, runs immediately. Otherwise hooks the callback
	 * onto `action_scheduler_init`. The callback runs exactly once.
	 *
	 * @param callable $callback Callable that performs AS operations.
	 * @return void
	 */
	public static function on_ready( callable $callback ): void {
		if ( self::is_ready() ) {
			$callback();
			return;
		}

		// add_action() with a closure wrapper so each on_ready() call gets
		// its own listener — using the raw callable twice would be
		// deduplicated by the hook system.
		add_action(
			'action_scheduler_init',
			static function () use ( $callback ): void {
				$callback();
			},
			10,
			0
		);
	}

	/**
	 * Schedule a one-off action at a specific timestamp.
	 *
	 * Idempotent: if an identical action (same hook + args + group) is
	 * already pending, returns the existing action ID rather than queueing
	 * a duplicate.
	 *
	 * @param string           $hook      Hook name.
	 * @param int              $timestamp Unix timestamp when the action should run.
	 * @param array<int,mixed> $args      Positional args passed to the hook callback.
	 * @param string           $group     Scheduler group.
	 * @return int Action ID, or 0 when AS is not ready.
	 */
	public static function schedule_single( string $hook, int $timestamp, array $args = array(), string $group = self::GROUP_FREE ): int {
		if ( ! self::is_ready() ) {
			self::on_ready(
				static function () use ( $hook, $timestamp, $args, $group ): void {
					self::schedule_single( $hook, $timestamp, $args, $group );
				}
			);
			return 0;
		}

		if ( self::has_pending( $hook, $args, $group ) ) {
			$existing = \as_next_scheduled_action( $hook, $args, $group );
			return is_int( $existing ) ? $existing : 0;
		}

		return (int) \as_schedule_single_action( $timestamp, $hook, $args, $group );
	}

	/**
	 * Schedule a recurring action.
	 *
	 * Uses the first run at now + interval (not now) so activation doesn't
	 * spike every daily job at once — matches the original wp_schedule_event
	 * contract where events fire after the first interval elapses.
	 *
	 * @param string $hook     Hook name.
	 * @param int    $interval Seconds between runs.
	 * @param string $group    Scheduler group.
	 * @return int Action ID, or 0 when AS is not ready / already scheduled.
	 */
	public static function schedule_recurring( string $hook, int $interval, string $group = self::GROUP_FREE ): int {
		if ( ! self::is_ready() ) {
			self::on_ready(
				static function () use ( $hook, $interval, $group ): void {
					self::schedule_recurring( $hook, $interval, $group );
				}
			);
			return 0;
		}

		if ( self::has_pending( $hook, array(), $group ) ) {
			return 0;
		}

		return (int) \as_schedule_recurring_action( time() + $interval, $interval, $hook, array(), $group );
	}

	/**
	 * Unschedule one specific action.
	 *
	 * @param string           $hook  Hook name.
	 * @param array<int,mixed> $args  Positional args identifying the action.
	 * @param string           $group Scheduler group.
	 * @return void
	 */
	public static function unschedule( string $hook, array $args = array(), string $group = self::GROUP_FREE ): void {
		if ( ! self::is_ready() ) {
			self::on_ready(
				static function () use ( $hook, $args, $group ): void {
					self::unschedule( $hook, $args, $group );
				}
			);
			return;
		}
		\as_unschedule_action( $hook, $args, $group );
	}

	/**
	 * Unschedule every pending action for a given hook, regardless of args.
	 *
	 * @param string $hook  Hook name.
	 * @param string $group Scheduler group.
	 * @return void
	 */
	public static function unschedule_all( string $hook, string $group = self::GROUP_FREE ): void {
		if ( ! self::is_ready() ) {
			self::on_ready(
				static function () use ( $hook, $group ): void {
					self::unschedule_all( $hook, $group );
				}
			);
			return;
		}
		\as_unschedule_all_actions( $hook, array(), $group );
	}

	/**
	 * Unschedule every pending action in a group.
	 *
	 * Used by the deactivator to sweep everything the plugin owns with one
	 * call — no need to enumerate hook names.
	 *
	 * @param string $group Scheduler group.
	 * @return void
	 */
	public static function unschedule_all_for_group( string $group = self::GROUP_FREE ): void {
		if ( ! self::is_ready() ) {
			self::on_ready(
				static function () use ( $group ): void {
					self::unschedule_all_for_group( $group );
				}
			);
			return;
		}
		\as_unschedule_all_actions( '', array(), $group );
	}

	/**
	 * Whether an action matching hook + args + group is already pending.
	 *
	 * @param string           $hook  Hook name.
	 * @param array<int,mixed> $args  Positional args.
	 * @param string           $group Scheduler group.
	 * @return bool
	 */
	public static function has_pending( string $hook, array $args = array(), string $group = self::GROUP_FREE ): bool {
		if ( ! self::is_ready() ) {
			return false;
		}
		return false !== \as_next_scheduled_action( $hook, $args, $group );
	}

	/**
	 * When background jobs stopped running, or 0 while they run.
	 *
	 * A plugin job left pending well past its time means the queue is not being
	 * worked: usually DISABLE_WP_CRON with no server cron behind it. Delayed
	 * message emails, late-order checks and auto-complete then never happen, and
	 * nothing used to say so (Zoho #41859).
	 *
	 * WP-Cron only fires on traffic, so the first admin visit after a quiet spell
	 * finds jobs overdue that this very visit is about to run. The stall is
	 * therefore reported only once it has been seen on two visits at least ten
	 * minutes apart. The option is written only when that state changes.
	 *
	 * @since 1.9.2
	 *
	 * @param int $grace Seconds a job may be late before it counts. Default one hour.
	 * @return int Scheduled time (UTC timestamp) of the oldest overdue job, or 0.
	 */
	public static function stalled_since( int $grace = HOUR_IN_SECONDS ): int {
		if ( ! self::is_ready() || ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}

		// One row, oldest first, on Action Scheduler's indexed status + date columns.
		$ids = \as_get_scheduled_actions(
			array(
				'group'        => self::GROUP_FREE,
				'status'       => \ActionScheduler_Store::STATUS_PENDING,
				'date'         => gmdate( 'Y-m-d H:i:s', time() - $grace ),
				'date_compare' => '<=',
				'orderby'      => 'date',
				'order'        => 'ASC',
				'per_page'     => 1,
			),
			'ids'
		);

		$seen = (int) get_option( 'wpss_jobs_stall_seen', 0 );

		if ( empty( $ids ) ) {
			if ( $seen ) {
				delete_option( 'wpss_jobs_stall_seen' );
			}
			return 0;
		}

		if ( ! $seen ) {
			update_option( 'wpss_jobs_stall_seen', time(), false );
			return 0;
		}

		if ( $seen > time() - 10 * MINUTE_IN_SECONDS ) {
			return 0;
		}

		$date = \ActionScheduler::store()->get_date( (int) reset( $ids ) );

		return $date ? (int) $date->getTimestamp() : 0;
	}
}
