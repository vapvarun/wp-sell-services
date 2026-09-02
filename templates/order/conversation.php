<?php
/**
 * Template: Order Conversation
 *
 * Displays the conversation/messaging interface for an order.
 * Uses CSS classes from messaging.css design system.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int    $order_id     Order ID.
 * @var object $order        Order object.
 * @var bool   $is_vendor    Whether current user is the vendor.
 * @var bool   $is_customer  Whether current user is the customer.
 *
 * Available Hooks:
 * - wpss_before_conversation
 * - wpss_conversation_header
 * - wpss_after_message
 * - wpss_conversation_form
 * - wpss_after_conversation
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $order_id ) || empty( $order ) ) {
	return;
}

// Enqueue messaging styles.
wp_enqueue_style( 'wpss-messaging', WPSS_PLUGIN_URL . 'assets/css/messaging.css', array( 'wpss-design-system' ), WPSS_VERSION );

// Enqueue frontend script. The `wpss` object (ajaxUrl, restUrl, nonce,
// restNonce) is localized once, authoritatively, in Frontend.php on
// wp_enqueue_scripts; re-localizing here during the_content runs too late
// (the script data is already printed) and would be a no-op.
wp_enqueue_script( 'wpss-frontend', WPSS_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), WPSS_VERSION, true );

$user_id     = get_current_user_id();
$is_vendor   = $is_vendor ?? ( (int) $order->vendor_id === $user_id );
$is_customer = $is_customer ?? ( (int) $order->customer_id === $user_id );

// Get conversation participants.
$vendor     = get_userdata( $order->vendor_id );
$customer   = get_userdata( $order->customer_id );
$other_user = $is_vendor ? $customer : $vendor;
$other_name = wpss_get_member_display_name( (int) ( $is_vendor ? $order->customer_id : $order->vendor_id ) );
$other_id   = $other_user ? $other_user->ID : 0;

// Get conversation for this order.
global $wpdb;
$conversation = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT * FROM {$wpdb->prefix}wpss_conversations WHERE order_id = %d LIMIT 1",
		$order_id
	)
);

// Get messages from the messages table.
$messages = array();
if ( $conversation ) {
	$messages = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT m.*, u.display_name as sender_name
			FROM {$wpdb->prefix}wpss_messages m
			LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
			WHERE m.conversation_id = %d
			ORDER BY m.created_at ASC",
			$conversation->id
		)
	);

	// Mark messages as read via read_by JSON field.
	if ( ! empty( $messages ) ) {
		$unread_ids = array();
		foreach ( $messages as $message ) {
			if ( (int) $message->sender_id !== $user_id ) {
				$read_by = $message->read_by ? json_decode( $message->read_by, true ) : array();
				if ( ! isset( $read_by[ $user_id ] ) ) {
					$unread_ids[] = (int) $message->id;
				}
			}
		}
		if ( ! empty( $unread_ids ) ) {
			foreach ( $unread_ids as $msg_id ) {
				$msg_row             = $wpdb->get_row( $wpdb->prepare( "SELECT read_by FROM {$wpdb->prefix}wpss_messages WHERE id = %d", $msg_id ) );
				$read_by             = $msg_row && $msg_row->read_by ? json_decode( $msg_row->read_by, true ) : array();
				$read_by[ $user_id ] = current_time( 'mysql', true );
				$wpdb->update(
					$wpdb->prefix . 'wpss_messages',
					array( 'read_by' => wp_json_encode( $read_by ) ),
					array( 'id' => $msg_id ),
					array( '%s' ),
					array( '%d' )
				);
			}

			// Also reset the unread count for this user in the conversation record.
			$unread_counts                      = $conversation->unread_counts ? json_decode( $conversation->unread_counts, true ) : array();
			$unread_counts[ (string) $user_id ] = 0;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$wpdb->prefix . 'wpss_conversations',
				array( 'unread_counts' => wp_json_encode( $unread_counts ) ),
				array( 'id' => $conversation->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}
}

// Can send messages? Allow messaging for every status where the order is still
// in flight — buyers and sellers need to communicate through requirements,
// approval, holds, lateness, cancellation requests and disputes, not just the
// original four. Only true terminals (completed / cancelled / refunded /
// partially_refunded / rejected) and the pre-payment states block the composer.
$active_message_statuses = array(
	'pending_requirements',
	'requirements_submitted',
	'accepted',
	'in_progress',
	'pending_approval',
	'revision_requested',
	'delivered',
	'on_hold',
	'late',
	'cancellation_requested',
	'disputed',
);
$can_message             = in_array( $order->status, $active_message_statuses, true );

/**
 * Hook: wpss_before_conversation
 *
 * Fires before the conversation interface is displayed.
 *
 * @since 1.0.0
 *
 * @param object $order Order object.
 */
