<?php
/**
 * Earnings Service
 *
 * Handles vendor earnings, withdrawals, and payout tracking.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\ServiceOrder;

/**
 * Manages vendor earnings and payouts.
 *
 * @since 1.0.0
 */
class EarningsService {

	/**
	 * Withdrawal statuses.
	 */
	public const WITHDRAWAL_PENDING   = 'pending';
	public const WITHDRAWAL_APPROVED  = 'approved';
	public const WITHDRAWAL_COMPLETED = 'completed';
	public const WITHDRAWAL_REJECTED  = 'rejected';

	/**
	 * Earnings grouping periods (consumed by get_by_period() and the REST
	 * `period` filter). Canonical source for both.
	 */
	public const PERIOD_DAY   = 'day';
	public const PERIOD_WEEK  = 'week';
	public const PERIOD_MONTH = 'month';
	public const PERIOD_YEAR  = 'year';

	/**
	 * Get vendor earnings summary.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array Earnings summary.
	 */
	public function get_summary( int $vendor_id ): array {
		global $wpdb;
		$orders_table      = $wpdb->prefix . 'wpss_orders';
		$withdrawals_table = $wpdb->prefix . 'wpss_withdrawals';

		// Earnings credited less than clearance_days ago are not yet available
		// for withdrawal. One authority for the value (see get_clearance_days).
		$clearance_days = self::get_clearance_days();

		// THE WALLET LEDGER IS THE AUTHORITY FOR EARNED MONEY.
		//
		// This used to derive earnings from wpss_orders (SUM of vendor_earnings
		// over completed orders) while the wallet UI derived them from
		// wpss_wallet_transactions. The two could not agree: any ledger entry
		// with no matching order row — a manual adjustment, a tip credit, an
		// order reversal — moved one number and not the other, so a vendor was
		// shown one balance and gated on a different one.
		//
		// wpss_get_ledger_balance() is now the single formula (see the free
		// manifest's money_authorities section). It is net of withdrawals:
		// completed withdrawals are debited into the ledger by
		// record_withdrawal_debit(), so they must NOT be subtracted again below.
		$ledger_balance = wpss_get_ledger_balance( $vendor_id );

		// Order count is presentational only — it labels the earnings card and
		// never feeds the balance, so it may still come from the orders table.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$completed = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) as order_count
				FROM {$orders_table}
				WHERE vendor_id = %d AND status = %s",
				$vendor_id,
				ServiceOrder::STATUS_COMPLETED
			)
		);

		// Gross lifetime credits, for display. Debit rows (withdrawal / debit /
		// dispute_refund) are excluded so this reads as "money ever earned"
		// rather than a running balance.
		$txn_table   = $wpdb->prefix . 'wpss_wallet_transactions';
		$debit_types = wpss_get_ledger_debit_types_sql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_earned = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$txn_table}
				WHERE user_id = %d AND status = 'completed'
				AND type NOT IN ({$debit_types})",
				$vendor_id
			)
		);

		// Credits still inside the clearance window are not withdrawable yet.
		// Derived from the ledger (not from orders' completed_at) so that tips,
		// milestones and extension credits observe clearance too — the
		// orders-derived version silently exempted all three.
		//
		// `amount > 0` is essential, not decorative. Reversal rows
		// ('order_reversal') are NOT in the debit-types list — they carry a
		// NEGATIVE amount instead — so without this guard a reversal landing
		// inside the clearance window would be summed as a clearance "credit",
		// and available = ledger - in_clearance would ADD the debt straight
		// back. A vendor refunded on already-withdrawn money would read $0.00
		// owed instead of -$90.00.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$in_clearance = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$txn_table}
				WHERE user_id = %d AND status = 'completed'
				AND type NOT IN ({$debit_types})
				AND amount > 0
				AND created_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
				$vendor_id,
				$clearance_days
			)
		);

		// Get pending earnings (orders in progress) — show vendor's expected share after commission.
		// Use CommissionService::get_global_commission_rate() for consistency with actual commission calculation.
		$commission_rate = CommissionService::get_global_commission_rate();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(
					CASE WHEN vendor_earnings IS NOT NULL THEN vendor_earnings
					ELSE total * (1 - %f / 100) END
				), 0) FROM {$orders_table}
				WHERE vendor_id = %d AND status IN (%s, %s, %s)",
				$commission_rate,
				$vendor_id,
				ServiceOrder::STATUS_IN_PROGRESS,
				ServiceOrder::STATUS_PENDING_APPROVAL,
				ServiceOrder::STATUS_REVISION_REQUESTED
			)
		);

		// Get withdrawn amount.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawn = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$withdrawals_table}
				WHERE vendor_id = %d AND status = %s",
				$vendor_id,
				self::WITHDRAWAL_COMPLETED
			)
		);

		// Get pending withdrawals.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending_withdrawal = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$withdrawals_table}
				WHERE vendor_id = %d AND status IN (%s, %s)",
				$vendor_id,
				self::WITHDRAWAL_PENDING,
				self::WITHDRAWAL_APPROVED
			)
		);

		$withdrawn          = (float) $withdrawn;
		$pending_withdrawal = (float) $pending_withdrawal;

		// $ledger_balance is ALREADY net of completed withdrawals — subtracting
		// $withdrawn here would debit them twice. Only still-open requests
		// (pending / approved) need reserving, because those have not reached
		// the ledger yet.
		// NOT clamped to zero. A refund reverses the vendor's earnings, and if
		// they had already withdrawn that money the balance goes NEGATIVE —
		// which is the correct record of a debt to the platform. Because the
		// balance is a SUM over the ledger, future earnings pay it down
		// automatically with no separate collection machinery.
		//
		// Clamping hid the debt and let the vendor start earning again as if
		// nothing had happened, so the platform silently ate every refund on
		// already-withdrawn money.
		$available = $ledger_balance - $pending_withdrawal - $in_clearance;

		return array(
			'total_earned'       => $total_earned,
			'ledger_balance'     => $ledger_balance,
			'available_balance'  => $available,
			'pending_clearance'  => (float) $pending + $in_clearance,
			'in_clearance'       => $in_clearance,
			'withdrawn'          => $withdrawn,
			'pending_withdrawal' => $pending_withdrawal,
			'completed_orders'   => (int) $completed->order_count,
		);
	}

	/**
	 * Classify a vendor's balance so every surface renders the same states.
	 *
	 * Three templates need to answer "is this vendor in debt?" — the earnings
	 * card, the withdrawal form and the admin vendors list. Deriving it in each
	 * of them is how they drift apart, so it is derived once here.
	 *
	 * @since 1.2.3
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return string One of 'positive', 'zero' or 'negative'.
	 */
	public function get_balance_state( int $vendor_id ): string {
		return self::classify_balance( $this->get_summary( $vendor_id )['available_balance'] );
	}

	/**
	 * Classify an already-known balance figure.
	 *
	 * Same rule as {@see get_balance_state()} without the extra query, for
	 * callers that already hold a summary.
	 *
	 * @since 1.2.3
	 *
	 * @param float $available Available balance.
	 * @return string One of 'positive', 'zero' or 'negative'.
	 */
	public static function classify_balance( float $available ): string {
		// Sub-cent noise is not a debt. Rounding keeps a -0.004 float from
		// rendering as "You owe $0.00".
		$available = round( $available, 2 );

		if ( $available < 0 ) {
			return 'negative';
		}

		return $available > 0 ? 'positive' : 'zero';
	}

	/**
	 * Backfill ledger debits for withdrawals completed before 1.2.3.
	 *
	 * The balance is now derived from the wallet ledger. On installs where
	 * nothing ever wrote a withdrawal debit (any free-only site — the debit
	 * used to live in Pro), every past payout is missing from the ledger, so
	 * each vendor's balance reads high by the total they have already been
	 * paid. Left unreconciled they could withdraw that money a second time.
	 *
	 * Idempotent, so it is safe to run repeatedly and safe on paired installs
	 * where Pro already wrote the rows: record_withdrawal_debit() matches on
	 * (reference_type, reference_id) and skips anything already present.
	 *
	 * @return array{scanned:int, written:int} Reconciliation counts.
	 */
	public function backfill_withdrawal_debits(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_withdrawals';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY id ASC",
				self::WITHDRAWAL_COMPLETED
			)
		);

		$written = 0;

		foreach ( $withdrawals as $withdrawal ) {
			if ( $this->record_withdrawal_debit( (int) $withdrawal->id, self::WITHDRAWAL_COMPLETED, $withdrawal ) ) {
				++$written;
			}
		}

		if ( $written > 0 ) {
			wpss_log(
				sprintf(
					'Wallet ledger reconciliation: wrote %1$d missing withdrawal debit(s) across %2$d completed withdrawal(s).',
					$written,
					count( $withdrawals )
				)
			);
		}

		return array(
			'scanned' => count( $withdrawals ),
			'written' => $written,
		);
	}

	/**
	 * Debit the wallet ledger when a withdrawal completes.
	 *
	 * The ledger is the authority for the vendor balance, so a payout that
	 * never reaches it leaves the vendor's balance permanently inflated. This
	 * lives in FREE deliberately: it used to exist only in Pro
	 * (Integrations\Wallets\WalletManager), which meant a free-only site paid
	 * vendors out without ever debiting them, and the same install grew a
	 * different balance depending on whether Pro happened to be active.
	 *
	 * Idempotent on (reference_type, reference_id) rather than on transaction
	 * type, so the debit rows Pro already wrote as type 'debit' on existing
	 * installs are recognised and never doubled.
	 *
	 * @param int                         $withdrawal_id Withdrawal record ID.
	 * @param string                      $status        New withdrawal status.
	 * @param object|array<string, mixed> $withdrawal    Withdrawal row.
	 * @return bool True when a debit row was written.
	 */
	public function record_withdrawal_debit( int $withdrawal_id, string $status, $withdrawal ): bool {
		global $wpdb;

		if ( self::WITHDRAWAL_COMPLETED !== $status ) {
			return false;
		}

		$wpdb->query( 'START TRANSACTION' );

		$result = $this->insert_withdrawal_debit( $withdrawal_id, (object) $withdrawal );

		if ( 'failed' === $result ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$wpdb->query( 'COMMIT' );

		return 'written' === $result;
	}

	/**
	 * Write the ledger debit row for a completed withdrawal — transactionless core.
	 *
	 * The caller MUST hold an open transaction: the idempotency check and the
	 * balance read (FOR UPDATE) are only race-safe under one. mark_paid() runs
	 * this inside the same transaction as the status flip, so a completed
	 * withdrawal without its debit can never be persisted;
	 * record_withdrawal_debit() wraps it for the hook/backfill callers.
	 *
	 * @param int    $withdrawal_id Withdrawal record ID.
	 * @param object $withdrawal    Withdrawal row.
	 * @return string 'written' when the debit row was inserted, 'skipped' when it
	 *                already exists (or the row is invalid), 'failed' on DB error
	 *                — the caller must roll back on 'failed'.
	 */
	private function insert_withdrawal_debit( int $withdrawal_id, object $withdrawal ): string {
		global $wpdb;

		$vendor_id = (int) ( $withdrawal->vendor_id ?? 0 );
		$amount    = (float) ( $withdrawal->amount ?? 0 );

		if ( $vendor_id <= 0 || $amount <= 0 ) {
			return 'skipped';
		}

		$txn_table = $wpdb->prefix . 'wpss_wallet_transactions';

		// Re-check under the row lock taken by wpss_get_ledger_balance(), so two
		// concurrent "mark paid" clicks cannot both write a debit.
		$current_balance = (float) wpss_get_ledger_balance( $vendor_id, true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$already = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$txn_table}
				WHERE user_id = %d AND reference_type = 'withdrawal' AND reference_id = %d",
				$vendor_id,
				$withdrawal_id
			)
		);

		if ( $already > 0 ) {
			return 'skipped';
		}

		// Amount is stored POSITIVE; the sign is applied on read by
		// wpss_get_ledger_balance(), which treats 'withdrawal' as a debit.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$txn_table,
			array(
				'user_id'        => $vendor_id,
				'type'           => 'withdrawal',
				'amount'         => $amount,
				'balance_after'  => $current_balance - $amount,
				'currency'       => $withdrawal->currency ?? wpss_get_currency(),
				'description'    => sprintf(
					/* translators: %d: withdrawal ID */
					__( 'Withdrawal #%d completed', 'wp-sell-services' ),
					$withdrawal_id
				),
				'reference_type' => 'withdrawal',
				'reference_id'   => $withdrawal_id,
				'status'         => 'completed',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 'failed';
		}

		return 'written';
	}

	/**
	 * Mark a withdrawal as paid — THE terminal step of every payout rail.
	 *
	 * Manual admin clicks, bulk actions, the REST controller and (later) the
	 * Stripe/PayPal rails all finish a payout here, so no rail keeps its own
	 * bookkeeping (MONEY-FLOW.md rule 2.3). The status flip and the ledger
	 * debit commit in ONE transaction: a completed withdrawal without its
	 * debit can never be observed or persisted.
	 *
	 * Idempotent: the row lock serialises concurrent calls, and the loser
	 * re-reads a completed row and is refused — marking twice debits once.
	 *
	 * @since 1.3.0
	 *
	 * @param int    $withdrawal_id Withdrawal record ID.
	 * @param string $note          Optional admin note.
	 * @return array{success: bool, message: string, code?: string} Result.
	 */
	public function mark_paid( int $withdrawal_id, string $note = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		$wpdb->query( 'START TRANSACTION' );

		// Row lock FIRST: two admins will click "Mark paid" at the same time.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawal = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d FOR UPDATE", $withdrawal_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $withdrawal ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'Withdrawal not found.', 'wp-sell-services' ),
				'code'    => 'not_found',
			);
		}

		if ( self::WITHDRAWAL_PENDING !== $withdrawal->status && self::WITHDRAWAL_APPROVED !== $withdrawal->status ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'This withdrawal has already been finalised and can no longer be changed.', 'wp-sell-services' ),
				'code'    => 'already_finalised',
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$table,
			array(
				'status'       => self::WITHDRAWAL_COMPLETED,
				'admin_note'   => sanitize_textarea_field( $note ),
				'processed_at' => current_time( 'mysql' ),
				'processed_by' => get_current_user_id(),
			),
			array( 'id' => $withdrawal_id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'Failed to update withdrawal.', 'wp-sell-services' ),
				'code'    => 'update_failed',
			);
		}

		$debit = $this->insert_withdrawal_debit( $withdrawal_id, $withdrawal );

		if ( 'failed' === $debit ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'The wallet ledger debit could not be written, so the withdrawal was left unchanged.', 'wp-sell-services' ),
				'code'    => 'debit_failed',
			);
		}

		$wpdb->query( 'COMMIT' );

		// Side effects only after commit — the money record is already safe.
		// The wpss_withdrawal_processed listener re-runs record_withdrawal_debit,
		// which finds the row we just wrote and skips (idempotent by design).
		$this->notify_withdrawal_processed( $withdrawal_id, self::WITHDRAWAL_COMPLETED, $withdrawal );

		/** This action is documented in process_withdrawal(). */
		do_action( 'wpss_withdrawal_processed', $withdrawal_id, self::WITHDRAWAL_COMPLETED, $withdrawal );

		return array(
			'success' => true,
			'message' => __( 'Withdrawal marked as paid.', 'wp-sell-services' ),
		);
	}

	/**
	 * Notify the vendor that their withdrawal changed state.
	 *
	 * Shared by mark_paid() and process_withdrawal() so the message and the
	 * notification type stay identical across every path.
	 *
	 * @param int    $withdrawal_id Withdrawal record ID.
	 * @param string $status        New withdrawal status.
	 * @param object $withdrawal    Withdrawal row (pre-transition).
	 * @return void
	 */
	private function notify_withdrawal_processed( int $withdrawal_id, string $status, object $withdrawal ): void {
		$notification_service = new NotificationService();
		$status_labels        = self::get_withdrawal_statuses();

		$notification_service->create(
			(int) $withdrawal->vendor_id,
			'withdrawal_' . $status,
			__( 'Withdrawal Update', 'wp-sell-services' ),
			sprintf(
				/* translators: 1: amount, 2: status */
				__( 'Your withdrawal request for %1$s has been %2$s.', 'wp-sell-services' ),
				wpss_format_price( (float) $withdrawal->amount ),
				strtolower( $status_labels[ $status ] ?? $status )
			),
			array( 'withdrawal_id' => $withdrawal_id )
		);
	}

	/**
	 * Get earnings history.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return array Earnings records.
	 */
	public function get_history( int $vendor_id, array $args = array() ): array {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';

		$defaults = array(
			'limit'      => 20,
			'offset'     => 0,
			'start_date' => '',
			'end_date'   => '',
			'status'     => ServiceOrder::STATUS_COMPLETED,
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'vendor_id = %d' );
		$params = array( $vendor_id );

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( $args['start_date'] ) {
			$where[]  = 'completed_at >= %s';
			$params[] = $args['start_date'];
		}

		if ( $args['end_date'] ) {
			$where[]  = 'completed_at <= %s';
			$params[] = $args['end_date'];
		}

		$where_clause = implode( ' AND ', $where );
		$params[]     = $args['limit'];
		$params[]     = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, order_number, service_id, total, vendor_earnings, commission_rate, platform_fee, status, completed_at, created_at
				FROM {$orders_table}
				WHERE {$where_clause}
				ORDER BY completed_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params
			)
		);

		return array_map(
			function ( $order ) {
				$service = get_post( $order->service_id );
				// Use vendor_earnings when available (commission calculated), fall back to total for older orders.
				$amount = null !== $order->vendor_earnings ? (float) $order->vendor_earnings : (float) $order->total;
				return array(
					'id'              => (int) $order->id,
					'order_number'    => $order->order_number,
					'service_name'    => $service ? $service->post_title : __( 'Deleted Service', 'wp-sell-services' ),
					'amount'          => $amount,
					'order_total'     => (float) $order->total,
					'commission_rate' => null !== $order->commission_rate ? (float) $order->commission_rate : null,
					'platform_fee'    => null !== $order->platform_fee ? (float) $order->platform_fee : null,
					'status'          => $order->status,
					'date'            => $order->completed_at ?: $order->created_at,
				);
			},
			$orders
		);
	}

	/**
	 * Get earnings by period.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param string $period    Period (day, week, month, year).
	 * @param int    $count     Number of periods.
	 * @return array Earnings by period.
	 */
	public function get_by_period( int $vendor_id, string $period = self::PERIOD_MONTH, int $count = 12 ): array {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';

		$format = match ( $period ) {
			self::PERIOD_DAY   => '%Y-%m-%d',
			self::PERIOD_WEEK  => '%Y-%u',
			self::PERIOD_MONTH => '%Y-%m',
			self::PERIOD_YEAR  => '%Y',
			default            => '%Y-%m',
		};

		$interval = match ( $period ) {
			self::PERIOD_DAY   => 'DAY',
			self::PERIOD_WEEK  => 'WEEK',
			self::PERIOD_MONTH => 'MONTH',
			self::PERIOD_YEAR  => 'YEAR',
			default            => 'MONTH',
		};

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE_FORMAT(completed_at, %s) as period,
					COUNT(*) as orders,
					COALESCE(SUM(COALESCE(vendor_earnings, 0)), 0) as earnings
				FROM {$orders_table}
				WHERE vendor_id = %d
				AND status = %s
				AND completed_at >= DATE_SUB(NOW(), INTERVAL %d {$interval})
				GROUP BY period
				ORDER BY period DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$format,
				$vendor_id,
				ServiceOrder::STATUS_COMPLETED,
				$count
			)
		);

		return array_map(
			function ( $row ) {
				return array(
					'period'   => $row->period,
					'orders'   => (int) $row->orders,
					'earnings' => (float) $row->earnings,
				);
			},
			$results
		);
	}

	/**
	 * Request withdrawal.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param float  $amount    Amount to withdraw.
	 * @param string $method    Withdrawal method.
	 * @param array  $details   Method details (account info).
	 * @return array Result with success status.
	 */
	public function request_withdrawal( int $vendor_id, float $amount, string $method, array $details = array() ): array {
		// Check minimum withdrawal.
		$min_withdrawal = self::get_min_withdrawal_amount();
		if ( $amount < $min_withdrawal ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: minimum withdrawal amount */
					__( 'Minimum withdrawal amount is %s.', 'wp-sell-services' ),
					wpss_format_price( $min_withdrawal )
				),
			);
		}

		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		// Lock vendor's pending withdrawals to prevent double-withdrawal race conditions.
		$wpdb->query( 'START TRANSACTION' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table} WHERE vendor_id = %d AND status = 'pending' FOR UPDATE",
				$vendor_id
			)
		);

		$summary = $this->get_summary( $vendor_id );

		// Check available balance.
		if ( $amount > $summary['available_balance'] ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'Insufficient balance for this withdrawal.', 'wp-sell-services' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'vendor_id'  => $vendor_id,
				'amount'     => $amount,
				'method'     => sanitize_key( $method ),
				'details'    => wp_json_encode( $details ),
				'status'     => self::WITHDRAWAL_PENDING,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'Failed to create withdrawal request.', 'wp-sell-services' ),
			);
		}

		$wpdb->query( 'COMMIT' );

		$withdrawal_id = (int) $wpdb->insert_id;

		// Notify admin (respects email settings).
		( new EmailService() )->send_withdrawal_notification( $vendor_id, $amount, $withdrawal_id );

		/**
		 * Fires when withdrawal is requested.
		 *
		 * @param int   $withdrawal_id Withdrawal ID.
		 * @param int   $vendor_id     Vendor user ID.
		 * @param float $amount        Amount.
		 */
		do_action( 'wpss_withdrawal_requested', $withdrawal_id, $vendor_id, $amount );

		return array(
			'success'       => true,
			'message'       => __( 'Withdrawal request submitted successfully.', 'wp-sell-services' ),
			'withdrawal_id' => $withdrawal_id,
		);
	}

	/**
	 * Get vendor withdrawals.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return array Withdrawals.
	 */
	public function get_withdrawals( int $vendor_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
			'status' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( 'vendor_id = %d' );
		$params = array( $vendor_id );

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$params[]     = $args['limit'];
		$params[]     = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE {$where_clause}
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params
			)
		);

		return array_map(
			function ( $row ) {
				return array(
					'id'           => (int) $row->id,
					'amount'       => (float) $row->amount,
					'method'       => $row->method,
					// Cast: the column is nullable, and under strict_types a NULL here
					// is a TypeError that fatals the whole earnings page.
					'details'      => json_decode( (string) ( $row->details ?? '' ), true ) ?: array(),
					'status'       => $row->status,
					'admin_note'   => $row->admin_note ?? '',
					'processed_at' => $row->processed_at,
					'created_at'   => $row->created_at,
				);
			},
			$withdrawals
		);
	}

	/**
	 * Process withdrawal (admin).
	 *
	 * @param int    $withdrawal_id Withdrawal ID.
	 * @param string $status        New status.
	 * @param string $note          Admin note.
	 * @return array Result.
	 */
	public function process_withdrawal( int $withdrawal_id, string $status, string $note = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		$valid_statuses = array( self::WITHDRAWAL_APPROVED, self::WITHDRAWAL_COMPLETED, self::WITHDRAWAL_REJECTED );
		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid status.', 'wp-sell-services' ),
				'code'    => 'invalid_status',
			);
		}

		// Completing IS paying — every payout terminates in mark_paid(), which
		// flips the status and writes the ledger debit in one transaction.
		if ( self::WITHDRAWAL_COMPLETED === $status ) {
			return $this->mark_paid( $withdrawal_id, $note );
		}

		$wpdb->query( 'START TRANSACTION' );

		// Row lock: two admins acting on the same request at once serialise
		// here, and the loser is refused by the terminal guard below.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawal = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d FOR UPDATE", $withdrawal_id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		if ( ! $withdrawal ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'Withdrawal not found.', 'wp-sell-services' ),
				'code'    => 'not_found',
			);
		}

		// Terminal-state guard. Only a still-open request (pending/approved) may
		// be moved. Once a withdrawal is completed or rejected its amount is
		// already reconciled in the balance calculation; re-processing it (e.g.
		// rejecting a completed payout) would re-inflate available_balance and
		// pay the vendor twice.
		if ( self::WITHDRAWAL_PENDING !== $withdrawal->status && self::WITHDRAWAL_APPROVED !== $withdrawal->status ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'This withdrawal has already been finalised and can no longer be changed.', 'wp-sell-services' ),
				'code'    => 'already_finalised',
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array(
				'status'       => $status,
				'admin_note'   => sanitize_textarea_field( $note ),
				'processed_at' => current_time( 'mysql' ),
				'processed_by' => get_current_user_id(),
			),
			array( 'id' => $withdrawal_id ),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);

		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'message' => __( 'Failed to update withdrawal.', 'wp-sell-services' ),
				'code'    => 'update_failed',
			);
		}

		$wpdb->query( 'COMMIT' );

		$this->notify_withdrawal_processed( $withdrawal_id, $status, $withdrawal );

		/**
		 * Fires when withdrawal is processed.
		 *
		 * @param int    $withdrawal_id Withdrawal ID.
		 * @param string $status        New status.
		 * @param object $withdrawal    Withdrawal data.
		 */
		do_action( 'wpss_withdrawal_processed', $withdrawal_id, $status, $withdrawal );

		return array(
			'success' => true,
			'message' => __( 'Withdrawal updated successfully.', 'wp-sell-services' ),
		);
	}

	/**
	 * Get all pending withdrawals (admin).
	 *
	 * @param array $args Query arguments.
	 * @return array Withdrawals.
	 */
	public function get_all_withdrawals( array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
			'status' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		$where_clause = implode( ' AND ', $where );
		$params[]     = $args['limit'];
		$params[]     = $args['offset'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$withdrawals = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT w.*, u.display_name as vendor_name
				FROM {$table} w
				LEFT JOIN {$wpdb->users} u ON w.vendor_id = u.ID
				WHERE {$where_clause}
				ORDER BY w.created_at DESC
				LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$params
			)
		);

		return array_map(
			function ( $row ) {
				return array(
					'id'           => (int) $row->id,
					'vendor_id'    => (int) $row->vendor_id,
					'vendor_name'  => $row->vendor_name,
					'amount'       => (float) $row->amount,
					'method'       => $row->method,
					// Cast: the column is nullable, and under strict_types a NULL here
					// is a TypeError that fatals the whole earnings page.
					'details'      => json_decode( (string) ( $row->details ?? '' ), true ) ?: array(),
					'status'       => $row->status,
					'admin_note'   => $row->admin_note ?? '',
					'processed_at' => $row->processed_at,
					'created_at'   => $row->created_at,
				);
			},
			$withdrawals
		);
	}

	/**
	 * Get withdrawal methods.
	 *
	 * @return array Available methods.
	 */
	public static function get_withdrawal_methods(): array {
		$methods = array(
			'paypal'        => __( 'PayPal', 'wp-sell-services' ),
			'bank_transfer' => __( 'Bank Transfer', 'wp-sell-services' ),
		);

		/**
		 * Filter withdrawal methods.
		 *
		 * @param array $methods Available methods.
		 */
		return apply_filters( 'wpss_withdrawal_methods', $methods );
	}

	/**
	 * Get withdrawal statuses.
	 *
	 * @return array Status labels.
	 */
	public static function get_withdrawal_statuses(): array {
		return array(
			self::WITHDRAWAL_PENDING   => __( 'Pending', 'wp-sell-services' ),
			self::WITHDRAWAL_APPROVED  => __( 'Approved', 'wp-sell-services' ),
			self::WITHDRAWAL_COMPLETED => __( 'Completed', 'wp-sell-services' ),
			self::WITHDRAWAL_REJECTED  => __( 'Rejected', 'wp-sell-services' ),
		);
	}

	/**
	 * Terminal/processed states an admin may transition a withdrawal into.
	 *
	 * Excludes the initial `pending` state (a request can never be moved back
	 * to pending). Canonical source for the REST process-withdrawal `status`
	 * action enum.
	 *
	 * @return array<int, string> Status keys.
	 */
	public static function get_processable_withdrawal_statuses(): array {
		return array(
			self::WITHDRAWAL_APPROVED,
			self::WITHDRAWAL_REJECTED,
			self::WITHDRAWAL_COMPLETED,
		);
	}

	/**
	 * Earnings grouping periods, keyed by period slug.
	 *
	 * Canonical source for the REST `period` filter and get_by_period().
	 *
	 * @return array<string, string> Period labels.
	 */
	public static function get_periods(): array {
		return array(
			self::PERIOD_DAY   => __( 'Day', 'wp-sell-services' ),
			self::PERIOD_WEEK  => __( 'Week', 'wp-sell-services' ),
			self::PERIOD_MONTH => __( 'Month', 'wp-sell-services' ),
			self::PERIOD_YEAR  => __( 'Year', 'wp-sell-services' ),
		);
	}

	/**
	 * Get minimum withdrawal amount from settings.
	 *
	 * Centralized helper to get the min withdrawal amount from the correct option.
	 *
	 * @return float Minimum withdrawal amount.
	 */
	public static function get_min_withdrawal_amount(): float {
		// Primary location: wpss_payouts (new structure).
		$payouts_settings = get_option( 'wpss_payouts', array() );
		if ( isset( $payouts_settings['min_withdrawal'] ) ) {
			return (float) $payouts_settings['min_withdrawal'];
		}

		// Fallback: wpss_vendor (old structure for backward compatibility).
		$vendor_settings = get_option( 'wpss_vendor', array() );
		if ( isset( $vendor_settings['min_payout_amount'] ) ) {
			return (float) $vendor_settings['min_payout_amount'];
		}

		// Default.
		return 50.0;
	}

	/**
	 * Get the clearance period in days — THE authority for the payout hold.
	 *
	 * How long a vendor's credited earnings are held before they can be
	 * withdrawn. **Defaults to 0: no hold.** How long to sit on someone else's
	 * money is a business-policy call for the site owner, not a rail the plugin
	 * imposes — plenty of marketplaces pay out the moment an order completes.
	 *
	 * Zero is safe in this plugin specifically because the wallet ledger is the
	 * balance authority and {@see get_summary()} deliberately does NOT clamp the
	 * balance at zero: a refund landing after a payout drives the vendor
	 * negative, which is an honest record of a debt that future earnings pay
	 * down on their own. Owners who would rather never have that conversation
	 * set a refund window (7 / 14 / 30) in Settings → Payouts.
	 *
	 * Read through this helper rather than the raw option so the default can
	 * never disagree between the settings field, the activator and runtime —
	 * that drift is how "saved but not applied" bugs start.
	 *
	 * @since 1.3.0
	 *
	 * @return int Days to hold earnings. 0 means no hold.
	 */
	public static function get_clearance_days(): int {
		$payouts_settings = get_option( 'wpss_payouts', array() );

		return max( 0, (int) ( $payouts_settings['clearance_days'] ?? 0 ) );
	}

	/**
	 * Check if auto withdrawal is enabled.
	 *
	 * @return bool True if auto withdrawal is enabled.
	 */
	public static function is_auto_withdrawal_enabled(): bool {
		$payouts_settings = get_option( 'wpss_payouts', array() );
		return ! empty( $payouts_settings['auto_withdrawal_enabled'] );
	}

	/**
	 * Get auto withdrawal threshold.
	 *
	 * @return float Threshold amount.
	 */
	public static function get_auto_withdrawal_threshold(): float {
		$payouts_settings = get_option( 'wpss_payouts', array() );
		return (float) ( $payouts_settings['auto_withdrawal_threshold'] ?? 500 );
	}

	/**
	 * Get auto withdrawal schedule.
	 *
	 * @return string Schedule (weekly, biweekly, or monthly).
	 */
	public static function get_auto_withdrawal_schedule(): string {
		$payouts_settings = get_option( 'wpss_payouts', array() );
		$schedule         = $payouts_settings['auto_withdrawal_schedule'] ?? 'monthly';
		return in_array( $schedule, array( 'weekly', 'biweekly', 'monthly' ), true ) ? $schedule : 'monthly';
	}

	/**
	 * Get vendors eligible for auto withdrawal.
	 *
	 * Returns vendors whose available balance exceeds the threshold.
	 *
	 * @return array Array of vendor data with id and balance.
	 */
	public function get_eligible_vendors_for_auto_withdrawal(): array {
		global $wpdb;
		$orders_table      = $wpdb->prefix . 'wpss_orders';
		$withdrawals_table = $wpdb->prefix . 'wpss_withdrawals';

		$threshold = self::get_auto_withdrawal_threshold();

		// Get all vendors with completed orders.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$vendors = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT vendor_id FROM {$orders_table} WHERE status = %s",
				ServiceOrder::STATUS_COMPLETED
			)
		);

		$eligible = array();

		foreach ( $vendors as $vendor_id ) {
			$summary = $this->get_summary( (int) $vendor_id );

			if ( $summary['available_balance'] >= $threshold ) {
				// Check if vendor has payout method configured.
				$payout_method  = get_user_meta( (int) $vendor_id, 'wpss_payout_method', true );
				$payout_details = get_user_meta( (int) $vendor_id, 'wpss_payout_details', true );

				if ( $payout_method && $payout_details ) {
					$eligible[] = array(
						'vendor_id'         => (int) $vendor_id,
						'available_balance' => $summary['available_balance'],
						'payout_method'     => $payout_method,
						'payout_details'    => is_array( $payout_details ) ? $payout_details : array(),
					);
				}
			}
		}

		return $eligible;
	}

	/**
	 * Process automatic withdrawals.
	 *
	 * Creates withdrawal requests for eligible vendors.
	 *
	 * @return array Processing results.
	 */
	public function process_auto_withdrawals(): array {
		if ( ! self::is_auto_withdrawal_enabled() ) {
			return array(
				'success'   => false,
				'message'   => __( 'Auto withdrawal is not enabled.', 'wp-sell-services' ),
				'processed' => 0,
			);
		}

		$eligible = $this->get_eligible_vendors_for_auto_withdrawal();

		if ( empty( $eligible ) ) {
			return array(
				'success'   => true,
				'message'   => __( 'No vendors eligible for auto withdrawal.', 'wp-sell-services' ),
				'processed' => 0,
			);
		}

		$processed = 0;
		$failed    = 0;
		$results   = array();

		foreach ( $eligible as $vendor ) {
			// Create automatic withdrawal request.
			$result = $this->create_auto_withdrawal(
				$vendor['vendor_id'],
				$vendor['available_balance'],
				$vendor['payout_method'],
				$vendor['payout_details']
			);

			if ( $result['success'] ) {
				++$processed;
			} else {
				++$failed;
			}

			$results[] = array(
				'vendor_id' => $vendor['vendor_id'],
				'amount'    => $vendor['available_balance'],
				'success'   => $result['success'],
				'message'   => $result['message'],
			);
		}

		// Log the auto withdrawal run.
		update_option(
			'wpss_last_auto_withdrawal_run',
			array(
				'timestamp' => current_time( 'mysql' ),
				'processed' => $processed,
				'failed'    => $failed,
			)
		);

		return array(
			'success'   => true,
			'message'   => sprintf(
				/* translators: 1: processed count, 2: failed count */
				__( 'Auto withdrawal completed. Processed: %1$d, Failed: %2$d.', 'wp-sell-services' ),
				$processed,
				$failed
			),
			'processed' => $processed,
			'failed'    => $failed,
			'details'   => $results,
		);
	}

	/**
	 * Create automatic withdrawal request.
	 *
	 * @param int    $vendor_id Vendor user ID.
	 * @param float  $amount    Amount to withdraw.
	 * @param string $method    Withdrawal method.
	 * @param array  $details   Method details (account info).
	 * @return array Result with success status.
	 */
	private function create_auto_withdrawal( int $vendor_id, float $amount, string $method, array $details = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_withdrawals';

		// Check for any pending auto withdrawal for this vendor.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE vendor_id = %d AND status IN (%s, %s) AND is_auto = 1",
				$vendor_id,
				self::WITHDRAWAL_PENDING,
				self::WITHDRAWAL_APPROVED
			)
		);

		if ( $existing > 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Vendor already has a pending auto withdrawal.', 'wp-sell-services' ),
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			array(
				'vendor_id'  => $vendor_id,
				'amount'     => $amount,
				'method'     => sanitize_key( $method ),
				'details'    => wp_json_encode( $details ),
				'status'     => self::WITHDRAWAL_PENDING,
				'is_auto'    => 1,
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%f', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( ! $result ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create auto withdrawal request.', 'wp-sell-services' ),
			);
		}

		$withdrawal_id = (int) $wpdb->insert_id;

		// Notify vendor.
		$notification_service = new NotificationService();
		$notification_service->create(
			$vendor_id,
			'auto_withdrawal_created',
			__( 'Auto Withdrawal Created', 'wp-sell-services' ),
			sprintf(
				/* translators: %s: amount */
				__( 'An automatic withdrawal of %s has been scheduled based on your payout settings.', 'wp-sell-services' ),
				wpss_format_price( $amount )
			),
			array( 'withdrawal_id' => $withdrawal_id )
		);

		// Notify admin (respects email settings).
		( new EmailService() )->send_withdrawal_notification( $vendor_id, $amount, $withdrawal_id, true );

		/**
		 * Fires when auto withdrawal is created.
		 *
		 * @param int   $withdrawal_id Withdrawal ID.
		 * @param int   $vendor_id     Vendor user ID.
		 * @param float $amount        Amount.
		 */
		do_action( 'wpss_auto_withdrawal_created', $withdrawal_id, $vendor_id, $amount );

		return array(
			'success'       => true,
			'message'       => __( 'Auto withdrawal request created successfully.', 'wp-sell-services' ),
			'withdrawal_id' => $withdrawal_id,
		);
	}

	/**
	 * Register custom cron schedules for auto withdrawals.
	 *
	 * Must be called before wp_schedule_event() uses 'biweekly'.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Modified schedules.
	 */
	public static function add_cron_schedules( array $schedules ): array {
		$schedules['biweekly'] = array(
			'interval' => 14 * DAY_IN_SECONDS,
			'display'  => __( 'Every 14 Days (Bi-weekly)', 'wp-sell-services' ),
		);
		return $schedules;
	}

	/**
	 * Schedule auto withdrawal cron job.
	 *
	 * @return void
	 */
	public static function schedule_auto_withdrawal_cron(): void {
		$hook = 'wpss_process_auto_withdrawals';

		if ( ! self::is_auto_withdrawal_enabled() ) {
			Scheduler::unschedule_all( $hook );
			return;
		}

		$schedule         = self::get_auto_withdrawal_schedule();
		$desired_interval = self::schedule_interval( $schedule );
		$stored_schedule  = (string) get_option( 'wpss_auto_withdrawal_schedule_type', '' );

		// Reschedule only when the admin-selected schedule changes — AS
		// recurring actions persist across requests so we don't want to
		// cancel-and-reinsert on every bootstrap.
		if ( $stored_schedule === $schedule && Scheduler::has_pending( $hook ) ) {
			return;
		}

		Scheduler::unschedule_all( $hook );
		\as_schedule_recurring_action(
			self::get_next_schedule_time( $schedule ),
			$desired_interval,
			$hook,
			array(),
			Scheduler::GROUP_FREE
		);
		update_option( 'wpss_auto_withdrawal_schedule_type', $schedule, false );
	}

	/**
	 * Map the admin-selected cadence to the AS interval in seconds.
	 *
	 * @param string $schedule Cadence identifier: weekly | biweekly | monthly.
	 * @return int Interval seconds.
	 */
	private static function schedule_interval( string $schedule ): int {
		return match ( $schedule ) {
			'monthly'  => 30 * DAY_IN_SECONDS,
			'biweekly' => 14 * DAY_IN_SECONDS,
			default    => WEEK_IN_SECONDS,
		};
	}

	/**
	 * Get next schedule time for auto withdrawal.
	 *
	 * @param string $schedule Schedule type (weekly, biweekly, or monthly).
	 * @return int Unix timestamp for next run.
	 */
	private static function get_next_schedule_time( string $schedule ): int {
		$timezone = wp_timezone();
		$now      = new \DateTime( 'now', $timezone );

		if ( 'monthly' === $schedule ) {
			// First day of next month at 2 AM.
			$next = new \DateTime( 'first day of next month 02:00:00', $timezone );
		} elseif ( 'biweekly' === $schedule ) {
			// Next 1st or 15th of the month at 2 AM.
			$day = (int) $now->format( 'j' );
			if ( $day < 15 ) {
				$next = new \DateTime( $now->format( 'Y-m' ) . '-15 02:00:00', $timezone );
			} else {
				$next = new \DateTime( 'first day of next month 02:00:00', $timezone );
			}
		} else {
			// Next Monday at 2 AM.
			$next = new \DateTime( 'next monday 02:00:00', $timezone );
		}

		return $next->getTimestamp();
	}

	/**
	 * Unschedule auto withdrawal cron job.
	 *
	 * @return void
	 */
	public static function unschedule_auto_withdrawal_cron(): void {
		Scheduler::unschedule_all( 'wpss_process_auto_withdrawals' );
		delete_option( 'wpss_auto_withdrawal_schedule_type' );
		// Also clear any WP-Cron residue from pre-1.1.0.
		wp_clear_scheduled_hook( 'wpss_process_auto_withdrawals' );
	}
}
