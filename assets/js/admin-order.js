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
			alert(wpssOrderAdmin.i18n.error || 'Please select a status.');
			return;
		}

		if (!confirm(wpssOrderAdmin.i18n.confirmStatusChange)) {
			return;
		}

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
					alert(response.data.message || wpssOrderAdmin.i18n.error);
					$button.prop('disabled', false).text(wpssOrderAdmin.i18n.update || 'Update');
				}
			},
			error: function() {
				alert(wpssOrderAdmin.i18n.error);
				$button.prop('disabled', false).text(wpssOrderAdmin.i18n.update || 'Update');
			}
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

		if (!confirm(wpssOrderAdmin.i18n.confirmStatusChange)) {
			return;
		}

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
			alert('This action is not yet implemented.');
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
					alert(response.data.message || wpssOrderAdmin.i18n.error);
					$button.prop('disabled', false);
				}
			},
			error: function() {
				alert(wpssOrderAdmin.i18n.error);
				$button.prop('disabled', false);
			}
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
			alert('Please enter a note.');
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

					alert(wpssOrderAdmin.i18n.noteAdded);
				} else {
					alert(response.data.message || wpssOrderAdmin.i18n.error);
				}
				$button.prop('disabled', false);
			},
			error: function() {
				alert(wpssOrderAdmin.i18n.error);
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

		if (!confirm('Resend notifications to buyer and vendor?')) {
			return;
		}

		$button.prop('disabled', true);

		// This would need a backend handler.
		alert('Notification resend is not yet implemented.');
		$button.prop('disabled', false);
	}

	/**
	 * Handle process refund.
	 *
	 * @param {Event} e Click event.
	 */
	function handleProcessRefund(e) {
		e.preventDefault();

		var $button = $(this);

		if (!confirm(wpssOrderAdmin.i18n.confirmRefund)) {
			return;
		}

		$button.prop('disabled', true);

		// This would need a backend handler.
		alert('Refund processing is not yet implemented.');
		$button.prop('disabled', false);
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
