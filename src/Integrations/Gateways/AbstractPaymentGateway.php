<?php
/**
 * Abstract Payment Gateway
 *
 * Defaults for the parts of PaymentGatewayInterface most gateways do not customise, so a
 * third-party gateway overrides only what it needs. Extend this instead of implementing the
 * interface directly.
 *
 * @package WPSellServices\Integrations\Gateways
 * @since   1.9.0
 */

declare(strict_types=1);

namespace WPSellServices\Integrations\Gateways;

use WPSellServices\Checkout\CheckoutIntent;
use WPSellServices\Integrations\Contracts\PaymentGatewayInterface;
use WP_Error;
use WP_REST_Response;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for payment gateways.
 *
 * @since 1.9.0
 */
abstract class AbstractPaymentGateway implements PaymentGatewayInterface {

	/**
	 * Features this gateway has, read through supports().
	 *
	 * - manual_confirmation: the money arrives outside the site and the owner confirms it (Mark as paid,
	 *   buyer proof of payment). Unpaid sub-orders on it are never swept as abandoned.
	 * - redirect_return: the buyer may come back from the provider's page, handled by
	 *   reconcile_redirect_return() when the return URL carries wpss_return=<id>.
	 * - verified_confirmation: confirm and return legs ask the provider whether the money was taken,
	 *   so they may finish a payment after the gateway is switched off.
	 *
	 * @since 2.0.0
	 *
	 * @var string[]
	 */
	protected array $supports = array();

	/**
	 * Whether this gateway has a feature. Callers ask the feature, never the gateway's name.
	 *
	 * @since 2.0.0
	 *
	 * @param string $feature Feature name, see $supports.
	 * @return bool
	 */
	public function supports( string $feature ): bool {
		return in_array( $feature, $this->supports, true );
	}

