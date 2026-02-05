<?php
/**
 * New Message Email (HTML)
 *
 * Sent when there's a new message on an order.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/new-message.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient       Recipient user object.
 * @var string  $email_heading   Email heading.
 * @var string  $base_color      Brand color.
 * @var string  $message_content Message content.
 * @var string  $sender_name     Name of the message sender.
 * @var WC_Email|null $email     WC Email object (when using WooCommerce).
 */

defined( 'ABSPATH' ) || exit;

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_header', $email_heading, $email );
}

$base_color  = $base_color ?? '#7f54b3';
$sender_name = $sender_name ?? __( 'Someone', 'wp-sell-services' );

/**
 * Fires before the email content for the new message email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object.
 */
do_action( 'wpss_email_content_before', 'new_message', $order, $recipient );
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
	<?php
	printf(
		/* translators: %s: sender name */
		esc_html__( 'You have a new message from %s on your order.', 'wp-sell-services' ),
		esc_html( $sender_name )
	);
	?>
</p>

<h2 style="margin: 0 0 20px 0; font-size: 20px; color: #3c3c3c;">
	<?php printf( esc_html__( 'Order #%s', 'wp-sell-services' ), esc_html( $order->order_number ) ); ?>
</h2>

<?php if ( ! empty( $message_content ) ) : ?>
<div style="background: #f9f9f9; padding: 16px; border-left: 4px solid <?php echo esc_attr( $base_color ); ?>; margin: 20px 0; border-radius: 0 4px 4px 0;">
	<?php echo wp_kses_post( wpautop( $message_content ) ); ?>
</div>
<?php endif; ?>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the new message email.
	 *
	 * @since 1.0.0
	 *
	 * @param string                             $button_url Default button URL.
	 * @param WPSellServices\Models\ServiceOrder $order Service order object.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', wpss_get_order_url( $order->id ), 'new_message', $order );

	/**
	 * Filters the button text for the new message email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_text Default button text.
	 */
	$button_text = apply_filters( 'wpss_email_button_text', __( 'Reply to Message', 'wp-sell-services' ), 'new_message' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php
/**
 * Fires after the email content for the new message email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object.
 */
do_action( 'wpss_email_content_after', 'new_message', $order, $recipient );

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_footer', $email );
}
