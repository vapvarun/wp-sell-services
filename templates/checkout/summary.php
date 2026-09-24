<?php
/**
 * Checkout: order summary card with the Pay button.
 *
 * Shared by the single-service / pay-order checkout and the multi-item cart
 * checkout. The two differ only in WHICH lines they list (one package plus
 * add-ons, one total for a pay-order, one line per cart item), so the caller
 * builds the lines and this template owns the card, the total, the payable
 * total hook and the submit button.
 *
 * Override by copying to yourtheme/wp-sell-services/checkout/summary.php.
 *
 * @package WPSellServices
 * @since   1.8.0
 *
 * @var string $wpss_title    Card title.
 * @var array  $wpss_lines    Lines, each: label (string), amount (float), and
 *                            optionally note (string, shown as a caption),
 *                            type ('addon'|'tax' modifier) and strong (bool).
 * @var float  $wpss_total    Payable total.
 * @var string $wpss_currency Currency code.
 */

defined( 'ABSPATH' ) || exit;

$wpss_title    = isset( $wpss_title ) ? (string) $wpss_title : __( 'Order Summary', 'wp-sell-services' );
$wpss_lines    = isset( $wpss_lines ) && is_array( $wpss_lines ) ? $wpss_lines : array();
$wpss_total    = isset( $wpss_total ) ? (float) $wpss_total : 0.0;
$wpss_currency = isset( $wpss_currency ) ? (string) $wpss_currency : wpss_get_currency();
?>
<div class="wpss-card wpss-co-card--summary">
	<div class="wpss-card__header">
		<h3 class="wpss-card__title"><?php echo esc_html( $wpss_title ); ?></h3>
	</div>
	<div class="wpss-card__body">
		<?php foreach ( $wpss_lines as $wpss_line ) : ?>
			<?php
			$wpss_line_class = 'wpss-co-summary-line';
			if ( ! empty( $wpss_line['type'] ) ) {
				$wpss_line_class .= ' wpss-co-summary-line--' . $wpss_line['type'];
			}
			$wpss_line_price = wpss_format_price( (float) ( $wpss_line['amount'] ?? 0 ), $wpss_currency );
			?>
			<div class="<?php echo esc_attr( $wpss_line_class ); ?>">
				<span>
					<?php echo esc_html( $wpss_line['label'] ?? '' ); ?>
					<?php if ( ! empty( $wpss_line['note'] ) ) : ?>
						<span class="wpss-caption"><?php echo esc_html( $wpss_line['note'] ); ?></span>
					<?php endif; ?>
				</span>
				<?php if ( ! empty( $wpss_line['strong'] ) ) : ?>
					<span><strong><?php echo esc_html( $wpss_line_price ); ?></strong></span>
				<?php else : ?>
					<span><?php echo esc_html( $wpss_line_price ); ?></span>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<div class="wpss-co-summary-total">
			<span><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></span>
			<span><?php echo esc_html( wpss_format_price( $wpss_total, $wpss_currency ) ); ?></span>
		</div>
	</div>

	<?php
	/**
	 * Fires after the payable total, before the Pay button.
	 *
	 * Same hook the cart summary fires - see templates/cart/cart.php. Fires on
	 * every checkout shape (single, pay-order, multi-item cart).
	 *
	 * @since 1.5.1
	 *
	 * @param float  $total   Payable total in the store base currency.
	 * @param string $context Surface identifier ('cart', 'checkout').
	 */
	do_action( 'wpss_payable_total_after', $wpss_total, 'checkout' );
	?>

	<div class="wpss-card__footer" style="flex-direction:column;align-items:stretch;">
		<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--lg wpss-btn--full wpss-checkout-button">
			<span class="wpss-checkout-button__text">
				<?php
				/* translators: %s: formatted price */
				printf( esc_html__( 'Pay %s', 'wp-sell-services' ), esc_html( wpss_format_price( $wpss_total, $wpss_currency ) ) );
				?>
			</span>
		</button>

		<?php wpss_get_template_part( 'partials/legal', 'links' ); ?>

		<div class="wpss-co-trust">
			<div class="wpss-co-trust__item">
				<i data-lucide="lock" class="wpss-icon wpss-co-trust__icon" aria-hidden="true"></i>
				<span><?php esc_html_e( 'Secure payment', 'wp-sell-services' ); ?></span>
			</div>
			<div class="wpss-co-trust__item">
				<i data-lucide="shield-check" class="wpss-icon wpss-co-trust__icon" aria-hidden="true"></i>
				<span><?php esc_html_e( 'Order protection', 'wp-sell-services' ); ?></span>
			</div>
		</div>
	</div>
</div>
