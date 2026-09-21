<?php
/**
 * Every registered REST route declares who may reach it, and the public ones are probed.
 *
 * Nothing else in this pipeline asks the authorisation question. WPCS, PHPStan,
 * the contract audit and a green browser smoke all passed on a route that
 * returned every buyer's billing address and every vendor's earnings to any
 * self-registered vendor, because it sat behind a capability whose NAME read
 * like an admin power (Basecamp 10321653293). No tool knows what a route is
 * supposed to require, because until this manifest nobody had written it down.
 *
 * So the claim moves out of the reader's head and into
 * audit/route-authority.json, where it is checked:
 *
 *   1. a route missing from the manifest fails - a new route cannot ship
 *      without someone stating its audience;
 *   2. a route the manifest names but the code no longer registers fails, so
 *      the file keeps meaning something;
 *   3. a route whose callback is __return_true must be declared public AND
 *      carry a reason;
 *   4. the declared callback must be the one actually registered - a manifest
 *      that records yesterday's guard is worse than none;
 *   5. an unpublished service must not reach an anonymous caller through any
 *      public route that takes an object id.
 *
 * Pro carries the same test over its own manifest.
 *
 * @package WPSellServices\Tests
 */

declare(strict_types=1);

namespace WPSellServices\Tests\Unit;

use WPSellServices\Tests\TestCase;
use WP_REST_Request;

class RouteAuthorityTest extends TestCase {

	private const MANIFEST = __DIR__ . '/../../audit/route-authority.json';

	/**
	 * Routes this plugin declares. Pro registers into the same namespace, so
	 * membership is decided by which manifest names the route, not by the path.
	 */
	private const NAMESPACES = array( 'wpss/v1', 'wpss-pro/v1' );

	/** @var array<string, array<string, mixed>> */
	private array $manifest = array();

	/** @var array<int> Fixtures to remove in tear_down(). */
	private array $created = array();

	protected function set_up(): void {
		parent::set_up();

		$decoded        = json_decode( (string) file_get_contents( self::MANIFEST ), true );
		$this->manifest = (array) ( $decoded['routes'] ?? array() );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		do_action( 'rest_api_init' );
	}

