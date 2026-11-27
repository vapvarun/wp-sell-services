<?php
/**
 * Bank chargebacks (card disputes) while they are open.
 *
 * @package WPSellServices\Services
 * @since   2.0.0
 */

namespace WPSellServices\Services;

use WPSellServices\Database\Repositories\OrderRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Records a chargeback on the orders its payment paid for, from opened to closed.
 *
 * Owner rule (Basecamp 10553225092): an opened chargeback tells the site owner and marks the order;
 * the vendor is not told and no money moves. A lost one is settled through the refund seam
 * (AbstractPaymentGateway::report_lost_dispute()); this class only closes the mark.
 *
 * The record lives in the order's meta under `chargebacks`, keyed by the provider's dispute id, so a
 * resent event finds it and adds nothing. The Activity box, the REST timeline and the order screen's
 * notice all read it from there.
 *
 * @since 2.0.0
 */
class ChargebackService {

	public const META_KEY = 'chargebacks';

	public const STATUS_OPEN = 'open';

	/**
	 * Order repository.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $orders;

	/**
	 * Constructor.
	 *
	 * @param OrderRepository|null $orders Order repository.
	 */
	public function __construct( ?OrderRepository $orders = null ) {
		$this->orders = $orders ?? new OrderRepository();
	}

	/**
	 * Record an opened chargeback on every order its payment paid for.
	 *
	 * @param string $gateway        Gateway id.
	 * @param string $transaction_id Payment id the orders were paid with.
	 * @param string $dispute_id     Provider dispute id.
	 * @param float  $amount         Disputed amount, major units.
	 * @param string $currency       Currency code.
	 * @param string $due_by         When evidence is due, UTC 'Y-m-d H:i:s', or ''.
	 * @return int[] Orders newly marked; empty when the payment is unknown or the event was seen before.
	 */
	public function opened( string $gateway, string $transaction_id, string $dispute_id, float $amount, string $currency, string $due_by = '' ): array {
		if ( '' === $transaction_id || '' === $dispute_id ) {
			return array();
		}

		$record = array(
			'gateway'   => $gateway,
			'status'    => self::STATUS_OPEN,
			'amount'    => $amount,
			'currency'  => strtoupper( $currency ),
			'due_by'    => $due_by,
			'opened_at' => current_time( 'mysql', true ),
		);

		$marked = $this->write(
			$transaction_id,
			$dispute_id,
			static fn( ?array $existing ): ?array => null === $existing ? $record : null
		);

		if ( $marked ) {
			/**
			 * Fires once when a bank chargeback is opened on a payment.
			 *
			 * The owner email listens here. A resent provider event does not fire it again.
			 *
			 * @since 2.0.0
			 *
			 * @param string $dispute_id Provider dispute id.
			 * @param int[]  $order_ids  Orders the disputed payment paid for.
			 * @param array  $record     gateway, status, amount, currency, due_by (UTC), opened_at (UTC).
			 */
			do_action( 'wpss_chargeback_opened', $dispute_id, $marked, $record );
		}

		return $marked;
	}

	/**
	 * Close a chargeback recorded as open. Money is not touched here.
	 *
	 * @param string $transaction_id Payment id the orders were paid with.
	 * @param string $dispute_id     Provider dispute id.
	 * @param string $outcome        'won', 'lost' or 'closed' (withdrawn, warning, accepted).
	 * @return int[] Orders whose open chargeback was closed.
	 */
	public function closed( string $transaction_id, string $dispute_id, string $outcome ): array {
		if ( '' === $transaction_id || '' === $dispute_id ) {
			return array();
		}

		$outcome = in_array( $outcome, array( 'won', 'lost' ), true ) ? $outcome : 'closed';
		$closed  = $this->write(
			$transaction_id,
			$dispute_id,
			static fn( ?array $existing ): ?array => ( $existing && self::STATUS_OPEN === ( $existing['status'] ?? '' ) )
				? array_merge(
					$existing,
					array(
						'status'    => $outcome,
						'closed_at' => current_time( 'mysql', true ),
					)
				)
				: null
		);

		if ( $closed ) {
			/**
			 * Fires once when an open bank chargeback is closed.
			 *
			 * @since 2.0.0
			 *
			 * @param string $dispute_id Provider dispute id.
			 * @param int[]  $order_ids  Orders the disputed payment paid for.
			 * @param string $outcome    won, lost or closed.
			 */
			do_action( 'wpss_chargeback_closed', $dispute_id, $closed, $outcome );
		}

		return $closed;
	}

	/**
	 * Every chargeback recorded on an order, keyed by dispute id.
	 *
	 * @param object $order Order row or model with a `meta` property.
	 * @return array<string, array>
	 */
	public static function for_order( object $order ): array {
		$meta = $order->meta ?? array();
		$meta = is_string( $meta ) ? json_decode( $meta, true ) : $meta;
		$list = is_array( $meta ) ? ( $meta[ self::META_KEY ] ?? array() ) : array();

		return is_array( $list ) ? $list : array();
	}

	/**
	 * Chargebacks still open on an order.
	 *
	 * @param object $order Order row or model.
	 * @return array<string, array>
	 */
	public static function open_for_order( object $order ): array {
		return array_filter( self::for_order( $order ), static fn( $c ): bool => self::STATUS_OPEN === ( $c['status'] ?? '' ) );
	}

	/**
	 * Apply one change to the dispute's record on every order of the payment, under a per-dispute lock.
	 *
	 * @param string   $transaction_id Payment id.
	 * @param string   $dispute_id     Dispute id.
	 * @param callable $change         Given the existing record (or null), returns the new one, or null to leave it.
	 * @return int[] Orders written.
	 */
	private function write( string $transaction_id, string $dispute_id, callable $change ): array {
		global $wpdb;

		$lock = wpss_lock_name( 'wpss_chargeback_' . md5( $dispute_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 10 )', $lock ) ) ) {
			// Another delivery of the same event is writing it; that one wins.
			return array();
		}

		$written = array();

		try {
			// Read inside the lock, so a resend that waited sees the first delivery's record.
			foreach ( $this->orders->get_by_transaction_ids( array( $transaction_id ) ) as $row ) {
				$meta = json_decode( (string) ( $row->meta ?? '' ), true );
				$meta = is_array( $meta ) ? $meta : array();
				$list = is_array( $meta[ self::META_KEY ] ?? null ) ? $meta[ self::META_KEY ] : array();
				$next = $change( $list[ $dispute_id ] ?? null );

				if ( null === $next ) {
					continue;
				}

				$list[ $dispute_id ]    = $next;
				$meta[ self::META_KEY ] = $list;

				if ( $this->orders->update( (int) $row->id, array( 'meta' => wp_json_encode( $meta ) ) ) ) {
					$written[] = (int) $row->id;
				}
			}
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
		}

		return $written;
	}
}
