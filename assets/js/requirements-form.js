/**
 * WP Sell Services - Requirements Form
 *
 * Handles file upload drag & drop and form submission for order requirements.
 *
 * @package WPSellServices
 * @since   1.0.0
 */

(function($) {
	'use strict';

	if (typeof wpss_ajax === 'undefined') {
		return;
	}

	/**
	 * Initialize requirements forms.
	 */
	function initRequirementsForms() {
		$('.wpss-requirements-form').each(function() {
			initForm($(this));
		});
	}

	/**
	 * Initialize a single form.
	 *
	 * @param {jQuery} $form The form element.
	 */
	function initForm($form) {
		var $submitBtn = $form.find('.wpss-requirements-form__submit-btn');
		var $btnText = $submitBtn.find('.wpss-requirements-form__submit-text');
		var $btnLoading = $submitBtn.find('.wpss-requirements-form__submit-loading');

		// Initialize file upload areas.
		$form.find('.wpss-requirements-form__upload').each(function() {
			initFileUpload($(this));
		});

		// Form submission handler.
		$form.on('submit', function(e) {
			e.preventDefault();

			// Validate required fields.
			var isValid = validateForm($form);

			if (!isValid) {
				$form.find('.wpss-requirements-form__input--error, .wpss-requirements-form__textarea--error, .wpss-requirements-form__select--error').first().focus();
				return;
			}

			// Show loading state.
			$btnText.hide();
			$btnLoading.show();
			$submitBtn.prop('disabled', true);

			var formData = new FormData($form[0]);

			$.ajax({
				url: wpss_ajax.ajax_url,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						if (response.data && response.data.redirect) {
							window.location.href = response.data.redirect;
						} else {
							window.location.reload();
						}
					} else {
						showError(response.data && response.data.message ? response.data.message : wpss_ajax.i18n.submit_error);
						resetButton();
					}
				},
				error: function() {
					showError(wpss_ajax.i18n.ajax_error);
					resetButton();
				}
			});

			function resetButton() {
				$btnText.show();
				$btnLoading.hide();
				$submitBtn.prop('disabled', false);
			}
		});
	}

	/**
	 * Validate form fields.
	 *
	 * @param {jQuery} $form The form element.
	 * @return {boolean} True if valid.
	 */
	function validateForm($form) {
		var isValid = true;

		$form.find('[required]').each(function() {
			var $field = $(this);
			var value = $field.val();

			// Remove previous error state.
			$field.removeClass('wpss-requirements-form__input--error wpss-requirements-form__textarea--error wpss-requirements-form__select--error');

			if (!value || (Array.isArray(value) && value.length === 0)) {
				isValid = false;

				// Add error class based on element type.
				if ($field.is('textarea')) {
					$field.addClass('wpss-requirements-form__textarea--error');
				} else if ($field.is('select')) {
					$field.addClass('wpss-requirements-form__select--error');
				} else {
					$field.addClass('wpss-requirements-form__input--error');
				}
			}
		});

		return isValid;
	}

	/**
	 * Initialize file upload area.
	 *
	 * @param {jQuery} $area The upload area element.
	 */
	function initFileUpload($area) {
		var $input = $area.find('.wpss-requirements-form__upload-input');
		var $fileList = $area.find('.wpss-requirements-form__file-list');
		var maxFiles = parseInt($area.data('max-files'), 10) || 10;
		var files = [];

		// Drag and drop events.
		$area.on('dragover dragenter', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$area.addClass('wpss-requirements-form__upload--dragover');
		});

		$area.on('dragleave dragend drop', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$area.removeClass('wpss-requirements-form__upload--dragover');
		});

		$area.on('drop', function(e) {
			var droppedFiles = e.originalEvent.dataTransfer.files;
			handleFiles(droppedFiles);
		});

		// File input change.
		$input.on('change', function() {
			handleFiles(this.files);
		});

		/**
		 * Handle new files.
		 *
		 * Appending and stopping at the cap silently discarded whatever would
		 * not fit. On a single-file requirement that meant the SECOND pick was
		 * thrown away: the list still showed the first file, input.files still
		 * held it, and the buyer submitted a file they had just replaced. A
		 * one-file control means "this file", so a fresh pick replaces.
		 *
		 * Above one file, appending across picker sessions is the useful
		 * behaviour (pick three, then two more) - but reaching the cap now says
		 * so rather than dropping the remainder in silence.
		 *
		 * @param {FileList} newFiles The files to add.
		 */
		function handleFiles(newFiles) {
			if (maxFiles === 1) {
				files = [];
			}

			var dropped = 0;

			for (var i = 0; i < newFiles.length; i++) {
				if (files.length < maxFiles) {
					files.push(newFiles[i]);
				} else {
					dropped++;
				}
			}

			renderFiles();
			syncInput();
			announceDropped(dropped);
		}

		/**
		 * Tell the user when the cap refused part of their selection.
		 *
		 * @param {number} dropped How many files did not fit.
		 */
		function announceDropped(dropped) {
			var $notice = $area.find('.wpss-requirements-form__upload-notice');

			if (!dropped) {
				$notice.remove();
				return;
			}

			if (!$notice.length) {
				$notice = $('<p class="wpss-requirements-form__upload-notice" role="status"></p>');
				$area.append($notice);
			}

			var template = 1 === dropped
				? (wpss_ajax.i18n.files_capped || 'This field takes up to %1$d. %2$d file was not added.')
				: (wpss_ajax.i18n.files_capped_plural || 'This field takes up to %1$d. %2$d files were not added.');

			$notice.text(
				template.replace('%1$d', maxFiles).replace('%2$d', dropped)
			);
		}

		/**
		 * Mirror the managed files[] list into the native file input via
		 * DataTransfer, so the FormData built from the form on submit includes
		 * drag-dropped files (and reflects removals). Without this the dropped
		 * files lived only in this array and were never submitted — only
		 * click-to-select worked, because the browser sets input.files itself.
		 */
		function syncInput() {
			if (typeof DataTransfer === 'undefined' || !$input.length) {
				return;
			}
			var dt = new DataTransfer();
			files.forEach(function(file) {
				dt.items.add(file);
			});
			$input[0].files = dt.files;
		}

		/**
		 * Render file list.
		 */
		function renderFiles() {
			$fileList.empty();

			files.forEach(function(file, index) {
				var size = formatFileSize(file.size);
				var icon = getFileIcon(file.type);

				var $item = $('<div class="wpss-requirements-form__file-item">')
					.append(
						'<div class="wpss-requirements-form__file-info">' +
						'<span class="wpss-requirements-form__file-icon">' + icon + '</span>' +
						'<div>' +
						'<span class="wpss-requirements-form__file-name">' + escapeHtml(file.name) + '</span>' +
						'<span class="wpss-requirements-form__file-size">' + size + '</span>' +
						'</div>' +
						'</div>'
					)
					.append(
						'<span class="wpss-requirements-form__file-remove" data-index="' + index + '">' +
						'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
						'</span>'
					);

				$fileList.append($item);
			});
		}

		// Remove file handler.
		$fileList.on('click', '.wpss-requirements-form__file-remove', function() {
			var index = $(this).data('index');
			files.splice(index, 1);
			renderFiles();
			syncInput();
		});
	}

	/**
	 * Format file size.
	 *
	 * @param {number} bytes File size in bytes.
	 * @return {string} Formatted size.
	 */
	function formatFileSize(bytes) {
		if (bytes === 0) {
			return '0 Bytes';
		}

		var k = 1024;
		var sizes = ['Bytes', 'KB', 'MB', 'GB'];
		var i = Math.floor(Math.log(bytes) / Math.log(k));

		return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
	}

	/**
	 * Get file icon SVG based on mime type.
	 *
	 * @param {string} mimeType The file's MIME type.
	 * @return {string} SVG icon markup.
	 */
	function getFileIcon(mimeType) {
		var iconPath;

		if (mimeType.indexOf('image/') === 0) {
			iconPath = '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>';
		} else if (mimeType.indexOf('video/') === 0) {
			iconPath = '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>';
		} else if (mimeType.indexOf('audio/') === 0) {
			iconPath = '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>';
		} else if (mimeType === 'application/pdf') {
			iconPath = '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>';
		} else if (mimeType.indexOf('spreadsheet') > -1 || mimeType.indexOf('excel') > -1) {
			iconPath = '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/><line x1="12" y1="9" x2="12" y2="21"/>';
		} else if (mimeType.indexOf('document') > -1 || mimeType.indexOf('word') > -1) {
			iconPath = '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>';
		} else if (mimeType.indexOf('zip') > -1 || mimeType.indexOf('archive') > -1) {
			iconPath = '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>';
		} else {
			iconPath = '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>';
		}

		return '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' + iconPath + '</svg>';
	}

	/**
	 * Escape HTML entities.
	 *
	 * @param {string} text The text to escape.
	 * @return {string} Escaped text.
	 */
	function escapeHtml(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Show error message.
	 *
	 * @param {string} message The error message.
	 */
	function showError(message) {
		if (typeof WPSS !== 'undefined' && WPSS.showNotification) {
			WPSS.showNotification(message, 'error');
		}
	}

	// Initialize on DOM ready.
	$(document).ready(function() {
		initRequirementsForms();
	});

	// Re-initialize on AJAX content load (for modals, etc.).
	$(document).on('wpss_content_loaded', function() {
		initRequirementsForms();
	});

})(jQuery);
