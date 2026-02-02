<?php
/**
 * New Message Email (HTML)
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php esc_html_e( 'You have a new message on your order.', 'wp-sell-services' ); ?></p>

<h2><?php printf( esc_html__( 'Order #%s', 'wp-sell-services' ), esc_html( $order->order_number ) ); ?></h2>

<?php if ( ! empty( $message_content ) ) : ?>
<div style="background: #f7f7f7; padding: 15px; border-left: 4px solid #7f54b3; margin: 20px 0;">
	<?php echo wp_kses_post( wpautop( $message_content ) ); ?>
</div>
<?php endif; ?>

<p><a href="<?php echo esc_url( wpss_get_order_url( $order->id ) ); ?>" class="button" style="background-color: #7f54b3; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 3px;"><?php esc_html_e( 'Reply', 'wp-sell-services' ); ?></a></p>

<?php do_action( 'woocommerce_email_footer', $email ); ?>
