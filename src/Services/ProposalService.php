<?php
/**
 * Proposal Service
 *
 * Business logic for vendor proposals on buyer requests.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * ProposalService class.
 *
 * @since 1.0.0
 */
class ProposalService {

	/**
	 * Proposal statuses.
	 */
	public const STATUS_PENDING   = 'pending';
	public const STATUS_ACCEPTED  = 'accepted';
	public const STATUS_REJECTED  = 'rejected';
	public const STATUS_WITHDRAWN = 'withdrawn';

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	private string $table;

	/**
	 * Allowed columns for ORDER BY.
	 *
	 * @var array<string>
	 */
	private array $allowed_order_columns = array(
		'id',
		'request_id',
		'vendor_id',
		'price',
		'delivery_days',
		'status',
		'created_at',
		'updated_at',
	);

	/**
	 * Validate ORDER BY column.
	 *
	 * @param string $column Column name.
	 * @return string Validated column name.
	 */
	private function validate_orderby( string $column ): string {
		$column = sanitize_key( $column );
		return in_array( $column, $this->allowed_order_columns, true ) ? $column : 'created_at';
	}

	/**
	 * Validate ORDER direction.
	 *
	 * @param string $order Order direction.
	 * @return string Validated order direction.
	 */
	private function validate_order( string $order ): string {
		$order = strtoupper( trim( $order ) );
		return in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'wpss_proposals';
	}

	/**
	 * Submit a proposal.
	 *
	 * @param int                  $request_id Buyer request ID.
	 * @param int                  $vendor_id Vendor user ID.
	 * @param array<string, mixed> $data Proposal data.
	 * @return int|false Proposal ID or false on failure.
	 */
	public function submit( int $request_id, int $vendor_id, array $data ): int|false {
		global $wpdb;

		// Check if request exists and is open.
		$request_service = new BuyerRequestService();
		$request         = $request_service->get( $request_id );

		if ( ! $request || $request->status !== BuyerRequestService::STATUS_OPEN ) {
			return false;
		}

		// Check if vendor already submitted a proposal.
		if ( $this->vendor_has_proposed( $request_id, $vendor_id ) ) {
			return false;
		}

		// Validate required fields.
		if ( empty( $data['description'] ) || empty( $data['price'] ) ) {
			return false;
		}

		$proposal_data = array(
			'request_id'    => $request_id,
			'vendor_id'     => $vendor_id,
			'description'   => sanitize_textarea_field( $data['description'] ),
			'price'         => (float) $data['price'],
			'delivery_days' => (int) ( $data['delivery_days'] ?? $request->delivery_days ),
			'status'        => self::STATUS_PENDING,
			'attachments'   => isset( $data['attachments'] ) ? wp_json_encode( $data['attachments'] ) : null,
			'meta'          => isset( $data['meta'] ) ? wp_json_encode( $data['meta'] ) : null,
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $this->table, $proposal_data );

		if ( $result ) {
			$proposal_id = $wpdb->insert_id;

			/**
			 * Fires when a proposal is submitted.
			 *
			 * @since 1.0.0
			 * @param int   $proposal_id Proposal ID.
			 * @param int   $request_id  Request ID.
			 * @param int   $vendor_id   Vendor user ID.
			 * @param array $data        Proposal data.
			 */
			do_action( 'wpss_proposal_submitted', $proposal_id, $request_id, $vendor_id, $proposal_data );

			return $proposal_id;
		}

		return false;
	}

