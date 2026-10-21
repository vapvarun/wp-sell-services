<?php
/**
 * Milestone Revision Requested Email (HTML)
 *
 * Sent to the seller when the buyer sends a delivered phase back for changes.
 *
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/milestone-revision-requested.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.9.1
 * @version 1.9.1
 *
 * @var \WPSellServices\Models\ServiceOrder $milestone
 * @var \WP_User                            $recipient Seller.
 * @var string $vendor_name
 * @var string $buyer_name
 * @var string $phase_title
 * @var string $reason
 * @var string $order_url
 */

defined( 'ABSPATH' ) || exit;

do_action( 'wpss_email_content_before', 'milestone_revision_requested', $milestone, $recipient );
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php /* translators: %s: recipient first name. */ printf( esc_html__( 'Hi %s,', 'wp-sell-services' ), esc_html( $vendor_name ) ); ?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: 1: buyer, 2: phase title */
		esc_html__( '%1$s asked for changes to %2$s. Update the work and submit it again.', 'wp-sell-services' ),
		'<strong>' . esc_html( $buyer_name ) . '</strong>',
		'<strong>' . esc_html( $phase_title ) . '</strong>'
	);
	?>
</p>

<?php if ( '' !== $reason ) : ?>
	<p style="margin: 0 0 8px 0; font-size: 14px; color: #666;">
		<strong><?php esc_html_e( 'What the buyer wants changed:', 'wp-sell-services' ); ?></strong>
	</p>
	<blockquote style="margin: 0 0 24px 0; padding: 12px 16px; border-left: 3px solid #d1d5db; color: #3c3c3c; font-style: italic; white-space: pre-wrap;">
		<?php echo esc_html( $reason ); ?>
	</blockquote>
<?php endif; ?>

<p style="margin: 0 0 24px 0; text-align: center;">
	<a href="<?php echo esc_url( $order_url ); ?>" style="display: inline-block; padding: 12px 24px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
		<?php esc_html_e( 'Open the order', 'wp-sell-services' ); ?>
	</a>
</p>

<?php
do_action( 'wpss_email_content_after', 'milestone_revision_requested', $milestone, $recipient );
