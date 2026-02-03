<?php
/**
 * Tipping Service
 *
 * Handles tips from customers to vendors after order completion.
 *
 * @package WPSellServices\Services
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

use WPSellServices\Models\ServiceOrder;

/**
 * Manages order tipping functionality.
 *
 * Tips are stored as wallet transactions with type='tip'.
 *
 * @since 1.0.0
 */
class TippingService {

	/**
	 * Transaction type for tips.
	 */
	public const TYPE_TIP = 'tip';

	/**
	 * Tip status constants.
	 */
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_PENDING   = 'pending';
	public const STATUS_REFUNDED  = 'refunded';

	/**
	 * Send a tip to a vendor for a completed order.
	 *
	 * @param int    $order_id    Order ID.
	 * @param float  $amount      Tip amount.
	 * @param int    $customer_id Customer user ID.
	 * @param string $message     Optional tip message.
	 * @return array{success: bool, tip_id: int|null, message: string}
	 */
	public function tip( int $order_id, float $amount, int $customer_id, string $message = '' ): array {
		global $wpdb;

		// Validate amount.
		if ( $amount <= 0 ) {
			return array(
				'success' => false,
				'tip_id'  => null,
				'message' => __( 'Tip amount must be greater than zero.', 'wp-sell-services' ),
			);
		}

		// Get order and validate.
		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return array(
				'success' => false,
				'tip_id'  => null,
				'message' => __( 'Order not found.', 'wp-sell-services' ),
			);
		}

		// Check order is completed.
		if ( ServiceOrder::STATUS_COMPLETED !== $order->status ) {
			return array(
				'success' => false,
				'tip_id'  => null,
				'message' => __( 'Tips can only be sent for completed orders.', 'wp-sell-services' ),
			);
		}

		// Check customer owns the order.
		if ( $customer_id !== (int) $order->customer_id ) {
			return array(
				'success' => false,
				'tip_id'  => null,
				'message' => __( 'You can only tip on your own orders.', 'wp-sell-services' ),
			);
		}

		// Check if already tipped.
		if ( $this->has_tipped( $order_id, $customer_id ) ) {
			return array(
				'success' => false,
				'tip_id'  => null,
				'message' => __( 'You have already tipped on this order.', 'wp-sell-services' ),
			);
		}

		$table = $wpdb->prefix . 'wpss_wallet_transactions';

		// Lock the vendor's wallet transactions to prevent race conditions.
		$wpdb->query( 'START TRANSACTION' );

		// Get vendor's current balance with row lock.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current_balance = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT balance_after FROM {$table}
				WHERE user_id = %d
				ORDER BY created_at DESC, id DESC
				LIMIT 1
				FOR UPDATE",
				$order->vendor_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$new_balance = $current_balance + $amount;

		// Build description.
		$description = sprintf(
			/* translators: 1: Order number */
			__( 'Tip received for order %s', 'wp-sell-services' ),
			$order->order_number
		);
		if ( ! empty( $message ) ) {
			$description .= ': ' . $message;
		}

		// Insert tip transaction.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table,
			array(
				'user_id'        => $order->vendor_id,
				'type'           => self::TYPE_TIP,
				'amount'         => $amount,
				'balance_after'  => $new_balance,
				'currency'       => $order->currency ?? 'USD',
				'description'    => $description,
				'reference_type' => 'order',
				'reference_id'   => $order_id,
				'status'         => self::STATUS_COMPLETED,
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return array(
				'success' => false,
				'tip_id'  => null,
				'message' => __( 'Failed to process tip. Please try again.', 'wp-sell-services' ),
			);
		}

		$wpdb->query( 'COMMIT' );

		$tip_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a tip is sent.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $tip_id      Tip transaction ID.
		 * @param int    $order_id    Order ID.
		 * @param int    $vendor_id   Vendor user ID.
		 * @param int    $customer_id Customer user ID.
		 * @param float  $amount      Tip amount.
		 * @param string $message     Tip message.
		 */
		do_action( 'wpss_tip_sent', $tip_id, $order_id, $order->vendor_id, $customer_id, $amount, $message );

		return array(
			'success' => true,
			'tip_id'  => $tip_id,
			'message' => __( 'Tip sent successfully!', 'wp-sell-services' ),
		);
	}

	/**
	 * Check if customer has already tipped on an order.
	 *
	 * @param int $order_id    Order ID.
	 * @param int $_customer_id Customer user ID (unused, for API consistency).
	 * @return bool
	 */
	public function has_tipped( int $order_id, int $_customer_id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_wallet_transactions';

		// Get the order to check vendor.
		$order = $this->get_order( $order_id );
		if ( ! $order ) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE type = %s
				AND reference_type = 'order'
				AND reference_id = %d
				AND user_id = %d
				LIMIT 1",
				self::TYPE_TIP,
				$order_id,
				$order->vendor_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (bool) $exists;
	}

	/**
	 * Get tips received by a vendor.
	 *
	 * @param int   $vendor_id Vendor user ID.
	 * @param array $args      Query arguments.
	 * @return array Array of tip records.
	 */
	public function get_vendor_tips( int $vendor_id, array $args = array() ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_wallet_transactions';

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE user_id = %d AND type = %s
				ORDER BY created_at DESC
				LIMIT %d OFFSET %d",
				$vendor_id,
				self::TYPE_TIP,
				$args['limit'],
				$args['offset']
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $results;
	}

	/**
	 * Get tip for a specific order.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null Tip record or null.
	 */
	public function get_order_tip( int $order_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_wallet_transactions';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$tip = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE type = %s AND reference_type = 'order' AND reference_id = %d
				LIMIT 1",
				self::TYPE_TIP,
				$order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $tip ? $tip : null;
	}

	/**
	 * Get total tips received by a vendor.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return float Total tips amount.
	 */
	public function get_vendor_tips_total( int $vendor_id ): float {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_wallet_transactions';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(amount), 0) FROM {$table}
				WHERE user_id = %d AND type = %s AND status = %s",
				$vendor_id,
				self::TYPE_TIP,
				self::STATUS_COMPLETED
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (float) $total;
	}

	/**
	 * Get order by ID.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null Order row or null.
	 */
	private function get_order( int $order_id ): ?object {
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_orders';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				$order_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $order ? $order : null;
	}
}
