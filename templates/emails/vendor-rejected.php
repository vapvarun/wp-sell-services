<?php
/**
 * Vendor Rejected Email (HTML)
 *
 * Sent to a user when their vendor application has been rejected or their
 * vendor access has been revoked (suspended). Dispatched from VendorService
 * when vendor access is revoked following a status change.
 *
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/vendor-rejected.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.5.0
 *
 * @var string  $rejection_reason Reason the application was rejected (optional).
 * @var string  $site_url         Site home URL.
 * @var WP_User  $recipient        Recipient user object.
 * @var string  $base_color       Brand color (provided by email settings).
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';

/**
 * Fires before the email content for the vendor rejected email.
 *
 * @since 1.5.0
 *
 * @param string  $type      Email type identifier.
 * @param WP_User $recipient Recipient user object.
 */
do_action( 'wpss_email_content_before', 'vendor_rejected', $recipient ?? null );
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

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Thank you for your interest in becoming a vendor on our marketplace. After reviewing your application, we are unable to approve your vendor account at this time.', 'wp-sell-services' ); ?>
</p>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9f9f9; border-radius: 4px;">
	<tbody>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 35%; font-weight: 600;"><?php esc_html_e( 'Account', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( isset( $recipient ) && $recipient ? $recipient->display_name : '' ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: #dc3545;"><?php esc_html_e( 'Not Approved', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<?php if ( ! empty( $rejection_reason ) ) : ?>
<div style="background: #f8d7da; padding: 16px; border-radius: 4px; margin: 20px 0;">
	<strong style="color: #721c24;"><?php esc_html_e( 'Reason:', 'wp-sell-services' ); ?></strong>
	<p style="margin: 8px 0 0; color: #721c24;"><?php echo esc_html( $rejection_reason ); ?></p>
</div>
<?php endif; ?>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'If you believe this was a mistake or you would like more information, please contact our support team. You are welcome to apply again in the future.', 'wp-sell-services' ); ?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the vendor rejected email.
	 *
	 * @since 1.5.0
	 *
	 * @param string $button_url Default button URL.
	 * @param string $type       Email type identifier.
	 */
	$button_url = apply_filters( 'wpss_email_button_url', $site_url ?? home_url(), 'vendor_rejected' );

	/**
	 * Filters the button text for the vendor rejected email.
	 *
	 * @since 1.5.0
	 *
	 * @param string $button_text Default button text.
	 * @param string $type        Email type identifier.
	 */
	$button_text = apply_filters( 'wpss_email_button_text', __( 'Visit Marketplace', 'wp-sell-services' ), 'vendor_rejected' );
	?>
	<?php if ( ! empty( $button_url ) ) : ?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
	<?php endif; ?>
</p>

<?php
/**
 * Fires after the email content for the vendor rejected email.
 *
 * @since 1.5.0
 *
 * @param string  $type      Email type identifier.
 * @param WP_User $recipient Recipient user object.
 */
do_action( 'wpss_email_content_after', 'vendor_rejected', $recipient ?? null );
