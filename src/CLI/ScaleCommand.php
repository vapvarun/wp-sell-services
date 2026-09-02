<?php
/**
 * WP-CLI Scale Benchmark Command
 *
 * Seeds a production-shape dataset (10k vendors + their orders, wallet
 * transactions and withdrawals), times every hot-path query cold-cache against
 * per-query budgets, and tears the data back down. The benchmark is the gate
 * that proves the additive indexes shipped in {@see \WPSellServices\Database\SchemaManager}
 * keep the wallet ledger, earnings balance and order list queries inside budget
 * at scale.
 *
 * @package WPSellServices\CLI
 * @since   1.4.4
 */

declare(strict_types=1);

namespace WPSellServices\CLI;

defined( 'ABSPATH' ) || exit;

use WP_CLI;
use WP_CLI_Command;
use WPSellServices\Database\SchemaManager;

/**
 * Seed, benchmark and teardown a production-shape scale dataset.
 *
 * All seeded rows are tagged with a sentinel (`description` / `vendor_notes`
 * prefix and a high user_id offset) so teardown removes exactly what seed
 * created and never touches real marketplace data.
 *
 * ## EXAMPLES
 *
 *     # Seed 10k vendors with orders + wallet transactions
 *     $ wp wpss scale seed
 *
 *     # Time every hot-path query against its budget
 *     $ wp wpss scale bench
 *
 *     # Seed, bench, then teardown in one shot (CI gate)
 *     $ wp wpss scale bench --seed --teardown
 *
 *     # Remove all benchmark data
 *     $ wp wpss scale teardown --yes
 *
 * @since 1.4.4
 */
class ScaleCommand extends WP_CLI_Command {

	/**
	 * Sentinel written into seeded rows so teardown is exact and safe.
	 *
	 * @var string
	 */
	private const SENTINEL = 'wpss_scale_bench';

	/**
	 * User-id floor for synthetic vendors. Real WordPress users are extremely
	 * unlikely to reach this range, keeping seed/teardown isolated even when the
	 * sentinel column is unavailable on a given table.
	 *
	 * @var int
	 */
	private const UID_BASE = 9_000_000;

	/**
	 * Rows inserted per multi-row INSERT. Keeps each statement under the default
	 * max_allowed_packet while still amortising round-trips for 100k+ rows.
	 *
	 * @var int
	 */
	private const BATCH = 500;

	/**
	 * Per-query budgets in milliseconds (cold cache, single execution).
	 *
	 * Keys map to the labels emitted by {@see run_bench()}. Budgets are
	 * deliberately generous headroom over observed timings so the gate flags
	 * regressions (a dropped index, a new hot-path aggregation) rather than
	 * normal jitter.
	 *
	 * @var array<string, float>
	 */
	private const BUDGETS_MS = array(
		'wallet_ledger_page'  => 50.0,
		'wallet_ledger_count' => 50.0,
		'wallet_ledger_typed' => 50.0,
		'earnings_completed'  => 60.0,
		'earnings_withdrawn'  => 60.0,
		'orders_vendor_list'  => 60.0,
		'orders_status_list'  => 80.0,
	);

