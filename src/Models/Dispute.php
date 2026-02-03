<?php
/**
 * Dispute Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Models;

/**
 * Represents an order dispute.
 *
 * @since 1.0.0
 */
class Dispute {

	/**
	 * Dispute statuses.
	 */
	public const STATUS_OPEN      = 'open';
	public const STATUS_PENDING   = 'pending_review';
	public const STATUS_RESOLVED  = 'resolved';
	public const STATUS_ESCALATED = 'escalated';
	public const STATUS_CLOSED    = 'closed';

	/**
	 * Dispute reasons.
	 */
	public const REASON_NOT_DELIVERED     = 'not_delivered';
	public const REASON_NOT_AS_DESCRIBED  = 'not_as_described';
	public const REASON_POOR_QUALITY      = 'poor_quality';
	public const REASON_LATE_DELIVERY     = 'late_delivery';
	public const REASON_COMMUNICATION     = 'communication';
	public const REASON_OTHER             = 'other';

	/**
	 * Resolution types.
	 */
	public const RESOLUTION_REFUND           = 'refund';
	public const RESOLUTION_PARTIAL_REFUND   = 'partial_refund';
	public const RESOLUTION_REVISION         = 'revision';
	public const RESOLUTION_FAVOR_BUYER      = 'favor_buyer';
	public const RESOLUTION_FAVOR_SELLER     = 'favor_seller';
	public const RESOLUTION_MUTUAL           = 'mutual';

	/**
	 * Dispute ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Dispute number (human-readable).
	 *
	 * @var string
	 */
	public string $dispute_number;

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	public int $order_id;

	/**
	 * Initiator user ID.
	 *
	 * @var int
	 */
	public int $initiator_id;

	/**
	 * Respondent user ID.
	 *
	 * @var int
	 */
	public int $respondent_id;

	/**
	 * Assigned admin user ID.
	 *
	 * @var int|null
	 */
	public ?int $assigned_to;

	/**
	 * Dispute reason.
	 *
	 * @var string
	 */
	public string $reason;

	/**
	 * Dispute description.
	 *
	 * @var string
	 */
	public string $description;

	/**
	 * Evidence attachments.
	 *
	 * @var array<array{id: int, name: string, url: string, uploaded_by: int}>
	 */
	public array $evidence = [];

	/**
	 * Dispute status.
	 *
	 * @var string
	 */
	public string $status = self::STATUS_OPEN;

	/**
	 * Resolution type.
	 *
	 * @var string|null
	 */
	public ?string $resolution_type;

	/**
	 * Resolution notes.
	 *
	 * @var string
	 */
	public string $resolution_notes = '';

	/**
	 * Refund amount (if applicable).
	 *
	 * @var float|null
	 */
	public ?float $refund_amount;

	/**
	 * Resolved timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $resolved_at;

	/**
	 * Resolved by user ID.
	 *
	 * @var int|null
	 */
	public ?int $resolved_by;

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
		$dispute = new self();

		$dispute->id               = (int) $row->id;
		$dispute->dispute_number   = $row->dispute_number ?? '';
		$dispute->order_id         = (int) $row->order_id;
		$dispute->initiator_id     = (int) $row->initiated_by;
		$dispute->respondent_id    = isset( $row->respondent_id ) ? (int) $row->respondent_id : 0;
		$dispute->assigned_to      = ( $row->assigned_admin ?? null ) ? (int) $row->assigned_admin : null;
		$dispute->reason           = $row->reason;
		$dispute->description      = $row->description;
		$dispute->evidence         = $row->evidence ? json_decode( $row->evidence, true ) : [];
		$dispute->status           = $row->status ?? self::STATUS_OPEN;
		$dispute->resolution_type  = $row->resolution ?? null;
		$dispute->resolution_notes = $row->resolution_notes ?? '';
		$dispute->refund_amount    = ( $row->refund_amount ?? null ) ? (float) $row->refund_amount : null;
		$dispute->resolved_by      = $row->resolved_by ? (int) $row->resolved_by : null;

		// Timestamps.
		$dispute->resolved_at = $row->resolved_at ? new \DateTimeImmutable( $row->resolved_at ) : null;
		$dispute->created_at  = $row->created_at ? new \DateTimeImmutable( $row->created_at ) : null;
		$dispute->updated_at  = $row->updated_at ? new \DateTimeImmutable( $row->updated_at ) : null;

