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
	 * Contract types — vendor picks at proposal time.
	 *
	 * 'fixed'     = single payment, single delivery (existing flow).
	 * 'milestone' = phased plan; on acceptance the milestones are pre-created
	 *               as pending_payment sub-orders the buyer pays in lock-step.
	 */
	public const CONTRACT_TYPE_FIXED     = 'fixed';
	public const CONTRACT_TYPE_MILESTONE = 'milestone';

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
		'proposed_price',
		'proposed_days',
		'status',
		'created_at',
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
		if ( empty( $data['description'] ) ) {
			return false;
		}

		// Contract type defaults to 'fixed'. Milestone proposals derive their
		// total price + total days from the milestone breakdown server-side
		// so the client cannot lie about the sum.
		$contract_type = isset( $data['contract_type'] ) && self::CONTRACT_TYPE_MILESTONE === $data['contract_type']
			? self::CONTRACT_TYPE_MILESTONE
			: self::CONTRACT_TYPE_FIXED;

		$milestones_json = null;
		$proposed_price  = 0.0;
		$proposed_days   = 0;

		if ( self::CONTRACT_TYPE_MILESTONE === $contract_type ) {
			$milestones = $this->normalize_milestones( $data['milestones'] ?? array() );
			if ( empty( $milestones ) ) {
				return false;
			}
			$milestones_json = wp_json_encode( $milestones );
			$proposed_price  = (float) array_sum( array_column( $milestones, 'amount' ) );
			$proposed_days   = (int) array_sum( array_column( $milestones, 'days' ) );
		} else {
			if ( empty( $data['price'] ) ) {
				return false;
			}
			$proposed_price = (float) $data['price'];
			$proposed_days  = (int) ( $data['delivery_days'] ?? $request->delivery_days );
		}

		$proposal_data = array(
			'request_id'     => $request_id,
			'vendor_id'      => $vendor_id,
			'cover_letter'   => sanitize_textarea_field( $data['description'] ),
			'proposed_price' => $proposed_price,
			'proposed_days'  => $proposed_days,
			'contract_type'  => $contract_type,
			'milestones'     => $milestones_json,
			'status'         => self::STATUS_PENDING,
			'attachments'    => isset( $data['attachments'] ) ? wp_json_encode( $data['attachments'] ) : null,
			'created_at'     => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $this->table, $proposal_data );

		if ( $result ) {
			$proposal_id = (int) $wpdb->insert_id;

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
		$proposal->proposed_price = (float) $proposal->proposed_price;
		$proposal->proposed_days  = (int) $proposal->proposed_days;
		$proposal->attachments    = $proposal->attachments ? json_decode( $proposal->attachments, true ) : array();

		// Normalize new contract fields so callers can rely on a stable shape.
		$proposal->contract_type = $proposal->contract_type ?? self::CONTRACT_TYPE_FIXED;
		if ( self::CONTRACT_TYPE_MILESTONE !== $proposal->contract_type ) {
			$proposal->contract_type = self::CONTRACT_TYPE_FIXED;
		}
		$proposal->milestones = ! empty( $proposal->milestones ) && is_string( $proposal->milestones )
			? ( json_decode( $proposal->milestones, true ) ?: array() )
			: ( is_array( $proposal->milestones ?? null ) ? $proposal->milestones : array() );

		return $proposal;
	}

	/**
	 * Validate + normalize a vendor-submitted milestone breakdown.
	 *
	 * Rejects rows missing a title or with a non-positive amount — the buyer
	 * needs to see a real plan, not zero-amount placeholders. Returns a
	 * clean indexed array of associative entries:
	 *   [
	 *     [ 'title' => string, 'description' => string, 'deliverables' => string,
	 *       'amount' => float, 'days' => int ],
	 *     ...
	 *   ]
	 *
	 * Empty input → empty output. Caller decides whether to reject.
	 *
	 * @param mixed $raw Whatever the form / REST handler passed in.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_milestones( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$title = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';
			$amount = isset( $row['amount'] ) ? (float) $row['amount'] : 0.0;
			if ( '' === $title || $amount <= 0 ) {
				continue;
			}
			$days = isset( $row['days'] ) ? max( 0, (int) $row['days'] ) : 0;
			$out[] = array(
				'title'        => $title,
				'description'  => isset( $row['description'] ) ? sanitize_textarea_field( (string) $row['description'] ) : '',
				'deliverables' => isset( $row['deliverables'] ) ? sanitize_textarea_field( (string) $row['deliverables'] ) : '',
				'amount'       => $amount,
				'days'         => $days,
			);
		}

		return $out;
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

		$update_data = array();

		if ( isset( $data['description'] ) ) {
			$update_data['cover_letter'] = sanitize_textarea_field( $data['description'] );
		}

		// Contract type can be flipped while still pending. Switching from
		// milestone -> fixed clears the milestones JSON and uses the
		// supplied price / days; the reverse derives them from the new
		// breakdown the same way submit() does.
		if ( isset( $data['contract_type'] ) ) {
			$update_data['contract_type'] = self::CONTRACT_TYPE_MILESTONE === $data['contract_type']
				? self::CONTRACT_TYPE_MILESTONE
				: self::CONTRACT_TYPE_FIXED;
		}

		$effective_contract = $update_data['contract_type'] ?? $proposal->contract_type ?? self::CONTRACT_TYPE_FIXED;

		if ( self::CONTRACT_TYPE_MILESTONE === $effective_contract ) {
			if ( isset( $data['milestones'] ) ) {
				$milestones = $this->normalize_milestones( $data['milestones'] );
				if ( empty( $milestones ) ) {
					return false;
				}
				$update_data['milestones']     = wp_json_encode( $milestones );
				$update_data['proposed_price'] = (float) array_sum( array_column( $milestones, 'amount' ) );
				$update_data['proposed_days']  = (int) array_sum( array_column( $milestones, 'days' ) );
			}
		} else {
			// Fixed: clear any previous milestones and accept manual price / days.
			if ( isset( $data['contract_type'] ) ) {
				$update_data['milestones'] = null;
			}
			if ( isset( $data['price'] ) ) {
				$update_data['proposed_price'] = (float) $data['price'];
			}
			if ( isset( $data['delivery_days'] ) ) {
				$update_data['proposed_days'] = (int) $data['delivery_days'];
			}
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
	 * @deprecated 1.0.1 Use {@see BuyerRequestService::convert_to_order()} instead.
	 *
	 * The live accept flow creates a pending_payment order via
	 * BuyerRequestService::convert_to_order(), which is what the REST API
	 * (BuyerRequestsController::accept_proposal) calls. This method previously
	 * only updated the proposal status without creating an order, leaving the
	 * buyer-request flow incomplete. It is retained as a thin delegate so any
	 * external code (Pro plugin hooks, custom integrations) continues to work.
	 *
	 * @param int $proposal_id Proposal ID.
	 * @param int $buyer_id    Buyer user ID (must own the request).
	 * @return bool True on success.
	 */
	public function accept( int $proposal_id, int $buyer_id ): bool {
		_deprecated_function(
			__METHOD__,
			'1.0.1',
			'BuyerRequestService::convert_to_order()'
		);

		$proposal = $this->get( $proposal_id );

		if ( ! $proposal ) {
			return false;
		}

		// Verify buyer owns the request before delegating.
		$request_service = new BuyerRequestService();
		$request         = $request_service->get( $proposal->request_id );

		if ( ! $request || (int) $request->author_id !== $buyer_id ) {
			return false;
		}

		$result = $request_service->convert_to_order( (int) $proposal->request_id, $proposal_id );

		return ! empty( $result['success'] );
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

		$result = $wpdb->update(
			$this->table,
			array( 'status' => self::STATUS_REJECTED ),
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

		if ( ! $proposal || (int) $proposal->vendor_id !== $vendor_id ) {
			return false;
		}

		if ( $proposal->status !== self::STATUS_PENDING ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table,
			array( 'status' => self::STATUS_WITHDRAWN ),
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
			array( 'status' => $status ),
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
			array( 'status' => self::STATUS_REJECTED ),
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
