<?php
/**
 * Revision Requested email (sent to vendor)
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

<p>
	<?php
	printf(
		/* translators: %s: Buyer name */
		esc_html__( '%s has requested a revision on their order.', 'wp-sell-services' ),
		esc_html( $buyer ? $buyer->display_name : __( 'The buyer', 'wp-sell-services' ) )
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
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Buyer', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;"><?php echo esc_html( $buyer ? $buyer->display_name : __( 'Guest', 'wp-sell-services' ) ); ?></td>
		</tr>
		<tr>
			<th class="td" scope="row" style="text-align:left;"><?php esc_html_e( 'Revisions Remaining', 'wp-sell-services' ); ?></th>
			<td class="td" style="text-align:left;"><?php echo esc_html( $order->revisions_remaining ); ?></td>
		</tr>
	</tbody>
</table>

<p><?php esc_html_e( 'Please check the revision request and update your delivery accordingly.', 'wp-sell-services' ); ?></p>

<p>
	<a class="button" href="<?php echo esc_url( add_query_arg( 'order_id', $order->id, wpss_get_dashboard_url( 'sales' ) ) ); ?>">
		<?php esc_html_e( 'View Revision Request', 'wp-sell-services' ); ?>
	</a>
</p>

<?php
do_action( 'woocommerce_email_footer', $email );
