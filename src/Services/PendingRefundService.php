<?php
/**
 * Refunds a payment rail reported without saying which gig they were for.
 *
 * A payment that covers several gigs is one charge. Our own refunds name the gig, so they never
 * land here. A refund made directly in a gateway's dashboard names only the charge and an amount;
 * we know the gigs on the charge but not the one the admin meant, and a guess would reverse the
 * wrong vendor's earnings. The refund waits here until an admin picks the gig.
 *
 * Stored as order meta on every gig of the charge (one record, same key on each), so it shows on
 * each order screen like a failed refund does. The admin notice reads them back from the order
 * meta table (indexed by key), the same way it finds failed refunds.
 *
 * @package WPSellServices\Services
 * @since   1.9.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

use WPSellServices\Models\ServiceOrder;

defined( 'ABSPATH' ) || exit;

/**
 * PendingRefundService class.
 *
 * @since 1.9.0
 */
class PendingRefundService {

	/**
	 * Order meta holding the pending record.
	 */
	public const META = '_wpss_pending_rail_refund';

	/**
	 * Park a refund until an admin assigns it.
	 *
	 * @param int[]  $order_ids      Every order on the charge.
	 * @param string $gateway        Rail slug that reported it.
	 * @param string $transaction_id Transaction the orders were paid with.
	 * @param float  $amount         Amount refunded and not yet assigned.
	 * @param string $currency       Currency code.
	 * @param string $key            Identity of this refund event; the same event never records twice.
	 * @return bool False when this event is already parked.
	 */
	public static function record( array $order_ids, string $gateway, string $transaction_id, float $amount, string $currency, string $key ): bool {
		$order_ids = array_values( array_unique( array_map( 'intval', $order_ids ) ) );

		if ( empty( $order_ids ) || $amount <= 0 || '' === $key ) {
			return false;
		}

		// One record per charge: a second refund on the same charge adds to it, and the same refund
		// event reported twice adds nothing.
		$record = null;

		foreach ( $order_ids as $oid ) {
			$record = self::get( $oid );

			if ( null !== $record ) {
				break;
			}
		}

		if ( null !== $record && in_array( $key, (array) $record['events'], true ) ) {
			return false;
		}

		$record = null === $record
			? array(
				'key'            => $key,
				'events'         => array( $key ),
				'gateway'        => $gateway,
				'transaction_id' => $transaction_id,
				'currency'       => $currency,
				'amount'         => $amount,
				'remaining'      => $amount,
				'orders'         => $order_ids,
				'created_at'     => current_time( 'mysql', true ),
			)
			: array_merge(
				$record,
				array(
					'events'    => array_merge( (array) $record['events'], array( $key ) ),
					'amount'    => (float) $record['amount'] + $amount,
					'remaining' => (float) $record['remaining'] + $amount,
				)
			);

		$provider = wpss_get_order_provider();

		foreach ( $order_ids as $oid ) {
			$provider->update_item_meta( $oid, self::META, $record );
		}

		wpss_log( sprintf( '"%s" reported a refund of %s on %s without naming the gig (orders %s); waiting for an admin to assign it.', $gateway, (string) $amount, $transaction_id, implode( ', ', $order_ids ) ), 'warning' );

		/**
		 * Fires when a rail refund cannot be tied to one gig and waits for an admin.
		 *
		 * @since 1.9.0
		 *
		 * @param array<string, mixed> $record Pending record: key, gateway, transaction_id, currency, amount, remaining, orders.
		 */
		do_action( 'wpss_gateway_refund_unallocated', $record );

		return true;
	}

	/**
	 * The pending record on an order, or null.
	 *
	 * @param int $order_id Order ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $order_id ): ?array {
		$record = wpss_get_order_provider()->get_item_meta( $order_id, self::META );

		return is_array( $record ) && ! empty( $record['key'] ) && (float) ( $record['remaining'] ?? 0 ) > 0 ? $record : null;
	}

	/**
	 * Refund money reported by the rail and still waiting for a gig, across these orders.
	 *
	 * Counted with what is already recorded when a later running total arrives, so the same money
	 * is not parked twice.
	 *
	 * @param int[] $order_ids Orders on one charge.
	 * @return float
	 */
	public static function outstanding( array $order_ids ): float {
		$seen  = array();
		$total = 0.0;

		foreach ( $order_ids as $oid ) {
			$record = self::get( (int) $oid );

			if ( null !== $record && ! isset( $seen[ $record['key'] ] ) ) {
				$seen[ $record['key'] ] = true;
				$total                 += (float) $record['remaining'];
			}
		}

		return $total;
	}

