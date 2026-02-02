<?php
/**
 * Requirements Submitted Email (HTML)
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'The customer has submitted their requirements for your order.', 'wp-sell-services' ); ?></p>

<h2><?php printf( esc_html__( 'Order #%s', 'wp-sell-services' ), esc_html( $order->order_number ) ); ?></h2>

<table class="td" cellspacing="0" cellpadding="6" border="1" style="width: 100%; margin-bottom: 20px;">
	<tbody>
		<tr>
			<th class="td" scope="row" style="text-align: left;"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
			<td class="td"><?php echo esc_html( get_the_title( $order->service_id ) ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align: left;"><?php esc_html_e( 'Customer', 'wp-sell-services' ); ?></th>
			<td class="td"><?php $customer = get_user_by( 'id', $order->customer_id ); echo esc_html( $customer ? $customer->display_name : '' ); ?></td>
		</tr>
	</tbody>
</table>

<p><?php esc_html_e( 'You can now start working on the order. View the full requirements by clicking the button below.', 'wp-sell-services' ); ?></p>

<p><a href="<?php echo esc_url( wpss_get_order_url( $order->id ) ); ?>" class="button" style="background-color: #7f54b3; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 3px;"><?php esc_html_e( 'View Requirements', 'wp-sell-services' ); ?></a></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
