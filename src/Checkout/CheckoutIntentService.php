<?php
/**
 * Checkout Intent Service — the single, gateway-agnostic place that decides
 * WHAT to charge (resolve) and turns a successful charge into order(s) (settle).
 *
 * Every gateway (Stripe, PayPal, Razorpay, and any future one) routes through
 * this. A gateway no longer re-implements single / multi-cart / pay-order
 * pricing or order creation — it calls resolve() to get an authoritative amount,
 * charges it with its own create_payment(), then calls settle() on success.
 *
 * @package WPSellServices\Checkout
 * @since   1.5.2
 */

declare(strict_types=1);

namespace WPSellServices\Checkout;

use WPSellServices\Services\MilestoneService;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves and settles gateway-agnostic checkout intents.
 *
 * @since 1.5.2
 */
class CheckoutIntentService {

	/**
	 * Resolve the current checkout request into an authoritative CheckoutIntent.
	 *
	 * Pricing is ALWAYS computed server-side (live post meta / stored order /
	 * server cart) — the client-supplied amount is never trusted. Priority:
	 * pay an existing order → multi-item cart → single service+package.
	 *
	 * @since 1.5.2
	 *
	 * @param array<string,mixed> $request Sanitized request data ($_POST-shaped).
	 * @param int                 $buyer_id Buyer user ID (defaults to current user).
	 * @return CheckoutIntent|\WP_Error
	 */
	public function resolve( array $request, int $buyer_id = 0 ) {
		$buyer_id = $buyer_id > 0 ? $buyer_id : get_current_user_id();

		if ( $buyer_id <= 0 ) {
			return new \WP_Error( 'wpss_not_logged_in', __( 'Please log in to continue.', 'wp-sell-services' ) );
		}

		$pay_order_id = absint( $request['pay_order'] ?? 0 );
		if ( $pay_order_id ) {
			return $this->resolve_order( $pay_order_id, $buyer_id );
		}

		if ( ! empty( $request['is_multi_checkout'] ) ) {
			return $this->resolve_cart( $buyer_id );
		}

		return $this->resolve_single( $request, $buyer_id );
	}

	/**
	 * Resolve a "pay an existing order" intent (proposal / milestone / tip / …).
	 *
	 * @since 1.5.2
	 *
	 * @param int $order_id Order ID.
	 * @param int $buyer_id Buyer ID.
	 * @return CheckoutIntent|\WP_Error
	 */
	private function resolve_order( int $order_id, int $buyer_id ) {
		$order = wpss_get_order( $order_id );

		if ( ! $order || (int) $order->customer_id !== $buyer_id ) {
			return new \WP_Error( 'wpss_invalid_order', __( 'Invalid order.', 'wp-sell-services' ) );
		}

		if ( 'pending_payment' !== $order->status ) {
			return new \WP_Error( 'wpss_already_paid', __( 'This order has already been paid.', 'wp-sell-services' ) );
		}

		// A locked milestone phase cannot be funded before the previous phase is
		// approved. The server is the only authority against a crafted request.
		if ( MilestoneService::ORDER_TYPE === ( $order->platform ?? '' ) ) {
			$milestones = new MilestoneService();
			if ( $milestones->is_locked( $order_id ) ) {
				return new \WP_Error( 'wpss_phase_locked', __( 'This phase is locked. Pay the previous phase first.', 'wp-sell-services' ) );
			}
		}

		$amount = (float) $order->total;
		if ( $amount <= 0 ) {
			return new \WP_Error( 'wpss_invalid_amount', __( 'Invalid amount.', 'wp-sell-services' ) );
		}

		return CheckoutIntent::order(
			$order_id,
			$amount,
			$order->currency ?: wpss_get_currency(),
			$buyer_id,
			array(
				'order_id'    => $order_id,
				'vendor_id'   => (int) $order->vendor_id,
				'service_id'  => (int) $order->service_id,
				'customer_id' => $buyer_id,
			)
		);
	}

