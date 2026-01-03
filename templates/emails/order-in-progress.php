<?php
/**
 * Order In Progress email (sent to customer)
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
		esc_html__( 'Great news! %s has started working on your order.', 'wp-sell-services' ),
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
		<tr>
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Expected Delivery', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;">
				<?php
				$deadline_timestamp = $order->delivery_deadline instanceof \DateTimeInterface
					? $order->delivery_deadline->getTimestamp()
					: strtotime( $order->delivery_deadline );
				echo esc_html( wp_date( get_option( 'date_format' ), $deadline_timestamp ) );
				?>
			</td>
		</tr>
	</tbody>
</table>

<p><?php esc_html_e( 'You will receive a notification when your delivery is ready.', 'wp-sell-services' ); ?></p>

<p>
	<a class="button" href="<?php echo esc_url( wpss_get_order_url( $order->id ) ); ?>">
		<?php esc_html_e( 'View Order', 'wp-sell-services' ); ?>
	</a>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
