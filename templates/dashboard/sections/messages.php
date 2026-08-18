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

/**
 * Filter how many conversations one page of the messages list shows.
 *
 * @since 1.6.0
 *
 * @param int $per_page Conversations per page.
 * @param int $user_id  Current user ID.
 */
$conversations_per_page = max( 1, (int) apply_filters( 'wpss_messages_per_page', 20, $user_id ) );

// Paginated, with a real total. This used to be a hardcoded LIMIT 20 with no
// OFFSET, no COUNT and no navigation, while the unread banner below counted
// EVERY conversation — so a vendor with more than twenty threads saw a banner
// that could not be reconciled with the rows on screen, and had no route to the
// rest of them (Basecamp 10208075268).
// Read from our OWN query arg, not `paged`.
//
// The dashboard is a singular page with section routing (/dashboard/messages/),
// so WordPress does not populate `paged` here and the pretty form the default
// paginator builds — /dashboard/messages/page/2/ — 404s. Verified in the browser
// before choosing this. A dedicated arg also cannot collide with WP's own
// singular-page pagination or trip redirect_canonical.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page number on a public list.
$conversations_page  = isset( $_GET['wpss_paged'] ) ? max( 1, absint( wp_unslash( $_GET['wpss_paged'] ) ) ) : 1;
$conversations_total = $conversation_repo->count_conversations_for_user( $user_id );
$conversations       = $conversation_repo->get_conversation_summary(
	$user_id,
	$conversations_per_page,
	( $conversations_page - 1 ) * $conversations_per_page
);
$unread_count        = $conversation_repo->count_unread_for_user( $user_id );

// Check if viewing a specific conversation thread.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$active_conversation_id = isset( $_GET['conversation_id'] ) ? absint( wp_unslash( $_GET['conversation_id'] ) ) : 0;
?>

