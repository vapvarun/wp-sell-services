<?php
/**
 * Action Scheduler function stubs for PHPStan static analysis.
 *
 * These functions are bundled via vendor/woocommerce/action-scheduler at
 * runtime but are declared inside a wrapper file PHPStan can't follow. The
 * stubs here mirror the public API exposed by
 * vendor/woocommerce/action-scheduler/functions.php.
 *
 * @package WPSellServices
 */

// phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase

if ( ! function_exists( 'as_schedule_single_action' ) ) {
	/**
	 * Schedule a single action.
	 *
	 * @param int                  $timestamp When the action should run.
	 * @param string               $hook      Hook name.
	 * @param array<int,mixed>     $args      Args.
	 * @param string               $group     Group.
	 * @param bool                 $unique    Unique.
	 * @param int                  $priority  Priority.
	 * @return int Action ID.
	 */
	function as_schedule_single_action( int $timestamp, string $hook, array $args = array(), string $group = '', bool $unique = false, int $priority = 10 ): int {
		return 0;
	}
}

if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
	/**
	 * Schedule a recurring action.
	 *
	 * @param int              $timestamp First run.
	 * @param int              $interval  Interval seconds.
	 * @param string           $hook      Hook.
	 * @param array<int,mixed> $args      Args.
	 * @param string           $group     Group.
	 * @param bool             $unique    Unique.
	 * @param int              $priority  Priority.
	 * @return int Action ID.
	 */
	function as_schedule_recurring_action( int $timestamp, int $interval, string $hook, array $args = array(), string $group = '', bool $unique = false, int $priority = 10 ): int {
		return 0;
	}
}

if ( ! function_exists( 'as_next_scheduled_action' ) ) {
	/**
	 * Get the next scheduled action timestamp.
	 *
	 * @param string                $hook  Hook.
	 * @param array<int,mixed>|null $args  Args.
	 * @param string                $group Group.
	 * @return int|bool Timestamp, or false when not scheduled.
	 */
	function as_next_scheduled_action( string $hook, ?array $args = null, string $group = '' ) {
		return false;
	}
}

if ( ! function_exists( 'as_unschedule_action' ) ) {
	/**
	 * Unschedule a single action.
	 *
	 * @param string           $hook  Hook.
	 * @param array<int,mixed> $args  Args.
	 * @param string           $group Group.
	 * @return int|null Action ID or null when none pending.
	 */
	function as_unschedule_action( string $hook, array $args = array(), string $group = '' ): ?int {
		return null;
	}
}

if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
	/**
	 * Unschedule every action matching hook / args / group.
	 *
	 * @param string           $hook  Hook.
	 * @param array<int,mixed> $args  Args.
	 * @param string           $group Group.
	 * @return void
	 */
	function as_unschedule_all_actions( string $hook = '', array $args = array(), string $group = '' ): void {
	}
}

if ( ! function_exists( 'as_has_scheduled_action' ) ) {
	/**
	 * Whether an action with the given hook is scheduled.
	 *
	 * @param string                $hook  Hook.
	 * @param array<int,mixed>|null $args  Args.
	 * @param string                $group Group.
	 * @return bool
	 */
	function as_has_scheduled_action( string $hook, ?array $args = null, string $group = '' ): bool {
		return false;
	}
}
