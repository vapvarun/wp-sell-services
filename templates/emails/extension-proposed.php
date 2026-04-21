<?php
/**
 * Extension Proposed Email (HTML)
 *
 * Sent to the buyer when the seller quotes extra work on a fixed-price
 * order. Includes the reason + amount + extra days + Accept & Pay deep
 * link so the buyer can move without hunting through the dashboard.
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.1.0
 *
 * @var \WPSellServices\Models\ServiceOrder $extension
 * @var \WP_User                            $recipient Buyer.
 * @var string $buyer_name
 * @var string $vendor_name
 * @var string $reason
 * @var float  $amount
 * @var int    $extra_days
 * @var string $currency
 * @var string $pay_url
 */

defined( 'ABSPATH' ) || exit;

$format = static function ( float $amt ) use ( $currency ): string {
	return function_exists( 'wpss_format_price' )
		? wpss_format_price( $amt, $currency )
		: number_format_i18n( $amt, 2 ) . ' ' . $currency;
};
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php printf( esc_html__( 'Hi %s,', 'wp-sell-services' ), esc_html( $buyer_name ) ); ?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: vendor name */
		esc_html__( '%s sent a quote for the extra work you discussed. Review the details and accept to pay, or decline to keep the original scope.', 'wp-sell-services' ),
		'<strong>' . esc_html( $vendor_name ) . '</strong>'
	);
	?>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px 0; border-collapse: collapse;">
	<tr>
		<td style="padding: 16px 20px; background: #fffbeb; border-radius: 6px; border-left: 3px solid #f59e0b; font-size: 14px; color: #3c3c3c; line-height: 1.6;">
			<?php if ( '' !== $reason ) : ?>
				<div style="margin-bottom: 10px;"><strong><?php esc_html_e( 'What the seller will do:', 'wp-sell-services' ); ?></strong><br><?php echo esc_html( $reason ); ?></div>
			<?php endif; ?>
			<div style="margin-bottom: 6px;"><strong><?php esc_html_e( 'Extra amount:', 'wp-sell-services' ); ?></strong> <?php echo esc_html( $format( $amount ) ); ?></div>
			<?php if ( $extra_days > 0 ) : ?>
				<div><strong><?php esc_html_e( 'Extra delivery time:', 'wp-sell-services' ); ?></strong> <?php printf( esc_html( _n( '+%d day', '+%d days', $extra_days, 'wp-sell-services' ) ), (int) $extra_days ); ?></div>
			<?php endif; ?>
		</td>
	</tr>
</table>

<p style="margin: 0 0 24px 0; text-align: center;">
	<a href="<?php echo esc_url( $pay_url ); ?>" style="display: inline-block; padding: 12px 24px; background: #b45309; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
		<?php
		printf(
			/* translators: %s: amount */
			esc_html__( 'Accept &amp; Pay %s', 'wp-sell-services' ),
			esc_html( $format( $amount ) )
		);
		?>
	</a>
</p>

<p style="margin: 0 0 8px 0; font-size: 13px; color: #6b7280;">
	<?php esc_html_e( 'Not sure? Open the order and discuss in chat first — the seller can send a revised quote.', 'wp-sell-services' ); ?>
</p>
