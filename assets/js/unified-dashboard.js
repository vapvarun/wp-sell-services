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

			if (!confirm(wpssUnifiedDashboard.i18n.becomeVendorConfirm)) {
				return;
			}

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
						alert(response.data.message || 'An error occurred.');
						$button
							.prop('disabled', false)
							.text(originalText);
					}
				},
				error: function () {
					alert('An error occurred. Please try again.');
					$button
						.prop('disabled', false)
						.text(originalText);
				}
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
			alert('Withdrawal feature coming soon.');
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
						alert(response.data.message || 'An error occurred.');
					}

					$button
						.prop('disabled', false)
						.text(originalText);
				},
				error: function () {
					alert('An error occurred. Please try again.');
					$button
						.prop('disabled', false)
						.text(originalText);
				}
			});
		}
	};

	// Initialize when DOM is ready
	$(function () {
		UnifiedDashboard.init();
	});

})(jQuery);
