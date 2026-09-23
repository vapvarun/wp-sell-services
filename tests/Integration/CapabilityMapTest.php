<?php
/**
 * Every wpss_* capability declares its SCOPE, and the declaration matches the roles.
 *
 * A capability name does not say whether it means "your own" or "anyone's", and
 * reading one the wrong way is a privilege escalation. wpss_manage_services
 * reads like a site-wide management power; every vendor is granted it at
 * registration. The FluentCart order routes gated on it and handed every
 * buyer's billing address and every competitor's earnings to any self-registered
 * vendor (Basecamp 10321653293).
 *
 * WPCS, PHPStan and the browser smoke all passed on that, because none of them
 * knows what a capability is supposed to mean. audit/capability-map.json says
 * what each one means, and this test fails when the code and the saying part
 * company:
 *
 *   1. a capability a role actually holds but the map does not declare;
 *   2. a capability the map declares that no role holds;
 *   3. a roles block that no longer matches the live role objects;
 *   4. a wpss_* capability checked bare in source without an allow-list entry
 *      explaining why a bare check is the right authority there.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Integration;

use WPSellServices\Tests\TestCase;

class CapabilityMapTest extends TestCase {

	private const MANIFEST = __DIR__ . '/../../audit/capability-map.json';

	private const SRC = __DIR__ . '/../../src';

	/** @var array<string, mixed> */
	private array $map = array();

	protected function set_up(): void {
		parent::set_up();

		$this->map = (array) json_decode( (string) file_get_contents( self::MANIFEST ), true );
	}

	/**
	 * Every wpss_* capability held by a live role, keyed by capability.
	 *
	 * @return array<string, array<int, string>> capability => roles holding it.
	 */
	private function live_capabilities(): array {
		$out = array();

		foreach ( wp_roles()->roles as $slug => $role ) {
			foreach ( array_keys( array_filter( (array) ( $role['capabilities'] ?? array() ) ) ) as $cap ) {
				if ( ! $this->is_ours( (string) $cap ) ) {
					continue;
				}

				$out[ $cap ][] = $slug;
			}
		}

		foreach ( $out as $cap => $roles ) {
			sort( $roles );
			$out[ $cap ] = $roles;
		}

		ksort( $out );

		return $out;
	}

	/** Capabilities this plugin owns: its own prefix, plus its CPT caps. */
	private function is_ours( string $cap ): bool {
		return 0 === strpos( $cap, 'wpss_' ) || false !== strpos( $cap, 'wpss_service' );
	}

	/**
	 * TRIPWIRE - Trap 1.
	 *
	 * If the roles registry is unavailable, live_capabilities() returns nothing
	 * and every comparison below passes against an empty set. The emptiness is
	 * the failure.
	 */
	public function test_the_role_registry_is_not_empty(): void {
		$live = $this->live_capabilities();

		$this->assertNotEmpty(
			$live,
			'No wpss_* capability was found on any role. wp_roles() did not load, or the plugin did not register its roles - '
			. 'either way every other check in this file would pass without comparing anything.'
		);

		$this->assertNotEmpty( $this->map['scope'] ?? array(), 'audit/capability-map.json declares no capabilities.' );
	}

	public function test_every_capability_a_role_holds_is_declared(): void {
		$declared   = array_keys( (array) ( $this->map['scope'] ?? array() ) );
		$undeclared = array_diff( array_keys( $this->live_capabilities() ), $declared );

		$this->assertSame(
			array(),
			array_values( $undeclared ),
			"These capabilities are granted to a role but declared nowhere.\n"
			. "Add each to audit/capability-map.json with its scope (own|any|site|feature).\n"
			. "A capability nobody scoped is a capability the next reader will guess at.\n"
			. implode( "\n", $undeclared )
		);
	}

	public function test_the_map_declares_no_capability_that_no_role_holds(): void {
		$live  = array_keys( $this->live_capabilities() );
		$stale = array_diff( array_keys( (array) ( $this->map['scope'] ?? array() ) ), $live );

		$this->assertSame(
			array(),
			array_values( $stale ),
			"These capabilities are declared but no role holds them. Remove them, or grant them.\n"
			. implode( "\n", $stale )
		);
	}

	public function test_every_declaration_names_a_valid_scope(): void {
		$valid    = array( 'own', 'any', 'site', 'feature' );
		$problems = array();

		foreach ( (array) ( $this->map['scope'] ?? array() ) as $cap => $entry ) {
			$scope = (string) ( $entry['scope'] ?? '' );

			if ( ! in_array( $scope, $valid, true ) ) {
				$problems[] = "{$cap} - scope '{$scope}' is not one of " . implode( '|', $valid );
			}

			if ( '' === trim( (string) ( $entry['means'] ?? '' ) ) ) {
				$problems[] = "{$cap} - no 'means' text. Say what it authorises, in words.";
			}
		}

		$this->assertSame( array(), $problems, implode( "\n", $problems ) );
	}

	/**
	 * The declared holders must be the actual holders.
	 *
	 * This is the assertion that would have caught the FluentCart gate: the map
	 * says wpss_manage_services is held by every vendor, so a reader reaching
	 * for it as an admin check has the contradiction in front of them.
	 */
	public function test_declared_holders_match_the_live_roles(): void {
		$drifted = array();

		foreach ( $this->live_capabilities() as $cap => $roles ) {
			$declared = (array) ( $this->map['scope'][ $cap ]['held_by'] ?? array() );
			sort( $declared );

			if ( $declared !== $roles ) {
				$drifted[] = "{$cap} - map says [" . implode( ', ', $declared ) . '], roles have [' . implode( ', ', $roles ) . ']';
			}
		}

		$this->assertSame( array(), $drifted, implode( "\n", $drifted ) );
	}

	/**
	 * A bare wpss_* capability check must be allow-listed with a reason.
	 *
	 * Bare is not automatically wrong - a create-time entry gate has no object
	 * to own yet. But it must be someone's stated decision, not an accident.
	 */
	public function test_bare_capability_checks_are_allow_listed(): void {
		$allow      = (array) ( $this->map['bare_check_allowlist'] ?? array() );
		$unexplained = array();
		$found       = 0;

		foreach ( $this->php_files( self::SRC ) as $file ) {
			$source = (string) file_get_contents( $file );

			// Comments stripped, so a commented-out check cannot satisfy - nor
			// trip - this walk (Trap 1).
			$code = '';
			foreach ( token_get_all( $source ) as $token ) {
				if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
					$code .= str_repeat( "\n", substr_count( $token[1], "\n" ) );
					continue;
				}
				$code .= is_array( $token ) ? $token[1] : $token;
			}

			foreach ( explode( "\n", $code ) as $i => $line ) {
				if ( ! preg_match( "/current_user_can\(\s*['\"](wpss_[a-z_]+)['\"]/", $line ) ) {
					continue;
				}

				++$found;

				$key = ltrim( str_replace( realpath( dirname( self::SRC ) ), '', (string) realpath( $file ) ), '/' )
					. ':' . ( $i + 1 );

				if ( '' === trim( (string) ( $allow[ $key ] ?? '' ) ) ) {
					$unexplained[] = $key . ' - ' . trim( $line );
				}
			}
		}

		$this->assertGreaterThan(
			0,
			$found,
			'The source sweep found no capability checks at all, so this test proved nothing. Check the SRC path and the pattern.'
		);

		$this->assertSame(
			array(),
			$unexplained,
			"These wpss_* capability checks are bare - no ownership comparison beside them - and not allow-listed.\n"
			. "Either pair the check with an ownership test, or record in bare_check_allowlist WHY bare is right here.\n"
			. implode( "\n", $unexplained )
		);
	}

	/** @return array<int, string> */
	private function php_files( string $dir ): array {
		$out = array();

		$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $it as $file ) {
			if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
				$out[] = $file->getPathname();
			}
		}

		return $out;
	}
}
