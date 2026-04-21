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
use WPSellServices\Services\ConversationService;

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

// Check if viewing a specific conversation thread.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$active_conversation_id = isset( $_GET['conversation_id'] ) ? absint( wp_unslash( $_GET['conversation_id'] ) ) : 0;
?>

<div class="wpss-section wpss-section--messages">

<?php if ( $active_conversation_id ) : ?>
	<?php
	$conversation_service = new ConversationService();
	$active_conversation  = $conversation_service->get( $active_conversation_id );

	if ( $active_conversation && $active_conversation->can_view( $user_id ) ) :
		$messages      = $conversation_service->get_messages( $active_conversation_id, array( 'limit' => 100 ) );
		$participants  = $active_conversation->participants;
		$other_user_id = 0;

		foreach ( $participants as $pid ) {
			if ( (int) $pid !== $user_id ) {
				$other_user_id = (int) $pid;
				break;
			}
		}
		$other_user = $other_user_id ? get_userdata( $other_user_id ) : null;
		$conv_title = $other_user
			? $other_user->display_name
			: ( $active_conversation->subject ?: __( 'Direct Message', 'wp-sell-services' ) );

		// Mark conversation as read.
		$conversation_repo->mark_read( $active_conversation_id, $user_id );
		?>
		<div class="wpss-conversation-thread">
			<div class="wpss-conversation-thread__header">
				<a href="<?php echo esc_url( add_query_arg( 'section', 'messages', wpss_get_dashboard_url() ) ); ?>" class="wpss-btn wpss-btn--sm wpss-btn--outline">&larr; <?php esc_html_e( 'Back', 'wp-sell-services' ); ?></a>
				<h3><?php echo esc_html( $conv_title ); ?></h3>
				<?php if ( $active_conversation->subject ) : ?>
					<span class="wpss-conversation-thread__subject"><?php echo esc_html( $active_conversation->subject ); ?></span>
				<?php endif; ?>
			</div>

			<div class="wpss-conversation-thread__messages" id="wpss-messages-container">
				<?php if ( empty( $messages ) ) : ?>
					<p class="wpss-text-muted"><?php esc_html_e( 'No messages yet.', 'wp-sell-services' ); ?></p>
				<?php else : ?>
					<?php foreach ( $messages as $msg ) : ?>
						<?php
						$is_mine   = (int) $msg->sender_id === $user_id;
						$sender    = get_userdata( (int) $msg->sender_id );
						$msg_class = $is_mine ? 'wpss-message--mine' : 'wpss-message--theirs';
						?>
						<div class="wpss-message <?php echo esc_attr( $msg_class ); ?>">
							<div class="wpss-message__avatar">
								<?php echo get_avatar( (int) $msg->sender_id, 36 ); ?>
							</div>
							<div class="wpss-message__body">
								<div class="wpss-message__meta">
									<strong><?php echo esc_html( $sender ? $sender->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?></strong>
									<time>
										<?php
										$msg_ts = $msg->created_at instanceof DateTimeInterface ? $msg->created_at->getTimestamp() : strtotime( $msg->created_at );
										echo esc_html( human_time_diff( $msg_ts, current_time( 'timestamp' ) ) );
										?>
										<?php esc_html_e( 'ago', 'wp-sell-services' ); ?>
									</time>
								</div>
								<div class="wpss-message__content"><?php echo wp_kses_post( wpautop( $msg->content ) ); ?></div>
								<?php if ( ! empty( $msg->attachments ) ) : ?>
									<?php $atts = is_string( $msg->attachments ) ? json_decode( $msg->attachments, true ) : $msg->attachments; ?>
									<?php if ( ! empty( $atts ) && is_array( $atts ) ) : ?>
										<div class="wpss-message__attachments">
											<?php foreach ( $atts as $att_id ) : ?>
												<?php
												$att_id  = is_array( $att_id ) ? ( $att_id['id'] ?? 0 ) : (int) $att_id;
												$att_url = $att_id ? wp_get_attachment_url( $att_id ) : '';
												?>
												<?php if ( $att_url ) : ?>
													<a href="<?php echo esc_url( $att_url ); ?>" target="_blank" class="wpss-attachment-link"><?php echo esc_html( basename( $att_url ) ); ?></a>
												<?php endif; ?>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<?php if ( $active_conversation->can_reply( $user_id ) ) : ?>
				<form id="wpss-reply-form" class="wpss-conversation-thread__reply">
					<?php wp_nonce_field( 'wpss_send_message', 'wpss_message_nonce' ); ?>
					<input type="hidden" name="conversation_id" value="<?php echo esc_attr( $active_conversation_id ); ?>">
					<div class="wpss-form-group">
						<label for="wpss-reply-message" class="screen-reader-text"><?php esc_html_e( 'Message', 'wp-sell-services' ); ?></label>
						<textarea name="message" id="wpss-reply-message" class="wpss-textarea" rows="3" placeholder="<?php esc_attr_e( 'Type your message...', 'wp-sell-services' ); ?>" required></textarea>
					</div>
					<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--sm"><?php esc_html_e( 'Send', 'wp-sell-services' ); ?></button>
				</form>
				<script>
				function wpssShowNotice(msg, type) {
					type = type || 'error';
					var bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
					var borderColor = type === 'success' ? '#c3e6cb' : '#f5c6cb';
					var textColor = type === 'success' ? '#155724' : '#721c24';
					var $notice = jQuery('<div class="wpss-inline-notice" style="padding:12px 16px;margin:10px 0;border:1px solid ' + borderColor + ';border-radius:4px;background:' + bgColor + ';color:' + textColor + ';position:relative;">' + msg + '<span style="position:absolute;right:10px;top:8px;cursor:pointer;font-size:18px;line-height:1;">&times;</span></div>');
					$notice.find('span').on('click', function() { $notice.fadeOut(200, function() { $notice.remove(); }); });
					jQuery('.wpss-conversation-thread, .wpss-dashboard').first().prepend($notice);
					setTimeout(function() { $notice.fadeOut(400, function() { $notice.remove(); }); }, 8000);
				}
				jQuery(function($) {
					$('#wpss-reply-form').on('submit', function(e) {
						e.preventDefault();
						var $form = $(this);
						var $btn = $form.find('button[type="submit"]');
						var $textarea = $form.find('textarea[name="message"]');

						if (!$textarea.val().trim()) return;

						$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Sending...', 'wp-sell-services' ) ); ?>');

						$.ajax({
							url: '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
							type: 'POST',
							data: {
								action: 'wpss_send_direct_message',
								conversation_id: $form.find('[name="conversation_id"]').val(),
								message: $textarea.val(),
								wpss_message_nonce: $form.find('[name="wpss_message_nonce"]').val()
							},
							success: function(response) {
								if (response.success) {
									location.reload();
								} else {
									wpssShowNotice(response.data.message || '<?php echo esc_js( __( 'Failed to send message.', 'wp-sell-services' ) ); ?>', 'error');
								}
							},
							error: function() {
								wpssShowNotice('<?php echo esc_js( __( 'An error occurred.', 'wp-sell-services' ) ); ?>', 'error');
							},
							complete: function() {
								$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Send', 'wp-sell-services' ) ); ?>');
							}
						});
					});

					// Auto-scroll to bottom of messages.
					var container = document.getElementById('wpss-messages-container');
					if (container) container.scrollTop = container.scrollHeight;
				});
				</script>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="wpss-notice wpss-notice--error">
			<p><?php esc_html_e( 'Conversation not found or you do not have permission to view it.', 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( add_query_arg( 'section', 'messages', wpss_get_dashboard_url() ) ); ?>">&larr; <?php esc_html_e( 'Back to Messages', 'wp-sell-services' ); ?></a>
		</div>
	<?php endif; ?>

<?php else : // Conversation list view. ?>
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
				<i data-lucide="messages-square" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
			</div>
			<h3><?php esc_html_e( 'No messages yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'Your conversations with buyers and sellers will appear here.', 'wp-sell-services' ); ?></p>
		</div>
	<?php else : ?>
		<div class="wpss-conversations-list">
			<?php foreach ( $conversations as $conversation ) : ?>
				<?php
				$is_direct = empty( $conversation->order_id ) || 0 === (int) $conversation->order_id;
				$service   = ! empty( $conversation->service_id ) ? get_post( $conversation->service_id ) : null;

				// Build conversation URL: order-linked goes to order page, direct goes to messages section.
				if ( $is_direct ) {
					$conv_url = add_query_arg(
						array(
							'section'         => 'messages',
							'conversation_id' => $conversation->id,
						),
						wpss_get_dashboard_url()
					);
				} else {
					$conv_url = wpss_get_order_url( (int) $conversation->order_id );
				}

				$unread_data       = $conversation->unread_counts ? json_decode( $conversation->unread_counts, true ) : array();
				$my_unread         = (int) ( $unread_data[ $user_id ] ?? 0 );
				$is_unread         = $my_unread > 0;
				$last_message_time = ! empty( $conversation->last_message_at ) ? strtotime( $conversation->last_message_at ) : false;
				$time_ago          = $last_message_time ? human_time_diff( $last_message_time, current_time( 'timestamp' ) ) : '';

				// Determine conversation title.
				if ( $is_direct ) {
					// For direct conversations, show the other participant's name or the subject.
					$participants  = ! empty( $conversation->participants ) ? json_decode( $conversation->participants, true ) : array();
					$other_user_id = 0;
					if ( is_array( $participants ) ) {
						foreach ( $participants as $pid ) {
							if ( (int) $pid !== $user_id ) {
								$other_user_id = (int) $pid;
								break;
							}
						}
					}
					$other_user         = $other_user_id ? get_userdata( $other_user_id ) : null;
					$conversation_title = $other_user
						? $other_user->display_name
						: ( ! empty( $conversation->subject ) ? $conversation->subject : __( 'Direct Message', 'wp-sell-services' ) );
				} else {
					// For request-based orders, use the request title.
					$request_post = null;
					if ( ! $service && ! empty( $conversation->platform ) && 'request' === $conversation->platform && $conversation->platform_order_id ) {
						$request_post = get_post( $conversation->platform_order_id );
					}
					$conversation_title = $service
						? wp_trim_words( $service->post_title, 6 )
						: ( ! empty( $request_post )
							? wp_trim_words( $request_post->post_title, 6 )
							: sprintf(
								/* translators: %s: order number */
								__( 'Order #%s', 'wp-sell-services' ),
								$conversation->order_number
							)
						);
				}
				?>
				<a href="<?php echo esc_url( $conv_url ); ?>" class="wpss-conversation-card <?php echo $is_unread ? 'wpss-conversation-card--unread' : ''; ?>">
					<div class="wpss-conversation-card__avatar">
						<?php
						// Always show the other participant's avatar instead of the service image.
						$avatar_user_id = 0;
						if ( $is_direct && ! empty( $other_user_id ) ) {
							$avatar_user_id = $other_user_id;
						} else {
							// For order conversations, resolve the other party from participants.
							$order_participants = ! empty( $conversation->participants ) ? json_decode( $conversation->participants, true ) : array();
							if ( is_array( $order_participants ) && ! empty( $order_participants ) ) {
								foreach ( $order_participants as $p_id ) {
									if ( (int) $p_id !== $user_id ) {
										$avatar_user_id = (int) $p_id;
										break;
									}
								}
							}

							// Fallback: if participants is NULL, get the other party from the order record.
							if ( ! $avatar_user_id && ! empty( $conversation->order_id ) ) {
								global $wpdb;
								$order_row = $wpdb->get_row(
									$wpdb->prepare(
										"SELECT customer_id, vendor_id FROM {$wpdb->prefix}wpss_orders WHERE id = %d",
										$conversation->order_id
									)
								);
								if ( $order_row ) {
									$avatar_user_id = ( (int) $order_row->vendor_id !== $user_id )
										? (int) $order_row->vendor_id
										: (int) $order_row->customer_id;
								}
							}
						}

						if ( $avatar_user_id ) :
							?>
							<?php echo get_avatar( $avatar_user_id, 48 ); ?>
						<?php else : ?>
							<div class="wpss-conversation-card__placeholder">
								<i data-lucide="layout-grid" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
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
							<?php
							$last_msg_text = $conversation->last_message ?? '';
							if ( $last_msg_text ) {
								$sender_prefix = '';
								$last_sender   = (int) ( $conversation->last_message_sender_id ?? 0 );
								if ( $last_sender === $user_id ) {
									$sender_prefix = __( 'You: ', 'wp-sell-services' );
								} elseif ( 0 === $last_sender ) {
									$sender_prefix = ''; // System message — no prefix.
								}
								echo esc_html( $sender_prefix . wp_trim_words( $last_msg_text, 15 ) );
							}
							?>
						</p>
						<?php if ( $is_direct ) : ?>
							<span class="wpss-conversation-card__label">
								<?php echo esc_html( ! empty( $conversation->subject ) ? $conversation->subject : __( 'Direct Message', 'wp-sell-services' ) ); ?>
							</span>
						<?php else : ?>
							<span class="wpss-conversation-card__order">
								<?php
								printf(
									/* translators: %s: order number */
									esc_html__( 'Order #%s', 'wp-sell-services' ),
									esc_html( $conversation->order_number )
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				</a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

<?php endif; // End conversation list vs detail view. ?>
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
