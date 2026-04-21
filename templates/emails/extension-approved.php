<?php
/**
 * Extension Approved Email (HTML)
 *
 * Sent to the vendor when the buyer pays an extension quote. Confirms
 * the net amount lands in the wallet and the parent deadline has
 * moved — vendor continues work on the expanded scope.
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.1.0
 *
 * @var \WPSellServices\Models\ServiceOrder $extension
 * @var \WP_User                            $recipient Vendor.
 * @var string $vendor_name
 * @var string $buyer_name
 * @var float  $gross_amount
 * @var float  $net_amount
 * @var int    $extra_days
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
$parent_id    = (int) ( $extension->platform_order_id ?? 0 );
$order_url    = $parent_id ? add_query_arg( array( 'section' => 'sales', 'order_id' => $parent_id ), $dashboard ) : $dashboard;
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php printf( esc_html__( 'Hi %s,', 'wp-sell-services' ), esc_html( $vendor_name ) ); ?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: 1: buyer name, 2: net amount */
		esc_html__( '%1$s approved your extension quote — %2$s is in your wallet, and the order deadline has been updated. You can continue working on the expanded scope.', 'wp-sell-services' ),
		'<strong>' . esc_html( $buyer_name ) . '</strong>',
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
			<div style="margin-bottom: 8px;"><strong><?php esc_html_e( 'Credited to your wallet:', 'wp-sell-services' ); ?></strong> <?php echo esc_html( $format( $net_amount ) ); ?></div>
			<?php if ( $extra_days > 0 ) : ?>
				<div><strong><?php esc_html_e( 'Extra delivery time:', 'wp-sell-services' ); ?></strong> <?php printf( esc_html( _n( '+%d day', '+%d days', $extra_days, 'wp-sell-services' ) ), (int) $extra_days ); ?></div>
			<?php endif; ?>
		</td>
	</tr>
</table>

<p style="margin: 0 0 24px 0; text-align: center;">
	<a href="<?php echo esc_url( $order_url ); ?>" style="display: inline-block; padding: 12px 24px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
		<?php esc_html_e( 'Open the order', 'wp-sell-services' ); ?>
	</a>
</p>
