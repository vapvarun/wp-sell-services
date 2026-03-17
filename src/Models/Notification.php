<?php
/**
 * Notification Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a user notification.
 *
 * @since 1.0.0
 */
class Notification {

	/**
	 * Notification ID.
	 *
	 * @var int
	 */
	public int $id = 0;

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public int $user_id = 0;

	/**
	 * Notification type.
	 *
	 * @var string
	 */
	public string $type = '';

	/**
	 * Notification title.
	 *
	 * @var string
	 */
	public string $title = '';

	/**
	 * Notification message.
	 *
	 * @var string
	 */
	public string $message = '';

	/**
	 * Action URL.
	 *
	 * @var string|null
	 */
	public ?string $action_url = null;

	/**
	 * Additional data.
	 *
	 * @var array
	 */
	public array $data = [];

	/**
	 * Read status.
	 *
	 * @var bool
	 */
	public bool $is_read = false;

	/**
	 * Read timestamp.
	 *
	 * @var string|null
	 */
	public ?string $read_at = null;

	/**
	 * Created timestamp.
	 *
	 * @var string
	 */
	public string $created_at = '';

	/**
	 * Notification types.
	 */
	public const TYPE_ORDER_NEW              = 'order_new';
	public const TYPE_ORDER_ACCEPTED         = 'order_accepted';
	public const TYPE_ORDER_IN_PROGRESS      = 'order_in_progress';
	public const TYPE_ORDER_DELIVERED        = 'order_delivered';
	public const TYPE_ORDER_COMPLETED        = 'order_completed';
	public const TYPE_ORDER_CANCELLED        = 'order_cancelled';
	public const TYPE_ORDER_REVISION         = 'order_revision';
	public const TYPE_ORDER_LATE             = 'order_late';
	public const TYPE_MESSAGE_NEW            = 'message_new';
	public const TYPE_REVIEW_RECEIVED        = 'review_received';
	public const TYPE_DISPUTE_OPENED         = 'dispute_opened';
	public const TYPE_DISPUTE_RESPONSE       = 'dispute_response';
	public const TYPE_DISPUTE_RESOLVED       = 'dispute_resolved';
	public const TYPE_PROPOSAL_RECEIVED      = 'proposal_received';
	public const TYPE_PROPOSAL_ACCEPTED      = 'proposal_accepted';
	public const TYPE_PROPOSAL_REJECTED      = 'proposal_rejected';
	public const TYPE_EARNINGS_CLEARED       = 'earnings_cleared';
	public const TYPE_WITHDRAWAL_APPROVED    = 'withdrawal_approved';
	public const TYPE_WITHDRAWAL_REJECTED    = 'withdrawal_rejected';
	public const TYPE_SERVICE_APPROVED       = 'service_approved';
	public const TYPE_SERVICE_REJECTED       = 'service_rejected';
	public const TYPE_DEADLINE_REMINDER      = 'deadline_reminder';
	public const TYPE_EXTENSION_REQUESTED    = 'extension_requested';
	public const TYPE_EXTENSION_APPROVED     = 'extension_approved';
	public const TYPE_EXTENSION_REJECTED     = 'extension_rejected';

	/**
	 * Create from database row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		$notification = new self();

		$notification->id         = (int) $row->id;
		$notification->user_id    = (int) $row->user_id;
		$notification->type       = $row->type;
		$notification->title      = $row->title ?? '';
		$notification->message    = $row->message;
		$notification->action_url = $row->action_url ?? null;
		$notification->data       = isset( $row->data ) ? ( is_string( $row->data ) ? json_decode( $row->data, true ) : $row->data ) : [];
		$notification->is_read    = (bool) ( $row->is_read ?? false );
		$notification->read_at    = $row->read_at ?? null;
		$notification->created_at = $row->created_at;

		return $notification;
	}

	/**
	 * Get the user.
	 *
	 * @return \WP_User|false
	 */
	public function get_user() {
		return get_userdata( $this->user_id );
	}

