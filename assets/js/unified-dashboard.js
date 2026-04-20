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
			$(document).on('click', '.wpss-reopen-request', this.handleReopenRequest.bind(this));
			$(document).on('click', '.wpss-delete-request', this.handleDeleteRequest.bind(this));

			// Portfolio
			$(document).on('click', '#wpss-portfolio-add-btn', this.handlePortfolioAdd.bind(this));
			$(document).on('click', '.wpss-portfolio-edit', this.handlePortfolioEdit.bind(this));
			$(document).on('click', '.wpss-portfolio-delete', this.handlePortfolioDelete.bind(this));
			$(document).on('click', '.wpss-portfolio-toggle-featured', this.handlePortfolioToggleFeatured.bind(this));
			$(document).on('submit', '#wpss-portfolio-form', this.handlePortfolioSave.bind(this));
			$(document).on('click', '#wpss-portfolio-upload-media', this.handlePortfolioMediaUpload.bind(this));
			$(document).on('click', '.wpss-modal__close, .wpss-modal__close-btn, .wpss-modal__overlay', this.handleModalClose.bind(this));

			// Favorites — unfavorite from the saved grid.
			$(document).on('click', '.wpss-favorites__remove', this.handleFavoriteRemove.bind(this));
		},

		/**
		 * Remove a service from the favorites grid and update the empty state
		 * if the grid becomes empty.
		 *
		 * @param {Event} e Click event.
		 */
		handleFavoriteRemove: function (e) {
			e.preventDefault();
			e.stopPropagation();

			var $button = $(e.currentTarget);
			var serviceId = parseInt($button.data('service-id'), 10);
			if (!serviceId || $button.prop('disabled')) {
				return;
			}

			var $card = $button.closest('.wpss-favorites__card');
			$button.prop('disabled', true);

			var i18n = (wpssUnifiedDashboard && wpssUnifiedDashboard.i18n) || {};

			$.post(wpssUnifiedDashboard.ajaxUrl, {
				action: 'wpss_unfavorite_service',
				service_id: serviceId,
				nonce: wpssUnifiedDashboard.serviceNonce
			}).done(function (response) {
				if (response && response.success) {
					$card.fadeOut(180, function () {
						$card.remove();
						// If the last card was removed, reload to show the empty state.
						if ($('.wpss-favorites__card').length === 0) {
							window.location.reload();
						} else {
							var $count = $('.wpss-favorites__count');
							if ($count.length && typeof response.data.count === 'number') {
								$count.text(
									(response.data.count === 1
										? (i18n.favoriteCountSingular || '%d saved service')
										: (i18n.favoriteCountPlural || '%d saved services')
									).replace('%d', response.data.count)
								);
							}
						}
					});
				} else {
					$button.prop('disabled', false);
					window.alert((response && response.data && response.data.message) || i18n.favoriteRemoveFailed || 'Could not remove favorite.');
				}
			}).fail(function () {
				$button.prop('disabled', false);
				window.alert(i18n.favoriteRemoveFailed || 'Could not remove favorite. Please try again.');
			});
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
						WPSS.showNotification(response.data.message || (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.errorOccurred) || 'An error occurred.', 'error');
						$button
							.prop('disabled', false)
							.text(originalText);
					}
				},
				error: function () {
					WPSS.showNotification((wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.errorTryAgain) || 'An error occurred. Please try again.', 'error');
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

			// Withdrawal form is handled inline in the earnings/wallet template.
			// Scroll to the withdrawal section if it exists.
			var $section = $('#wpss-withdrawal-form, #wpss-withdraw-form');
			if ($section.length) {
				$('html, body').animate({ scrollTop: $section.offset().top - 100 }, 300);
				$section.find('input[name="amount"]').focus();
			}
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
						WPSS.showNotification(response.data.message || (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.errorOccurred) || 'An error occurred.', 'error');
					}

					$button
						.prop('disabled', false)
						.text(originalText);
				},
				error: function () {
					WPSS.showNotification((wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.errorTryAgain) || 'An error occurred. Please try again.', 'error');
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
						const newStatusText = response.data.new_status === 'publish' ? ((wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.published) || 'Published') : ((wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.draft) || 'Draft');
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
						WPSS.showNotification(response.data.message || (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.errorOccurred) || 'An error occurred.', 'error');
					}
					$button.prop('disabled', false);
				},
				error: function () {
					WPSS.showNotification((wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.errorTryAgain) || 'An error occurred. Please try again.', 'error');
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
							WPSS.showNotification(response.data.message || (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.errorOccurred) || 'An error occurred.', 'error');
							$button.prop('disabled', false);
						}
					},
					error: function () {
						WPSS.showNotification((wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.errorTryAgain) || 'An error occurred. Please try again.', 'error');
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
				title: (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.chooseProfilePhoto) || 'Choose Profile Photo',
				button: { text: (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.useAsProfilePhoto) || 'Use as Profile Photo' },
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
						' <button type="button" class="wpss-btn wpss-btn--small wpss-btn--link" id="wpss-avatar-remove-btn">' + ((wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.remove) || 'Remove') + '</button>'
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
				title: (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.selectCoverImage) || 'Select Cover Image',
				button: { text: (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.setCoverImage) || 'Set Cover Image' },
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
						' <button type="button" class="wpss-btn wpss-btn--small wpss-btn--link" id="wpss-cover-remove-btn">' + ((wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.remove) || 'Remove') + '</button>'
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

			WPSS.showConfirm(
				wpssUnifiedDashboard.i18n.closeRequestConfirm || 'Close this request? It will no longer be visible to sellers.',
				function () {
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
								WPSS.showNotification(response.data.message || (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.requestClosed) || 'Request closed.', 'success');
								location.reload();
							} else {
								WPSS.showNotification(response.data.message || (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.requestCloseFailed) || 'Failed to close request.', 'error');
							}
						}
					});
				}
			);
		},

		/**
		 * Handle reopening a closed buyer request (set to publish).
		 *
		 * @param {Event} e Click event.
		 */
		handleReopenRequest: function (e) {
			e.preventDefault();
			var requestId = $(e.currentTarget).data('request-id');

			WPSS.showConfirm(
				wpssUnifiedDashboard.i18n.reopenRequestConfirm || 'Reopen this request? It will be visible to sellers again.',
				function () {
					$.ajax({
						url: wpssUnifiedDashboard.ajaxUrl,
						type: 'POST',
						data: {
							action: 'wpss_update_request_status',
							request_id: requestId,
							status: 'publish',
							nonce: wpssUnifiedDashboard.nonce
						},
						success: function (response) {
							if (response.success) {
								WPSS.showNotification(response.data.message || (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.requestReopened) || 'Request reopened.', 'success');
								location.reload();
							} else {
								WPSS.showNotification(response.data.message || (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.requestReopenFailed) || 'Failed to reopen request.', 'error');
							}
						}
					});
				}
			);
		},

		/**
		 * Handle deleting a buyer request.
		 *
		 * @param {Event} e Click event.
		 */
		handleDeleteRequest: function (e) {
			e.preventDefault();
			var requestId = $(e.currentTarget).data('request-id');

			WPSS.showConfirm(
				wpssUnifiedDashboard.i18n.deleteRequestConfirm || 'Delete this request permanently? This cannot be undone.',
				function () {
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
								WPSS.showNotification(response.data.message || (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.requestDeleted) || 'Request deleted.', 'success');
								location.reload();
							} else {
								WPSS.showNotification(response.data.message || (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.requestDeleteFailed) || 'Failed to delete request.', 'error');
							}
						}
					});
				},
				{ confirmText: wpssUnifiedDashboard.i18n.deleteConfirmBtn || 'Delete' }
			);
		},

		/**
		 * Open portfolio modal for adding.
		 */
		handlePortfolioAdd: function (e) {
			e.preventDefault();
			this.resetPortfolioForm();
			$('#wpss-portfolio-modal-title').text((wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.addPortfolioItem) || 'Add Portfolio Item');
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
			$('#wpss-portfolio-modal-title').text((wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.editPortfolioItem) || 'Edit Portfolio Item');
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

			WPSS.showConfirm(
				wpssUnifiedDashboard.i18n.deletePortfolioConfirm || 'Are you sure you want to delete this portfolio item?',
				function () {
					$btn.prop('disabled', true);

					$.ajax({
						url: wpssUnifiedDashboard.restUrl + 'portfolio/' + itemId,
						type: 'DELETE',
						beforeSend: function (xhr) {
							xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce);
						},
						success: function () {
							$btn.closest('.wpss-portfolio__item').fadeOut(300, function () {
								$(this).remove();
							});
						},
						error: function (xhr) {
							var msg = (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.deleteFailed) || 'Delete failed.';
							try { msg = JSON.parse(xhr.responseText).message || msg; } catch (ex) {}
							WPSS.showNotification(msg, 'error');
							$btn.prop('disabled', false);
						}
					});
				},
				{ confirmText: wpssUnifiedDashboard.i18n.deleteConfirmBtn || 'Delete' }
			);
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
				url: wpssUnifiedDashboard.restUrl + 'portfolio/' + itemId + '/featured',
				type: 'POST',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce);
				},
				success: function () {
					window.location.reload();
				},
				error: function (xhr) {
					var msg = (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.failed) || 'Failed.';
					try { msg = JSON.parse(xhr.responseText).message || msg; } catch (ex) {}
					WPSS.showNotification(msg, 'error');
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
			var itemId = $form.find('[name="item_id"]').val();
			var isEdit = itemId && itemId !== '0';

			$btn.prop('disabled', true).text(wpssUnifiedDashboard.i18n.processing);

			// Build REST API payload from form fields.
			var mediaVal = $form.find('[name="media"]').val();
			var data = {
				title: $form.find('[name="title"]').val(),
				description: $form.find('[name="description"]').val(),
				external_url: $form.find('[name="external_url"]').val() || '',
				media: mediaVal ? JSON.parse(mediaVal) : []
			};
			var serviceId = $form.find('[name="service_id"]').val();
			if (serviceId) {
				data.service_id = parseInt(serviceId, 10);
			}

			$.ajax({
				url: wpssUnifiedDashboard.restUrl + 'portfolio' + (isEdit ? '/' + itemId : ''),
				type: isEdit ? 'PUT' : 'POST',
				contentType: 'application/json',
				data: JSON.stringify(data),
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce);
				},
				success: function () {
					$('#wpss-portfolio-modal').removeClass('wpss-modal-open');
					window.location.reload();
				},
				error: function (xhr) {
					var msg = (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.saveFailed) || 'Save failed.';
					try { msg = JSON.parse(xhr.responseText).message || msg; } catch (ex) {}
					WPSS.showNotification(msg, 'error');
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
				title: (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.selectPortfolioImages) || 'Select Portfolio Images',
				button: { text: (wpssUnifiedDashboard.i18n && wpssUnifiedDashboard.i18n.addToPortfolio) || 'Add to Portfolio' },
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
