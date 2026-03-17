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

<style>
.wpss-notifications {
	padding: 20px 0;
}

.wpss-notifications-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
}

.wpss-notifications-header h2 {
	margin: 0;
}

.wpss-notifications-list {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.wpss-notification {
	display: flex;
	align-items: flex-start;
	gap: 15px;
	padding: 15px;
	background: #fff;
	border: 1px solid #e5e5e5;
	border-radius: 8px;
	transition: background 0.2s;
}

.wpss-notification.wpss-unread {
	background: #f0f8ff;
	border-color: #74b9ff;
}

.wpss-notification-icon {
	font-size: 24px;
	line-height: 1;
}

.wpss-notification-content {
	flex: 1;
}

.wpss-notification-title {
	font-weight: 600;
	margin-bottom: 4px;
}

.wpss-notification-message {
	color: #636e72;
	font-size: 14px;
	margin-bottom: 4px;
}

.wpss-notification-time {
	font-size: 12px;
	color: #999;
}

.wpss-notification-link {
	display: inline-block;
	padding: 6px 12px;
	background: #f5f5f5;
	border-radius: 4px;
	text-decoration: none;
	font-size: 13px;
	color: #333;
}

.wpss-notification-link:hover {
	background: #e5e5e5;
}

.wpss-no-notifications {
	text-align: center;
	padding: 40px 20px;
	color: #636e72;
}
</style>

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
