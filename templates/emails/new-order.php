<?php
/**
 * New Order Email (HTML)
 *
 * Sent to vendor when a new service order is placed.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/new-order.php
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
$customer      = get_user_by( 'id', $order->customer_id );
$customer_name = $customer ? $customer->display_name : __( 'A customer', 'wp-sell-services' );
$vendor        = isset( $recipient ) ? $recipient : get_user_by( 'id', $order->vendor_id );

/**
 * Fires before the email content for the new order email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object (vendor).
 */
do_action( 'wpss_email_content_before', 'new_order', $order, $recipient );
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
	<?php if ( ! empty( $is_customer ) ) : ?>
		<?php esc_html_e( 'Your order has been placed successfully! Here are the details.', 'wp-sell-services' ); ?>
	<?php else : ?>
		<?php esc_html_e( 'Great news! You have received a new service order.', 'wp-sell-services' ); ?>
	<?php endif; ?>

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
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; font-weight: 600;"><?php esc_html_e( 'Package', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $order->get_package_name() ); ?></td>
		</tr>
		<tr>
			<?php if ( ! empty( $is_customer ) ) : ?>
				<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; font-weight: 600;"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></th>
				<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $vendor_name ?? ( $vendor ? $vendor->display_name : '' ) ); ?></td>
			<?php else : ?>
				<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; font-weight: 600;"><?php esc_html_e( 'Customer', 'wp-sell-services' ); ?></th>
				<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $customer_name ); ?></td>
			<?php endif; ?>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: <?php echo esc_attr( $base_color ); ?>;"><?php echo wp_kses_post( wpss_format_price( $order->total ) ); ?></td>
		</tr>
	</tbody>
</table>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php if ( ! empty( $is_customer ) ) : ?>
		<?php esc_html_e( 'Please submit your requirements so the vendor can start working on your order. Click the button below to get started.', 'wp-sell-services' ); ?>
	<?php else : ?>
		<?php esc_html_e( 'The customer will submit their requirements shortly. You\'ll receive another notification when they do.', 'wp-sell-services' ); ?>
	<?php endif; ?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the new order email.
	 *
	 * @since 1.0.0
	 *
	 * @param string                             $button_url Default button URL.
	 * @param WPSellServices\Models\ServiceOrder $order Service order object.
	 */
	if ( ! empty( $is_customer ) ) {
		$default_url  = function_exists( 'wpss_get_order_requirements_url' ) ? wpss_get_order_requirements_url( $order->id ) : wpss_get_order_url( $order->id );
		$default_text = __( 'Submit Requirements', 'wp-sell-services' );
	} else {
		$default_url  = wpss_get_order_url( $order->id );
		$default_text = __( 'View Order', 'wp-sell-services' );
	}

	/**
	 * Filters the button URL for the new order email.
	 *
	 * @since 1.0.0
	 *
	 * @param string                             $button_url Default button URL.
	 * @param WPSellServices\Models\ServiceOrder $order Service order object.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', $default_url, 'new_order', $order );

	/**
	 * Filters the button text for the new order email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_text Default button text.
	 */
	$button_text = apply_filters( 'wpss_email_button_text', $default_text, 'new_order' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php
/**
 * Fires after the email content for the new order email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object (vendor).
 */
do_action( 'wpss_email_content_after', 'new_order', $order, $recipient );

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_footer', $email );
}
