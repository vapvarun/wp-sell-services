<?php
/**
 * New Message email
 *
 * @package WPSellServices\Templates\Emails
 * @version 1.0.0
 *
 * @var \WPSellServices\Models\ServiceOrder $order
 * @var string                               $message_content
 * @var string                               $email_heading
 * @var \WC_Email                            $email
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );

$service = wpss_get_service( $order->service_id );
?>

<p><?php esc_html_e( 'You have received a new message on your order:', 'wp-sell-services' ); ?></p>

<h2>
	<?php
	printf(
		/* translators: %s: Order number */
		esc_html__( 'Order #%s', 'wp-sell-services' ),
		esc_html( $order->order_number )
	);
	?>
</h2>

<div style="background-color:#f8f9fa;padding:15px;margin:20px 0;border-left:4px solid #0073aa;">
	<?php echo wp_kses_post( wpautop( $message_content ) ); ?>
</div>

<table class="td" cellspacing="0" cellpadding="6" style="width: 100%; margin-bottom: 20px;" border="1">
	<tbody>
		<tr>
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;"><?php echo esc_html( $service ? $service->title : __( 'N/A', 'wp-sell-services' ) ); ?></td>
		</tr>
	</tbody>
</table>

<p>
	<a class="button" href="<?php echo esc_url( add_query_arg( 'order_id', $order->id, wpss_get_dashboard_url( 'messages' ) ) ); ?>">
		<?php esc_html_e( 'Reply to Message', 'wp-sell-services' ); ?>
	</a>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
