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
