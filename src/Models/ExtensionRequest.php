<?php
/**
 * Extension Request Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Represents an order deadline extension request.
 *
 * @since 1.0.0
 */
class ExtensionRequest {

	/**
	 * Request ID.
	 *
	 * @var int
	 */
	public int $id = 0;

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	public int $order_id = 0;

	/**
	 * User who requested extension.
	 *
	 * @var int
	 */
	public int $requested_by = 0;

	/**
	 * User who responded.
	 *
	 * @var int|null
	 */
	public ?int $responded_by = null;

	/**
	 * Extra days requested.
	 *
	 * @var int
	 */
	public int $extra_days = 0;

	/**
	 * Reason for extension.
	 *
	 * @var string
	 */
	public string $reason = '';

	/**
	 * Request status.
	 *
	 * @var string
	 */
	public string $status = 'pending';

	/**
	 * Response message.
	 *
	 * @var string|null
	 */
	public ?string $response_message = null;

	/**
	 * Original due date.
	 *
	 * @var string|null
	 */
	public ?string $original_due_date = null;

	/**
	 * New due date (if approved).
	 *
	 * @var string|null
	 */
	public ?string $new_due_date = null;

	/**
	 * Created timestamp.
	 *
	 * @var string
	 */
	public string $created_at = '';

	/**
	 * Responded timestamp.
	 *
	 * @var string|null
	 */
	public ?string $responded_at = null;

	/**
	 * Valid statuses.
	 */
	public const STATUS_PENDING  = 'pending';
	public const STATUS_APPROVED = 'approved';
	public const STATUS_REJECTED = 'rejected';
	public const STATUS_EXPIRED  = 'expired';

	/**
	 * Create from database row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		$request = new self();

		$request->id                = (int) $row->id;
		$request->order_id          = (int) $row->order_id;
		$request->requested_by      = (int) $row->requested_by;
		$request->responded_by      = isset( $row->responded_by ) && $row->responded_by ? (int) $row->responded_by : null;
		$request->extra_days        = (int) $row->extra_days;
		$request->reason            = $row->reason;
		$request->status            = $row->status;
		$request->response_message  = $row->response_message ?? null;
		$request->original_due_date = $row->original_due_date ?? null;
		$request->new_due_date      = $row->new_due_date ?? null;
		$request->created_at        = $row->created_at;
		$request->responded_at      = $row->responded_at ?? null;

		return $request;
	}

	/**
	 * Get the requester user object.
	 *
	 * @return \WP_User|false
	 */
	public function get_requester() {
		return get_userdata( $this->requested_by );
	}

	/**
	 * Get the responder user object.
	 *
	 * @return \WP_User|false|null
	 */
	public function get_responder() {
		if ( ! $this->responded_by ) {
			return null;
		}
		return get_userdata( $this->responded_by );
	}

	/**
	 * Check if request is pending.
	 *
	 * @return bool
	 */
	public function is_pending(): bool {
		return self::STATUS_PENDING === $this->status;
	}

	/**
	 * Check if request was approved.
	 *
	 * @return bool
	 */
	public function is_approved(): bool {
		return self::STATUS_APPROVED === $this->status;
	}

	/**
	 * Check if request was rejected.
	 *
	 * @return bool
	 */
	public function is_rejected(): bool {
		return self::STATUS_REJECTED === $this->status;
	}

	/**
	 * Check if request has expired.
	 *
	 * @return bool
	 */
	public function is_expired(): bool {
		if ( self::STATUS_EXPIRED === $this->status ) {
			return true;
		}

		// Auto-expire pending requests after 48 hours.
		if ( self::STATUS_PENDING === $this->status ) {
			$expires_at = strtotime( $this->created_at ) + ( 48 * HOUR_IN_SECONDS );
			return time() > $expires_at;
		}

		return false;
	}

	/**
	 * Get extension duration display.
	 *
	 * @return string
	 */
	public function get_duration_display(): string {
		return sprintf(
			/* translators: %d: number of days */
			_n( '%d day', '%d days', $this->extra_days, 'wp-sell-services' ),
			$this->extra_days
		);
	}

	/**
	 * Get time remaining to respond.
	 *
	 * @return int Seconds remaining, or 0 if expired.
	 */
	public function get_response_time_remaining(): int {
		if ( ! $this->is_pending() ) {
			return 0;
		}

		$expires_at = strtotime( $this->created_at ) + ( 48 * HOUR_IN_SECONDS );
		$remaining  = $expires_at - time();

		return max( 0, $remaining );
	}

	/**
	 * Get time remaining display.
	 *
	 * @return string
	 */
	public function get_time_remaining_display(): string {
		$remaining = $this->get_response_time_remaining();

		if ( $remaining <= 0 ) {
			return __( 'Expired', 'wp-sell-services' );
		}

		return human_time_diff( time(), time() + $remaining );
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'id'                => $this->id,
			'order_id'          => $this->order_id,
			'requested_by'      => $this->requested_by,
			'responded_by'      => $this->responded_by,
			'extra_days'        => $this->extra_days,
			'reason'            => $this->reason,
			'status'            => $this->status,
			'response_message'  => $this->response_message,
			'original_due_date' => $this->original_due_date,
			'new_due_date'      => $this->new_due_date,
			'created_at'        => $this->created_at,
			'responded_at'      => $this->responded_at,
			'is_expired'        => $this->is_expired(),
			'time_remaining'    => $this->get_time_remaining_display(),
		];
	}

	/**
	 * Get available statuses.
	 *
	 * @return array<string, string>
	 */
	public static function get_statuses(): array {
		return [
			self::STATUS_PENDING  => __( 'Pending', 'wp-sell-services' ),
			self::STATUS_APPROVED => __( 'Approved', 'wp-sell-services' ),
			self::STATUS_REJECTED => __( 'Rejected', 'wp-sell-services' ),
			self::STATUS_EXPIRED  => __( 'Expired', 'wp-sell-services' ),
		];
	}
}
