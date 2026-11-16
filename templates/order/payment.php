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
 * @version 1.9.1
 *
 * @var \WPSellServices\Models\ServiceOrder $wpss_order The order.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $wpss_order ) ) {
	return;
}

$wpss_payment = wpss_get_payment_status_label( (string) $wpss_order->payment_status, null !== $wpss_order->paid_at, (string) $wpss_order->payment_method );
// A phased contract's parent is stamped paid so work can start, but money moves one phase at a time: say so
// rather than "Paid" beside a Paid so far of $0.00.
$wpss_by_phase = ( new \WPSellServices\Services\MilestoneService() )->get_contract_totals( (int) $wpss_order->id )['count'] > 0;
if ( $wpss_by_phase ) {
	$wpss_payment = __( 'Paid phase by phase', 'wp-sell-services' );
}
?>
<div class="wpss-order-detail-item">
	<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Payment', 'wp-sell-services' ); ?></span>
	<span class="wpss-order-detail-item__value"><?php echo esc_html( $wpss_payment ); ?></span>
</div>
<?php // A pay-on-delivery order is stamped when work starts, not when money arrives, so no "Paid On" date. ?>
<?php if ( $wpss_order->paid_at && ! $wpss_by_phase && 'cod' !== (string) $wpss_order->payment_method ) : ?>
	<div class="wpss-order-detail-item">
		<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Paid on', 'wp-sell-services' ); ?></span>
		<span class="wpss-order-detail-item__value"><?php echo esc_html( wpss_datetime_label( $wpss_order->paid_at->getTimestamp() ) ); ?></span>
	</div>
<?php endif; ?>
<?php // A reference the plugin made up when the owner typed none is not a transaction ID worth showing. ?>
<?php if ( '' !== (string) $wpss_order->transaction_id && ! \WPSellServices\Integrations\Gateways\OfflineGateway::is_generated_transaction_id( (string) $wpss_order->transaction_id ) ) : ?>
	<div class="wpss-order-detail-item">
		<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Transaction ID', 'wp-sell-services' ); ?></span>
		<span class="wpss-order-detail-item__value wpss-order-detail-item__value--id"><?php echo esc_html( (string) $wpss_order->transaction_id ); ?></span>
	</div>
<?php endif; ?>