	/**
	 * Refunds waiting for a gig, one record per charge, oldest first.
	 *
	 * @param int $limit Most records to return.
	 * @return array<int, array<string, mixed>>
	 */
	public static function all( int $limit = 20 ): array {
		global $wpdb;

		if ( '1.0' !== get_option( 'wpss_order_meta_table_version' ) ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}wpss_order_meta WHERE meta_key = %s AND meta_value <> '' ORDER BY order_id ASC LIMIT %d",
				self::META,
				$limit * 8
			)
		);
		$out  = array();

		foreach ( (array) $rows as $row ) {
			$record = maybe_unserialize( (string) $row->meta_value );

			if ( is_array( $record ) && ! empty( $record['key'] ) && (float) ( $record['remaining'] ?? 0 ) > 0 ) {
				$out[ $record['key'] ] = $record;
			}

			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return array_values( $out );
	}

	/**
	 * Assign all or part of a parked refund to one gig.
	 *
	 * @param int        $order_id Gig the refund was for.
	 * @param float|null $amount   Amount to assign; null assigns what the gig can still take.
	 * @return array{ok: bool, message: string, amount: float}
	 */
	public function assign( int $order_id, ?float $amount = null ): array {
		$fail   = static fn( string $message ): array => array(
			'ok'      => false,
			'message' => $message,
			'amount'  => 0.0,
		);
		$record = self::get( $order_id );

		if ( null === $record ) {
			return $fail( __( 'No refund is waiting for this order.', 'wp-sell-services' ) );
		}

		$service = new OrderService();
		$order   = $service->get( $order_id );

		if ( ! $order || ! in_array( $order_id, array_map( 'intval', $record['orders'] ), true ) ) {
			return $fail( __( 'This order is not one of the orders on that payment.', 'wp-sell-services' ) );
		}

		// Two clicks must not assign the same money twice.
		$lock = 'wpss_pending_refund_lock_' . md5( (string) $record['key'] );

		if ( ! $this->claim_lock( $lock ) ) {
			return $fail( __( 'This refund is being assigned. Reload and check the order.', 'wp-sell-services' ) );
		}

		try {
			$decimals  = wpss_get_currency_decimals( (string) ( $order->currency ?? '' ) );
			$room      = round( (float) $order->total - wpss_get_order_refunded_amount( $order ), $decimals );
			$requested = null === $amount || $amount <= 0 ? (float) $record['remaining'] : $amount;
			$apply     = round( min( $requested, (float) $record['remaining'], $room ), $decimals );

			if ( $apply <= 0 ) {
				return $fail( __( 'This order has nothing left to refund.', 'wp-sell-services' ) );
			}

			$result = $service->refund(
				$order_id,
				$apply,
				$apply >= $room ? ServiceOrder::STATUS_REFUNDED : ServiceOrder::STATUS_PARTIALLY_REFUNDED,
				array(
					'settled_at_rail' => true,
					'origin'          => (string) $record['gateway'],
				)
			);

			if ( ! $result['ok'] ) {
				return $fail( $result['message'] );
			}

			$record['remaining'] = round( (float) $record['remaining'] - $apply, $decimals );
			self::store( $record );

			( new AuditLogService() )->log(
				'order.pending_refund_assigned',
				'order',
				$order_id,
				array(
					'action'  => 'refund',
					'context' => array(
						'key'    => $record['key'],
						'amount' => $apply,
					),
				)
			);

			return array(
				'ok'      => true,
				'message' => '',
				'amount'  => $apply,
			);
		} finally {
			delete_option( $lock );
		}
	}

	/**
	 * Claim a named lock atomically: the options table refuses a second row with the same name.
	 *
	 * A lock older than a minute belongs to a request that died, so it is taken over once.
	 *
	 * @param string $name Option name used as the lock.
	 * @return bool True when this request holds the lock.
	 */
	private function claim_lock( string $name ): bool {
		global $wpdb;

		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic claim of a unique option name.
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) time() ) );

			if ( 1 === (int) $wpdb->rows_affected ) {
				return true;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the lock row directly; the options cache can be stale.
			$taken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
			if ( $taken && time() - $taken < MINUTE_IN_SECONDS ) {
				return false;
			}

			delete_option( $name );
		}

		return false;
	}

	/**
	 * Write the record back to every order on it, or clear it once nothing is left.
	 *
	 * @param array<string, mixed> $record Pending record.
	 * @return void
	 */
	private static function store( array $record ): void {
		$provider = wpss_get_order_provider();
		$done     = (float) $record['remaining'] <= 0;

		foreach ( $record['orders'] as $oid ) {
			$provider->update_item_meta( (int) $oid, self::META, $done ? '' : $record );
		}
	}
}
