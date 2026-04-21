<?php
/**
 * Milestone Submitted Email (HTML)
 *
 * Sent to the buyer when the seller marks a phase as delivered. Buyer
 * can review + approve, or reply in chat to request changes (no
 * separate rejection flow per product decision).
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.1.0
 *
 * @var \WPSellServices\Models\ServiceOrder $milestone
 * @var \WP_User                            $recipient Buyer.
 * @var string $buyer_name
 * @var string $vendor_name
 * @var string $phase_title
 * @var string $description
 * @var string $submit_note
 * @var string $review_url
 */

defined( 'ABSPATH' ) || exit;
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php printf( esc_html__( 'Hi %s,', 'wp-sell-services' ), esc_html( $buyer_name ) ); ?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: 1: vendor, 2: phase title */
		esc_html__( '%1$s has submitted the delivery for %2$s. Review the work and approve, or reply in chat if you need revisions.', 'wp-sell-services' ),
		'<strong>' . esc_html( $vendor_name ) . '</strong>',
		'<strong>' . esc_html( $phase_title ) . '</strong>'
	);
	?>
</p>

<?php if ( '' !== $submit_note ) : ?>
	<p style="margin: 0 0 8px 0; font-size: 14px; color: #666;">
		<strong><?php esc_html_e( 'Delivery note from the seller:', 'wp-sell-services' ); ?></strong>
	</p>
	<blockquote style="margin: 0 0 24px 0; padding: 12px 16px; border-left: 3px solid #d1d5db; color: #3c3c3c; font-style: italic; white-space: pre-wrap;">
		<?php echo esc_html( $submit_note ); ?>
	</blockquote>
<?php endif; ?>

<p style="margin: 0 0 24px 0; text-align: center;">
	<a href="<?php echo esc_url( $review_url ); ?>" style="display: inline-block; padding: 12px 24px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
		<?php esc_html_e( 'Review & approve', 'wp-sell-services' ); ?>
	</a>
</p>

<p style="margin: 0 0 8px 0; font-size: 13px; color: #6b7280;">
	<?php esc_html_e( 'If something is not right, head to the order page and use the conversation thread to ask for changes — the seller can resubmit when ready.', 'wp-sell-services' ); ?>
</p>
