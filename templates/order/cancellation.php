<?php
/**
 * Why an order was cancelled: the reason and the details typed with it.
 *
 * Items for a .wpss-order-details-grid, rendered by the buyer's and seller's
 * order summary once the order is cancelled, and by the admin order screen.
 * The details typed with an immediate cancel were kept only in the audit row
 * and shown nowhere (Basecamp 10351457462).
 *
 * Override: {theme}/wp-sell-services/order/cancellation.php
 *
 * @package WPSellServices
 * @since   1.8.0
 *
 * @var \WPSellServices\Models\ServiceOrder $wpss_order The order.
 */

defined( 'ABSPATH' ) || exit;

$wpss_cancellation = empty( $wpss_order ) ? array() : (array) $wpss_order->get_cancellation_request();

if ( empty( $wpss_cancellation['reason'] ) && empty( $wpss_cancellation['note'] ) ) {
	return;
}
?>
<?php if ( ! empty( $wpss_cancellation['reason'] ) ) : ?>
	<div class="wpss-order-detail-item">
		<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Cancellation reason', 'wp-sell-services' ); ?></span>
		<span class="wpss-order-detail-item__value"><?php echo esc_html( wpss_get_cancellation_reason_label( (string) $wpss_cancellation['reason'] ) ); ?></span>
	</div>
<?php endif; ?>
<?php if ( ! empty( $wpss_cancellation['note'] ) ) : ?>
	<div class="wpss-order-detail-item">
		<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Cancellation details', 'wp-sell-services' ); ?></span>
		<span class="wpss-order-detail-item__value"><?php echo esc_html( (string) $wpss_cancellation['note'] ); ?></span>
	</div>
<?php endif; ?>
