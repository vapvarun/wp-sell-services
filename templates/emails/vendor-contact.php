<?php
/**
 * Vendor Contact Email (HTML)
 *
 * Sent when a customer uses the "Contact Me" button on a vendor profile.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/vendor-contact.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.2.2
 *
 * @var WP_User $recipient      Vendor (recipient) user object.
 * @var WP_User $sender         Sender (customer) user object.
 * @var string  $sender_name    Sender display name.
 * @var string  $sender_email   Sender email address.
 * @var string  $email_heading  Email heading.
 * @var string  $base_color     Brand color.
 * @var string  $message        Message content.
 * @var string  $service_title  Service title (may be empty).
 * @var array   $attachments    Attachments array (may be empty).
 * @var string  $dashboard_url  Link to vendor messages dashboard.
 */

defined( 'ABSPATH' ) || exit;

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_header', $email_heading, $email );
}

$base_color  = $base_color ?? '#7f54b3';
$sender_name = $sender_name ?? __( 'Someone', 'wp-sell-services' );
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
	<?php if ( ! empty( $service_title ) ) : ?>
		<?php
		printf(
			/* translators: 1: sender name, 2: service title */
			esc_html__( '%1$s has sent you a message about "%2$s".', 'wp-sell-services' ),
			esc_html( $sender_name ),
			esc_html( $service_title )
		);
		?>
	<?php else : ?>
		<?php
		printf(
			/* translators: %s: sender name */
			esc_html__( '%s has sent you a direct message.', 'wp-sell-services' ),
			esc_html( $sender_name )
		);
		?>
	<?php endif; ?>
</p>

<?php if ( ! empty( $message ) ) : ?>
<div style="background: #f9f9f9; padding: 16px; border-left: 4px solid <?php echo esc_attr( $base_color ); ?>; margin: 20px 0; border-radius: 0 4px 4px 0;">
	<?php echo wp_kses_post( wpautop( $message ) ); ?>
</div>
<?php endif; ?>

<?php if ( ! empty( $attachments ) ) : ?>
<p style="margin: 20px 0 8px 0; font-size: 14px; color: #3c3c3c; font-weight: 600;">
	<?php esc_html_e( 'Attachments:', 'wp-sell-services' ); ?>
</p>
<ul style="margin: 0 0 20px 0; padding-left: 20px;">
	<?php foreach ( $attachments as $attachment ) : ?>
		<?php if ( ! empty( $attachment['url'] ) ) : ?>
		<li style="margin-bottom: 4px;">
			<a href="<?php echo esc_url( $attachment['url'] ); ?>" style="color: <?php echo esc_attr( $base_color ); ?>;">
				<?php echo esc_html( $attachment['name'] ?? basename( $attachment['url'] ) ); ?>
			</a>
		</li>
		<?php endif; ?>
	<?php endforeach; ?>
</ul>
<?php endif; ?>

<p style="margin: 20px 0 0 0; font-size: 14px; color: #555555; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: sender email address */
		esc_html__( 'You can reply from your dashboard or contact the sender directly at %s.', 'wp-sell-services' ),
		'<a href="mailto:' . esc_attr( $sender_email ) . '" style="color: ' . esc_attr( $base_color ) . ';">' . esc_html( $sender_email ) . '</a>'
	);
	?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<?php
	$button_url  = apply_filters( 'wpss_email_button_url', $dashboard_url ?? wpss_get_dashboard_url(), 'vendor_contact' );
	$button_text = apply_filters( 'wpss_email_button_text', __( 'View Message', 'wp-sell-services' ), 'vendor_contact' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php
/**
 * Fires after the email content for the vendor contact email.
 *
 * @since 1.2.2
 *
 * @param WP_User $recipient Vendor (recipient) user object.
 * @param WP_User $sender    Sender (customer) user object.
 */
do_action( 'wpss_email_content_after', 'vendor_contact', $recipient, $sender );

// WooCommerce compatibility.
if ( isset( $email ) && function_exists( 'WC' ) ) {
	do_action( 'woocommerce_email_footer', $email );
}
