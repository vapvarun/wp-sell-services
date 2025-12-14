<?php
/**
 * Dispute Service
 *
 * Business logic for dispute management.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

namespace WPSellServices\Services;

use WPSellServices\Database\Repositories\OrderRepository;

defined( 'ABSPATH' ) || exit;

/**
 * DisputeService class.
 *
 * @since 1.0.0
 */
class DisputeService {

	/**
	 * Dispute statuses.
	 */
	public const STATUS_OPEN       = 'open';
	public const STATUS_PENDING    = 'pending_review';
	public const STATUS_RESOLVED   = 'resolved';
	public const STATUS_ESCALATED  = 'escalated';
	public const STATUS_CLOSED     = 'closed';

	/**
	 * Resolution types.
	 */
	public const RESOLUTION_REFUND         = 'full_refund';
	public const RESOLUTION_PARTIAL_REFUND = 'partial_refund';
	public const RESOLUTION_FAVOR_VENDOR   = 'favor_vendor';
	public const RESOLUTION_FAVOR_BUYER    = 'favor_buyer';
	public const RESOLUTION_MUTUAL         = 'mutual_agreement';

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Evidence table name.
	 *
	 * @var string
	 */
	private string $evidence_table;

	/**
	 * Order repository.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $order_repo;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table          = $wpdb->prefix . 'wpss_disputes';
		$this->evidence_table = $wpdb->prefix . 'wpss_dispute_evidence';
		$this->order_repo     = new OrderRepository();
	}

	/**
	 * Open a dispute.
	 *
	 * @param int                  $order_id Order ID.
	 * @param int                  $opened_by User ID who opened dispute.
	 * @param string               $reason Dispute reason.
	 * @param string               $description Detailed description.
	 * @param array<string, mixed> $meta Additional metadata.
	 * @return int|false Dispute ID or false on failure.
	 */
	public function open( int $order_id, int $opened_by, string $reason, string $description, array $meta = [] ): int|false {
		global $wpdb;

		// Check if order exists.
		$order = $this->order_repo->find( $order_id );

		if ( ! $order ) {
			return false;
		}

		// Check if user is part of the order.
		if ( $order->customer_id !== $opened_by && $order->vendor_id !== $opened_by ) {
			return false;
		}

		// Check if dispute already exists for this order.
		if ( $this->get_by_order( $order_id ) ) {
			return false;
		}

		$data = [
			'order_id'    => $order_id,
			'opened_by'   => $opened_by,
			'reason'      => sanitize_text_field( $reason ),
			'description' => sanitize_textarea_field( $description ),
			'status'      => self::STATUS_OPEN,
			'meta'        => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		];

		$result = $wpdb->insert( $this->table, $data );

		if ( $result ) {
			$dispute_id = $wpdb->insert_id;

			// Update order status.
			$this->order_repo->update( $order_id, [ 'status' => 'disputed' ] );

			/**
			 * Fires when a dispute is opened.
			 *
			 * @since 1.0.0
			 * @param int   $dispute_id Dispute ID.
			 * @param int   $order_id   Order ID.
			 * @param int   $opened_by  User ID.
			 * @param array $data       Dispute data.
			 */
			do_action( 'wpss_dispute_opened', $dispute_id, $order_id, $opened_by, $data );

			return $dispute_id;
		}

		return false;
	}

