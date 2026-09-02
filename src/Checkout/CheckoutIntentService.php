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
 * @since   1.3.0
 */

declare(strict_types=1);

namespace WPSellServices\Checkout;

use WPSellServices\Services\MilestoneService;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves and settles gateway-agnostic checkout intents.
 *
 * @since 1.3.0
 */
class CheckoutIntentService {

	/**
	 * Resolve the current checkout request into an authoritative CheckoutIntent.
	 *
	 * Pricing is ALWAYS computed server-side (live post meta / stored order /
	 * server cart) — the client-supplied amount is never trusted. Priority:
	 * pay an existing order → multi-item cart → single service+package.
	 *
	 * @since 1.3.0
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
	 * @since 1.3.0
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
	 * Each line is priced and taxed exactly as create_order() will price and tax
	 * its order row, and the priced numbers travel on the intent so settle_cart()
	 * writes the rows from them. A two-item cart was charged untaxed while every
	 * row it produced stored a taxed total (Basecamp 10264284228).
	 *
	 * @since 1.3.0
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

		$lines  = array();
		$totals = array(
			'subtotal'     => 0.0,
			'addons_total' => 0.0,
			'tax'          => 0.0,
			'total'        => 0.0,
		);

		foreach ( $cart as $key => $item ) {
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

			$line = $this->price_line(
				$service_id,
				(float) ( $pkg['price'] ?? 0 ) * $quantity,
				(float) array_reduce(
					$item['addons'] ?? array(),
					static fn( float $carry, array $addon ) => $carry + (float) ( $addon['price'] ?? 0 ),
					0.0
				)
			);

			foreach ( $totals as $k => $v ) {
				$totals[ $k ] = $v + $line[ $k ];
			}

			$lines[ $key ] = $item + $line;
		}

		if ( $totals['total'] <= 0 ) {
			return new \WP_Error( 'wpss_invalid_amount', __( 'Invalid amount.', 'wp-sell-services' ) );
		}

		$intent = CheckoutIntent::cart(
			$lines,
			$totals['total'],
			wpss_get_currency(),
			$buyer_id,
			array(
				'is_multi_checkout' => 1,
				'customer_id'       => $buyer_id,
			)
		);

		$intent->addons_total = $totals['addons_total'];
		$intent->taxable_base = $totals['subtotal'] + $totals['addons_total'];
		$intent->tax          = $totals['tax'];

		return $intent;
	}

	/**
	 * Price one line the way StandaloneOrderProvider::create_order() prices its
	 * row: subtotal plus add-ons is the taxable base, tax through the shared
	 * helper. Both callers (single and cart) and the order row are the same
	 * arithmetic by construction.
	 *
	 * @since 1.7.1
	 *
	 * @param int   $service_id   Service ID.
	 * @param float $subtotal     Package price (times quantity).
	 * @param float $addons_total Add-ons total.
	 * @return array{subtotal:float,addons_total:float,tax:float,total:float}
	 */
	private function price_line( int $service_id, float $subtotal, float $addons_total ): array {
		$tax = wpss_calculate_tax( $subtotal + $addons_total, (int) get_post_field( 'post_author', $service_id ), $service_id );

		return array(
			'subtotal'     => $subtotal,
			'addons_total' => $addons_total,
			'tax'          => (float) $tax['amount'],
			'total'        => (float) $tax['total'],
		);
	}

