<?php
/**
 * Order In Progress Email (HTML)
 *
 * Sent to buyer when the vendor starts working on their order.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/order-in-progress.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient     Recipient user object.
 * @var string  $email_heading Email heading.
 * @var string  $base_color    Brand color.
 * @var bool    $is_customer   Whether the recipient is the buyer.
 * @var string  $vendor_name   Vendor display name (buyer copy).
 * @var string  $customer_name Customer display name (vendor copy).
 * @var WC_Email|null $email   WC Email object (when using WooCommerce).
 */

defined( 'ABSPATH' ) || exit;

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_header', $email_heading, $email );
}

$base_color    = $base_color ?? '#7f54b3';
$is_customer   = $is_customer ?? true;
$vendor_name   = $vendor_name ?? __( 'The vendor', 'wp-sell-services' );
$customer_name = $customer_name ?? __( 'The customer', 'wp-sell-services' );

/**
 * Fires before the email content for the order in progress email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object (buyer).
 */
do_action( 'wpss_email_content_before', 'order_in_progress', $order, $recipient );
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
		<?php
		printf(
			/* translators: %s: vendor name */
			esc_html__( 'Great news! %s has started working on your order.', 'wp-sell-services' ),
			esc_html( $vendor_name )
		);
		?>
	<?php else : ?>
		<?php
		printf(
			/* translators: 1: order number, 2: customer name */
			esc_html__( 'You\'ve started working on Order #%1$s for %2$s. The customer has been notified.', 'wp-sell-services' ),
			esc_html( $order->order_number ),
			esc_html( $customer_name )
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
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: #007bff;"><?php esc_html_e( 'In Progress', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php if ( $is_customer ) : ?>
		<?php esc_html_e( 'You can track your order status and communicate with the vendor through the order page.', 'wp-sell-services' ); ?>
	<?php else : ?>
		<?php esc_html_e( 'You can manage this order and communicate with the customer through the order page.', 'wp-sell-services' ); ?>
	<?php endif; ?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the order in progress email.
	 *
	 * @since 1.0.0
	 *
	 * @param string                             $button_url Default button URL.
	 * @param WPSellServices\Models\ServiceOrder $order Service order object.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', wpss_get_order_url( $order->id ), 'order_in_progress', $order );

	/**
	 * Filters the button text for the order in progress email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_text Default button text.
	 */
	$button_text = apply_filters( 'wpss_email_button_text', __( 'View Order', 'wp-sell-services' ), 'order_in_progress' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php
/**
 * Fires after the email content for the order in progress email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object (buyer).
 */
do_action( 'wpss_email_content_after', 'order_in_progress', $order, $recipient );

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_footer', $email );
}
