/**
 * WP Sell Services - Conversation/Messaging JavaScript
 *
 * Real-time messaging functionality for order conversations.
 *
 * @package WPSellServices
 * @since   1.0.0
 */

(function($) {
    'use strict';

    const WPSSConversation = {
        /**
         * Configuration.
         */
        config: {
            pollInterval: 10000, // 10 seconds
            maxRetries: 3,
            container: '#wpss-conversation',
            messagesContainer: '#wpss-messages-list',
            form: '#wpss-message-form',
            typingIndicator: '.wpss-typing-indicator'
        },

        /**
         * State.
         */
        state: {
            orderId: null,
            conversationId: null,
            lastMessageId: 0,
            polling: false,
            pollTimer: null,
            retryCount: 0,
            isTyping: false,
            typingTimer: null
        },

        /**
         * Initialize conversation.
         */
        init: function() {
            const $container = $(this.config.container);

            if (!$container.length) {
                return;
            }

            this.state.orderId = $container.data('order-id');
            this.state.conversationId = $container.data('conversation-id');

            if (!this.state.conversationId) {
                return;
            }

            this.bindEvents();
            this.scrollToBottom();
            this.getLastMessageId();
            this.startPolling();
        },

        /**
         * Bind event handlers.
         */
        bindEvents: function() {
            const self = this;

            // Message form submission.
            $(document).on('submit', this.config.form, function(e) {
                e.preventDefault();
                self.sendMessage($(this));
            });

            // Typing detection.
            $(document).on('input', this.config.form + ' textarea', function() {
                self.handleTyping();
            });

            // File attachment.
            $(document).on('change', this.config.form + ' input[type="file"]', function() {
                self.handleFileSelect($(this));
            });

            // Remove attachment.
            $(document).on('click', '.wpss-attachment-remove', function() {
                self.removeAttachment($(this));
            });

            // Scroll detection for loading older messages.
            $(this.config.messagesContainer).on('scroll', function() {
                self.handleScroll($(this));
            });

            // Page visibility change.
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    self.stopPolling();
                } else {
                    self.startPolling();
                }
            });

            // Before unload - stop polling.
            $(window).on('beforeunload', function() {
                self.stopPolling();
            });
        },

        /**
         * Send message.
         */
        sendMessage: function($form) {
            const self = this;
            const $textarea = $form.find('textarea');
            const $btn = $form.find('button[type="submit"]');
            const message = $textarea.val().trim();
            const $fileInput = $form.find('input[type="file"]');

            if (!message && !$fileInput[0].files.length) {
                return;
            }

            // Disable form.
            $btn.prop('disabled', true).addClass('loading');
            $textarea.prop('disabled', true);

            // Prepare form data.
            const formData = new FormData();
            formData.append('action', 'wpss_send_message');
            formData.append('conversation_id', this.state.conversationId);
            formData.append('message', message);
            formData.append('nonce', wpssData.nonce);

            // Add files.
            const files = $fileInput[0].files;
            for (let i = 0; i < files.length; i++) {
                formData.append('attachments[]', files[i]);
            }

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Add message to UI.
                        self.appendMessage(response.data.message, true);

                        // Clear form.
                        $textarea.val('');
                        $fileInput.val('');
                        self.clearAttachmentPreview();

                        // Update last message ID.
                        self.state.lastMessageId = response.data.message.id;

                        // Scroll to bottom.
                        self.scrollToBottom();
                    } else {
                        self.showError(response.data.message || 'Failed to send message');
                    }
                },
                error: function() {
                    self.showError('Network error. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading');
                    $textarea.prop('disabled', false).focus();
                }
            });
        },

        /**
         * Append message to container.
         */
        appendMessage: function(message, isOwn) {
            const $container = $(this.config.messagesContainer);
            const html = this.renderMessage(message, isOwn);

            // Remove "no messages" placeholder.
            $container.find('.wpss-no-messages').remove();

            // Remove typing indicator before adding message.
            $(this.config.typingIndicator).remove();

            $container.append(html);
        },

        /**
         * Render message HTML.
         */
        renderMessage: function(message, isOwn) {
            const className = isOwn ? 'wpss-message-own' : 'wpss-message-other';
            let attachmentsHtml = '';

            if (message.attachments && message.attachments.length) {
                attachmentsHtml = '<div class="wpss-message-attachments">';
                message.attachments.forEach(function(attachment) {
                    if (attachment.is_image) {
                        attachmentsHtml += `
                            <a href="${attachment.url}" target="_blank" class="wpss-attachment wpss-attachment-image">
                                <img src="${attachment.thumbnail || attachment.url}" alt="${attachment.name}">
                            </a>
                        `;
                    } else {
                        attachmentsHtml += `
                            <a href="${attachment.url}" target="_blank" class="wpss-attachment wpss-attachment-file">
                                <span class="dashicons dashicons-media-default"></span>
                                <span class="name">${this.escapeHtml(attachment.name)}</span>
                            </a>
                        `;
                    }
                }.bind(this));
                attachmentsHtml += '</div>';
            }

            return `
                <div class="wpss-message ${className}" data-id="${message.id}">
                    <div class="wpss-message-avatar">
                        <img src="${message.sender_avatar}" alt="${this.escapeHtml(message.sender_name)}">
                    </div>
                    <div class="wpss-message-body">
                        <div class="wpss-message-header">
                            <span class="wpss-message-sender">${this.escapeHtml(message.sender_name)}</span>
                            <span class="wpss-message-time">${message.time_ago || 'Just now'}</span>
                        </div>
                        <div class="wpss-message-content">
                            ${message.content ? '<p>' + this.escapeHtml(message.content) + '</p>' : ''}
                            ${attachmentsHtml}
                        </div>
                    </div>
                </div>
            `;
        },

        /**
         * Handle typing indicator.
         */
        handleTyping: function() {
            const self = this;

            // Clear existing timer.
            if (this.state.typingTimer) {
                clearTimeout(this.state.typingTimer);
            }

            // Send typing indicator if not already typing.
            if (!this.state.isTyping) {
                this.state.isTyping = true;
                this.sendTypingIndicator(true);
            }

            // Set timer to stop typing.
            this.state.typingTimer = setTimeout(function() {
                self.state.isTyping = false;
                self.sendTypingIndicator(false);
            }, 2000);
        },

        /**
         * Send typing indicator.
         */
        sendTypingIndicator: function(isTyping) {
            // Optional: Send to server for real-time updates.
            // This would require WebSocket or similar for real-time.
        },

        /**
         * Show typing indicator.
         */
        showTypingIndicator: function(senderName) {
            const $container = $(this.config.messagesContainer);

            // Remove existing indicator.
            $(this.config.typingIndicator).remove();

            const html = `
                <div class="wpss-typing-indicator">
                    <span class="name">${this.escapeHtml(senderName)}</span>
                    <span class="dots">
                        <span></span><span></span><span></span>
                    </span>
                </div>
            `;

            $container.append(html);
            this.scrollToBottom();
        },

        /**
         * Handle file selection.
         */
        handleFileSelect: function($input) {
            const files = $input[0].files;
            const $preview = $input.siblings('.wpss-attachment-preview');

            $preview.empty();

            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                const isImage = file.type.startsWith('image/');

                let previewHtml;
                if (isImage) {
                    previewHtml = `
                        <div class="wpss-attachment-item" data-index="${i}">
                            <img src="${URL.createObjectURL(file)}" alt="${file.name}">
                            <button type="button" class="wpss-attachment-remove">&times;</button>
                        </div>
                    `;
                } else {
                    previewHtml = `
                        <div class="wpss-attachment-item wpss-attachment-file" data-index="${i}">
                            <span class="dashicons dashicons-media-default"></span>
                            <span class="name">${file.name}</span>
                            <button type="button" class="wpss-attachment-remove">&times;</button>
                        </div>
                    `;
                }

                $preview.append(previewHtml);
            }

            $preview.toggle(files.length > 0);
        },

        /**
         * Remove attachment.
         */
        removeAttachment: function($btn) {
            const $item = $btn.closest('.wpss-attachment-item');
            const $preview = $item.parent();

            $item.remove();

            // Note: Can't modify FileList, so we'd need DataTransfer for full implementation.
            if (!$preview.children().length) {
                $preview.hide();
                $(this.config.form).find('input[type="file"]').val('');
            }
        },

        /**
         * Clear attachment preview.
         */
        clearAttachmentPreview: function() {
            $(this.config.form).find('.wpss-attachment-preview').empty().hide();
        },

        /**
         * Start polling for new messages.
         */
        startPolling: function() {
            if (this.state.polling) {
                return;
            }

            this.state.polling = true;
            this.poll();
        },

        /**
         * Stop polling.
         */
        stopPolling: function() {
            this.state.polling = false;
            if (this.state.pollTimer) {
                clearTimeout(this.state.pollTimer);
                this.state.pollTimer = null;
            }
        },

        /**
         * Poll for new messages.
         */
        poll: function() {
            const self = this;

            if (!this.state.polling) {
                return;
            }

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_get_messages',
                    conversation_id: this.state.conversationId,
                    after_id: this.state.lastMessageId,
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    self.state.retryCount = 0;

                    if (response.success && response.data.messages) {
                        response.data.messages.forEach(function(message) {
                            // Don't add own messages (already added on send).
                            if (message.sender_id !== wpssData.currentUserId) {
                                self.appendMessage(message, false);
                            }
                            self.state.lastMessageId = Math.max(self.state.lastMessageId, message.id);
                        });

                        if (response.data.messages.length > 0) {
                            self.scrollToBottom();
                            self.playNotificationSound();
                        }
                    }
                },
                error: function() {
                    self.state.retryCount++;
                    if (self.state.retryCount >= self.config.maxRetries) {
                        self.stopPolling();
                        self.showError('Connection lost. Please refresh the page.');
                    }
                },
                complete: function() {
                    if (self.state.polling) {
                        self.state.pollTimer = setTimeout(function() {
                            self.poll();
                        }, self.config.pollInterval);
                    }
                }
            });
        },

        /**
         * Get last message ID.
         */
        getLastMessageId: function() {
            const $lastMessage = $(this.config.messagesContainer).find('.wpss-message').last();
            if ($lastMessage.length) {
                this.state.lastMessageId = parseInt($lastMessage.data('id')) || 0;
            }
        },

        /**
         * Handle scroll for loading older messages.
         */
        handleScroll: function($container) {
            // Load older messages when scrolled to top.
            if ($container.scrollTop() === 0) {
                this.loadOlderMessages();
            }
        },

        /**
         * Load older messages.
         */
        loadOlderMessages: function() {
            const self = this;
            const $container = $(this.config.messagesContainer);
            const $firstMessage = $container.find('.wpss-message').first();

            if (!$firstMessage.length) {
                return;
            }

            const beforeId = $firstMessage.data('id');

            $.ajax({
                url: wpssData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wpss_get_messages',
                    conversation_id: this.state.conversationId,
                    before_id: beforeId,
                    limit: 20,
                    nonce: wpssData.nonce
                },
                success: function(response) {
                    if (response.success && response.data.messages && response.data.messages.length) {
                        const scrollHeight = $container[0].scrollHeight;

                        response.data.messages.reverse().forEach(function(message) {
                            const html = self.renderMessage(message, message.sender_id === wpssData.currentUserId);
                            $container.prepend(html);
                        });

                        // Maintain scroll position.
                        $container.scrollTop($container[0].scrollHeight - scrollHeight);
                    }
                }
            });
        },

        /**
         * Scroll to bottom of messages.
         */
        scrollToBottom: function() {
            const $container = $(this.config.messagesContainer);
            $container.scrollTop($container[0].scrollHeight);
        },

        /**
         * Play notification sound.
         */
        playNotificationSound: function() {
            if (typeof wpssData.notificationSound !== 'undefined' && wpssData.notificationSound) {
                const audio = new Audio(wpssData.notificationSound);
                audio.volume = 0.5;
                audio.play().catch(function() {
                    // Ignore autoplay restrictions.
                });
            }
        },

        /**
         * Show error message.
         */
        showError: function(message) {
            const $error = $('<div class="wpss-conversation-error">' + message + '</div>');
            $(this.config.container).prepend($error);

            setTimeout(function() {
                $error.fadeOut(function() {
                    $error.remove();
                });
            }, 5000);
        },

        /**
         * Escape HTML.
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Initialize on document ready.
    $(document).ready(function() {
        WPSSConversation.init();
    });

    // Expose globally.
    window.WPSSConversation = WPSSConversation;

})(jQuery);
