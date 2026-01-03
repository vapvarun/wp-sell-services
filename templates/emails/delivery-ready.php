<?php
/**
 * Delivery Ready email (sent to customer)
 *
 * @package WPSellServices\Templates\Emails
 * @version 1.0.0
 *
 * @var \WPSellServices\Models\ServiceOrder $order
 * @var string                               $email_heading
 * @var \WC_Email                            $email
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

$service = wpss_get_service( $order->service_id );
$vendor  = get_user_by( 'id', $order->vendor_id );
?>

<p>
	<?php
	printf(
		/* translators: %s: Vendor name */
		esc_html__( '%s has delivered your order! Please review the delivery and accept it if you are satisfied.', 'wp-sell-services' ),
		esc_html( $vendor ? $vendor->display_name : __( 'The seller', 'wp-sell-services' ) )
	);
	?>
</p>

<h2>
	<?php
	printf(
		/* translators: %s: Order number */
		esc_html__( 'Order #%s', 'wp-sell-services' ),
		esc_html( $order->order_number )
	);
	?>
</h2>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 20px;" border="1">
	<tbody>
		<tr>
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;"><?php echo esc_html( $service ? $service->title : __( 'N/A', 'wp-sell-services' ) ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Seller', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;"><?php echo esc_html( $vendor ? $vendor->display_name : __( 'N/A', 'wp-sell-services' ) ); ?></td>
		</tr>
		<?php if ( $order->revisions_remaining > 0 ) : ?>
		<tr>
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Revisions Remaining', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;"><?php echo esc_html( $order->revisions_remaining ); ?></td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>

<p><?php esc_html_e( 'Please review the delivery carefully. You can accept the delivery or request revisions if needed.', 'wp-sell-services' ); ?></p>

<p>
	<a class="button" href="<?php echo esc_url( wpss_get_order_url( $order->id ) ); ?>">
		<?php esc_html_e( 'Review Delivery', 'wp-sell-services' ); ?>
	</a>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
