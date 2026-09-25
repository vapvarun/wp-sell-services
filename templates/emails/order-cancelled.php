<?php
/**
 * Order Cancelled Email (HTML)
 *
 * Sent when an order is cancelled.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/order-cancelled.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient     Recipient user object.
 * @var string  $email_heading Email heading.
 * @var string  $base_color    Brand color.
 * @var string  $reason        Cancellation reason (optional).
 * @var WC_Email|null $email   WC Email object (when using WooCommerce).
 */

defined( 'ABSPATH' ) || exit;

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_header', $email_heading, $email );
}

$base_color = $base_color ?? '#7f54b3';

/**
 * Fires before the email content for the order cancelled email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object.
 */
do_action( 'wpss_email_content_before', 'order_cancelled', $order, $recipient );
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: recipient name */
		esc_html__( 'Hi %s,', 'wp-sell-services' ),
		esc_html( $recipient ? $recipient->display_name : __( 'there', 'wp-sell-services' ) )
	);
	?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'We\'re sorry to inform you that your service order has been cancelled.', 'wp-sell-services' ); ?>
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
			<td style="padding: 12px; font-weight: 600; color: #dc3545;"><?php esc_html_e( 'Cancelled', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<?php
// Where the buyer's money stands, for the buyer only (Basecamp 10336731713).
$wpss_refund = ( $recipient && (int) $recipient->ID === (int) $order->customer_id ) ? wpss_get_order_refund_state( $order ) : array( 'state' => '' );
if ( '' !== $wpss_refund['state'] ) :
	$wpss_refund_amount = wpss_format_price( (float) $wpss_refund['amount'], (string) $order->currency );
	if ( 'refunded' === $wpss_refund['state'] ) {
		/* translators: %s: refunded amount */
		$wpss_refund_copy = sprintf( __( 'Your payment of %s has been refunded to your original payment method. It can take a few days to show on your statement.', 'wp-sell-services' ), $wpss_refund_amount );
	} elseif ( 'pending' === $wpss_refund['state'] ) {
		/* translators: %s: amount to be refunded */
		$wpss_refund_copy = sprintf( __( 'Your payment of %s will be refunded. It is sent back to you by hand, and your order page will show when it has gone out.', 'wp-sell-services' ), $wpss_refund_amount );
	} else {
		/* translators: %s: amount to be refunded */
		$wpss_refund_copy = sprintf( __( 'Your payment of %s will be refunded. We are completing the refund and your order page will show when it has gone out.', 'wp-sell-services' ), $wpss_refund_amount );
	}
	?>
<div style="background: #e8f4fd; padding: 16px; border-radius: 4px; margin: 20px 0;">
	<strong style="color: #0c5460;"><?php esc_html_e( 'Your refund', 'wp-sell-services' ); ?></strong>
	<p style="margin: 8px 0 0; color: #0c5460;"><?php echo esc_html( $wpss_refund_copy ); ?></p>
</div>
<?php endif; ?>

<?php if ( ! empty( $reason ) ) : ?>
<div style="background: #fff3cd; padding: 16px; border-radius: 4px; margin: 20px 0;">
	<strong style="color: #856404;"><?php esc_html_e( 'Reason:', 'wp-sell-services' ); ?></strong>
	<p style="margin: 8px 0 0; color: #856404;"><?php echo esc_html( $reason ); ?></p>
</div>
<?php endif; ?>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'If you have any questions or believe this was done in error, please contact our support team.', 'wp-sell-services' ); ?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the order cancelled email.
	 *
	 * @since 1.0.0
	 *
	 * @param string                             $button_url Default button URL.
	 * @param WPSellServices\Models\ServiceOrder $order Service order object.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', wpss_get_order_url( $order->id ), 'order_cancelled', $order );

	/**
	 * Filters the button text for the order cancelled email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_text Default button text.
	 */
	$button_text = apply_filters( 'wpss_email_button_text', __( 'View Order Details', 'wp-sell-services' ), 'order_cancelled' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php
/**
 * Fires after the email content for the order cancelled email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object.
 */
do_action( 'wpss_email_content_after', 'order_cancelled', $order, $recipient );

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_footer', $email );
}
