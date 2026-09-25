<?php
/**
 * Checkout: payment method card.
 *
 * The ONE gateway picker, shared by the single-service / pay-order checkout
 * and the multi-item cart checkout. Both used to carry their own copy and the
 * two had started to drift; every change to how a buyer picks a gateway now
 * lands here once. Selection behaviour lives in assets/js/checkout.js.
 *
 * Gateways that own their own submit (Stripe, PayPal, Razorpay) mark their
 * rendered form with data-wpss-own-submit; keep the .wpss-gateway-form
 * container and its data-gateway attribute, the scripts look for both.
 *
 * Override by copying to yourtheme/wp-sell-services/checkout/payment-methods.php.
 *
 * @package WPSellServices
 * @since   1.8.0
 *
 * @var array<string,object> $wpss_gateways Enabled gateways keyed by id.
 * @var float                $wpss_amount   Amount handed to each gateway form.
 * @var string               $wpss_currency Currency code.
 * @var int                  $wpss_order_id Existing order being paid, 0 for a new purchase.
 */

defined( 'ABSPATH' ) || exit;

$wpss_gateways = isset( $wpss_gateways ) && is_array( $wpss_gateways ) ? $wpss_gateways : array();
$wpss_amount   = isset( $wpss_amount ) ? (float) $wpss_amount : 0.0;
$wpss_currency = isset( $wpss_currency ) ? (string) $wpss_currency : wpss_get_currency();
$wpss_order_id = isset( $wpss_order_id ) ? (int) $wpss_order_id : 0;
?>
<div class="wpss-card">
	<div class="wpss-card__header">
		<h3 class="wpss-card__title"><?php esc_html_e( 'Payment Method', 'wp-sell-services' ); ?></h3>
	</div>
	<div class="wpss-card__body">
		<div class="wpss-co-methods">
			<?php
			// The first method is chosen for the buyer: with one method, making
			// them tick the only option (and hiding its instructions until they
			// do) was a dead end the browser's own "select one" message guarded.
			$wpss_first = true;
			foreach ( $wpss_gateways as $wpss_gateway_id => $wpss_gateway ) :
				$wpss_label = wpss_checkout_button_label( $wpss_gateway, $wpss_amount, $wpss_currency );
				?>
				<div class="wpss-co-method<?php echo $wpss_first ? ' wpss-co-method--active' : ''; ?>" data-method="<?php echo esc_attr( $wpss_gateway_id ); ?>">
					<label class="wpss-co-method__label">
						<input type="radio" name="payment_method" value="<?php echo esc_attr( $wpss_gateway_id ); ?>" data-button-label="<?php echo esc_attr( $wpss_label ); ?>" required <?php checked( $wpss_first ); ?>>
						<?php echo esc_html( $wpss_gateway->get_name() ); ?>
					</label>
					<div class="wpss-co-method__form wpss-gateway-form" data-gateway="<?php echo esc_attr( $wpss_gateway_id ); ?>"<?php echo $wpss_first ? '' : ' style="display: none;"'; ?>>
						<?php
						echo $wpss_gateway->render_payment_form( $wpss_amount, $wpss_currency, $wpss_order_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gateway-owned markup, escaped by each gateway.
						?>
					</div>
				</div>
				<?php
				$wpss_first = false;
			endforeach;
			?>
		</div>

		<?php
		// The same Pay button again, right where the buyer finishes the form:
		// on desktop and tablet the summary's button had scrolled out of view
		// by the time the payment method was on screen (Basecamp 10337204220).
		// Same form, same handler, same validation.
		$wpss_first_gateway = reset( $wpss_gateways );
		if ( $wpss_first_gateway ) :
			?>
			<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--lg wpss-btn--full wpss-checkout-button wpss-co-methods__submit">
				<span class="wpss-checkout-button__text"><?php echo esc_html( wpss_checkout_button_label( $wpss_first_gateway, $wpss_amount, $wpss_currency ) ); ?></span>
			</button>
		<?php endif; ?>
	</div>
</div>
