/**
 * Admin Order Management JavaScript
 *
 * Handles order status changes, quick actions, and admin notes.
 *
 * @package WPSellServices
 * @since 1.0.0
 */

/* global jQuery, wpssOrderAdmin */

(function($) {
	'use strict';

	/**
	 * Show admin toast notification.
	 */
	function notify(message, type) {
		type = type || 'info';
		var $container = $('#wpss-admin-notification-container');
		if (!$container.length) {
			$container = $('<div id="wpss-admin-notification-container" style="position:fixed;top:40px;right:20px;z-index:160000;"></div>');
			$('body').append($container);
		}
		var bgColors = { success: '#00a32a', error: '#d63638', warning: '#dba617', info: '#2271b1' };
		var $toast = $('<div style="background:' + (bgColors[type] || bgColors.info) + ';color:#fff;padding:12px 20px;border-radius:6px;margin-bottom:8px;font-size:13px;box-shadow:0 2px 8px rgba(0,0,0,.15);max-width:360px;opacity:0;transition:opacity .3s;">' + $('<span>').text(message).html() + '</div>');
		$container.append($toast);
		setTimeout(function() { $toast.css('opacity', '1'); }, 10);
		setTimeout(function() { $toast.css('opacity', '0'); setTimeout(function() { $toast.remove(); }, 300); }, 4000);
	}

	/**
	 * Show admin confirm dialog.
	 */
	function adminConfirm(message, onConfirm, options) {
		options = options || {};
		var confirmText = options.confirmText || 'Confirm';
		var cancelText = options.cancelText || 'Cancel';
		$('#wpss-admin-confirm-modal').remove();
		var $modal = $('<div id="wpss-admin-confirm-modal" style="position:fixed;inset:0;z-index:160000;display:flex;align-items:center;justify-content:center;">' +
			'<div style="position:absolute;inset:0;background:rgba(0,0,0,.5);" class="wpss-acm-backdrop"></div>' +
			'<div style="position:relative;background:#fff;border-radius:8px;padding:24px;max-width:400px;width:90%;box-shadow:0 4px 20px rgba(0,0,0,.2);">' +
				'<p style="margin:0 0 20px;font-size:14px;">' + $('<span>').text(message).html() + '</p>' +
				'<div style="display:flex;gap:10px;justify-content:flex-end;">' +
					'<button type="button" class="button wpss-acm-cancel">' + $('<span>').text(cancelText).html() + '</button>' +
					'<button type="button" class="button button-primary wpss-acm-ok">' + $('<span>').text(confirmText).html() + '</button>' +
				'</div>' +
			'</div>' +
		'</div>');
		$('body').append($modal);
		$modal.find('.wpss-acm-ok').on('click', function() { $modal.remove(); if (onConfirm) onConfirm(); });
		$modal.find('.wpss-acm-cancel, .wpss-acm-backdrop').on('click', function() { $modal.remove(); });
	}

	/**
	 * Initialize admin order handlers.
	 */
	function init() {
		// Update status button.
		$(document).on('click', '.wpss-update-status', handleStatusUpdate);

		// Quick action buttons.
		$(document).on('click', '.wpss-quick-action', handleQuickAction);

		// Add note button.
		$(document).on('click', '.wpss-add-note-btn', handleAddNote);

		// Resend notification button.
		$(document).on('click', '.wpss-resend-notification', handleResendNotification);

		// Process refund button.
		$(document).on('click', '.wpss-process-refund', handleProcessRefund);

		// Admin requirements form submission.
		$(document).on('submit', '#wpss-admin-requirements-form', handleAdminRequirementsSubmit);
	}

	/**
	 * Handle status update.
	 *
	 * @param {Event} e Click event.
	 */
	function handleStatusUpdate(e) {
		e.preventDefault();

		var $button = $(this);
		var orderId = $button.data('order');
		var $select = $('#wpss-order-status');
		var newStatus = $select.val();

		if (!newStatus) {
			notify(wpssOrderAdmin.i18n.error || 'Please select a status.', 'warning');
			return;
		}

		adminConfirm(wpssOrderAdmin.i18n.confirmStatusChange, function() {
			$button.prop('disabled', true).text(wpssOrderAdmin.i18n.updating || 'Updating...');

			$.ajax({
				url: wpssOrderAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_admin_update_order_status',
					nonce: wpssOrderAdmin.nonce,
					order_id: orderId,
					status: newStatus
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						notify(response.data.message || wpssOrderAdmin.i18n.error, 'error');
						$button.prop('disabled', false).text(wpssOrderAdmin.i18n.update || 'Update');
					}
				},
				error: function() {
					notify(wpssOrderAdmin.i18n.error, 'error');
					$button.prop('disabled', false).text(wpssOrderAdmin.i18n.update || 'Update');
				}
			});
		});
	}

	/**
	 * Handle quick action.
	 *
	 * @param {Event} e Click event.
	 */
	function handleQuickAction(e) {
		e.preventDefault();

		var $button = $(this);
		var orderId = $button.data('order');
		var action = $button.data('action');

		adminConfirm(wpssOrderAdmin.i18n.confirmStatusChange, function() {
			// Map quick actions to status changes.
			var actionToStatus = {
				'start': 'in_progress',
				'complete': 'completed',
				'cancel': 'cancelled',
				'extend': null,
				'revision': 'revision_requested',
				'resolve_buyer': 'refunded',
				'resolve_vendor': 'completed'
			};

			var newStatus = actionToStatus[action];

			if (!newStatus) {
				notify('This action is not yet implemented.', 'info');
				return;
			}

			$button.prop('disabled', true);

			$.ajax({
				url: wpssOrderAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_admin_update_order_status',
					nonce: wpssOrderAdmin.nonce,
					order_id: orderId,
					status: newStatus
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						notify(response.data.message || wpssOrderAdmin.i18n.error, 'error');
						$button.prop('disabled', false);
					}
				},
				error: function() {
					notify(wpssOrderAdmin.i18n.error, 'error');
					$button.prop('disabled', false);
				}
			});
		});
	}

	/**
	 * Handle add note.
	 *
	 * @param {Event} e Click event.
	 */
	function handleAddNote(e) {
		e.preventDefault();

		var $button = $(this);
		var orderId = $button.data('order');
		var $textarea = $('#wpss-new-note');
		var note = $textarea.val().trim();

		if (!note) {
			notify((wpssOrderAdmin.i18n && wpssOrderAdmin.i18n.enterNote) || 'Please enter a note.', 'warning');
			return;
		}

		$button.prop('disabled', true);

		$.ajax({
			url: wpssOrderAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpss_admin_add_order_note',
				nonce: wpssOrderAdmin.nonce,
				order_id: orderId,
				note: note
			},
			success: function(response) {
				if (response.success) {
					// Add the note to the list.
					var noteHtml = '<li class="wpss-note">' +
						'<div class="wpss-note-content">' + escapeHtml(response.data.note.content) + '</div>' +
						'<div class="wpss-note-meta">' + response.data.note.author + ' - ' + response.data.note.created_at + '</div>' +
						'</li>';

					var $notesList = $('.wpss-notes-list');
					if ($notesList.length === 0) {
						$('.wpss-admin-notes').prepend('<ul class="wpss-notes-list"></ul>');
						$notesList = $('.wpss-notes-list');
					}
					$notesList.append(noteHtml);

					// Clear textarea.
					$textarea.val('');

					notify(wpssOrderAdmin.i18n.noteAdded, 'success');
				} else {
					notify(response.data.message || wpssOrderAdmin.i18n.error, 'error');
				}
				$button.prop('disabled', false);
			},
			error: function() {
				notify(wpssOrderAdmin.i18n.error, 'error');
				$button.prop('disabled', false);
			}
		});
	}

	/**
	 * Handle resend notification.
	 *
	 * @param {Event} e Click event.
	 */
	function handleResendNotification(e) {
		e.preventDefault();

		var $button = $(this);

		adminConfirm('Resend notifications to buyer and vendor?', function() {
			$button.prop('disabled', true);

			// This would need a backend handler.
			notify('Notification resend is not yet implemented.', 'info');
			$button.prop('disabled', false);
		});
	}

	/**
	 * Handle process refund.
	 *
	 * @param {Event} e Click event.
	 */
	function handleProcessRefund(e) {
		e.preventDefault();

		var $button = $(this);

		adminConfirm(wpssOrderAdmin.i18n.confirmRefund, function() {
			$button.prop('disabled', true);

			// This would need a backend handler.
			notify('Refund processing is not yet implemented.', 'info');
			$button.prop('disabled', false);
		});
	}

	/**
	 * Handle admin requirements form submission.
	 *
	 * @param {Event} e Submit event.
	 */
	function handleAdminRequirementsSubmit(e) {
		e.preventDefault();

		var $form = $(this);
		var $button = $form.find('.wpss-submit-requirements');
		var $spinner = $form.find('.spinner');
		var $errors = $form.find('.wpss-form-errors');
		var orderId = $button.data('order');

		// Collect form data.
		var formData = $form.serializeArray();
		var fieldData = {};

		// Parse form data into field_data object.
		formData.forEach(function(item) {
			if (item.name.indexOf('field_data[') === 0) {
				// Extract field key from name like "field_data[Field Name]" or "field_data[Field Name][]"
				var match = item.name.match(/field_data\[([^\]]+)\](\[\])?/);
				if (match) {
					var key = match[1];
					var isArray = match[2] === '[]';

					if (isArray) {
						if (!fieldData[key]) {
							fieldData[key] = [];
						}
						fieldData[key].push(item.value);
					} else {
						fieldData[key] = item.value;
					}
				}
			}
		});

		// Clear previous errors.
		$errors.hide().empty();

		// Disable button and show spinner.
		$button.prop('disabled', true);
		$spinner.addClass('is-active');

		$.ajax({
			url: wpssOrderAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpss_admin_submit_requirements',
				nonce: wpssOrderAdmin.nonce,
				order_id: orderId,
				field_data: fieldData
			},
			success: function(response) {
				if (response.success) {
					// Reload page to show submitted requirements.
					location.reload();
				} else {
					// Show errors.
					var errorHtml = '<p><strong>' + escapeHtml(response.data.message || 'An error occurred.') + '</strong></p>';

					if (response.data.errors) {
						errorHtml += '<ul>';
						for (var field in response.data.errors) {
							if (response.data.errors.hasOwnProperty(field)) {
								errorHtml += '<li>' + escapeHtml(response.data.errors[field]) + '</li>';
							}
						}
						errorHtml += '</ul>';
					}

					$errors.html(errorHtml).show();

					$button.prop('disabled', false);
					$spinner.removeClass('is-active');
				}
			},
			error: function() {
				$errors.html('<p>' + escapeHtml(wpssOrderAdmin.i18n.error || 'An error occurred.') + '</p>').show();
				$button.prop('disabled', false);
				$spinner.removeClass('is-active');
			}
		});
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} text Text to escape.
	 * @return {string} Escaped text.
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	// Initialize on DOM ready.
	$(document).ready(init);

})(jQuery);
