/**
 * WP Sell Services Pro - Stripe Payment Integration
 *
 * @package WPSellServicesPro
 * @since   1.0.0
 */

(function($) {
	'use strict';

	const WPSSStripe = {
		stripe: null,
		elements: null,
		paymentElement: null,
		form: null,
		submitButton: null,
		errorElement: null,
		paymentIntentId: null,

		/**
		 * Initialize Stripe integration.
		 */
		init: function() {
			const container = document.querySelector('.wpss-stripe-payment');
			if (!container) {
				return;
			}

			const publishableKey = container.dataset.publishableKey || wpssStripe.publishableKey;
			if (!publishableKey) {
				console.error('WPSS Stripe: Publishable key not found');
				return;
			}

			this.stripe = Stripe(publishableKey);
			this.form = document.getElementById('wpss-checkout-form');
			this.errorElement = document.getElementById('wpss-stripe-error');

			this.setupEventListeners();
		},

		/**
		 * Set up event listeners.
		 */
		setupEventListeners: function() {
			// Listen for payment method selection.
			const stripeRadio = document.querySelector('input[name="payment_method"][value="stripe"]');
			if (stripeRadio) {
				stripeRadio.addEventListener('change', () => {
					this.mountPaymentElement();
				});

				// Auto-mount if already selected.
				if (stripeRadio.checked) {
					this.mountPaymentElement();
				}
			}

			// Handle form submission.
			if (this.form) {
				this.form.addEventListener('submit', (e) => {
					const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
					if (selectedMethod && selectedMethod.value === 'stripe') {
						e.preventDefault();
						this.handlePayment();
					}
				});
			}
		},

		/**
		 * Mount Stripe Payment Element.
		 */
		mountPaymentElement: async function() {
			const elementContainer = document.getElementById('wpss-stripe-payment-element');
			if (!elementContainer || this.paymentElement) {
				return;
			}

			// Get payment details from form.
			const amount = parseFloat(document.querySelector('input[name="amount"]')?.value || 0);
			const currency = document.querySelector('input[name="currency"]')?.value || 'USD';
			const serviceId = document.querySelector('input[name="service_id"]')?.value || 0;
			const packageId = document.querySelector('input[name="package_id"]')?.value || 0;

			if (amount <= 0) {
				this.showError('Invalid payment amount.');
				return;
			}

			// Create payment intent.
			try {
				const response = await this.createPaymentIntent(amount, currency, serviceId, packageId);

				if (!response.success) {
					this.showError(response.data?.message || 'Failed to initialize payment.');
					return;
				}

				this.paymentIntentId = response.data.id;
				document.getElementById('wpss-stripe-payment-intent-id').value = response.data.id;

				// Create and mount Payment Element.
				this.elements = this.stripe.elements({
					clientSecret: response.data.client_secret,
					appearance: {
						theme: 'stripe',
						variables: {
							colorPrimary: '#1e3a5f',
							fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
						},
					},
				});

				this.paymentElement = this.elements.create('payment', {
					layout: 'tabs',
				});

				this.paymentElement.mount(elementContainer);

				this.paymentElement.on('change', (event) => {
					if (event.error) {
						this.showError(event.error.message);
					} else {
						this.hideError();
					}
				});

			} catch (error) {
				console.error('Stripe initialization error:', error);
				this.showError(wpssStripe.i18n.error);
			}
		},

		/**
		 * Create payment intent via AJAX.
		 */
		createPaymentIntent: function(amount, currency, serviceId, packageId) {
			return new Promise((resolve) => {
				$.ajax({
					url: wpssStripe.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_stripe_create_payment_intent',
						nonce: wpssStripe.nonce,
						amount: amount,
						currency: currency,
						service_id: serviceId,
						package_id: packageId,
					},
					success: resolve,
					error: () => {
						resolve({ success: false, data: { message: wpssStripe.i18n.error } });
					},
				});
			});
		},

		/**
		 * Handle payment submission.
		 */
		handlePayment: async function() {
			if (!this.stripe || !this.elements) {
				this.showError('Payment not initialized. Please refresh and try again.');
				return;
			}

			this.setLoading(true);
			this.hideError();

			try {
				// Confirm payment with Stripe.
				const { error, paymentIntent } = await this.stripe.confirmPayment({
					elements: this.elements,
					confirmParams: {
						return_url: wpssStripe.returnUrl,
					},
					redirect: 'if_required',
				});

				if (error) {
					this.showError(error.message);
					this.setLoading(false);
					return;
				}

				// Payment succeeded, create order.
				if (paymentIntent && paymentIntent.status === 'succeeded') {
					await this.confirmPaymentAndCreateOrder(paymentIntent.id);
				}

			} catch (error) {
				console.error('Payment error:', error);
				this.showError(wpssStripe.i18n.error);
				this.setLoading(false);
			}
		},

		/**
		 * Confirm payment and create order.
		 */
		confirmPaymentAndCreateOrder: function(paymentIntentId) {
			const serviceId = document.querySelector('input[name="service_id"]')?.value || 0;
			const packageId = document.querySelector('input[name="package_id"]')?.value || 0;

			$.ajax({
				url: wpssStripe.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_stripe_confirm_payment',
					nonce: wpssStripe.nonce,
					payment_intent_id: paymentIntentId,
					service_id: serviceId,
					package_id: packageId,
				},
				success: (response) => {
					this.setLoading(false);

					if (response.success) {
						// Redirect to requirements page.
						window.location.href = response.data.redirect_url;
					} else {
						this.showError(response.data?.message || 'Failed to create order.');
					}
				},
				error: () => {
					this.setLoading(false);
					this.showError(wpssStripe.i18n.error);
				},
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
			this.submitButton = this.submitButton || this.form?.querySelector('button[type="submit"]');

			if (this.submitButton) {
				this.submitButton.disabled = loading;

				if (loading) {
					this.submitButton.dataset.originalText = this.submitButton.textContent;
					this.submitButton.textContent = wpssStripe.i18n.processing;
				} else if (this.submitButton.dataset.originalText) {
					this.submitButton.textContent = this.submitButton.dataset.originalText;
				}
			}
		},
	};

	// Initialize on DOM ready.
	$(document).ready(function() {
		WPSSStripe.init();
	});

	// Export for external use.
	window.WPSSStripe = WPSSStripe;

})(jQuery);
