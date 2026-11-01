<?php
/**
 * Extension Request Service
 *
 * Reads the extension request rows and holds the status constants. Requests are
 * created and answered by ExtensionOrderService (the paid extension flow).
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Manages deadline extension requests.
 *
 * @since 1.0.0
 */
class ExtensionRequestService {

	/**
	 * Extension request statuses.
	 */
	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';

	/**
	 * Get extension request by ID.
	 *
	 * @param int $request_id Request ID.
	 * @return array|null
	 */
	public function get( int $request_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $request_id )
		);

		return $row ? $this->format_request( $row ) : null;
	}

	/**
	 * Get extension requests for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function get_by_order( int $order_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d ORDER BY created_at DESC",
				$order_id
			)
		);

		return array_map( [ $this, 'format_request' ], $rows );
	}

	/**
	 * Get pending extension request for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public function get_pending( int $order_id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_extension_requests';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d AND status = %s ORDER BY created_at DESC LIMIT 1",
				$order_id,
				self::STATUS_PENDING
			)
		);

		return $row ? $this->format_request( $row ) : null;
	}


	/**
	 * Format request row to array.
	 *
	 * @param object $row Database row.
	 * @return array
	 */
	private function format_request( object $row ): array {
		return [
			'id'               => (int) $row->id,
			'order_id'         => (int) $row->order_id,
			'requested_by'     => (int) $row->requested_by,
			'extra_days'       => (int) $row->extra_days,
			'reason'           => $row->reason,
			'status'           => $row->status,
			'responded_by'     => $row->responded_by ? (int) $row->responded_by : null,
			'response_message' => $row->response_message,
			'responded_at'     => $row->responded_at,
			'created_at'       => $row->created_at,
		];
	}

	/**
	 * Get status labels.
	 *
	 * @return array
	 */
	public static function get_statuses(): array {
		return [
			self::STATUS_PENDING  => __( 'Pending', 'wp-sell-services' ),
			self::STATUS_APPROVED => __( 'Approved', 'wp-sell-services' ),
			self::STATUS_REJECTED => __( 'Rejected', 'wp-sell-services' ),
		];
	}
}
