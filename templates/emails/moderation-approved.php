<?php
/**
 * Moderation Approved Email (HTML)
 *
 * Sent to the vendor when their service has been approved.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/moderation-approved.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var string   $service_title Service title.
 * @var string   $service_url   Service permalink URL.
 * @var WP_User  $recipient     Recipient user object (vendor).
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';

/**
 * Fires before the email content for the moderation approved email.
 *
 * @since 1.0.0
 *
 * @param string  $service_title Service title.
 * @param WP_User $recipient     Recipient user object.
 */
do_action( 'wpss_email_content_before', 'moderation_approved', $service_title, $recipient );
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

<div style="background: #d4edda; padding: 16px; border-radius: 4px; margin: 0 0 20px 0;">
	<p style="margin: 0; font-size: 16px; color: #155724; line-height: 1.6;">
		<?php esc_html_e( 'Great news! Your service has been reviewed and approved. It is now live on the marketplace.', 'wp-sell-services' ); ?>
	</p>
</div>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9f9f9; border-radius: 4px;">
	<tbody>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 35%; font-weight: 600;"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $service_title ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: #28a745;"><?php esc_html_e( 'Approved', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Buyers can now find and order your service. Thank you for being a valued seller on our platform.', 'wp-sell-services' ); ?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the moderation approved email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_url    Default button URL.
	 * @param string $service_title Service title.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', $service_url, 'moderation_approved', $service_title );

	/**
	 * Filters the button text for the moderation approved email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_text Default button text.
	 */
	$button_text = apply_filters( 'wpss_email_button_text', __( 'View Your Service', 'wp-sell-services' ), 'moderation_approved' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: #28a745; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php
/**
 * Fires after the email content for the moderation approved email.
 *
 * @since 1.0.0
 *
 * @param string  $service_title Service title.
 * @param WP_User $recipient     Recipient user object.
 */
do_action( 'wpss_email_content_after', 'moderation_approved', $service_title, $recipient );
