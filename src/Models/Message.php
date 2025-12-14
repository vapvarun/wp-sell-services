<?php
/**
 * Message Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Models;

/**
 * Represents a conversation message.
 *
 * @since 1.0.0
 */
class Message {

	/**
	 * Message types.
	 */
	public const TYPE_TEXT           = 'text';
	public const TYPE_ATTACHMENT     = 'attachment';
	public const TYPE_DELIVERY       = 'delivery';
	public const TYPE_REVISION       = 'revision';
	public const TYPE_STATUS_CHANGE  = 'status_change';
	public const TYPE_SYSTEM         = 'system';

	/**
	 * Message ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Conversation ID.
	 *
	 * @var int
	 */
	public int $conversation_id;

	/**
	 * Sender user ID.
	 *
	 * @var int
	 */
	public int $sender_id;

	/**
	 * Message type.
	 *
	 * @var string
	 */
	public string $type = self::TYPE_TEXT;

	/**
	 * Message content.
	 *
	 * @var string
	 */
	public string $content;

	/**
	 * Attachments.
	 *
	 * @var array<array{id: int, name: string, url: string, type: string, size: int}>
	 */
	public array $attachments = [];

	/**
	 * Additional metadata (delivery details, status info, etc.).
	 *
	 * @var array
	 */
	public array $metadata = [];

	/**
	 * Read status per user.
	 *
	 * @var array<int, bool>
	 */
	public array $read_by = [];

	/**
	 * Whether message is edited.
	 *
	 * @var bool
	 */
	public bool $is_edited = false;

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
		$message = new self();

		$message->id              = (int) $row->id;
		$message->conversation_id = (int) $row->conversation_id;
		$message->sender_id       = (int) $row->sender_id;
		$message->type            = $row->type ?? self::TYPE_TEXT;
		$message->content         = $row->content;
		$message->attachments     = $row->attachments ? json_decode( $row->attachments, true ) : [];
		$message->metadata        = $row->metadata ? json_decode( $row->metadata, true ) : [];
		$message->read_by         = $row->read_by ? json_decode( $row->read_by, true ) : [];
		$message->is_edited       = (bool) $row->is_edited;
		$message->created_at      = $row->created_at ? new \DateTimeImmutable( $row->created_at ) : null;
		$message->updated_at      = $row->updated_at ? new \DateTimeImmutable( $row->updated_at ) : null;

		return $message;
	}

	/**
	 * Get all message types.
	 *
	 * @return array<string, string>
	 */
	public static function get_types(): array {
		return [
			self::TYPE_TEXT          => __( 'Text', 'wp-sell-services' ),
			self::TYPE_ATTACHMENT    => __( 'Attachment', 'wp-sell-services' ),
			self::TYPE_DELIVERY      => __( 'Delivery', 'wp-sell-services' ),
			self::TYPE_REVISION      => __( 'Revision Request', 'wp-sell-services' ),
			self::TYPE_STATUS_CHANGE => __( 'Status Change', 'wp-sell-services' ),
			self::TYPE_SYSTEM        => __( 'System', 'wp-sell-services' ),
		];
	}

	/**
	 * Get sender user.
	 *
	 * @return \WP_User|null
	 */
	public function get_sender(): ?\WP_User {
		if ( 0 === $this->sender_id ) {
			return null; // System message.
		}

		return get_user_by( 'id', $this->sender_id ) ?: null;
	}

	/**
	 * Get sender name.
	 *
	 * @return string
	 */
	public function get_sender_name(): string {
		if ( 0 === $this->sender_id ) {
			return __( 'System', 'wp-sell-services' );
		}

		$user = $this->get_sender();
		return $user ? $user->display_name : __( 'Unknown', 'wp-sell-services' );
	}

	/**
	 * Check if message is read by user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_read_by( int $user_id ): bool {
		return ! empty( $this->read_by[ $user_id ] );
	}

	/**
	 * Check if this is a system message.
	 *
	 * @return bool
	 */
	public function is_system_message(): bool {
		return self::TYPE_SYSTEM === $this->type || 0 === $this->sender_id;
	}

	/**
	 * Check if this is a delivery message.
	 *
	 * @return bool
	 */
	public function is_delivery(): bool {
		return self::TYPE_DELIVERY === $this->type;
	}

	/**
	 * Check if this is a revision request.
	 *
	 * @return bool
	 */
	public function is_revision_request(): bool {
		return self::TYPE_REVISION === $this->type;
	}

	/**
	 * Get formatted timestamp.
	 *
	 * @return string
	 */
	public function get_formatted_time(): string {
		if ( ! $this->created_at ) {
			return '';
		}

		return wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$this->created_at->getTimestamp()
		);
	}

	/**
	 * Get relative time (e.g., "2 hours ago").
	 *
	 * @return string
	 */
	public function get_relative_time(): string {
		if ( ! $this->created_at ) {
			return '';
		}

		return human_time_diff( $this->created_at->getTimestamp(), time() ) . ' ' . __( 'ago', 'wp-sell-services' );
	}
}
