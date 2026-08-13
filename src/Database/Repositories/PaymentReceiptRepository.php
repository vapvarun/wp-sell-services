<?php
/**
 * Payment Receipt Repository
 *
 * Database operations for offline payment proof.
 *
 * @package WPSellServices\Database\Repositories
 * @since   1.6.0
 */

namespace WPSellServices\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * PaymentReceiptRepository class.
 *
 * @since 1.6.0
 */
class PaymentReceiptRepository extends AbstractRepository {

	/**
	 * Allowed columns for ordering and filtering.
	 *
	 * @var array<string>
	 */
	protected array $allowed_columns = array(
		'id',
		'order_id',
		'uploaded_by',
		'status',
		'verified_by',
		'verified_at',
		'created_at',
	);

	/**
	 * Get the table name.
	 *
	 * @return string Table name.
	 */
	protected function get_table_name(): string {
		return $this->table_name( 'payment_receipts' );
	}

	/**
	 * Receipts attached to an order, newest first.
	 *
	 * @since 1.6.0
	 *
	 * @param int $order_id Order ID.
	 * @return array<int, object> Receipt rows.
	 */
	public function get_for_order( int $order_id ): array {
		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE order_id = %d ORDER BY id DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * The receipt currently awaiting a decision on an order, if any.
	 *
	 * @since 1.6.0
	 *
	 * @param int $order_id Order ID.
	 * @return object|null Receipt row.
	 */
	public function get_pending_for_order( int $order_id ): ?object {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE order_id = %d AND status = 'submitted' ORDER BY id DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);

		return $row ?: null;
	}

	/**
	 * Count receipts by status, for the admin queue badge.
	 *
	 * A COUNT(*), never count( get_all() ) - the queue on a busy marketplace is
	 * the whole point of this being a table.
	 *
	 * @since 1.6.0
	 *
	 * @param string $status Status to count.
	 * @return int Number of receipts.
	 */
	public function count_by_status( string $status = 'submitted' ): int {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE status = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status
			)
		);
	}

	/**
	 * Claim a submitted receipt for review, atomically.
	 *
	 * Verifying a receipt releases money to a vendor, so two admins clicking
	 * Approve at the same moment must not both succeed. This moves the row out
	 * of `submitted` in a single conditional UPDATE and reports whether THIS
	 * caller was the one that moved it - the same shape
	 * EarningsService::mark_paid() uses for withdrawals, and the reason a
	 * receipt is a row rather than a blob of order meta.
	 *
	 * @since 1.6.0
	 *
	 * @param int    $receipt_id  Receipt ID.
	 * @param string $status      New status ('verified' or 'rejected').
	 * @param int    $reviewer_id Admin user ID.
	 * @param string $admin_note  Optional note shown to the buyer.
	 * @return bool True when this call transitioned the row; false if someone got there first.
	 */
	public function claim_for_review( int $receipt_id, string $status, int $reviewer_id, string $admin_note = '' ): bool {
		$updated = $this->wpdb->query(
			$this->wpdb->prepare(
				"UPDATE {$this->table}
				 SET status = %s, verified_by = %d, verified_at = %s, admin_note = %s
				 WHERE id = %d AND status = 'submitted'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$status,
				$reviewer_id,
				current_time( 'mysql' ),
				$admin_note,
				$receipt_id
			)
		);

		return (int) $updated === 1;
	}
}
