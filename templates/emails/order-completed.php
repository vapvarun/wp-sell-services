<?php
/**
 * Order Completed Email (HTML)
 *
 * Sent to buyer when their order is marked as completed.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/order-completed.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient     Recipient user object.
 * @var string  $email_heading Email heading.
 * @var string  $base_color    Brand color.
 * @var bool    $is_customer   Whether the recipient is the buyer.
 * @var string  $vendor_name   Vendor display name.
 * @var string  $customer_name Customer display name.
 * @var WC_Email|null $email   WC Email object (when using WooCommerce).
 */

defined( 'ABSPATH' ) || exit;

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_header', $email_heading, $email );
}

$base_color    = $base_color ?? '#7f54b3';
$is_customer   = $is_customer ?? true;
$vendor_name   = $vendor_name ?? __( 'the vendor', 'wp-sell-services' );
$customer_name = $customer_name ?? __( 'the customer', 'wp-sell-services' );

/**
 * Fires before the email content for the order completed email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object (buyer).
 */
do_action( 'wpss_email_content_before', 'order_completed', $order, $recipient );
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
	<?php if ( $is_customer ) : ?>
		<?php esc_html_e( 'Great news! Your service order has been completed.', 'wp-sell-services' ); ?>
	<?php else : ?>
		<?php
		printf(
			/* translators: %s: order number */
			esc_html__( 'Order #%s has been completed.', 'wp-sell-services' ),
			esc_html( $order->order_number )
		);
		?>
	<?php endif; ?>
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
		<?php if ( $is_customer ) : ?>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; font-weight: 600;"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $vendor_name ); ?></td>
		</tr>
		<?php else : ?>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; font-weight: 600;"><?php esc_html_e( 'Customer', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $customer_name ); ?></td>
		</tr>
		<?php endif; ?>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; font-weight: 600;"><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo wp_kses_post( wpss_format_price( $order->total ) ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: #28a745;"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<?php if ( $is_customer ) : ?>
<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: vendor name */
		esc_html__( 'Your order has been completed! If you\'re happy with the result, please take a moment to leave a review for %s.', 'wp-sell-services' ),
		esc_html( $vendor_name )
	);
	?>
</p>
<?php else : ?>
<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: formatted price */
		esc_html__( 'Payment of %s will be released to your earnings.', 'wp-sell-services' ),
		wp_kses_post( wpss_format_price( $order->vendor_earnings ?? $order->total ) )
	);
	?>
</p>
<?php endif; ?>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the order completed email.
	 *
	 * @since 1.0.0
	 *
	 * @param string                             $button_url Default button URL.
	 * @param WPSellServices\Models\ServiceOrder $order Service order object.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', wpss_get_order_url( $order->id ), 'order_completed', $order );

	/**
	 * Filters the button text for the order completed email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_text Default button text.
	 */
	$default_button_text = $is_customer
		? __( 'View Order & Leave Review', 'wp-sell-services' )
		: __( 'View Order', 'wp-sell-services' );
	$button_text         = apply_filters( 'wpss_email_button_text', $default_button_text, 'order_completed' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Thank you for using our marketplace!', 'wp-sell-services' ); ?>
</p>

<?php
/**
 * Fires after the email content for the order completed email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object (buyer).
 */
do_action( 'wpss_email_content_after', 'order_completed', $order, $recipient );

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_footer', $email );
}
