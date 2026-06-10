<?php
/**
 * Vendor Approved Email (HTML)
 *
 * Sent to a user when their vendor application has been approved and they
 * have been granted seller access. Dispatched from VendorService when the
 * vendor profile status changes to 'active' (admin approval).
 *
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/vendor-approved.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.5.0
 *
 * @var string  $dashboard_url Vendor dashboard URL.
 * @var WP_User $recipient     Recipient user object (vendor).
 * @var string  $base_color    Brand color (provided by email settings).
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';

/**
 * Fires before the email content for the vendor approved email.
 *
 * @since 1.5.0
 *
 * @param string  $type      Email type identifier.
 * @param WP_User $recipient Recipient user object.
 */
do_action( 'wpss_email_content_before', 'vendor_approved', $recipient ?? null );
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

<div style="background: #d4edda; padding: 16px; border-radius: 4px; margin: 0 0 20px 0;">
	<p style="margin: 0; font-size: 16px; color: #155724; line-height: 1.6;">
		<?php esc_html_e( 'Congratulations! Your vendor application has been approved. You are now a seller on our marketplace.', 'wp-sell-services' ); ?>
	</p>
</div>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9f9f9; border-radius: 4px;">
	<tbody>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 35%; font-weight: 600;"><?php esc_html_e( 'Account', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( isset( $recipient ) && $recipient ? $recipient->display_name : '' ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: #28a745;"><?php esc_html_e( 'Approved', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'You can now create services, manage orders, and start earning from your vendor dashboard. Welcome aboard!', 'wp-sell-services' ); ?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the vendor approved email.
	 *
	 * @since 1.5.0
	 *
	 * @param string $button_url Default button URL.
	 * @param string $type       Email type identifier.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', $dashboard_url ?? '', 'vendor_approved' );

	/**
	 * Filters the button text for the vendor approved email.
	 *
	 * @since 1.5.0
	 *
	 * @param string $button_text Default button text.
	 * @param string $type        Email type identifier.
	 */
	$button_text = apply_filters( 'wpss_email_button_text', __( 'Go to Your Dashboard', 'wp-sell-services' ), 'vendor_approved' );
	?>
	<?php if ( ! empty( $button_url ) ) : ?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: #28a745; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
	<?php endif; ?>
</p>

<?php
/**
 * Fires after the email content for the vendor approved email.
 *
 * @since 1.5.0
 *
 * @param string  $type      Email type identifier.
 * @param WP_User $recipient Recipient user object.
 */
do_action( 'wpss_email_content_after', 'vendor_approved', $recipient ?? null );
