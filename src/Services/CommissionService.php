<?php
/**
 * Commission Service
 *
 * Handles commission calculation and distribution for vendor earnings.
 *
 * @package WPSellServices\Services
 * @since   1.3.0
 */

declare(strict_types=1);


namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Models\ServiceOrder;

/**
 * Manages platform commission calculation and tracking.
 *
 * @since 1.3.0
 */
class CommissionService {

	/**
	 * Calculate commission for an order.
	 *
	 * @param int $order_id Order ID.
	 * @return array{
	 *     order_total: float,
	 *     commission_rate: float,
	 *     platform_fee: float,
	 *     vendor_earnings: float
	 * }|null Commission breakdown or null if order not found.
	 */
	public function calculate( int $order_id ): ?array {
		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return null;
		}

		// Use pre-tax base (subtotal + addons) for commission calculation.
		$commission_base = (float) $order->subtotal + (float) $order->addons_total;

		/**
		 * Filters the base amount used for commission calculation.
		 *
		 * Allows adjusting the amount before the commission percentage is applied.
		 *
		 * @since 1.4.0
		 *
		 * @param float $commission_base Base amount (subtotal + addons, pre-tax).
		 * @param int   $order_id        Order ID.
		 * @param int   $vendor_id       Vendor user ID.
		 */
		$commission_base = (float) apply_filters( 'wpss_commission_base_amount', $commission_base, $order_id, $order->vendor_id );

		$commission_rate = $this->get_commission_rate( $order );
		$platform_fee    = round( $commission_base * ( $commission_rate / 100 ), 2 );
		$vendor_earnings = round( $commission_base - $platform_fee, 2 );

