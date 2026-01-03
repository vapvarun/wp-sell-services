<?php
/**
 * Order Completed email (sent to both parties)
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
?>

<p><?php esc_html_e( 'Congratulations! Your order has been completed successfully.', 'wp-sell-services' ); ?></p>

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
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;"><?php echo esc_html( wpss_format_price( $order->total, $order->currency ) ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;color:#28a745;"><strong><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></strong></td>
		</tr>
	</tbody>
</table>

<p><?php esc_html_e( 'Thank you for using our service marketplace!', 'wp-sell-services' ); ?></p>

<p>
	<a class="button" href="<?php echo esc_url( wpss_get_order_url( $order->id ) ); ?>">
		<?php esc_html_e( 'View Order Details', 'wp-sell-services' ); ?>
	</a>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
