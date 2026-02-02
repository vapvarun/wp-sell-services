<?php
/**
 * Order Completed Email (HTML)
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'Great news! Your service order has been completed.', 'wp-sell-services' ); ?></p>

<h2><?php printf( esc_html__( 'Order #%s', 'wp-sell-services' ), esc_html( $order->order_number ) ); ?></h2>

<table class="td" cellspacing="0" cellpadding="6" border="1" style="width: 100%; margin-bottom: 20px;">
	<tbody>
		<tr>
			<th class="td" scope="row" style="text-align: left;"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
			<td class="td"><?php echo esc_html( get_the_title( $order->service_id ) ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align: left;"><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
			<td class="td"><?php echo wp_kses_post( wpss_format_price( $order->total ) ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align: left;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td class="td" style="color: #28a745;"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<p><a href="<?php echo esc_url( wpss_get_order_url( $order->id ) ); ?>" class="button" style="background-color: #7f54b3; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 3px;"><?php esc_html_e( 'View Order', 'wp-sell-services' ); ?></a></p>

<p><?php esc_html_e( 'Thank you for using our service marketplace!', 'wp-sell-services' ); ?></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
