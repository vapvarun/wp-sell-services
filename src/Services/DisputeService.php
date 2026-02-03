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
	public const STATUS_OPEN      = 'open';
	public const STATUS_PENDING   = 'pending_review';
	public const STATUS_RESOLVED  = 'resolved';
	public const STATUS_ESCALATED = 'escalated';
	public const STATUS_CLOSED    = 'closed';

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
	 * Dispute messages table name.
	 *
	 * @var string
	 */
	private string $messages_table;

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
		$this->messages_table = $wpdb->prefix . 'wpss_dispute_messages';
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
	public function open( int $order_id, int $opened_by, string $reason, string $description, array $meta = array() ): int|false {
		global $wpdb;

		// Check if order exists.
		$order = $this->order_repo->find( $order_id );

		if ( ! $order ) {
			return false;
		}

		// Check if user is part of the order.
		// Cast to int since database returns string values.
		if ( (int) $order->customer_id !== $opened_by && (int) $order->vendor_id !== $opened_by ) {
			return false;
		}

		// Check if dispute already exists for this order.
		if ( $this->get_by_order( $order_id ) ) {
			return false;
		}

		$data = array(
			'order_id'     => $order_id,
			'initiated_by' => $opened_by,
			'reason'       => sanitize_text_field( $reason ),
			'description'  => sanitize_textarea_field( $description ),
			'status'       => self::STATUS_OPEN,
			'evidence'     => ! empty( $meta ) ? wp_json_encode( $meta ) : null,
			'created_at'   => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $this->table, $data );

		if ( $result ) {
			$dispute_id = $wpdb->insert_id;

			// Update order status.
			$this->order_repo->update( $order_id, array( 'status' => 'disputed' ) );

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$dispute = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$dispute_id
			)
		);

		if ( $dispute && ! empty( $dispute->evidence ) ) {
			$dispute->evidence = json_decode( $dispute->evidence, true );
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$dispute = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE order_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);

		if ( $dispute && ! empty( $dispute->evidence ) ) {
			$dispute->evidence = json_decode( $dispute->evidence, true );
		}

		return $dispute;
	}

	/**
	 * Add evidence to a dispute.
	 *
	 * Evidence is stored in the dispute's evidence JSON column.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param int    $user_id User ID submitting evidence.
	 * @param string $type Evidence type (text, image, file, link).
	 * @param string $content Evidence content.
	 * @param string $description Evidence description.
	 * @return bool True on success, false on failure.
	 */
	public function add_evidence( int $dispute_id, int $user_id, string $type, string $content, string $description = '' ): bool {
		global $wpdb;

		$dispute = $this->get( $dispute_id );

		if ( ! $dispute || $dispute->status === self::STATUS_CLOSED ) {
			return false;
		}

		// Get existing evidence or initialize empty array.
		$evidence = is_array( $dispute->evidence ) ? $dispute->evidence : array();

		// Sanitize content based on evidence type.
		$sanitized_type = sanitize_key( $type );
		$sanitized_content = match ( $sanitized_type ) {
			'link'          => esc_url_raw( $content ),
			'image', 'file' => absint( $content ),
			default         => sanitize_textarea_field( $content ),
		};

		// Add new evidence item.
		$evidence[] = array(
			'id'          => uniqid( 'ev_' ),
			'user_id'     => $user_id,
			'type'        => $sanitized_type,
			'content'     => $sanitized_content,
			'description' => sanitize_textarea_field( $description ),
			'created_at'  => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array(
				'evidence'   => wp_json_encode( $evidence ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $dispute_id )
		);

		if ( false !== $result ) {
			/**
			 * Fires when evidence is added to a dispute.
			 *
			 * @since 1.0.0
			 * @param int $dispute_id Dispute ID.
			 * @param int $user_id    User ID.
			 */
			do_action( 'wpss_dispute_evidence_added', $dispute_id, $user_id );

			return true;
		}

		return false;
	}

	/**
	 * Get evidence for a dispute.
	 *
	 * @param int $dispute_id Dispute ID.
	 * @return array<array<string, mixed>> Array of evidence items.
	 */
	public function get_evidence( int $dispute_id ): array {
		$dispute = $this->get( $dispute_id );

		if ( ! $dispute ) {
			return array();
		}

		return is_array( $dispute->evidence ) ? $dispute->evidence : array();
	}

	/**
	 * Update dispute status.
	 *
	 * Notes are stored in the evidence JSON column with type 'status_note'.
	 *
	 * @param int    $dispute_id Dispute ID.
	 * @param string $status New status.
	 * @param string $note Optional note.
	 * @return bool True on success.
	 */
	public function update_status( int $dispute_id, string $status, string $note = '' ): bool {
		global $wpdb;

		$valid_statuses = array(
			self::STATUS_OPEN,
			self::STATUS_PENDING,
			self::STATUS_RESOLVED,
			self::STATUS_ESCALATED,
			self::STATUS_CLOSED,
		);

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		$dispute    = $this->get( $dispute_id );
		$old_status = $dispute ? $dispute->status : '';

		$data = array(
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		);

		// Store status note in evidence JSON if provided.
		if ( $note ) {
			$evidence         = is_array( $dispute->evidence ) ? $dispute->evidence : array();
			$evidence[]       = array(
				'id'         => uniqid( 'note_' ),
				'type'       => 'status_note',
				'note'       => sanitize_textarea_field( $note ),
				'status'     => $status,
				'created_at' => current_time( 'mysql' ),
			);
			$data['evidence'] = wp_json_encode( $evidence );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			$data,
			array( 'id' => $dispute_id )
		);

		if ( false !== $result ) {
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

		// Store refund amount in evidence JSON if applicable.
		$evidence = is_array( $dispute->evidence ) ? $dispute->evidence : array();
		if ( $refund_amount > 0 ) {
			$evidence[] = array(
				'id'            => uniqid( 'refund_' ),
				'type'          => 'refund_info',
				'refund_amount' => $refund_amount,
				'created_at'    => current_time( 'mysql' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$this->table,
			array(
				'status'           => self::STATUS_RESOLVED,
				'resolution'       => sanitize_key( $resolution ),
				'resolution_notes' => sanitize_textarea_field( $notes ),
				'resolved_by'      => $resolved_by,
				'resolved_at'      => current_time( 'mysql' ),
				'evidence'         => wp_json_encode( $evidence ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $dispute_id )
		);

		if ( false !== $result ) {
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
				$this->order_repo->update( $dispute->order_id, array( 'status' => 'refunded' ) );
				break;

			case self::RESOLUTION_PARTIAL_REFUND:
				// Update order status.
				$this->order_repo->update( $dispute->order_id, array( 'status' => 'partially_refunded' ) );
				break;

			case self::RESOLUTION_FAVOR_VENDOR:
				// Restore order to completed.
				$this->order_repo->update( $dispute->order_id, array( 'status' => 'completed' ) );
				break;

			case self::RESOLUTION_MUTUAL:
				// Both parties agreed, mark as completed.
				$this->order_repo->update( $dispute->order_id, array( 'status' => 'completed' ) );
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
	public function get_by_user( int $user_id, array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		// User can be initiator or part of the order.
		$where[]  = '(d.initiated_by = %d OR o.customer_id = %d OR o.vendor_id = %d)';
		$values[] = $user_id;
		$values[] = $user_id;
		$values[] = $user_id;

		if ( $args['status'] ) {
			$where[]  = 'd.status = %s';
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
			array_merge( $values, array( $args['limit'], $args['offset'] ) )
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Get all disputes for admin.
	 *
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of disputes.
	 */
	public function get_all( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$values = array();

		if ( $args['status'] ) {
			$where[]  = 'd.status = %s';
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

		$counts = array(
			self::STATUS_OPEN      => 0,
			self::STATUS_PENDING   => 0,
			self::STATUS_RESOLVED  => 0,
			self::STATUS_ESCALATED => 0,
			self::STATUS_CLOSED    => 0,
		);

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
		return array(
			self::STATUS_OPEN      => __( 'Open', 'wp-sell-services' ),
			self::STATUS_PENDING   => __( 'Pending Review', 'wp-sell-services' ),
			self::STATUS_RESOLVED  => __( 'Resolved', 'wp-sell-services' ),
			self::STATUS_ESCALATED => __( 'Escalated', 'wp-sell-services' ),
			self::STATUS_CLOSED    => __( 'Closed', 'wp-sell-services' ),
		);
	}

	/**
	 * Get available resolution types.
	 *
	 * @return array<string, string> Resolution slugs and labels.
	 */
	public static function get_resolution_types(): array {
		return array(
			self::RESOLUTION_REFUND         => __( 'Full Refund', 'wp-sell-services' ),
			self::RESOLUTION_PARTIAL_REFUND => __( 'Partial Refund', 'wp-sell-services' ),
			self::RESOLUTION_FAVOR_VENDOR   => __( 'In Favor of Vendor', 'wp-sell-services' ),
			self::RESOLUTION_FAVOR_BUYER    => __( 'In Favor of Buyer', 'wp-sell-services' ),
			self::RESOLUTION_MUTUAL         => __( 'Mutual Agreement', 'wp-sell-services' ),
		);
	}
}
