<?php
/**
 * Dashboard Section: Messages
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

use WPSellServices\Database\Repositories\ConversationRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Fires before the messages dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('messages').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'messages', $user_id );

$conversation_repo = new ConversationRepository();
$conversations     = $conversation_repo->get_conversation_summary( $user_id, 20 );
$unread_count      = $conversation_repo->count_unread_for_user( $user_id );
?>

<div class="wpss-section wpss-section--messages">
	<?php if ( $unread_count > 0 ) : ?>
		<div class="wpss-alert wpss-alert--info">
			<?php
			printf(
				/* translators: %d: number of unread messages */
				esc_html( _n( 'You have %d unread message.', 'You have %d unread messages.', $unread_count, 'wp-sell-services' ) ),
				(int) $unread_count
			);
			?>
		</div>
	<?php endif; ?>

	<?php if ( empty( $conversations ) ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4c0-1.1.9-2 2-2h8a2 2 0 0 1 2 2v5Z"/><path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1"/></svg>
			</div>
			<h3><?php esc_html_e( 'No messages yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'Your conversations with buyers and sellers will appear here.', 'wp-sell-services' ); ?></p>
		</div>
	<?php else : ?>
		<div class="wpss-conversations-list">
			<?php foreach ( $conversations as $conversation ) : ?>
				<?php
				$service   = $conversation->service_id ? get_post( $conversation->service_id ) : null;
				$order_url = wpss_get_order_url( $conversation->order_id );
				$unread_data    = $conversation->unread_counts ? json_decode( $conversation->unread_counts, true ) : array();
				$my_unread      = (int) ( $unread_data[ $user_id ] ?? 0 );
				$is_unread      = $my_unread > 0;
				$last_message_time = ! empty( $conversation->last_message_at ) ? strtotime( $conversation->last_message_at ) : false;
				$time_ago          = $last_message_time ? human_time_diff( $last_message_time, current_time( 'timestamp' ) ) : '';

				// For request-based orders, use the request title.
				if ( ! $service && ! empty( $conversation->platform ) && 'request' === $conversation->platform && $conversation->platform_order_id ) {
					$request_post = get_post( $conversation->platform_order_id );
				}
				$conversation_title = $service ? wp_trim_words( $service->post_title, 6 ) : ( ! empty( $request_post ) ? wp_trim_words( $request_post->post_title, 6 ) : sprintf( __( 'Order #%s', 'wp-sell-services' ), $conversation->order_number ) );
				?>
				<a href="<?php echo esc_url( $order_url ); ?>" class="wpss-conversation-card <?php echo $is_unread ? 'wpss-conversation-card--unread' : ''; ?>">
					<div class="wpss-conversation-card__avatar">
						<?php if ( $service && has_post_thumbnail( $service ) ) : ?>
							<?php echo get_the_post_thumbnail( $service, 'thumbnail' ); ?>
						<?php else : ?>
							<div class="wpss-conversation-card__placeholder">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M7 7h.01"/><path d="M17 7h.01"/><path d="M7 17h.01"/><path d="M17 17h.01"/></svg>
							</div>
						<?php endif; ?>
						<?php if ( $is_unread ) : ?>
							<span class="wpss-conversation-card__badge"><?php echo esc_html( $my_unread ); ?></span>
						<?php endif; ?>
					</div>
					<div class="wpss-conversation-card__content">
						<div class="wpss-conversation-card__header">
							<span class="wpss-conversation-card__name">
								<?php echo esc_html( $conversation_title ); ?>
							</span>
							<span class="wpss-conversation-card__time"><?php echo esc_html( $time_ago ); ?></span>
						</div>
						<p class="wpss-conversation-card__preview">
							<?php echo esc_html( wp_trim_words( $conversation->last_message ?? '', 15 ) ); ?>
						</p>
						<span class="wpss-conversation-card__order">
							<?php
							printf(
								/* translators: %s: order number */
								esc_html__( 'Order #%s', 'wp-sell-services' ),
								esc_html( $conversation->order_number )
							);
							?>
						</span>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the messages dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('messages').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'messages', $user_id );
?>