	/**
	 * Get dispute by ID.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return object|null Dispute object or null.
	 */
	public function get( int $dispute_id ): ?object {
		global $wpdb;

		$dispute = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$dispute_id
			)
		);

		if ( $dispute && $dispute->meta ) {
			$dispute->meta = json_decode( $dispute->meta, true );
		}

		return $dispute;
	}

	/**
	 * Get dispute by order ID.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null Dispute object or null.
	 */
	public function get_by_order( int $order_id ): ?object {
		global $wpdb;

		$dispute = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE order_id = %d",
				$order_id
			)
		);

		if ( $dispute && $dispute->meta ) {
			$dispute->meta = json_decode( $dispute->meta, true );
		}

		return $dispute;
	}

	/**
	 * Add evidence to a dispute.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param int    $user_id User ID submitting evidence.
	 * @param string $type Evidence type (text, image, file, link).
	 * @param string $content Evidence content.
	 * @param string $description Evidence description.
	 * @return int|false Evidence ID or false on failure.
	 */
	public function add_evidence( int $dispute_id, int $user_id, string $type, string $content, string $description = '' ): int|false {
		global $wpdb;

		$dispute = $this->get( $dispute_id );

		if ( ! $dispute || $dispute->status === self::STATUS_CLOSED ) {
			return false;
		}

		$data = [
			'dispute_id'  => $dispute_id,
			'user_id'     => $user_id,
			'type'        => sanitize_key( $type ),
			'content'     => $content,
			'description' => sanitize_textarea_field( $description ),
			'created_at'  => current_time( 'mysql' ),
		];

		$result = $wpdb->insert( $this->evidence_table, $data );

		if ( $result ) {
			$evidence_id = $wpdb->insert_id;

			/**
			 * Fires when evidence is added to a dispute.
			 *
			 * @since 1.0.0
			 * @param int $evidence_id Evidence ID.
			 * @param int $dispute_id  Dispute ID.
			 * @param int $user_id     User ID.
			 */
			do_action( 'wpss_dispute_evidence_added', $evidence_id, $dispute_id, $user_id );

			return $evidence_id;
		}

		return false;
	}

	/**
	 * Get evidence for a dispute.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return array<object> Array of evidence objects.
	 */
	public function get_evidence( int $dispute_id ): array {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->evidence_table}
				WHERE dispute_id = %d
				ORDER BY created_at ASC",
				$dispute_id
			)
		);
	}

	/**
	 * Update dispute status.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param string $status New status.
	 * @param string $note Optional note.
	 * @return bool True on success.
	 */
	public function update_status( int $dispute_id, string $status, string $note = '' ): bool {
		global $wpdb;

		$valid_statuses = [
			self::STATUS_OPEN,
			self::STATUS_PENDING,
			self::STATUS_RESOLVED,
			self::STATUS_ESCALATED,
			self::STATUS_CLOSED,
		];

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		$dispute    = $this->get( $dispute_id );
		$old_status = $dispute ? $dispute->status : '';

		$data = [
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( $note ) {
			$meta         = $dispute->meta ?? [];
			$meta['notes'] = $meta['notes'] ?? [];
			$meta['notes'][] = [
				'note'       => $note,
				'status'     => $status,
				'created_at' => current_time( 'mysql' ),
			];
			$data['meta'] = wp_json_encode( $meta );
		}

		$result = $wpdb->update(
			$this->table,
			$data,
			[ 'id' => $dispute_id ]
		);

		if ( $result !== false ) {
			/**
			 * Fires when dispute status changes.
			 *
			 * @since 1.0.0
			 * @param int    $dispute_id Dispute ID.
			 * @param string $status     New status.
			 * @param string $old_status Old status.
			 */
			do_action( 'wpss_dispute_status_changed', $dispute_id, $status, $old_status );

			return true;
		}

		return false;
	}

	/**
	 * Resolve a dispute.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param string $resolution Resolution type.
	 * @param string $notes Resolution notes.
	 * @param int    $resolved_by Admin user ID.
	 * @param float  $refund_amount Optional refund amount.
	 * @return bool True on success.
	 */
	public function resolve( int $dispute_id, string $resolution, string $notes, int $resolved_by, float $refund_amount = 0.0 ): bool {
		global $wpdb;

		$dispute = $this->get( $dispute_id );

		if ( ! $dispute ) {
			return false;
		}

		$meta = $dispute->meta ?? [];
		$meta['resolution'] = [
			'type'          => $resolution,
			'notes'         => $notes,
			'resolved_by'   => $resolved_by,
			'refund_amount' => $refund_amount,
			'resolved_at'   => current_time( 'mysql' ),
		];

		$result = $wpdb->update(
			$this->table,
			[
				'status'      => self::STATUS_RESOLVED,
				'resolved_at' => current_time( 'mysql' ),
				'meta'        => wp_json_encode( $meta ),
				'updated_at'  => current_time( 'mysql' ),
			],
			[ 'id' => $dispute_id ]
		);

		if ( $result !== false ) {
			// Handle resolution actions.
			$this->handle_resolution( $dispute, $resolution, $refund_amount );

			/**
			 * Fires when a dispute is resolved.
			 *
			 * @since 1.0.0
			 * @param int    $dispute_id    Dispute ID.
			 * @param string $resolution    Resolution type.
			 * @param object $dispute       Dispute object.
			 * @param float  $refund_amount Refund amount.
			 */
			do_action( 'wpss_dispute_resolved', $dispute_id, $resolution, $dispute, $refund_amount );

			return true;
		}

		return false;
	}

	/**
	 * Handle resolution actions.
	 *
	 * @param object $dispute Dispute object.
	 * @param string $resolution Resolution type.
	 * @param float  $refund_amount Refund amount.
	 * @return void
	 */
	private function handle_resolution( object $dispute, string $resolution, float $refund_amount ): void {
		switch ( $resolution ) {
			case self::RESOLUTION_REFUND:
			case self::RESOLUTION_FAVOR_BUYER:
				// Update order status to refunded.
				$this->order_repo->update( $dispute->order_id, [ 'status' => 'refunded' ] );
				break;

			case self::RESOLUTION_PARTIAL_REFUND:
				// Update order status.
				$this->order_repo->update( $dispute->order_id, [ 'status' => 'partially_refunded' ] );
				break;

			case self::RESOLUTION_FAVOR_VENDOR:
				// Restore order to completed.
				$this->order_repo->update( $dispute->order_id, [ 'status' => 'completed' ] );
				break;

			case self::RESOLUTION_MUTUAL:
				// Both parties agreed, mark as completed.
				$this->order_repo->update( $dispute->order_id, [ 'status' => 'completed' ] );
				break;
		}
	}

	/**
	 * Get disputes by user.
	 *
	 * @param int                  $user_id User ID.
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of disputes.
	 */
	public function get_by_user( int $user_id, array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ '1=1' ];
		$values = [];

		// User can be opener or part of the order.
		$where[] = "(d.opened_by = %d OR o.customer_id = %d OR o.vendor_id = %d)";
		$values[] = $user_id;
		$values[] = $user_id;
		$values[] = $user_id;

		if ( $args['status'] ) {
			$where[] = 'd.status = %s';
			$values[] = $args['status'];
		}

		$orders_table = $wpdb->prefix . 'wpss_orders';
		$where_clause = implode( ' AND ', $where );

		$sql = $wpdb->prepare(
			"SELECT d.*, o.customer_id, o.vendor_id, o.service_id
			FROM {$this->table} d
			LEFT JOIN {$orders_table} o ON d.order_id = o.id
			WHERE {$where_clause}
			ORDER BY d.{$args['order_by']} {$args['order']}
			LIMIT %d OFFSET %d",
			array_merge( $values, [ $args['limit'], $args['offset'] ] )
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get all disputes for admin.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of disputes.
	 */
	public function get_all( array $args = [] ): array {
		global $wpdb;

		$defaults = [
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		];

		$args = wp_parse_args( $args, $defaults );

		$where = [ '1=1' ];
		$values = [];

		if ( $args['status'] ) {
			$where[] = 'd.status = %s';
			$values[] = $args['status'];
		}

		$orders_table = $wpdb->prefix . 'wpss_orders';
		$where_clause = implode( ' AND ', $where );

		$values[] = $args['limit'];
		$values[] = $args['offset'];

		$sql = $wpdb->prepare(
			"SELECT d.*, o.customer_id, o.vendor_id, o.service_id
			FROM {$this->table} d
			LEFT JOIN {$orders_table} o ON d.order_id = o.id
			WHERE {$where_clause}
			ORDER BY d.{$args['order_by']} {$args['order']}
			LIMIT %d OFFSET %d",
			$values
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Count disputes by status.
	 *
	 * @return array<string, int> Status counts.
	 */
	public function count_by_status(): array {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT status, COUNT(*) as count
			FROM {$this->table}
			GROUP BY status"
		);

		$counts = [
			self::STATUS_OPEN      => 0,
			self::STATUS_PENDING   => 0,
			self::STATUS_RESOLVED  => 0,
			self::STATUS_ESCALATED => 0,
			self::STATUS_CLOSED    => 0,
		];

		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Get available dispute statuses.
	 *
	 * @return array<string, string> Status slugs and labels.
	 */
	public static function get_statuses(): array {
		return [
			self::STATUS_OPEN      => __( 'Open', 'wp-sell-services' ),
			self::STATUS_PENDING   => __( 'Pending Review', 'wp-sell-services' ),
			self::STATUS_RESOLVED  => __( 'Resolved', 'wp-sell-services' ),
			self::STATUS_ESCALATED => __( 'Escalated', 'wp-sell-services' ),
			self::STATUS_CLOSED    => __( 'Closed', 'wp-sell-services' ),
		];
	}

	/**
	 * Get available resolution types.
	 *
	 * @return array<string, string> Resolution slugs and labels.
	 */
	public static function get_resolution_types(): array {
		return [
			self::RESOLUTION_REFUND         => __( 'Full Refund', 'wp-sell-services' ),
			self::RESOLUTION_PARTIAL_REFUND => __( 'Partial Refund', 'wp-sell-services' ),
			self::RESOLUTION_FAVOR_VENDOR   => __( 'In Favor of Vendor', 'wp-sell-services' ),
			self::RESOLUTION_FAVOR_BUYER    => __( 'In Favor of Buyer', 'wp-sell-services' ),
			self::RESOLUTION_MUTUAL         => __( 'Mutual Agreement', 'wp-sell-services' ),
		];
	}
}