		return array(
			'order_total'     => $commission_base,
			'commission_rate' => $commission_rate,
			'platform_fee'    => $platform_fee,
			'vendor_earnings' => $vendor_earnings,
		);
	}

	/**
	 * Record commission for a completed order.
	 *
	 * Should be called when order transitions to 'completed' status.
	 *
	 * @param int $order_id Order ID.
	 * @return bool True on success, false on failure.
	 */
	public function record( int $order_id ): bool {
		global $wpdb;

		$order = wpss_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		// Skip if commission already recorded (check for existing wallet transaction).
		$transactions_table = $wpdb->prefix . 'wpss_wallet_transactions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$transactions_table} WHERE reference_id = %d AND reference_type = 'order' AND type = 'order_earning'",
				$order_id
			)
		);

		if ( $existing ) {
			return true;
		}

		$commission = $this->calculate( $order_id );

		if ( ! $commission ) {
			return false;
		}

		$orders_table = $wpdb->prefix . 'wpss_orders';

		// Update order with commission breakdown.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$orders_table,
			array(
				'commission_rate' => $commission['commission_rate'],
				'platform_fee'    => $commission['platform_fee'],
				'vendor_earnings' => $commission['vendor_earnings'],
			),
			array( 'id' => $order_id ),
			array( '%f', '%f', '%f' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		// Update vendor profile earnings.
		$this->update_vendor_earnings( $order->vendor_id, $commission );

		// Create wallet transaction for vendor earnings.
		$this->create_earnings_transaction( $order_id, $order->vendor_id, $commission );

		/**
		 * Fires when commission is recorded for an order.
		 *
		 * @param int   $order_id   Order ID.
		 * @param array $commission Commission breakdown.
		 * @param int   $vendor_id  Vendor user ID.
		 */
		do_action( 'wpss_commission_recorded', $order_id, $commission, $order->vendor_id );

		return true;
	}

	/**
	 * Get commission rate for an order.
	 *
	 * Checks for vendor-specific commission rate first, then falls back to global platform fee.
	 *
	 * @param object $order Order object.
	 * @return float Commission rate percentage.
	 */
	private function get_commission_rate( object $order ): float {
		global $wpdb;

		$rate = null;

		// Check if per-vendor rates are enabled in commission settings.
		$commission_settings = get_option( 'wpss_commission', array() );
		$enable_vendor_rates = ! empty( $commission_settings['enable_vendor_rates'] );

		if ( $enable_vendor_rates ) {
			// Check for vendor-specific commission rate.
			$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$vendor_rate = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT custom_commission_rate FROM {$profiles_table} WHERE user_id = %d",
					$order->vendor_id
				)
			);

			// Use vendor rate if set.
			if ( null !== $vendor_rate && '' !== $vendor_rate ) {
				$rate = (float) $vendor_rate;
			}
		}

		// Fall back to global commission rate if no vendor rate was applied.
		if ( null === $rate ) {
			$rate = self::get_global_commission_rate();
		}

		/**
		 * Filter the commission rate for a specific order.
		 *
		 * Allows for per-vendor or per-service commission overrides.
		 *
		 * @param float  $rate       Commission rate percentage.
		 * @param object $order      Order object.
		 * @param int    $vendor_id  Vendor user ID.
		 * @param int    $service_id Service post ID.
		 */
		return (float) apply_filters(
			'wpss_commission_rate',
			$rate,
			$order,
			$order->vendor_id,
			$order->service_id
		);
	}

	/**
	 * Update vendor profile with earnings.
	 *
	 * @param int   $vendor_id  Vendor user ID.
	 * @param array $commission Commission breakdown.
	 * @return void
	 */
	private function update_vendor_earnings( int $vendor_id, array $commission ): void {
		global $wpdb;

		$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$profiles_table}
				SET
					total_earnings = total_earnings + %f,
					net_earnings = net_earnings + %f,
					total_commission = total_commission + %f,
					updated_at = %s
				WHERE user_id = %d",
				$commission['order_total'],
				$commission['vendor_earnings'],
				$commission['platform_fee'],
				current_time( 'mysql' ),
				$vendor_id
			)
		);
	}

	/**
	 * Create wallet transaction for vendor earnings.
	 *
	 * @param int   $order_id   Order ID.
	 * @param int   $vendor_id  Vendor user ID.
	 * @param array $commission Commission breakdown.
	 * @return void
	 */
	private function create_earnings_transaction( int $order_id, int $vendor_id, array $commission ): void {
		global $wpdb;

		$transactions_table = $wpdb->prefix . 'wpss_wallet_transactions';

		// Lock the vendor's wallet transactions to prevent balance race conditions.
		$wpdb->query( 'START TRANSACTION' );

		// Get current balance with row lock.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current_balance = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(balance_after, 0)
				FROM {$transactions_table}
				WHERE user_id = %d
				ORDER BY created_at DESC, id DESC
				LIMIT 1
				FOR UPDATE",
				$vendor_id
			)
		);

		$new_balance = $current_balance + $commission['vendor_earnings'];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$transactions_table,
			array(
				'user_id'        => $vendor_id,
				'type'           => 'order_earning',
				'amount'         => $commission['vendor_earnings'],
				'balance_after'  => $new_balance,
				'currency'       => wpss_get_currency(),
				'description'    => sprintf(
					/* translators: 1: order ID, 2: order total, 3: commission rate */
					__( 'Earning from order #%1$d (Total: %2$s, Commission: %3$s%%)', 'wp-sell-services' ),
					$order_id,
					wpss_format_price( $commission['order_total'] ),
					$commission['commission_rate']
				),
				'reference_type' => 'order',
				'reference_id'   => $order_id,
				'status'         => 'completed',
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return;
		}

		$wpdb->query( 'COMMIT' );
	}

	/**
	 * Get order commission details.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null Commission details or null if not found/calculated.
	 */
	public function get_order_commission( int $order_id ): ?array {
		$order = wpss_get_order( $order_id );

		if ( ! $order || empty( $order->vendor_earnings ) ) {
			return null;
		}

		return array(
			'order_total'     => (float) $order->subtotal + (float) $order->addons_total,
			'commission_rate' => (float) $order->commission_rate,
			'platform_fee'    => (float) $order->platform_fee,
			'vendor_earnings' => (float) $order->vendor_earnings,
		);
	}

	/**
	 * Get vendor commission summary.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array{
	 *     total_orders: int,
	 *     total_revenue: float,
	 *     total_commission: float,
	 *     net_earnings: float,
	 *     avg_commission_rate: float
	 * }
	 */
	public function get_vendor_summary( int $vendor_id ): array {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$summary = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_orders,
					COALESCE(SUM(total), 0) as total_revenue,
					COALESCE(SUM(platform_fee), 0) as total_commission,
					COALESCE(SUM(vendor_earnings), 0) as net_earnings,
					COALESCE(AVG(commission_rate), 0) as avg_commission_rate
				FROM {$orders_table}
				WHERE vendor_id = %d
					AND status = %s
					AND vendor_earnings IS NOT NULL",
				$vendor_id,
				ServiceOrder::STATUS_COMPLETED
			)
		);

		return array(
			'total_orders'        => (int) ( $summary->total_orders ?? 0 ),
			'total_revenue'       => (float) ( $summary->total_revenue ?? 0 ),
			'total_commission'    => (float) ( $summary->total_commission ?? 0 ),
			'net_earnings'        => (float) ( $summary->net_earnings ?? 0 ),
			'avg_commission_rate' => (float) ( $summary->avg_commission_rate ?? 0 ),
		);
	}

	/**
	 * Get platform commission totals.
	 *
	 * @param string|null $start_date Start date (Y-m-d format).
	 * @param string|null $end_date   End date (Y-m-d format).
	 * @return array{
	 *     total_orders: int,
	 *     total_revenue: float,
	 *     total_commission: float,
	 *     total_vendor_earnings: float
	 * }
	 */
	public function get_platform_totals( ?string $start_date = null, ?string $end_date = null ): array {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		$where  = array( 'status = %s', 'vendor_earnings IS NOT NULL' );
		$params = array( ServiceOrder::STATUS_COMPLETED );

		if ( $start_date ) {
			$where[]  = 'completed_at >= %s';
			$params[] = $start_date . ' 00:00:00';
		}

		if ( $end_date ) {
			$where[]  = 'completed_at <= %s';
			$params[] = $end_date . ' 23:59:59';
		}

		$where_clause = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_orders,
					COALESCE(SUM(total), 0) as total_revenue,
					COALESCE(SUM(platform_fee), 0) as total_commission,
					COALESCE(SUM(vendor_earnings), 0) as total_vendor_earnings
				FROM {$orders_table}
				WHERE {$where_clause}",
				$params
			)
		);

		return array(
			'total_orders'          => (int) ( $totals->total_orders ?? 0 ),
			'total_revenue'         => (float) ( $totals->total_revenue ?? 0 ),
			'total_commission'      => (float) ( $totals->total_commission ?? 0 ),
			'total_vendor_earnings' => (float) ( $totals->total_vendor_earnings ?? 0 ),
		);
	}

	/**
	 * Get vendor's custom commission rate.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return float|null Custom commission rate or null if not set.
	 */
	public function get_vendor_commission_rate( int $vendor_id ): ?float {
		global $wpdb;

		$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rate = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT custom_commission_rate FROM {$profiles_table} WHERE user_id = %d",
				$vendor_id
			)
		);

		if ( null === $rate || '' === $rate ) {
			return null;
		}

		return (float) $rate;
	}

	/**
	 * Set vendor's custom commission rate.
	 *
	 * @param int        $vendor_id Vendor user ID.
	 * @param float|null $rate      Commission rate percentage (0-100), or null to use global rate.
	 * @return bool True on success, false on failure.
	 */
	public function set_vendor_commission_rate( int $vendor_id, ?float $rate ): bool {
		global $wpdb;

		$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';

		// Validate rate if provided.
		if ( null !== $rate && ( $rate < 0 || $rate > 100 ) ) {
			return false;
		}

		if ( null === $rate ) {
			// Reset to global: set column to SQL NULL.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$profiles_table} SET custom_commission_rate = NULL, updated_at = %s WHERE user_id = %d",
					current_time( 'mysql' ),
					$vendor_id
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$profiles_table,
				array(
					'custom_commission_rate' => $rate,
					'updated_at'             => current_time( 'mysql' ),
				),
				array( 'user_id' => $vendor_id ),
				array( '%f', '%s' ),
				array( '%d' )
			);
		}

		return false !== $result;
	}

	/**
	 * Get effective commission rate for a vendor.
	 *
	 * Returns the vendor's custom rate if set, otherwise returns the global platform fee.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return array{rate: float, is_custom: bool} Rate and whether it's a custom rate.
	 */
	public function get_effective_vendor_rate( int $vendor_id ): array {
		$custom_rate = $this->get_vendor_commission_rate( $vendor_id );

		if ( null !== $custom_rate ) {
			return array(
				'rate'      => $custom_rate,
				'is_custom' => true,
			);
		}

		return array(
			'rate'      => self::get_global_commission_rate(),
			'is_custom' => false,
		);
	}

	/**
	 * Get the global commission rate from settings.
	 *
	 * Reads from wpss_commission (new) with fallback to wpss_general (old)
	 * for backward compatibility.
	 *
	 * @return float Commission rate percentage.
	 */
	public static function get_global_commission_rate(): float {
		// Primary location: wpss_commission (new structure).
		$commission_settings = get_option( 'wpss_commission', array() );
		if ( isset( $commission_settings['commission_rate'] ) ) {
			return (float) $commission_settings['commission_rate'];
		}

		// Fallback: wpss_general (old structure for backward compatibility).
		$general_settings = get_option( 'wpss_general', array() );
		if ( isset( $general_settings['platform_fee_percentage'] ) ) {
			return (float) $general_settings['platform_fee_percentage'];
		}

		// Default.
		return 10.0;
	}

	/**
	 * Backfill commission for existing completed orders.
	 *
	 * Use this for migration when commission tracking is added to existing installation.
	 *
	 * @param int $batch_size Number of orders to process per batch.
	 * @return array{processed: int, remaining: int}
	 */
	public function backfill_commissions( int $batch_size = 50 ): array {
		global $wpdb;

		$orders_table = $wpdb->prefix . 'wpss_orders';

		// Get orders without commission calculated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$orders = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$orders_table}
				WHERE status = %s
					AND vendor_earnings IS NULL
				LIMIT %d",
				ServiceOrder::STATUS_COMPLETED,
				$batch_size
			)
		);

		$processed = 0;
		foreach ( $orders as $order_id ) {
			if ( $this->record( (int) $order_id ) ) {
				++$processed;
			}
		}

		// Count remaining.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table}
				WHERE status = %s AND vendor_earnings IS NULL",
				ServiceOrder::STATUS_COMPLETED
			)
		);

		return array(
			'processed' => $processed,
			'remaining' => $remaining,
		);
	}
}