<div class="wpss-section wpss-section--messages wpss-card">

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
				<a href="<?php echo esc_url( wpss_get_dashboard_url( 'messages' ) ); ?>" class="wpss-btn wpss-btn--sm wpss-btn--outline">&larr; <?php esc_html_e( 'Back', 'wp-sell-services' ); ?></a>
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
						// The SAME renderer the order conversation, the REST endpoint and
						// the AJAX handlers use. This template used to hand-roll its own
						// message markup, which is why the dashboard thread had no image
						// previews, lost the original filename on attachments, showed no
						// read receipts, rendered system messages as ordinary ones, and
						// let long URLs run out of the container - the shared renderer's
						// .wpss-messaging__text carries the break-word rule
						// (Basecamp #10159632931).
						//
						// Two renderers for one thing is how they drift; there is now one.
						echo wpss_render_message_row( $msg, $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer returns internally-escaped markup.
						?>
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
					<div class="wpss-form-group">
						<label for="wpss-reply-attachments" class="wpss-messaging__composer-action">
							<i data-lucide="paperclip" class="wpss-icon" aria-hidden="true"></i>
							<span><?php esc_html_e( 'Attach files', 'wp-sell-services' ); ?></span>
						</label>
						<?php // Same accept list as the order composer - one answer to "what may be uploaded". ?>
						<input type="file" name="attachments[]" id="wpss-reply-attachments" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display:none;">
						<span class="wpss-messaging__composer-attachments" aria-live="polite"></span>
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

						// REST: POST /conversations/{id}/messages (text-only here).
						var convId = $form.find('[name="conversation_id"]').val();
						$.ajax({
							url: '<?php echo esc_url_raw( rest_url( 'wpss/v1/conversations/' ) ); ?>' + convId + '/messages',
							method: 'POST',
							// FormData, not a plain object: the endpoint already parses
							// multipart attachments (wpss_handle_message_attachments),
							// the dashboard form simply never sent any.
							data: (function() {
								var fd = new FormData();
								fd.append('content', $textarea.val());
								var files = $form.find('input[type="file"]')[0];
								if (files && files.files) {
									for (var i = 0; i < files.files.length; i++) {
										fd.append('attachments[]', files.files[i]);
									}
								}
								return fd;
							})(),
							processData: false,
							contentType: false,
							beforeSend: function(xhr) {
								xhr.setRequestHeader('X-WP-Nonce', '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>');
							},
							success: function() {
								location.reload();
							},
							error: function(xhr) {
								var msg = (xhr.responseJSON && xhr.responseJSON.message)
									|| '<?php echo esc_js( __( 'Failed to send message.', 'wp-sell-services' ) ); ?>';
								wpssShowNotice(msg, 'error');
							},
							complete: function() {
								$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Send', 'wp-sell-services' ) ); ?>');
							}
						});
					});

					// Name the files the moment they are picked - a file input that
					// shows nothing back looks broken.
					$('#wpss-reply-attachments').on('change', function() {
						var names = [];
						for (var i = 0; i < this.files.length; i++) { names.push(this.files[i].name); }
						$(this).closest('.wpss-form-group').find('.wpss-messaging__composer-attachments').text(names.join(', '));
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
			<a href="<?php echo esc_url( wpss_get_dashboard_url( 'messages' ) ); ?>">&larr; <?php esc_html_e( 'Back to Messages', 'wp-sell-services' ); ?></a>
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

				$unread_data = $conversation->unread_counts ? json_decode( $conversation->unread_counts, true ) : array();
				$my_unread   = (int) ( $unread_data[ $user_id ] ?? 0 );

				// A closed thread must not carry an unread badge. The banner's
				// count_unread_for_user() excludes closed conversations, but this
				// list does not filter them out (history stays visible), so
				// without this the badges on screen and the banner disagreed.
				$is_unread         = $my_unread > 0 && empty( $conversation->is_closed );
				$last_message_time = ! empty( $conversation->last_message_at ) ? strtotime( $conversation->last_message_at . ' UTC' ) : false;
				$time_ago          = $last_message_time ? human_time_diff( $last_message_time, time() ) : '';

				// EVERY row leads with the person, never the thing being discussed.
				// Order conversations used to title themselves with the service,
				// so a buyer saw "Maya Chen" on one row and "I will design a
				// modern, memorable…" on the next three — no name at all — and two
				// rows for the same service were distinguishable only by an order
				// number, which is the one thing a human cannot use.
				//
				// The other participant is resolved ONCE here and reused by the
				// avatar below, which repeated this same lookup per row.
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

				// participants can be NULL on older order conversations; recover the
				// other party from the order itself. Hoisted out of the avatar
				// block so the NAME and the AVATAR can never disagree about who
				// this conversation is with.
				if ( ! $other_user_id && ! empty( $conversation->order_id ) ) {
					global $wpdb;

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$order_row = $wpdb->get_row(
						$wpdb->prepare( "SELECT customer_id, vendor_id FROM {$wpdb->prefix}wpss_orders WHERE id = %d", $conversation->order_id )
					);

					if ( $order_row ) {
						$other_user_id = ( (int) $order_row->vendor_id !== $user_id )
							? (int) $order_row->vendor_id
							: (int) $order_row->customer_id;
					}
				}

				$other_user = $other_user_id ? get_userdata( $other_user_id ) : null;

				// What the conversation is ABOUT — the secondary line.
				$request_post = null;

				if ( ! $service && ! empty( $conversation->platform ) && 'request' === $conversation->platform && $conversation->platform_order_id ) {
					$request_post = get_post( $conversation->platform_order_id );
				}

				$conversation_subject = $service
					? wp_trim_words( $service->post_title, 6 )
					: ( ! empty( $request_post )
						? wp_trim_words( $request_post->post_title, 6 )
						: sprintf(
							/* translators: %s: order number */
							__( 'Order #%s', 'wp-sell-services' ),
							$conversation->order_number
						)
					);

				// Name first; fall back to the subject only when the other party
				// cannot be resolved (a deleted account), never to nothing.
				$conversation_title = $other_user
					? $other_user->display_name
					: ( $is_direct
						? ( ! empty( $conversation->subject ) ? $conversation->subject : __( 'Direct Message', 'wp-sell-services' ) )
						: $conversation_subject );
				?>
				<a href="<?php echo esc_url( $conv_url ); ?>" class="wpss-conversation-card <?php echo $is_unread ? 'wpss-conversation-card--unread' : ''; ?>">
					<div class="wpss-conversation-card__avatar">
						<?php
						// The other participant, already resolved above for the row
						// title — including the order-record fallback. This block
						// used to repeat that whole lookup, second JSON decode and
						// per-row query included, and could disagree with the name.
						$avatar_user_id = $other_user_id;

						if ( $avatar_user_id ) :
							?>
							<?php echo get_avatar( $avatar_user_id, 48 ); ?>
						<?php else : ?>
							<div class="wpss-conversation-card__placeholder">
								<i data-lucide="layout-grid" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
							</div>
						<?php endif; ?>
						<?php if ( $is_unread ) : ?>
							<?php
							/*
							 * The preview line below can legitimately show YOUR last
							 * message - the newest message in a thread that still has
							 * older unread ones from the other party. Until 1.6.0 the
							 * badge beside it rendered as a bare DOT: the stylesheet
							 * pushed the count off screen with text-indent: -999999px,
							 * so the template wrote a number nobody could see. The row
							 * therefore read as "your own message is unread", with no
							 * figure anywhere to correct it - which is exactly what was
							 * reported (Basecamp 10208075268). The count was always
							 * right; it was invisible.
							 *
							 * The number is shown now, capped so a very busy thread
							 * cannot stretch the bubble, and labelled so it is explicit
							 * whose messages it counts.
							 */
							$unread_display = $my_unread > 99 ? '99+' : (string) $my_unread;
							$unread_label   = $other_user
								? sprintf(
									/* translators: 1: number of unread messages, 2: other participant's name */
									_n( '%1$d unread message from %2$s', '%1$d unread messages from %2$s', $my_unread, 'wp-sell-services' ),
									$my_unread,
									$other_user->display_name
								)
								: sprintf(
									/* translators: %d: number of unread messages */
									_n( '%d unread message', '%d unread messages', $my_unread, 'wp-sell-services' ),
									$my_unread
								);
							?>
							<span class="wpss-conversation-card__badge" title="<?php echo esc_attr( $unread_label ); ?>">
								<span aria-hidden="true"><?php echo esc_html( $unread_display ); ?></span>
								<span class="screen-reader-text"><?php echo esc_html( $unread_label ); ?></span>
							</span>
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
							} else {
								// An order opens its conversation before anyone has
								// written in it. Those rows rendered an empty line and
								// no timestamp, which read as broken rather than new.
								echo '<em>' . esc_html__( 'No messages yet — say hello', 'wp-sell-services' ) . '</em>';
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
								// The service, not the order number. Two rows for the
								// same seller are told apart by what they are about;
								// an order number identifies nothing to a human, and
								// it is already on the order itself.
								echo esc_html( $conversation_subject );
								?>
							</span>
						<?php endif; ?>
					</div>
				</a>
			<?php endforeach; ?>
		</div>

		<?php
		// The route to conversation 21+, which did not exist before. Same shared
		// paginator the other custom-table surfaces use, but with an explicit
		// base: its default builds a pretty /page/2/ URL, which 404s on this
		// section-routed dashboard page.
		wpss_render_pagination(
			(int) ceil( $conversations_total / $conversations_per_page ),
			array(
				'base'    => add_query_arg( 'wpss_paged', '%#%', wpss_get_dashboard_url( 'messages' ) ),
				'format'  => '',
				'current' => $conversations_page,
			)
		);
		?>
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
