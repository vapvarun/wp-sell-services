<?php
/**
 * Every scheduled job is declared: what it touches, whether it moves money, and
 * which identity it acts as.
 *
 * A scheduled callback runs as nobody: no nonce, no capability, and on the
 * Action Scheduler async runner sometimes the cookie of whichever admin opened
 * the page that kicked the queue. A job that reads get_current_user_id() or
 * current_user_can() is deciding with an identity nobody chose, so it either
 * says why that is safe or it does not read one.
 *
 * Jobs are found where they actually live - the Action Scheduler store (groups
 * wpss and wpss-pro, any status) and WP-Cron - after the recurring set is
 * scheduled, so a fresh install still sees every recurring job. A one-off job
 * that has never been queued on this database is not seen; its declaration is
 * still checked for a listener and for the identity rule.
 *
 * Each declaration names in runs the method(s) that do the work, because most
 * listeners are one-line closures that hand off to a service.
 *
 * Fails when a job is queued but undeclared, when a declaration has no listener
 * and no conditional_on, when a runs method no longer exists, when a hook
 * declared retired is still pending, when a listener reads the current user without an
 * actor_reason, and when a declaration leaves moves_money or touches unset.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;

class ScheduledWorkAuthorityTest extends TestCase {

	private const MANIFEST = __DIR__ . '/../../audit/scheduled-work.json';

	private const PAIRED = __DIR__ . '/../../../wp-sell-services-pro/audit/scheduled-work.json';

	/** @return array<string, array{string}> */
	public static function plugins(): array {
		return array(
			'free' => array( 'free' ),
			'pro'  => array( 'pro' ),
		);
	}

	private function manifest_of( string $plugin ): array {
		if ( 'pro' === $plugin && ( ! class_exists( 'WPSellServicesPro\\Core\\Pro' ) || ! file_exists( self::PAIRED ) ) ) {
			$this->markTestSkipped( 'Pro is not active, so its jobs are not registered.' );
		}

		return (array) ( json_decode( (string) file_get_contents( 'pro' === $plugin ? self::PAIRED : self::MANIFEST ), true )['jobs'] ?? array() );
	}

	/** Hooks this plugin stopped using: past rows may remain, nothing may be pending. */
	private function retired_of( string $plugin ): array {
		$file = 'pro' === $plugin ? self::PAIRED : self::MANIFEST;

		return (array) ( json_decode( (string) file_get_contents( $file ), true )['retired'] ?? array() );
	}

	/**
	 * @dataProvider plugins
	 */
	public function test_a_retired_job_is_not_still_pending( string $plugin ): void {
		$this->manifest_of( $plugin );
		$pending = array();

		foreach ( array_keys( $this->retired_of( $plugin ) ) as $hook ) {
			if ( false !== as_next_scheduled_action( (string) $hook ) || false !== wp_next_scheduled( (string) $hook ) ) {
				$pending[] = $hook;
			}
		}

		$this->assertSame( array(), $pending, 'Declared retired but still queued: ' . implode( ', ', $pending ) );
	}

	private static function owner( string $hook, string $group ): string {
		return ( 'wpss-pro' === $group || 0 === strpos( $hook, 'wpss_pro_' ) ) ? 'pro' : 'free';
	}

	/**
	 * Hooks queued on this site, keyed by hook, valued by owner.
	 *
	 * @return array<string, string>
	 */
	private function queued(): array {
		global $wpdb;

		\WPSellServices\Core\Activator::schedule_cron_events();

		$out  = array();
		$rows = (array) $wpdb->get_results(
			"SELECT DISTINCT a.hook, g.slug FROM {$wpdb->prefix}actionscheduler_actions a
			JOIN {$wpdb->prefix}actionscheduler_groups g ON g.group_id = a.group_id
			WHERE g.slug IN ( 'wpss', 'wpss-pro' )"
		); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		foreach ( $rows as $row ) {
			$out[ (string) $row->hook ] = self::owner( (string) $row->hook, (string) $row->slug );
		}

		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( array_keys( (array) $events ) as $hook ) {
				if ( 0 === strpos( (string) $hook, 'wpss' ) ) {
					$out[ (string) $hook ] = self::owner( (string) $hook, '' );
				}
			}
		}

		return $out;
	}

	/**
	 * Each listener on a hook, with its own body (comments stripped).
	 *
	 * @return array<string, string> callback name => source.
	 */
	private function listeners( string $hook ): array {
		$out = array();

		foreach ( (array) ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array() ) as $callbacks ) {
			foreach ( $callbacks as $cb ) {
				$fn = $cb['function'] ?? null;

				try {
					if ( is_array( $fn ) && isset( $fn[0], $fn[1] ) ) {
						$class = is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0];
						$ref   = new \ReflectionMethod( $class, (string) $fn[1] );
						$name  = $class . '::' . $fn[1];
					} elseif ( $fn instanceof \Closure || ( is_string( $fn ) && function_exists( $fn ) ) ) {
						$ref  = new \ReflectionFunction( $fn );
						$name = is_string( $fn ) ? $fn : 'Closure@' . basename( (string) $ref->getFileName() ) . ':' . $ref->getStartLine();
					} else {
						continue;
					}
				} catch ( \Throwable $e ) {
					continue;
				}

				$lines        = (array) file( (string) $ref->getFileName() );
				$body         = implode( '', array_slice( $lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1 ) );
				$out[ $name ] = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $body );
			}
		}

		return $out;
	}

	/**
	 * The body of a declared "Class::method", or null when it no longer exists.
	 */
	private static function method_source( string $target ): ?string {
		try {
			$ref = new \ReflectionMethod( ...explode( '::', $target, 2 ) );
		} catch ( \Throwable $e ) {
			return null;
		}

		$lines = (array) file( (string) $ref->getFileName() );
		$body  = implode( '', array_slice( $lines, $ref->getStartLine() - 1, $ref->getEndLine() - $ref->getStartLine() + 1 ) );

		return (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $body );
	}

	/** TRIPWIRE: an empty store would make every check below pass on nothing. */
	public function test_the_store_holds_our_recurring_jobs(): void {
		$this->assertArrayHasKey( 'wpss_check_late_orders', $this->queued(), 'No recurring job was found queued, so this test would prove nothing.' );
	}

	/**
	 * @dataProvider plugins
	 */
	public function test_every_queued_job_is_declared( string $plugin ): void {
		$declared   = $this->manifest_of( $plugin ) + $this->retired_of( $plugin );
		$undeclared = array_keys( array_diff_key( array_filter( $this->queued(), static fn( $o ) => $o === $plugin ), $declared ) );

		$this->assertSame(
			array(),
			$undeclared,
			"These jobs are queued but declared nowhere. Add each to audit/scheduled-work.json.\n" . implode( "\n", $undeclared )
		);
	}

	/**
	 * @dataProvider plugins
	 */
	public function test_every_declaration_is_complete_and_has_a_listener( string $plugin ): void {
		$problems = array();

		foreach ( $this->manifest_of( $plugin ) as $hook => $entry ) {
			$entry = (array) $entry;

			if ( ! is_bool( $entry['moves_money'] ?? null ) || '' === trim( (string) ( $entry['touches'] ?? '' ) ) ) {
				$problems[] = "{$hook} - say what it touches and whether it moves money.";
			}

			$runs = (array) ( $entry['runs'] ?? array() );
			if ( array() === $runs ) {
				$problems[] = "{$hook} - name the method(s) that do the work in runs.";
			}
			foreach ( $runs as $target ) {
				if ( null === self::method_source( (string) $target ) ) {
					$problems[] = "{$hook} - runs names {$target}, which does not exist.";
				}
			}

			if ( array() === $this->listeners( (string) $hook ) && '' === trim( (string) ( $entry['conditional_on'] ?? '' ) ) ) {
				$problems[] = "{$hook} - nothing listens, so the job does nothing. Remove it or record conditional_on.";
			}
		}

		$this->assertSame( array(), $problems, implode( "\n", $problems ) );
	}

	/**
	 * A job decides with no identity, or says why the one it reads is safe.
	 *
	 * Reads the listener and each method the declaration names in runs; helpers
	 * those call are not followed.
	 *
	 * @dataProvider plugins
	 */
	public function test_no_job_trusts_the_current_user_without_a_reason( string $plugin ): void {
		$problems = array();
		$checked  = 0;

		foreach ( $this->manifest_of( $plugin ) as $hook => $entry ) {
			$sources = $this->listeners( (string) $hook );
			foreach ( (array) ( ( (array) $entry )['runs'] ?? array() ) as $target ) {
				$sources[ (string) $target ] = (string) self::method_source( (string) $target );
			}

			foreach ( $sources as $name => $source ) {
				++$checked;

				if ( preg_match( '/(?<![\w>:$])(get_current_user_id|wp_get_current_user|current_user_can|is_user_logged_in)\s*\(/', $source )
					&& '' === trim( (string) ( ( (array) $entry )['actor_reason'] ?? '' ) ) ) {
					$problems[] = "{$hook} ({$name}) reads the current user; a scheduled run has none it chose. Add actor_reason or stop reading it.";
				}
			}
		}

		$this->assertGreaterThan( 0, $checked, 'No listener source was examined, so this proved nothing.' );
		$this->assertSame( array(), $problems, implode( "\n", $problems ) );
	}
}
