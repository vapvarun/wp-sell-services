<?php
/**
 * New Order email (sent to vendor)
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
$buyer   = get_user_by( 'id', $order->customer_id );
?>

<p><?php esc_html_e( 'You have received a new service order. Here are the details:', 'wp-sell-services' ); ?></p>

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
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Buyer', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;"><?php echo esc_html( $buyer ? $buyer->display_name : __( 'Guest', 'wp-sell-services' ) ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;"><?php echo esc_html( wpss_format_price( $order->total, $order->currency ) ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Delivery Deadline', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $order->delivery_deadline ) ) ); ?></td>
		</tr>
	</tbody>
</table>

<p>
	<a class="button" href="<?php echo esc_url( add_query_arg( 'order_id', $order->id, wpss_get_dashboard_url( 'sales' ) ) ); ?>">
		<?php esc_html_e( 'View Order Details', 'wp-sell-services' ); ?>
	</a>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
