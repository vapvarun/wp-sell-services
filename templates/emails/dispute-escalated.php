<?php
/**
 * Dispute Escalated Email (HTML)
 *
 * Sent to admins when a dispute is escalated and requires urgent review.
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/emails/dispute-escalated.php
 *
 * @package WPSellServices\Templates\Emails
 * @since   1.0.0
 *
 * @var int      $dispute_id Dispute ID.
 * @var int      $order_id   Order ID associated with the dispute.
 * @var string   $reason     Escalation reason.
 * @var WP_User  $recipient  Recipient user object (admin).
 */

defined( 'ABSPATH' ) || exit;

$base_color = $base_color ?? '#7f54b3';

/**
 * Fires before the email content for the dispute escalated email.
 *
 * @since 1.0.0
 *
 * @param int     $dispute_id Dispute ID.
 * @param WP_User $recipient  Recipient user object.
 */
do_action( 'wpss_email_content_before', 'dispute_escalated', $dispute_id, $recipient );
?>

<p style="margin: 0 0 16px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php
	printf(
		/* translators: %s: recipient name */
		esc_html__( 'Hi %s,', 'wp-sell-services' ),
		esc_html( $recipient ? $recipient->display_name : __( 'there', 'wp-sell-services' ) )
	);
	?>
</p>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'A dispute has been escalated and requires your immediate attention. Please review and mediate between both parties.', 'wp-sell-services' ); ?>
</p>

<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; background-color: #f9f9f9; border-radius: 4px;">
	<tbody>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 35%; font-weight: 600;"><?php esc_html_e( 'Dispute ID', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( '#' . $dispute_id ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; border-bottom: 1px solid #e5e5e5; width: 35%; font-weight: 600;"><?php esc_html_e( 'Order ID', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html( '#' . $order_id ); ?></td>
		</tr>
		<tr>
			<th style="padding: 12px; text-align: left; font-weight: 600;"><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
			<td style="padding: 12px; font-weight: 600; color: #dc3545;"><?php esc_html_e( 'Escalated', 'wp-sell-services' ); ?></td>
		</tr>
	</tbody>
</table>

<?php if ( ! empty( $reason ) ) : ?>
<div style="background: #f8d7da; padding: 16px; border-radius: 4px; margin: 20px 0;">
	<strong style="color: #721c24;"><?php esc_html_e( 'Escalation Reason:', 'wp-sell-services' ); ?></strong>
	<p style="margin: 8px 0 0; color: #721c24;"><?php echo esc_html( $reason ); ?></p>
</div>
<?php endif; ?>

<p style="margin: 0 0 20px 0; font-size: 16px; color: #3c3c3c; line-height: 1.6;">
	<?php esc_html_e( 'Please investigate this dispute and take the appropriate action to resolve it promptly.', 'wp-sell-services' ); ?>
</p>

<p style="text-align: center; margin: 30px 0;">
	<?php
	/**
	 * Filters the button URL for the dispute escalated email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_url Default button URL.
	 * @param int    $dispute_id Dispute ID.
	 */
	$button_url = apply_filters(
		'wpss_email_button_url',
		admin_url( 'admin.php?page=wpss-disputes&dispute_id=' . $dispute_id ),
		'dispute_escalated',
		$dispute_id
	);

	/**
	 * Filters the button text for the dispute escalated email.
	 *
	 * @since 1.0.0
	 *
	 * @param string $button_text Default button text.
	 */
	$button_text = apply_filters( 'wpss_email_button_text', __( 'Review Dispute', 'wp-sell-services' ), 'dispute_escalated' );
	?>
	<a href="<?php echo esc_url( $button_url ); ?>" style="display: inline-block; background-color: #dc3545; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: 600;">
		<?php echo esc_html( $button_text ); ?>
	</a>
</p>

<?php
/**
 * Fires after the email content for the dispute escalated email.
 *
 * @since 1.0.0
 *
 * @param int     $dispute_id Dispute ID.
 * @param WP_User $recipient  Recipient user object.
 */
do_action( 'wpss_email_content_after', 'dispute_escalated', $dispute_id, $recipient );
