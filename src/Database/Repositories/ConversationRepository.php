<?php
/**
 * Conversation Repository
 *
 * Database operations for order messages/conversations.
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

namespace WPSellServices\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * ConversationRepository class.
 *
 * @since 1.0.0
 */
class ConversationRepository extends AbstractRepository {

	/**
	 * Allowed columns for ordering and filtering.
	 *
	 * @var array<string>
	 */
	protected array $allowed_columns = array(
		'id',
		'order_id',
		'sender_id',
		'recipient_id',
		'message_type',
		'is_read',
		'created_at',
		'read_at',
	);

	/**
	 * Get the table name.
	 *
	 * @return string Table name.
	 */
	protected function get_table_name(): string {
		return $this->schema->get_table_name( 'conversations' );
	}

	/**
	 * Get messages for an order.
	 *
	 * @param int                  $order_id Order ID.
	 * @param array<string, mixed> $args     Query arguments.
	 * @return array<object> Array of messages.
	 */
	public function get_by_order( int $order_id, array $args = array() ): array {
		$defaults = array(
			'orderby' => 'created_at',
			'order'   => 'ASC',
			'limit'   => 100,
			'offset'  => 0,
			'since'   => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY and ORDER against whitelist.
		$orderby = $this->validate_orderby( $args['orderby'] );
		$order   = $this->validate_order( $args['order'] );

		$sql    = "SELECT * FROM {$this->table} WHERE order_id = %d";
		$params = array( $order_id );

		if ( ! empty( $args['since'] ) ) {
			$sql     .= ' AND created_at > %s';
			$params[] = $args['since'];
		}

		$sql .= " ORDER BY {$orderby} {$order}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $args['limit'] > 0 ) {
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = $args['limit'];
			$params[] = $args['offset'];
		}

		return $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Get new messages since a timestamp.
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $since     Timestamp to check from.
	 * @param int    $exclude_sender Sender ID to exclude (to not return own messages).
	 * @return array<object> Array of new messages.
	 */
	public function get_new_messages( int $order_id, string $since, int $exclude_sender = 0 ): array {
		$sql    = "SELECT * FROM {$this->table} WHERE order_id = %d AND created_at > %s";
		$params = array( $order_id, $since );

		if ( $exclude_sender > 0 ) {
			$sql     .= ' AND sender_id != %d';
			$params[] = $exclude_sender;
		}

		$sql .= ' ORDER BY created_at ASC';

		return $this->wpdb->get_results(
			$this->wpdb->prepare( $sql, ...$params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Get unread messages for a user.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Maximum messages to return.
	 * @return array<object> Array of unread messages.
	 */
	public function get_unread_for_user( int $user_id, int $limit = 50 ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE recipient_id = %d AND is_read = 0
				ORDER BY created_at DESC
				LIMIT %d",
				$user_id,
				$limit
			)
		);
	}

	/**
	 * Count unread messages for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int Unread count.
	 */
	public function count_unread_for_user( int $user_id ): int {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				WHERE recipient_id = %d AND is_read = 0",
				$user_id
			)
		);
	}

	/**
	 * Count unread messages for an order.
	 *
	 * @param int $order_id Order ID.
	 * @param int $user_id  User ID to check for.
	 * @return int Unread count.
	 */
	public function count_unread_for_order( int $order_id, int $user_id ): int {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				WHERE order_id = %d AND recipient_id = %d AND is_read = 0",
				$order_id,
				$user_id
			)
		);
	}

	/**
	 * Mark messages as read.
	 *
	 * @param int $order_id     Order ID.
	 * @param int $recipient_id Recipient user ID.
	 * @return int Number of messages marked as read.
	 */
	public function mark_as_read( int $order_id, int $recipient_id ): int {
		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table}
				SET is_read = 1, read_at = %s
				WHERE order_id = %d AND recipient_id = %d AND is_read = 0",
				current_time( 'mysql' ),
				$order_id,
				$recipient_id
			)
		);

		return $result ?: 0;
	}

	/**
	 * Mark a single message as read.
	 *
	 * @param int $message_id Message ID.
	 * @return bool True on success.
	 */
	public function mark_single_as_read( int $message_id ): bool {
		return $this->update(
			$message_id,
			array(
				'is_read' => 1,
				'read_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get messages by type.
	 *
	 * @param int    $order_id     Order ID.
	 * @param string $message_type Message type.
	 * @return array<object> Array of messages.
	 */
	public function get_by_type( int $order_id, string $message_type ): array {
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE order_id = %d AND message_type = %s
				ORDER BY created_at ASC",
				$order_id,
				$message_type
			)
		);
	}

	/**
	 * Get the last message for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null Last message or null.
	 */
	public function get_last_message( int $order_id ): ?object {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE order_id = %d
				ORDER BY created_at DESC
				LIMIT 1",
				$order_id
			)
		);

		return $result ?: null;
	}

	/**
	 * Delete all messages for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return int Number of deleted messages.
	 */
	public function delete_by_order( int $order_id ): int {
		$result = $this->wpdb->delete(
			$this->table,
			array( 'order_id' => $order_id ),
			array( '%d' )
		);

		return $result ?: 0;
	}

	/**
	 * Get conversation summary for user dashboard.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Number of conversations.
	 * @return array<object> Array of conversation summaries.
	 */
	public function get_conversation_summary( int $user_id, int $limit = 10 ): array {
		$orders_table = $this->schema->get_table_name( 'orders' );

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT
					c.order_id,
					o.order_number,
					o.service_id,
					MAX(c.created_at) as last_message_at,
					SUM(CASE WHEN c.recipient_id = %d AND c.is_read = 0 THEN 1 ELSE 0 END) as unread_count,
					(SELECT message FROM {$this->table} WHERE order_id = c.order_id ORDER BY created_at DESC LIMIT 1) as last_message
				FROM {$this->table} c
				INNER JOIN {$orders_table} o ON c.order_id = o.id
				WHERE c.sender_id = %d OR c.recipient_id = %d
				GROUP BY c.order_id, o.order_number, o.service_id
				ORDER BY last_message_at DESC
				LIMIT %d",
				$user_id,
				$user_id,
				$user_id,
				$limit
			)
		);
	}
}
