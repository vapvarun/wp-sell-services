<?php
/**
 * Template: Order Conversation
 *
 * Displays the conversation/messaging interface for an order.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int    $order_id     Order ID.
 * @var object $order        Order object.
 * @var bool   $is_vendor    Whether current user is the vendor.
 * @var bool   $is_customer  Whether current user is the customer.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $order_id ) || empty( $order ) ) {
	return;
}

$user_id     = get_current_user_id();
$is_vendor   = $is_vendor ?? ( (int) $order->vendor_id === $user_id );
$is_customer = $is_customer ?? ( (int) $order->customer_id === $user_id );

// Get conversation participants.
$vendor   = get_userdata( $order->vendor_id );
$customer = get_userdata( $order->customer_id );
$other_user = $is_vendor ? $customer : $vendor;

// Get messages.
global $wpdb;
$messages = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT m.*, u.display_name as sender_name
		FROM {$wpdb->prefix}wpss_conversations m
		LEFT JOIN {$wpdb->users} u ON m.sender_id = u.ID
		WHERE m.order_id = %d
		ORDER BY m.created_at ASC",
		$order_id
	)
);

// Mark messages as read.
if ( ! empty( $messages ) ) {
	$unread_ids = [];
	foreach ( $messages as $message ) {
		if ( (int) $message->sender_id !== $user_id && empty( $message->read_at ) ) {
			$unread_ids[] = (int) $message->id;
		}
	}
	if ( ! empty( $unread_ids ) ) {
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}wpss_conversations SET read_at = %s WHERE id IN (" . implode( ',', array_fill( 0, count( $unread_ids ), '%d' ) ) . ')',
				array_merge( [ current_time( 'mysql', true ) ], $unread_ids )
			)
		);
	}
}

// Can send messages?
$can_message = in_array( $order->status, [ 'pending_requirements', 'in_progress', 'revision_requested', 'delivered' ], true );
?>

<div class="wpss-conversation" data-order-id="<?php echo esc_attr( $order_id ); ?>">
	<!-- Conversation Header -->
	<div class="wpss-conversation-header">
		<div class="wpss-conversation-participant">
			<?php echo get_avatar( $other_user->ID, 48 ); ?>
			<div class="wpss-participant-info">
				<span class="wpss-participant-name"><?php echo esc_html( $other_user->display_name ); ?></span>
				<span class="wpss-participant-role">
					<?php echo $is_vendor ? esc_html__( 'Buyer', 'wp-sell-services' ) : esc_html__( 'Seller', 'wp-sell-services' ); ?>
				</span>
			</div>
		</div>
		<div class="wpss-conversation-meta">
			<span class="wpss-order-status wpss-status-<?php echo esc_attr( $order->status ); ?>">
				<?php echo esc_html( wpss_get_order_status_label( $order->status ) ); ?>
			</span>
		</div>
	</div>

	<!-- Messages Container -->
	<div class="wpss-conversation-messages" id="wpss-messages-container">
		<?php if ( empty( $messages ) ) : ?>
			<div class="wpss-no-messages">
				<p><?php esc_html_e( 'No messages yet. Start the conversation!', 'wp-sell-services' ); ?></p>
			</div>
		<?php else : ?>
			<?php
			$current_date = '';
			foreach ( $messages as $message ) :
				$message_date = wp_date( get_option( 'date_format' ), strtotime( $message->created_at ) );
				$is_own       = (int) $message->sender_id === $user_id;
				$is_system    = $message->type === 'system';

				// Date separator.
				if ( $message_date !== $current_date ) :
					$current_date = $message_date;
					?>
					<div class="wpss-message-date-separator">
						<span><?php echo esc_html( $message_date ); ?></span>
					</div>
				<?php endif; ?>

				<?php if ( $is_system ) : ?>
					<div class="wpss-message wpss-message-system">
						<div class="wpss-message-content">
							<?php echo wp_kses_post( $message->message ); ?>
						</div>
						<span class="wpss-message-time">
							<?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( $message->created_at ) ) ); ?>
						</span>
					</div>
				<?php else : ?>
					<div class="wpss-message <?php echo $is_own ? 'wpss-message-own' : 'wpss-message-other'; ?>">
						<?php if ( ! $is_own ) : ?>
							<div class="wpss-message-avatar">
								<?php echo get_avatar( $message->sender_id, 32 ); ?>
							</div>
						<?php endif; ?>
						<div class="wpss-message-bubble">
							<?php if ( ! $is_own ) : ?>
								<span class="wpss-message-sender"><?php echo esc_html( $message->sender_name ); ?></span>
							<?php endif; ?>
							<div class="wpss-message-content">
								<?php echo wp_kses_post( nl2br( $message->message ) ); ?>
							</div>
							<?php if ( ! empty( $message->attachments ) ) : ?>
								<?php $attachments = json_decode( $message->attachments, true ); ?>
								<?php if ( ! empty( $attachments ) ) : ?>
									<div class="wpss-message-attachments">
										<?php foreach ( $attachments as $attachment ) : ?>
											<div class="wpss-attachment">
												<?php
												$file_url  = $attachment['url'] ?? '';
												$file_name = $attachment['name'] ?? basename( $file_url );
												$file_type = $attachment['type'] ?? '';
												$is_image  = strpos( $file_type, 'image/' ) === 0;
												?>
												<?php if ( $is_image && $file_url ) : ?>
													<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-attachment-image">
														<img src="<?php echo esc_url( $file_url ); ?>" alt="<?php echo esc_attr( $file_name ); ?>">
													</a>
												<?php else : ?>
													<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" class="wpss-attachment-file">
														<span class="wpss-attachment-icon dashicons dashicons-media-default"></span>
														<span class="wpss-attachment-name"><?php echo esc_html( $file_name ); ?></span>
													</a>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							<?php endif; ?>
							<span class="wpss-message-time">
								<?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( $message->created_at ) ) ); ?>
								<?php if ( $is_own && ! empty( $message->read_at ) ) : ?>
									<span class="wpss-message-read" title="<?php esc_attr_e( 'Read', 'wp-sell-services' ); ?>">
										<span class="dashicons dashicons-yes-alt"></span>
									</span>
								<?php endif; ?>
							</span>
						</div>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<!-- Message Input -->
	<?php if ( $can_message ) : ?>
		<div class="wpss-conversation-input">
			<form id="wpss-message-form" class="wpss-message-form" method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'wpss_send_message', 'wpss_message_nonce' ); ?>
				<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

				<div class="wpss-message-input-wrapper">
					<div class="wpss-message-attachments-preview" id="wpss-attachments-preview"></div>

					<div class="wpss-message-input-row">
						<label for="wpss-file-input" class="wpss-attach-btn" title="<?php esc_attr_e( 'Attach files', 'wp-sell-services' ); ?>">
							<span class="dashicons dashicons-paperclip"></span>
							<input type="file" id="wpss-file-input" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display: none;">
						</label>

						<textarea name="message"
								  id="wpss-message-input"
								  class="wpss-message-textarea"
								  placeholder="<?php esc_attr_e( 'Type your message...', 'wp-sell-services' ); ?>"
								  rows="1"
								  maxlength="5000"
								  required></textarea>

						<button type="submit" class="wpss-send-btn" id="wpss-send-btn">
							<span class="dashicons dashicons-arrow-right-alt"></span>
							<span class="wpss-send-text"><?php esc_html_e( 'Send', 'wp-sell-services' ); ?></span>
						</button>
					</div>
				</div>

				<div class="wpss-message-hint">
					<?php esc_html_e( 'Press Enter to send, Shift+Enter for new line', 'wp-sell-services' ); ?>
				</div>
			</form>
		</div>
	<?php else : ?>
		<div class="wpss-conversation-closed">
			<p>
				<?php
				if ( $order->status === 'completed' ) {
					esc_html_e( 'This order has been completed. You can no longer send messages.', 'wp-sell-services' );
				} elseif ( $order->status === 'cancelled' ) {
					esc_html_e( 'This order has been cancelled.', 'wp-sell-services' );
				} elseif ( $order->status === 'refunded' ) {
					esc_html_e( 'This order has been refunded.', 'wp-sell-services' );
				} else {
					esc_html_e( 'Messaging is not available for this order status.', 'wp-sell-services' );
				}
				?>
			</p>
		</div>
	<?php endif; ?>
</div>

<style>
.wpss-conversation {
	display: flex;
	flex-direction: column;
	height: 600px;
	max-height: 80vh;
	border: 1px solid var(--wpss-border-color, #dcdcde);
	border-radius: var(--wpss-border-radius, 8px);
	background: var(--wpss-card-bg, #fff);
	overflow: hidden;
}

.wpss-conversation-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 15px 20px;
	border-bottom: 1px solid var(--wpss-border-color, #dcdcde);
	background: var(--wpss-bg-light, #f6f7f7);
}

.wpss-conversation-participant {
	display: flex;
	align-items: center;
	gap: 12px;
}

.wpss-conversation-participant img {
	width: 48px;
	height: 48px;
	border-radius: 50%;
}

.wpss-participant-info {
	display: flex;
	flex-direction: column;
}

.wpss-participant-name {
	font-weight: 600;
	font-size: 16px;
	color: var(--wpss-text-primary, #1d2327);
}

.wpss-participant-role {
	font-size: 13px;
	color: var(--wpss-text-secondary, #646970);
}

.wpss-conversation-messages {
	flex: 1;
	overflow-y: auto;
	padding: 20px;
	display: flex;
	flex-direction: column;
	gap: 12px;
	background: var(--wpss-bg-light, #f6f7f7);
}

.wpss-no-messages {
	flex: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	text-align: center;
	color: var(--wpss-text-muted, #8c8f94);
}

.wpss-message-date-separator {
	text-align: center;
	margin: 10px 0;
}

.wpss-message-date-separator span {
	background: var(--wpss-bg-light, #f6f7f7);
	padding: 4px 12px;
	font-size: 12px;
	color: var(--wpss-text-secondary, #646970);
	border-radius: 12px;
	background: var(--wpss-border-color, #dcdcde);
}

.wpss-message {
	display: flex;
	gap: 10px;
	max-width: 80%;
}

.wpss-message-own {
	align-self: flex-end;
	flex-direction: row-reverse;
}

.wpss-message-other {
	align-self: flex-start;
}

.wpss-message-system {
	align-self: center;
	max-width: 90%;
	text-align: center;
}

.wpss-message-system .wpss-message-content {
	background: var(--wpss-bg-muted, #e8e8e8);
	padding: 8px 15px;
	border-radius: 15px;
	font-size: 13px;
	color: var(--wpss-text-secondary, #646970);
}

.wpss-message-avatar img {
	width: 32px;
	height: 32px;
	border-radius: 50%;
}

.wpss-message-bubble {
	background: var(--wpss-card-bg, #fff);
	padding: 10px 15px;
	border-radius: 18px;
	box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}

.wpss-message-own .wpss-message-bubble {
	background: var(--wpss-primary-color, #2271b1);
	color: #fff;
}

.wpss-message-sender {
	display: block;
	font-size: 12px;
	font-weight: 600;
	margin-bottom: 4px;
	color: var(--wpss-primary-color, #2271b1);
}

.wpss-message-content {
	word-wrap: break-word;
	line-height: 1.5;
}

.wpss-message-time {
	display: flex;
	align-items: center;
	gap: 4px;
	font-size: 11px;
	color: var(--wpss-text-muted, #8c8f94);
	margin-top: 4px;
}

.wpss-message-own .wpss-message-time {
	color: rgba(255, 255, 255, 0.7);
	justify-content: flex-end;
}

.wpss-message-read .dashicons {
	font-size: 14px;
	width: 14px;
	height: 14px;
}

.wpss-message-attachments {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	margin-top: 10px;
}

.wpss-attachment-image img {
	max-width: 200px;
	max-height: 150px;
	border-radius: 8px;
}

.wpss-attachment-file {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 12px;
	background: rgba(0, 0, 0, 0.05);
	border-radius: 8px;
	text-decoration: none;
	color: inherit;
}

.wpss-message-own .wpss-attachment-file {
	background: rgba(255, 255, 255, 0.2);
}

.wpss-conversation-input {
	padding: 15px 20px;
	border-top: 1px solid var(--wpss-border-color, #dcdcde);
	background: var(--wpss-card-bg, #fff);
}

.wpss-message-input-wrapper {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.wpss-message-input-row {
	display: flex;
	align-items: flex-end;
	gap: 10px;
}

.wpss-attach-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	border-radius: 50%;
	background: var(--wpss-bg-light, #f6f7f7);
	color: var(--wpss-text-secondary, #646970);
	cursor: pointer;
	transition: all 0.2s;
}

.wpss-attach-btn:hover {
	background: var(--wpss-border-color, #dcdcde);
	color: var(--wpss-text-primary, #1d2327);
}

.wpss-message-textarea {
	flex: 1;
	padding: 10px 15px;
	border: 1px solid var(--wpss-border-color, #dcdcde);
	border-radius: 20px;
	resize: none;
	font-size: 14px;
	line-height: 1.5;
	max-height: 120px;
	overflow-y: auto;
}

.wpss-message-textarea:focus {
	outline: none;
	border-color: var(--wpss-primary-color, #2271b1);
}

.wpss-send-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 5px;
	padding: 10px 20px;
	background: var(--wpss-primary-color, #2271b1);
	color: #fff;
	border: none;
	border-radius: 20px;
	cursor: pointer;
	font-weight: 500;
	transition: background 0.2s;
}

.wpss-send-btn:hover {
	background: var(--wpss-primary-dark, #135e96);
}

.wpss-send-btn:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}

.wpss-send-btn .dashicons {
	font-size: 18px;
	width: 18px;
	height: 18px;
}

.wpss-message-hint {
	font-size: 11px;
	color: var(--wpss-text-muted, #8c8f94);
	margin-top: 5px;
	text-align: center;
}

.wpss-attachments-preview {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.wpss-attachment-preview {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 10px;
	background: var(--wpss-bg-light, #f6f7f7);
	border-radius: 6px;
	font-size: 12px;
}

.wpss-attachment-preview .wpss-remove-attachment {
	cursor: pointer;
	color: var(--wpss-danger-color, #d63638);
}

.wpss-conversation-closed {
	padding: 20px;
	text-align: center;
	background: var(--wpss-bg-light, #f6f7f7);
	color: var(--wpss-text-secondary, #646970);
}

@media (max-width: 600px) {
	.wpss-conversation {
		height: 500px;
	}

	.wpss-message {
		max-width: 90%;
	}

	.wpss-send-btn .wpss-send-text {
		display: none;
	}

	.wpss-send-btn {
		padding: 10px;
		border-radius: 50%;
	}
}
</style>

<script>
(function($) {
	'use strict';

	var $conversation = $('.wpss-conversation');
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
			var $preview = $('<div class="wpss-attachment-preview">')
				.append('<span class="wpss-attachment-name">' + file.name + '</span>')
				.append('<span class="wpss-remove-attachment dashicons dashicons-no-alt" data-index="' + index + '"></span>');
			$attachmentsPreview.append($preview);
		});
	}

	// Remove attachment.
	$attachmentsPreview.on('click', '.wpss-remove-attachment', function() {
		var index = $(this).data('index');
		selectedFiles.splice(index, 1);
		updateAttachmentsPreview();
	});

	// Submit message.
	$messageForm.on('submit', function(e) {
		e.preventDefault();

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
			url: wpss_ajax.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					// Add message to container.
					$messagesContainer.find('.wpss-no-messages').remove();
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
	});

	// Poll for new messages (simple polling, can be replaced with WebSockets).
	var lastMessageId = $messagesContainer.find('.wpss-message:last').data('message-id') || 0;

	function pollMessages() {
		if (!$conversation.length || $conversation.is(':hidden')) {
			return;
		}

		$.ajax({
			url: wpss_ajax.ajax_url,
			type: 'POST',
			data: {
				action: 'wpss_get_new_messages',
				nonce: wpss_ajax.nonce,
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
