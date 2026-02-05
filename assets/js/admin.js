/**
 * WP Sell Services - Admin JavaScript
 *
 * @package WPSellServices
 * @since   1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Initialize admin functionality
	 */
	function init() {
		initPackagesRepeater();
		initFAQRepeater();
		initRequirementsRepeater();
		initAddonsRepeater();
		initGalleryUpload();
		initSortable();
		initColorPicker();
		initCategoryImageUpload();
	}

	/**
	 * Packages repeater functionality
	 */
	function initPackagesRepeater() {
		var $container = $('#wpss-packages-list');
		var $addButton = $('#wpss-add-package');
		var maxPackages = 3;

		if (!$container.length || !$addButton.length) {
			return;
		}

		// Add Package
		$addButton.on('click', function(e) {
			e.preventDefault();
			var count = $container.find('.wpss-package-item').length;
			if (count >= maxPackages) {
				return;
			}

			var template = wp.template('wpss-package-item');
			$container.append(template({ index: count }));

			// Hide add button if max reached
			if (count + 1 >= maxPackages) {
				$addButton.hide();
			}
		});

		// Remove Package (not first one)
		$(document).on('click', '.wpss-remove-package', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).closest('.wpss-package-item').remove();
			reindexPackages();
			$addButton.show();
		});

		// Toggle collapse/expand
		$(document).on('click', '.wpss-package-toggle', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).closest('.wpss-package-item').toggleClass('collapsed');
		});

		// Click header to toggle (but not on buttons)
		$(document).on('click', '.wpss-package-header', function(e) {
			if (!$(e.target).closest('button').length) {
				$(this).closest('.wpss-package-item').toggleClass('collapsed');
			}
		});

		// Update title in header when name changes
		$(document).on('input', '.wpss-package-name-input', function() {
			var $item = $(this).closest('.wpss-package-item');
			var name = $(this).val() || 'New Package';
			$item.find('.wpss-package-title').text(name);
		});

		// Update price in header when price changes
		$(document).on('input', '.wpss-package-price-input', function() {
			var $item = $(this).closest('.wpss-package-item');
			var price = parseFloat($(this).val()) || 0;
			var formatted = price > 0 ? '$' + price.toFixed(2) : '';
			$item.find('.wpss-package-price-display').text(formatted);
		});
	}

	/**
	 * Reindex packages after removal
	 */
	function reindexPackages() {
		$('#wpss-packages-list .wpss-package-item').each(function(index) {
			$(this).attr('data-index', index);
			$(this).find('input, select, textarea').each(function() {
				var name = $(this).attr('name');
				if (name) {
					$(this).attr('name', name.replace(/wpss_packages\[\d+\]/, 'wpss_packages[' + index + ']'));
				}
			});
			// First package cannot be removed
			var $removeBtn = $(this).find('.wpss-remove-package');
			if (index === 0) {
				$removeBtn.remove();
			}
		});
	}

	/**
	 * FAQ repeater functionality
	 */
	function initFAQRepeater() {
		var $container = $('#wpss-faqs-list');
		var $addButton = $('#wpss-add-faq');

		if (!$container.length || !$addButton.length) {
			return;
		}

		// Add FAQ using WordPress template
		$addButton.on('click', function(e) {
			e.preventDefault();
			var index = $container.find('.wpss-faq-item').length;
			var template = wp.template('wpss-faq-item');
			$container.append(template({ index: index }));
		});

		// Remove FAQ
		$(document).on('click', '.wpss-remove-faq', function(e) {
			e.preventDefault();
			$(this).closest('.wpss-faq-item').remove();
			reindexFAQs();
		});
	}

	/**
	 * Reindex FAQs after removal
	 */
	function reindexFAQs() {
		$('#wpss-faqs-list .wpss-faq-item').each(function(index) {
			$(this).attr('data-index', index);
			$(this).find('input, textarea').each(function() {
				var name = $(this).attr('name');
				if (name) {
					$(this).attr('name', name.replace(/wpss_faqs\[\d+\]/, 'wpss_faqs[' + index + ']'));
				}
			});
		});
	}

	/**
	 * Requirements repeater functionality
	 */
	function initRequirementsRepeater() {
		var $container = $('#wpss-requirements-list');
		var $addButton = $('#wpss-add-requirement');

		if (!$container.length || !$addButton.length) {
			return;
		}

		// Add Requirement using WordPress template
		$addButton.on('click', function(e) {
			e.preventDefault();
			var index = $container.find('.wpss-requirement-item').length;
			var template = wp.template('wpss-requirement-item');
			$container.append(template({ index: index }));
		});

		// Remove Requirement
		$(document).on('click', '.wpss-remove-requirement', function(e) {
			e.preventDefault();
			$(this).closest('.wpss-requirement-item').remove();
			reindexRequirements();
		});

		// Handle type change to show/hide choices field
		$(document).on('change', '.wpss-requirement-type', function() {
			var $item = $(this).closest('.wpss-requirement-item');
			var $choices = $item.find('.wpss-requirement-choices');
			var type = $(this).val();

			if (type === 'select' || type === 'radio') {
				$choices.slideDown(200);
			} else {
				$choices.slideUp(200);
			}
		});
	}

	/**
	 * Reindex requirements after removal
	 */
	function reindexRequirements() {
		$('#wpss-requirements-list .wpss-requirement-item').each(function(index) {
			$(this).attr('data-index', index);
			$(this).find('input, select').each(function() {
				var name = $(this).attr('name');
				if (name) {
					$(this).attr('name', name.replace(/wpss_requirements\[\d+\]/, 'wpss_requirements[' + index + ']'));
				}
			});
		});
	}

	/**
	 * Addons repeater functionality
	 */
	function initAddonsRepeater() {
		var $container = $('#wpss-addons-list');
		var $addButton = $('#wpss-add-addon');

		if (!$container.length || !$addButton.length) {
			return;
		}

		// Add Addon using WordPress template
		$addButton.on('click', function(e) {
			e.preventDefault();
			var index = $container.find('.wpss-addon-item').length;
			var template = wp.template('wpss-addon-item');
			$container.append(template({ index: index }));
		});

		// Remove Addon
		$(document).on('click', '.wpss-remove-addon', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).closest('.wpss-addon-item').remove();
			reindexAddons();
		});

		// Toggle Addon collapse/expand
		$(document).on('click', '.wpss-addon-toggle', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).closest('.wpss-addon-item').toggleClass('collapsed');
		});

		// Handle field type change to show/hide conditional fields
		$(document).on('change', '.wpss-addon-field-type', function() {
			var $item = $(this).closest('.wpss-addon-item');
			var $quantityFields = $item.find('.wpss-addon-quantity-fields');
			var $dropdownFields = $item.find('.wpss-addon-dropdown-fields');
			var type = $(this).val();

			// Hide all conditional fields first
			$quantityFields.slideUp(200);
			$dropdownFields.slideUp(200);

			// Show relevant fields based on type
			if (type === 'quantity') {
				$quantityFields.slideDown(200);
			} else if (type === 'dropdown') {
				$dropdownFields.slideDown(200);
			}
		});

		// Update addon title in header when title input changes
		$(document).on('input', '.wpss-addon-title-input', function() {
			var $item = $(this).closest('.wpss-addon-item');
			var title = $(this).val() || 'New Add-on';
			$item.find('.wpss-addon-header .wpss-addon-title').text(title);
		});
	}

	/**
	 * Reindex addons after removal
	 */
	function reindexAddons() {
		$('#wpss-addons-list .wpss-addon-item').each(function(index) {
			$(this).attr('data-index', index);
			$(this).find('input, select, textarea').each(function() {
				var name = $(this).attr('name');
				if (name) {
					$(this).attr('name', name.replace(/wpss_addons\[\d+\]/, 'wpss_addons[' + index + ']'));
				}
			});
		});
	}

	/**
	 * Gallery upload functionality
	 */
	function initGalleryUpload() {
		var $container = $('#wpss-gallery-images');
		var $addButton = $('#wpss-add-gallery-images');

		if (!$container.length || !$addButton.length) {
			return;
		}

		// Open media library
		$addButton.on('click', function(e) {
			e.preventDefault();

			var frame = wp.media({
				title: wpssAdmin.i18n.selectImages || 'Select Gallery Images',
				multiple: true,
				library: { type: 'image' },
				button: { text: wpssAdmin.i18n.useImage || 'Add to Gallery' }
			});

			frame.on('select', function() {
				var attachments = frame.state().get('selection').toJSON();
				var existingIds = [];

				// Get existing IDs
				$container.find('.wpss-gallery-item').each(function() {
					existingIds.push($(this).data('id').toString());
				});

				attachments.forEach(function(attachment) {
					if (existingIds.indexOf(attachment.id.toString()) === -1) {
						var imgUrl = attachment.sizes && attachment.sizes.thumbnail
							? attachment.sizes.thumbnail.url
							: attachment.url;
						var item = '<div class="wpss-gallery-item" data-id="' + attachment.id + '">' +
							'<img src="' + imgUrl + '">' +
							'<button type="button" class="wpss-remove-image">&times;</button>' +
							'<input type="hidden" name="wpss_gallery[]" value="' + attachment.id + '">' +
							'</div>';
						$container.append(item);
					}
				});
			});

			frame.open();
		});

		// Remove image
		$(document).on('click', '.wpss-remove-image', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$(this).closest('.wpss-gallery-item').remove();
		});
	}

	/**
	 * Initialize sortable for packages, FAQs, requirements, addons, and gallery
	 */
	function initSortable() {
		if (!$.fn.sortable) {
			return;
		}

		$('#wpss-packages-list').sortable({
			handle: '.wpss-sortable-handle',
			placeholder: 'wpss-sortable-placeholder',
			update: function() {
				reindexPackages();
			}
		});

		$('#wpss-faqs-list').sortable({
			handle: '.wpss-sortable-handle',
			placeholder: 'wpss-sortable-placeholder',
			update: function() {
				reindexFAQs();
			}
		});

		$('#wpss-requirements-list').sortable({
			handle: '.wpss-sortable-handle',
			placeholder: 'wpss-sortable-placeholder',
			update: function() {
				reindexRequirements();
			}
		});

		$('#wpss-addons-list').sortable({
			handle: '.wpss-sortable-handle',
			placeholder: 'wpss-sortable-placeholder',
			update: function() {
				reindexAddons();
			}
		});

		$('#wpss-gallery-images').sortable({
			placeholder: 'wpss-sortable-placeholder',
			update: function() {
				// Gallery order is determined by hidden input order
			}
		});
	}

	/**
	 * Initialize color picker for category color
	 */
	function initColorPicker() {
		if ($.fn.wpColorPicker) {
			$('.wpss-color-picker').wpColorPicker();
		}
	}

	/**
	 * Initialize category image upload functionality
	 */
	function initCategoryImageUpload() {
		// Upload image button
		$(document).on('click', '.wpss-upload-image', function(e) {
			e.preventDefault();

			var $button = $(this);
			var targetId = $button.data('target');
			var $input = $('#' + targetId);
			var $preview = $('#' + targetId + '-preview');
			var $removeBtn = $button.siblings('.wpss-remove-image');

			var frame = wp.media({
				title: wpssAdmin.i18n.selectImage || 'Select Image',
				multiple: false,
				library: { type: 'image' },
				button: { text: wpssAdmin.i18n.useImage || 'Use Image' }
			});

			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				var imgUrl = attachment.sizes && attachment.sizes.thumbnail
					? attachment.sizes.thumbnail.url
					: attachment.url;

				$input.val(attachment.id);
				$preview.html('<img src="' + imgUrl + '" style="max-width: 150px; height: auto;">');
				$removeBtn.show();
			});

			frame.open();
		});

		// Remove image button
		$(document).on('click', '.wpss-remove-image', function(e) {
			e.preventDefault();

			var $button = $(this);
			var targetId = $button.data('target');
			var $input = $('#' + targetId);
			var $preview = $('#' + targetId + '-preview');

			$input.val('');
			$preview.html('');
			$button.hide();
		});
	}

	// Clear image upload state when a new term is added via AJAX (WordPress add-tag form).
	$(document).ajaxComplete(function(event, xhr, settings) {
		if (settings.data && typeof settings.data === 'string' &&
			settings.data.indexOf('action=add-tag') !== -1 &&
			xhr.responseJSON && xhr.responseJSON.success) {

			// Target both standard WordPress form and any term forms.
			var $form = $('#addtag, .term-form');

			// Clear all image inputs (hidden and regular) that contain "image" in the name/id.
			$form.find('input[name*="image"]').val('');
			$form.find('input[id*="image"]').val('');

			// Clear image previews - match any element with "image" and "preview" in the id/class.
			$form.find('[class*="image-preview"]').html('');
			$form.find('[id$="-preview"]').html('');

			// Hide remove buttons.
			$form.find('.wpss-remove-image').hide();

			// Also clear the specific WPSS category/tag image fields directly.
			$('#wpss_category_image, #wpss_tag_image').val('');
			$('#wpss_category_image-preview, #wpss_tag_image-preview').html('');
		}
	});

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);