	protected function tear_down(): void {
		foreach ( $this->created as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->created = array();

		wp_set_current_user( 0 );

		parent::tear_down();
	}

	/**
	 * The live registry, keyed exactly as the manifest keys it.
	 *
	 * @return array<string, array{route:string, methods:array<int,string>, public:bool, object_id:bool, callback:string}>
	 */
	private function live_routes(): array {
		$out = array();

		foreach ( rest_get_server()->get_routes() as $route => $handlers ) {
			$ns = '';
			foreach ( self::NAMESPACES as $candidate ) {
				if ( 0 === strpos( ltrim( $route, '/' ), $candidate ) ) {
					$ns = $candidate;
					break;
				}
			}

			if ( '' === $ns || rtrim( '/' . $ns, '/' ) === rtrim( $route, '/' ) ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				$methods = array_keys( array_filter( (array) ( $handler['methods'] ?? array() ) ) );
				$cb      = $handler['permission_callback'] ?? null;

				if ( is_array( $cb ) && isset( $cb[0], $cb[1] ) ) {
					$name = ( is_object( $cb[0] ) ? get_class( $cb[0] ) : (string) $cb[0] ) . '::' . $cb[1];
				} elseif ( $cb instanceof \Closure ) {
					$name = 'Closure';
				} elseif ( is_string( $cb ) ) {
					$name = $cb;
				} else {
					$name = 'NONE';
				}

				$out[ implode( ',', $methods ) . ' ' . $route ] = array(
					'route'     => $route,
					'methods'   => $methods,
					'public'    => ( '__return_true' === $name ),
					'object_id' => (bool) preg_match( '/\(\?P<[a-z_]*id>/', $route ),
					'callback'  => $name,
				);
			}
		}

		return $out;
	}

	/** Only the live routes this plugin's manifest claims. */
	private function mine(): array {
		return array_intersect_key( $this->live_routes(), $this->manifest );
	}

	/**
	 * TRIPWIRE - Trap 1 in the standard.
	 *
	 * If rest_api_init has not run, or a namespace is renamed, live_routes()
	 * comes back empty and every other assertion in this file passes against
	 * zero routes. That is exactly how a guard ends up green on provably
	 * vulnerable code, so the emptiness itself is the failure.
	 */
	public function test_the_registry_is_not_empty(): void {
		$live = $this->live_routes();

		$this->assertNotEmpty(
			$live,
			'The REST registry came back empty, so every other check in this file would pass without testing anything. '
			. 'rest_api_init did not run, or the namespaces in self::NAMESPACES no longer match what the plugin registers.'
		);

		$this->assertNotEmpty(
			$this->manifest,
			'audit/route-authority.json parsed to no routes. A manifest that reads as empty silently excuses every route.'
		);
	}

	public function test_every_live_route_declares_its_audience(): void {
		$undeclared = array();

		foreach ( $this->live_routes() as $key => $live ) {
			if ( isset( $this->manifest[ $key ] ) ) {
				continue;
			}

			// A route owned by the paired plugin is declared in ITS manifest.
			if ( $this->declared_by_pair( $key ) ) {
				continue;
			}

			$undeclared[] = $key;
		}

		$this->assertSame(
			array(),
			$undeclared,
			"These routes are registered but declared nowhere.\n"
			. "Add each to audit/route-authority.json with its audience (public|member|owner|admin|guarded).\n"
			. "A route nobody declared is a route nobody reviewed.\n"
			. implode( "\n", $undeclared )
		);
	}

	public function test_the_manifest_has_no_routes_that_no_longer_exist(): void {
		$stale = array_keys( array_diff_key( $this->manifest, $this->live_routes() ) );

		$this->assertSame(
			array(),
			$stale,
			"These routes are declared but no longer registered. Remove them so the manifest keeps meaning something.\n"
			. implode( "\n", $stale )
		);
	}

	public function test_public_routes_are_declared_public_and_say_why(): void {
		$problems = array();

		foreach ( $this->mine() as $key => $live ) {
			$declared = $this->manifest[ $key ];
			$audience = (string) ( $declared['audience'] ?? '' );

			if ( $live['public'] && 'public' !== $audience ) {
				$problems[] = $key . ' - permission_callback is __return_true but declared "' . $audience . '"';
				continue;
			}

			if ( ! $live['public'] && 'public' === $audience ) {
				$problems[] = $key . ' - declared public but its callback is ' . $live['callback'];
				continue;
			}

			if ( 'public' === $audience ) {
				$reason = trim( (string) ( $declared['reason'] ?? '' ) );

				if ( '' === $reason || 0 === stripos( $reason, 'todo' ) ) {
					$problems[] = $key . ' - declared public with no reason. Say what a stranger gets from it.';
				}
			}
		}

		$this->assertSame( array(), $problems, implode( "\n", $problems ) );
	}

	/**
	 * The declared guard must be the guard that is registered.
	 *
	 * Without this, renaming or swapping a permission callback leaves the
	 * manifest describing a check that no longer runs - the manifest would
	 * then be actively misleading rather than merely stale.
	 */
	public function test_the_declared_callback_is_the_registered_one(): void {
		$drifted = array();

		foreach ( $this->mine() as $key => $live ) {
			$declared = (string) ( $this->manifest[ $key ]['permission_callback'] ?? '' );

			if ( $declared !== $live['callback'] ) {
				$drifted[] = $key . " - manifest says {$declared}, registry has {$live['callback']}";
			}
		}

		$this->assertSame( array(), $drifted, implode( "\n", $drifted ) );
	}

	/**
	 * A public route that takes an object id must not serve an unpublished one.
	 *
	 * This is the leak shape that applies to a marketplace: a service that is
	 * draft, pending moderation or private is somebody's unfinished or refused
	 * listing, and a public catalogue route that resolves it by id hands it to
	 * anyone who can count.
	 */
	public function test_public_object_routes_do_not_serve_unpublished_services(): void {
		$vendor = $this->probe_vendor_id();

		$this->assertGreaterThan( 0, $vendor, 'No vendor to own the probe fixture; the probe would prove nothing.' );

		$secret = 'ROUTE-AUTHORITY-PROBE-SECRET';
		$probed = 0;
		$leaks  = array();

		foreach ( array( 'draft', 'pending', 'private' ) as $status ) {
			$service_id = wp_insert_post(
				array(
					'post_type'    => 'wpss_service',
					'post_title'   => $secret . ' ' . $status,
					'post_content' => str_repeat( 'x', 200 ),
					'post_status'  => $status,
					'post_author'  => $vendor,
				)
			);

			if ( ! $service_id || is_wp_error( $service_id ) ) {
				continue;
			}

			$this->created[] = (int) $service_id;

			wp_set_current_user( 0 );

			foreach ( $this->mine() as $key => $live ) {
				if ( 'public' !== ( $this->manifest[ $key ]['audience'] ?? '' ) ) {
					continue;
				}
				if ( ! $live['object_id'] || ! in_array( 'GET', $live['methods'], true ) ) {
					continue;
				}
				if ( false === strpos( $live['route'], '/services/' ) ) {
					continue;
				}

				// The route carries a literal [\d], so this pattern needs \\\\d.
				// One backslash short and it matches nothing, the loop body
				// never runs, and this test passes having probed zero routes.
				$path = (string) preg_replace( '/\(\?P<[a-z_]*id>\[\\\\d\]\+\)/', (string) $service_id, $live['route'] );

				if ( $path === $live['route'] ) {
					$leaks[] = $key . ' - the probe could not build a path for this route, so it proved nothing';
					continue;
				}

				++$probed;

				$response = rest_do_request( new WP_REST_Request( 'GET', $path ) );
				$body     = (string) wp_json_encode( $response->get_data() );

				if ( 200 === $response->get_status() && false !== strpos( $body, $secret ) ) {
					$leaks[] = $key . " - a {$status} service reached an anonymous caller";
				}
			}
		}

		$this->assertGreaterThan(
			0,
			$probed,
			'The probe dispatched no routes at all. A pass here would mean nothing - check the route pattern above.'
		);

		$this->assertSame( array(), $leaks, implode( "\n", $leaks ) );
	}

	/** A vendor to own the probe fixture. */
	private function probe_vendor_id(): int {
		$vendors = get_users( array( 'role' => 'wpss_vendor', 'number' => 1, 'fields' => 'ID' ) );

		if ( ! empty( $vendors ) ) {
			return (int) $vendors[0];
		}

		$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );

		return empty( $admins ) ? 0 : (int) $admins[0];
	}

	/** Is this route declared in the paired plugin's manifest? */
	private function declared_by_pair( string $key ): bool {
		$paired = dirname( __DIR__, 3 ) . '/wp-sell-services-pro/audit/route-authority.json';

		if ( ! file_exists( $paired ) ) {
			return false;
		}

		$decoded = json_decode( (string) file_get_contents( $paired ), true );

		return isset( $decoded['routes'][ $key ] );
	}
}
