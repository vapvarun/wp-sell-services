<?php
/**
 * Requirements Reminder Email (HTML)
 *
 * Sent to buyer when they haven't submitted requirements.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/requirements-reminder.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WPSellServices\Models\ServiceOrder $order Service order object.
 * @var WP_User $recipient   Recipient user object.
 * @var string  $email_heading Email heading.
 * @var int     $reminder_num  Reminder number (1, 2, or 3).
 * @var string  $message       Reminder message.
 * @var string  $vendor_name   Vendor display name.
 * @var string  $service_title Service title.
 * @var string  $base_color    Brand color.
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';
$is_final   = 3 === $reminder_num;

/**
 * Fires before the email content for the requirements reminder email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object.
 */
do_action( 'wpss_email_content_before', 'requirements_reminder', $order, $recipient );
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: recipient name */
		esc_html__( 'Hi %s,', 'wp-sell-services' ),
		esc_html( $recipient->display_name )
	);
	?>
</p>

<?php if ( $is_final ) : ?>
	<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px;">
		<p style="margin: 0; color: #856404; font-weight: 600;">
			<?php esc_html_e( 'This is your final reminder!', 'wp-sell-services' ); ?>
		</p>
	</div>
<?php endif; ?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Your vendor is waiting for your project requirements to start working on your order.', 'wp-sell-services' ); ?>
</p>

<?php if ( ! empty( $message ) ) : ?>
<div style="background: #f0f4ff; border-left: 4px solid <?php echo esc_attr( $base_color ); ?>; padding: 14px 16px; margin: 0 0 20px 0; border-radius: 0 4px 4px 0;">
	<?php echo wp_kses_post( wpautop( $message ) ); ?>
</div>
<?php endif; ?>

<table class="email-table" style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9f9f9; border-radius: 4px;">
	<tr>
		<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 35%;"><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
		<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;">#<?php echo esc_html( $order->order_number ); ?></td>
	</tr>
	<tr>
		<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5;"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
		<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $service_title ); ?></td>
	</tr>
	<tr>
		<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5;"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></th>
		<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $vendor_name ); ?></td>
	</tr>
	<tr>
		<th style="padding: 12px; text-align: left;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
		<td style="padding: 12px;">
			<span style="background-color: #ffc107; color: #333; padding: 4px 8px; border-radius: 3px; font-size: 13px;">
				<?php esc_html_e( 'Awaiting Requirements', 'wp-sell-services' ); ?>
			</span>
		</td>
	</tr>
</table>

<p style="margin: 0 0 24px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Please submit your requirements so the vendor can begin working on your order. The sooner you submit, the sooner you\'ll receive your delivery!', 'wp-sell-services' ); ?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the requirements reminder email.
	 *
	 * @since 1.0.0
	 *
	 * @param string                             $button_url Default button URL.
	 * @param WPSellServices\Models\ServiceOrder $order Service order object.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', wpss_get_order_url( $order->id ), 'requirements_reminder', $order );

	/**
	 * Filters the button text for the requirements reminder email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_text Default button text.
	 */
	$button_text = apply_filters( 'wpss_email_button_text', __( 'Submit Requirements Now', 'wp-sell-services' ), 'requirements_reminder' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" class="button" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 4px; font-weight: 600; font-size: 16px;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php if ( $is_final ) : ?>
	<p style="margin: 20px 0 0 0; font-size: 14px; color: #666666; line-height: 1.6;">
		<?php esc_html_e( 'If you have questions about what to provide, feel free to message your vendor through the order page.', 'wp-sell-services' ); ?>
	</p>
<?php endif; ?>

<?php
/**
 * Fires after the email content for the requirements reminder email.
 *
 * @since 1.0.0
 *
 * @param WPSellServices\Models\ServiceOrder $order Service order object.
 * @param WP_User                            $recipient Recipient user object.
 */
do_action( 'wpss_email_content_after', 'requirements_reminder', $order, $recipient );
