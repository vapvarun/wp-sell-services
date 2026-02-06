<?php
/**
 * Conversation Repository
 *
 * Database operations for order conversations.
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

namespace WPSellServices\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * ConversationRepository class.
 *
 * Handles conversations and messages for orders.
 * Messages are stored in wpss_messages table, conversations in wpss_conversations.
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
		'subject',
		'message_count',
		'is_closed',
		'last_message_at',
		'created_at',
		'updated_at',
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
	 * Get messages table name.
	 *
	 * @return string Messages table name.
	 */
	protected function get_messages_table(): string {
		return $this->schema->get_table_name( 'messages' );
	}

	/**
	 * Find conversation by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null Conversation or null.
	 */
	public function find_by_order( int $order_id ): ?object {
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE order_id = %d LIMIT 1",
				$order_id
			)
		);

		return $result ?: null;
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

		// Validate ORDER BY and ORDER.
		$allowed_message_columns = array( 'id', 'created_at', 'sender_id', 'type' );
		$orderby                 = in_array( $args['orderby'], $allowed_message_columns, true ) ? $args['orderby'] : 'created_at';
		$order                   = $this->validate_order( $args['order'] );

		$messages_table = $this->get_messages_table();

		// First get conversation for this order.
		$conversation = $this->find_by_order( $order_id );
		if ( ! $conversation ) {
			return array();
		}

		$sql    = "SELECT * FROM {$messages_table} WHERE conversation_id = %d";
		$params = array( $conversation->id );

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
	 * @param int    $order_id       Order ID.
	 * @param string $since          Timestamp to check from.
	 * @param int    $exclude_sender Sender ID to exclude (to not return own messages).
	 * @return array<object> Array of new messages.
	 */
	public function get_new_messages( int $order_id, string $since, int $exclude_sender = 0 ): array {
		$conversation = $this->find_by_order( $order_id );
		if ( ! $conversation ) {
			return array();
		}

		$messages_table = $this->get_messages_table();

		$sql    = "SELECT * FROM {$messages_table} WHERE conversation_id = %d AND created_at > %s";
		$params = array( $conversation->id, $since );

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
	 * Get unread message count for a user from unread_counts JSON field.
	 *
	 * @param int $user_id User ID.
	 * @return int Unread count.
	 */
	public function count_unread_for_user( int $user_id ): int {
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT unread_counts FROM {$this->table}
				WHERE is_closed = 0
				AND JSON_EXTRACT(participants, %s) IS NOT NULL",
				'$."' . $user_id . '"'
			)
		);

		$total = 0;
		foreach ( $results as $row ) {
			$unread = json_decode( $row->unread_counts, true );
			if ( isset( $unread[ $user_id ] ) ) {
				$total += (int) $unread[ $user_id ];
			}
		}

		return $total;
	}

	/**
	 * Get unread count for a specific order conversation.
	 *
	 * @param int $order_id Order ID.
	 * @param int $user_id  User ID to check for.
	 * @return int Unread count.
	 */
	public function count_unread_for_order( int $order_id, int $user_id ): int {
		$conversation = $this->find_by_order( $order_id );
		if ( ! $conversation || empty( $conversation->unread_counts ) ) {
			return 0;
		}

		$unread = json_decode( $conversation->unread_counts, true );
		return isset( $unread[ $user_id ] ) ? (int) $unread[ $user_id ] : 0;
	}

	/**
	 * Mark messages as read for a user in a conversation.
	 *
	 * Updates the read_by JSON field in messages and unread_counts in conversation.
	 *
	 * @param int $order_id Order ID.
	 * @param int $user_id  User ID marking as read.
	 * @return int Number of messages marked as read.
	 */
	public function mark_as_read( int $order_id, int $user_id ): int {
		$conversation = $this->find_by_order( $order_id );
		if ( ! $conversation ) {
			return 0;
		}

		$messages_table = $this->get_messages_table();

		// Get messages not yet read by this user.
		$unread_messages = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, read_by FROM {$messages_table}
				WHERE conversation_id = %d
				AND sender_id != %d
				AND (read_by IS NULL OR JSON_EXTRACT(read_by, %s) IS NULL)",
				$conversation->id,
				$user_id,
				'$."' . $user_id . '"'
			)
		);

		$count = 0;
		foreach ( $unread_messages as $message ) {
			$read_by                      = json_decode( $message->read_by ?: '{}', true );
			$read_by[ (string) $user_id ] = current_time( 'mysql' );

			$result = $this->wpdb->update(
				$messages_table,
				array( 'read_by' => wp_json_encode( $read_by ) ),
				array( 'id' => $message->id ),
				array( '%s' ),
				array( '%d' )
			);
			if ( false === $result ) {
				wpss_log( "Failed to mark message {$message->id} as read: " . $this->wpdb->last_error, 'error' );
			}
			++$count;
		}

		// Reset unread count for this user in conversation.
		$unread_counts = json_decode( $conversation->unread_counts ?: '{}', true );
		if ( isset( $unread_counts[ $user_id ] ) ) {
			$unread_counts[ $user_id ] = 0;
			$result = $this->wpdb->update(
				$this->table,
				array( 'unread_counts' => wp_json_encode( $unread_counts ) ),
				array( 'id' => $conversation->id ),
				array( '%s' ),
				array( '%d' )
			);
			if ( false === $result ) {
				wpss_log( "Failed to reset unread count for conversation {$conversation->id}: " . $this->wpdb->last_error, 'error' );
			}
		}

		return $count;
	}

	/**
	 * Mark a single message as read by a user.
	 *
	 * @param int $message_id Message ID.
	 * @param int $user_id    User ID marking as read.
	 * @return bool True on success.
	 */
	public function mark_single_as_read( int $message_id, int $user_id ): bool {
		$messages_table = $this->get_messages_table();

		$message = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT read_by FROM {$messages_table} WHERE id = %d",
				$message_id
			)
		);

		if ( ! $message ) {
			return false;
		}

		$read_by                      = json_decode( $message->read_by ?: '{}', true );
		$read_by[ (string) $user_id ] = current_time( 'mysql' );

		return (bool) $this->wpdb->update(
			$messages_table,
			array( 'read_by' => wp_json_encode( $read_by ) ),
			array( 'id' => $message_id ),
			array( '%s' ),
			array( '%d' )
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
		$conversation = $this->find_by_order( $order_id );
		if ( ! $conversation ) {
			return array();
		}

		$messages_table = $this->get_messages_table();

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$messages_table}
				WHERE conversation_id = %d AND type = %s
				ORDER BY created_at ASC",
				$conversation->id,
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
		$conversation = $this->find_by_order( $order_id );
		if ( ! $conversation ) {
			return null;
		}

		$messages_table = $this->get_messages_table();

		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$messages_table}
				WHERE conversation_id = %d
				ORDER BY created_at DESC
				LIMIT 1",
				$conversation->id
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
		$conversation = $this->find_by_order( $order_id );
		if ( ! $conversation ) {
			return 0;
		}

		$messages_table = $this->get_messages_table();

		// Delete messages first.
		$deleted = $this->wpdb->delete(
			$messages_table,
			array( 'conversation_id' => $conversation->id ),
			array( '%d' )
		);

		// Delete conversation.
		$this->wpdb->delete(
			$this->table,
			array( 'id' => $conversation->id ),
			array( '%d' )
		);

		return $deleted ?: 0;
	}

	/**
	 * Get conversation summary for user dashboard.
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Number of conversations.
	 * @return array<object> Array of conversation summaries.
	 */
	public function get_conversation_summary( int $user_id, int $limit = 10 ): array {
		$orders_table   = $this->schema->get_table_name( 'orders' );
		$messages_table = $this->get_messages_table();

		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT
					c.id as conversation_id,
					c.order_id,
					o.order_number,
					o.service_id,
					o.platform,
					o.platform_order_id,
					c.last_message_at,
					c.message_count,
					c.unread_counts,
					(SELECT content FROM {$messages_table} WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message
				FROM {$this->table} c
				INNER JOIN {$orders_table} o ON c.order_id = o.id
				WHERE o.customer_id = %d OR o.vendor_id = %d
				ORDER BY c.last_message_at DESC
				LIMIT %d",
				$user_id,
				$user_id,
				$limit
			)
		);
	}

	/**
	 * Create a new conversation for an order.
	 *
	 * @param int    $order_id     Order ID.
	 * @param array  $participants Array of participant user IDs.
	 * @param string $subject     Optional subject.
	 * @return int|false Conversation ID or false on failure.
	 */
	public function create_conversation( int $order_id, array $participants, string $subject = '' ): int|false {
		// Check if conversation already exists.
		$existing = $this->find_by_order( $order_id );
		if ( $existing ) {
			return (int) $existing->id;
		}

		// Build participants JSON.
		$participants_json = wp_json_encode( array_fill_keys( array_map( 'strval', $participants ), true ) );

		// Build initial unread counts (all zero).
		$unread_json = wp_json_encode( array_fill_keys( array_map( 'strval', $participants ), 0 ) );

		$result = $this->wpdb->insert(
			$this->table,
			array(
				'order_id'      => $order_id,
				'subject'       => $subject,
				'participants'  => $participants_json,
				'message_count' => 0,
				'unread_counts' => $unread_json,
				'is_closed'     => 0,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		return $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Add a message to a conversation.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param int    $sender_id       Sender user ID.
	 * @param string $content         Message content.
	 * @param string $type            Message type (text, system, delivery, etc.).
	 * @param array  $attachments     Optional attachments.
	 * @return int|false Message ID or false on failure.
	 */
	public function add_message( int $conversation_id, int $sender_id, string $content, string $type = 'text', array $attachments = array() ): int|false {
		$messages_table = $this->get_messages_table();

		$result = $this->wpdb->insert(
			$messages_table,
			array(
				'conversation_id' => $conversation_id,
				'sender_id'       => $sender_id,
				'type'            => $type,
				'content'         => $content,
				'attachments'     => ! empty( $attachments ) ? wp_json_encode( $attachments ) : null,
				'read_by'         => wp_json_encode( array( (string) $sender_id => current_time( 'mysql' ) ) ),
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$message_id = (int) $this->wpdb->insert_id;

		// Update conversation stats.
		$conversation = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT participants, unread_counts FROM {$this->table} WHERE id = %d",
				$conversation_id
			)
		);

		if ( $conversation ) {
			// Increment unread counts for other participants.
			$participants = json_decode( $conversation->participants ?: '{}', true );
			$unread       = json_decode( $conversation->unread_counts ?: '{}', true );

			foreach ( array_keys( $participants ) as $participant_id ) {
				if ( (int) $participant_id !== $sender_id ) {
					$unread[ $participant_id ] = ( $unread[ $participant_id ] ?? 0 ) + 1;
				}
			}

			$stats_result = $this->wpdb->update(
				$this->table,
				array(
					'message_count'   => $this->wpdb->get_var(
						$this->wpdb->prepare(
							"SELECT COUNT(*) FROM {$messages_table} WHERE conversation_id = %d",
							$conversation_id
						)
					),
					'unread_counts'   => wp_json_encode( $unread ),
					'last_message_at' => current_time( 'mysql' ),
					'updated_at'      => current_time( 'mysql' ),
				),
				array( 'id' => $conversation_id ),
				array( '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
			if ( false === $stats_result ) {
				wpss_log( "Failed to update conversation {$conversation_id} stats: " . $this->wpdb->last_error, 'error' );
			}
		}

		return $message_id;
	}
}
