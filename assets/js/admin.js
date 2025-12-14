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
		initPackageTabs();
		initFAQRepeater();
		initRequirementsRepeater();
		initGalleryUpload();
		initSortable();
		initColorPicker();
	}

	/**
	 * Package tabs functionality
	 */
	function initPackageTabs() {
		var $nav = $('.wpss-packages-nav');
		var $content = $('.wpss-packages-content');

		if (!$nav.length || !$content.length) {
			return;
		}

		$nav.on('click', '.wpss-package-nav-btn', function(e) {
			e.preventDefault();
			var tier = $(this).data('tier');

			// Update nav buttons
			$nav.find('.wpss-package-nav-btn').removeClass('active');
			$(this).addClass('active');

			// Update panels
			$content.find('.wpss-package-panel').removeClass('active');
			$content.find('.wpss-package-panel[data-tier="' + tier + '"]').addClass('active');
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
	 * Initialize sortable for FAQs, requirements, and gallery
	 */
	function initSortable() {
		if (!$.fn.sortable) {
			return;
		}

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

	// Initialize on document ready
	$(document).ready(init);

})(jQuery);
