<?php
/**
 * Milestone Proposed Email (HTML)
 *
 * Sent to the buyer when the seller proposes a new phase on a custom-
 * project (request-mode) order. Includes the deliverables and an
 * Accept & Pay deep link so the buyer can move forward without hunting
 * through the dashboard.
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.1.0
 *
 * @var \WPSellServices\Models\ServiceOrder      $milestone    Milestone sub-order.
 * @var \WPSellServices\Models\ServiceOrder|null $parent_order Parent order (may be null).
 * @var \WP_User                                 $recipient    Buyer user.
 * @var string $buyer_name
 * @var string $vendor_name
 * @var string $phase_title
 * @var string $description
 * @var string $deliverables
 * @var float  $amount
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
		/* translators: 1: vendor name, 2: phase title */
		esc_html__( '%1$s has proposed a new milestone on your project: %2$s.', 'wp-sell-services' ),
		'<strong>' . esc_html( $vendor_name ) . '</strong>',
		'<strong>' . esc_html( $phase_title ) . '</strong>'
	);
	?>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px 0; border-collapse: collapse;">
	<tr>
		<td style="padding: 16px 20px; background: #f7f7f7; border-radius: 6px; font-size: 14px; color: #3c3c3c; line-height: 1.6;">
			<?php if ( '' !== $description ) : ?>
				<div style="margin-bottom: 10px;"><strong><?php esc_html_e( 'What this phase covers:', 'wp-sell-services' ); ?></strong><br><?php echo esc_html( $description ); ?></div>
			<?php endif; ?>
			<?php if ( '' !== $deliverables ) : ?>
				<div style="margin-bottom: 10px;"><strong><?php esc_html_e( 'Deliverables:', 'wp-sell-services' ); ?></strong><br><?php echo esc_html( $deliverables ); ?></div>
			<?php endif; ?>
			<div><strong><?php esc_html_e( 'Amount:', 'wp-sell-services' ); ?></strong> <?php echo esc_html( $format( $amount ) ); ?></div>
		</td>
	</tr>
</table>

<p style="margin: 0 0 20px 0; font-size: 14px; color: #4b5563; line-height: 1.6;">
	<?php esc_html_e( 'Once you pay, the seller starts this phase and submits the delivery for your approval. If the quote looks off, reply in chat on the order page to discuss first.', 'wp-sell-services' ); ?>
</p>

<p style="margin: 0 0 24px 0; text-align: center;">
	<a href="<?php echo esc_url( $pay_url ); ?>" style="display: inline-block; padding: 12px 24px; background: #0f766e; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
		<?php
		printf(
			/* translators: %s: amount */
			esc_html__( 'Accept & Pay %s', 'wp-sell-services' ),
			esc_html( $format( $amount ) )
		);
		?>
	</a>
</p>