	/**
	 * Resolve a multi-item cart intent, pricing the total from the SERVER cart.
	 *
	 * Mirrors StandaloneOrderProvider::create_orders_from_cart() exactly, so the
	 * amount charged equals the sum of the orders that settle() will create.
	 *
	 * @since 1.5.2
	 *
	 * @param int $buyer_id Buyer ID.
	 * @return CheckoutIntent|\WP_Error
	 */
	private function resolve_cart( int $buyer_id ) {
		$cart = get_user_meta( $buyer_id, '_wpss_cart', true );
		$cart = is_array( $cart ) ? $cart : array();

		if ( empty( $cart ) ) {
			return new \WP_Error( 'wpss_empty_cart', __( 'Your cart is empty.', 'wp-sell-services' ) );
		}

		$total = 0.0;
		foreach ( $cart as $item ) {
			$service_id = (int) ( $item['service_id'] ?? 0 );
			if ( ! $service_id ) {
				continue;
			}

			$quantity = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$packages = get_post_meta( $service_id, '_wpss_packages', true ) ?: array();
			$pkg      = $packages[ (int) ( $item['package_id'] ?? 0 ) ] ?? ( ! empty( $packages ) ? reset( $packages ) : null );

			if ( ! $pkg ) {
				continue;
			}

			$total += (float) ( $pkg['price'] ?? 0 ) * $quantity;
			$total += (float) array_reduce(
				$item['addons'] ?? array(),
				static fn( float $carry, array $addon ) => $carry + (float) ( $addon['price'] ?? 0 ),
				0.0
			);
		}

		if ( $total <= 0 ) {
			return new \WP_Error( 'wpss_invalid_amount', __( 'Invalid amount.', 'wp-sell-services' ) );
		}

		return CheckoutIntent::cart(
			$cart,
			$total,
			wpss_get_currency(),
			$buyer_id,
			array( 'customer_id' => $buyer_id )
		);
	}

	/**
	 * Resolve a single service + package (+ add-ons) intent.
	 *
	 * @since 1.5.2
	 *
	 * @param array $request  Request data.
	 * @param int   $buyer_id Buyer ID.
	 * @return CheckoutIntent|\WP_Error
	 */
	private function resolve_single( array $request, int $buyer_id ) {
		$service_id = absint( $request['service_id'] ?? 0 );
		$package_id = absint( $request['package_id'] ?? 0 );

		$service = get_post( $service_id );
		if ( ! $service || 'wpss_service' !== $service->post_type || 'publish' !== $service->post_status ) {
			return new \WP_Error( 'wpss_invalid_service', __( 'Invalid service.', 'wp-sell-services' ) );
		}

		$packages = get_post_meta( $service_id, '_wpss_packages', true );
		if ( ! is_array( $packages ) || ! isset( $packages[ $package_id ] ) ) {
			return new \WP_Error( 'wpss_invalid_package', __( 'Invalid package.', 'wp-sell-services' ) );
		}

		$amount = (float) ( $packages[ $package_id ]['price'] ?? 0 );
		if ( $amount <= 0 ) {
			return new \WP_Error( 'wpss_invalid_amount', __( 'Invalid amount.', 'wp-sell-services' ) );
		}

		$addon_data   = wpss_resolve_checkout_addons( $service_id );
		$addons_total = (float) ( $addon_data['addons_total'] ?? 0 );
		$amount      += $addons_total;

		return CheckoutIntent::single(
			$service_id,
			$package_id,
			(array) ( $addon_data['addons'] ?? array() ),
			$addons_total,
			$amount,
			wpss_get_currency(),
			$buyer_id,
			array(
				'service_id'  => $service_id,
				'package_id'  => $package_id,
				'customer_id' => $buyer_id,
			)
		);
	}

	/**
	 * Turn a verified successful charge into order(s).
	 *
	 * Gateway-agnostic. Does NOT refund on failure — the gateway owns its own
	 * refund API, so on a false return the gateway refunds the charge it made.
	 *
	 * @since 1.5.2
	 *
	 * @param CheckoutIntent $intent          The resolved intent.
	 * @param string         $gateway_id      Gateway slug (e.g. 'stripe').
	 * @param string         $transaction_id  Gateway transaction / intent ID.
	 * @param float          $charged_amount  Verified charged amount.
	 * @param string         $charged_currency Verified charged currency.
	 * @return array<string,mixed> { success:bool, ... }
	 */
	public function settle( CheckoutIntent $intent, string $gateway_id, string $transaction_id, float $charged_amount, string $charged_currency ): array {
		$provider = wpss_get_order_provider();
		if ( ! $provider ) {
			return array(
				'success' => false,
				'error'   => __( 'No order provider available.', 'wp-sell-services' ),
			);
		}

		switch ( $intent->kind ) {
			case CheckoutIntent::KIND_ORDER:
				return $this->settle_order( $intent, $provider, $gateway_id, $transaction_id );

			case CheckoutIntent::KIND_CART:
				return $this->settle_cart( $intent, $provider, $gateway_id, $transaction_id );

			case CheckoutIntent::KIND_SINGLE:
			default:
				return $this->settle_single( $intent, $provider, $gateway_id, $transaction_id, $charged_amount, $charged_currency );
		}
	}

