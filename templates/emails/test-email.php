<?php
/**
 * Test Email (HTML)
 *
 * Sent to the admin to verify that email notifications are configured correctly.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/test-email.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var WP_User $recipient Recipient user object (current admin).
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';
$site_name  = isset( $site_name ) ? $site_name : get_bloginfo( 'name' );

/**
 * Fires before the email content for the test email.
 *
 * @since 1.0.0
 *
 * @param WP_User $recipient Recipient user object.
 */
do_action( 'wpss_email_content_before', 'test_email', $recipient );
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
		<?php
		printf(
			/* translators: %s: site/platform name */
			esc_html__( 'This is a test email from %s. If you received this, your email configuration is working correctly.', 'wp-sell-services' ),
			esc_html( $site_name )
		);
		?>
	</p>
</div>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'No action is required. This message was sent to confirm that outgoing email is set up and delivering successfully.', 'wp-sell-services' ); ?>
</p>

<?php
/**
 * Fires after the email content for the test email.
 *
 * @since 1.0.0
 *
 * @param WP_User $recipient Recipient user object.
 */
do_action( 'wpss_email_content_after', 'test_email', $recipient );
