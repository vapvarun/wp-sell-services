<?php
/**
 * Extension Declined Email (HTML)
 *
 * Sent to the vendor when the buyer declines an extension quote. The
 * buyer's optional note is surfaced so the vendor knows what to change
 * before sending a revised quote.
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.1.0
 *
 * @var \WPSellServices\Models\ServiceOrder $extension
 * @var \WP_User                            $recipient Vendor.
 * @var string $vendor_name
 * @var string $buyer_name
 * @var string $note
 * @var float  $amount
 * @var string $currency
 */

defined( 'ABSPATH' ) || exit;

$format = static function ( float $amt ) use ( $currency ): string {
	return function_exists( 'wpss_format_price' )
		? wpss_format_price( $amt, $currency )
		: number_format_i18n( $amt, 2 ) . ' ' . $currency;
};

$dashboard = wpss_get_dashboard_url() ?: home_url( '/dashboard/' );
$parent_id = (int) ( $extension->platform_order_id ?? 0 );
$order_url = $parent_id ? add_query_arg( array( 'section' => 'sales', 'order_id' => $parent_id ), $dashboard ) : $dashboard;
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php printf( esc_html__( 'Hi %s,', 'wp-sell-services' ), esc_html( $vendor_name ) ); ?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: 1: buyer name, 2: amount */
		esc_html__( '%1$s declined the extension quote for %2$s. The original order scope stays unchanged — you can propose a revised quote any time.', 'wp-sell-services' ),
		'<strong>' . esc_html( $buyer_name ) . '</strong>',
		'<strong>' . esc_html( $format( $amount ) ) . '</strong>'
	);
	?>
</p>

<?php if ( '' !== trim( $note ) ) : ?>
	<p style="margin: 0 0 8px 0; font-size: 14px; color: #666;">
		<strong><?php esc_html_e( 'Buyer note:', 'wp-sell-services' ); ?></strong>
	</p>
	<blockquote style="margin: 0 0 24px 0; padding: 12px 16px; border-left: 3px solid #d1d5db; color: #3c3c3c; font-style: italic; white-space: pre-wrap;">
		<?php echo esc_html( $note ); ?>
	</blockquote>
<?php endif; ?>

<p style="margin: 0 0 24px 0; text-align: center;">
	<a href="<?php echo esc_url( $order_url ); ?>" style="display: inline-block; padding: 12px 24px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
		<?php esc_html_e( 'Open the order', 'wp-sell-services' ); ?>
	</a>
</p>
