<?php
/**
 * Moderation Rejected Email (HTML)
 *
 * Sent to the vendor when their service has been rejected and requires changes.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/moderation-rejected.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var string   $service_title     Service title.
 * @var string   $rejection_reason  Reason the service was rejected.
 * @var string   $edit_url          URL to edit the service.
 * @var WP_User  $recipient         Recipient user object (vendor).
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';

/**
 * Fires before the email content for the moderation rejected email.
 *
 * @since 1.0.0
 *
 * @param string  $service_title Service title.
 * @param WP_User $recipient     Recipient user object.
 */
do_action( 'wpss_email_content_before', 'moderation_rejected', $service_title, $recipient );
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
	<?php esc_html_e( 'Thank you for submitting your service for review. Unfortunately, your service could not be approved at this time and requires some changes.', 'wp-sell-services' ); ?>
</p>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9f9f9; border-radius: 4px;">
	<tbody>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 35%; font-weight: 600;"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $service_title ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: #dc3545;"><?php esc_html_e( 'Requires Changes', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<?php if ( ! empty( $rejection_reason ) ) : ?>
<div style="background: #f8d7da; padding: 16px; border-radius: 4px; margin: 20px 0;">
	<strong style="color: #721c24;"><?php esc_html_e( 'Reason for Rejection:', 'wp-sell-services' ); ?></strong>
	<p style="margin: 8px 0 0; color: #721c24;"><?php echo esc_html( $rejection_reason ); ?></p>
</div>
<?php endif; ?>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Please update your service based on the feedback above and resubmit it for approval. If you have any questions, please contact our support team.', 'wp-sell-services' ); ?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the moderation rejected email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_url    Default button URL.
	 * @param string $service_title Service title.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', $edit_url, 'moderation_rejected', $service_title );

	/**
	 * Filters the button text for the moderation rejected email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_text Default button text.
	 */
	$button_text = apply_filters( 'wpss_email_button_text', __( 'Edit Your Service', 'wp-sell-services' ), 'moderation_rejected' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php
/**
 * Fires after the email content for the moderation rejected email.
 *
 * @since 1.0.0
 *
 * @param string  $service_title Service title.
 * @param WP_User $recipient     Recipient user object.
 */
do_action( 'wpss_email_content_after', 'moderation_rejected', $service_title, $recipient );
