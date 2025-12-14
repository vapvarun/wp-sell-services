<?php
/**
 * Conversation Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Models;

/**
 * Represents an order conversation thread.
 *
 * @since 1.0.0
 */
class Conversation {

	/**
	 * Conversation ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	public int $order_id;

	/**
	 * Conversation subject.
	 *
	 * @var string
	 */
	public string $subject = '';

	/**
	 * Participant user IDs.
	 *
	 * @var int[]
	 */
	public array $participants = [];

	/**
	 * Total message count.
	 *
	 * @var int
	 */
	public int $message_count = 0;

	/**
	 * Unread count per user.
	 *
	 * @var array<int, int>
	 */
	public array $unread_counts = [];

	/**
	 * Whether conversation is closed.
	 *
	 * @var bool
	 */
	public bool $is_closed = false;

	/**
	 * Last message timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $last_message_at;

	/**
	 * Created timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $created_at;

	/**
	 * Updated timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $updated_at;

	/**
	 * Create from database row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_db( object $row ): self {
		$conversation = new self();

		$conversation->id            = (int) $row->id;
		$conversation->order_id      = (int) $row->order_id;
		$conversation->subject       = $row->subject ?? '';
		$conversation->participants  = $row->participants ? json_decode( $row->participants, true ) : [];
		$conversation->message_count = (int) $row->message_count;
		$conversation->unread_counts = $row->unread_counts ? json_decode( $row->unread_counts, true ) : [];
		$conversation->is_closed     = (bool) $row->is_closed;

		// Timestamps.
		$conversation->last_message_at = $row->last_message_at ? new \DateTimeImmutable( $row->last_message_at ) : null;
		$conversation->created_at      = $row->created_at ? new \DateTimeImmutable( $row->created_at ) : null;
		$conversation->updated_at      = $row->updated_at ? new \DateTimeImmutable( $row->updated_at ) : null;

		return $conversation;
	}

	/**
	 * Get conversation by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return self|null
	 */
	public static function get_by_order( int $order_id ): ?self {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_conversations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE order_id = %d",
				$order_id
			)
		);

		return $row ? self::from_db( $row ) : null;
	}

	/**
	 * Get order associated with this conversation.
	 *
	 * @return ServiceOrder|null
	 */
	public function get_order(): ?ServiceOrder {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$this->order_id
			)
		);

		return $row ? ServiceOrder::from_db( $row ) : null;
	}

	/**
	 * Get unread count for a specific user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_unread_count( int $user_id ): int {
		return $this->unread_counts[ $user_id ] ?? 0;
	}

	/**
	 * Check if user is a participant.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_participant( int $user_id ): bool {
		return in_array( $user_id, $this->participants, true );
	}

	/**
	 * Check if user can view this conversation.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function can_view( int $user_id ): bool {
		// Participants can always view.
		if ( $this->is_participant( $user_id ) ) {
			return true;
		}

		// Admins can view.
		return user_can( $user_id, 'manage_options' );
	}

	/**
	 * Check if user can reply to this conversation.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function can_reply( int $user_id ): bool {
		if ( $this->is_closed ) {
			return false;
		}

		return $this->is_participant( $user_id );
	}
}
