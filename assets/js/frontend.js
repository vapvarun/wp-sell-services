/**
 * WP Sell Services - Frontend JavaScript
 *
 * @package WPSellServices
 * @since   1.0.0
 */

(function($) {
	'use strict';

	// Global WPSS object.
	window.WPSS = window.WPSS || {};

	/**
	 * Initialize all components.
	 */
	WPSS.init = function() {
		WPSS.initGallery();
		WPSS.initPackages();
		WPSS.initFAQs();
		WPSS.initMessages();
		WPSS.initOrderActions();
		WPSS.initReviews();
		WPSS.initModals();
		WPSS.initContactVendor();
		WPSS.initFilterSidebar();
		WPSS.initProposals();
		WPSS.initVendorRegistration();
		WPSS.initRequirementsView();
		WPSS.portfolioServicesOptions();
	};

	/**
	 * Update mini-cart indicator with current cart count.
	 *
	 * @param {number} count Cart item count.
	 */
	WPSS.updateMiniCart = function(count) {
		var $miniCart = $('#wpss-mini-cart');
		var $count = $('.wpss-cart-count');

		$count.text(count);

		if (count > 0) {
			$miniCart.show();
		} else {
			$miniCart.hide();
		}
	};

	/**
	 * Service Gallery.
	 */
	WPSS.initGallery = function() {
		const $gallery = $('.wpss-service-gallery');

		if (!$gallery.length) {
			return;
		}

		$gallery.on('click', '.wpss-gallery-thumb', function() {
			const $thumb = $(this);
			const $main = $gallery.find('.wpss-gallery-active');
			const src = $thumb.data('src');

			// Update active state.
			$gallery.find('.wpss-gallery-thumb').removeClass('active');
			$thumb.addClass('active');

			// Update main image.
			$main.find('img').attr('src', src);
		});
	};

	/**
	 * Package Tabs.
	 */
	WPSS.initPackages = function() {
		const $widget = $('.wpss-packages-widget');

		if (!$widget.length) {
			return;
		}

		$widget.on('click', '.wpss-package-tab', function() {
			const $tab = $(this);
			const index = $tab.data('package');

			// Update tabs.
			$widget.find('.wpss-package-tab').removeClass('active');
			$tab.addClass('active');

			// Update content.
			$widget.find('.wpss-package').removeClass('active');
			$widget.find('.wpss-package[data-package="' + index + '"]').addClass('active');
		});

		// Order button click.
		// Skip on single service pages - handled by single-service.js.
		if (typeof wpssService !== 'undefined') {
			return;
		}

		$widget.on('click', '.wpss-order-btn', function() {
			const $btn = $(this);
			const serviceId = $btn.data('service');
			const packageIndex = $btn.data('package');
			const price = $btn.data('price');

			// Trigger checkout process.
			WPSS.checkout({
				serviceId: serviceId,
				packageIndex: packageIndex,
				price: price
			});
		});
	};

	/**
	 * FAQ Accordion.
	 *
	 * Note: On single service pages, single-service.js handles FAQs with enhanced animations.
	 * This handler only runs when WPSSService is not available.
	 */
	WPSS.initFAQs = function() {
		// Skip if single-service.js handles FAQs (has enhanced handler).
		if (typeof window.WPSSService !== 'undefined') {
			return;
		}

		const $faqs = $('.wpss-service-faqs');

		if (!$faqs.length) {
			return;
		}

		$faqs.on('click', '.wpss-faq-question', function(e) {
			e.preventDefault();

			const $question = $(this);
			const $item = $question.closest('.wpss-faq-item');
			const $answer = $item.length ? $item.find('.wpss-faq-answer') : $question.next('.wpss-faq-answer');
			const isExpanded = $question.attr('aria-expanded') === 'true';

			// Toggle aria-expanded state.
			$question.attr('aria-expanded', !isExpanded);

			// Toggle visibility with animation.
			if (isExpanded) {
				$answer.slideUp(200, function() {
					$(this).prop('hidden', true);
				});
			} else {
				$answer.prop('hidden', false).hide().slideDown(200);
			}
		});
	};

	/**
	 * Order Messages.
	 */
	WPSS.initMessages = function() {
		const $form = $('#wpss-message-form');

		if (!$form.length) {
			return;
		}

		$form.on('submit', function(e) {
			e.preventDefault();

			const orderId = $form.data('order');
			const message = $form.find('textarea[name="message"]').val();
			const nonce = $form.find('#wpss_message_nonce').val();

			if (!message.trim()) {
				return;
			}

			const $btn = $form.find('button[type="submit"]');
			$btn.prop('disabled', true);

			$.ajax({
				url: wpssData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_send_message',
					order_id: orderId,
					message: message,
					nonce: nonce
				},
				success: function(response) {
					if (response.success) {
						// Add message to container.
						WPSS.appendMessage(response.data);

						// Clear form.
						$form.find('textarea').val('');

						// Scroll to bottom.
						const $container = $('#wpss-messages-container');
						$container.scrollTop($container[0].scrollHeight);
					} else {
						WPSS.showNotification(response.data.message || (wpssData.i18n && wpssData.i18n.messageFailed) || 'Failed to send message.', 'error');
					}
				},
				error: function() {
					WPSS.showNotification('An error occurred. Please try again.', 'error');
				},
				complete: function() {
					$btn.prop('disabled', false);
				}
			});
		});

		// Scroll to bottom on load.
		const $container = $('#wpss-messages-container');
		if ($container.length) {
			$container.scrollTop($container[0].scrollHeight);
		}
	};

	/**
	 * Append new message to container.
	 */
	WPSS.appendMessage = function(data) {
		const $container = $('#wpss-messages-container');

		const html = `
			<div class="wpss-message wpss-message-own">
				<img src="${data.user_avatar}" alt="" class="wpss-message-avatar">
				<div class="wpss-message-content">
					<span class="wpss-message-author">${data.user_name}</span>
					<div class="wpss-message-text"><p>${WPSS.escapeHtml(data.message)}</p></div>
					<span class="wpss-message-time">${data.time_ago || ((wpssData.i18n && wpssData.i18n.justNow) || 'Just now')}</span>
				</div>
			</div>
		`;

		$container.find('.wpss-no-messages').remove();
		$container.append(html);
	};

	/**
	 * Order Actions.
	 */
	WPSS.initOrderActions = function() {
		// Status action buttons.
		$(document).on('click', '.wpss-order-action', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const action = $btn.data('action');
			const orderId = $btn.data('order');

			// Actions that require confirmation/reason.
			const reasonActions = ['reject', 'cancel', 'dispute'];

			if (reasonActions.includes(action)) {
				WPSS.showPrompt(WPSS.getActionPrompt(action), function(reason) {
					WPSS.performOrderAction(orderId, action, reason);
				}, { submitText: (wpssData.i18n && wpssData.i18n.submit) || 'Submit', placeholder: (wpssData.i18n && wpssData.i18n.enterReason) || 'Enter your reason...' });
			} else {
				WPSS.showConfirm(WPSS.getActionConfirm(action), function() {
					WPSS.performOrderAction(orderId, action);
				});
			}
		});

		// Deliver button.
		$(document).on('click', '.wpss-deliver-btn', function(e) {
			e.preventDefault();
			const orderId = $(this).data('order');
			WPSS.showDeliverModal(orderId);
		});

		// Revision button - show modal.
		$(document).on('click', '.wpss-revision-btn', function(e) {
			e.preventDefault();
			WPSS.showModal('wpss-revision-modal');
		});

		// Revision form submission.
		$(document).on('submit', '#wpss-revision-form', function(e) {
			e.preventDefault();
			const $form = $(this);
			const $btn = $form.find('button[type="submit"]');
			const originalText = $btn.html();
			const orderId = $form.find('input[name="order_id"]').val();
			const reason = $form.find('textarea[name="reason"]').val();

			if (!reason || !reason.trim()) {
				WPSS.showNotification((wpssData.i18n && wpssData.i18n.revisionRequired) || 'Please describe what changes you need.', 'error');
				return;
			}

			$btn.prop('disabled', true).html('<span class="wpss-spinner"></span> ' + ((wpssData.i18n && wpssData.i18n.submitting) || 'Submitting...'));

			WPSS.requestRevision(orderId, reason);

			// Reset form after submission (reload will happen in requestRevision).
			setTimeout(function() {
				$btn.prop('disabled', false).html(originalText);
			}, 3000);
		});
	};

	/**
	 * Perform order action via AJAX.
	 */
	WPSS.performOrderAction = function(orderId, action, reason) {
		// Map frontend actions to AJAX action names.
		const actionMap = {
			accept: 'wpss_accept_order',
			reject: 'wpss_decline_order',
			start: 'wpss_start_work',
			deliver: 'wpss_deliver_order',
			complete: 'wpss_accept_delivery',
			cancel: 'wpss_cancel_order',
			dispute: 'wpss_open_dispute',
			'accept-cancellation': 'wpss_accept_cancellation',
			'reject-cancellation': 'wpss_reject_cancellation'
		};

		const ajaxAction = actionMap[action] || 'wpss_' + action + '_order';

		$.ajax({
			url: wpssData.ajaxUrl,
			type: 'POST',
			data: {
				action: ajaxAction,
				order_id: orderId,
				reason: reason || '',
				nonce: wpssData.orderNonce || wpssData.nonce
			},
			success: function(response) {
				if (response.success) {
					// Reload page to show updated state.
					location.reload();
				} else {
					WPSS.showNotification(response.data?.message || (wpssData.i18n && wpssData.i18n.actionFailed) || 'Action failed. Please try again.', 'error');
				}
			},
			error: function(xhr) {
				WPSS.showNotification((wpssData.i18n && wpssData.i18n.error) || 'An error occurred. Please try again.', 'error');
			}
		});
	};

	/**
	 * Get action confirmation text.
	 */
	WPSS.getActionConfirm = function(action) {
		var i18n = (wpssData && wpssData.i18n) || {};
		var confirms = {
			accept: i18n.confirmAcceptOrder || 'Are you sure you want to accept this order?',
			start: i18n.confirmStartOrder || 'Are you sure you want to start working on this order?',
			deliver: i18n.confirmDeliverOrder || 'Are you sure you want to mark this order as delivered?',
			complete: i18n.confirmCompleteOrder || 'Are you sure you want to mark this order as complete?',
			'accept-cancellation': i18n.confirmAcceptCancellation || 'Are you sure you want to accept this cancellation request? The order will be cancelled.',
			'reject-cancellation': i18n.confirmRejectCancellation || 'Are you sure you want to dispute this cancellation? The order will be escalated for admin review.'
		};

		return confirms[action] || (i18n.confirmTitle || 'Are you sure?');
	};

	/**
	 * Get action prompt text.
	 */
	WPSS.getActionPrompt = function(action) {
		var i18n = (wpssData && wpssData.i18n) || {};
		var prompts = {
			reject: i18n.promptReject || 'Please provide a reason for declining:',
			cancel: i18n.promptCancel || 'Please provide a reason for cancellation:',
			dispute: i18n.promptDispute || 'Please describe your issue:'
		};

		return prompts[action] || (i18n.promptDefault || 'Please provide details:');
	};

	/**
	 * Reviews.
	 */
	WPSS.initReviews = function() {
		// Load more reviews.
		$(document).on('click', '.wpss-load-more-reviews', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const serviceId = $btn.data('service');
			const vendorId = $btn.data('vendor');
			const page = parseInt($btn.data('page')) || 2;

			$btn.prop('disabled', true).text((wpssData.i18n && wpssData.i18n.loading) || 'Loading...');

			let endpoint = 'reviews?';
			if (serviceId) {
				endpoint += 'service_id=' + serviceId;
			} else if (vendorId) {
				endpoint += 'vendor_id=' + vendorId;
			}
			endpoint += '&page=' + page + '&per_page=10';

			$.ajax({
				url: wpssData.apiUrl + endpoint,
				type: 'GET',
				success: function(response) {
					if (response && response.length) {
						const $list = $('.wpss-reviews-list');

						response.forEach(function(review) {
							$list.append(WPSS.renderReview(review));
						});

						if (response.length < 10) {
							$btn.hide();
						} else {
							$btn.data('page', page + 1).text((wpssData.i18n && wpssData.i18n.loadMoreReviews) || 'Load More Reviews');
						}
					} else {
						$btn.hide();
					}
				},
				error: function() {
					WPSS.showNotification((wpssData.i18n && wpssData.i18n.reviewsFailed) || 'Failed to load reviews.', 'error');
				},
				complete: function() {
					$btn.prop('disabled', false);
				}
			});
		});

		// Write review button.
		$(document).on('click', '.wpss-write-review-btn', function(e) {
			e.preventDefault();
			const orderId = $(this).data('order');
			WPSS.showReviewModal(orderId);
		});
	};

	/**
	 * Render review HTML.
	 */
	WPSS.renderReview = function(review) {
		let starsHtml = '';
		for (let i = 1; i <= 5; i++) {
			starsHtml += `<span class="wpss-star ${i <= review.rating ? 'filled' : ''}">★</span>`;
		}

		let replyHtml = '';
		if (review.vendor_reply) {
			replyHtml = `
				<div class="wpss-review-reply">
					<div class="wpss-reply-header">
						<strong>${WPSS.escapeHtml((wpssData.i18n && wpssData.i18n.sellerResponse) || 'Seller Response:')}</strong>
					</div>
					<p>${WPSS.escapeHtml(review.vendor_reply)}</p>
				</div>
			`;
		}

		return `
			<div class="wpss-review">
				<div class="wpss-review-header">
					<img src="${review.customer_avatar}" alt="" class="wpss-review-avatar">
					<div class="wpss-review-info">
						<strong class="wpss-review-author">${WPSS.escapeHtml(review.customer_name)}</strong>
						<div class="wpss-review-rating">${starsHtml}</div>
					</div>
					<span class="wpss-review-date">${review.time_ago || review.created_at || ((wpssData.i18n && wpssData.i18n.justNow) || 'Just now')}</span>
				</div>
				<div class="wpss-review-content">
					<p>${WPSS.escapeHtml(review.review)}</p>
				</div>
				${replyHtml}
			</div>
		`;
	};

	/**
	 * Checkout process.
	 */
	WPSS.checkout = function(options) {
		// This will be overridden by the active e-commerce adapter.
		if (typeof wpssData.checkoutUrl !== 'undefined') {
			// Redirect to checkout with parameters.
			const url = new URL(wpssData.checkoutUrl);
			url.searchParams.set('service', options.serviceId);
			url.searchParams.set('package', options.packageIndex);

			window.location.href = url.toString();
		} else {
			// Default: Add to cart via AJAX.
			$.ajax({
				url: wpssData.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_add_service_to_cart',
					service_id: options.serviceId,
					package_id: options.packageIndex,
					nonce: wpssData.nonce
				},
				success: function(response) {
					if (response.success) {
						if (response.data.cart_count !== undefined) {
							WPSS.updateMiniCart(response.data.cart_count);
						}
						if (response.data.redirect) {
							window.location.href = response.data.redirect;
						} else {
							WPSS.showNotification((wpssData.i18n && wpssData.i18n.addedToCart) || 'Added to cart!', 'success');
						}
					} else {
						WPSS.showNotification(response.data.message || (wpssData.i18n && wpssData.i18n.cartFailed) || 'Failed to add to cart.', 'error');
					}
				},
				error: function() {
					WPSS.showNotification((wpssData.i18n && wpssData.i18n.error) || 'An error occurred. Please try again.', 'error');
				}
			});
		}
	};

	/**
	 * Show deliver modal.
	 */
	WPSS.showDeliverModal = function(orderId) {
		const $modal = $('#wpss-deliver-modal');

		if ($modal.length) {
			// Use the proper modal
			$modal.data('order', orderId);
			$modal.find('input[name="order_id"]').val(orderId);
			WPSS.showModal('wpss-deliver-modal');
		} else {
			// Fallback for when modal doesn't exist
			WPSS.showPrompt((wpssData.i18n && wpssData.i18n.describeDelivery) || 'Describe your delivery:', function(message) {
				WPSS.submitDelivery(orderId, message, null);
			}, { placeholder: (wpssData.i18n && wpssData.i18n.deliveryPlaceholder) || 'Describe what you are delivering...' });
		}
	};

	/**
	 * Submit delivery via AJAX with file uploads.
	 */
	WPSS.submitDelivery = function(orderId, message, fileInput) {
		var formData = new FormData();
		formData.append('action', 'wpss_deliver_order');
		formData.append('order_id', orderId);
		formData.append('message', message);
		formData.append('nonce', wpssData.orderNonce || wpssData.nonce);

		// Add files from file input element.
		if (fileInput && fileInput.files) {
			for (var i = 0; i < fileInput.files.length; i++) {
				formData.append('files[]', fileInput.files[i]);
			}
		}

		$.ajax({
			url: wpssData.ajaxUrl,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					WPSS.showNotification(response.data?.message || (wpssData.i18n && wpssData.i18n.deliverySubmitted) || 'Delivery submitted successfully!', 'success');
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					WPSS.showNotification(response.data?.message || (wpssData.i18n && wpssData.i18n.deliveryFailed) || 'Failed to submit delivery.', 'error');
				}
			},
			error: function() {
				WPSS.showNotification((wpssData.i18n && wpssData.i18n.error) || 'An error occurred. Please try again.', 'error');
			}
		});
	};

	/**
	 * Show modal utility with focus trap.
	 */
	WPSS.showModal = function(modalId) {
		const $modal = $('#' + modalId);
		if ($modal.length) {
			WPSS._lastFocused = document.activeElement;
			$modal.addClass('wpss-modal-open');
			$('body').addClass('wpss-modal-active');

			// Focus first focusable element.
			var $focusable = $modal.find('button, [href], input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
			if ($focusable.length) {
				$focusable.first().focus();
			}

			// Focus trap.
			$modal.off('keydown.wpss-trap').on('keydown.wpss-trap', function(e) {
				if (e.key !== 'Tab') return;
				var $focusables = $modal.find('button, [href], input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])').filter(':visible');
				var $first = $focusables.first();
				var $last = $focusables.last();
				if (e.shiftKey && document.activeElement === $first[0]) {
					e.preventDefault();
					$last.focus();
				} else if (!e.shiftKey && document.activeElement === $last[0]) {
					e.preventDefault();
					$first.focus();
				}
			});
		}
	};

	/**
	 * Hide modal utility and restore focus.
	 */
	WPSS.hideModal = function(modalId) {
		const $modal = modalId ? $('#' + modalId) : $('.wpss-modal.wpss-modal-open');
		$modal.removeClass('wpss-modal-open').off('keydown.wpss-trap');
		$('body').removeClass('wpss-modal-active');

		// Restore focus.
		if (WPSS._lastFocused) {
			WPSS._lastFocused.focus();
			WPSS._lastFocused = null;
		}
	};

	/**
	 * Show inline error notice in a container.
	 *
	 * @param {jQuery|string} container Selector or jQuery object.
	 * @param {string}        message   Error message.
	 * @param {string}        type      Notice type: error, success, info.
	 */
	WPSS.showNotice = function(container, message, type) {
		type = type || 'error';
		var $container = $(container);
		$container.find('.wpss-notice').remove();
		$container.prepend(
			'<div class="wpss-notice wpss-notice--' + type + '" role="alert">' +
				'<span>' + $('<span>').text(message).html() + '</span>' +
			'</div>'
		);
	};

	/**
	 * Set button to loading state.
	 */
	WPSS.setButtonLoading = function($btn, loading) {
		if (loading) {
			$btn.data('original-text', $btn.html());
			$btn.prop('disabled', true).addClass('wpss-btn--loading')
				.html('<span class="wpss-spinner"></span> ' + (wpssData.i18n?.processing || 'Processing...'));
		} else {
			$btn.prop('disabled', false).removeClass('wpss-btn--loading')
				.html($btn.data('original-text'));
		}
	};

	/**
	 * Initialize modal handlers.
	 */
	WPSS.initModals = function() {
		// Close modal on backdrop click.
		$(document).on('click', '.wpss-modal-backdrop, .wpss-modal__backdrop', function() {
			WPSS.hideModal();
		});

		// Close modal on close button click (support both naming conventions).
		$(document).on('click', '.wpss-modal-close, .wpss-modal-close-btn, .wpss-modal__close, .wpss-modal__close-btn', function() {
			WPSS.hideModal();
		});

		// Close modal on escape key.
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape') {
				WPSS.hideModal();
			}
		});

		// Review form submission.
		$(document).on('submit', '#wpss-review-form', function(e) {
			e.preventDefault();
			WPSS.submitReview($(this));
		});

		// Dispute form submission.
		$(document).on('submit', '#wpss-dispute-form', function(e) {
			e.preventDefault();
			WPSS.submitDispute($(this));
		});

		// Dispute button click.
		$(document).on('click', '.wpss-dispute-btn', function(e) {
			e.preventDefault();
			WPSS.showModal('wpss-dispute-modal');
		});

		// Delivery form submission.
		$(document).on('submit', '#wpss-deliver-form', function(e) {
			e.preventDefault();
			const $form = $(this);
			const $btn = $form.find('button[type="submit"]');
			const originalText = $btn.html();

			// Disable button
			$btn.prop('disabled', true).html('<span class="wpss-spinner"></span> ' + ((wpssData.i18n && wpssData.i18n.submitting) || 'Submitting...'));

			const orderId = $form.find('input[name="order_id"]').val();
			const message = $form.find('#deliver-message').val();
			const fileInput = $form.find('#deliver-files')[0];

			if (!message || !message.trim()) {
				WPSS.showNotification((wpssData.i18n && wpssData.i18n.deliveryRequired) || 'Please provide a delivery message.', 'error');
				$btn.prop('disabled', false).html(originalText);
				return;
			}

			WPSS.submitDelivery(orderId, message, fileInput);

			// Re-enable button after a delay (in case page doesn't reload)
			setTimeout(function() {
				$btn.prop('disabled', false).html(originalText);
			}, 5000);
		});

		// File input preview for delivery modal.
		$(document).on('change', '#deliver-files', function() {
			const files = this.files;
			const $list = $('#deliver-file-list');
			$list.empty();

			if (files.length > 0) {
				for (let i = 0; i < files.length; i++) {
					const file = files[i];
					const size = (file.size / 1024 / 1024).toFixed(2);
					$list.append('<div class="wpss-file-item"><span>' + file.name + '</span><small>(' + size + ' MB)</small></div>');
				}
			}
		});
	};

	/**
	 * Contact Vendor (vendor profile and other non-single-service pages).
	 *
	 * On single service pages, single-service.js handles contact via WPSSService.
	 * This handler covers the vendor profile page and any other page with a contact modal.
	 */
	WPSS.initContactVendor = function() {
		// Skip if single-service.js is active (it has its own handler).
		if (typeof window.WPSSService !== 'undefined') {
			return;
		}

		var $modal = $('#wpss-contact-modal');

		if (!$modal.length) {
			return;
		}

		// Open contact modal on button click.
		$(document).on('click', '.wpss-contact-btn', function(e) {
			e.preventDefault();
			$modal.prop('hidden', false).addClass('wpss-modal-open');
			$('body').addClass('wpss-modal-active');
			$modal.find('textarea').focus();
		});

		// Close modal on overlay click.
		$modal.on('click', '.wpss-modal-overlay', function() {
			$modal.prop('hidden', true).removeClass('wpss-modal-open');
			$('body').removeClass('wpss-modal-active');
		});

		// Close modal on close button.
		$modal.on('click', '.wpss-modal-close', function(e) {
			e.preventDefault();
			$modal.prop('hidden', true).removeClass('wpss-modal-open');
			$('body').removeClass('wpss-modal-active');
		});

		// Close on Escape key.
		$(document).on('keydown', function(e) {
			if (e.key === 'Escape' && $modal.hasClass('wpss-modal-open')) {
				$modal.prop('hidden', true).removeClass('wpss-modal-open');
				$('body').removeClass('wpss-modal-active');
			}
		});

		// Contact form submission.
		$modal.on('submit', '#wpss-contact-form', function(e) {
			e.preventDefault();

			var $form = $(this);
			var $btn = $form.find('button[type="submit"]');
			var btnText = $btn.text();

			$btn.prop('disabled', true).text(wpssData.i18n?.submitting || 'Sending...');

			var formData = new FormData($form[0]);
			formData.append('action', 'wpss_contact_vendor');
			formData.append('nonce', wpssData.contactNonce);

			$.ajax({
				url: wpssData.ajaxUrl,
				type: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function(response) {
					if (response.success) {
						$form.html(
							'<div class="wpss-success-message">' +
							'<span class="wpss-success-icon">&#10003;</span>' +
							'<p>' + WPSS.escapeHtml(response.data.message) + '</p>' +
							'</div>'
						);

						setTimeout(function() {
							$modal.prop('hidden', true).removeClass('wpss-modal-open');
							$('body').removeClass('wpss-modal-active');
						}, 2000);
					} else {
						WPSS.showNotification(response.data.message || wpssData.i18n?.error || 'Failed to send message.', 'error');
						$btn.prop('disabled', false).text(btnText);
					}
				},
				error: function() {
					WPSS.showNotification(wpssData.i18n?.ajaxError || 'An error occurred. Please try again.', 'error');
					$btn.prop('disabled', false).text(btnText);
				}
			});
		});
	};

	/**
	 * Show review modal.
	 */
	WPSS.showReviewModal = function(orderId) {
		WPSS.showModal('wpss-review-modal');
	};

	/**
	 * Submit review via AJAX.
	 */
	WPSS.submitReview = function($form) {
		const $btn = $form.find('button[type="submit"]');
		const btnText = $btn.text();

		$btn.prop('disabled', true).text((wpssData.i18n && wpssData.i18n.submitting) || 'Submitting...');

		$.ajax({
			url: wpssData.ajaxUrl,
			type: 'POST',
			data: $form.serialize() + '&action=wpss_submit_review',
			success: function(response) {
				if (response.success) {
					WPSS.hideModal();
					WPSS.showNotification(response.data.message || (wpssData.i18n && wpssData.i18n.reviewSubmitted) || 'Review submitted successfully!', 'success');
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					WPSS.showNotification(response.data.message || (wpssData.i18n && wpssData.i18n.reviewFailed) || 'Failed to submit review.', 'error');
				}
			},
			error: function() {
				WPSS.showNotification((wpssData.i18n && wpssData.i18n.error) || 'An error occurred. Please try again.', 'error');
			},
			complete: function() {
				$btn.prop('disabled', false).text(btnText);
			}
		});
	};

	/**
	 * Submit dispute via AJAX.
	 */
	WPSS.submitDispute = function($form) {
		const $btn = $form.find('button[type="submit"]');
		const btnText = $btn.text();

		$btn.prop('disabled', true).text((wpssData.i18n && wpssData.i18n.submitting) || 'Submitting...');

		$.ajax({
			url: wpssData.ajaxUrl,
			type: 'POST',
			data: $form.serialize() + '&action=wpss_open_dispute',
			success: function(response) {
				if (response.success) {
					WPSS.hideModal();
					WPSS.showNotification(response.data.message || (wpssData.i18n && wpssData.i18n.disputeOpened) || 'Dispute opened successfully. Our team will review your case.', 'success');
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					WPSS.showNotification(response.data.message || (wpssData.i18n && wpssData.i18n.disputeFailed) || 'Failed to open dispute.', 'error');
				}
			},
			error: function() {
				WPSS.showNotification((wpssData.i18n && wpssData.i18n.error) || 'An error occurred. Please try again.', 'error');
			},
			complete: function() {
				$btn.prop('disabled', false).text(btnText);
			}
		});
	};

	/**
	 * Request revision.
	 */
	WPSS.requestRevision = function(orderId, reason) {
		$.ajax({
			url: wpssData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpss_request_revision',
				order_id: orderId,
				reason: reason,
				nonce: wpssData.orderNonce || wpssData.nonce
			},
			success: function(response) {
				if (response.success) {
					WPSS.hideModal();
					WPSS.showNotification(response.data.message || (wpssData.i18n && wpssData.i18n.revisionSubmitted) || 'Revision requested successfully!', 'success');
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					WPSS.showNotification(response.data.message || (wpssData.i18n && wpssData.i18n.revisionFailed) || 'Failed to request revision.', 'error');
				}
			},
			error: function() {
				WPSS.showNotification((wpssData.i18n && wpssData.i18n.error) || 'An error occurred. Please try again.', 'error');
			}
		});
	};

	/**
	 * Initialize filter sidebar toggle.
	 */
	WPSS.initFilterSidebar = function() {
		const $toggle = $('.wpss-filter-toggle');
		const $sidebar = $('#wpss-sidebar');
		const $close = $('.wpss-sidebar-close');

		// Handle URL select dropdowns (category/sort filters) - replaces inline onchange.
		$(document).on('change', '.wpss-url-select', function() {
			const url = $(this).val();
			if (url) {
				window.location.href = url;
			}
		});

		if (!$toggle.length || !$sidebar.length) {
			return;
		}

		$toggle.on('click', function() {
			$sidebar.addClass('is-open');
			$('body').addClass('wpss-sidebar-open');
		});

		$close.on('click', function() {
			$sidebar.removeClass('is-open');
			$('body').removeClass('wpss-sidebar-open');
		});

		// Close on backdrop click.
		$(document).on('click', function(e) {
			if ($sidebar.hasClass('is-open') && !$(e.target).closest('#wpss-sidebar, .wpss-filter-toggle').length) {
				$sidebar.removeClass('is-open');
				$('body').removeClass('wpss-sidebar-open');
			}
		});
	};

	/**
	 * Initialize proposal handlers.
	 */
	WPSS.initProposals = function() {
		// Submit proposal button - open modal.
		$(document).on('click', '.wpss-submit-proposal-btn', function(e) {
			e.preventDefault();
			WPSS.showModal('wpss-proposal-modal');
		});

		// Proposal form submission.
		$(document).on('submit', '#wpss-proposal-form', function(e) {
			e.preventDefault();
			WPSS.submitProposal($(this));
		});

		// Accept proposal.
		$(document).on('click', '.wpss-accept-proposal', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const proposalId = $btn.data('proposal-id') || $btn.data('proposal');

			WPSS.showConfirm(
				wpssData.i18n?.confirmAcceptProposal || 'Accept this proposal and create an order?',
				function() { WPSS.handleProposalAction($btn, proposalId, 'accept'); },
				{ confirmText: 'Accept' }
			);
		});

		// Reject proposal.
		$(document).on('click', '.wpss-reject-proposal', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const proposalId = $btn.data('proposal-id') || $btn.data('proposal');
			WPSS.showPrompt(
				wpssData.i18n?.rejectProposalReason || 'Please provide a reason for rejection (optional):',
				function(reason) { WPSS.handleProposalAction($btn, proposalId, 'reject', reason); },
				{ required: false, submitText: 'Decline', placeholder: 'Reason (optional)...' }
			);
		});

		// Withdraw proposal (vendor).
		$(document).on('click', '.wpss-withdraw-proposal', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const proposalId = $btn.data('proposal-id') || $btn.data('proposal');

			WPSS.showConfirm(
				wpssData.i18n?.confirmWithdrawProposal || 'Withdraw this proposal?',
				function() { WPSS.handleProposalAction($btn, proposalId, 'withdraw'); },
				{ confirmText: 'Withdraw' }
			);
		});
	};

	/**
	 * Submit proposal via AJAX.
	 */
	WPSS.submitProposal = function($form) {
		const $btn = $form.find('button[type="submit"]');
		const btnText = $btn.text();

		// Validate fields.
		const description = $form.find('[name="description"]').val();
		const price = parseFloat($form.find('[name="price"]').val()) || 0;
		const deliveryDays = parseInt($form.find('[name="delivery_days"]').val()) || 0;

		if (!description || !description.trim()) {
			WPSS.showNotification(wpssData.i18n?.proposalDescriptionRequired || 'Please provide a proposal description.', 'warning');
			return;
		}

		if (price <= 0) {
			WPSS.showNotification(wpssData.i18n?.proposalPriceRequired || 'Please enter a valid price.', 'warning');
			return;
		}

		if (deliveryDays <= 0) {
			WPSS.showNotification(wpssData.i18n?.proposalDeliveryRequired || 'Please enter delivery time in days.', 'warning');
			return;
		}

		$btn.prop('disabled', true).text(wpssData.i18n?.submitting || 'Submitting...');

		$.ajax({
			url: wpssData.ajaxUrl,
			type: 'POST',
			data: $form.serialize() + '&action=wpss_submit_proposal',
			success: function(response) {
				if (response.success) {
					WPSS.hideModal('wpss-proposal-modal');
					WPSS.showNotification(response.data.message || wpssData.i18n?.proposalSubmitted || 'Proposal submitted successfully!', 'success');
					location.reload();
				} else {
					WPSS.showNotification(response.data.message || wpssData.i18n?.proposalFailed || 'Failed to submit proposal.', 'error');
				}
			},
			error: function() {
				WPSS.showNotification(wpssData.i18n?.ajaxError || 'An error occurred. Please try again.', 'error');
			},
			complete: function() {
				$btn.prop('disabled', false).text(btnText);
			}
		});
	};

	/**
	 * Handle proposal action (accept/reject/withdraw).
	 */
	WPSS.handleProposalAction = function($btn, proposalId, action, reason) {
		const btnText = $btn.text();
		$btn.prop('disabled', true).text(wpssData.i18n?.processing || 'Processing...');

		const data = {
			action: 'wpss_' + action + '_proposal',
			proposal_id: proposalId,
			nonce: wpssData.nonce
		};

		if (reason) {
			data.reason = reason;
		}

		$.ajax({
			url: wpssData.ajaxUrl,
			type: 'POST',
			data: data,
			success: function(response) {
				if (response.success) {
					if (response.data.redirect) {
						window.location.href = response.data.redirect;
					} else {
						location.reload();
					}
				} else {
					WPSS.showNotification(response.data.message || (wpssData.i18n && wpssData.i18n.actionFailed) || 'Action failed.', 'error');
					$btn.prop('disabled', false).text(btnText);
				}
			},
			error: function() {
				WPSS.showNotification(wpssData.i18n?.ajaxError || 'An error occurred. Please try again.', 'error');
				$btn.prop('disabled', false).text(btnText);
			}
		});
	};

	/**
	 * Escape HTML entities.
	 */
	WPSS.escapeHtml = function(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	};

	/**
	 * Show toast notification.
	 *
	 * @param {string} message - The message to display.
	 * @param {string} type    - The type: 'success', 'error', 'warning', 'info'.
	 * @param {number} duration - How long to show (ms). Default 4000.
	 */
	WPSS.showNotification = function(message, type, duration) {
		type = type || 'info';
		duration = duration || 4000;

		// Create notification container if it doesn't exist.
		let $container = $('#wpss-notification-container');
		if (!$container.length) {
			$container = $('<div id="wpss-notification-container"></div>');
			$('body').append($container);
		}

		// Icon based on type.
		const icons = {
			success: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
			error: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
			warning: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
			info: '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>'
		};

		// Create notification element.
		const $notification = $(`
			<div class="wpss-notification wpss-notification--${type}">
				<span class="wpss-notification__icon">${icons[type] || icons.info}</span>
				<span class="wpss-notification__message">${WPSS.escapeHtml(message)}</span>
				<button type="button" class="wpss-notification__close">&times;</button>
			</div>
		`);

		// Add to container.
		$container.append($notification);

		// Trigger animation.
		setTimeout(function() {
			$notification.addClass('wpss-notification--visible');
		}, 10);

		// Close button.
		$notification.find('.wpss-notification__close').on('click', function() {
			$notification.removeClass('wpss-notification--visible');
			setTimeout(function() {
				$notification.remove();
			}, 300);
		});

		// Auto-remove after duration.
		setTimeout(function() {
			$notification.removeClass('wpss-notification--visible');
			setTimeout(function() {
				$notification.remove();
			}, 300);
		}, duration);
	};

	/**
	 * Show confirm dialog (replaces browser confirm()).
	 *
	 * @param {string}   message   - The confirmation message.
	 * @param {Function} onConfirm - Callback when confirmed.
	 * @param {Object}   options   - Optional: title, confirmText, cancelText.
	 */
	WPSS.showConfirm = function(message, onConfirm, options) {
		options = options || {};
		var i18n = (wpssData && wpssData.i18n) || {};
		var confirmText = options.confirmText || i18n.confirm || 'Confirm';
		var cancelText = options.cancelText || i18n.cancel || 'Cancel';

		$('#wpss-confirm-modal').remove();

		var $modal = $('<div id="wpss-confirm-modal" class="wpss-modal wpss-modal-open">' +
			'<div class="wpss-modal__backdrop"></div>' +
			'<div class="wpss-modal__dialog wpss-modal__dialog--sm">' +
				'<div class="wpss-modal__content">' +
					'<div class="wpss-modal__body" style="padding:24px;text-align:center;">' +
						'<p style="margin:0 0 20px;font-size:15px;">' + WPSS.escapeHtml(message) + '</p>' +
						'<div style="display:flex;gap:10px;justify-content:center;">' +
							'<button type="button" class="wpss-btn wpss-btn--outline wpss-confirm-cancel">' + WPSS.escapeHtml(cancelText) + '</button>' +
							'<button type="button" class="wpss-btn wpss-btn--primary wpss-confirm-ok">' + WPSS.escapeHtml(confirmText) + '</button>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>' +
		'</div>');

		$('body').append($modal).addClass('wpss-modal-active');

		$modal.find('.wpss-confirm-ok').on('click', function() {
			$modal.remove();
			$('body').removeClass('wpss-modal-active');
			if (onConfirm) onConfirm();
		});

		$modal.find('.wpss-confirm-cancel, .wpss-modal__backdrop').on('click', function() {
			$modal.remove();
			$('body').removeClass('wpss-modal-active');
		});
	};

	/**
	 * Show prompt dialog (replaces browser prompt()).
	 *
	 * @param {string}   message  - The prompt message.
	 * @param {Function} onSubmit - Callback with the entered value.
	 * @param {Object}   options  - Optional: placeholder, submitText, cancelText, required.
	 */
	WPSS.showPrompt = function(message, onSubmit, options) {
		options = options || {};
		var i18n = (wpssData && wpssData.i18n) || {};
		var placeholder = options.placeholder || '';
		var submitText = options.submitText || i18n.submit || 'Submit';
		var cancelText = options.cancelText || i18n.cancel || 'Cancel';
		var required = options.required !== false;

		$('#wpss-prompt-modal').remove();

		var $modal = $('<div id="wpss-prompt-modal" class="wpss-modal wpss-modal-open">' +
			'<div class="wpss-modal__backdrop"></div>' +
			'<div class="wpss-modal__dialog wpss-modal__dialog--sm">' +
				'<div class="wpss-modal__content">' +
					'<div class="wpss-modal__body" style="padding:24px;">' +
						'<p style="margin:0 0 12px;font-size:15px;">' + WPSS.escapeHtml(message) + '</p>' +
						'<textarea class="wpss-prompt-input" rows="3" placeholder="' + WPSS.escapeHtml(placeholder) + '" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:6px;resize:vertical;font-size:14px;"></textarea>' +
						'<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">' +
							'<button type="button" class="wpss-btn wpss-btn--outline wpss-prompt-cancel">' + WPSS.escapeHtml(cancelText) + '</button>' +
							'<button type="button" class="wpss-btn wpss-btn--primary wpss-prompt-submit">' + WPSS.escapeHtml(submitText) + '</button>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>' +
		'</div>');

		$('body').append($modal).addClass('wpss-modal-active');
		$modal.find('.wpss-prompt-input').trigger('focus');

		$modal.find('.wpss-prompt-submit').on('click', function() {
			var value = $modal.find('.wpss-prompt-input').val();
			if (required && (!value || !value.trim())) {
				WPSS.showNotification((wpssData.i18n && wpssData.i18n.promptRequired) || 'Please provide a response.', 'warning');
				return;
			}
			$modal.remove();
			$('body').removeClass('wpss-modal-active');
			if (onSubmit) onSubmit(value);
		});

		$modal.find('.wpss-prompt-cancel, .wpss-modal__backdrop').on('click', function() {
			$modal.remove();
			$('body').removeClass('wpss-modal-active');
		});
	};

	/**
	 * Requirements View - Expand/Collapse and Copy to Clipboard.
	 */
	WPSS.initRequirementsView = function() {
		// Expand/Collapse toggle.
		$(document).on('click', '.wpss-requirement-view__expand-btn', function() {
			const $btn = $(this);
			const $answer = $btn.closest('.wpss-requirement-view__answer');
			const isExpanded = $btn.attr('aria-expanded') === 'true';

			if (isExpanded) {
				// Collapse.
				$answer.removeClass('wpss-requirement-view__answer--expanded')
					   .addClass('wpss-requirement-view__answer--collapsed');
				$btn.attr('aria-expanded', 'false');
				$btn.find('.wpss-expand-text').show();
				$btn.find('.wpss-collapse-text').hide();
			} else {
				// Expand.
				$answer.removeClass('wpss-requirement-view__answer--collapsed')
					   .addClass('wpss-requirement-view__answer--expanded');
				$btn.attr('aria-expanded', 'true');
				$btn.find('.wpss-expand-text').hide();
				$btn.find('.wpss-collapse-text').show();
			}
		});

		// Copy to clipboard.
		$(document).on('click', '.wpss-requirement-view__copy-btn', function() {
			const $btn = $(this);
			const text = $btn.data('copy-text');

			if (!text) {
				return;
			}

			// Use modern clipboard API if available.
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(function() {
					WPSS.showCopySuccess($btn);
				}).catch(function() {
					WPSS.fallbackCopy(text, $btn);
				});
			} else {
				WPSS.fallbackCopy(text, $btn);
			}
		});

		// Image preview lightbox (simple).
		$(document).on('click', '.wpss-requirement-view__thumbnail', function() {
			const src = $(this).attr('src');
			const alt = $(this).attr('alt') || '';

			// Create lightbox.
			const $lightbox = $(`
				<div class="wpss-lightbox">
					<div class="wpss-lightbox__backdrop"></div>
					<div class="wpss-lightbox__content">
						<button type="button" class="wpss-lightbox__close">&times;</button>
						<img src="${src}" alt="${WPSS.escapeHtml(alt)}">
					</div>
				</div>
			`);

			$('body').append($lightbox);

			// Animate in.
			setTimeout(function() {
				$lightbox.addClass('wpss-lightbox--visible');
			}, 10);

			// Close handlers.
			$lightbox.on('click', '.wpss-lightbox__backdrop, .wpss-lightbox__close', function() {
				$lightbox.removeClass('wpss-lightbox--visible');
				setTimeout(function() {
					$lightbox.remove();
				}, 300);
			});

			// Close on escape.
			$(document).on('keydown.lightbox', function(e) {
				if (e.key === 'Escape') {
					$lightbox.removeClass('wpss-lightbox--visible');
					setTimeout(function() {
						$lightbox.remove();
					}, 300);
					$(document).off('keydown.lightbox');
				}
			});
		});
	};

	/**
	 * Show copy success feedback.
	 */
	WPSS.showCopySuccess = function($btn) {
		$btn.addClass('copied');

		// Change icon to checkmark temporarily.
		const originalHtml = $btn.html();
		$btn.html('<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>');

		setTimeout(function() {
			$btn.removeClass('copied').html(originalHtml);
		}, 2000);
	};

	/**
	 * Fallback copy method for older browsers.
	 */
	WPSS.fallbackCopy = function(text, $btn) {
		const textarea = document.createElement('textarea');
		textarea.value = text;
		textarea.style.position = 'fixed';
		textarea.style.left = '-9999px';
		document.body.appendChild(textarea);
		textarea.select();

		try {
			document.execCommand('copy');
			WPSS.showCopySuccess($btn);
		} catch (err) {
			console.error('Copy failed:', err);
		}

		document.body.removeChild(textarea);
	};

	/**
	 * Vendor Registration Form.
	 */
	WPSS.initVendorRegistration = function() {
		const $form = $('#wpss-vendor-registration-form');

		if (!$form.length) {
			return;
		}

		$form.on('submit', function(e) {
			e.preventDefault();

			const $btn = $form.find('button[type="submit"]');
			const originalText = $btn.text();

			// Disable button and show loading.
			$btn.prop('disabled', true).text(wpssData.i18n?.submitting || 'Submitting...');

			$.ajax({
				url: wpssData.ajaxUrl,
				type: 'POST',
				data: $form.serialize() + '&action=wpss_vendor_registration',
				success: function(response) {
					if (response.success) {
						// Show success message.
						WPSS.showNotification(response.data.message || (wpssData.i18n && wpssData.i18n.vendorRegistered) || 'Application submitted successfully!', 'success');

						// Redirect if provided.
						if (response.data.redirect) {
							setTimeout(function() {
								window.location.href = response.data.redirect;
							}, 1500);
						} else {
							// Re-enable button.
							$btn.prop('disabled', false).text(originalText);
						}
					} else {
						WPSS.showNotification(response.data.message || wpssData.i18n?.error || 'An error occurred.', 'error');
						$btn.prop('disabled', false).text(originalText);
					}
				},
				error: function() {
					WPSS.showNotification(wpssData.i18n?.error || 'An error occurred. Please try again.', 'error');
					$btn.prop('disabled', false).text(originalText);
				}
			});
		});
	};

	/**
	 * Portfolio Services Options.
	 */
	WPSS.portfolioServicesOptions = function() {
		const select = document.querySelector('#portfolio-service');
		const canvas = WPSS.textMeasureCanvas || document.createElement('canvas');
		const context = canvas.getContext('2d');
		const ellipsis = '...';

		WPSS.textMeasureCanvas = canvas;

		if (!select || !context) {
			return;
		}

		const getTruncatedText = function(text, maxWidth) {
			if (context.measureText(text).width <= maxWidth) {
				return text;
			}

			let low = 0;
			let high = text.length;

			while (low < high) {
				const mid = Math.ceil((low + high) / 2);
				const trial = text.slice(0, mid) + ellipsis;

				if (context.measureText(trial).width <= maxWidth) {
					low = mid;
				} else {
					high = mid - 1;
				}
			}

			return text.slice(0, low) + ellipsis;
		};

		const updateOptionLabels = function() {
			const computedStyle = window.getComputedStyle(select);
			const selectWidth = Math.max(select.offsetWidth, Math.round(select.getBoundingClientRect().width));
			const availableWidth = Math.max(
				80,
				selectWidth -
				parseFloat(computedStyle.paddingLeft) -
				parseFloat(computedStyle.paddingRight) -
				28
			);

			context.font = computedStyle.font;

			Array.from(select.options).forEach(option => {
				const originalText = option.dataset.fulltext || option.textContent.trim();

				option.dataset.fulltext = originalText;
				option.title = originalText;
				option.textContent = getTruncatedText(originalText, availableWidth);
			});

			select.title = select.selectedOptions[0]?.dataset.fulltext || '';
		};

		updateOptionLabels();
		window.requestAnimationFrame(updateOptionLabels);
		window.setTimeout(updateOptionLabels, 150);

		if (select.dataset.wpssOptionsBound === 'true') {
			return;
		}

		select.dataset.wpssOptionsBound = 'true';
		['change', 'focus', 'mousedown'].forEach(eventName => {
			select.addEventListener(eventName, updateOptionLabels);
		});
		select.addEventListener('touchstart', updateOptionLabels, { passive: true });
		window.addEventListener('resize', updateOptionLabels);
	};

	// Initialize on DOM ready.
	$(document).ready(function() {
		WPSS.init();
	});

})(jQuery);
