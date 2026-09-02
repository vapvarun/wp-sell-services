<?php
/**
 * REST contract smoke command.
 *
 * @package WPSellServices\CLI
 * @since   1.6.0
 */

declare(strict_types=1);

namespace WPSellServices\CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Assert the REST error contract the mobile/web client depends on.
 *
 * Clients branch on the HTTP status: 401 means "refresh the token and retry",
 * 403 means "stop, this will never work", 404 means "gone". Mixing those causes
 * infinite retry loops, spurious logouts, or a feature cached as permanently
 * unavailable. This command asserts the contract instead of trusting that it
 * still holds after a refactor (Basecamp #10154921558).
 *
 * It runs in-process through rest_do_request(), so it needs no cookies, no
 * nonces and no HTTP server - which also means it can run in CI.
 *
 * @since 1.6.0
 */
class RestContractCommand {

	/**
	 * Roles the contract is asserted for, resolved to real users at run time.
	 *
	 * @var array<string, string>
	 */
	private const PERSONAS = array(
		'anon'   => '',
		'buyer'  => 'customer',
		'vendor' => 'wpss_vendor',
		'admin'  => 'administrator',
	);

	/**
	 * Assert the REST status/code contract.
	 *
	 * Exits non-zero on the first violated expectation so CI fails loudly.
	 *
	 * ## OPTIONS
	 *
	 * [--porcelain]
	 * : Print only the failure count.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpss rest:contract
	 *     wp wpss rest:contract --porcelain
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args = array() ): void {
		$porcelain = isset( $assoc_args['porcelain'] );
		$users     = $this->resolve_personas();
		$failures  = array();
		$checks    = 0;

		foreach ( $this->expectations() as $case ) {
			list( $method, $route, $persona, $want_status, $want_code ) = $case;

			if ( ! isset( $users[ $persona ] ) ) {
				\WP_CLI::warning( sprintf( 'No %s user on this site; skipping %s %s.', $persona, $method, $route ) );
				continue;
			}

			++$checks;

			wp_set_current_user( $users[ $persona ] );

			$response = rest_do_request( new \WP_REST_Request( $method, $route ) );
			$status   = $response->get_status();
			$data     = $response->get_data();
			$code     = is_array( $data ) && isset( $data['code'] ) ? (string) $data['code'] : '';

			$ok = ( $status === $want_status ) && ( '' === $want_code || $code === $want_code );

			if ( ! $ok ) {
				$failures[] = sprintf(
					'%s %s as %s: expected %d %s, got %d %s',
					$method,
					$route,
					$persona,
					$want_status,
					$want_code ?: '(any code)',
					$status,
					$code ?: '(no code)'
				);
			}

			if ( ! $porcelain ) {
				\WP_CLI::log(
					sprintf(
						'  %s %-30s %-7s %s %s',
						$ok ? '✓' : '✗',
						$route,
						$persona,
						$status,
						$code
					)
				);
			}
		}

		wp_set_current_user( 0 );

		if ( $porcelain ) {
			\WP_CLI::log( (string) count( $failures ) );
		}

		if ( $failures ) {
			foreach ( $failures as $f ) {
				\WP_CLI::warning( $f );
			}
			\WP_CLI::error( sprintf( '%d of %d REST contract checks failed.', count( $failures ), $checks ) );
		}

		\WP_CLI::success( sprintf( 'All %d REST contract checks passed.', $checks ) );
	}

	/**
	 * The contract, as (method, route, persona, expected status, expected code).
	 *
	 * An empty expected code means "any code" - used where the body is a success
	 * payload rather than an error.
	 *
	 * @return array<int, array{0:string,1:string,2:string,3:int,4:string}>
	 */
	private function expectations(): array {
		return array(
			// Anonymous on a protected route must be 401, never 403. This is the
			// one a client's re-auth rule actually keys off.
			array( 'GET', '/wpss/v1/me', 'anon', 401, 'rest_not_logged_in' ),
			array( 'GET', '/wpss/v1/orders', 'anon', 401, 'rest_not_logged_in' ),
			array( 'GET', '/wpss/v1/orders/999999999/files/no-such-file', 'anon', 401, 'rest_not_logged_in' ),
			array( 'GET', '/wpss/v1/moderation/pending', 'anon', 401, 'rest_not_logged_in' ),

			// Authenticated but lacking the capability must be 403 with the
			// capability code - NOT the ownership code, which means something
			// else entirely.
			array( 'GET', '/wpss/v1/moderation/pending', 'buyer', 403, 'wpss_not_admin' ),
			array( 'GET', '/wpss/v1/moderation/pending', 'vendor', 403, 'wpss_not_admin' ),

			// The same route must succeed for someone who does hold it.
			array( 'GET', '/wpss/v1/moderation/pending', 'admin', 200, '' ),

			// A missing resource is 404 with our code, not a bare rest_no_route.
			array( 'GET', '/wpss/v1/orders/999999999', 'admin', 404, 'wpss_order_not_found' ),
			// The file route runs wpss_can_read_order_files(), and an admin may
			// read any order's files - so a missing file is 404 with the file
			// code, never a 403 that hides whether the order exists.
			array( 'GET', '/wpss/v1/orders/999999999/files/no-such-file', 'admin', 404, 'wpss_file_not_found' ),

			// An unknown path is core's 404.
			array( 'GET', '/wpss/v1/no-such-route', 'anon', 404, 'rest_no_route' ),

			// Public routes stay public.
			array( 'GET', '/wpss/v1/services', 'anon', 200, '' ),
			array( 'GET', '/wpss/v1/vendors', 'anon', 200, '' ),
		);
	}

	/**
	 * Resolve one real user id per persona.
	 *
	 * @return array<string, int>
	 */
	private function resolve_personas(): array {
		$users = array( 'anon' => 0 );

		foreach ( self::PERSONAS as $persona => $role ) {
			if ( '' === $role ) {
				continue;
			}

			$found = get_users(
				array(
					'role'   => $role,
					'number' => 1,
					'fields' => 'ID',
				)
			);

			if ( $found ) {
				$users[ $persona ] = (int) $found[0];
			}
		}

		return $users;
	}
}
