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
		return $this->table_name( 'conversations' );
	}

	/**
	 * Get messages table name.
	 *
	 * @return string Messages table name.
	 */
	protected function get_messages_table(): string {
		return $this->table_name( 'messages' );
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
	 * `participants` has ONE shape: a flat list of user ids, [5, 3], written by
	 * ConversationService::create_for_order() and create_direct(). The docblock
	 * here used to advertise a second key-value shape, {"5": true}, produced by
	 * this class's own create_conversation() — that method had no callers and has
	 * been removed, along with the add_message() that iterated it. JSON_CONTAINS
	 * below matches list VALUES, so it would never have matched a key-value row
	 * anyway: those conversations' unread counts were silently excluded from this
	 * total.
	 *
	 * Closed conversations are excluded deliberately — a finished thread must not
	 * keep nagging. The dashboard list therefore suppresses the unread badge on
	 * closed rows, so the badges on screen always sum to this number.
	 *
	 * @param int $user_id User ID.
	 * @return int Unread count.
	 */
	public function count_unread_for_user( int $user_id ): int {
		// Check if user is a participant using JSON_CONTAINS on the array.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT unread_counts FROM {$this->table}
				WHERE is_closed = 0
				AND participants IS NOT NULL
				AND JSON_CONTAINS(participants, %s)",
				wp_json_encode( $user_id )
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
	 * Mark a conversation as read by resetting the user's unread count.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id         User ID.
	 * @return void
	 */
	public function mark_read( int $conversation_id, int $user_id ): void {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT unread_counts FROM {$this->table} WHERE id = %d",
				$conversation_id
			)
		);

		if ( ! $row ) {
			return;
		}

		$unread_counts = json_decode( $row->unread_counts ?: '{}', true );
		if ( isset( $unread_counts[ $user_id ] ) && (int) $unread_counts[ $user_id ] > 0 ) {
			$unread_counts[ $user_id ] = 0;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->wpdb->update(
				$this->table,
				array( 'unread_counts' => wp_json_encode( $unread_counts ) ),
				array( 'id' => $conversation_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		// Also mark individual messages as read.
		$messages_table = $this->get_messages_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$unread_messages = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT id, read_by FROM {$messages_table}
				WHERE conversation_id = %d
				AND sender_id != %d
				AND (read_by IS NULL OR JSON_EXTRACT(read_by, %s) IS NULL)",
				$conversation_id,
				$user_id,
				'$."' . $user_id . '"'
			)
		);

		foreach ( $unread_messages as $message ) {
			$read_by                      = json_decode( $message->read_by ?: '{}', true );
			$read_by[ (string) $user_id ] = current_time( 'mysql' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$this->wpdb->update(
				$messages_table,
				array( 'read_by' => wp_json_encode( $read_by ) ),
				array( 'id' => $message->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
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
				continue;
			}
			++$count;
		}

		// Reset unread count for this user in conversation.
		$unread_counts = json_decode( $conversation->unread_counts ?: '{}', true );
		if ( isset( $unread_counts[ $user_id ] ) ) {
			$unread_counts[ $user_id ] = 0;
			$result                    = $this->wpdb->update(
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
	 * Count the conversations a user can see.
	 *
	 * Counts exactly what get_conversation_summary() returns — the same two arms,
	 * the same participation rules — so a paginator built on this can never
	 * disagree with the rows on screen.
	 *
	 * The messages dashboard previously ran the summary with a hardcoded
	 * `LIMIT 20`, no OFFSET, no total and no navigation, so a vendor with more
	 * than twenty threads could see twenty and had no route to the rest — while
	 * the unread banner counted ALL of them, which is one way a correct banner
	 * reads as "inflated" (Basecamp 10208075268).
	 *
	 * @since 1.6.0
	 *
	 * @param int $user_id User ID.
	 * @return int Conversation count.
	 */
	public function count_conversations_for_user( int $user_id ): int {
		$orders_table = $this->table_name( 'orders' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix.
				"SELECT COUNT(*) FROM (
					(SELECT c.id
					FROM {$this->table} c
					INNER JOIN {$orders_table} o ON c.order_id = o.id
					WHERE (o.customer_id = %d OR o.vendor_id = %d)
					GROUP BY c.id)
					UNION
					(SELECT c.id
					FROM {$this->table} c
					WHERE c.order_id = 0
					AND c.participants IS NOT NULL
					AND JSON_CONTAINS(c.participants, %s))
				) conversations",
				$user_id,
				$user_id,
				wp_json_encode( $user_id )
			)
		);
	}

	/**
	 * Get conversation summary for user dashboard.
	 *
	 * Returns both order-linked conversations (where user is customer/vendor)
	 * and direct conversations (order_id = 0, where user is a participant).
	 *
	 * @param int $user_id User ID.
	 * @param int $limit   Number of conversations.
	 * @param int $offset  Number of conversations to skip.
	 * @return array<object> Array of conversation summaries.
	 */
	public function get_conversation_summary( int $user_id, int $limit = 10, int $offset = 0 ): array {
		$orders_table   = $this->table_name( 'orders' );
		$messages_table = $this->get_messages_table();

		// Use UNION to combine order-linked and direct conversations.
		// Order-linked: join with orders table where user is customer or vendor.
		// Direct: order_id = 0 and user appears in participants JSON array.
		// Uses pre-aggregated max-message subquery to fetch last message in one pass.
		return $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT conversations.*,
					lm.content AS last_message,
					lm.sender_id AS last_message_sender_id,
					lm.msg_created_at AS last_message_created_at
				FROM (
					(SELECT
						c.id,
						c.order_id,
						o.order_number,
						o.service_id,
						o.platform,
						o.platform_order_id,
						c.subject,
						c.participants,
						c.last_message_at,
						c.message_count,
						c.unread_counts,
						c.is_closed,
						c.created_at,
						c.updated_at
					FROM {$this->table} c
					INNER JOIN {$orders_table} o ON c.order_id = o.id
					WHERE (o.customer_id = %d OR o.vendor_id = %d)
					GROUP BY c.id)
					UNION
					(SELECT
						c.id,
						c.order_id,
						NULL AS order_number,
						c.service_id,
						NULL AS platform,
						NULL AS platform_order_id,
						c.subject,
						c.participants,
						c.last_message_at,
						c.message_count,
						c.unread_counts,
						c.is_closed,
						c.created_at,
						c.updated_at
					FROM {$this->table} c
					WHERE c.order_id = 0
					AND c.participants IS NOT NULL
					AND JSON_CONTAINS(c.participants, %s))
				) conversations
				LEFT JOIN (
					SELECT m1.conversation_id,
						m1.content,
						m1.sender_id,
						m1.created_at AS msg_created_at
					FROM {$messages_table} m1
					INNER JOIN (
						SELECT conversation_id, MAX(id) AS max_id
						FROM {$messages_table}
						GROUP BY conversation_id
					) latest ON m1.conversation_id = latest.conversation_id AND m1.id = latest.max_id
				) lm ON lm.conversation_id = conversations.id
				ORDER BY conversations.last_message_at DESC
				LIMIT %d OFFSET %d",
				$user_id,
				$user_id,
				wp_json_encode( $user_id ),
				$limit,
				$offset
			)
		);
	}

	/*
	 * Two methods used to live here and both are gone.
	 *
	 * create_conversation() and add_message() were DEAD - zero callers anywhere
	 * in Free or Pro - and between them they held a second, incompatible idea of
	 * the `participants` column plus an iterator built on it:
	 *
	 * - create_conversation() wrote participants as a MAP keyed by user id,
	 *   array_fill_keys( [ "24", "13" ], true ) => {"24":true,"13":true}, while
	 *   ConversationService (the live creator) writes a LIST, [24,13].
	 * - add_message() then incremented unread with
	 *   `foreach ( array_keys( $participants ) ... )`, which on the LIST shape
	 *   yields the INDICES 0 and 1, not user ids. Wired up against real data it
	 *   would have credited unread messages to users 0 and 1 forever and never
	 *   to the actual recipient.
	 *
	 * Nothing was broken in production because nothing called them. They are
	 * removed rather than fixed because ConversationService::send_message() and
	 * create_for_order()/create_direct() already own these jobs - keeping a
	 * second implementation of a store shape is exactly how this plugin
	 * accumulated its recurring bugs (Basecamp 10208075268).
	 */
}
