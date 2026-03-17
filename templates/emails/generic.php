<?php
/**
 * Generic Email Template (HTML)
 *
 * Used for notification emails that don't have a dedicated template.
 * Renders a heading, content paragraph, and optional CTA button.
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.2.0
 *
 * @var WP_User $recipient     Recipient user object.
 * @var string  $email_heading Email heading.
 * @var string  $content       Email body content.
 * @var string  $button_url    CTA button URL (optional).
 * @var string  $button_text   CTA button text (optional).
 * @var string  $base_color    Brand color.
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';

do_action( 'wpss_email_content_before', 'generic', $recipient ?? null );
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: recipient name */
		esc_html__( 'Hi %s,', 'wp-sell-services' ),
		esc_html( isset( $recipient ) && $recipient ? $recipient->display_name : __( 'there', 'wp-sell-services' ) )
	);
	?>
</p>

<?php if ( ! empty( $content ) ) : ?>
<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php echo wp_kses_post( $content ); ?>
</p>
<?php endif; ?>

<?php if ( ! empty( $button_url ) && ! empty( $button_text ) ) : ?>
<p style="text-align: center; margin: 30px 0;">
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>
<?php endif; ?>

<?php
do_action( 'wpss_email_content_after', 'generic', $recipient ?? null );