	/**
	 * WordPress database handle.
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Seed a production-shape benchmark dataset.
	 *
	 * ## OPTIONS
	 *
	 * [--vendors=<number>]
	 * : Number of synthetic vendors to create.
	 * ---
	 * default: 10000
	 * ---
	 *
	 * [--orders-per-vendor=<number>]
	 * : Orders to create per vendor (each completed order also yields one wallet
	 * transaction).
	 * ---
	 * default: 12
	 * ---
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * [--force]
	 * : Seed on a production site (wp_get_environment_type() === 'production').
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp wpss scale seed --vendors=10000
	 *
	 * @subcommand seed
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function seed( array $args, array $assoc_args ): void {
		$vendors = max( 1, (int) ( $assoc_args['vendors'] ?? 10000 ) );
		$per     = max( 1, (int) ( $assoc_args['orders-per-vendor'] ?? 12 ) );

		Guard::writes( "benchmark orders across {$vendors} synthetic vendors, plus ledger rows", $vendors * $per, $assoc_args );

		WP_CLI::log( 'Ensuring schema/indexes are current...' );
		( new SchemaManager() )->sync();

		WP_CLI::log( sprintf( 'Seeding %d vendors x %d orders...', $vendors, $per ) );
		$summary = $this->run_seed( $vendors, $per );

		WP_CLI::success(
			sprintf(
				'Seeded %d vendors, %d orders, %d wallet transactions, %d withdrawals.',
				$summary['vendors'],
				$summary['orders'],
				$summary['wallet_transactions'],
				$summary['withdrawals']
			)
		);
	}

	/**
	 * Benchmark every hot-path query against its per-query budget.
	 *
	 * Each query runs with SQL_NO_CACHE so the timing reflects a cold result
	 * cache. Any query exceeding its budget fails the command (non-zero exit),
	 * which is what makes this a CI gate.
	 *
	 * ## OPTIONS
	 *
	 * [--seed]
	 * : Seed the dataset before benching (uses seed defaults).
	 *
	 * [--teardown]
	 * : Remove the dataset after benching.
	 *
	 * [--yes]
	 * : Skip the seed confirmation prompt (with --seed).
	 *
	 * [--force]
	 * : Seed on a production site (with --seed).
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp wpss scale bench
	 *     $ wp wpss scale bench --seed --teardown --format=json
	 *
	 * @subcommand bench
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function bench( array $args, array $assoc_args ): void {
		$do_seed     = isset( $assoc_args['seed'] );
		$do_teardown = isset( $assoc_args['teardown'] );
		$format      = (string) ( $assoc_args['format'] ?? 'table' );

		if ( $do_seed ) {
			$this->seed( array(), $assoc_args );
		} else {
			// Bench still needs the indexes present; sync is idempotent.
			( new SchemaManager() )->sync();
		}

		$rows   = $this->run_bench();
		$failed = 0;

		$display = array();
		foreach ( $rows as $row ) {
			if ( ! $row['within_budget'] ) {
				++$failed;
			}
			$display[] = array(
				'Query'      => $row['label'],
				'Time (ms)'  => number_format( $row['ms'], 2 ),
				'Budget'     => number_format( $row['budget_ms'], 2 ),
				'Rows'       => $row['rows'],
				'Uses Index' => $row['uses_index'] ? 'yes' : 'NO',
				'Result'     => $row['within_budget'] ? 'PASS' : 'OVER',
			);
		}

		WP_CLI\Utils\format_items(
			$format,
			$display,
			array( 'Query', 'Time (ms)', 'Budget', 'Rows', 'Uses Index', 'Result' )
		);

		if ( $do_teardown ) {
			$this->teardown( array(), array( 'yes' => true ) );
		}

		if ( $failed > 0 ) {
			WP_CLI::error( sprintf( '%d hot-path query(ies) exceeded budget.', $failed ) );
		}

		WP_CLI::success( 'All hot-path queries within budget.' );
	}

	/**
	 * Remove all benchmark data.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp wpss scale teardown --yes
	 *
	 * @subcommand teardown
	 *
	 * @param array<int, string>   $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function teardown( array $args, array $assoc_args ): void {
		WP_CLI::confirm( 'Delete all scale-benchmark data?', $assoc_args );

		$deleted = $this->run_teardown();

		WP_CLI::success(
			sprintf(
				'Removed %d vendor profiles, %d orders, %d wallet transactions, %d withdrawals.',
				$deleted['vendors'],
				$deleted['orders'],
				$deleted['wallet_transactions'],
				$deleted['withdrawals']
			)
		);
	}

	/**
	 * Seed the dataset using batched multi-row INSERTs.
	 *
	 * @param int $vendors Number of vendors.
	 * @param int $per     Orders per vendor.
	 * @return array{vendors:int, orders:int, wallet_transactions:int, withdrawals:int}
	 */
	private function run_seed( int $vendors, int $per ): array {
		$vp_table  = $this->wpdb->prefix . 'wpss_vendor_profiles';
		$ord_table = $this->wpdb->prefix . 'wpss_orders';
		$txn_table = $this->wpdb->prefix . 'wpss_wallet_transactions';
		$wd_table  = $this->wpdb->prefix . 'wpss_withdrawals';

		$totals = array(
			'vendors'             => 0,
			'orders'              => 0,
			'wallet_transactions' => 0,
			'withdrawals'         => 0,
		);

		$progress = \WP_CLI\Utils\make_progress_bar( 'Seeding vendors', $vendors );

		$vp_rows  = array();
		$ord_rows = array();
		$txn_rows = array();
		$wd_rows  = array();

		for ( $i = 0; $i < $vendors; $i++ ) {
			$uid     = self::UID_BASE + $i;
			$balance = 0.0;

			$vp_rows[] = $this->wpdb->prepare(
				'(%d, %s, %s, %d, %d, %f, %f, %s)',
				$uid,
				'Bench Vendor ' . $i,
				'active',
				$per,
				$per,
				0,
				0,
				self::SENTINEL
			);

			for ( $o = 0; $o < $per; $o++ ) {
				$is_completed = ( $o % 3 ) !== 0; // ~2/3 completed.
				$total        = 50.0 + ( $o * 7 );
				$earnings     = round( $total * 0.8, 2 );
				$status       = $is_completed ? 'completed' : 'in_progress';
				$days_ago     = ( $o * 3 ) % 365;
				$created      = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days_ago} days" ) );
				$order_num    = sprintf( 'BENCH-%d-%d', $uid, $o );

				$ord_rows[] = $this->wpdb->prepare(
					'(%s, %d, %d, %d, %f, %f, %f, %s, %s, %s)',
					$order_num,
					self::UID_BASE,
					$uid,
					( $i % 50 ) + 1,
					$total,
					$total,
					$earnings,
					$status,
					self::SENTINEL,
					$created
				);

				if ( $is_completed ) {
					$balance   += $earnings;
					$txn_rows[] = $this->wpdb->prepare(
						'(%d, %s, %f, %f, %s, %s, %s, %s)',
						$uid,
						'earning',
						$earnings,
						$balance,
						'order',
						self::SENTINEL,
						'completed',
						$created
					);
					++$totals['wallet_transactions'];
				}

				++$totals['orders'];
			}

			// One withdrawal per vendor.
			$wd_rows[] = $this->wpdb->prepare(
				'(%d, %f, %s, %s, %s)',
				$uid,
				round( $balance * 0.25, 2 ),
				'paypal',
				'completed',
				self::SENTINEL
			);
			++$totals['withdrawals'];
			++$totals['vendors'];

			$this->flush_if_full( $vp_rows, $vp_table, '(user_id, display_name, status, total_orders, completed_orders, total_earnings, net_earnings, vacation_message)' );
			$this->flush_if_full( $ord_rows, $ord_table, '(order_number, customer_id, vendor_id, service_id, subtotal, total, vendor_earnings, status, vendor_notes, created_at)' );
			$this->flush_if_full( $txn_rows, $txn_table, '(user_id, type, amount, balance_after, reference_type, description, status, created_at)' );
			$this->flush_if_full( $wd_rows, $wd_table, '(vendor_id, amount, method, status, admin_note)' );

			$progress->tick();
		}

