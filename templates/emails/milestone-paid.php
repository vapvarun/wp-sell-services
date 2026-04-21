<?php
/**
 * Milestone Paid Email (HTML)
 *
 * Sent to the vendor when the buyer's milestone payment clears. Confirms
 * the amount (net of commission) has landed in the wallet and that work
 * can start — the next step for the vendor is to Submit Delivery.
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.1.0
 *
 * @var \WPSellServices\Models\ServiceOrder $milestone
 * @var \WP_User                            $recipient Vendor.
 * @var string $vendor_name
 * @var string $buyer_name
 * @var string $phase_title
 * @var string $description
 * @var float  $gross_amount
 * @var float  $net_amount
 * @var string $currency
 */

defined( 'ABSPATH' ) || exit;

$format = static function ( float $amt ) use ( $currency ): string {
	return function_exists( 'wpss_format_price' )
		? wpss_format_price( $amt, $currency )
		: number_format_i18n( $amt, 2 ) . ' ' . $currency;
};

$platform_fee = max( 0.0, $gross_amount - $net_amount );
$dashboard    = wpss_get_dashboard_url() ?: home_url( '/dashboard/' );
$order_url    = add_query_arg( array( 'section' => 'sales', 'order_id' => (int) $milestone->id ), $dashboard );
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php printf( esc_html__( 'Hi %s,', 'wp-sell-services' ), esc_html( $vendor_name ) ); ?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: 1: buyer, 2: phase title, 3: net amount */
		esc_html__( '%1$s paid the phase %2$s. %3$s has been credited to your wallet — you can start work.', 'wp-sell-services' ),
		'<strong>' . esc_html( $buyer_name ) . '</strong>',
		'<strong>' . esc_html( $phase_title ) . '</strong>',
		'<strong>' . esc_html( $format( $net_amount ) ) . '</strong>'
	);
	?>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px 0; border-collapse: collapse;">
	<tr>
		<td style="padding: 16px 20px; background: #f0fdf4; border-radius: 6px; border-left: 3px solid #22c55e; font-size: 14px; color: #3c3c3c; line-height: 1.6;">
			<div style="margin-bottom: 8px;"><strong><?php esc_html_e( 'Buyer paid:', 'wp-sell-services' ); ?></strong> <?php echo esc_html( $format( $gross_amount ) ); ?></div>
			<?php if ( $platform_fee > 0 ) : ?>
				<div style="margin-bottom: 8px; color: #666;"><strong><?php esc_html_e( 'Platform fee:', 'wp-sell-services' ); ?></strong> &minus;<?php echo esc_html( $format( $platform_fee ) ); ?></div>
			<?php endif; ?>
			<div><strong><?php esc_html_e( 'Credited to your wallet:', 'wp-sell-services' ); ?></strong> <?php echo esc_html( $format( $net_amount ) ); ?></div>
		</td>
	</tr>
</table>

<p style="margin: 0 0 24px 0; text-align: center;">
	<a href="<?php echo esc_url( $order_url ); ?>" style="display: inline-block; padding: 12px 24px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
		<?php esc_html_e( 'Open this phase', 'wp-sell-services' ); ?>
	</a>
</p>

<p style="margin: 0 0 8px 0; font-size: 13px; color: #6b7280;">
	<?php esc_html_e( 'When you are done, use Submit delivery on this phase so the buyer can review and approve.', 'wp-sell-services' ); ?>
</p>
