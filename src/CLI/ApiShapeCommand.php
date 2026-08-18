<?php
/**
 * REST payload shape audit.
 *
 * @package WPSellServices\CLI
 * @since   1.6.0
 */

declare(strict_types=1);


namespace WPSellServices\CLI;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Walks every registered GET route and asserts the payload conventions.
 *
 * This exists because the same class of bug was reported three times against
 * Basecamp 10154919636, and twice the fix was verified against a HAND-PICKED
 * SAMPLE of endpoints and reported as if it covered the API. Both times the
 * gaps were on routes that were simply not in the sample.
 *
 * A check that lives in somebody's terminal is not a check. This walks the
 * route table itself, so an endpoint added tomorrow is audited without anyone
 * remembering to add it to a list.
 *
 * Two conventions are asserted:
 *
 * 1. DATES are ISO-8601 with an offset. A bare MySQL datetime carries no
 *    timezone, so a client has to guess - and half the API used to guess
 *    differently from the other half.
 * 2. ACTORS are the wpss_rest_user() shape. Any object carrying id + name +
 *    avatar but no `deleted` is a hand-built copy: it cannot tell a client
 *    that a member's account is gone, which matters because orders and
 *    conversations outlive the people in them.
 *
 * @since 1.6.0
 */
class ApiShapeCommand {

	/**
	 * Anything matching this is a MySQL datetime, which must not reach a client.
	 */
	private const MYSQL_DATE = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';

	/**
	 * Audit every GET route's payload.
	 *
	 * ## OPTIONS
	 *
	 * [--user=<login-or-id>]
	 * : Run as this member. Defaults to the first administrator, because most
	 * routes return nothing useful to a logged-out caller.
	 *
	 * [--route=<substring>]
	 * : Only audit routes containing this substring.
	 *
	 * [--verbose]
	 * : List every route audited, not just the failures.
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpss api:shapes
	 *     wp wpss api:shapes --route=conversations --verbose
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 * @return void
	 */
	public static function shapes( array $args, array $assoc_args ): void {
		unset( $args );

		self::authenticate( $assoc_args['user'] ?? '' );

		$filter  = (string) ( $assoc_args['route'] ?? '' );
		$verbose = isset( $assoc_args['verbose'] );

		$routes = self::collect_routes( $filter );

		if ( empty( $routes ) ) {
			WP_CLI::error( 'No GET routes matched.' );
		}

		$failures = array();
		$skipped  = array();
		$audited  = 0;

		foreach ( $routes as $route ) {
			$path = self::fill_parameters( $route );

			/*
			 * A route whose placeholders could not be filled is REPORTED, not
			 * silently passed over. Sampling that hides its own gaps is the
			 * failure this command was written for - the last miss was on a
			 * parameterised route that never got a live id.
			 */
			if ( false !== strpos( $path, '(?P<' ) ) {
				$skipped[] = $route;
				continue;
			}

			$response = rest_do_request( new \WP_REST_Request( 'GET', $path ) );

			if ( $response->is_error() || $response->get_status() >= 400 ) {
				$skipped[] = $path . ' (HTTP ' . $response->get_status() . ')';
				continue;
			}

			++$audited;

			$problems = array();
			self::inspect( $response->get_data(), '', $problems );

			if ( $problems ) {
				$failures[ $path ] = $problems;
			} elseif ( $verbose ) {
				WP_CLI::log( '  ok    ' . $path );
			}
		}

		WP_CLI::log( '' );
		WP_CLI::log( sprintf( 'Audited %d route(s) with a response body.', $audited ) );

		if ( $skipped ) {
			WP_CLI::log( sprintf( 'Not audited: %d (no live row, or not readable as this user)', count( $skipped ) ) );

			if ( $verbose ) {
				foreach ( $skipped as $one ) {
					WP_CLI::log( '  skip  ' . $one );
				}
			}
		}

		if ( empty( $failures ) ) {
			WP_CLI::success( 'Every audited payload uses ISO dates and the shared actor shape.' );
			return;
		}

		WP_CLI::log( '' );

		foreach ( $failures as $path => $problems ) {
			WP_CLI::warning( $path );

			foreach ( $problems as $problem ) {
				WP_CLI::log( '    ' . $problem );
			}
		}

		WP_CLI::error( sprintf( '%d route(s) break the payload contract.', count( $failures ) ) );
	}