		return $dispute;
	}

	/**
	 * Get all dispute statuses.
	 *
	 * @return array<string, string>
	 */
	public static function get_statuses(): array {
		return [
			self::STATUS_OPEN      => __( 'Open', 'wp-sell-services' ),
			self::STATUS_PENDING   => __( 'Pending Review', 'wp-sell-services' ),
			self::STATUS_ESCALATED => __( 'Escalated', 'wp-sell-services' ),
			self::STATUS_RESOLVED  => __( 'Resolved', 'wp-sell-services' ),
			self::STATUS_CLOSED    => __( 'Closed', 'wp-sell-services' ),
		];
	}

	/**
	 * Get all dispute reasons.
	 *
	 * @return array<string, string>
	 */
	public static function get_reasons(): array {
		return [
			self::REASON_NOT_DELIVERED    => __( 'Order not delivered', 'wp-sell-services' ),
			self::REASON_NOT_AS_DESCRIBED => __( 'Not as described', 'wp-sell-services' ),
			self::REASON_POOR_QUALITY     => __( 'Poor quality work', 'wp-sell-services' ),
			self::REASON_LATE_DELIVERY    => __( 'Late delivery', 'wp-sell-services' ),
			self::REASON_COMMUNICATION    => __( 'Communication issues', 'wp-sell-services' ),
			self::REASON_OTHER            => __( 'Other', 'wp-sell-services' ),
		];
	}

	/**
	 * Get all resolution types.
	 *
	 * @return array<string, string>
	 */
	public static function get_resolution_types(): array {
		return [
			self::RESOLUTION_REFUND         => __( 'Full Refund', 'wp-sell-services' ),
			self::RESOLUTION_PARTIAL_REFUND => __( 'Partial Refund', 'wp-sell-services' ),
			self::RESOLUTION_REVISION       => __( 'Revision Required', 'wp-sell-services' ),
			self::RESOLUTION_FAVOR_BUYER    => __( 'Resolved in Buyer Favor', 'wp-sell-services' ),
			self::RESOLUTION_FAVOR_SELLER   => __( 'Resolved in Seller Favor', 'wp-sell-services' ),
			self::RESOLUTION_MUTUAL         => __( 'Mutual Agreement', 'wp-sell-services' ),
		];
	}

	/**
	 * Get status label.
	 *
	 * @return string
	 */
	public function get_status_label(): string {
		$statuses = self::get_statuses();
		return $statuses[ $this->status ] ?? $this->status;
	}

	/**
	 * Get reason label.
	 *
	 * @return string
	 */
	public function get_reason_label(): string {
		$reasons = self::get_reasons();
		return $reasons[ $this->reason ] ?? $this->reason;
	}

	/**
	 * Get resolution type label.
	 *
	 * @return string
	 */
	public function get_resolution_label(): string {
		if ( ! $this->resolution_type ) {
			return '';
		}

		$types = self::get_resolution_types();
		return $types[ $this->resolution_type ] ?? $this->resolution_type;
	}

	/**
	 * Get order.
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
	 * Get initiator user.
	 *
	 * @return \WP_User|null
	 */
	public function get_initiator(): ?\WP_User {
		return get_user_by( 'id', $this->initiator_id ) ?: null;
	}

	/**
	 * Get respondent user.
	 *
	 * @return \WP_User|null
	 */
	public function get_respondent(): ?\WP_User {
		return get_user_by( 'id', $this->respondent_id ) ?: null;
	}

	/**
	 * Get assigned admin user.
	 *
	 * @return \WP_User|null
	 */
	public function get_assigned_admin(): ?\WP_User {
		if ( ! $this->assigned_to ) {
			return null;
		}

		return get_user_by( 'id', $this->assigned_to ) ?: null;
	}

	/**
	 * Check if dispute is open.
	 *
	 * @return bool
	 */
	public function is_open(): bool {
		return in_array( $this->status, [ self::STATUS_OPEN, self::STATUS_PENDING, self::STATUS_ESCALATED ], true );
	}

	/**
	 * Check if dispute is resolved.
	 *
	 * @return bool
	 */
	public function is_resolved(): bool {
		return self::STATUS_RESOLVED === $this->status;
	}

	/**
	 * Check if user can view this dispute.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function can_view( int $user_id ): bool {
		// Participants can view.
		if ( $user_id === $this->initiator_id || $user_id === $this->respondent_id ) {
			return true;
		}

		// Assigned admin can view.
		if ( $user_id === $this->assigned_to ) {
			return true;
		}

		// Admins can view.
		return user_can( $user_id, 'manage_options' );
	}
}