	/**
	 * Resolve a single service + package (+ add-ons) intent.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $request  Request data.
	 * @param int                  $buyer_id Buyer ID.
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

		// Nobody buys from themselves; every rail used to check this on its own
		// (or, on Stripe, not at all).
		if ( (int) $service->post_author === $buyer_id ) {
			return new \WP_Error( 'wpss_own_service', __( 'You cannot purchase your own service.', 'wp-sell-services' ) );
		}

		$price = (float) ( $packages[ $package_id ]['price'] ?? 0 );
		if ( $price <= 0 ) {
			return new \WP_Error( 'wpss_invalid_amount', __( 'Invalid amount.', 'wp-sell-services' ) );
		}

		// Add-ons come from the request (the checkout form) or, on a gateway
		// return leg where the form is long gone, from the ids the gateway
		// carried in its own metadata.
		$addon_data = wpss_resolve_checkout_addons( $service_id, (string) ( $request['addon_ids'] ?? '' ) );

		/*
		 * Charge what the buyer was shown.
		 *
		 * The checkout template computes tax and puts the taxed figure on the
		 * Pay button; the order row records the taxed total; the gateway must
		 * charge THAT number. A buyer saw $100.30 and was charged $85.00, and
		 * the 18% was never collected from anybody (Basecamp 10254444011).
		 * Commission is unaffected: CommissionService works from the PRE-tax
		 * base, because tax is not revenue to split.
		 */
		$line = $this->price_line( $service_id, $price, (float) ( $addon_data['addons_total'] ?? 0 ) );

		$intent = CheckoutIntent::single(
			$service_id,
			$package_id,
			(array) ( $addon_data['addons'] ?? array() ),
			$line['addons_total'],
			$line['total'],
			wpss_get_currency(),
			$buyer_id,
			array(
				'service_id'  => $service_id,
				'package_id'  => $package_id,
				'customer_id' => $buyer_id,
			),
			$line['subtotal'] + $line['addons_total']
		);

		$intent->tax = $line['tax'];

		return $intent;
	}

	/**
	 * Turn a verified successful charge into order(s).
	 *
	 * Gateway-agnostic. Does NOT refund on failure — the gateway owns its own
	 * refund API, so on a false return the gateway refunds the charge it made.
	 *
	 * @since 1.3.0
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
	 * @since 1.3.0
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
	 * @since 1.3.0
	 *
	 * @param CheckoutIntent $intent   Intent.
	 * @param object         $provider Order provider.
	 * @param string         $gateway  Gateway slug.
	 * @param string         $txn      Transaction ID.
	 * @return array<string,mixed>
	 */
	private function settle_cart( CheckoutIntent $intent, $provider, string $gateway, string $txn ): array {
		// Rows are written from the numbers the intent priced and the gateway
		// charged - not re-priced here - so the charge and the sum of the rows
		// cannot drift. create_order() applies tax to the pre-tax subtotal.
		$order_ids = array();
		foreach ( $intent->cart as $line ) {
			$order = $provider->create_order(
				array(
					'service_id'     => (int) ( $line['service_id'] ?? 0 ),
					'package_id'     => (int) ( $line['package_id'] ?? 0 ),
					'quantity'       => max( 1, (int) ( $line['quantity'] ?? 1 ) ),
					'customer_id'    => $intent->buyer_id,
					'subtotal'       => (float) ( $line['subtotal'] ?? 0 ),
					'addons'         => $line['addons'] ?? array(),
					'addons_total'   => (float) ( $line['addons_total'] ?? 0 ),
					'currency'       => $intent->currency,
					'payment_method' => $gateway,
					'transaction_id' => $txn,
				)
			);

			if ( $order ) {
				$order_ids[] = (int) $order->id;
			}
		}

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
	 * @since 1.3.0
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

				/*
				 * PRE-TAX subtotal, deliberately not $charged_amount.
				 *
				 * create_order() applies tax itself and CommissionService takes
				 * its cut from subtotal + addons_total. Passing the charged
				 * amount here - which now includes tax - taxed the order a
				 * SECOND time (Pay $88.50, order total $104.43) and billed
				 * commission on the tax as well (Basecamp 10254561978).
				 *
				 * $charged_amount is still what the gateway reports it took, and
				 * is checked against the intent below; it is simply not the
				 * number the order row is built from.
				 */
				'subtotal'       => max( 0, $intent->taxable_base - $intent->addons_total ),
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
