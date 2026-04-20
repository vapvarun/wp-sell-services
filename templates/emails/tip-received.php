<?php
/**
 * Tip Received Email (HTML)
 *
 * Sent to the vendor when a tip order is paid and the vendor wallet
 * has been credited. Override in your theme:
 * yourtheme/wp-sell-services/emails/tip-received.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.1.0
 *
 * @var WPSellServices\Models\ServiceOrder      $tip_order     The paid tip sub-order.
 * @var WPSellServices\Models\ServiceOrder|null $parent_order  Parent service order (may be null if deleted).
 * @var WP_User                                 $recipient     Vendor user object.
 * @var string                                  $email_heading Email heading.
 * @var string                                  $vendor_name   Vendor display name.
 * @var string                                  $customer_name Buyer display name.
 * @var float                                   $gross_amount  Amount buyer paid.
 * @var float                                   $net_amount    Amount credited to vendor wallet.
 * @var string                                  $currency      Currency code.
 * @var string                                  $tip_note      Optional buyer note.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fires before the tip-received email body renders.
 *
 * @since 1.1.0
 *
 * @param WPSellServices\Models\ServiceOrder $tip_order Tip sub-order.
 * @param WP_User                            $recipient Vendor user.
 */
do_action( 'wpss_email_content_before', 'tip_received', $tip_order, $recipient );

$format = static function ( float $amount ) use ( $currency ): string {
	return function_exists( 'wpss_format_price' )
		? wpss_format_price( $amount, $currency )
		: number_format_i18n( $amount, 2 ) . ' ' . $currency;
};

$platform_fee = max( 0.0, $gross_amount - $net_amount );
$dashboard    = wpss_get_dashboard_url() ?: home_url( '/dashboard/' );
$earnings_url = add_query_arg( 'section', 'earnings', $dashboard );
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: vendor name */
		esc_html__( 'Hi %s,', 'wp-sell-services' ),
		esc_html( $vendor_name )
	);
	?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: 1: buyer name, 2: net tip amount */
		esc_html__( 'Great news — %1$s just sent you a tip of %2$s. It has been added to your earnings balance.', 'wp-sell-services' ),
		'<strong>' . esc_html( $customer_name ) . '</strong>',
		'<strong>' . esc_html( $format( $net_amount ) ) . '</strong>'
	);
	?>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px 0; border-collapse: collapse;">
	<tr>
		<td style="padding: 16px 20px; background: #f7f7f7; border-radius: 6px; font-size: 14px; color: #3c3c3c; line-height: 1.6;">
			<?php if ( $parent_order ) : ?>
				<div style="margin-bottom: 8px;">
					<strong><?php esc_html_e( 'On order:', 'wp-sell-services' ); ?></strong>
					#<?php echo esc_html( $parent_order->order_number ); ?>
				</div>
			<?php endif; ?>
			<div style="margin-bottom: 8px;">
				<strong><?php esc_html_e( 'Tip amount:', 'wp-sell-services' ); ?></strong>
				<?php echo esc_html( $format( $gross_amount ) ); ?>
			</div>
			<?php if ( $platform_fee > 0 ) : ?>
				<div style="margin-bottom: 8px; color: #666;">
					<strong><?php esc_html_e( 'Platform fee:', 'wp-sell-services' ); ?></strong>
					&minus;<?php echo esc_html( $format( $platform_fee ) ); ?>
				</div>
			<?php endif; ?>
			<div>
				<strong><?php esc_html_e( 'Credited to your balance:', 'wp-sell-services' ); ?></strong>
				<?php echo esc_html( $format( $net_amount ) ); ?>
			</div>
		</td>
	</tr>
</table>

<?php if ( ! empty( $tip_note ) ) : ?>
	<p style="margin: 0 0 8px 0; font-size: 14px; color: #666;">
		<strong><?php esc_html_e( 'Message from the buyer:', 'wp-sell-services' ); ?></strong>
	</p>
	<blockquote style="margin: 0 0 24px 0; padding: 12px 16px; border-left: 3px solid #d1d5db; color: #3c3c3c; font-style: italic; white-space: pre-wrap;">
		<?php echo esc_html( $tip_note ); ?>
	</blockquote>
<?php endif; ?>

<p style="margin: 0 0 24px 0; text-align: center;">
	<a href="<?php echo esc_url( $earnings_url ); ?>"
		style="display: inline-block; padding: 12px 24px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
		<?php esc_html_e( 'View your earnings', 'wp-sell-services' ); ?>
	</a>
</p>

<?php
/**
 * Fires after the tip-received email body renders.
 *
 * @since 1.1.0
 *
 * @param WPSellServices\Models\ServiceOrder $tip_order Tip sub-order.
 * @param WP_User                            $recipient Vendor user.
 */
do_action( 'wpss_email_content_after', 'tip_received', $tip_order, $recipient );
