<?php
/**
 * Proposal Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a vendor proposal to a buyer request.
 *
 * @since 1.0.0
 */
class Proposal {

	/**
	 * Proposal ID.
	 *
	 * @var int
	 */
	public int $id = 0;

	/**
	 * Buyer request ID.
	 *
	 * @var int
	 */
	public int $request_id = 0;

	/**
	 * Vendor user ID.
	 *
	 * @var int
	 */
	public int $vendor_id = 0;

	/**
	 * Related service ID (optional).
	 *
	 * @var int|null
	 */
	public ?int $service_id = null;

	/**
	 * Cover letter.
	 *
	 * @var string
	 */
	public string $cover_letter = '';

	/**
	 * Proposed price.
	 *
	 * @var float
	 */
	public float $price = 0.0;

	/**
	 * Delivery days.
	 *
	 * @var int
	 */
	public int $delivery_days = 0;

	/**
	 * Proposal status.
	 *
	 * @var string
	 */
	public string $status = 'pending';

	/**
	 * Rejection reason (if rejected).
	 *
	 * @var string|null
	 */
	public ?string $rejection_reason = null;

	/**
	 * Withdrawal reason (if withdrawn).
	 *
	 * @var string|null
	 */
	public ?string $withdrawal_reason = null;

	/**
	 * Created order ID (if accepted).
	 *
	 * @var int|null
	 */
	public ?int $order_id = null;

	/**
	 * Created timestamp.
	 *
	 * @var string
	 */
	public string $created_at = '';

	/**
	 * Updated timestamp.
	 *
	 * @var string
	 */
	public string $updated_at = '';

	/**
	 * Valid statuses.
	 */
	public const STATUS_PENDING   = 'pending';
	public const STATUS_ACCEPTED  = 'accepted';
	public const STATUS_REJECTED  = 'rejected';
	public const STATUS_WITHDRAWN = 'withdrawn';

	/**
	 * Create from database row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		$proposal = new self();

		$proposal->id                = (int) $row->id;
		$proposal->request_id        = (int) $row->request_id;
		$proposal->vendor_id         = (int) $row->vendor_id;
		$proposal->service_id        = $row->service_id ? (int) $row->service_id : null;
		$proposal->cover_letter      = $row->cover_letter;
		$proposal->price             = (float) ( $row->proposed_price ?? 0 );
		$proposal->delivery_days     = (int) ( $row->proposed_days ?? 0 );
		$proposal->status            = $row->status;
		$proposal->rejection_reason  = $row->rejection_reason ?? null;
		$proposal->withdrawal_reason = $row->withdrawal_reason ?? null;
		$proposal->order_id          = isset( $row->order_id ) && $row->order_id ? (int) $row->order_id : null;
		$proposal->created_at        = $row->created_at;
		$proposal->updated_at        = $row->updated_at ?? $row->created_at;

		return $proposal;
	}

	/**
	 * Get the vendor user object.
	 *
	 * @return \WP_User|false
	 */
	public function get_vendor() {
		return get_userdata( $this->vendor_id );
	}

	/**
	 * Get the related service.
	 *
	 * @return \WP_Post|null
	 */
	public function get_service(): ?\WP_Post {
		if ( ! $this->service_id ) {
			return null;
		}

		$post = get_post( $this->service_id );
		return $post instanceof \WP_Post ? $post : null;
	}

	/**
	 * Get the buyer request.
	 *
	 * @return \WP_Post|null
	 */
	public function get_request(): ?\WP_Post {
		$post = get_post( $this->request_id );
		return $post instanceof \WP_Post ? $post : null;
	}

	/**
	 * Check if proposal is pending.
	 *
	 * @return bool
	 */
	public function is_pending(): bool {
		return self::STATUS_PENDING === $this->status;
	}

	/**
	 * Check if proposal was accepted.
	 *
	 * @return bool
	 */
	public function is_accepted(): bool {
		return self::STATUS_ACCEPTED === $this->status;
	}

	/**
	 * Check if proposal was rejected.
	 *
	 * @return bool
	 */
	public function is_rejected(): bool {
		return self::STATUS_REJECTED === $this->status;
	}

	/**
	 * Check if proposal was withdrawn.
	 *
	 * @return bool
	 */
	public function is_withdrawn(): bool {
		return self::STATUS_WITHDRAWN === $this->status;
	}

	/**
	 * Get formatted price.
	 *
	 * @return string
	 */
	public function get_formatted_price(): string {
		if ( function_exists( 'wpss_format_currency' ) ) {
			return wpss_format_currency( $this->price );
		}
		return '$' . number_format( $this->price, 2 );
	}

	/**
	 * Get delivery time display.
	 *
	 * @return string
	 */
	public function get_delivery_display(): string {
		return sprintf(
			/* translators: %d: number of days */
			_n( '%d day', '%d days', $this->delivery_days, 'wp-sell-services' ),
			$this->delivery_days
		);
	}

	/**
	 * Convert to array.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'id'                => $this->id,
			'request_id'        => $this->request_id,
			'vendor_id'         => $this->vendor_id,
			'service_id'        => $this->service_id,
			'cover_letter'      => $this->cover_letter,
			'price'             => $this->price,
			'delivery_days'     => $this->delivery_days,
			'status'            => $this->status,
			'rejection_reason'  => $this->rejection_reason,
			'withdrawal_reason' => $this->withdrawal_reason,
			'order_id'          => $this->order_id,
			'created_at'        => $this->created_at,
			'updated_at'        => $this->updated_at,
		];
	}

	/**
	 * Get available statuses.
	 *
	 * @return array<string, string>
	 */
	public static function get_statuses(): array {
		return [
			self::STATUS_PENDING   => __( 'Pending', 'wp-sell-services' ),
			self::STATUS_ACCEPTED  => __( 'Accepted', 'wp-sell-services' ),
			self::STATUS_REJECTED  => __( 'Rejected', 'wp-sell-services' ),
			self::STATUS_WITHDRAWN => __( 'Withdrawn', 'wp-sell-services' ),
		];
	}
}
