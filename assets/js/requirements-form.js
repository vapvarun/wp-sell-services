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
						showError(response.data && response.data.message ? response.data.message : wpss_ajax.i18n.submit_error || 'Failed to submit requirements.');
						resetButton();
					}
				},
				error: function() {
					showError(wpss_ajax.i18n.ajax_error || 'An error occurred. Please try again.');
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
		 * @param {FileList} newFiles The files to add.
		 */
		function handleFiles(newFiles) {
			for (var i = 0; i < newFiles.length && files.length < maxFiles; i++) {
				files.push(newFiles[i]);
			}
			renderFiles();
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
