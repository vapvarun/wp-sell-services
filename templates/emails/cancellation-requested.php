<?php
/**
 * Cancellation Requested Email (HTML)
 *
 * Sent to the vendor when a buyer requests order cancellation.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/cancellation-requested.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.3.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient     Recipient user object (vendor).
 * @var string  $email_heading Email heading.
 * @var string  $base_color    Brand color.
 * @var string  $buyer_name    Buyer display name.
 * @var string  $reason        Cancellation reason label.
 * @var string  $note          Optional additional note from buyer.
 * @var string  $deadline      Response deadline (formatted date).
 * @var WC_Email|null $email   WC Email object (when using WooCommerce).
 */

defined( 'ABSPATH' ) || exit;

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_header', $email_heading, $email );
}

$base_color = $base_color ?? '#7f54b3';
$buyer_name = $buyer_name ?? __( 'The buyer', 'wp-sell-services' );

/**
 * Fires before the email content for the cancellation requested email.
 *
 * @since 1.3.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object.
 */
do_action( 'wpss_email_content_before', 'cancellation_requested', $order, $recipient );
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
		<?php esc_html_e( 'Your cancellation request has been submitted for the following order. The vendor has 48 hours to respond.', 'wp-sell-services' ); ?>
	<?php else : ?>
		<?php
		printf(
			/* translators: %s: buyer name */
			esc_html__( '%s has requested to cancel the following order. Please review and respond within 48 hours.', 'wp-sell-services' ),
			'<strong>' . esc_html( $buyer_name ) . '</strong>'
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
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; font-weight: 600;"><?php esc_html_e( 'Order Total', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: #f59e0b;"><?php esc_html_e( 'Cancellation Requested', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<div style="background: #fff8e1; padding: 16px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #f59e0b;">
	<strong style="color: #92400e;"><?php esc_html_e( 'Cancellation Reason:', 'wp-sell-services' ); ?></strong>
	<p style="margin: 8px 0 0; color: #92400e;"><?php echo esc_html( $reason ); ?></p>
	<?php if ( ! empty( $note ) ) : ?>
		<p style="margin: 8px 0 0; color: #92400e;">
			<strong><?php esc_html_e( 'Additional Details:', 'wp-sell-services' ); ?></strong>
			<?php echo esc_html( $note ); ?>
		</p>
	<?php endif; ?>
</div>

<div style="background: #fef2f2; padding: 16px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #ef4444;">
	<p style="margin: 0; color: #991b1b; font-weight: 600;">
		<?php
		printf(
			/* translators: %s: response deadline */
			esc_html__( 'You have until %s to respond.', 'wp-sell-services' ),
			esc_html( $deadline )
		);
		?>
	</p>
	<p style="margin: 8px 0 0; color: #991b1b; font-size: 14px;">
		<?php esc_html_e( 'If you do not respond, the order will be automatically cancelled.', 'wp-sell-services' ); ?>
	</p>
</div>

<p style="margin: 0 0 10px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'You can:', 'wp-sell-services' ); ?>
</p>
<ul style="margin: 0 0 20px 0; padding-left: 20px; color: #3c3c3c; line-height: 1.8;">
	<li><strong><?php esc_html_e( 'Accept Cancellation', 'wp-sell-services' ); ?></strong> &mdash; <?php esc_html_e( 'the order will be cancelled and a refund may be initiated.', 'wp-sell-services' ); ?></li>
	<li><strong><?php esc_html_e( 'Dispute Cancellation', 'wp-sell-services' ); ?></strong> &mdash; <?php esc_html_e( 'the case will be escalated to admin mediation.', 'wp-sell-services' ); ?></li>
</ul>

<p style="text-align: center; margin: 30px 0;">
	<?php
	$button_url = apply_filters( 'wpss_email_button_url', wpss_get_order_url( $order->id ), 'cancellation_requested', $order );
	$button_text = apply_filters( 'wpss_email_button_text', __( 'Respond to Request', 'wp-sell-services' ), 'cancellation_requested' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php
/**
 * Fires after the email content for the cancellation requested email.
 *
 * @since 1.3.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object.
 */
do_action( 'wpss_email_content_after', 'cancellation_requested', $order, $recipient );

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_footer', $email );
}