	/**
	 * Get proposal by ID.
	 *
	 * @param int $proposal_id Proposal ID.
	 * @return object|null Proposal object or null.
	 */
	public function get( int $proposal_id ): ?object {
		global $wpdb;

		$proposal = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE id = %d",
				$proposal_id
			)
		);

		if ( $proposal ) {
			$proposal = $this->format_proposal( $proposal );
		}

		return $proposal;
	}

	/**
	 * Format proposal object.
	 *
	 * @param object $proposal Raw proposal object.
	 * @return object Formatted proposal.
	 */
	private function format_proposal( object $proposal ): object {
		$proposal->price         = (float) $proposal->price;
		$proposal->delivery_days = (int) $proposal->delivery_days;
		$proposal->attachments   = $proposal->attachments ? json_decode( $proposal->attachments, true ) : array();
		$proposal->meta          = $proposal->meta ? json_decode( $proposal->meta, true ) : array();

		return $proposal;
	}

	/**
	 * Update a proposal.
	 *
	 * @param int                  $proposal_id Proposal ID.
	 * @param array<string, mixed> $data Updated data.
	 * @return bool True on success.
	 */
	public function update( int $proposal_id, array $data ): bool {
		global $wpdb;

		$proposal = $this->get( $proposal_id );

		if ( ! $proposal || $proposal->status !== self::STATUS_PENDING ) {
			return false;
		}

		$update_data = array( 'updated_at' => current_time( 'mysql' ) );

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
		}

		if ( isset( $data['price'] ) ) {
			$update_data['price'] = (float) $data['price'];
		}

		if ( isset( $data['delivery_days'] ) ) {
			$update_data['delivery_days'] = (int) $data['delivery_days'];
		}

		if ( isset( $data['attachments'] ) ) {
			$update_data['attachments'] = wp_json_encode( $data['attachments'] );
		}

		$result = $wpdb->update(
			$this->table,
			$update_data,
			array( 'id' => $proposal_id )
		);

		if ( $result !== false ) {
			/**
			 * Fires when a proposal is updated.
			 *
			 * @since 1.0.0
			 * @param int   $proposal_id Proposal ID.
			 * @param array $data        Updated data.
			 */
			do_action( 'wpss_proposal_updated', $proposal_id, $update_data );

			return true;
		}

		return false;
	}

	/**
	 * Accept a proposal.
	 *
	 * @param int $proposal_id Proposal ID.
	 * @param int $buyer_id Buyer user ID (must own the request).
	 * @return bool True on success.
	 */
	public function accept( int $proposal_id, int $buyer_id ): bool {
		global $wpdb;

		$proposal = $this->get( $proposal_id );

		if ( ! $proposal ) {
			return false;
		}

		// Verify buyer owns the request.
		$request_service = new BuyerRequestService();
		$request         = $request_service->get( $proposal->request_id );

		if ( ! $request || $request->author_id !== $buyer_id ) {
			return false;
		}

		// Update proposal status.
		$result = $wpdb->update(
			$this->table,
			array(
				'status'     => self::STATUS_ACCEPTED,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $proposal_id )
		);

		if ( $result !== false ) {
			// Mark request as hired.
			$request_service->mark_hired( $proposal->request_id, $proposal->vendor_id, $proposal_id );

			// Reject other proposals for this request.
			$this->reject_other_proposals( $proposal->request_id, $proposal_id );

			/**
			 * Fires when a proposal is accepted.
			 *
			 * @since 1.0.0
			 * @param int    $proposal_id Proposal ID.
			 * @param object $proposal    Proposal object.
			 * @param object $request     Request object.
			 */
			do_action( 'wpss_proposal_accepted', $proposal_id, $proposal, $request );

			return true;
		}

		return false;
	}

	/**
	 * Reject a proposal.
	 *
	 * @param int    $proposal_id Proposal ID.
	 * @param int    $buyer_id Buyer user ID.
	 * @param string $reason Optional rejection reason.
	 * @return bool True on success.
	 */
	public function reject( int $proposal_id, int $buyer_id, string $reason = '' ): bool {
		global $wpdb;

		$proposal = $this->get( $proposal_id );

		if ( ! $proposal ) {
			return false;
		}

		// Verify buyer owns the request.
		$request_service = new BuyerRequestService();
		$request         = $request_service->get( $proposal->request_id );

		if ( ! $request || $request->author_id !== $buyer_id ) {
			return false;
		}

		$meta                     = $proposal->meta;
		$meta['rejection_reason'] = $reason;

		$result = $wpdb->update(
			$this->table,
			array(
				'status'     => self::STATUS_REJECTED,
				'meta'       => wp_json_encode( $meta ),
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $proposal_id )
		);

		if ( $result !== false ) {
			/**
			 * Fires when a proposal is rejected.
			 *
			 * @since 1.0.0
			 * @param int    $proposal_id Proposal ID.
			 * @param object $proposal    Proposal object.
			 * @param string $reason      Rejection reason.
			 */
			do_action( 'wpss_proposal_rejected', $proposal_id, $proposal, $reason );

			return true;
		}

		return false;
	}

	/**
	 * Withdraw a proposal.
	 *
	 * @param int $proposal_id Proposal ID.
	 * @param int $vendor_id Vendor user ID.
	 * @return bool True on success.
	 */
	public function withdraw( int $proposal_id, int $vendor_id ): bool {
		global $wpdb;

		$proposal = $this->get( $proposal_id );

		if ( ! $proposal || $proposal->vendor_id !== $vendor_id ) {
			return false;
		}

		if ( $proposal->status !== self::STATUS_PENDING ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table,
			array(
				'status'     => self::STATUS_WITHDRAWN,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $proposal_id )
		);

		if ( $result !== false ) {
			/**
			 * Fires when a proposal is withdrawn.
			 *
			 * @since 1.0.0
			 * @param int    $proposal_id Proposal ID.
			 * @param object $proposal    Proposal object.
			 */
			do_action( 'wpss_proposal_withdrawn', $proposal_id, $proposal );

			return true;
		}

		return false;
	}

	/**
	 * Update proposal status directly.
	 *
	 * Use this for simple status updates without full accept/reject workflow.
	 *
	 * @param int    $proposal_id Proposal ID.
	 * @param string $status      New status.
	 * @return bool True on success.
	 */
	public function update_status( int $proposal_id, string $status ): bool {
		global $wpdb;

		$valid_statuses = array(
			self::STATUS_PENDING,
			self::STATUS_ACCEPTED,
			self::STATUS_REJECTED,
			self::STATUS_WITHDRAWN,
		);

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table,
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $proposal_id )
		);

		if ( false !== $result ) {
			/**
			 * Fires when a proposal status is updated.
			 *
			 * @since 1.0.0
			 * @param int    $proposal_id Proposal ID.
			 * @param string $status      New status.
			 */
			do_action( 'wpss_proposal_status_updated', $proposal_id, $status );

			return true;
		}

		return false;
	}

	/**
	 * Reject all other proposals for a request.
	 *
	 * @param int $request_id Request ID.
	 * @param int $except_id Proposal ID to exclude.
	 * @return void
	 */
	public function reject_other_proposals( int $request_id, int $except_id ): void {
		global $wpdb;

		$wpdb->update(
			$this->table,
			array(
				'status'     => self::STATUS_REJECTED,
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'request_id' => $request_id,
				'status'     => self::STATUS_PENDING,
			)
		);

		// The accepted one might have been updated, restore it.
		$wpdb->update(
			$this->table,
			array( 'status' => self::STATUS_ACCEPTED ),
			array( 'id' => $except_id )
		);
	}

	/**
	 * Get proposals for a request.
	 *
	 * @param int                  $request_id Request ID.
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of proposals.
	 */
	public function get_by_request( int $request_id, array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'limit'    => 50,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY and ORDER against whitelist.
		$order_by = $this->validate_orderby( $args['order_by'] );
		$order    = $this->validate_order( $args['order'] );

		$where  = array( 'request_id = %d' );
		$values = array( $request_id );

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$values[]     = $args['limit'];
		$values[]     = $args['offset'];

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table}
			WHERE {$where_clause}
			ORDER BY {$order_by} {$order}
			LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$values
		);

		$proposals = $wpdb->get_results( $sql );

		return array_map( array( $this, 'format_proposal' ), $proposals );
	}

	/**
	 * Get proposals by vendor.
	 *
	 * @param int                  $vendor_id Vendor user ID.
	 * @param array<string, mixed> $args Query arguments.
	 * @return array<object> Array of proposals.
	 */
	public function get_by_vendor( int $vendor_id, array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'order_by' => 'created_at',
			'order'    => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// Validate ORDER BY and ORDER against whitelist.
		$order_by = $this->validate_orderby( $args['order_by'] );
		$order    = $this->validate_order( $args['order'] );

		$where  = array( 'vendor_id = %d' );
		$values = array( $vendor_id );

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$values[]     = $args['limit'];
		$values[]     = $args['offset'];

		$sql = $wpdb->prepare(
			"SELECT * FROM {$this->table}
			WHERE {$where_clause}
			ORDER BY {$order_by} {$order}
			LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$values
		);

		$proposals = $wpdb->get_results( $sql );

		return array_map( array( $this, 'format_proposal' ), $proposals );
	}

	/**
	 * Check if vendor has already proposed to a request.
	 *
	 * @param int $request_id Request ID.
	 * @param int $vendor_id Vendor user ID.
	 * @return bool True if already proposed.
	 */
	public function vendor_has_proposed( int $request_id, int $vendor_id ): bool {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				WHERE request_id = %d AND vendor_id = %d AND status != %s",
				$request_id,
				$vendor_id,
				self::STATUS_WITHDRAWN
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Get vendor proposal for a request.
	 *
	 * @param int $request_id Request ID.
	 * @param int $vendor_id Vendor user ID.
	 * @return object|null Proposal object or null.
	 */
	public function get_vendor_proposal( int $request_id, int $vendor_id ): ?object {
		global $wpdb;

		$proposal = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE request_id = %d AND vendor_id = %d AND status != %s
				LIMIT 1",
				$request_id,
				$vendor_id,
				self::STATUS_WITHDRAWN
			)
		);

		if ( $proposal ) {
			$proposal = $this->format_proposal( $proposal );
		}

		return $proposal;
	}

	/**
	 * Count proposals by vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array<string, int> Status counts.
	 */
	public function count_by_vendor( int $vendor_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count
				FROM {$this->table}
				WHERE vendor_id = %d
				GROUP BY status",
				$vendor_id
			)
		);

		$counts = array(
			self::STATUS_PENDING   => 0,
			self::STATUS_ACCEPTED  => 0,
			self::STATUS_REJECTED  => 0,
			self::STATUS_WITHDRAWN => 0,
			'total'                => 0,
		);

		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->count;
			$counts['total']       += (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Delete a proposal.
	 *
	 * @param int $proposal_id Proposal ID.
	 * @return bool True on success.
	 */
	public function delete( int $proposal_id ): bool {
		global $wpdb;

		$proposal = $this->get( $proposal_id );

		if ( ! $proposal ) {
			return false;
		}

		$result = $wpdb->delete(
			$this->table,
			array( 'id' => $proposal_id )
		);

		if ( $result ) {
			/**
			 * Fires when a proposal is deleted.
			 *
			 * @since 1.0.0
			 * @param int    $proposal_id Proposal ID.
			 * @param object $proposal    Proposal object.
			 */
			do_action( 'wpss_proposal_deleted', $proposal_id, $proposal );

			return true;
		}

		return false;
	}

	/**
	 * Get available statuses.
	 *
	 * @return array<string, string> Status slugs and labels.
	 */
	public static function get_statuses(): array {
		return array(
			self::STATUS_PENDING   => __( 'Pending', 'wp-sell-services' ),
			self::STATUS_ACCEPTED  => __( 'Accepted', 'wp-sell-services' ),
			self::STATUS_REJECTED  => __( 'Rejected', 'wp-sell-services' ),
			self::STATUS_WITHDRAWN => __( 'Withdrawn', 'wp-sell-services' ),
		);
	}
}