		// Final flush of any partial batches.
		$this->flush( $vp_rows, $vp_table, '(user_id, display_name, status, total_orders, completed_orders, total_earnings, net_earnings, vacation_message)' );
		$this->flush( $ord_rows, $ord_table, '(order_number, customer_id, vendor_id, service_id, subtotal, total, vendor_earnings, status, vendor_notes, created_at)' );
		$this->flush( $txn_rows, $txn_table, '(user_id, type, amount, balance_after, reference_type, description, status, created_at)' );
		$this->flush( $wd_rows, $wd_table, '(vendor_id, amount, method, status, admin_note)' );

		$progress->finish();

		return $totals;
	}

	/**
	 * Flush a value-row buffer when it reaches the batch size.
	 *
	 * @param array<int, string> $rows    Buffer of prepared `(...)` value tuples (by reference).
	 * @param string             $table   Fully-qualified table name.
	 * @param string             $columns Column list including the wrapping parentheses.
	 * @return void
	 */
	private function flush_if_full( array &$rows, string $table, string $columns ): void {
		if ( count( $rows ) >= self::BATCH ) {
			$this->flush( $rows, $table, $columns );
		}
	}

	/**
	 * Execute a multi-row INSERT for the buffered value tuples and clear it.
	 *
	 * @param array<int, string> $rows    Buffer of prepared `(...)` value tuples (by reference).
	 * @param string             $table   Fully-qualified table name.
	 * @param string             $columns Column list including the wrapping parentheses.
	 * @return void
	 */
	private function flush( array &$rows, string $table, string $columns ): void {
		if ( empty( $rows ) ) {
			return;
		}

		$values = implode( ',', $rows );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is internal; each value tuple is individually $wpdb->prepare()d before buffering.
		$this->wpdb->query( "INSERT INTO {$table} {$columns} VALUES {$values}" );
		$rows = array();
	}

	/**
	 * Run every hot-path query with timing + EXPLAIN index verification.
	 *
	 * @return array<int, array{label:string, ms:float, budget_ms:float, rows:int, uses_index:bool, within_budget:bool}>
	 */
	private function run_bench(): array {
		$ord_table = $this->wpdb->prefix . 'wpss_orders';
		$txn_table = $this->wpdb->prefix . 'wpss_wallet_transactions';
		$wd_table  = $this->wpdb->prefix . 'wpss_withdrawals';
		$uid       = self::UID_BASE; // First seeded vendor (or any vendor for shape).

		$queries = array(
			'wallet_ledger_page'  => $this->wpdb->prepare(
				"SELECT SQL_NO_CACHE id, type, amount, balance_after, currency, description, reference_type, reference_id, status, created_at
				FROM {$txn_table}
				WHERE user_id = %d
				ORDER BY created_at DESC, id DESC
				LIMIT 20 OFFSET 0",
				$uid
			),
			'wallet_ledger_count' => $this->wpdb->prepare(
				"SELECT SQL_NO_CACHE COUNT(*) FROM {$txn_table} WHERE user_id = %d",
				$uid
			),
			'wallet_ledger_typed' => $this->wpdb->prepare(
				"SELECT SQL_NO_CACHE id, amount, created_at
				FROM {$txn_table}
				WHERE user_id = %d AND type = %s
				ORDER BY created_at DESC, id DESC
				LIMIT 20",
				$uid,
				'earning'
			),
			'earnings_completed'  => $this->wpdb->prepare(
				"SELECT SQL_NO_CACHE COALESCE(SUM(COALESCE(vendor_earnings, 0)), 0)
				FROM {$ord_table}
				WHERE vendor_id = %d AND status = %s",
				$uid,
				'completed'
			),
			'earnings_withdrawn'  => $this->wpdb->prepare(
				"SELECT SQL_NO_CACHE COALESCE(SUM(amount), 0)
				FROM {$wd_table}
				WHERE vendor_id = %d AND status IN (%s, %s, %s)",
				$uid,
				'pending',
				'approved',
				'completed'
			),
			'orders_vendor_list'  => $this->wpdb->prepare(
				"SELECT SQL_NO_CACHE id, order_number, total, status, created_at
				FROM {$ord_table}
				WHERE vendor_id = %d
				ORDER BY created_at DESC
				LIMIT 20",
				$uid
			),
			'orders_status_list'  => $this->wpdb->prepare(
				"SELECT SQL_NO_CACHE id, order_number, vendor_id, total, created_at
				FROM {$ord_table}
				WHERE status = %s
				ORDER BY created_at DESC
				LIMIT 20",
				'completed'
			),
		);

		$results = array();

		foreach ( $queries as $label => $sql ) {
			$uses_index = $this->uses_index( $sql );

			$start = microtime( true );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $sql already prepared; read-only benchmark query.
			$rows = $this->wpdb->get_results( $sql );
			$ms   = ( microtime( true ) - $start ) * 1000;

			$budget = self::BUDGETS_MS[ $label ];

			$results[] = array(
				'label'         => $label,
				'ms'            => $ms,
				'budget_ms'     => $budget,
				'rows'          => is_array( $rows ) ? count( $rows ) : 0,
				'uses_index'    => $uses_index,
				'within_budget' => $ms <= $budget,
			);
		}

		return $results;
	}

	/**
	 * Verify a query uses an index (not a full table scan) via EXPLAIN.
	 *
	 * Returns false only when EXPLAIN reports `type = ALL` with no `key`, which
	 * is the unambiguous full-scan signal. This is advisory context in the
	 * bench output, surfacing a dropped/ineffective index even when timing on a
	 * warm box still happens to pass.
	 *
	 * @param string $sql Prepared SQL statement.
	 * @return bool
	 */
	private function uses_index( string $sql ): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- read-only EXPLAIN of an already-prepared statement.
		$explain = $this->wpdb->get_results( 'EXPLAIN ' . $sql, ARRAY_A );

		if ( empty( $explain ) || ! is_array( $explain ) ) {
			return false;
		}

		foreach ( $explain as $row ) {
			$type = strtoupper( (string) ( $row['type'] ?? '' ) );
			$key  = $row['key'] ?? null;
			if ( 'ALL' === $type && empty( $key ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Delete every benchmark row by sentinel + synthetic user-id range.
	 *
	 * @return array{vendors:int, orders:int, wallet_transactions:int, withdrawals:int}
	 */
	private function run_teardown(): array {
		$vp_table  = $this->wpdb->prefix . 'wpss_vendor_profiles';
		$ord_table = $this->wpdb->prefix . 'wpss_orders';
		$txn_table = $this->wpdb->prefix . 'wpss_wallet_transactions';
		$wd_table  = $this->wpdb->prefix . 'wpss_withdrawals';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table names; user_id bound via prepare.
		$vendors     = (int) $this->wpdb->query(
			$this->wpdb->prepare( "DELETE FROM {$vp_table} WHERE user_id >= %d AND vacation_message = %s", self::UID_BASE, self::SENTINEL )
		);
		$orders      = (int) $this->wpdb->query(
			$this->wpdb->prepare( "DELETE FROM {$ord_table} WHERE vendor_id >= %d AND vendor_notes = %s", self::UID_BASE, self::SENTINEL )
		);
		$txns        = (int) $this->wpdb->query(
			$this->wpdb->prepare( "DELETE FROM {$txn_table} WHERE user_id >= %d AND description = %s", self::UID_BASE, self::SENTINEL )
		);
		$withdrawals = (int) $this->wpdb->query(
			$this->wpdb->prepare( "DELETE FROM {$wd_table} WHERE vendor_id >= %d AND admin_note = %s", self::UID_BASE, self::SENTINEL )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'vendors'             => $vendors,
			'orders'              => $orders,
			'wallet_transactions' => $txns,
			'withdrawals'         => $withdrawals,
		);
	}
}
