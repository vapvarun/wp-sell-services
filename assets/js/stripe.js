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
		addressElement: null,
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
			// Single-service checkout and cart (multi) checkout use different form
			// IDs; bind to whichever is present so cart checkout also gets the
			// confirmPayment step instead of being left with no handler.
			this.form = document.getElementById('wpss-checkout-form')
				|| document.getElementById('wpss-multi-checkout-form');
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

				// No Stripe Address Element. Billing details are our own block
				// (templates/partials/billing-fields.php), rendered above the
				// payment section and identical on every gateway. We read the
				// values out of it at confirm time — see readBillingDetails().

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
			var addonIds = document.querySelector('input[name="addon_ids"]')?.value || '';

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
						addon_ids: addonIds,
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
				// Collect billing name + address (required by Stripe for export
				// charges on India-registered accounts). Validate before charging
				// so the buyer gets a field-level prompt instead of a raw API error.
				const confirmParams = {
					return_url: wpssStripe.returnUrl,
				};

				// Billing details come from OUR block, not a gateway element.
				// Stripe REQUIRES name + address for export (cross-border)
				// charges on India-registered accounts, so this is validated
				// before charging — the buyer gets a field-level prompt rather
				// than a raw API error.
				//
				// No confirmParams.shipping: services are not shippable, and it
				// used to mirror billing_details for no reason.
				const billing = this.readBillingDetails();

				if (!billing.complete) {
					this.showError(wpssStripe.i18n.addressRequired || 'Please complete your billing name and address.');
					this.revealBillingForm();
					this.setLoading(false);
					return;
				}

				confirmParams.payment_method_data = {
					billing_details: billing.details,
				};

				// Confirm payment with Stripe.
				const { error, paymentIntent } = await this.stripe.confirmPayment({
					elements: this.elements,
					confirmParams: confirmParams,
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
		 * Read billing details out of OUR billing block.
		 *
		 * Gateway-agnostic by design: the same block feeds Stripe here, and
		 * PayPal / Razorpay / Woo elsewhere. Reads from the visible form when
		 * the buyer is editing, and from the server-rendered profile values
		 * when the block is collapsed to its summary — so a returning customer
		 * who never opens the form still sends a complete address.
		 *
		 * @return {{complete: boolean, details: Object}}
		 */
		readBillingDetails: function() {
			const val = (key) => {
				const el = document.querySelector('[name="' + key + '"]');
				return el ? (el.value || '').trim() : '';
			};

			// Collapsed summary state: the form is present but hidden, and its
			// inputs still carry the profile values, so the same read works.
			const details = {
				name: [val('billing_first_name'), val('billing_last_name')].filter(Boolean).join(' '),
				email: val('billing_email'),
				phone: val('billing_phone'),
				address: {
					line1: val('billing_address_1'),
					line2: val('billing_address_2'),
					city: val('billing_city'),
					state: val('billing_state'),
					postal_code: val('billing_postcode'),
					country: (val('billing_country') || '').toUpperCase(),
				},
			};

			// Stripe rejects empty strings on optional fields; drop them.
			if (!details.phone) { delete details.phone; }
			if (!details.email) { delete details.email; }
			if (!details.address.line2) { delete details.address.line2; }
			if (!details.address.state) { delete details.address.state; }

			const complete = !!(
				details.name &&
				details.address.line1 &&
				details.address.city &&
				details.address.postal_code &&
				details.address.country
			);

			return { complete: complete, details: details };
		},

		/**
		 * Expand the billing form when validation fails on a collapsed block.
		 *
		 * Without this the buyer is told to complete their address while the
		 * fields are still hidden behind the summary — an error with nothing
		 * to act on.
		 */
		revealBillingForm: function() {
			const form = document.querySelector('[data-wpss-billing-form]');
			const summary = document.querySelector('[data-wpss-billing-summary]');

			if (form) {
				form.removeAttribute('hidden');
				const firstEmpty = form.querySelector('input[required]:invalid, select[required]:invalid');
				if (firstEmpty && firstEmpty.focus) { firstEmpty.focus(); }
			}
			if (summary) { summary.setAttribute('hidden', 'hidden'); }
		},

		/**
		 * Confirm payment and create order.
		 */
		confirmPaymentAndCreateOrder: function(paymentIntentId) {
			const serviceId = document.querySelector('input[name="service_id"]')?.value || 0;
			const packageId = document.querySelector('input[name="package_id"]')?.value || 0;
			const addonIds = document.querySelector('input[name="addon_ids"]')?.value || '';

			$.ajax({
				url: wpssStripe.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_stripe_confirm_payment',
					nonce: wpssStripe.nonce,
					payment_intent_id: paymentIntentId,
					service_id: serviceId,
					package_id: packageId,
					addon_ids: addonIds,
					// Cart checkout creates one order per cart item server-side.
					is_multi_checkout: (this.form && this.form.id === 'wpss-multi-checkout-form') ? 1 : '',
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
