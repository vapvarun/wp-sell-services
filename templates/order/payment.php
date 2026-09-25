<?php
/**
 * How an order was paid: method, state, transaction ID and when.
 *
 * Items for a .wpss-order-details-grid, rendered by the buyer's and seller's
 * order summary and the admin order screen alike. The admin screen showed no
 * payment at all - the method appeared only inside refund-failure notices
 * (Basecamp 10337161480).
 *
 * Override: {theme}/wp-sell-services/order/payment.php
 *
 * @package WPSellServices
 * @since   1.8.0
 *
 * @var \WPSellServices\Models\ServiceOrder $wpss_order The order.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $wpss_order ) ) {
	return;
}

$wpss_method = wpss_get_payment_method_label( (string) $wpss_order->payment_method );
$wpss_paid   = 'paid' === $wpss_order->payment_status || null !== $wpss_order->paid_at;

if ( $wpss_paid ) {
	/* translators: %s: payment method, e.g. Stripe. */
	$wpss_payment = '' !== $wpss_method ? sprintf( __( 'Paid · %s', 'wp-sell-services' ), $wpss_method ) : __( 'Paid', 'wp-sell-services' );
} else {
	/* translators: %s: payment method, e.g. Offline Payment. */
	$wpss_payment = '' !== $wpss_method ? sprintf( __( 'Not paid · %s', 'wp-sell-services' ), $wpss_method ) : __( 'Not paid', 'wp-sell-services' );
}
?>
<div class="wpss-order-detail-item">
	<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Payment', 'wp-sell-services' ); ?></span>
	<span class="wpss-order-detail-item__value"><?php echo esc_html( $wpss_payment ); ?></span>
</div>
<?php if ( $wpss_order->paid_at ) : ?>
	<div class="wpss-order-detail-item">
		<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Paid On', 'wp-sell-services' ); ?></span>
		<span class="wpss-order-detail-item__value"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $wpss_order->paid_at->getTimestamp() ) ); ?></span>
	</div>
<?php endif; ?>
<?php if ( '' !== (string) $wpss_order->transaction_id ) : ?>
	<div class="wpss-order-detail-item">
		<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Transaction ID', 'wp-sell-services' ); ?></span>
		<span class="wpss-order-detail-item__value wpss-order-detail-item__value--id"><?php echo esc_html( (string) $wpss_order->transaction_id ); ?></span>
	</div>
<?php endif; ?>