	/**
	 * Mark as read.
	 *
	 * @return bool
	 */
	public function mark_as_read(): bool {
		if ( $this->is_read ) {
			return true;
		}

		global $wpdb;

		$result = $wpdb->update(
			$wpdb->prefix . 'wpss_notifications',
			[
				'is_read' => 1,
				'read_at' => current_time( 'mysql' ),
			],
			[ 'id' => $this->id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		if ( $result !== false ) {
			$this->is_read = true;
			$this->read_at = current_time( 'mysql' );
			return true;
		}

		return false;
	}

	/**
	 * Get notification icon class.
	 *
	 * @return string
	 */
	public function get_icon_class(): string {
		$icons = [
			self::TYPE_ORDER_NEW          => 'dashicons-cart',
			self::TYPE_ORDER_ACCEPTED     => 'dashicons-yes-alt',
			self::TYPE_ORDER_IN_PROGRESS  => 'dashicons-update',
			self::TYPE_ORDER_DELIVERED    => 'dashicons-yes',
			self::TYPE_ORDER_COMPLETED    => 'dashicons-awards',
			self::TYPE_ORDER_CANCELLED    => 'dashicons-dismiss',
			self::TYPE_ORDER_REVISION     => 'dashicons-backup',
			self::TYPE_ORDER_LATE         => 'dashicons-warning',
			self::TYPE_MESSAGE_NEW        => 'dashicons-email',
			self::TYPE_REVIEW_RECEIVED    => 'dashicons-star-filled',
			self::TYPE_DISPUTE_OPENED     => 'dashicons-flag',
			self::TYPE_DISPUTE_RESPONSE   => 'dashicons-format-chat',
			self::TYPE_DISPUTE_RESOLVED   => 'dashicons-saved',
			self::TYPE_PROPOSAL_RECEIVED  => 'dashicons-businessman',
			self::TYPE_PROPOSAL_ACCEPTED  => 'dashicons-thumbs-up',
			self::TYPE_PROPOSAL_REJECTED  => 'dashicons-thumbs-down',
			self::TYPE_EARNINGS_CLEARED   => 'dashicons-money-alt',
			self::TYPE_WITHDRAWAL_APPROVED => 'dashicons-bank',
			self::TYPE_WITHDRAWAL_REJECTED => 'dashicons-no-alt',
			self::TYPE_SERVICE_APPROVED   => 'dashicons-yes-alt',
			self::TYPE_SERVICE_REJECTED   => 'dashicons-dismiss',
			self::TYPE_DEADLINE_REMINDER  => 'dashicons-calendar-alt',
			self::TYPE_EXTENSION_REQUESTED => 'dashicons-clock',
			self::TYPE_EXTENSION_APPROVED => 'dashicons-calendar',
			self::TYPE_EXTENSION_REJECTED => 'dashicons-no',
		];

		return $icons[ $this->type ] ?? 'dashicons-bell';
	}

	/**
	 * Get time ago string.
	 *
	 * @return string
	 */
	public function get_time_ago(): string {
		return human_time_diff( strtotime( $this->created_at ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wp-sell-services' );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'id'         => $this->id,
			'user_id'    => $this->user_id,
			'type'       => $this->type,
			'title'      => $this->title,
			'message'    => $this->message,
			'action_url' => $this->action_url,
			'data'       => $this->data,
			'is_read'    => $this->is_read,
			'read_at'    => $this->read_at,
			'created_at' => $this->created_at,
			'time_ago'   => $this->get_time_ago(),
			'icon'       => $this->get_icon_class(),
		];
	}

	/**
	 * Get notification types with labels.
	 *
	 * @return array<string, string>
	 */
	public static function get_types(): array {
		return [
			self::TYPE_ORDER_NEW           => __( 'New Order', 'wp-sell-services' ),
			self::TYPE_ORDER_ACCEPTED      => __( 'Order Accepted', 'wp-sell-services' ),
			self::TYPE_ORDER_IN_PROGRESS   => __( 'Order In Progress', 'wp-sell-services' ),
			self::TYPE_ORDER_DELIVERED     => __( 'Order Delivered', 'wp-sell-services' ),
			self::TYPE_ORDER_COMPLETED     => __( 'Order Completed', 'wp-sell-services' ),
			self::TYPE_ORDER_CANCELLED     => __( 'Order Cancelled', 'wp-sell-services' ),
			self::TYPE_ORDER_REVISION      => __( 'Revision Requested', 'wp-sell-services' ),
			self::TYPE_ORDER_LATE          => __( 'Order Late', 'wp-sell-services' ),
			self::TYPE_MESSAGE_NEW         => __( 'New Message', 'wp-sell-services' ),
			self::TYPE_REVIEW_RECEIVED     => __( 'Review Received', 'wp-sell-services' ),
			self::TYPE_DISPUTE_OPENED      => __( 'Dispute Opened', 'wp-sell-services' ),
			self::TYPE_DISPUTE_RESPONSE    => __( 'Dispute Response', 'wp-sell-services' ),
			self::TYPE_DISPUTE_RESOLVED    => __( 'Dispute Resolved', 'wp-sell-services' ),
			self::TYPE_PROPOSAL_RECEIVED   => __( 'Proposal Received', 'wp-sell-services' ),
			self::TYPE_PROPOSAL_ACCEPTED   => __( 'Proposal Accepted', 'wp-sell-services' ),
			self::TYPE_PROPOSAL_REJECTED   => __( 'Proposal Rejected', 'wp-sell-services' ),
			self::TYPE_EARNINGS_CLEARED    => __( 'Earnings Cleared', 'wp-sell-services' ),
			self::TYPE_WITHDRAWAL_APPROVED => __( 'Withdrawal Approved', 'wp-sell-services' ),
			self::TYPE_WITHDRAWAL_REJECTED => __( 'Withdrawal Rejected', 'wp-sell-services' ),
			self::TYPE_SERVICE_APPROVED    => __( 'Service Approved', 'wp-sell-services' ),
			self::TYPE_SERVICE_REJECTED    => __( 'Service Rejected', 'wp-sell-services' ),
			self::TYPE_DEADLINE_REMINDER   => __( 'Deadline Reminder', 'wp-sell-services' ),
			self::TYPE_EXTENSION_REQUESTED => __( 'Extension Requested', 'wp-sell-services' ),
			self::TYPE_EXTENSION_APPROVED  => __( 'Extension Approved', 'wp-sell-services' ),
			self::TYPE_EXTENSION_REJECTED  => __( 'Extension Rejected', 'wp-sell-services' ),
		];
	}
}
