<?php
/**
 * Milestone Approved Email (HTML)
 *
 * Sent to the vendor once the buyer has approved a submitted phase.
 * Money already landed in the wallet at payment time — this is the
 * delivery sign-off. Terminal state for the milestone.
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.1.0
 *
 * @var \WPSellServices\Models\ServiceOrder $milestone
 * @var \WP_User                            $recipient Vendor.
 * @var string $vendor_name
 * @var string $buyer_name
 * @var string $phase_title
 * @var float  $net_amount
 * @var string $currency
 */

defined( 'ABSPATH' ) || exit;

$format = static function ( float $amt ) use ( $currency ): string {
	return function_exists( 'wpss_format_price' )
		? wpss_format_price( $amt, $currency )
		: number_format_i18n( $amt, 2 ) . ' ' . $currency;
};

$dashboard = wpss_get_dashboard_url() ?: home_url( '/dashboard/' );
$sales_url = add_query_arg( array( 'section' => 'sales', 'order_id' => (int) $milestone->id ), $dashboard );
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php printf( esc_html__( 'Hi %s,', 'wp-sell-services' ), esc_html( $vendor_name ) ); ?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: 1: buyer, 2: phase title */
		esc_html__( 'Nice work — %1$s approved the phase %2$s. The phase is complete and the payment is already in your wallet.', 'wp-sell-services' ),
		'<strong>' . esc_html( $buyer_name ) . '</strong>',
		'<strong>' . esc_html( $phase_title ) . '</strong>'
	);
	?>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px 0; border-collapse: collapse;">
	<tr>
		<td style="padding: 16px 20px; background: #f0fdf4; border-radius: 6px; border-left: 3px solid #22c55e; font-size: 14px; color: #3c3c3c; line-height: 1.6;">
			<div><strong><?php esc_html_e( 'Your earnings on this phase:', 'wp-sell-services' ); ?></strong> <?php echo esc_html( $format( $net_amount ) ); ?></div>
		</td>
	</tr>
</table>

<p style="margin: 0 0 24px 0; text-align: center;">
	<a href="<?php echo esc_url( $sales_url ); ?>" style="display: inline-block; padding: 12px 24px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
		<?php esc_html_e( 'Open this phase', 'wp-sell-services' ); ?>
	</a>
</p>
