<?php
/**
 * REST Controller Base
 *
 * @package WPSellServices\API
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\API;

defined( 'ABSPATH' ) || exit;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Base REST API controller.
 *
 * @since 1.0.0
 */
abstract class RestController extends WP_REST_Controller {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wpss/v1';

	/**
	 * Check if user can access endpoints.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_permissions( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'wp-sell-services' ),
				[ 'status' => 401 ]
			);
		}

		// Rate limit write operations.
		if ( in_array( $request->get_method(), array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			$action = $this->get_rate_limit_action( $request );
			if ( \WPSellServices\Core\RateLimiter::check_and_track( $action, get_current_user_id() ) ) {
				$reset_time = \WPSellServices\Core\RateLimiter::get_reset_time( $action, get_current_user_id() );
				return new WP_Error(
					'rate_limited',
					sprintf(
						/* translators: %d: seconds until rate limit resets */
						__( 'Too many requests. Please try again in %d seconds.', 'wp-sell-services' ),
						$reset_time
					),
					array( 'status' => 429 )
				);
			}
		}

		return true;
	}

	/**
	 * Check if user can manage (admin).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_admin_permissions( WP_REST_Request $request ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this endpoint.', 'wp-sell-services' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * Check if user owns the resource.
	 *
	 * @param int    $resource_id   Resource ID.
	 * @param string $resource_type Resource type (service, order).
	 * @return bool
	 */
	protected function user_owns_resource( int $resource_id, string $resource_type ): bool {
		$user_id = get_current_user_id();

		switch ( $resource_type ) {
			case 'service':
				$author_id = (int) get_post_field( 'post_author', $resource_id );
				return $author_id === $user_id;

			case 'order':
				global $wpdb;
				$table = $wpdb->prefix . 'wpss_orders';
				$order = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT customer_id, vendor_id FROM {$table} WHERE id = %d",
						$resource_id
					)
				);
				if ( $order ) {
					return (int) $order->customer_id === $user_id || (int) $order->vendor_id === $user_id;
				}
				return false;

			default:
				return false;
		}
	}

	/**
	 * Format response with pagination.
	 *
	 * @param array $items Items.
	 * @param int   $total Total count.
	 * @param int   $page  Current page.
	 * @param int   $per_page Per page count.
	 * @return WP_REST_Response
	 */
	protected function paginated_response( array $items, int $total, int $page, int $per_page ): WP_REST_Response {
		$response = new WP_REST_Response( $items );

		$max_pages = ceil( $total / $per_page );

		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $max_pages );

		return $response;
	}

	/**
	 * Get pagination args from request.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array{page: int, per_page: int, offset: int}
	 */
	protected function get_pagination_args( WP_REST_Request $request ): array {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 10 ) );

		return [
			'page'     => $page,
			'per_page' => $per_page,
			'offset'   => ( $page - 1 ) * $per_page,
		];
	}

	/**
	 * Validate and sanitize ID parameter.
	 *
	 * @param mixed $value Parameter value.
	 * @return int|WP_Error
	 */
	public function validate_id( $value ) {
		$id = (int) $value;

		if ( $id <= 0 ) {
			return new WP_Error(
				'invalid_id',
				__( 'Invalid ID.', 'wp-sell-services' ),
				[ 'status' => 400 ]
			);
		}

		return $id;
	}

	/**
	 * Format a datetime value to ISO 8601.
	 *
	 * Accepts DateTimeInterface objects, MySQL datetime strings, or null.
	 * Returns ISO 8601 string (e.g. '2024-01-15T10:30:00+00:00') or null.
	 *
	 * @since 1.1.0
	 *
	 * @param mixed $datetime DateTime value.
	 * @return string|null ISO 8601 formatted string or null.
	 */
	protected function format_datetime( $datetime ): ?string {
		if ( ! $datetime ) {
			return null;
		}

		if ( $datetime instanceof \DateTimeInterface ) {
			return $datetime->format( 'c' );
		}

		if ( is_string( $datetime ) && '' !== $datetime ) {
			try {
				return ( new \DateTimeImmutable( $datetime, new \DateTimeZone( 'UTC' ) ) )->format( 'c' );
			} catch ( \Exception $e ) {
				return null;
			}
		}

		return null;
	}

	/**
	 * Get the rate limit action key for a request.
	 *
	 * Controllers can override this to provide specific action keys.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return string Rate limit action key.
	 */
	protected function get_rate_limit_action( WP_REST_Request $request ): string {
		return 'default';
	}

	/**
	 * Build a standardised error response for sub-order flows.
	 *
	 * Keeps the plugin's convention consistent across Milestones, Extensions,
	 * Tipping, and Proposals: a machine-readable code, a user-facing message,
	 * and an explicit HTTP status on `data.status` so clients don't have to
	 * read headers and payload separately.
	 *
	 * @since 1.1.0
	 *
	 * @param string $code    Error code (e.g. `wpss_milestone_locked`).
	 * @param string $message Human-readable message, already translated.
	 * @param int    $status  HTTP status (400, 401, 403, 404, 409, 500).
	 * @param array  $extra   Optional extra fields merged into the `data` bag.
	 * @return WP_Error
	 */
	protected function error( string $code, string $message, int $status, array $extra = array() ): WP_Error {
		$data = array_merge( array( 'status' => $status ), $extra );
		return new WP_Error( $code, $message, $data );
	}

	/**
	 * Require the current request to come from an authenticated user.
	 *
	 * Shared gate used by write endpoints that don't need an ownership check
	 * before validating the body — returns the canonical 401 error.
	 *
	 * @since 1.1.0
	 *
	 * @return true|WP_Error
	 */
	protected function require_login() {
		if ( ! is_user_logged_in() ) {
			return $this->error(
				'wpss_not_logged_in',
				__( 'You must be logged in to access this endpoint.', 'wp-sell-services' ),
				401
			);
		}

		return true;
	}

	/**
	 * Load a parent order and confirm the current user is either its buyer
	 * or its vendor. Returns either the order row or a WP_Error with the
	 * correct HTTP status (401 / 403 / 404).
	 *
	 * @since 1.1.0
	 *
	 * @param int $order_id Order ID to load.
	 * @return object|WP_Error
	 */
	protected function get_order_for_participant( int $order_id ) {
		$login = $this->require_login();
		if ( is_wp_error( $login ) ) {
			return $login;
		}

		$order = function_exists( 'wpss_get_order' ) ? wpss_get_order( $order_id ) : null;
		if ( ! $order ) {
			return $this->error(
				'wpss_order_not_found',
				__( 'Order not found.', 'wp-sell-services' ),
				404
			);
		}

		$user_id = get_current_user_id();
		if ( (int) $order->customer_id !== $user_id && (int) $order->vendor_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return $this->error(
				'wpss_forbidden',
				__( 'You do not have access to this order.', 'wp-sell-services' ),
				403
			);
		}

		return $order;
	}

	/**
	 * Get common schema properties.
	 *
	 * @return array
	 */
	protected function get_common_schema_properties(): array {
		return [
			'id'         => [
				'description' => __( 'Unique identifier.', 'wp-sell-services' ),
				'type'        => 'integer',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'created_at' => [
				'description' => __( 'Creation date.', 'wp-sell-services' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
			'updated_at' => [
				'description' => __( 'Last update date.', 'wp-sell-services' ),
				'type'        => 'string',
				'format'      => 'date-time',
				'context'     => [ 'view', 'edit' ],
				'readonly'    => true,
			],
		];
	}
}
