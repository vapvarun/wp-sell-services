<?php
/**
 * Withdrawal Rejected Email (HTML)
 *
 * Sent when admin rejects a vendor's withdrawal request.
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.2.0
 *
 * @var WP_User $recipient      Recipient (vendor) user object.
 * @var string  $email_heading  Email heading.
 * @var string  $amount         Formatted withdrawal amount.
 * @var int     $withdrawal_id  Withdrawal ID.
 * @var string  $status         Withdrawal status.
 * @var string  $admin_note     Admin note with rejection reason.
 * @var string  $dashboard_url  Link to vendor earnings dashboard.
 * @var string  $base_color     Brand color.
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';

do_action( 'wpss_email_content_before', 'withdrawal_rejected', $recipient );
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

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: amount */
		esc_html__( 'Your withdrawal request for %s has been rejected.', 'wp-sell-services' ),
		'<strong>' . esc_html( $amount ) . '</strong>'
	);
	?>
</p>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9f9f9; border-radius: 4px;">
	<tbody>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 35%; font-weight: 600;"><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $amount ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: #dc3545;"><?php esc_html_e( 'Rejected', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<?php if ( ! empty( $admin_note ) ) : ?>
<div style="background: #f8d7da; padding: 16px; border-radius: 4px; margin: 20px 0;">
	<strong style="color: #721c24;"><?php esc_html_e( 'Reason:', 'wp-sell-services' ); ?></strong>
	<p style="margin: 8px 0 0; color: #721c24;"><?php echo esc_html( $admin_note ); ?></p>
</div>
<?php endif; ?>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'The withdrawal amount has been returned to your available balance. You can submit a new request from your earnings dashboard.', 'wp-sell-services' ); ?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<a href="<?php echo esc_url( $dashboard_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php esc_html_e( 'View Earnings', 'wp-sell-services' ); ?>
	</a>
</p>

<?php
do_action( 'wpss_email_content_after', 'withdrawal_rejected', $recipient );
