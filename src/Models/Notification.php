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
	 * Create from database row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_db( object $row ): self {
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
				'read_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $this->id ],
			[ '%d', '%s' ],
			[ '%d' ]
		);

		if ( $result !== false ) {
			$this->is_read = true;
			$this->read_at = current_time( 'mysql', true );
			return true;
		}

		return false;
	}

	/**
	 * Get notification Lucide icon name.
	 *
	 * Returns a Lucide icon name from the wpss_notification_types() registry.
	 * Consumers render it via <i data-lucide="…" class="wpss-icon">.
	 *
	 * @return string
	 */
	public function get_icon_class(): string {
		return wpss_notification_type_icon( $this->type );
	}

	/**
	 * Get time ago string.
	 *
	 * @return string
	 */
	public function get_time_ago(): string {
		return human_time_diff( strtotime( $this->created_at . ' UTC' ), time() ) . ' ' . __( 'ago', 'wp-sell-services' );
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
			'action_url' => wpss_get_notification_url( is_array( $this->data ) ? $this->data : array(), $this->action_url ),
			'data'       => $this->data,
			'is_read'    => $this->is_read,
			'read_at'    => $this->read_at,
			'created_at' => $this->created_at,
			'time_ago'   => $this->get_time_ago(),
			'icon'       => $this->get_icon_class(),
		];
	}
}
