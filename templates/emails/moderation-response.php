<?php
/**
 * Moderation Response Email (HTML)
 *
 * Generic moderation response sent to vendor after admin approves or rejects
 * a service from the admin dashboard moderation page.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/moderation-response.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var string   $service_title Service title.
 * @var string   $status        Moderation status: 'approved' or 'rejected'.
 * @var string   $message       Optional message or rejection reason from admin.
 * @var string   $service_url   Service permalink URL (used when approved).
 * @var string   $edit_url      URL to edit the service (used when rejected).
 * @var WP_User  $recipient     Recipient user object (vendor).
 */

defined( 'ABSPATH' ) || exit;

$base_color  = $base_color ?? '#7f54b3';
$is_approved = 'approved' === $status;

/**
 * Fires before the email content for the moderation response email.
 *
 * @since 1.0.0
 *
 * @param string  $service_title Service title.
 * @param WP_User $recipient     Recipient user object.
 */
do_action( 'wpss_email_content_before', 'moderation_response', $service_title, $recipient );
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

<?php if ( $is_approved ) : ?>
<div style="background: #d4edda; padding: 16px; border-radius: 4px; margin: 0 0 20px 0;">
	<p style="margin: 0; font-size: 16px; color: #155724; line-height: 1.6;">
		<?php esc_html_e( 'Great news! Your service has been reviewed and approved. It is now live on the marketplace.', 'wp-sell-services' ); ?>
	</p>
</div>
<?php else : ?>
<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Your service has been reviewed. Unfortunately, it could not be approved at this time and requires some changes before it can go live.', 'wp-sell-services' ); ?>
</p>
<?php endif; ?>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9f9f9; border-radius: 4px;">
	<tbody>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 35%; font-weight: 600;"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $service_title ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: <?php echo $is_approved ? '#28a745' : '#dc3545'; ?>;">
				<?php echo $is_approved ? esc_html__( 'Approved', 'wp-sell-services' ) : esc_html__( 'Requires Changes', 'wp-sell-services' ); ?>
			</td>
		</tr>
	</tbody>
</table>

<?php if ( ! empty( $message ) && ! $is_approved ) : ?>
<div style="background: #f8d7da; padding: 16px; border-radius: 4px; margin: 20px 0;">
	<strong style="color: #721c24;"><?php esc_html_e( 'Feedback from the Reviewer:', 'wp-sell-services' ); ?></strong>
	<p style="margin: 8px 0 0; color: #721c24;"><?php echo wp_kses_post( $message ); ?></p>
</div>
<?php elseif ( ! empty( $message ) ) : ?>
<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;"><?php echo wp_kses_post( $message ); ?></p>
<?php endif; ?>

<?php if ( ! $is_approved ) : ?>
<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Please update your service based on the feedback above and resubmit it for approval. If you have questions, please contact our support team.', 'wp-sell-services' ); ?>
</p>
<?php else : ?>
<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Buyers can now find and order your service. Thank you for being a valued seller on our platform.', 'wp-sell-services' ); ?>
</p>
<?php endif; ?>

<p style="text-align: center; margin: 30px 0;">
	<?php
	$default_cta_url = $is_approved ? $service_url : $edit_url;

	/**
	 * Filters the button URL for the moderation response email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_url    Default button URL.
	 * @param string $service_title Service title.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', $default_cta_url, 'moderation_response', $service_title );

	$default_cta_text = $is_approved
		? __( 'View Your Service', 'wp-sell-services' )
		: __( 'Edit Your Service', 'wp-sell-services' );

	/**
	 * Filters the button text for the moderation response email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_text Default button text.
	 */
	$button_text      = apply_filters( 'wpss_email_button_text', $default_cta_text, 'moderation_response' );
	$button_color     = $is_approved ? '#28a745' : $base_color;
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $button_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php
/**
 * Fires after the email content for the moderation response email.
 *
 * @since 1.0.0
 *
 * @param string  $service_title Service title.
 * @param WP_User $recipient     Recipient user object.
 */
do_action( 'wpss_email_content_after', 'moderation_response', $service_title, $recipient );
