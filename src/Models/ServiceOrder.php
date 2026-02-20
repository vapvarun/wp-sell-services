<?php
/**
 * Service Order Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Models;

/**
 * Represents a service order.
 *
 * @since 1.0.0
 */
class ServiceOrder {

	/**
	 * Order statuses.
	 */
	public const STATUS_PENDING_PAYMENT      = 'pending_payment';
	public const STATUS_PENDING_REQUIREMENTS = 'pending_requirements';
	public const STATUS_IN_PROGRESS          = 'in_progress';
	public const STATUS_PENDING_APPROVAL     = 'pending_approval';
	public const STATUS_REVISION_REQUESTED   = 'revision_requested';
	public const STATUS_PENDING_REVIEW       = 'pending_review';
	public const STATUS_COMPLETED            = 'completed';
	public const STATUS_CANCELLED            = 'cancelled';
	public const STATUS_DISPUTED             = 'disputed';
	public const STATUS_ON_HOLD                = 'on_hold';
	public const STATUS_LATE                   = 'late';
	public const STATUS_CANCELLATION_REQUESTED = 'cancellation_requested';

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	public int $id;

	/**
	 * Order number (human-readable).
	 *
	 * @var string
	 */
	public string $order_number;

	/**
	 * Customer user ID.
	 *
	 * @var int
	 */
	public int $customer_id;

	/**
	 * Vendor user ID.
	 *
	 * @var int
	 */
	public int $vendor_id;

	/**
	 * Service ID.
	 *
	 * @var int
	 */
	public int $service_id;

	/**
	 * Selected package ID.
	 *
	 * @var int|null
	 */
	public ?int $package_id;

	/**
	 * Selected add-ons.
	 *
	 * @var array<int, array{id: int, quantity: int}>
	 */
	public array $addons = array();

	/**
	 * Platform identifier.
	 *
	 * @var string
	 */
	public string $platform = 'standalone';

	/**
	 * Platform order ID.
	 *
	 * @var int|null
	 */
	public ?int $platform_order_id;

	/**
	 * Platform order item ID.
	 *
	 * @var int|null
	 */
	public ?int $platform_item_id;

	/**
	 * Order subtotal.
	 *
	 * @var float
	 */
	public float $subtotal;

	/**
	 * Add-ons total.
	 *
	 * @var float
	 */
	public float $addons_total = 0.0;

	/**
	 * Order total.
	 *
	 * @var float
	 */
	public float $total;

	/**
	 * Currency code.
	 *
	 * @var string
	 */
	public string $currency = 'USD';

	/**
	 * Commission rate applied to this order.
	 *
	 * @var float|null
	 */
	public ?float $commission_rate = null;

	/**
	 * Platform fee amount.
	 *
	 * @var float|null
	 */
	public ?float $platform_fee = null;

	/**
	 * Vendor earnings after fees.
	 *
	 * @var float|null
	 */
	public ?float $vendor_earnings = null;

	/**
	 * Order status.
	 *
	 * @var string
	 */
	public string $status = self::STATUS_PENDING_PAYMENT;

	/**
	 * Delivery deadline.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $delivery_deadline;

	/**
	 * Original deadline (before extensions).
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $original_deadline;

	/**
	 * Payment method.
	 *
	 * @var string|null
	 */
	public ?string $payment_method;

	/**
	 * Payment status.
	 *
	 * @var string
	 */
	public string $payment_status = 'pending';

	/**
	 * Transaction ID.
	 *
	 * @var string|null
	 */
	public ?string $transaction_id;

	/**
	 * Paid timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $paid_at;

	/**
	 * Revisions included in package.
	 *
	 * @var int
	 */
	public int $revisions_included = 0;

	/**
	 * Revisions used.
	 *
	 * @var int
	 */
	public int $revisions_used = 0;

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
	 * Started timestamp (when work began).
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $started_at;

	/**
	 * Completed timestamp.
	 *
	 * @var \DateTimeImmutable|null
	 */
	public ?\DateTimeImmutable $completed_at;

