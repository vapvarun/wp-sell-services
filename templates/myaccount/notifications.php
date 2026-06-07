<?php
/**
 * Notifications - My Account Template
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var array $notifications Notification objects.
 * @var int   $user_id       Current user ID.
 */

defined( 'ABSPATH' ) || exit;

$notification_icons = array(
	'order_created'      => '📦',
	'order_status'       => '🔄',
	'new_message'        => '💬',
	'delivery_submitted' => '📤',
	'delivery_accepted'  => '✅',
	'revision_requested' => '🔁',
	'review_received'    => '⭐',
	'dispute_opened'     => '⚠️',
	'dispute_resolved'   => '✓',
	'deadline_warning'   => '⏰',
);

/**
 * Fires before the notifications content.
 *
 * @since 1.1.0
 *
 * @param int $user_id Current user ID.
 */
do_action( 'wpss_notifications_before', $user_id );
?>

<div class="wpss-notifications">
	<div class="wpss-notifications-header">
		<h2><?php esc_html_e( 'Notifications', 'wp-sell-services' ); ?></h2>
		<?php if ( ! empty( $notifications ) ) : ?>
			<button type="button" class="button wpss-mark-all-read" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wpss_notification_nonce' ) ); ?>">
				<?php esc_html_e( 'Mark All as Read', 'wp-sell-services' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<?php if ( empty( $notifications ) ) : ?>
		<div class="wpss-no-notifications">
			<p><?php esc_html_e( 'You have no notifications.', 'wp-sell-services' ); ?></p>
		</div>
	<?php else : ?>
		<div class="wpss-notifications-list">
			<?php foreach ( $notifications as $notification ) : ?>
				<?php
				$data      = $notification->data ? json_decode( $notification->data, true ) : array();
				$icon      = $notification_icons[ $notification->type ] ?? '📣';
				$is_unread = ! $notification->is_read;
				$created   = new \DateTimeImmutable( $notification->created_at );
				?>
				<div class="wpss-notification <?php echo $is_unread ? 'wpss-unread' : ''; ?>" data-id="<?php echo esc_attr( $notification->id ); ?>">
					<div class="wpss-notification-icon"><?php echo esc_html( $icon ); ?></div>
					<div class="wpss-notification-content">
						<div class="wpss-notification-title"><?php echo esc_html( $notification->title ); ?></div>
						<div class="wpss-notification-message"><?php echo esc_html( $notification->message ); ?></div>
						<div class="wpss-notification-time">
							<?php
							/* translators: %s: time ago */
							printf( esc_html__( '%s ago', 'wp-sell-services' ), esc_html( human_time_diff( $created->getTimestamp(), time() ) ) );
							?>
						</div>
					</div>
					<?php if ( ! empty( $data['order_id'] ) ) : ?>
						<a href="<?php echo esc_url( wpss_get_order_url( (int) $data['order_id'] ) ); ?>" class="wpss-notification-link">
							<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
						</a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the notifications content.
 *
 * @since 1.1.0
 *
 * @param int $user_id Current user ID.
 */
do_action( 'wpss_notifications_after', $user_id );
?>


<script>
document.addEventListener('DOMContentLoaded', function() {
	const markAllBtn = document.querySelector('.wpss-mark-all-read');
	if (markAllBtn) {
		markAllBtn.addEventListener('click', function() {
			fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: 'action=wpss_mark_all_notifications_read&nonce=' + this.dataset.nonce
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					document.querySelectorAll('.wpss-notification.wpss-unread').forEach(el => {
						el.classList.remove('wpss-unread');
					});
				}
			});
		});
	}
});
</script>
