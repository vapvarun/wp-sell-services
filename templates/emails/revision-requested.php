<?php
/**
 * Revision Requested Email (HTML)
 *
 * Sent to vendor when the buyer requests a revision.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/revision-requested.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient     Recipient user object (vendor).
 * @var string  $email_heading Email heading.
 * @var string  $base_color    Brand color.
 * @var WC_Email|null $email   WC Email object (when using WooCommerce).
 */

defined( 'ABSPATH' ) || exit;

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_header', $email_heading, $email );
}

$base_color    = $base_color ?? '#7f54b3';
$vendor        = isset( $recipient ) ? $recipient : get_user_by( 'id', $order->vendor_id );
$customer      = get_user_by( 'id', $order->customer_id );
$customer_name = $customer ? $customer->display_name : __( 'The customer', 'wp-sell-services' );

/**
 * Fires before the email content for the revision requested email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object (vendor).
 */
do_action( 'wpss_email_content_before', 'revision_requested', $order, $recipient );
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: vendor name */
		esc_html__( 'Hi %s,', 'wp-sell-services' ),
		esc_html( $vendor ? $vendor->display_name : __( 'there', 'wp-sell-services' ) )
	);
	?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: customer name */
		esc_html__( '%s has requested a revision for their delivery.', 'wp-sell-services' ),
		esc_html( $customer_name )
	);
	?>
</p>

<h2 style="margin: 0 0 20px 0; font-size: 20px; color: #3c3c3c;">
	<?php printf( esc_html__( 'Order #%s', 'wp-sell-services' ), esc_html( $order->order_number ) ); ?>
</h2>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9f9f9; border-radius: 4px;">
	<tbody>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 35%; font-weight: 600;"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( get_the_title( $order->service_id ) ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: #ffc107;"><?php esc_html_e( 'Revision Requested', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Please review the feedback and submit a revised delivery to meet the customer\'s expectations.', 'wp-sell-services' ); ?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the revision requested email.
	 *
	 * @since 1.0.0
	 *
	 * @param string                             $button_url Default button URL.
	 * @param WPSellServices\Models\ServiceOrder $order Service order object.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', wpss_get_order_url( $order->id ), 'revision_requested', $order );

	/**
	 * Filters the button text for the revision requested email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_text Default button text.
	 */
	$button_text = apply_filters( 'wpss_email_button_text', __( 'View Feedback', 'wp-sell-services' ), 'revision_requested' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php
/**
 * Fires after the email content for the revision requested email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object (vendor).
 */
do_action( 'wpss_email_content_after', 'revision_requested', $order, $recipient );

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_footer', $email );
}