	/**
	 * Find order by ID.
	 *
	 * @param int $order_id Order ID.
	 * @return self|null
	 */
	public static function find( int $order_id ): ?self {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $order_id )
		);

		return $row ? self::from_db( $row ) : null;
	}

	/**
	 * Update order fields.
	 *
	 * When updating status, validates the transition is allowed.
	 *
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public function update( array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// If updating status, validate the transition.
		if ( isset( $data['status'] ) && $data['status'] !== $this->status ) {
			$order_service = new \WPSellServices\Services\OrderService();
			if ( ! $order_service->can_transition( $this->status, $data['status'] ) ) {
				return false;
			}
		}

		// Add updated timestamp.
		$data['updated_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( $table, $data, array( 'id' => $this->id ) );

		if ( false === $result ) {
			return false;
		}

		// Fire status change hook if status changed.
		if ( isset( $data['status'] ) && $data['status'] !== $this->status ) {
			$old_status   = $this->status;
			$this->status = $data['status'];

			/**
			 * Fires when order status changes.
			 *
			 * @param int    $order_id   Order ID.
			 * @param string $new_status New status.
			 * @param string $old_status Old status.
			 */
			do_action( 'wpss_order_status_changed', $this->id, $data['status'], $old_status );
			do_action( "wpss_order_status_{$data['status']}", $this->id, $old_status );
		}

		// Update instance properties.
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				$this->$key = $value;
			}
		}

		return true;
	}

	/**
	 * Create from database row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_db( object $row ): self {
		$order = new self();

		$order->id                 = (int) $row->id;
		$order->order_number       = $row->order_number;
		$order->customer_id        = (int) $row->customer_id;
		$order->vendor_id          = (int) $row->vendor_id;
		$order->service_id         = (int) $row->service_id;
		$order->package_id         = $row->package_id ? (int) $row->package_id : null;
		$order->addons             = $row->addons ? json_decode( $row->addons, true ) : array();
		$order->platform           = $row->platform;
		$order->platform_order_id  = $row->platform_order_id ? (int) $row->platform_order_id : null;
		$order->platform_item_id   = $row->platform_item_id ? (int) $row->platform_item_id : null;
		$order->subtotal           = (float) $row->subtotal;
		$order->addons_total       = (float) $row->addons_total;
		$order->total              = (float) $row->total;
		$order->currency           = $row->currency;
		$order->commission_rate    = isset( $row->commission_rate ) ? (float) $row->commission_rate : null;
		$order->platform_fee       = isset( $row->platform_fee ) ? (float) $row->platform_fee : null;
		$order->vendor_earnings    = isset( $row->vendor_earnings ) ? (float) $row->vendor_earnings : null;
		$order->status             = $row->status;
		$order->payment_method     = $row->payment_method;
		$order->payment_status     = $row->payment_status;
		$order->transaction_id     = $row->transaction_id;
		$order->revisions_included = (int) $row->revisions_included;
		$order->revisions_used     = (int) $row->revisions_used;

		// Timestamps.
		$order->delivery_deadline = $row->delivery_deadline ? new \DateTimeImmutable( $row->delivery_deadline ) : null;
		$order->original_deadline = $row->original_deadline ? new \DateTimeImmutable( $row->original_deadline ) : null;
		$order->paid_at           = $row->paid_at ? new \DateTimeImmutable( $row->paid_at ) : null;
		$order->created_at        = $row->created_at ? new \DateTimeImmutable( $row->created_at ) : null;
		$order->updated_at        = $row->updated_at ? new \DateTimeImmutable( $row->updated_at ) : null;
		$order->started_at        = $row->started_at ? new \DateTimeImmutable( $row->started_at ) : null;
		$order->completed_at      = $row->completed_at ? new \DateTimeImmutable( $row->completed_at ) : null;

		return $order;
	}

	/**
	 * Get all available statuses.
	 *
	 * @return array<string, string>
	 */
	public static function get_statuses(): array {
		return array(
			self::STATUS_PENDING_PAYMENT      => __( 'Pending Payment', 'wp-sell-services' ),
			self::STATUS_PENDING_REQUIREMENTS => __( 'Waiting for Requirements', 'wp-sell-services' ),
			self::STATUS_IN_PROGRESS          => __( 'In Progress', 'wp-sell-services' ),
			self::STATUS_PENDING_APPROVAL     => __( 'Pending Approval', 'wp-sell-services' ),
			self::STATUS_REVISION_REQUESTED   => __( 'Revision Requested', 'wp-sell-services' ),
			self::STATUS_PENDING_REVIEW       => __( 'Pending Review', 'wp-sell-services' ),
			self::STATUS_COMPLETED            => __( 'Completed', 'wp-sell-services' ),
			self::STATUS_CANCELLED            => __( 'Cancelled', 'wp-sell-services' ),
			self::STATUS_DISPUTED             => __( 'Disputed', 'wp-sell-services' ),
			self::STATUS_ON_HOLD                => __( 'On Hold', 'wp-sell-services' ),
			self::STATUS_LATE                   => __( 'Late', 'wp-sell-services' ),
			self::STATUS_CANCELLATION_REQUESTED => __( 'Cancellation Requested', 'wp-sell-services' ),
		);
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
	 * Check if order is past deadline.
	 *
	 * @return bool
	 */
	public function is_late(): bool {
		if ( ! $this->delivery_deadline ) {
			return false;
		}

		if ( in_array( $this->status, array( self::STATUS_COMPLETED, self::STATUS_CANCELLED ), true ) ) {
			return false;
		}

		return $this->delivery_deadline < new \DateTimeImmutable();
	}

	/**
	 * Get remaining revisions.
	 *
	 * @return int -1 for unlimited.
	 */
	public function get_remaining_revisions(): int {
		if ( -1 === $this->revisions_included ) {
			return -1; // Unlimited.
		}

		return max( 0, $this->revisions_included - $this->revisions_used );
	}

	/**
	 * Check if more revisions are available.
	 *
	 * @return bool
	 */
	public function can_request_revision(): bool {
		$remaining = $this->get_remaining_revisions();
		return -1 === $remaining || $remaining > 0;
	}

	/**
	 * Get time remaining until deadline.
	 *
	 * @return \DateInterval|null
	 */
	public function get_time_remaining(): ?\DateInterval {
		if ( ! $this->delivery_deadline ) {
			return null;
		}

		$now = new \DateTimeImmutable();
		if ( $this->delivery_deadline < $now ) {
			return null;
		}

		return $now->diff( $this->delivery_deadline );
	}

	/**
	 * Get service.
	 *
	 * @return Service|null
	 */
	public function get_service(): ?Service {
		$post = get_post( $this->service_id );
		return $post ? Service::from_post( $post ) : null;
	}

	/**
	 * Get customer user.
	 *
	 * @return \WP_User|null
	 */
	public function get_customer(): ?\WP_User {
		return get_user_by( 'id', $this->customer_id ) ?: null;
	}

	/**
	 * Get vendor profile.
	 *
	 * @return VendorProfile|null
	 */
	public function get_vendor(): ?VendorProfile {
		return VendorProfile::get_by_user_id( $this->vendor_id );
	}

	/**
	 * Get formatted total.
	 *
	 * @return string
	 */
	public function get_formatted_total(): string {
		return wpss_format_price( $this->total, $this->currency );
	}
}
