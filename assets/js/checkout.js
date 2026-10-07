/**
 * Standalone checkout: gateway picker, account step and generic submit.
 *
 * The ONE script for both checkout forms - the single-service / pay-order form
 * (#wpss-checkout-form) and the multi-item cart form (#wpss-multi-checkout-form).
 * Each used to carry its own inline copy and they had drifted. A form opts in
 * with data-wpss-checkout-notice="<id of its notice element>".
 *
 * Gateway scripts (stripe.js, paypal.js, Pro razorpay.js) bind to the same forms
 * by id; this handler stands down for any gateway that declares
 * data-wpss-own-submit, so exactly one listener ever posts a payment.
 *
 * Data comes from wp_localize_script as window.wpssCheckout.
 *
 * @package WPSellServices
 * @since   1.8.0
 */
(function() {
	'use strict';

	var config = window.wpssCheckout || {};
	var i18n = config.i18n || {};

	/*
	 * Create the buyer's account BEFORE paying, when they are logged out and
	 * the owner has enabled account-at-checkout.
	 *
	 * The order matters. Every gateway handler requires a logged-in user and
	 * the order row needs a real customer_id, so the account has to exist
	 * first - not after a successful charge, which would leave money taken
	 * against nobody if creation then failed.
	 *
	 * The fresh nonce also matters: WordPress nonces are bound to the user, so
	 * the checkout nonce rendered for a logged-out visitor stops verifying the
	 * instant they are signed in. Without swapping it, the payment request
	 * would fail its own security check with the account already created.
	 *
	 * needsAccount is decided on the server, never here: true only when the
	 * visitor is logged out AND the owner enabled account-at-checkout. The
	 * cart checkout needs a login to have a cart at all, so it is always false
	 * there and this resolves immediately.
	 */
	function initForm(form) {
		// Two Pay buttons (under the payment method and in the summary) submit
		// the same form; they always read and behave the same.
		var submitBtns = form.querySelectorAll('.wpss-checkout-button');

		function setButtons(text, disabled) {
			submitBtns.forEach(function(btn) {
				btn.disabled = disabled;
				var label = btn.querySelector('.wpss-checkout-button__text');
				if (label) {
					label.textContent = text;
				}
			});
		}

		function selectedLabel() {
			var checked = form.querySelector('input[name="payment_method"]:checked');
			var first = submitBtns[0] && submitBtns[0].querySelector('.wpss-checkout-button__text');
			return (checked && checked.getAttribute('data-button-label')) || (first ? first.textContent : '');
		}
		var noticeEl = document.getElementById(form.getAttribute('data-wpss-checkout-notice'));
		var needsAccount = !!config.needsAccount;

		function showNotice(msg, type) {
			if (!noticeEl) {
				return;
			}
			noticeEl.className = 'wpss-notice wpss-notice--' + (type || 'error');
			noticeEl.textContent = msg;
			noticeEl.style.display = 'flex';
			noticeEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}

		function hideNotice() {
			if (noticeEl) {
				noticeEl.style.display = 'none';
			}
		}

		function ensureAccount() {
			if (!needsAccount) {
				return Promise.resolve();
			}

			var accountData = new FormData();
			accountData.append('action', 'wpss_checkout_create_account');
			accountData.append('nonce', form.querySelector('[name="wpss_checkout_nonce"]').value);
			accountData.append('checkout_url', window.location.href);

			['billing_first_name', 'billing_last_name', 'billing_email'].forEach(function(name) {
				var field = form.querySelector('[name="' + name + '"]');
				accountData.append(name, field ? field.value : '');
			});

			return fetch(config.ajaxUrl, {
				method: 'POST',
				body: accountData,
				credentials: 'same-origin'
			})
			.then(function(response) { return response.json(); })
			.then(function(data) {
				if (data.success && data.data && data.data.checkout_nonce) {
					form.querySelector('[name="wpss_checkout_nonce"]').value = data.data.checkout_nonce;
					needsAccount = false;
					return;
				}

				var payload = data.data || {};

				if (payload.code === 'account_exists' && payload.login_url && noticeEl) {
					// A link, not a redirect: bouncing the buyer away from a
					// filled-in checkout without asking is worse than telling
					// them what to do next.
					noticeEl.className = 'wpss-notice wpss-notice--error';
					noticeEl.textContent = payload.message + ' ';
					var link = document.createElement('a');
					link.href = payload.login_url;
					link.textContent = i18n.logIn;
					noticeEl.appendChild(link);
					noticeEl.style.display = 'flex';
					noticeEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
					throw new Error('wpss_account_handled');
				}

				showNotice(payload.message || i18n.accountFailed);
				throw new Error('wpss_account_handled');
			});
		}

		// Published so gateways that own their own submit (Stripe, PayPal) can
		// await the same account step instead of each re-implementing it.
		// Assigned at init, not inside the submit handler - that handler returns
		// early for exactly those gateways, so publishing from in there would
		// define the seam only for the buyers who never need it.
		window.wpssEnsureCheckoutAccount = ensureAccount;

		// Show/hide gateway forms + active state on radio change.
		form.querySelectorAll('input[name="payment_method"]').forEach(function(radio) {
			radio.addEventListener('change', function() {
				hideNotice();
				form.querySelectorAll('.wpss-co-method').forEach(function(m) {
					m.classList.remove('wpss-co-method--active');
				});
				form.querySelectorAll('.wpss-gateway-form').forEach(function(gform) {
					gform.style.display = 'none';
				});
				var method = this.closest('.wpss-co-method');
				if (method) {
					method.classList.add('wpss-co-method--active');
				}
				var selected = form.querySelector('.wpss-gateway-form[data-gateway="' + this.value + '"]');
				if (selected) {
					selected.style.display = 'block';
				}
				// The gateway's own words: "Place order" for pay-later methods.
				setButtons(selectedLabel(), false);
			});
		});

		// Click anywhere on a method card to select its radio.
		form.querySelectorAll('.wpss-co-method').forEach(function(method) {
			method.addEventListener('click', function(e) {
				if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON' || e.target.tagName === 'A') {
					return;
				}
				var radio = this.querySelector('input[type="radio"]');
				if (radio && !radio.checked) {
					radio.checked = true;
					radio.dispatchEvent(new Event('change', { bubbles: true }));
				}
			});
		});

		form.addEventListener('submit', function(e) {
			e.preventDefault();
			hideNotice();

			var paymentMethod = form.querySelector('input[name="payment_method"]:checked');
			if (!paymentMethod) {
				showNotice(i18n.selectMethod);
				return;
			}

			// Gateways that mount their own payment UI declare
			// data-wpss-own-submit and are bound to this same form by their own
			// script. They MUST confirm with the PSP before an order is created,
			// so this generic handler stands down - otherwise it races them and
			// posts an unconfirmed payment intent (card never charged). They
			// await window.wpssEnsureCheckoutAccount themselves.
			var ownSubmit = form.querySelector('.wpss-gateway-form[data-gateway="' + paymentMethod.value + '"] [data-wpss-own-submit], [data-gateway="' + paymentMethod.value + '"][data-wpss-own-submit]');
			if (ownSubmit) {
				return;
			}

			var restoreText = selectedLabel();
			setButtons(i18n.processing, true);

			function restoreButton() {
				setButtons(restoreText, false);
			}

			ensureAccount().then(function() {
				var formData = new FormData(form);
				formData.append('action', 'wpss_' + paymentMethod.value + '_process_payment');
				// Gateway-specific nonce if the gateway renders one (e.g.
				// wpss_test_nonce), otherwise the checkout nonce.
				var gatewayNonce = form.querySelector('[name="wpss_' + paymentMethod.value + '_nonce"]');
				if (gatewayNonce) {
					formData.append('nonce', gatewayNonce.value);
				} else {
					formData.append('nonce', form.querySelector('[name="wpss_checkout_nonce"]').value);
				}

				return fetch(config.ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					if (data.success && data.data && data.data.redirect_url) {
						window.location.href = data.data.redirect_url;
					} else if (data.success && data.data && data.data.redirect) {
						window.location.href = data.data.redirect;
					} else {
						showNotice((data.data && data.data.message) ? data.data.message : i18n.paymentFailed);
						restoreButton();
					}
				});
			})
			.catch(function(error) {
				// The account step shows its own message; do not talk over it
				// with a generic payment error.
				if (error && 'wpss_account_handled' === error.message) {
					restoreButton();
					return;
				}
				console.error('Checkout error:', error);
				showNotice(i18n.genericError);
				restoreButton();
			});
		});
	}

	function init() {
		// Hides the theme sidebar via the body.wpss-checkout-page rules the
		// checkout renders. Set on any page carrying the checkout shell,
		// including the sign-in wall, so the layout matches with or without
		// a form.
		if (document.querySelector('.wpss-checkout-page')) {
			document.body.classList.add('wpss-checkout-page');
		}

		document.querySelectorAll('form[data-wpss-checkout-notice]').forEach(initForm);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
