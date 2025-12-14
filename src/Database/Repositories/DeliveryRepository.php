<?php
/**
 * Delivery Repository
 *
 * Database operations for order deliveries.
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

namespace WPSellServices\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * DeliveryRepository class.
 *
 * @since 1.0.0
 */
class DeliveryRepository extends AbstractRepository {

	/**
	 * Get the table name.
	 *
	 * @return string Table name.
	 */
	protected function get_table_name(): string {
		return $this->schema->get_table_name( 'deliveries' );
	}

	/**
	 * Get deliveries for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array<object> Array of deliveries.
	 */
	public function get_by_order( int $order_id ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE order_id = %d
				ORDER BY version DESC",
				$order_id
			)
		);
	}

	/**
	 * Get the latest delivery for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null Latest delivery or null.
	 */
	public function get_latest( int $order_id ): ?object {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE order_id = %d
				ORDER BY version DESC
				LIMIT 1",
				$order_id
			)
		);

		return $result ?: null;
	}

	/**
	 * Get the next version number for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return int Next version number.
	 */
	public function get_next_version( int $order_id ): int {
		$max = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT MAX(version) FROM {$this->table}
				WHERE order_id = %d",
				$order_id
			)
		);

		return ( (int) $max ) + 1;
	}

	/**
	 * Create a new delivery.
	 *
	 * @param int                  $order_id  Order ID.
	 * @param int                  $vendor_id Vendor user ID.
	 * @param array<string, mixed> $data      Delivery data.
	 * @return int|false Delivery ID or false.
	 */
	public function create_delivery( int $order_id, int $vendor_id, array $data ): int|false {
		$attachments = $data['attachments'] ?? [];
		if ( is_array( $attachments ) ) {
			$attachments = wp_json_encode( $attachments );
		}

		return $this->insert(
			[
				'order_id'    => $order_id,
				'vendor_id'   => $vendor_id,
				'message'     => $data['message'] ?? '',
				'attachments' => $attachments,
				'version'     => $this->get_next_version( $order_id ),
				'status'      => 'pending',
			]
		);
	}

	/**
	 * Update delivery status.
	 *
	 * @param int    $delivery_id      Delivery ID.
	 * @param string $status           New status.
	 * @param string $response_message Response message.
	 * @return bool True on success.
	 */
	public function update_status( int $delivery_id, string $status, string $response_message = '' ): bool {
		return $this->update(
			$delivery_id,
			[
				'status'           => $status,
				'response_message' => $response_message,
				'responded_at'     => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Accept a delivery.
	 *
	 * @param int    $delivery_id Delivery ID.
	 * @param string $message     Acceptance message.
	 * @return bool True on success.
	 */
	public function accept( int $delivery_id, string $message = '' ): bool {
		return $this->update_status( $delivery_id, 'accepted', $message );
	}

	/**
	 * Request revision for a delivery.
	 *
	 * @param int    $delivery_id Delivery ID.
	 * @param string $message     Revision request message.
	 * @return bool True on success.
	 */
	public function request_revision( int $delivery_id, string $message ): bool {
		return $this->update_status( $delivery_id, 'revision_requested', $message );
	}

	/**
	 * Count deliveries for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return int Delivery count.
	 */
	public function count_by_order( int $order_id ): int {
		return $this->count( [ 'order_id' => $order_id ] );
	}

	/**
	 * Get pending deliveries for a customer.
	 *
	 * @param int $customer_id Customer user ID.
	 * @return array<object> Array of pending deliveries with order info.
	 */
	public function get_pending_for_customer( int $customer_id ): array {
		$orders_table = $this->schema->get_table_name( 'orders' );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT d.*, o.order_number, o.service_id
				FROM {$this->table} d
				INNER JOIN {$orders_table} o ON d.order_id = o.id
				WHERE o.customer_id = %d AND d.status = 'pending'
				ORDER BY d.created_at DESC",
				$customer_id
			)
		);
	}

	/**
	 * Delete all deliveries for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return int Number of deleted deliveries.
	 */
	public function delete_by_order( int $order_id ): int {
		$result = $this->wpdb->delete(
			$this->table,
			[ 'order_id' => $order_id ],
			[ '%d' ]
		);

		return $result ?: 0;
	}
}
