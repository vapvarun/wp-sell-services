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
		$cart = wpss_get_user_cart( (int) $buyer_id );

		if ( empty( $cart ) ) {
			return new \WP_Error( 'wpss_empty_cart', __( 'Your cart is empty.', 'wp-sell-services' ) );
		}

		$lines   = array();
		$vendors = array();
		$totals  = array(
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

			$line = self::price_service_line( $service_id, (int) ( $item['package_id'] ?? 0 ), max( 1, (int) ( $item['quantity'] ?? 1 ) ), $item['addons'] ?? array() );

			// A line whose package or required add-on no longer resolves stops
			// the checkout with the reason, rather than dropping out of the
			// total while the buyer still sees it.
			if ( is_wp_error( $line ) ) {
				return $line;
			}

			$vendors[ (int) $line['vendor_id'] ] = true;

			foreach ( $totals as $k => $v ) {
				$totals[ $k ] = $v + $line[ $k ];
			}

			// The priced line wins over what the cart stored (its old add-on prices).
			$lines[ $key ] = $line + $item;
		}

		if ( $totals['total'] <= 0 ) {
			return new \WP_Error( 'wpss_invalid_amount', __( 'Invalid amount.', 'wp-sell-services' ) );
		}

		// A rail that splits at charge time (Pro's Stripe Connect) needs ONE
		// vendor on the intent. A cart from a single vendor carries vendor_id
		// like a single purchase does; a cart spanning vendors carries the
		// list, and the rail keeps the whole charge on the platform - the
		// wallet settles each vendor on completion as it always has.
		$vendor_ids = array_keys( $vendors );
		$metadata   = array(
			'is_multi_checkout' => 1,
			'customer_id'       => $buyer_id,
		);
		if ( 1 === count( $vendor_ids ) ) {
			$metadata['vendor_id'] = $vendor_ids[0];
		} else {
			$metadata['vendor_ids'] = implode( ',', $vendor_ids );
		}

		$intent = CheckoutIntent::cart(
			$lines,
			$totals['total'],
			wpss_get_currency(),
			$buyer_id,
			$metadata
		);

		$intent->addons_total = $totals['addons_total'];
		$intent->taxable_base = $totals['subtotal'] + $totals['addons_total'];
		$intent->tax          = $totals['tax'];

		return $this->check_order_limits( $totals['total'] ) ?? $intent;
	}

	/**
	 * Enforce the owner's minimum / maximum order amount.
	 *
	 * Both are standalone options from Settings > General. 0 means no limit on
	 * that side. Applies to new purchases only - paying a phase of an existing
	 * order is a partial amount the limits were never meant to cover.
	 *
	 * @since 1.7.1
	 *
	 * @param float $total Checkout total, tax included.
	 * @return \WP_Error|null Error when outside the range, null when fine.
	 */
	private function check_order_limits( float $total ): ?\WP_Error {
		// The rule itself lives in wpss_check_order_limits() so the Offline rail,
		// which never routes through resolve(), can apply the same one.
		return wpss_check_order_limits( $total, 'intent' );
	}

	/**
	 * Price one service line from ids alone: package, quantity, add-ons, tax.
	 *
	 * THE line pricer. Checkout, the cart, the app's payment intent, the order
	 * modal quote and the admin manual order all price through here, so a
	 * buyer is charged the same on every surface. Nothing a request says about
	 * money is read - only which package, how many, and which add-ons.
	 *
	 * @since 1.8.0
	 *
	 * @param int   $service_id  Service post ID.
	 * @param int   $package_ref Stable package id, or legacy index.
	 * @param int   $quantity    How many of the package.
	 * @param mixed $selection   Add-on selection (see wpss_normalize_addon_selection()).
	 * @return array<string, mixed>|\WP_Error Line: service_id, vendor_id, package_id (index), package, quantity,
	 *                                        subtotal, addons, addons_total, delivery_days_extra, tax, tax_rate,
	 *                                        net, tax_included, total.
	 */
	public static function price_service_line( int $service_id, int $package_ref, int $quantity, $selection ) {
		// Paused: the page stays up but no new order is taken. The service page
		// only hid its button, so /service-checkout/{id}/ still sold a paused
		// service (Basecamp 10337190248, order 6962). Every purchase path prices
		// through here - checkout, cart, REST cart and quote, the gateways - so
		// the refusal is here once.
		if ( 'paused' === wpss_get_service_status( $service_id ) ) {
			return new \WP_Error( 'wpss_service_paused', __( 'This service is not taking new orders right now.', 'wp-sell-services' ), array( 'status' => 409 ) );
		}

		$resolved = wpss_resolve_service_package( $service_id, $package_ref );

		// A service sold without packages is bought at its starting price.
		if ( null === $resolved && ! wpss_get_service_packages( $service_id ) ) {
			$resolved = array(
				'package' => array( 'price' => (float) get_post_meta( $service_id, '_wpss_starting_price', true ) ),
				'index'   => 0,
			);
		}

		if ( null === $resolved ) {
			return new \WP_Error( 'wpss_invalid_package', __( 'Invalid package.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		$quantity = max( 1, $quantity );
		$subtotal = round( (float) ( $resolved['package']['price'] ?? 0 ) * $quantity, wpss_get_currency_decimals() );
		$addons   = wpss_price_addons( $service_id, $selection, $subtotal );

		if ( is_wp_error( $addons ) ) {
			return $addons;
		}

		$vendor_id = (int) get_post_field( 'post_author', $service_id );
		$line      = self::price_line( $service_id, $subtotal, (float) $addons['addons_total'], $vendor_id );

		return array(
			'service_id'          => $service_id,
			'vendor_id'           => $vendor_id,
			'package_id'          => (int) $resolved['index'],
			'package'             => $resolved['package'],
			'quantity'            => $quantity,
			'addons'              => $addons['addons'],
			'delivery_days_extra' => (int) $addons['delivery_days_extra'],
		) + $line;
	}

	/**
	 * Price one line the way StandaloneOrderProvider::create_order() prices its
	 * row: subtotal plus add-ons is the taxable base, tax through the shared
	 * helper. Every caller (single, cart, and an order created from an
	 * accepted proposal) and the order row are the same arithmetic by
	 * construction - a proposal order used to be inserted untaxed while
	 * catalog checkout charged the configured rate (Basecamp F23).
	 *
	 * @since 1.7.1
	 *
	 * @param int   $service_id   Service ID (0 for a custom request with none).
	 * @param float $subtotal     Package / proposal price (times quantity).
	 * @param float $addons_total Add-ons total.
	 * @param int   $vendor_id    Vendor, when the service cannot say (proposals).
	 * @return array{subtotal:float,addons_total:float,tax:float,tax_rate:float,total:float,net:float,tax_included:bool,tax_label:string}
	 */
	public static function price_line( int $service_id, float $subtotal, float $addons_total, int $vendor_id = 0 ): array {
		$vendor_id = $vendor_id > 0 ? $vendor_id : (int) get_post_field( 'post_author', $service_id );
		$tax       = wpss_calculate_tax( $subtotal + $addons_total, $vendor_id, $service_id );

		return array(
			'subtotal'     => $subtotal,
			'addons_total' => $addons_total,
			'tax'          => (float) $tax['amount'],
			'tax_rate'     => (float) $tax['rate'],
			'total'        => (float) $tax['total'],
			'net'          => (float) $tax['net'],
			'tax_included' => (bool) $tax['included'],
			'tax_label'    => (string) $tax['label'],
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

		$service = get_post( $service_id );
		if ( ! $service || 'wpss_service' !== $service->post_type || 'publish' !== $service->post_status ) {
			return new \WP_Error( 'wpss_invalid_service', __( 'Invalid service.', 'wp-sell-services' ) );
		}

		// Nobody buys from themselves; every rail used to check this on its own
		// (or, on Stripe, not at all).
		if ( (int) $service->post_author === $buyer_id ) {
			return new \WP_Error( 'wpss_own_service', __( 'You cannot purchase your own service.', 'wp-sell-services' ) );
		}

		// Priced from ids alone by the one line pricer: package (stable id or
		// index), quantity, add-ons as the vendor set them, then tax. Charge
		// what the buyer was shown (Basecamp 10254444011) - the template, this
		// intent and the order row all read the same line.
		$selection = self::request_selection( $request );
		$line      = self::price_service_line( $service_id, absint( $request['package_id'] ?? 0 ), max( 1, absint( $request['quantity'] ?? 1 ) ), $selection );

		if ( is_wp_error( $line ) ) {
			return $line;
		}

		if ( $line['subtotal'] <= 0 ) {
			return new \WP_Error( 'wpss_invalid_amount', __( 'Invalid amount.', 'wp-sell-services' ) );
		}

		$intent = CheckoutIntent::single(
			$service_id,
			(int) $line['package_id'],
			$line['addons'],
			$line['addons_total'],
			$line['total'],
			wpss_get_currency(),
			$buyer_id,
			array(
				'service_id'  => $service_id,
				'package_id'  => (int) $line['package_id'],
				'quantity'    => (int) $line['quantity'],
				'customer_id' => $buyer_id,
				// Every order type names its vendor. Without this only
				// pay-order intents did, so a split rail (Pro Connect) never
				// split a catalog purchase.
				'vendor_id'   => (int) $service->post_author,
			) + self::selection_metadata( $line['addons'] ),
			$line['subtotal'] + $line['addons_total']
		);

		$intent->tax = $line['tax'];

		return $this->check_order_limits( $line['total'] ) ?? $intent;
	}

	/**
	 * The add-on selection a resolve() request carries, in any accepted key.
	 *
	 * Accepts addon_sel (the full selection, JSON), addons (array or JSON), or the
	 * legacy addon_ids CSV. Never read from a superglobal: rails build the
	 * request with request_from_post() or from their own stored metadata.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $request Request.
	 * @return array<int, array<string, mixed>>
	 */
	public static function request_selection( array $request ): array {
		foreach ( array( 'addon_sel', 'addons', 'addon_ids' ) as $key ) {
			if ( isset( $request[ $key ] ) && '' !== $request[ $key ] && array() !== $request[ $key ] ) {
				return wpss_normalize_addon_selection( $request[ $key ] );
			}
		}

		return array();
	}

	/**
	 * Build a resolve() request from a checkout POST - the one reader of it.
	 *
	 * @since 1.8.0
	 *
	 * @param array<string, mixed> $post Raw $_POST (unslashed here).
	 * @return array<string, mixed>
	 */
	public static function request_from_post( array $post ): array {
		$post = wp_unslash( $post );

		return array(
			'pay_order'         => absint( $post['pay_order'] ?? 0 ),
			'is_multi_checkout' => ! empty( $post['is_multi_checkout'] ),
			'service_id'        => absint( $post['service_id'] ?? 0 ),
			'package_id'        => absint( $post['package_id'] ?? 0 ),
			'quantity'          => max( 1, absint( $post['quantity'] ?? 1 ) ),
			'addon_sel'         => isset( $post['addon_sel'] ) ? (string) $post['addon_sel'] : '',
			'addon_ids'         => isset( $post['addon_ids'] ) ? sanitize_text_field( (string) $post['addon_ids'] ) : '',
		);
	}

	/**
	 * Metadata that lets a return leg re-price the same add-ons.
	 *
	 * The addon_ids key stays compact for rails with small metadata fields (PayPal's
	 * custom_id is 127 characters); addon_sel carries quantity, option and text
	 * when there is any, for rails that can hold it.
	 *
	 * @since 1.8.0
	 *
	 * @param array<int, array<string, mixed>> $addons Priced add-on lines.
	 * @return array<string, string>
	 */
	public static function selection_metadata( array $addons ): array {
		if ( empty( $addons ) ) {
			return array();
		}

		$meta  = array( 'addon_ids' => implode( ',', array_column( $addons, 'id' ) ) );
		$plain = true;

		foreach ( $addons as $addon ) {
			if ( (int) ( $addon['quantity'] ?? 1 ) > 1 || '' !== (string) ( $addon['option'] ?? '' ) || '' !== (string) ( $addon['text'] ?? '' ) ) {
				$plain = false;
				break;
			}
		}

		if ( ! $plain ) {
			$meta['addon_sel'] = (string) wp_json_encode(
				array_map(
					static fn( array $a ): array => array(
						'id'       => (int) $a['id'],
						'quantity' => (int) ( $a['quantity'] ?? 1 ),
						'option'   => (string) ( $a['option'] ?? '' ),
						'text'     => (string) ( $a['text'] ?? '' ),
					),
					$addons
				)
			);
		}

		return $meta;
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
		/*
		 * Bind the charge to the intent it is paying for, before anything is
		 * created or marked paid.
		 *
		 * Nothing compared the two. The gateway confirmed only that SOME
		 * PaymentIntent reached 'succeeded'; the intent was then resolved from
		 * the request's own service_id / package_id / pay_order, and
		 * settle_single() priced the order from the server-side intent. So a
		 * buyer could pay for a cheap service, keep the pi_... id the JS
		 * response exposes, re-post it naming an expensive one, and receive a
		 * $5,000 order for a $5 charge - with the vendor credited in full on
		 * completion (Basecamp 10321653099).
		 *
		 * The docblock on settle_single() has claimed since 1.3.0 that
		 * $charged_amount "is checked against the intent below". It never was.
		 * It is now, and this is the line that makes that comment true.
		 *
		 * Placed here rather than in each gateway deliberately: PayPal already
		 * does this check itself (PayPalGateway.php:711) and Stripe and
		 * Razorpay did not. One guard on the shared seam closes both, covers
		 * all three intent kinds, and covers any rail added later. Every caller
		 * refunds on a false return, so a mismatched charge is given back.
		 */
		if ( strtoupper( $charged_currency ) !== strtoupper( $intent->currency )
			|| ! wpss_amounts_match( $charged_amount, $intent->amount, $intent->currency ) ) {
			wpss_log(
				sprintf(
					'%s charge %s took %s %s but the checkout intent is %s %s. Refusing to settle.',
					$gateway_id,
					$transaction_id,
					$charged_currency,
					$charged_amount,
					$intent->currency,
					$intent->amount
				),
				'error'
			);

			return array(
				'success' => false,
				'error'   => __( 'The paid amount does not match the order total.', 'wp-sell-services' ),
			);
		}

		/*
		 * One charge, one set of orders.
		 *
		 * mark_as_paid() is idempotent per order, but create_order() had no
		 * transaction_id dedupe and neither settle path consulted
		 * get_by_transaction_ids() - only the webhook did
		 * (OrderWorkflowManager.php:875). So re-posting the same succeeded
		 * pi_... minted a fresh paid order every time, and on the AJAX rail
		 * each one was priced from the intent (Basecamp 10321653385).
		 *
		 * Returning the existing order rather than an error: a client retrying
		 * a settle it never saw the response to is doing the right thing, and
		 * should get the order it already paid for. KIND_ORDER is already safe
		 * through resolve_order()'s 'pending_payment' check, but it costs
		 * nothing to cover it here too.
		 */
		$existing = ( new \WPSellServices\Database\Repositories\OrderRepository() )
			->get_by_transaction_ids( array( $transaction_id ) );

		if ( ! empty( $existing ) ) {
			$first = $existing[0];

			wpss_log(
				sprintf(
					'%s transaction %s has already settled into %d order(s); returning the existing order instead of creating another.',
					$gateway_id,
					$transaction_id,
					count( $existing )
				),
				'warning'
			);

			return array(
				'success'      => true,
				'order_id'     => (int) $first->id,
				'order_ids'    => array_map( static fn( $row ) => (int) $row->id, $existing ),
				'order_number' => (string) $first->order_number,
				'redirect_url' => wpss_get_post_checkout_url( (int) $first->id, wpss_get_order_requirements_url( (int) $first->id ), 'intent' ),
				'duplicate'    => true,
			);
		}

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
			'redirect_url' => wpss_get_post_checkout_url( (int) $order->id, wpss_get_order_requirements_url( $order->id ), 'intent' ),
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
				 * $charged_amount is still what the gateway reports it took. It
				 * is verified against the intent's amount and currency in
				 * settle(), before this method is reached; it is simply not the
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
		$cart = wpss_get_user_cart( (int) $intent->buyer_id );
		if ( ! empty( $cart ) ) {
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
			'redirect_url' => wpss_get_post_checkout_url( (int) $order->id, wpss_get_order_requirements_url( $order->id ), 'intent' ),
		);
	}
}
