<?php
/**
 * Proposal Repository
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Database\Repositories;

use WPSellServices\Models\Proposal;

/**
 * Repository for proposal data access.
 *
 * @since 1.0.0
 */
class ProposalRepository extends AbstractRepository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	protected function get_table_name(): string {
		return 'wpss_proposals';
	}

	/**
	 * Find proposal by ID.
	 *
	 * @param int $id Proposal ID.
	 * @return Proposal|null
	 */
	public function find( int $id ): ?Proposal {
		$row = $this->find_by_id( $id );

		return $row ? Proposal::from_row( $row ) : null;
	}

	/**
	 * Find proposals for a buyer request.
	 *
	 * @param int    $request_id Request ID.
	 * @param string $status     Status filter (optional).
	 * @return Proposal[]
	 */
	public function find_by_request( int $request_id, string $status = '' ): array {
		global $wpdb;

		$where = $wpdb->prepare( 'request_id = %d', $request_id );

		if ( $status ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			"SELECT * FROM {$this->table} WHERE {$where} ORDER BY created_at DESC"
		);

		return array_map( [ Proposal::class, 'from_row' ], $results );
	}

	/**
	 * Find proposals by vendor.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param string $status    Status filter (optional).
	 * @param int    $limit     Limit.
	 * @param int    $offset    Offset.
	 * @return Proposal[]
	 */
	public function find_by_vendor( int $vendor_id, string $status = '', int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		$where = $wpdb->prepare( 'vendor_id = %d', $vendor_id );

		if ( $status ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		return array_map( [ Proposal::class, 'from_row' ], $results );
	}

	/**
	 * Check if vendor already submitted proposal for request.
	 *
	 * @param int $vendor_id  Vendor user ID.
	 * @param int $request_id Request ID.
	 * @return bool
	 */
	public function vendor_has_proposed( int $vendor_id, int $request_id ): bool {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				WHERE vendor_id = %d AND request_id = %d AND status != 'withdrawn'",
				$vendor_id,
				$request_id
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Create a proposal.
	 *
	 * @param array $data Proposal data.
	 * @return int|false Proposal ID or false on failure.
	 */
	public function create( array $data ) {
		$defaults = [
			'status'     => 'pending',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		];

		$data = wp_parse_args( $data, $defaults );

		return $this->insert( $data );
	}

	/**
	 * Update proposal status.
	 *
	 * @param int    $id     Proposal ID.
	 * @param string $status New status.
	 * @param string $reason Reason (for rejection/withdrawal).
	 * @return bool
	 */
	public function update_status( int $id, string $status, string $reason = '' ): bool {
		$data = [
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( 'rejected' === $status && $reason ) {
			$data['rejection_reason'] = $reason;
		}

		if ( 'withdrawn' === $status && $reason ) {
			$data['withdrawal_reason'] = $reason;
		}

		return $this->update( $id, $data );
	}

	/**
	 * Accept proposal and link to order.
	 *
	 * @param int $id       Proposal ID.
	 * @param int $order_id Created order ID.
	 * @return bool
	 */
	public function accept( int $id, int $order_id ): bool {
		return $this->update(
			$id,
			[
				'status'     => 'accepted',
				'order_id'   => $order_id,
				'updated_at' => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Count proposals for a request.
	 *
	 * @param int $request_id Request ID.
	 * @return int
	 */
	public function count_for_request( int $request_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table}
				WHERE request_id = %d AND status != 'withdrawn'",
				$request_id
			)
		);
	}

	/**
	 * Count proposals by vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array Status counts.
	 */
	public function count_by_status_for_vendor( int $vendor_id ): array {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count FROM {$this->table}
				WHERE vendor_id = %d
				GROUP BY status",
				$vendor_id
			)
		);

		$counts = [];
		foreach ( $results as $row ) {
			$counts[ $row->status ] = (int) $row->count;
		}

		return $counts;
	}

	/**
	 * Get vendor proposal statistics.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array
	 */
	public function get_vendor_stats( int $vendor_id ): array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total,
					SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
					SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
					SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
					AVG(CASE WHEN status = 'accepted' THEN price ELSE NULL END) as avg_accepted_price
				FROM {$this->table}
				WHERE vendor_id = %d",
				$vendor_id
			)
		);

		return [
			'total'              => (int) $row->total,
			'pending'            => (int) $row->pending,
			'accepted'           => (int) $row->accepted,
			'rejected'           => (int) $row->rejected,
			'acceptance_rate'    => $row->total > 0 ? round( ( $row->accepted / $row->total ) * 100, 1 ) : 0,
			'avg_accepted_price' => $row->avg_accepted_price ? (float) $row->avg_accepted_price : 0,
		];
	}

	/**
	 * Get lowest proposal for a request.
	 *
	 * @param int $request_id Request ID.
	 * @return Proposal|null
	 */
	public function get_lowest_price_proposal( int $request_id ): ?Proposal {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table}
				WHERE request_id = %d AND status = 'pending'
				ORDER BY price ASC
				LIMIT 1",
				$request_id
			)
		);

		return $row ? Proposal::from_row( $row ) : null;
	}

	/**
	 * Delete proposals for a request.
	 *
	 * @param int $request_id Request ID.
	 * @return int Number deleted.
	 */
	public function delete_for_request( int $request_id ): int {
		global $wpdb;

		return (int) $wpdb->delete(
			$this->table,
			[ 'request_id' => $request_id ],
			[ '%d' ]
		);
	}
}
