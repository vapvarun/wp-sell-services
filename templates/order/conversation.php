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

$user_id     = get_current_user_id();
$is_vendor   = $is_vendor ?? ( (int) $order->vendor_id === $user_id );
$is_customer = $is_customer ?? ( (int) $order->customer_id === $user_id );

// Get conversation participants.
$vendor     = get_userdata( $order->vendor_id );
$customer   = get_userdata( $order->customer_id );
$other_user = $is_vendor ? $customer : $vendor;

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
				$msg_row = $wpdb->get_row( $wpdb->prepare( "SELECT read_by FROM {$wpdb->prefix}wpss_messages WHERE id = %d", $msg_id ) );
				$read_by = $msg_row && $msg_row->read_by ? json_decode( $msg_row->read_by, true ) : array();
				$read_by[ $user_id ] = current_time( 'mysql', true );
				$wpdb->update(
					$wpdb->prefix . 'wpss_messages',
					array( 'read_by' => wp_json_encode( $read_by ) ),
					array( 'id' => $msg_id ),
					array( '%s' ),
					array( '%d' )
				);
			}
		}
	}
}

// Can send messages?
$can_message = in_array( $order->status, array( 'pending_requirements', 'in_progress', 'revision_requested', 'delivered' ), true );

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

<div class="wpss-messaging wpss-messaging--order" data-order-id="<?php echo esc_attr( $order_id ); ?>">
	<!-- Conversation Header -->
	<div class="wpss-messaging__header">
		<div class="wpss-messaging__header-info">
			<div class="wpss-messaging__header-avatar">
				<?php echo get_avatar( $other_user->ID, 40 ); ?>
			</div>
			<div class="wpss-messaging__header-details">
				<span class="wpss-messaging__header-name"><?php echo esc_html( $other_user->display_name ); ?></span>
				<span class="wpss-messaging__header-status">
					<?php echo $is_vendor ? esc_html__( 'Buyer', 'wp-sell-services' ) : esc_html__( 'Seller', 'wp-sell-services' ); ?>
				</span>
			</div>
		</div>
		<div class="wpss-messaging__header-actions">
			<span class="wpss-badge wpss-badge--status-<?php echo esc_attr( str_replace( '_', '-', $order->status ) ); ?>">
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
					<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
					</svg>
				</div>
				<h3 class="wpss-messaging__empty-title"><?php esc_html_e( 'No messages yet', 'wp-sell-services' ); ?></h3>
				<p class="wpss-messaging__empty-text"><?php esc_html_e( 'Start the conversation by sending a message!', 'wp-sell-services' ); ?></p>
			</div>
		<?php else : ?>
			<?php
			$current_date = '';
			foreach ( $messages as $message ) :
				$message_date = wp_date( get_option( 'date_format' ), strtotime( $message->created_at ) );
				$is_own       = (int) $message->sender_id === $user_id;
				$is_system    = 'system' === ( isset( $message->type ) ? $message->type : ( isset( $message->content_type ) ? $message->content_type : 'text' ) );

				// Date separator.
				if ( $message_date !== $current_date ) :
					$current_date = $message_date;
					?>
					<div class="wpss-messaging__date-divider">
						<span class="wpss-messaging__date-text"><?php echo esc_html( $message_date ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( $is_system ) : ?>
					<div class="wpss-messaging__system">
						<span class="wpss-messaging__system-text">
							<?php echo wp_kses_post( $message->content ); ?>
							<span class="wpss-messaging__message-time">
								<?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( $message->created_at ) ) ); ?>
							</span>
						</span>
					</div>
				<?php else : ?>
					<div class="wpss-messaging__message <?php echo $is_own ? 'wpss-messaging__message--sent' : ''; ?>" data-message-id="<?php echo esc_attr( $message->id ); ?>">
						<?php if ( ! $is_own ) : ?>
							<div class="wpss-messaging__message-avatar">
								<?php echo get_avatar( $message->sender_id, 32 ); ?>
							</div>
						<?php endif; ?>
						<div class="wpss-messaging__message-content">
							<div class="wpss-messaging__bubble">
								<?php if ( ! $is_own ) : ?>
									<span class="wpss-messaging__sender"><?php echo esc_html( $message->sender_name ); ?></span>
								<?php endif; ?>
								<div class="wpss-messaging__text">
									<?php echo wp_kses_post( nl2br( $message->content ) ); ?>
								</div>
								<?php if ( ! empty( $message->attachments ) ) : ?>
									<?php $attachments = json_decode( $message->attachments, true ); ?>
									<?php if ( ! empty( $attachments ) ) : ?>
										<div class="wpss-messaging__attachments">
											<?php foreach ( $attachments as $attachment ) : ?>
												<?php
												$file_url  = $attachment['url'] ?? '';
												$file_name = $attachment['name'] ?? basename( $file_url );
												$file_type = $attachment['type'] ?? '';
												$is_image  = strpos( $file_type, 'image/' ) === 0;
												?>
												<?php if ( $is_image && $file_url ) : ?>
													<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-messaging__attachment-image">
														<img src="<?php echo esc_url( $file_url ); ?>" alt="<?php echo esc_attr( $file_name ); ?>">
													</a>
												<?php else : ?>
													<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-messaging__attachment-file">
														<span class="wpss-messaging__attachment-icon">
															<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
																<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
																<polyline points="14 2 14 8 20 8"/>
															</svg>
														</span>
														<span class="wpss-messaging__attachment-info">
															<span class="wpss-messaging__attachment-name"><?php echo esc_html( $file_name ); ?></span>
														</span>
													</a>
												<?php endif; ?>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								<?php endif; ?>
							</div>
							<span class="wpss-messaging__message-time">
								<?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( $message->created_at ) ) ); ?>
								<?php
							$read_by_data = $message->read_by ? json_decode( $message->read_by, true ) : array();
							$is_read      = ! empty( array_diff_key( $read_by_data, array( $user_id => '' ) ) );
							?>
							<?php if ( $is_own && $is_read ) : ?>
									<span class="wpss-messaging__message-status wpss-messaging__message-status--read" title="<?php esc_attr_e( 'Read', 'wp-sell-services' ); ?>">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<polyline points="20 6 9 17 4 12"/>
										</svg>
									</span>
								<?php endif; ?>
							</span>
						</div>
					</div>
					<?php
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
					?>
				<?php endif; ?>
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
			<form id="wpss-message-form" method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wpss_send_message', 'wpss_message_nonce' ); ?>
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

				<div class="wpss-messaging__composer-attachments" id="wpss-attachments-preview"></div>

				<div class="wpss-messaging__composer-input-area">
					<label for="wpss-file-input" class="wpss-messaging__composer-btn" title="<?php esc_attr_e( 'Attach files', 'wp-sell-services' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
						</svg>
						<input type="file" id="wpss-file-input" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display: none;">
					</label>

					<div class="wpss-messaging__composer-input-wrapper">
						<textarea name="message"
									id="wpss-message-input"
									class="wpss-messaging__composer-input"
									placeholder="<?php esc_attr_e( 'Type your message...', 'wp-sell-services' ); ?>"
									rows="1"
									maxlength="5000"
									required></textarea>
					</div>

					<button type="button" class="wpss-messaging__send-btn" id="wpss-send-btn" title="<?php esc_attr_e( 'Send message', 'wp-sell-services' ); ?>">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<line x1="22" y1="2" x2="11" y2="13"/>
							<polygon points="22 2 15 22 11 13 2 9 22 2"/>
						</svg>
					</button>
				</div>

				<div class="wpss-messaging__hint">
					<?php esc_html_e( 'Press Enter to send, Shift+Enter for new line', 'wp-sell-services' ); ?>
				</div>
			</form>
		</div>
	<?php else : ?>
		<div class="wpss-messaging__closed">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
				<path d="M7 11V7a5 5 0 0 1 10 0v4"/>
			</svg>
			<p>
				<?php
				if ( 'completed' === $order->status ) {
					esc_html_e( 'This order has been completed. You can no longer send messages.', 'wp-sell-services' );
				} elseif ( 'cancelled' === $order->status ) {
					esc_html_e( 'This order has been cancelled.', 'wp-sell-services' );
				} elseif ( 'refunded' === $order->status ) {
					esc_html_e( 'This order has been refunded.', 'wp-sell-services' );
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
				.append('<button type="button" class="wpss-messaging__composer-attachment-remove" data-index="' + index + '"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>');
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
	$messageForm.on('submit', function(e) {
		e.preventDefault();
		submitMessage();
	});

	// Submit message function.
	function submitMessage() {

		var message = $messageInput.val().trim();
		if (!message && selectedFiles.length === 0) {
			return;
		}

		var formData = new FormData();
		formData.append('action', 'wpss_send_message');
		formData.append('nonce', $('#wpss_message_nonce').val());
		formData.append('order_id', $conversation.data('order-id'));
		formData.append('message', message);

		selectedFiles.forEach(function(file) {
			formData.append('attachments[]', file);
		});

		$sendBtn.prop('disabled', true);

		$.ajax({
			url: wpss.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					// Add message to container.
					$messagesContainer.find('.wpss-messaging__empty').remove();
					$messagesContainer.append(response.data.html);
					scrollToBottom();

					// Clear form.
					$messageInput.val('').css('height', 'auto');
					selectedFiles = [];
					updateAttachmentsPreview();
					$fileInput.val('');
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Failed to send message.', 'wp-sell-services' ); ?>');
				}
			},
			error: function() {
				alert('<?php esc_html_e( 'An error occurred. Please try again.', 'wp-sell-services' ); ?>');
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
		if (!$conversation.length || $conversation.is(':hidden')) {
			return;
		}

		$.ajax({
			url: wpss.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpss_get_new_messages',
				nonce: wpss.nonce,
				order_id: $conversation.data('order-id'),
				last_id: lastMessageId
			},
			success: function(response) {
				if (response.success && response.data.messages && response.data.messages.length > 0) {
					response.data.messages.forEach(function(msg) {
						if (msg.id > lastMessageId) {
							$messagesContainer.append(msg.html);
							lastMessageId = msg.id;
						}
					});
					scrollToBottom();
				}
			}
		});
	}

	// Poll every 10 seconds.
	if ($conversation.length) {
		setInterval(pollMessages, 10000);
	}

})(jQuery);
</script>