	/**
	 * Words on the checkout button. "Pay $X" for a gateway that takes the money now.
	 *
	 * @since 2.0.0
	 *
	 * @param float  $total    Payable total.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public function get_checkout_button_label( float $total, string $currency ): string {
		/* translators: %s: formatted total */
		return sprintf( __( 'Pay %s', 'wp-sell-services' ), wp_strip_all_tags( wpss_format_price( $total, $currency ) ) );
	}

	/**
	 * Settle a buyer's return from the provider's page. Gateways that support redirect_return override it.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, string> $query The return URL's query arguments.
	 * @return array{status: string, order_id: int, message: string}
	 */
	public function reconcile_redirect_return( array $query ): array {
		return array(
			'status'   => 'processing',
			'order_id' => 0,
			'message'  => __( 'We are confirming your payment. Please check your orders in a moment, and do not pay again.', 'wp-sell-services' ),
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * Falls back to the `wpss_rest_create_payment_intent` filter, which is how a gateway added
	 * before 1.9.0 handled REST payments.
	 */
	public function rest_create_intent( CheckoutIntent $intent, array $request ) {
		/** This filter is documented in docs/website/developer-guide/hooks-filters.md. */
		$result = apply_filters(
			'wpss_rest_create_payment_intent',
			null,
			$this,
			(float) $intent->amount,
			(string) $intent->currency,
			(int) ( $request['service_id'] ?? 0 ),
			(int) ( $request['package_id'] ?? 0 ),
			(int) ( $request['pay_order'] ?? 0 )
		);

		if ( null !== $result ) {
			return $result;
		}

		return new \WP_Error( 'unsupported_gateway', __( 'This gateway is not supported for REST payments.', 'wp-sell-services' ), array( 'status' => 400 ) );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Falls back to the `wpss_rest_confirm_payment` filter, like rest_create_intent().
	 */
	public function rest_confirm_payment( array $params ) {
		/** This filter is documented in docs/website/developer-guide/hooks-filters.md. */
		$result = apply_filters(
			'wpss_rest_confirm_payment',
			null,
			$this,
			(string) ( $params['payment_id'] ?? '' ),
			(int) ( $params['service_id'] ?? 0 ),
			(int) ( $params['package_id'] ?? 0 ),
			(int) ( $params['pay_order'] ?? 0 )
		);

		if ( null !== $result ) {
			return $result;
		}

		return new \WP_Error( 'unsupported_gateway', __( 'This gateway does not support REST confirmation.', 'wp-sell-services' ), array( 'status' => 400 ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_settings_card(): ?array {
		return array(
			'title'        => $this->get_name(),
			'description'  => $this->get_description(),
			'option_group' => 'wpss_' . $this->get_id() . '_settings',
			'order'        => 50,
		);
	}

	/**
	 * Mark an existing order paid from a verified gateway payment.
	 *
	 * Through the same settle() the website checkout uses, not a second copy of
	 * its checks: it compares amount and currency, and holds the per-payment
	 * lock while asking whether this payment has already paid an order. The
	 * copy that lived here checked the amount and nothing else, so one payment
	 * confirmed every equal-priced order the buyer had (Basecamp 10375173905).
	 *
	 * @since 1.8.0
	 *
	 * @param object $order          The order to pay; its owner is already checked.
	 * @param string $gateway_id     Gateway slug.
	 * @param string $transaction_id Verified gateway payment id.
	 * @param float  $amount         Verified paid amount.
	 * @param string $currency       Verified paid currency.
	 * @param int    $charged_buyer  Member the gateway's stored metadata says started the payment.
	 * @return true|\WP_Error
	 */
	protected function settle_pay_order( object $order, string $gateway_id, string $transaction_id, float $amount, string $currency, int $charged_buyer ) {
		$checkout = new \WPSellServices\Checkout\CheckoutIntentService();
		$intent   = $checkout->resolve( array( 'pay_order' => (int) $order->id ), (int) $order->customer_id );

		if ( is_wp_error( $intent ) ) {
			return new \WP_Error( $intent->get_error_code(), $intent->get_error_message(), array( 'status' => 400 ) );
		}

		$settle = $checkout->settle( $intent, $gateway_id, $transaction_id, $amount, $currency, $charged_buyer );

		if ( \WPSellServices\Checkout\CheckoutIntentService::PAYMENT_NOT_YOURS === ( $settle['code'] ?? '' ) ) {
			return new \WP_Error( $settle['code'], $settle['error'], array( 'status' => 403 ) );
		}

		if ( empty( $settle['success'] ) ) {
			return new \WP_Error( 'rest_amount_mismatch', $settle['error'] ?? __( 'The paid amount does not match the order total.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// settle() answers a payment it has seen before with the order(s) that
		// payment already paid. If this order is not one of them, the payment is spent.
		if ( ! in_array( (int) $order->id, array_map( 'intval', (array) ( $settle['order_ids'] ?? array( $settle['order_id'] ?? 0 ) ) ), true ) ) {
			return new \WP_Error( 'wpss_payment_already_used', __( 'This payment has already been used for another order.', 'wp-sell-services' ), array( 'status' => 409 ) );
		}

		return true;
	}

	/**
	 * Open the order a manual payment will settle: the buyer's existing order (pay_order), put on this
	 * rail, or a new pending-payment order for one service.
	 *
	 * The one pending-order writer for the checkout form and the app (Basecamp 10551488242). Out-of-band
	 * rails settle later, so the order remembers which one it waits on: the admin confirmation box and
	 * the buyer's proof upload key off payment_method (Basecamp 10208094640).
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $request Checkout request: pay_order, or service_id, package_id, quantity, add-ons.
	 * @return object|WP_Error The order.
	 */
	public function open_pending_order( array $request ) {
		$pay_order = absint( $request['pay_order'] ?? 0 );

		if ( ! $pay_order ) {
			return $this->create_service_order(
				absint( $request['service_id'] ?? 0 ),
				absint( $request['package_id'] ?? 0 ),
				max( 1, absint( $request['quantity'] ?? 1 ) ),
				\WPSellServices\Checkout\CheckoutIntentService::request_selection( $request ),
				$this->get_id()
			);
		}

		$order = wpss_get_order( $pay_order );

		if ( ! $order || (int) $order->customer_id !== get_current_user_id() ) {
			return new WP_Error( 'wpss_invalid_order', __( 'Invalid order.', 'wp-sell-services' ), array( 'status' => 403 ) );
		}

		$refusal = wpss_get_order_payment_refusal( $order );
		if ( '' !== $refusal ) {
			return new WP_Error( 'wpss_order_not_payable', $refusal, array( 'status' => 409 ) );
		}

		if ( ! wpss_record_pending_payment_method( $pay_order, $this->get_id() ) ) {
			return new WP_Error( 'wpss_payment_method_not_recorded', __( 'Could not record the payment method on this order.', 'wp-sell-services' ), array( 'status' => 500 ) );
		}

		return wpss_get_order( $pay_order );
	}

	/**
	 * Create a pending-payment order for one service, priced by the one pricer.
	 *
	 * Shared by the checkout form (AJAX) and the app (POST
	 * /payments/create-intent), so both refuse a self-purchase, price the
	 * package, quantity and add-ons the same way, and apply the owner's order
	 * limits. The REST copy created the order with its own flat package price
	 * (Basecamp 10336467402).
	 *
	 * @since 1.8.0
	 * @since 2.0.0 Moved from OfflineGateway to the base, the one pending-order writer.
	 *
	 * @param int                              $service_id Service ID.
	 * @param int                              $package_id Stable package id or legacy index.
	 * @param int                              $quantity   Quantity.
	 * @param array<int, array<string, mixed>> $selection  Add-on selection.
	 * @param string                           $gateway_id Rail that will collect the money.
	 * @return object|\WP_Error The order.
	 */
	public function create_service_order( int $service_id, int $package_id, int $quantity, array $selection, string $gateway_id ) {
		$service_post = get_post( $service_id );

		if ( ! $service_post || ! wpss_get_service( $service_id ) ) {
			return new \WP_Error( 'wpss_invalid_service', __( 'Service not found.', 'wp-sell-services' ), array( 'status' => 404 ) );
		}

		if ( (int) $service_post->post_author === get_current_user_id() ) {
			return new \WP_Error( 'wpss_own_service', __( 'You cannot purchase your own service.', 'wp-sell-services' ), array( 'status' => 400 ) );
		}

		// A new purchase on every offline door (checkout form, REST create-intent,
		// Pro /payments/offline/submit): the standing rule resolve() applies.
		$standing = wpss_account_status_block( get_current_user_id() );
		if ( $standing ) {
			return $standing;
		}

		// Priced by the one line pricer, from ids alone - package (stable id or
		// index), quantity and the add-ons as the vendor set them.
		$line = \WPSellServices\Checkout\CheckoutIntentService::price_service_line( $service_id, $package_id, max( 1, $quantity ), $selection );

		if ( is_wp_error( $line ) ) {
			return $line;
		}

		// The owner's min/max order amount, the same rule Stripe and PayPal
		// get from CheckoutIntentService::resolve() (Basecamp 10304350394).
		$limit_error = wpss_check_order_limits( (float) $line['subtotal'] + (float) $line['addons_total'], $gateway_id );

		if ( null !== $limit_error ) {
			return $limit_error;
		}

		// subtotal = package price only; addons_total is separate - StandaloneOrderProvider sums them.
		$order = wpss_get_order_provider()->create_order(
			array(
				'service_id'     => $service_id,
				'package_id'     => (int) $line['package_id'],
				'quantity'       => (int) $line['quantity'],
				'customer_id'    => get_current_user_id(),
				'subtotal'       => (float) $line['subtotal'],
				'addons'         => $line['addons'],
				'addons_total'   => (float) $line['addons_total'],
				'currency'       => wpss_get_currency(),
				'payment_method' => $gateway_id,
			)
		);

		return $order ? $order : new \WP_Error( 'wpss_order_failed', __( 'Failed to create order.', 'wp-sell-services' ), array( 'status' => 500 ) );
	}

	/**
	 * Create order directly for offline/test gateways.
	 *
	 * @param array<string, mixed> $checkout_request Resolve() request.
	 * @return WP_REST_Response|WP_Error
	 */
	protected function create_offline_rest_order( array $checkout_request ) {
		$pay_order = (int) $checkout_request['pay_order'];
		$order     = $this->open_pending_order( $checkout_request );

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		if ( $pay_order ) {
			return new WP_REST_Response(
				array(
					'gateway'      => $this->get_id(),
					'order_id'     => (int) $order->id,
					'order_number' => $order->order_number,
					'status'       => 'pending_payment',
					'message'      => __( 'Please complete your payment using the offline instructions. Your order will be activated once payment is confirmed.', 'wp-sell-services' ),
				),
				201
			);
		}

		/**
		 * Fires when an offline order is created via REST API.
		 *
		 * @param int    $order_id   Order ID.
		 * @param object $order      Order object.
		 * @param string $gateway_id Gateway ID.
		 */
		do_action( 'wpss_rest_offline_order_created', $order->id, $order, $this->get_id() );

		return new WP_REST_Response(
			array(
				'gateway'      => $this->get_id(),
				'order_id'     => (int) $order->id,
				'order_number' => $order->order_number,
				'status'       => 'pending_payment',
			),
			201
		);
	}

	/**
	 * Answer a provider's webhook and remember it as handled, unless it should come again.
	 *
	 * A result with `retry` (a settle or refund that failed part-way) answers 500 and is not
	 * remembered, so the provider delivers the event again. Anything else answers 200 and is
	 * remembered for 48 hours, so a repeat delivery is not applied twice.
	 *
	 * @since 1.9.4 Lifted from StripeGateway so every gateway has it (Basecamp 10550988500).
	 *
	 * @param array<string, mixed> $result    What the gateway's handler returned.
	 * @param string               $dedup_key Transient key for this event, '' when it has no id.
	 * @return void Sends the response and exits.
	 */
	protected function respond_to_webhook( array $result, string $dedup_key ): void {
		if ( ! empty( $result['retry'] ) ) {
			wpss_log( sprintf( '%s webhook not handled (%s); answering 500 so it is sent again.', $this->get_id(), (string) ( $result['message'] ?? '' ) ), 'warning' );
			status_header( 500 );
			echo wp_json_encode( $result );
			exit;
		}

		if ( '' !== $dedup_key ) {
			set_transient( $dedup_key, true, 48 * HOUR_IN_SECONDS );
		}

		status_header( 200 );
		echo wp_json_encode( $result );
		exit;
	}

	/**
	 * Mark the order a webhook names as paid, and say what the provider should do next.
	 *
	 * A payment for the wrong amount is given back. One already spent elsewhere, already
	 * given back, or for an order that is gone or closed is final. A write that failed is
	 * a `retry`, so the provider sends the event again.
	 *
	 * @since 1.9.4
	 *
	 * @param int    $order_id       Order the webhook names.
	 * @param string $transaction_id Provider payment id.
	 * @param float  $amount         Amount the provider reports, in major units.
	 * @param string $currency       Currency the provider reports.
	 * @return array{success: bool, message: string, retry?: bool}
	 */
	protected function settle_webhook_payment( int $order_id, string $transaction_id, float $amount, string $currency ): array {
		$refusal = '';

		if ( ( new \WPSellServices\Checkout\CheckoutIntentService() )->settle_webhook_order( $order_id, $this->get_id(), $transaction_id, $amount, $currency, $refusal ) ) {
			return array(
				'success' => true,
				'message' => 'Order paid.',
			);
		}

		if ( 'mismatch' === $refusal ) {
			$given_back = $this->give_back_unsettled( $transaction_id );

			return array(
				'success' => $given_back,
				'message' => $given_back ? 'Payment does not match the order; given back.' : 'Payment does not match the order and could not be given back.',
				'retry'   => ! $given_back,
			);
		}

		return array(
			'success' => '' !== $refusal,
			'message' => '' !== $refusal ? "Not marked paid ({$refusal})." : 'Marking the order paid failed.',
			'retry'   => '' === $refusal,
		);
	}

	/**
	 * Give back a payment that could not become an order.
	 *
	 * @since 1.9.4
	 *
	 * @param string $transaction_id Provider payment id.
	 * @return bool Whether it is given back, or there was nothing to give back.
	 */
	protected function give_back_unsettled( string $transaction_id ): bool {
		$refunded = false;
		$asked    = ( new \WPSellServices\Checkout\CheckoutIntentService() )->refund_unsettled(
			$this->get_id(),
			$transaction_id,
			function () use ( $transaction_id, &$refunded ): bool {
				$refunded = ! empty( $this->process_refund( $transaction_id )['success'] );

				return $refunded;
			}
		);

		return ! $asked || $refunded;
	}

	/**
	 * Record a lost card dispute (chargeback) as a refund of the disputed amount.
	 *
	 * Owner rule: a lost dispute is a refund on the ledger, a won one changes nothing. It enters the
	 * one refund seam (handle_gateway_refund()), which reverses the vendor's share, lets their balance
	 * go negative when they already withdrew, and notifies as any refund does. The amount is this
	 * dispute's own, not a running total, and the dispute id is the refund id, so a resend adds nothing.
	 *
	 * @since 1.9.4 (Basecamp 10550988459)
	 *
	 * @param string $transaction_id Payment id the order was paid with.
	 * @param float  $amount         Disputed amount the platform lost, major units.
	 * @param string $currency       Currency code.
	 * @param string $dispute_id     Provider dispute id.
	 * @param int    $order_id       The order, when the provider names it.
	 * @return array{success: bool, message: string}
	 */
	protected function report_lost_dispute( string $transaction_id, float $amount, string $currency, string $dispute_id, int $order_id = 0 ): array {
		if ( '' === $transaction_id || $amount <= 0 ) {
			return array(
				'success' => true,
				'message' => 'Lost dispute had no payment or amount to record.',
			);
		}

		wpss_log( sprintf( '%s chargeback lost: dispute %s took %s %s from payment %s; recording it as a refund.', $this->get_id(), $dispute_id, (string) $amount, $currency, $transaction_id ), 'warning' );

		do_action(
			'wpss_gateway_refund_received',
			$this->get_id(),
			$transaction_id,
			$amount,
			array_filter(
				array(
					'refund_id' => $dispute_id,
					'currency'  => strtoupper( $currency ),
					'order_id'  => $order_id,
				)
			)
		);

		return array(
			'success' => true,
			'message' => 'Lost dispute recorded as a refund.',
		);
	}

	/**
	 * Record a bank chargeback that has just been opened on a payment.
	 *
	 * Owner rule (Basecamp 10553225092): the site owner is emailed and the order shows "Chargeback open";
	 * the vendor is not told and no money moves until it is lost. A resent event adds nothing, and an
	 * event for a payment we never took is acknowledged and ignored.
	 *
	 * @since 2.0.0
	 *
	 * @param string $transaction_id Payment id the order was paid with.
	 * @param float  $amount         Disputed amount, major units.
	 * @param string $currency       Currency code.
	 * @param string $dispute_id     Provider dispute id.
	 * @param int    $due_by         When evidence is due, Unix time, or 0.
	 * @return array{success: bool, message: string}
	 */
	protected function report_opened_dispute( string $transaction_id, float $amount, string $currency, string $dispute_id, int $due_by = 0 ): array {
		$marked = ( new \WPSellServices\Services\ChargebackService() )->opened(
			$this->get_id(),
			$transaction_id,
			$dispute_id,
			$amount,
			$currency,
			$due_by > 0 ? gmdate( 'Y-m-d H:i:s', $due_by ) : ''
		);

		if ( $marked ) {
			wpss_log( sprintf( '%s chargeback opened: dispute %s on payment %s (orders %s).', $this->get_id(), $dispute_id, $transaction_id, implode( ', ', $marked ) ), 'warning' );
		}

		return array(
			'success' => true,
			'message' => $marked ? 'Chargeback recorded on the order.' : 'Chargeback already recorded, or no order for this payment.',
		);
	}

	/**
	 * Clear the "Chargeback open" mark when the provider closes the dispute.
	 *
	 * Moves no money: a lost dispute is settled by report_lost_dispute() (or the provider's own
	 * reversal event), a won one changes nothing.
	 *
	 * @since 2.0.0
	 *
	 * @param string $transaction_id Payment id the order was paid with.
	 * @param string $dispute_id     Provider dispute id.
	 * @param string $outcome        won, lost or closed.
	 * @return array{success: bool, message: string}
	 */
	protected function report_closed_dispute( string $transaction_id, string $dispute_id, string $outcome ): array {
		$closed = ( new \WPSellServices\Services\ChargebackService() )->closed( $transaction_id, $dispute_id, $outcome );

		return array(
			'success' => true,
			'message' => $closed ? 'Chargeback closed on the order.' : 'No open chargeback for this dispute.',
		);
	}
}
