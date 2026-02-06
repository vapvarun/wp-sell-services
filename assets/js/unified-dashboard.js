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
		}
	};

	// Initialize when DOM is ready
	$(function () {
		UnifiedDashboard.init();
	});

})(jQuery);
