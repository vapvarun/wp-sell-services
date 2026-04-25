<?php
/**
 * Review Received Email (HTML)
 *
 * Sent to the vendor when a buyer leaves a review on a completed order.
 * Surfaces the rating, the written review, and a deep link to the public
 * review on the service page so the vendor can read + reply.
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.1.0
 *
 * @var \WP_User $recipient     Vendor.
 * @var string   $vendor_name
 * @var string   $buyer_name
 * @var int      $rating        1-5 stars.
 * @var string   $comment       Buyer's written review (may be empty).
 * @var string   $service_title
 * @var string   $service_url   Public URL for the service (where review appears).
 */

defined( 'ABSPATH' ) || exit;

$rating  = max( 1, min( 5, (int) $rating ) );
$comment = trim( (string) $comment );
$stars   = str_repeat( '★', $rating ) . str_repeat( '☆', 5 - $rating );
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php printf( esc_html__( 'Hi %s,', 'wp-sell-services' ), esc_html( $vendor_name ) ); ?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: 1: buyer name, 2: service title */
		esc_html__( '%1$s left a review on %2$s.', 'wp-sell-services' ),
		'<strong>' . esc_html( $buyer_name ) . '</strong>',
		'<strong>' . esc_html( $service_title ) . '</strong>'
	);
	?>
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin: 0 0 24px 0; border-collapse: collapse;">
	<tr>
		<td style="padding: 20px; background: #fefce8; border-radius: 8px; border-left: 3px solid #eab308; font-size: 14px; color: #3c3c3c; line-height: 1.6;">
			<div style="font-size: 24px; color: #eab308; margin-bottom: 8px; letter-spacing: 2px;">
				<?php echo esc_html( $stars ); ?>
				<span style="font-size: 14px; color: #6b7280; margin-left: 8px;">
					<?php
					printf(
						/* translators: %d: rating out of 5 */
						esc_html__( '%d / 5', 'wp-sell-services' ),
						$rating
					);
					?>
				</span>
			</div>
			<?php if ( '' !== $comment ) : ?>
				<blockquote style="margin: 12px 0 0 0; padding: 12px 16px; background: #ffffff; border-left: 2px solid #d1d5db; font-style: italic; color: #4b5563;">
					<?php echo esc_html( $comment ); ?>
				</blockquote>
			<?php else : ?>
				<p style="margin: 8px 0 0 0; color: #6b7280; font-size: 13px;">
					<?php esc_html_e( 'No written comment was left.', 'wp-sell-services' ); ?>
				</p>
			<?php endif; ?>
		</td>
	</tr>
</table>

<?php if ( '' !== $service_url ) : ?>
	<p style="margin: 0 0 24px 0; text-align: center;">
		<a href="<?php echo esc_url( $service_url ); ?>" style="display: inline-block; padding: 12px 24px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 600;">
			<?php esc_html_e( 'View on your service page', 'wp-sell-services' ); ?>
		</a>
	</p>
<?php endif; ?>

<p style="margin: 0; font-size: 13px; color: #6b7280; line-height: 1.5;">
	<?php esc_html_e( "Tip: thoughtful replies to reviews build trust with future buyers. You can reply from the service page.", 'wp-sell-services' ); ?>
</p>