	/**
	 * Settle an existing-order payment: mark it paid.
	 *
	 * @since 1.5.2
	 *
	 * @param CheckoutIntent $intent   Intent.
	 * @param object         $provider Order provider.
	 * @param string         $gateway  Gateway slug.
	 * @param string         $txn      Transaction ID.
	 * @return array<string,mixed>
	 */
	private function settle_order( CheckoutIntent $intent, $provider, string $gateway, string $txn ): array {
		$order = wpss_get_order( $intent->order_id );
		if ( ! $order || (int) $order->customer_id !== $intent->buyer_id ) {
			return array(
				'success' => false,
				'error'   => __( 'Invalid order.', 'wp-sell-services' ),
			);
		}

		$provider->mark_as_paid( $order->id, $txn, $gateway );

		return array(
			'success'      => true,
			'order_id'     => (int) $order->id,
			'order_number' => $order->order_number,
			'redirect_url' => wpss_get_order_requirements_url( $order->id ),
		);
	}

	/**
	 * Settle a multi-item cart: create one order per line, mark paid, clear cart.
	 *
	 * @since 1.5.2
	 *
	 * @param CheckoutIntent $intent   Intent.
	 * @param object         $provider Order provider.
	 * @param string         $gateway  Gateway slug.
	 * @param string         $txn      Transaction ID.
	 * @return array<string,mixed>
	 */
	private function settle_cart( CheckoutIntent $intent, $provider, string $gateway, string $txn ): array {
		$order_ids = $provider->create_orders_from_cart( $intent->cart, $gateway, $txn, $intent->buyer_id );

		if ( empty( $order_ids ) ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to create orders. Please contact support.', 'wp-sell-services' ),
			);
		}

		foreach ( $order_ids as $oid ) {
			$provider->mark_as_paid( (int) $oid, $txn, $gateway );
		}

		delete_user_meta( $intent->buyer_id, '_wpss_cart' );

		return array(
			'success'      => true,
			'order_ids'    => array_map( 'intval', $order_ids ),
			'redirect_url' => add_query_arg( 'tab', 'orders', wpss_get_page_url( 'dashboard' ) ),
		);
	}

	/**
	 * Settle a single purchase: create the order, mark it paid.
	 *
	 * @since 1.5.2
	 *
	 * @param CheckoutIntent $intent           Intent.
	 * @param object         $provider         Order provider.
	 * @param string         $gateway          Gateway slug.
	 * @param string         $txn              Transaction ID.
	 * @param float          $charged_amount   Verified charged amount.
	 * @param string         $charged_currency Verified charged currency.
	 * @return array<string,mixed>
	 */
	private function settle_single( CheckoutIntent $intent, $provider, string $gateway, string $txn, float $charged_amount, string $charged_currency ): array {
		$order = $provider->create_order(
			array(
				'service_id'     => $intent->service_id,
				'package_id'     => $intent->package_id,
				'customer_id'    => $intent->buyer_id,
				'subtotal'       => $charged_amount - $intent->addons_total,
				'addons'         => $intent->addons,
				'addons_total'   => $intent->addons_total,
				'currency'       => $charged_currency,
				'payment_method' => $gateway,
				'transaction_id' => $txn,
			)
		);

		if ( ! $order ) {
			return array(
				'success' => false,
				'error'   => __( 'Failed to create order.', 'wp-sell-services' ),
			);
		}

		$provider->mark_as_paid( $order->id, $txn, $gateway );

		// Remove the purchased line from the cart so it can't be re-paid.
		// settle_cart() clears the whole cart after a multi-item checkout; the
		// single path buys one service/package, so drop just that line (matched
		// on service_id + package_id) and leave any unrelated items intact.
		// Without this, a single Stripe checkout left the item in the cart —
		// PayPal and Offline already cleared it (re-checkout risk).
		$cart = get_user_meta( $intent->buyer_id, '_wpss_cart', true );
		if ( is_array( $cart ) && ! empty( $cart ) ) {
			foreach ( $cart as $key => $item ) {
				if ( (int) ( $item['service_id'] ?? 0 ) === $intent->service_id
					&& (int) ( $item['package_id'] ?? 0 ) === $intent->package_id ) {
					unset( $cart[ $key ] );
				}
			}

			if ( empty( $cart ) ) {
				delete_user_meta( $intent->buyer_id, '_wpss_cart' );
			} else {
				update_user_meta( $intent->buyer_id, '_wpss_cart', $cart );
			}
		}

		return array(
			'success'      => true,
			'order_id'     => (int) $order->id,
			'order_number' => $order->order_number,
			'redirect_url' => wpss_get_order_requirements_url( $order->id ),
		);
	}
}
