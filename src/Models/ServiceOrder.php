<?php
/**
 * Service Order Model
 *
 * @package WPSellServices\Models
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Models;

defined( 'ABSPATH' ) || exit;

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
	public const STATUS_COMPLETED            = 'completed';
	public const STATUS_CANCELLED            = 'cancelled';
	public const STATUS_DISPUTED             = 'disputed';
	public const STATUS_ON_HOLD                = 'on_hold';
	public const STATUS_LATE                   = 'late';
	public const STATUS_CANCELLATION_REQUESTED = 'cancellation_requested';

	// REST API workflow statuses (stored in DB during order lifecycle).
	public const STATUS_PENDING                = 'pending';
	public const STATUS_ACCEPTED               = 'accepted';
	public const STATUS_REJECTED               = 'rejected';
	public const STATUS_REQUIREMENTS_SUBMITTED = 'requirements_submitted';
	public const STATUS_DELIVERED               = 'delivered';
	public const STATUS_REFUNDED                = 'refunded';
	public const STATUS_PARTIALLY_REFUNDED      = 'partially_refunded';

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
	 * Vendor notes (cancellation reasons, admin notes, etc.).
	 *
	 * @var string|null
	 */
	public ?string $vendor_notes = null;

	/**
	 * Additional metadata (JSON-decoded).
	 *
	 * @var array<string, mixed>
	 */
	public array $meta = [];

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
	 * Query orders with filters.
	 *
	 * @param array $args {
	 *     Query arguments.
	 *
	 *     @type int    $limit       Number of results. Default 20.
	 *     @type int    $offset      Offset for pagination. Default 0.
	 *     @type int    $vendor_id   Filter by vendor.
	 *     @type int    $customer_id Filter by customer.
	 *     @type int    $user_id     Filter where user is vendor OR customer.
	 *     @type string $status      Filter by status.
	 *     @type int    $service_id  Filter by service.
	 *     @type string $orderby     Column to sort by. Default 'created_at'.
	 *     @type string $order       ASC or DESC. Default 'DESC'.
	 * }
	 * @return self[]
	 */
	public static function query( array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		$defaults = array(
			'limit'       => 20,
			'offset'      => 0,
			'orderby'     => 'created_at',
			'order'       => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		list( $where, $params ) = self::build_where_clause( $args );

		$allowed_orderby = array( 'id', 'created_at', 'updated_at', 'total', 'status' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$params[] = (int) $args['limit'];
		$params[] = (int) $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params
			)
		);

		return array_map( array( self::class, 'from_db' ), $rows ?: array() );
	}

	/**
	 * Count orders matching filters.
	 *
	 * @param array $args Same as query() but limit/offset/orderby/order are ignored.
	 * @return int
	 */
	public static function count( array $args = array() ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		list( $where, $params ) = self::build_where_clause( $args );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			empty( $params )
				? "SELECT COUNT(*) FROM {$table} {$where}" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				: $wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$params
				)
		);
	}

	/**
	 * Build WHERE clause from query args.
	 *
	 * @param array $args Query arguments.
	 * @return array{0: string, 1: array} WHERE clause string and params array.
	 */
	private static function build_where_clause( array $args ): array {
		$conditions = array();
		$params     = array();

		if ( ! empty( $args['vendor_id'] ) ) {
			$conditions[] = 'vendor_id = %d';
			$params[]     = (int) $args['vendor_id'];
		}

		if ( ! empty( $args['customer_id'] ) ) {
			$conditions[] = 'customer_id = %d';
			$params[]     = (int) $args['customer_id'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$conditions[] = '(vendor_id = %d OR customer_id = %d)';
			$params[]     = (int) $args['user_id'];
			$params[]     = (int) $args['user_id'];
		}

		if ( ! empty( $args['status'] ) ) {
			$conditions[] = 'status = %s';
			$params[]     = $args['status'];
		}

		if ( ! empty( $args['service_id'] ) ) {
			$conditions[] = 'service_id = %d';
			$params[]     = (int) $args['service_id'];
		}

		$where = ! empty( $conditions ) ? 'WHERE ' . implode( ' AND ', $conditions ) : '';

		return array( $where, $params );
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

		// Fire status change hook and record history if status changed.
		if ( isset( $data['status'] ) && $data['status'] !== $this->status ) {
			$old_status   = $this->status;
			$this->status = $data['status'];

			// Record status change in meta for timeline.
			$meta    = $this->meta;
			$history = $meta['status_history'] ?? array();

			$history[] = array(
				'status'    => $data['status'],
				'timestamp' => current_time( 'mysql' ),
				'note'      => '',
			);

			$meta['status_history'] = $history;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array( 'meta' => wp_json_encode( $meta ) ),
				array( 'id' => $this->id )
			);
			$this->meta = $meta;

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
		$datetime_props = array( 'delivery_deadline', 'original_deadline', 'paid_at', 'created_at', 'updated_at', 'started_at', 'completed_at' );
		foreach ( $data as $key => $value ) {
			if ( property_exists( $this, $key ) ) {
				// Convert MySQL datetime strings to DateTimeImmutable for typed properties.
				if ( in_array( $key, $datetime_props, true ) && is_string( $value ) ) {
					$value = new \DateTimeImmutable( $value );
				}
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
		$order->vendor_notes       = $row->vendor_notes ?? null;
		$order->meta               = ( $row->meta ?? null ) ? json_decode( $row->meta, true ) : [];

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
			self::STATUS_PENDING_PAYMENT        => __( 'Pending Payment', 'wp-sell-services' ),
			self::STATUS_PENDING                 => __( 'Pending', 'wp-sell-services' ),
			self::STATUS_ACCEPTED                => __( 'Accepted', 'wp-sell-services' ),
			self::STATUS_REJECTED                => __( 'Rejected', 'wp-sell-services' ),
			self::STATUS_PENDING_REQUIREMENTS    => __( 'Waiting for Requirements', 'wp-sell-services' ),
			self::STATUS_REQUIREMENTS_SUBMITTED  => __( 'Requirements Submitted', 'wp-sell-services' ),
			self::STATUS_IN_PROGRESS             => __( 'In Progress', 'wp-sell-services' ),
			self::STATUS_DELIVERED               => __( 'Delivered', 'wp-sell-services' ),
			self::STATUS_PENDING_APPROVAL        => __( 'Pending Approval', 'wp-sell-services' ),
			self::STATUS_REVISION_REQUESTED      => __( 'Revision Requested', 'wp-sell-services' ),
			self::STATUS_COMPLETED               => __( 'Completed', 'wp-sell-services' ),
			self::STATUS_CANCELLED               => __( 'Cancelled', 'wp-sell-services' ),
			self::STATUS_DISPUTED                => __( 'Disputed', 'wp-sell-services' ),
			self::STATUS_ON_HOLD                 => __( 'On Hold', 'wp-sell-services' ),
			self::STATUS_LATE                    => __( 'Late', 'wp-sell-services' ),
			self::STATUS_CANCELLATION_REQUESTED  => __( 'Cancellation Requested', 'wp-sell-services' ),
			self::STATUS_REFUNDED                => __( 'Refunded', 'wp-sell-services' ),
			self::STATUS_PARTIALLY_REFUNDED      => __( 'Partially Refunded', 'wp-sell-services' ),
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

	/**
	 * Get order ID.
	 *
	 * @return int
	 */
	public function get_id(): int {
		return $this->id;
	}

	/**
	 * Get buyer (customer) ID.
	 *
	 * @return int
	 */
	public function get_buyer_id(): int {
		return $this->customer_id;
	}

	/**
	 * Get vendor ID.
	 *
	 * @return int
	 */
	public function get_vendor_id(): int {
		return $this->vendor_id;
	}

	/**
	 * Get service ID.
	 *
	 * @return int
	 */
	public function get_service_id(): int {
		return $this->service_id;
	}

	/**
	 * Get status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * Get order total.
	 *
	 * @return float
	 */
	public function get_total(): float {
		return $this->total;
	}

	/**
	 * Get order subtotal.
	 *
	 * @return float
	 */
	public function get_subtotal(): float {
		return $this->subtotal;
	}

	/**
	 * Get platform order ID (e.g. WooCommerce order ID).
	 *
	 * @return int|null
	 */
	public function get_wc_order_id(): ?int {
		return $this->platform_order_id;
	}

	/**
	 * Get created timestamp as string.
	 *
	 * @return string MySQL datetime string.
	 */
	public function get_created_at(): string {
		return $this->created_at ? $this->created_at->format( 'Y-m-d H:i:s' ) : '';
	}

	/**
	 * Get delivery deadline as string.
	 *
	 * @return string|null MySQL datetime string or null.
	 */
	public function get_due_date(): ?string {
		return $this->delivery_deadline ? $this->delivery_deadline->format( 'Y-m-d H:i:s' ) : null;
	}

	/**
	 * Get days until delivery deadline.
	 *
	 * @return int Positive = days remaining, negative = days overdue.
	 */
	public function get_days_until_due(): int {
		if ( ! $this->delivery_deadline ) {
			return 0;
		}

		$now  = new \DateTimeImmutable();
		$diff = $now->diff( $this->delivery_deadline );

		return $diff->invert ? -$diff->days : $diff->days;
	}

	/**
	 * Get platform fee amount.
	 *
	 * @return float
	 */
	public function get_fee(): float {
		return $this->platform_fee ?? 0.0;
	}

	/**
	 * Get discount amount from meta.
	 *
	 * @return float
	 */
	public function get_discount(): float {
		return (float) ( $this->meta['discount'] ?? 0.0 );
	}

	/**
	 * Get package snapshot stored at order creation time.
	 *
	 * Falls back to live package data if no snapshot exists (for legacy orders).
	 *
	 * @return array|null Package data array or null if not available.
	 */
	public function get_package_snapshot(): ?array {
		// Try snapshot from meta first.
		$snapshot = $this->meta['package_snapshot'] ?? null;
		if ( is_array( $snapshot ) && ! empty( $snapshot ) ) {
			return $snapshot;
		}

		// Try proposal snapshot for request-based orders.
		$proposal = $this->meta['proposal_snapshot'] ?? null;
		if ( is_array( $proposal ) && ! empty( $proposal ) ) {
			return $proposal;
		}

		// Fall back to live data.
		if ( ! $this->package_id ) {
			return null;
		}

		$service = $this->get_service();
		if ( ! $service ) {
			return null;
		}

		$packages = get_post_meta( $service->id, '_wpss_packages', true ) ?: [];

		return $packages[ $this->package_id ] ?? null;
	}

	/**
	 * Get package name.
	 *
	 * Uses the package snapshot first (frozen at order creation), then falls back to live data.
	 *
	 * @return string
	 */
	public function get_package_name(): string {
		if ( ! $this->package_id && empty( $this->meta['proposal_snapshot'] ) ) {
			return __( 'Custom', 'wp-sell-services' );
		}

		// Try snapshot first.
		$snapshot = $this->get_package_snapshot();
		if ( $snapshot ) {
			// Package snapshot has 'name', proposal snapshot has 'request_title'.
			return $snapshot['name'] ?? $snapshot['request_title'] ?? __( 'Package', 'wp-sell-services' );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_service_packages';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$name = $wpdb->get_var(
			$wpdb->prepare( "SELECT name FROM {$table} WHERE id = %d", $this->package_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $name ?: __( 'Package', 'wp-sell-services' );
	}

	/**
	 * Get order line items (constructed from package + addons).
	 *
	 * @return array<array{name: string, description: string, quantity: int, price: float, total: float}>
	 */
	public function get_items(): array {
		$items = array();

		// Main package item.
		$items[] = array(
			'name'        => $this->get_package_name(),
			'description' => '',
			'quantity'    => 1,
			'price'       => $this->subtotal,
			'total'       => $this->subtotal,
		);

		// Add-on items.
		if ( ! empty( $this->addons ) ) {
			foreach ( $this->addons as $addon ) {
				$items[] = array(
					'name'        => $addon['name'] ?? __( 'Add-on', 'wp-sell-services' ),
					'description' => $addon['description'] ?? '',
					'quantity'    => $addon['quantity'] ?? 1,
					'price'       => (float) ( $addon['price'] ?? 0 ),
					'total'       => (float) ( $addon['total'] ?? $addon['price'] ?? 0 ),
				);
			}
		}

		return $items;
	}

	/**
	 * Get buyer requirements from the order_requirements table.
	 *
	 * @return array<string, mixed>
	 */
	public function get_requirements(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_requirements';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT field_data FROM {$table} WHERE order_id = %d ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$this->id
			)
		);

		if ( ! $row || ! $row->field_data ) {
			return array();
		}

		$data = json_decode( $row->field_data, true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Get admin notes (stored in meta JSON).
	 *
	 * @return array<array{content: string, author_id: int, created_at: string}>
	 */
	public function get_admin_notes(): array {
		return $this->meta['admin_notes'] ?? array();
	}

	/**
	 * Get order status history (stored in meta JSON).
	 *
	 * @return array<array{status: string, timestamp: string, note: string}>
	 */
	public function get_status_history(): array {
		return $this->meta['status_history'] ?? array();
	}
}