do_action( 'wpss_before_conversation', $order );
?>

<div class="wpss-messaging wpss-messaging--order" data-order-id="<?php echo esc_attr( $order_id ); ?>" data-conversation-id="<?php echo esc_attr( $conversation ? (string) $conversation->id : '' ); ?>">
	<!-- Conversation Header -->
	<div class="wpss-messaging__header">
		<div class="wpss-messaging__header-info">
			<div class="wpss-messaging__header-avatar">
				<?php echo get_avatar( $other_id, 40 ); ?>
			</div>
			<div class="wpss-messaging__header-details">
				<span class="wpss-messaging__header-name"><?php echo esc_html( $other_name ); ?></span>
				<span class="wpss-messaging__header-status">
					<?php echo $is_vendor ? esc_html__( 'Buyer', 'wp-sell-services' ) : esc_html__( 'Seller', 'wp-sell-services' ); ?>
				</span>
			</div>
		</div>
		<div class="wpss-messaging__header-actions">
			<span class="<?php echo esc_attr( wpss_status_class( $order->status ) ); ?>">
				<?php echo esc_html( wpss_get_order_status_label( $order->status ) ); ?>
			</span>
		</div>
	</div>

	<?php
	/**
	 * Hook: wpss_conversation_header
	 *
	 * Fires after the conversation header is displayed.
	 *
	 * @since 1.0.0
	 *
	 * @param object $order Order object.
	 */
	do_action( 'wpss_conversation_header', $order );
	?>

	<!-- Messages Container -->
	<div class="wpss-messaging__messages" id="wpss-messages-container">
		<?php if ( empty( $messages ) ) : ?>
			<div class="wpss-messaging__empty">
				<div class="wpss-messaging__empty-icon">
					<i data-lucide="message-square" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
				</div>
				<h3 class="wpss-messaging__empty-title"><?php esc_html_e( 'No messages yet', 'wp-sell-services' ); ?></h3>
				<?php
				// $can_message is the single authority on whether this person may
				// send. The invite used to branch on its own status list, so
				// pending_payment fell through to "Start the conversation"
				// directly above the composer's "Messaging is not available for
				// this order status" (Basecamp 10240019323). When sending is
				// blocked the closed notice below states the reason; the empty
				// state must not contradict it by inviting.
				//
				// Built as a string first so the paragraph is not emitted at all
				// when there is nothing to put in it. It used to render an empty
				// <p> under the heading on every blocked non-terminal status,
				// which reads as a half-loaded panel.
				$wpss_empty_text = '';
				if ( $can_message ) {
					$wpss_empty_text = __( 'Start the conversation by sending a message!', 'wp-sell-services' );
				} elseif ( in_array( $order->status, array( 'completed', 'cancelled', 'refunded' ), true ) ) {
					$wpss_empty_text = __( 'No messages were exchanged during this order.', 'wp-sell-services' );
				}

				if ( '' !== $wpss_empty_text ) :
					?>
					<p class="wpss-messaging__empty-text"><?php echo esc_html( $wpss_empty_text ); ?></p>
					<?php
				endif;
				?>
			</div>
		<?php else : ?>
			<?php
			// CB7 (plans/ORDER-FLOW-AUDIT.md): friendlier date dividers so long
			// threads are easy to scan. "Today" / "Yesterday" for the most
			// common cases, weekday name for the previous 6 days, full date
			// for older messages. Replaces the previous "April 25, 2026"
			// repeated across every divider.
			$current_date_key  = '';
			$today_key         = wp_date( 'Y-m-d' );
			$yesterday_key     = wp_date( 'Y-m-d', strtotime( '-1 day' ) );
			$week_threshold_ts = strtotime( '-6 days', strtotime( $today_key ) );
			$weekday_format    = 'l'; // e.g. "Monday".

			$format_divider = static function ( int $message_ts ) use ( $today_key, $yesterday_key, $week_threshold_ts, $weekday_format ): string {
				$message_key = wp_date( 'Y-m-d', $message_ts );
				if ( $message_key === $today_key ) {
					return __( 'Today', 'wp-sell-services' );
				}
				if ( $message_key === $yesterday_key ) {
					return __( 'Yesterday', 'wp-sell-services' );
				}
				if ( $message_ts >= $week_threshold_ts ) {
					return wp_date( $weekday_format, $message_ts );
				}
				return wp_date( get_option( 'date_format', 'F j, Y' ), $message_ts );
			};

	foreach ( $messages as $message ) :
		$message_ts   = strtotime( $message->created_at );
		$divider_key  = wp_date( 'Y-m-d', $message_ts );
		$divider_text = $format_divider( $message_ts );
		$is_own       = (int) $message->sender_id === $user_id;
		$is_system    = 'system' === ( isset( $message->type ) ? $message->type : ( isset( $message->content_type ) ? $message->content_type : 'text' ) );

		// Date separator changes only when the calendar day changes.
		if ( $divider_key !== $current_date_key ) :
			$current_date_key = $divider_key;
			?>
					<div class="wpss-messaging__date-divider">
						<span class="wpss-messaging__date-text"><?php echo esc_html( $divider_text ); ?></span>
					</div>
				<?php endif; ?>

				<?php
				// Canonical message row markup — shared with the REST message
				// response and the AJAX/REST poll so first paint and appended
				// messages are byte-identical.
				echo wpss_render_message_row( $message, $user_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer returns internally-escaped markup.

				if ( ! $is_system ) {
					/**
					 * Hook: wpss_after_message
					 *
					 * Fires after each message is displayed.
					 *
					 * @since 1.0.0
					 *
					 * @param object $message Message object.
					 * @param object $order   Order object.
					 */
					do_action( 'wpss_after_message', $message, $order );
				}
				?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<!-- Message Input -->
	<?php if ( $can_message ) : ?>
		<?php
		/**
		 * Hook: wpss_conversation_form
		 *
		 * Fires in the message input area, before the form.
		 *
		 * @since 1.0.0
		 *
		 * @param object $order Order object.
		 */
		do_action( 'wpss_conversation_form', $order );
		?>
		<div class="wpss-messaging__composer">
			<form id="wpss-message-form" method="post" enctype="multipart/form-data" data-order="<?php echo esc_attr( $order_id ); ?>">
				<?php wp_nonce_field( 'wpss_send_message', 'wpss_message_nonce' ); ?>
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

				<div class="wpss-messaging__composer-attachments" id="wpss-attachments-preview"></div>

				<div class="wpss-messaging__composer-input-area">
					<label for="wpss-file-input" class="wpss-messaging__composer-btn" title="<?php esc_attr_e( 'Attach files', 'wp-sell-services' ); ?>">
						<i data-lucide="paperclip" class="wpss-icon" aria-hidden="true"></i>
						<input type="file" id="wpss-file-input" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display: none;">
					</label>

					<div class="wpss-messaging__composer-input-wrapper">
						<label for="wpss-message-input" class="screen-reader-text"><?php esc_html_e( 'Message', 'wp-sell-services' ); ?></label>
						<textarea name="message"
									id="wpss-message-input"
									class="wpss-messaging__composer-input"
									placeholder="<?php esc_attr_e( 'Type your message...', 'wp-sell-services' ); ?>"
									rows="1"
									maxlength="5000"
									required></textarea>
					</div>

					<button type="button" class="wpss-messaging__send-btn" id="wpss-send-btn" title="<?php esc_attr_e( 'Send message', 'wp-sell-services' ); ?>">
						<i data-lucide="send" class="wpss-icon" aria-hidden="true"></i>
					</button>
				</div>

				<div class="wpss-messaging__hint">
					<?php esc_html_e( 'Press Enter to send, Shift+Enter for new line', 'wp-sell-services' ); ?>
				</div>
			</form>
		</div>
	<?php else : ?>
		<div class="wpss-messaging__closed">
			<i data-lucide="lock" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
			<p>
				<?php
				if ( 'completed' === $order->status ) {
					esc_html_e( 'This order has been completed. You can no longer send messages.', 'wp-sell-services' );
				} elseif ( 'cancelled' === $order->status ) {
					esc_html_e( 'This order has been cancelled.', 'wp-sell-services' );
				} elseif ( 'refunded' === $order->status ) {
					if ( ! empty( $order->refunded_amount ) ) {
						printf(
							/* translators: %s: refunded amount */
							esc_html__( 'This order has been refunded (%s returned).', 'wp-sell-services' ),
							esc_html( wpss_format_price( (float) $order->refunded_amount, $order->currency ) )
						);
					} else {
						esc_html_e( 'This order has been refunded.', 'wp-sell-services' );
					}
				} elseif ( 'partially_refunded' === $order->status ) {
					// Already treated as terminal by the composer guard above,
					// but it had no message of its own — so a partially
					// refunded buyer fell through to the generic "Messaging is
					// not available" and was told nothing about their money.
					if ( ! empty( $order->refunded_amount ) ) {
						printf(
							/* translators: %s: refunded amount */
							esc_html__( 'This order has been partially refunded (%s returned).', 'wp-sell-services' ),
							esc_html( wpss_format_price( (float) $order->refunded_amount, $order->currency ) )
						);
					} else {
						esc_html_e( 'This order has been partially refunded.', 'wp-sell-services' );
					}
				} elseif ( in_array( $order->status, array( 'pending_payment', 'pending' ), true ) ) {
					// "Not available for this order status" told the buyer it was
					// shut without saying it would ever open, which reads as a
					// broken feature rather than a sequence. They are one step
					// away from being able to message; say which step.
					//
					// 'pending' is covered here because ServiceOrder declares and
					// labels it while nothing sets it - there are zero rows in it
					// on any install checked. Naming it costs one word and keeps
					// the coverage assertion in
					// tests/test-conversation-states-contract.php strict, so a
					// status added later cannot fall silently into the bare
					// "not available for this order status" line.
					esc_html_e( 'You can message this seller once the order is paid.', 'wp-sell-services' );
				} elseif ( 'rejected' === $order->status ) {
					esc_html_e( 'This order was declined, so messaging is closed.', 'wp-sell-services' );
				} else {
					esc_html_e( 'Messaging is not available for this order status.', 'wp-sell-services' );
				}
				?>
			</p>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Hook: wpss_after_conversation
 *
 * Fires after the conversation interface is displayed.
 *
 * @since 1.0.0
 *
 * @param object $order Order object.
 */
do_action( 'wpss_after_conversation', $order );
?>

<script>
function wpssShowNotice(msg, type) {
	type = type || 'error';
	var bgColor = type === 'success' ? '#d4edda' : '#f8d7da';
	var borderColor = type === 'success' ? '#c3e6cb' : '#f5c6cb';
	var textColor = type === 'success' ? '#155724' : '#721c24';
	var $notice = jQuery('<div class="wpss-inline-notice" style="padding:12px 16px;margin:10px 0;border:1px solid ' + borderColor + ';border-radius:4px;background:' + bgColor + ';color:' + textColor + ';position:relative;">' + msg + '<span style="position:absolute;right:10px;top:8px;cursor:pointer;font-size:18px;line-height:1;">&times;</span></div>');
	$notice.find('span').on('click', function() { $notice.fadeOut(200, function() { $notice.remove(); }); });
	jQuery('.wpss-messaging, .wpss-order-view, .wpss-dashboard').first().prepend($notice);
	setTimeout(function() { $notice.fadeOut(400, function() { $notice.remove(); }); }, 8000);
}
document.addEventListener('DOMContentLoaded', function() {
(function($) {
	'use strict';

	var $conversation = $('.wpss-messaging--order');
	var $messagesContainer = $('#wpss-messages-container');
	var $messageForm = $('#wpss-message-form');
	var $messageInput = $('#wpss-message-input');
	var $fileInput = $('#wpss-file-input');
	var $attachmentsPreview = $('#wpss-attachments-preview');
	var $sendBtn = $('#wpss-send-btn');
	var selectedFiles = [];

	// Scroll to bottom on load.
	function scrollToBottom() {
		$messagesContainer.scrollTop($messagesContainer[0].scrollHeight);
	}
	scrollToBottom();

	// Auto-resize textarea.
	$messageInput.on('input', function() {
		this.style.height = 'auto';
		this.style.height = Math.min(this.scrollHeight, 120) + 'px';
	});

	// Handle Enter key.
	$messageInput.on('keydown', function(e) {
		if (e.key === 'Enter' && !e.shiftKey) {
			e.preventDefault();
			$messageForm.submit();
		}
	});

	// File selection.
	$fileInput.on('change', function() {
		var files = Array.from(this.files);
		selectedFiles = selectedFiles.concat(files);
		updateAttachmentsPreview();
	});

	function updateAttachmentsPreview() {
		$attachmentsPreview.empty();
		selectedFiles.forEach(function(file, index) {
			var $preview = $('<div class="wpss-messaging__composer-attachment">')
				.append('<span class="wpss-messaging__composer-attachment-name">' + file.name + '</span>')
				.append('<button type="button" class="wpss-messaging__composer-attachment-remove" data-index="' + index + '"><i data-lucide="x" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i></button>');
			$attachmentsPreview.append($preview);
		});
	}

	// Remove attachment.
	$attachmentsPreview.on('click', '.wpss-messaging__composer-attachment-remove', function() {
		var index = $(this).data('index');
		selectedFiles.splice(index, 1);
		updateAttachmentsPreview();
	});

	// Handle send button click.
	$sendBtn.on('click', function(e) {
		e.preventDefault();
		submitMessage();
	});

	// Handle form submit (Enter key).
	// stopImmediatePropagation prevents the duplicate handler in frontend.js from also firing.
	$messageForm.on('submit', function(e) {
		e.preventDefault();
		e.stopImmediatePropagation();
		submitMessage();
	});

	// Submit message function.
	// Conversation id is known once a conversation exists (always, after the
	// first message). The order-scoped send endpoint creates it on first send
	// and echoes the id back so polling can begin.
	var conversationId = $conversation.data('conversation-id') || 0;
	var restBase = wpss.restUrl; // e.g. http://site/wp-json/wpss/v1/

	function hydrateIcons() {
		if (window.lucide && typeof window.lucide.createIcons === 'function') {
			window.lucide.createIcons();
		}
	}

	function submitMessage() {

		var message = $messageInput.val().trim();
		if (!message && selectedFiles.length === 0) {
			return;
		}

		// REST: POST /orders/{order_id}/conversation/messages (creates the
		// conversation on first send). Multipart for file attachments.
		var formData = new FormData();
		formData.append('content', message);
		selectedFiles.forEach(function(file) {
			formData.append('attachments[]', file);
		});

		$sendBtn.prop('disabled', true);

		$.ajax({
			url: restBase + 'orders/' + $conversation.data('order-id') + '/conversation/messages',
			method: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', wpss.restNonce);
			},
			success: function(response) {
				// Add message to container.
				$messagesContainer.find('.wpss-messaging__empty').remove();
				$messagesContainer.append(response.html);
				hydrateIcons();
				scrollToBottom();

				// Track ids so polling does not re-append this message.
				if (response.conversation_id) {
					conversationId = response.conversation_id;
				}
				if (response.data && response.data.id && response.data.id > lastMessageId) {
					lastMessageId = response.data.id;
				}

				// Clear form.
				$messageInput.val('').css('height', 'auto');
				selectedFiles = [];
				updateAttachmentsPreview();
				$fileInput.val('');
			},
			error: function(xhr) {
				var msg = (xhr.responseJSON && xhr.responseJSON.message)
					|| '<?php esc_html_e( 'Failed to send message.', 'wp-sell-services' ); ?>';
				wpssShowNotice(msg, 'error');
			},
			complete: function() {
				$sendBtn.prop('disabled', false);
				$messageInput.focus();
			}
		});
	}

	// Poll for new messages (simple polling, can be replaced with WebSockets).
	var lastMessageId = $messagesContainer.find('.wpss-messaging__message:last').data('message-id') || 0;

	function pollMessages() {
		if (!$conversation.length || $conversation.is(':hidden') || !conversationId) {
			return;
		}

		// REST: GET /conversations/{id}/messages?after_id=N - only new messages.
		$.ajax({
			url: restBase + 'conversations/' + conversationId + '/messages',
			method: 'GET',
			data: { after_id: lastMessageId, per_page: 50 },
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', wpss.restNonce);
			},
			success: function(messages) {
				if (!Array.isArray(messages) || !messages.length) {
					return;
				}
				messages.forEach(function(msg) {
					// Skip our own just-sent message (already appended) and
					// anything at/under the high-water mark.
					if (msg.id > lastMessageId) {
						$messagesContainer.append(msg.html);
						lastMessageId = msg.id;
					}
				});
				hydrateIcons();
				scrollToBottom();
			}
		});
	}

	// Poll every 10 seconds.
	if ($conversation.length) {
		setInterval(pollMessages, 10000);
	}

})(jQuery);
});
</script>
