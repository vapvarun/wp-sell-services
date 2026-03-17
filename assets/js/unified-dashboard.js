/**
 * Unified Dashboard JavaScript
 *
 * Handles AJAX interactions for the unified dashboard.
 *
 * @package WPSellServices
 * @since   1.1.0
 */

(function ($) {
	'use strict';

	if (typeof wpssUnifiedDashboard === 'undefined') {
		return;
	}

	const UnifiedDashboard = {
		/**
		 * Initialize the dashboard.
		 */
		init: function () {
			this.bindEvents();
		},

		/**
		 * Bind event handlers.
		 */
		bindEvents: function () {
			// Become vendor button
			$(document).on('click', '[data-action="become-vendor"]', this.handleBecomeVendor.bind(this));

			// Request withdrawal button
			$(document).on('click', '[data-action="request-withdrawal"]', this.handleWithdrawal.bind(this));

			// Profile form submission
			$(document).on('submit', '[data-ajax-form="update-profile"]', this.handleProfileUpdate.bind(this));

			// Toggle service status
			$(document).on('click', '.wpss-toggle-status', this.handleToggleStatus.bind(this));

			// Delete service
			$(document).on('click', '.wpss-delete-service', this.handleDeleteService.bind(this));

			// Avatar upload
			$(document).on('click', '#wpss-avatar-upload-btn', this.handleAvatarUpload.bind(this));
			$(document).on('click', '#wpss-avatar-remove-btn', this.handleAvatarRemove.bind(this));

			// Cover image upload
			$(document).on('click', '#wpss-cover-upload-btn', this.handleCoverUpload.bind(this));
			$(document).on('click', '#wpss-cover-remove-btn', this.handleCoverRemove.bind(this));

			// Buyer request management
			$(document).on('click', '.wpss-close-request', this.handleCloseRequest.bind(this));
			$(document).on('click', '.wpss-delete-request', this.handleDeleteRequest.bind(this));

			// Portfolio
			$(document).on('click', '#wpss-portfolio-add-btn', this.handlePortfolioAdd.bind(this));
			$(document).on('click', '.wpss-portfolio-edit', this.handlePortfolioEdit.bind(this));
			$(document).on('click', '.wpss-portfolio-delete', this.handlePortfolioDelete.bind(this));
			$(document).on('click', '.wpss-portfolio-toggle-featured', this.handlePortfolioToggleFeatured.bind(this));
			$(document).on('submit', '#wpss-portfolio-form', this.handlePortfolioSave.bind(this));
			$(document).on('click', '#wpss-portfolio-upload-media', this.handlePortfolioMediaUpload.bind(this));
			$(document).on('click', '.wpss-modal__close, .wpss-modal__close-btn, .wpss-modal__overlay', this.handleModalClose.bind(this));
		},

		/**
		 * Handle become vendor button click.
		 *
		 * @param {Event} e Click event.
		 */
		handleBecomeVendor: function (e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const originalText = $button.text();

			WPSS.showConfirm(wpssUnifiedDashboard.i18n.becomeVendorConfirm, function () {
				$button
					.prop('disabled', true)
					.text(wpssUnifiedDashboard.i18n.processing);

			$.ajax({
				url: wpssUnifiedDashboard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_become_vendor',
					nonce: wpssUnifiedDashboard.nonce
				},
				success: function (response) {
					if (response.success) {
						// Redirect to services section
						if (response.data.redirect) {
							window.location.href = response.data.redirect;
						} else {
							window.location.reload();
						}
					} else {
						WPSS.showNotification(response.data.message || 'An error occurred.', 'error');
						$button
							.prop('disabled', false)
							.text(originalText);
					}
				},
				error: function () {
					WPSS.showNotification('An error occurred. Please try again.', 'error');
					$button
						.prop('disabled', false)
						.text(originalText);
				}
			});
			});
		},

		/**
		 * Handle withdrawal request.
		 *
		 * @param {Event} e Click event.
		 */
		handleWithdrawal: function (e) {
			e.preventDefault();

			// TODO: Open withdrawal modal
			WPSS.showNotification('Withdrawal feature coming soon.', 'info');
		},

		/**
		 * Handle profile form update.
		 *
		 * @param {Event} e Submit event.
		 */
		handleProfileUpdate: function (e) {
			e.preventDefault();

			const $form = $(e.currentTarget);
			const $button = $form.find('button[type="submit"]');
			const originalText = $button.text();

			$button
				.prop('disabled', true)
				.text(wpssUnifiedDashboard.i18n.processing);

			$.ajax({
				url: wpssUnifiedDashboard.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=wpss_update_vendor_profile',
				success: function (response) {
					if (response.success) {
						// Show success message
						$form.prepend(
							'<div class="wpss-alert wpss-alert--success" style="margin-bottom: 16px;">' +
							response.data.message +
							'</div>'
						);

						// Remove after 3 seconds
						setTimeout(function () {
							$form.find('.wpss-alert--success').fadeOut(function () {
								$(this).remove();
							});
						}, 3000);
					} else {
						WPSS.showNotification(response.data.message || 'An error occurred.', 'error');
					}

					$button
						.prop('disabled', false)
						.text(originalText);
				},
				error: function () {
					WPSS.showNotification('An error occurred. Please try again.', 'error');
					$button
						.prop('disabled', false)
						.text(originalText);
				}
			});
		},

		/**
		 * Handle toggle service status.
		 *
		 * @param {Event} e Click event.
		 */
		handleToggleStatus: function (e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const serviceId = $button.data('service-id');
			const currentStatus = $button.data('current-status');

			$button.prop('disabled', true);

			$.ajax({
				url: wpssUnifiedDashboard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_update_service_status',
					nonce: wpssUnifiedDashboard.nonce,
					service_id: serviceId,
					status: currentStatus
				},
				success: function (response) {
					if (response.success) {
						$button.data('current-status', response.data.new_status);

						const $card = $button.closest('.wpss-card');
						const $badge = $card.find('.wpss-badge');
						const newStatusText = response.data.new_status === 'publish' ? 'Published' : 'Draft';
						$badge.text(newStatusText);
						$badge.removeClass('wpss-badge--success wpss-badge--neutral');
						$badge.addClass(response.data.new_status === 'publish' ? 'wpss-badge--success' : 'wpss-badge--neutral');

						if (response.data.new_status === 'publish') {
							$button.html('<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>');
							$button.attr('title', wpssUnifiedDashboard.i18n.pause || 'Pause');
						} else {
							$button.html('<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>');
							$button.attr('title', wpssUnifiedDashboard.i18n.activate || 'Activate');
						}
					} else {
						WPSS.showNotification(response.data.message || 'An error occurred.', 'error');
					}
					$button.prop('disabled', false);
				},
				error: function () {
					WPSS.showNotification('An error occurred. Please try again.', 'error');
					$button.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle delete service.
		 *
		 * @param {Event} e Click event.
		 */
		handleDeleteService: function (e) {
			e.preventDefault();

			const $button = $(e.currentTarget);
			const serviceId = $button.data('service-id');
			const $card = $button.closest('.wpss-card');

			WPSS.showConfirm(wpssUnifiedDashboard.i18n.confirmDelete || 'Are you sure you want to delete this service? This action cannot be undone.', function () {
				$button.prop('disabled', true);

				$.ajax({
					url: wpssUnifiedDashboard.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_delete_service',
						nonce: wpssUnifiedDashboard.nonce,
						service_id: serviceId
					},
					success: function (response) {
						if (response.success) {
							$card.fadeOut(300, function () {
								$(this).remove();
							});
						} else {
							WPSS.showNotification(response.data.message || 'An error occurred.', 'error');
							$button.prop('disabled', false);
						}
					},
					error: function () {
						WPSS.showNotification('An error occurred. Please try again.', 'error');
						$button.prop('disabled', false);
					}
				});
			}, { confirmText: 'Delete' });
		},

		/**
		 * Handle avatar upload via WP media library.
		 *
		 * @param {Event} e Click event.
		 */
		handleAvatarUpload: function (e) {
			e.preventDefault();

			if (this.avatarFrame) {
				this.avatarFrame.open();
				return;
			}

			this.avatarFrame = wp.media({
				title: 'Choose Profile Photo',
				button: { text: 'Use as Profile Photo' },
				multiple: false,
				library: { type: 'image' }
			});

			this.avatarFrame.on('select', function () {
				var attachment = this.avatarFrame.state().get('selection').first().toJSON();
				var url = attachment.sizes && attachment.sizes.thumbnail
					? attachment.sizes.thumbnail.url
					: attachment.url;

				$('#wpss-avatar-preview').attr('src', url);
				$('#wpss-avatar-id').val(attachment.id);

				// Show remove button if not already visible.
				if ($('#wpss-avatar-remove-btn').length === 0) {
					$('#wpss-avatar-upload-btn').after(
						' <button type="button" class="wpss-btn wpss-btn--small wpss-btn--link" id="wpss-avatar-remove-btn">Remove</button>'
					);
				}
			}.bind(this));

			this.avatarFrame.open();
		},

		/**
		 * Handle avatar removal.
		 *
		 * @param {Event} e Click event.
		 */
		handleAvatarRemove: function (e) {
			e.preventDefault();

			$('#wpss-avatar-id').val('0');
			// Fall back to Gravatar.
			var $img = $('#wpss-avatar-preview');
			var gravatarUrl = $img.data('gravatar') || '';
			if (gravatarUrl) {
				$img.attr('src', gravatarUrl);
			}
			$(e.currentTarget).remove();
		},

		/**
		 * Handle cover image upload via WP media library.
		 *
		 * @param {Event} e Click event.
		 */
		handleCoverUpload: function (e) {
			e.preventDefault();

			if (this.coverFrame) {
				this.coverFrame.open();
				return;
			}

			this.coverFrame = wp.media({
				title: 'Select Cover Image',
				button: { text: 'Set Cover Image' },
				multiple: false,
				library: { type: 'image' }
			});

			this.coverFrame.on('select', function () {
				var attachment = this.coverFrame.state().get('selection').first().toJSON();
				var url = attachment.sizes && attachment.sizes.large
					? attachment.sizes.large.url
					: attachment.url;

				$('#wpss-cover-preview').attr('src', url).show();
				$('#wpss-cover-placeholder').hide();
				$('#wpss-cover-id').val(attachment.id);

				// Show remove button if not already visible.
				if ($('#wpss-cover-remove-btn').length === 0) {
					$('#wpss-cover-upload-btn').after(
						' <button type="button" class="wpss-btn wpss-btn--small wpss-btn--link" id="wpss-cover-remove-btn">Remove</button>'
					);
				}
			}.bind(this));

			this.coverFrame.open();
		},

		/**
		 * Handle cover image removal.
		 *
		 * @param {Event} e Click event.
		 */
		handleCoverRemove: function (e) {
			e.preventDefault();

			$('#wpss-cover-id').val('0');
			$('#wpss-cover-preview').hide();
			$('#wpss-cover-placeholder').show();
			$(e.currentTarget).remove();
		},

		/**
		 * Handle closing a buyer request (set to draft).
		 *
		 * @param {Event} e Click event.
		 */
		handleCloseRequest: function (e) {
			e.preventDefault();
			var requestId = $(e.currentTarget).data('request-id');

			if (!confirm('Close this request? It will no longer be visible to sellers.')) {
				return;
			}

			$.ajax({
				url: wpssUnifiedDashboard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_update_request_status',
					request_id: requestId,
					status: 'draft',
					nonce: wpssUnifiedDashboard.nonce
				},
				success: function (response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Failed to close request.');
					}
				}
			});
		},

		/**
		 * Handle deleting a buyer request.
		 *
		 * @param {Event} e Click event.
		 */
		handleDeleteRequest: function (e) {
			e.preventDefault();
			var requestId = $(e.currentTarget).data('request-id');

			if (!confirm('Delete this request permanently? This cannot be undone.')) {
				return;
			}

			$.ajax({
				url: wpssUnifiedDashboard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_delete_request',
					request_id: requestId,
					nonce: wpssUnifiedDashboard.nonce
				},
				success: function (response) {
					if (response.success) {
						location.reload();
					} else {
						alert(response.data.message || 'Failed to delete request.');
					}
				}
			});
		},

		/**
		 * Open portfolio modal for adding.
		 */
		handlePortfolioAdd: function (e) {
			e.preventDefault();
			this.resetPortfolioForm();
			$('#wpss-portfolio-modal-title').text('Add Portfolio Item');
			$('#wpss-portfolio-modal').addClass('wpss-modal-open');
		},

		/**
		 * Open portfolio modal for editing.
		 *
		 * @param {Event} e Click event.
		 */
		handlePortfolioEdit: function (e) {
			e.preventDefault();
			var $item = $(e.currentTarget).closest('.wpss-portfolio__item');
			var itemId = $item.data('item-id');

			this.resetPortfolioForm();
			$('#wpss-portfolio-modal-title').text('Edit Portfolio Item');
			$('#wpss-portfolio-item-id').val(itemId);

			// Populate form from data attributes.
			var title = $item.find('.wpss-portfolio__title').text();
			var description = $item.attr('data-description') || '';
			var externalUrl = $item.attr('data-external-url') || '';
			var tags = $item.attr('data-tags') || '';
			var serviceId = $item.attr('data-service-id') || '0';
			var isFeatured = $item.attr('data-is-featured') === '1';
			var mediaIds = [];
			var mediaThumbs = [];

			try {
				mediaIds = JSON.parse($item.attr('data-media') || '[]');
				mediaThumbs = JSON.parse($item.attr('data-media-thumbs') || '[]');
			} catch (ex) {
				mediaIds = [];
				mediaThumbs = [];
			}

			$('#portfolio-title').val(title);
			$('#portfolio-description').val(description);
			$('#portfolio-external-url').val(externalUrl);
			$('#portfolio-tags').val(tags);
			$('#portfolio-service').val(serviceId);
			$('#portfolio-featured').prop('checked', isFeatured);

			// Restore media preview and hidden field.
			if (mediaIds.length) {
				var $preview = $('#wpss-portfolio-media-preview');
				$preview.empty();
				mediaThumbs.forEach(function (thumbUrl) {
					$preview.append('<img src="' + thumbUrl + '" class="wpss-portfolio-media-thumb">');
				});
				$('#wpss-portfolio-media').val(JSON.stringify(mediaIds));
			}

			$('#wpss-portfolio-modal').addClass('wpss-modal-open');
		},

		/**
		 * Handle portfolio delete.
		 *
		 * @param {Event} e Click event.
		 */
		handlePortfolioDelete: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var itemId = $btn.data('item-id');

			if (!confirm('Are you sure you want to delete this portfolio item?')) {
				return;
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: wpssUnifiedDashboard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_delete_portfolio_item',
					portfolio_nonce: $('#wpss-portfolio-form [name="portfolio_nonce"]').val() || $('[name="portfolio_nonce"]').val(),
					item_id: itemId
				},
				success: function (response) {
					if (response.success) {
						$btn.closest('.wpss-portfolio__item').fadeOut(300, function () {
							$(this).remove();
						});
					} else {
						WPSS.showNotification(response.data.message || 'Delete failed.', 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function () {
					WPSS.showNotification('An error occurred.', 'error');
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle portfolio toggle featured.
		 *
		 * @param {Event} e Click event.
		 */
		handlePortfolioToggleFeatured: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var itemId = $btn.data('item-id');

			$btn.prop('disabled', true);

			$.ajax({
				url: wpssUnifiedDashboard.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_toggle_featured_portfolio',
					portfolio_nonce: $('[name="portfolio_nonce"]').val(),
					item_id: itemId
				},
				success: function (response) {
					if (response.success) {
						window.location.reload();
					} else {
						WPSS.showNotification(response.data.message || 'Failed.', 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function () {
					WPSS.showNotification('An error occurred.', 'error');
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle portfolio form save.
		 *
		 * @param {Event} e Submit event.
		 */
		handlePortfolioSave: function (e) {
			e.preventDefault();
			var $form = $(e.currentTarget);
			var $btn = $form.find('button[type="submit"]');
			var originalText = $btn.text();

			$btn.prop('disabled', true).text(wpssUnifiedDashboard.i18n.processing);

			$.ajax({
				url: wpssUnifiedDashboard.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=wpss_add_portfolio_item',
				success: function (response) {
					if (response.success) {
						$('#wpss-portfolio-modal').removeClass('wpss-modal-open');
						window.location.reload();
					} else {
						WPSS.showNotification(response.data.message || 'Save failed.', 'error');
						$btn.prop('disabled', false).text(originalText);
					}
				},
				error: function () {
					WPSS.showNotification('An error occurred.', 'error');
					$btn.prop('disabled', false).text(originalText);
				}
			});
		},

		/**
		 * Handle portfolio media upload.
		 *
		 * @param {Event} e Click event.
		 */
		handlePortfolioMediaUpload: function (e) {
			e.preventDefault();

			if (this.portfolioMediaFrame) {
				this.portfolioMediaFrame.open();
				return;
			}

			this.portfolioMediaFrame = wp.media({
				title: 'Select Portfolio Images',
				button: { text: 'Add to Portfolio' },
				multiple: true,
				library: { type: 'image' }
			});

			this.portfolioMediaFrame.on('select', function () {
				var attachments = this.portfolioMediaFrame.state().get('selection').toJSON();
				var ids = attachments.map(function (a) { return a.id; });
				var $preview = $('#wpss-portfolio-media-preview');

				$preview.empty();
				attachments.forEach(function (att) {
					var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
					$preview.append('<img src="' + url + '" class="wpss-portfolio-media-thumb">');
				});
				$('#wpss-portfolio-media').val(JSON.stringify(ids));
			}.bind(this));

			this.portfolioMediaFrame.open();
		},

		/**
		 * Close modal.
		 *
		 * @param {Event} e Click event.
		 */
		handleModalClose: function (e) {
			e.preventDefault();
			$(e.currentTarget).closest('.wpss-modal').removeClass('wpss-modal-open');
		},

		/**
		 * Reset portfolio form to defaults.
		 */
		resetPortfolioForm: function () {
			$('#wpss-portfolio-form')[0].reset();
			$('#wpss-portfolio-item-id').val('0');
			$('#wpss-portfolio-media').val('[]');
			$('#wpss-portfolio-media-preview').empty();
		}
	};

	// Initialize when DOM is ready
	$(function () {
		UnifiedDashboard.init();
	});

})(jQuery);