	/**
	 * Become somebody who can read the routes.
	 *
	 * @param string $who Login or ID, empty for the first administrator.
	 * @return void
	 */
	private static function authenticate( string $who ): void {
		if ( '' !== $who ) {
			$user = is_numeric( $who ) ? get_user_by( 'id', (int) $who ) : get_user_by( 'login', $who );

			if ( ! $user ) {
				WP_CLI::error( sprintf( 'No such user: %s', $who ) );
			}

			wp_set_current_user( $user->ID );
			WP_CLI::log( sprintf( 'Running as %s (#%d).', $user->user_login, $user->ID ) );
			return;
		}

		$admins = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
				'fields' => array( 'ID', 'user_login' ),
			)
		);

		if ( empty( $admins ) ) {
			WP_CLI::error( 'No administrator to run as. Pass --user.' );
		}

		wp_set_current_user( (int) $admins[0]->ID );
		WP_CLI::log( sprintf( 'Running as %s (#%d).', $admins[0]->user_login, $admins[0]->ID ) );
	}

	/**
	 * Every registered GET route in the plugin's namespaces.
	 *
	 * @param string $filter Optional substring filter.
	 * @return array<int, string>
	 */
	private static function collect_routes( string $filter ): array {
		$found = array();

		foreach ( rest_get_server()->get_routes() as $route => $handlers ) {
			if ( 0 !== strpos( $route, '/wpss/v1' ) && 0 !== strpos( $route, '/wpss-pro/v1' ) ) {
				continue;
			}

			if ( '' !== $filter && false === strpos( $route, $filter ) ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				if ( ! empty( $handler['methods']['GET'] ) ) {
					$found[ $route ] = true;
					break;
				}
			}
		}

		return array_keys( $found );
	}

	/**
	 * Substitute a real id for each placeholder in a route.
	 *
	 * Real rows, not invented ids: an endpoint handed an id that matches
	 * nothing answers 404 and audits nothing, which is how a parameterised
	 * route can look green while never having been read at all.
	 *
	 * @param string $route Route pattern.
	 * @return string Path with placeholders filled where possible.
	 */
	private static function fill_parameters( string $route ): string {
		global $wpdb;

		static $ids = null;

		if ( null === $ids ) {
			$prefix = $wpdb->prefix;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ids = array(
				'order_id'        => (int) $wpdb->get_var( "SELECT id FROM {$prefix}wpss_orders ORDER BY id DESC LIMIT 1" ),
				'conversation_id' => (int) $wpdb->get_var( "SELECT id FROM {$prefix}wpss_conversations ORDER BY message_count DESC LIMIT 1" ),
				'dispute_id'      => (int) $wpdb->get_var( "SELECT id FROM {$prefix}wpss_disputes ORDER BY id DESC LIMIT 1" ),
				'review_id'       => (int) $wpdb->get_var( "SELECT id FROM {$prefix}wpss_reviews ORDER BY id DESC LIMIT 1" ),
				'proposal_id'     => (int) $wpdb->get_var( "SELECT id FROM {$prefix}wpss_proposals ORDER BY id DESC LIMIT 1" ),
				'service_id'      => (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpss_service' AND post_status = 'publish' LIMIT 1" ),
				'request_id'      => (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpss_request' LIMIT 1" ),
				'vendor_id'       => get_current_user_id(),
				'user_id'         => get_current_user_id(),
				'portfolio_id'    => (int) $wpdb->get_var( "SELECT id FROM {$prefix}wpss_portfolio_items ORDER BY id DESC LIMIT 1" ),
				'media_id'        => (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' LIMIT 1" ),
			);
			// phpcs:enable

			// A bare (?P<id>) means different things per controller, so it is
			// filled from the route's own noun rather than one global guess.
			$ids['id'] = 0;
		}

		$path = $route;

		foreach ( $ids as $name => $value ) {
			if ( $value > 0 ) {
				$path = (string) preg_replace( '/\(\?P<' . preg_quote( $name, '/' ) . '>[^)]*\)/', (string) $value, $path );
			}
		}

		// Resolve a generic (?P<id>) from the route's own noun.
		if ( false !== strpos( $path, '(?P<id>' ) ) {
			$noun = array(
				'/orders'         => 'order_id',
				'/conversations'  => 'conversation_id',
				'/disputes'       => 'dispute_id',
				'/reviews'        => 'review_id',
				'/proposals'      => 'proposal_id',
				'/services'       => 'service_id',
				'/buyer-requests' => 'request_id',
				'/vendors'        => 'vendor_id',
				'/milestones'     => 'order_id',
				'/portfolio'      => 'portfolio_id',
				'/media'          => 'media_id',
			);

			foreach ( $noun as $segment => $key ) {
				if ( false !== strpos( $path, $segment ) && ! empty( $ids[ $key ] ) ) {
					$path = (string) preg_replace( '/\(\?P<id>[^)]*\)/', (string) $ids[ $key ], $path );
					break;
				}
			}
		}

		return $path;
	}

	/**
	 * Walk a payload and collect every convention breach.
	 *
	 * @param mixed              $node     Current node.
	 * @param string             $key      Key this node arrived under.
	 * @param array<int, string> $problems Collected problems, by reference.
	 * @return void
	 */
	private static function inspect( $node, string $key, array &$problems ): void {
		if ( is_object( $node ) ) {
			$node = (array) $node;
		}

		if ( is_array( $node ) ) {
			$keys = array_keys( $node );

			// An actor is anything shaped like a person. The shared helper always
			// adds `deleted`, so its absence means somebody built this by hand.
			$is_actor = in_array( 'id', $keys, true )
				&& in_array( 'name', $keys, true )
				&& in_array( 'avatar', $keys, true );

			if ( $is_actor && ! in_array( 'deleted', $keys, true ) ) {
				$problems[] = sprintf(
					'actor "%s" is missing `deleted` - build it with wpss_rest_user()',
					'' !== $key ? $key : '(root)'
				);
			}

			foreach ( $node as $child_key => $child ) {
				self::inspect( $child, is_string( $child_key ) ? $child_key : $key, $problems );
			}

			return;
		}

		if ( is_string( $node ) && preg_match( self::MYSQL_DATE, $node ) ) {
			$problems[] = sprintf(
				'"%s" is a MySQL datetime (%s) - format it with format_datetime() or wpss_rest_date()',
				'' !== $key ? $key : '(root)',
				$node
			);
		}
	}
}
