<?php
/**
 * Manual payment service
 *
 * Settles an order whose money arrived outside the site: a manual gateway (Offline, or any gateway
 * that supports manual_confirmation), or a slug no gateway registers (bank_transfer, cash, a provider
 * an integration reports). One door for the admin Mark as paid box and POST /orders/{id}/mark-paid.
 *
 * @package WPSellServices\Services
 * @since   2.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

use WPSellServices\Integrations\Gateways\OfflineGateway;
use WPSellServices\Models\ServiceOrder;

defined( 'ABSPATH' ) || exit;

/**
 * ManualPaymentService class.
 *
 * @since 2.0.0
 */
class ManualPaymentService {

	/**
	 * Record a payment made outside the plugin and settle the order.
	 *
	 * The caller checks the capability.
	 *
	 * @since 1.9.2 As OfflineGateway::settle_manually().
	 * @since 2.0.0 Any manual method; an empty method keeps the order's own manual one.
	 *
	 * @param int    $order_id       Order ID.
	 * @param string $transaction_id Reference from the payment provider; generated when empty.
	 * @param string $payment_method Slug stored on the order, e.g. flutterwave. Empty keeps the order's manual method, else offline.
	 * @return array{status: string}|\WP_Error The order's new status, or why it was refused.
	 */
	public function settle( int $order_id, string $transaction_id = '', string $payment_method = '' ) {
		$order = $order_id > 0 ? wpss_get_order( $order_id ) : null;

		if ( ! $order ) {
			return new \WP_Error( 'wpss_order_not_found', __( 'Order not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		// A whole order must be awaiting payment. A tip, extension or phase may be in any state an admin moved
		// it to, as long as its payment was never confirmed (it is credited only once this confirms it).
		if ( 'pending_payment' !== $order->status && ! $this->is_unpaid_sub_order( $order ) ) {
			return new \WP_Error( 'wpss_order_not_awaiting_payment', __( 'Order is not awaiting payment.', 'wp-sell-services' ), array( 'status' => 409 ) );
		}

		$payment_method = sanitize_key( $payment_method );

		if ( '' === $payment_method ) {
			$current        = (string) ( $order->payment_method ?? '' );
			$payment_method = wpss_is_manual_payment_method( $current ) ? $current : 'offline';
		}

		// A gateway that takes money itself records its own charges, and a refund calls that gateway with the
		// stored id, so a hand-typed "stripe" plus an invented id would send the refund to Stripe and fail.
		// Any other slug (flutterwave, a payment link) is recorded as given.
		$gateway = wpss()->get_payment_gateways()[ $payment_method ] ?? null;
		if ( $gateway && ! wpss_gateway_supports( $gateway, 'manual_confirmation' ) ) {
			return new \WP_Error(
				'wpss_method_is_a_gateway',
				/* translators: %s: payment method slug, for example stripe. */
				sprintf( __( 'Payments through %s are recorded by that gateway itself. Name the method the money actually came through, or leave it empty for offline.', 'wp-sell-services' ), $payment_method ),
				array( 'status' => 400 )
			);
		}

		$transaction_id = '' === $transaction_id ? OfflineGateway::GENERATED_TRANSACTION_PREFIX . time() : $transaction_id;

		if ( ! wpss_get_order_provider()->mark_as_paid( $order_id, $transaction_id, $payment_method ) ) {
			return new \WP_Error( 'wpss_mark_paid_failed', __( 'Failed to mark order as paid.', 'wp-sell-services' ), array( 'status' => 409 ) );
		}

		/**
		 * Fires when an order paid outside the site is marked paid.
		 *
		 * @since 1.0.0
		 * @since 2.0.0 Fires for every manual method, which is the third argument.
		 *
		 * @param int    $order_id       Order ID.
		 * @param string $transaction_id Transaction ID.
		 * @param string $payment_method Manual method slug.
		 */
		do_action( 'wpss_offline_order_paid', $order_id, $transaction_id, $payment_method );

		return array( 'status' => (string) ( wpss_get_order( $order_id )->status ?? 'pending_requirements' ) );
	}

	/**
	 * Is this a manually paid tip, extension or phase whose payment is still unconfirmed?
	 *
	 * An admin can move such a sub-order to any status, Completed included, and
	 * CommissionService::record() / MilestoneService::credit_phase() then credit
	 * nothing until the payment is confirmed. Mark as paid is that confirmation,
	 * so it has to stay available whatever the status (except a closed order,
	 * which mark_as_paid() refuses anyway).
	 *
	 * @since 1.9.1
	 *
	 * @param object $order Order object.
	 * @return bool
	 */
	public function is_unpaid_sub_order( object $order ): bool {
		// A manual method, or none chosen at all: the buyer never opened the pay page, or the owner was
		// paid outside the site. A card or store rail confirms itself, so it is no door.
		$method = (string) ( $order->payment_method ?? '' );

		return ( '' === $method || wpss_is_manual_payment_method( $method ) )
			&& in_array( $order->platform ?? '', array( ServiceOrder::SUB_ORDER_TYPE_TIP, ServiceOrder::SUB_ORDER_TYPE_EXTENSION, ServiceOrder::SUB_ORDER_TYPE_MILESTONE ), true )
			&& ! in_array( (string) ( $order->payment_status ?? '' ), array( 'paid', 'refunded', 'partially_refunded' ), true )
			&& empty( $order->paid_at )
			&& ! in_array( (string) ( $order->status ?? '' ), array( ServiceOrder::STATUS_CANCELLED, ServiceOrder::STATUS_REFUNDED ), true );
	}
}
