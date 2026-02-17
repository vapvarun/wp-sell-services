/**
 * WP Sell Services Pro - PayPal Payment Integration
 *
 * @package WPSellServicesPro
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const WPSSPayPal = {
		form: null,
		errorElement: null,
		buttonRendered: false,

		/**
		 * Initialize PayPal integration.
		 */
		init: function() {
			const container = document.querySelector('.wpss-paypal-payment');
			if (!container) {
				return;
			}

			this.form = document.getElementById('wpss-checkout-form');
			this.errorElement = document.getElementById('wpss-paypal-error');

			this.setupEventListeners();
		},

		/**
		 * Set up event listeners.
		 */
		setupEventListeners: function() {
			// Listen for payment method selection.
			const paypalRadio = document.querySelector('input[name="payment_method"][value="paypal"]');
			if (paypalRadio) {
				paypalRadio.addEventListener('change', () => {
					this.renderPayPalButtons();
				});

				// Auto-render if already selected.
				if (paypalRadio.checked) {
					this.renderPayPalButtons();
				}
			}
		},

		/**
		 * Render PayPal buttons.
		 */
		renderPayPalButtons: function() {
			if (this.buttonRendered || typeof paypal === 'undefined') {
				return;
			}

			const buttonContainer = document.getElementById('wpss-paypal-button-container');
			if (!buttonContainer) {
				return;
			}

			this.buttonRendered = true;

			paypal.Buttons({
				style: {
					layout: 'vertical',
					color: 'gold',
					shape: 'rect',
					label: 'paypal',
				},

				// Create order.
				createOrder: async () => {
					this.hideError();

					const amount = parseFloat(document.querySelector('input[name="amount"]')?.value || 0);
					const currency = document.querySelector('input[name="currency"]')?.value || 'USD';
					const serviceId = document.querySelector('input[name="service_id"]')?.value || 0;
					const packageId = document.querySelector('input[name="package_id"]')?.value || 0;

					if (amount <= 0) {
						this.showError('Invalid payment amount.');
						return Promise.reject(new Error('Invalid amount'));
					}

					try {
						const response = await this.createOrder(amount, currency, serviceId, packageId);

						if (!response.success) {
							this.showError(response.data?.message || 'Failed to create PayPal order.');
							return Promise.reject(new Error(response.data?.message));
						}

						document.getElementById('wpss-paypal-order-id').value = response.data.id;
						return response.data.id;

					} catch (error) {
						console.error('PayPal createOrder error:', error);
						this.showError(wpssPayPal.i18n.error);
						return Promise.reject(error);
					}
				},

				// Capture order.
				onApprove: async (data) => {
					this.setLoading(true);

					try {
						const serviceId = document.querySelector('input[name="service_id"]')?.value || 0;
						const packageId = document.querySelector('input[name="package_id"]')?.value || 0;

						const response = await this.captureOrder(data.orderID, serviceId, packageId);

						if (response.success) {
							window.location.href = response.data.redirect_url;
						} else {
							this.showError(response.data?.message || 'Payment capture failed.');
							this.setLoading(false);
						}

					} catch (error) {
						console.error('PayPal onApprove error:', error);
						this.showError(wpssPayPal.i18n.error);
						this.setLoading(false);
					}
				},

				// Handle errors.
				onError: (err) => {
					console.error('PayPal error:', err);
					this.showError(wpssPayPal.i18n.error);
				},

				// Handle cancel.
				onCancel: () => {
					this.showError('Payment cancelled.');
				},

			}).render(buttonContainer);
		},

		/**
		 * Create PayPal order via AJAX.
		 */
		createOrder: function(amount, currency, serviceId, packageId) {
			return new Promise((resolve) => {
				$.ajax({
					url: wpssPayPal.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_paypal_create_order',
						nonce: wpssPayPal.nonce,
						amount: amount,
						currency: currency,
						service_id: serviceId,
						package_id: packageId,
					},
					success: resolve,
					error: () => {
						resolve({ success: false, data: { message: wpssPayPal.i18n.error } });
					},
				});
			});
		},

		/**
		 * Capture PayPal order via AJAX.
		 */
		captureOrder: function(orderId, serviceId, packageId) {
			return new Promise((resolve) => {
				$.ajax({
					url: wpssPayPal.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_paypal_capture',
						nonce: wpssPayPal.nonce,
						paypal_order_id: orderId,
						service_id: serviceId,
						package_id: packageId,
					},
					success: resolve,
					error: () => {
						resolve({ success: false, data: { message: wpssPayPal.i18n.error } });
					},
				});
			});
		},

		/**
		 * Show error message.
		 */
		showError: function(message) {
			if (this.errorElement) {
				this.errorElement.textContent = message;
				this.errorElement.style.display = 'block';
			}
		},

		/**
		 * Hide error message.
		 */
		hideError: function() {
			if (this.errorElement) {
				this.errorElement.style.display = 'none';
			}
		},

		/**
		 * Set loading state.
		 */
		setLoading: function(loading) {
			const submitButton = this.form?.querySelector('button[type="submit"]');

			if (submitButton) {
				submitButton.disabled = loading;

				if (loading) {
					submitButton.dataset.originalText = submitButton.textContent;
					submitButton.textContent = wpssPayPal.i18n.processing;
				} else if (submitButton.dataset.originalText) {
					submitButton.textContent = submitButton.dataset.originalText;
				}
			}
		},
	};

	// Initialize on DOM ready.
	$(document).ready(function() {
		WPSSPayPal.init();
	});

	// Export for external use.
	window.WPSSPayPal = WPSSPayPal;

})(jQuery);
