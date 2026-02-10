<?php
/**
 * Conversation Service
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

use WPSellServices\Models\Conversation;
use WPSellServices\Models\Message;

/**
 * Handles conversation business logic.
 *
 * @since 1.0.0
 */
class ConversationService {

	/**
	 * Get conversation by ID.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return Conversation|null
	 */
	public function get( int $conversation_id ): ?Conversation {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_conversations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$conversation_id
			)
		);

		return $row ? Conversation::from_db( $row ) : null;
	}

	/**
	 * Get conversation by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return Conversation|null
	 */
	public function get_by_order( int $order_id ): ?Conversation {
		return Conversation::get_by_order( $order_id );
	}

	/**
	 * Create conversation for order.
	 *
	 * @param int $order_id Order ID.
	 * @return Conversation|null
	 */
	public function create_for_order( int $order_id ): ?Conversation {
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return null;
		}

		// Check if conversation already exists.
		$existing = $this->get_by_order( $order_id );
		if ( $existing ) {
			return $existing;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_conversations';

		$participants = array( $order->customer_id, $order->vendor_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			array(
				'order_id'      => $order_id,
				/* translators: %s: order number */
				'subject'       => sprintf( __( 'Order %s', 'wp-sell-services' ), $order->order_number ),
				'participants'  => wp_json_encode( $participants ),
				'message_count' => 0,
				'unread_counts' => wp_json_encode( array() ),
				'is_closed'     => 0,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);

		$conversation_id = (int) $wpdb->insert_id;

		return $conversation_id ? $this->get( $conversation_id ) : null;
	}

	/**
	 * Get messages for conversation.
	 *
	 * @param int   $conversation_id Conversation ID.
	 * @param array $args            Query args.
	 * @return Message[]
	 */
	public function get_messages( int $conversation_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_messages';

		$defaults = array(
			'limit'  => 50,
			'offset' => 0,
			'order'  => 'ASC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE conversation_id = %d ORDER BY created_at {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$conversation_id,
				$args['limit'],
				$args['offset']
			)
		);

		return array_map( fn( $row ) => Message::from_db( $row ), $rows );
	}

	/**
	 * Send message.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param int    $sender_id       Sender user ID.
	 * @param string $content         Message content.
	 * @param array  $attachments     Optional attachments.
	 * @param string $type            Message type.
	 * @return Message|null
	 */
	public function send_message(
		int $conversation_id,
		int $sender_id,
		string $content,
		array $attachments = array(),
		string $type = Message::TYPE_TEXT
	): ?Message {
		$conversation = $this->get( $conversation_id );

		if ( ! $conversation || ! $conversation->can_reply( $sender_id ) ) {
			return null;
		}

		global $wpdb;
		$messages_table      = $wpdb->prefix . 'wpss_messages';
		$conversations_table = $wpdb->prefix . 'wpss_conversations';

		// Insert message.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$messages_table,
			array(
				'conversation_id' => $conversation_id,
				'sender_id'       => $sender_id,
				'type'            => $type,
				'content'         => $content,
				'attachments'     => wp_json_encode( $attachments ),
				'metadata'        => wp_json_encode( array() ),
				'read_by'         => wp_json_encode( array( $sender_id => true ) ),
				'is_edited'       => 0,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		$message_id = (int) $wpdb->insert_id;

		if ( ! $message_id ) {
			return null;
		}

		// Update conversation.
		$unread_counts = $conversation->unread_counts;
		foreach ( $conversation->participants as $participant_id ) {
			if ( $participant_id !== $sender_id ) {
				$unread_counts[ $participant_id ] = ( $unread_counts[ $participant_id ] ?? 0 ) + 1;
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$conversations_table,
			array(
				'message_count'   => $conversation->message_count + 1,
				'unread_counts'   => wp_json_encode( $unread_counts ),
				'last_message_at' => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $conversation_id )
		);

		// Get and return the message.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$messages_table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$message_id
			)
		);

		$message = $row ? Message::from_db( $row ) : null;

		if ( $message ) {
			/**
			 * Fires when a message is sent.
			 *
			 * @param Message      $message      The message.
			 * @param Conversation $conversation The conversation.
			 */
			do_action( 'wpss_message_sent', $message, $conversation );
		}

		return $message;
	}

	/**
	 * Add system message.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $content         Message content.
	 * @param array  $metadata        Optional metadata.
	 * @return Message|null
	 */
	public function add_system_message( int $conversation_id, string $content, array $metadata = array() ): ?Message {
		$conversation = $this->get( $conversation_id );

		if ( ! $conversation ) {
			return null;
		}

		global $wpdb;
		$messages_table      = $wpdb->prefix . 'wpss_messages';
		$conversations_table = $wpdb->prefix . 'wpss_conversations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$messages_table,
			array(
				'conversation_id' => $conversation_id,
				'sender_id'       => 0, // System.
				'type'            => Message::TYPE_SYSTEM,
				'content'         => $content,
				'attachments'     => wp_json_encode( array() ),
				'metadata'        => wp_json_encode( $metadata ),
				'read_by'         => wp_json_encode( array() ),
				'is_edited'       => 0,
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		$message_id = (int) $wpdb->insert_id;

		// Update conversation.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$conversations_table,
			array(
				'message_count'   => $conversation->message_count + 1,
				'last_message_at' => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( 'id' => $conversation_id )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$messages_table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$message_id
			)
		);

		return $row ? Message::from_db( $row ) : null;
	}

	/**
	 * Mark messages as read.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id         User ID.
	 * @return bool
	 */
	public function mark_as_read( int $conversation_id, int $user_id ): bool {
		$conversation = $this->get( $conversation_id );

		if ( ! $conversation || ! $conversation->is_participant( $user_id ) ) {
			return false;
		}

		global $wpdb;
		$messages_table      = $wpdb->prefix . 'wpss_messages';
		$conversations_table = $wpdb->prefix . 'wpss_conversations';

		// Get unread messages.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$unread_messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, read_by FROM {$messages_table}
				WHERE conversation_id = %d
				AND sender_id != %d
				AND (read_by NOT LIKE %s OR read_by IS NULL)",
				$conversation_id,
				$user_id,
				'%"' . $user_id . '"%'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$has_failure = false;

		foreach ( $unread_messages as $msg ) {
			$read_by             = $msg->read_by ? json_decode( $msg->read_by, true ) : array();
			$read_by[ $user_id ] = true;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$messages_table,
				array( 'read_by' => wp_json_encode( $read_by ) ),
				array( 'id' => $msg->id )
			);

			if ( false === $result ) {
				wpss_log( "Failed to mark message {$msg->id} as read for user {$user_id}: " . $wpdb->last_error, 'error' );
				$has_failure = true;
			}
		}

		// Reset unread count.
		$unread_counts             = $conversation->unread_counts;
		$unread_counts[ $user_id ] = 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$conversations_table,
			array( 'unread_counts' => wp_json_encode( $unread_counts ) ),
			array( 'id' => $conversation_id )
		);

		if ( false === $result ) {
			wpss_log( "Failed to reset unread count for conversation {$conversation_id}, user {$user_id}: " . $wpdb->last_error, 'error' );
			$has_failure = true;
		}

		return ! $has_failure;
	}

	/**
	 * Close conversation.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return bool
	 */
	public function close( int $conversation_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_conversations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->update(
			$table,
			array(
				'is_closed'  => 1,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $conversation_id )
		);
	}

	/**
	 * Get user's unread message count.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_total_unread_count( int $user_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_conversations';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$conversations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT unread_counts FROM {$table}
				WHERE participants LIKE %s AND is_closed = 0",
				'%' . $wpdb->esc_like( '"' . $user_id . '"' ) . '%'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total = 0;
		foreach ( $conversations as $conv ) {
			$unread_counts = json_decode( $conv->unread_counts, true );
			$unread_counts = $unread_counts ? $unread_counts : array();
			$total        += (int) ( $unread_counts[ $user_id ] ?? 0 );
		}

		return $total;
	}

	/**
	 * Get unread count (alias for get_total_unread_count).
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_unread_count( int $user_id ): int {
		return $this->get_total_unread_count( $user_id );
	}

	/**
	 * Get conversations for a user.
	 *
	 * @param int   $user_id User ID.
	 * @param array $args    Query args.
	 * @return Conversation[]
	 */
	public function get_by_user( int $user_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_conversations';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE participants LIKE %s
				ORDER BY COALESCE(last_message_at, created_at) DESC
				LIMIT %d OFFSET %d",
				'%' . $wpdb->esc_like( '"' . $user_id . '"' ) . '%',
				$args['limit'],
				$args['offset']
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array_map( fn( $row ) => Conversation::from_db( $row ), $rows );
	}

	/**
	 * Count conversations for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function count_by_user( int $user_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_conversations';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE participants LIKE %s",
				'%' . $wpdb->esc_like( '"' . $user_id . '"' ) . '%'
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $count;
	}

	/**
	 * Count messages in a conversation.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return int
	 */
	public function count_messages( int $conversation_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE conversation_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$conversation_id
			)
		);
	}

	/**
	 * Check if user can access a conversation.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id         User ID.
	 * @return bool
	 */
	public function user_can_access( int $conversation_id, int $user_id ): bool {
		$conversation = $this->get( $conversation_id );

		if ( ! $conversation ) {
			return false;
		}

		return $conversation->can_view( $user_id );
	}

	/**
	 * Get a single message by ID.
	 *
	 * @param int $message_id Message ID.
	 * @return Message|null
	 */
	public function get_message( int $message_id ): ?Message {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_messages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$message_id
			)
		);

		return $row ? Message::from_db( $row ) : null;
	}

	/**
	 * Get last message in a conversation.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return Message|null
	 */
	public function get_last_message( int $conversation_id ): ?Message {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_messages';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE conversation_id = %d
				ORDER BY created_at DESC
				LIMIT 1",
				$conversation_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $row ? Message::from_db( $row ) : null;
	}

	/**
	 * Get unread count for a specific conversation and user.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $user_id         User ID.
	 * @return int
	 */
	public function get_unread_count_for_conversation( int $conversation_id, int $user_id ): int {
		$conversation = $this->get( $conversation_id );

		if ( ! $conversation ) {
			return 0;
		}

		return $conversation->get_unread_count( $user_id );
	}
}
