<?php
/**
 * Withdrawal Requested Email (HTML)
 *
 * Sent to admin when a vendor submits a withdrawal request.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/withdrawal-requested.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.3.0
 *
 * @var WP_User|null $vendor          Vendor user object.
 * @var float        $amount          Requested withdrawal amount.
 * @var int          $withdrawal_id   Withdrawal record ID.
 * @var string       $admin_panel_url URL to the admin withdrawals page.
 * @var string       $email_heading   Email heading.
 * @var string       $base_color      Brand color.
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Hi Admin,', 'wp-sell-services' ); ?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'A vendor has submitted a new withdrawal request that requires your review.', 'wp-sell-services' ); ?>
</p>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9f9f9; border-radius: 4px;">
	<tbody>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 35%; font-weight: 600;"><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $vendor ? $vendor->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; font-weight: 600;"><?php esc_html_e( 'Email', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( $vendor ? $vendor->user_email : '' ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Amount Requested', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: <?php echo esc_attr( $base_color ); ?>;"><?php echo wp_kses_post( wpss_format_price( $amount ) ); ?></td>
		</tr>
	</tbody>
</table>

<p style="text-align: center; margin: 30px 0;">
	<a href="<?php echo esc_url( $admin_panel_url ); ?>" style="display: inline-block; background-color: <?php echo esc_attr( $base_color ); ?>; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php esc_html_e( 'Review Withdrawal', 'wp-sell-services' ); ?>
	</a>
</p>
