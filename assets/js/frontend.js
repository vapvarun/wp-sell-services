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
	 */
	WPSS.initFAQs = function() {
		const $faqs = $('.wpss-service-faqs');

		if (!$faqs.length) {
			return;
		}

		$faqs.on('click', '.wpss-faq-question', function() {
			const $question = $(this);
			const $answer = $question.next('.wpss-faq-answer');
			const isExpanded = $question.attr('aria-expanded') === 'true';

			// Toggle state.
			$question.attr('aria-expanded', !isExpanded);
			$answer.prop('hidden', isExpanded);
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
						alert(response.data.message || 'Failed to send message.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
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
					<span class="wpss-message-time">${data.time_ago || 'Just now'}</span>
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
				const reason = prompt(WPSS.getActionPrompt(action));

				if (reason === null) {
					return; // Cancelled.
				}

				if (!reason.trim()) {
					alert('Please provide a reason.');
					return;
				}

				WPSS.performOrderAction(orderId, action, reason);
			} else {
				if (confirm(WPSS.getActionConfirm(action))) {
					WPSS.performOrderAction(orderId, action);
				}
			}
		});

		// Deliver button.
		$(document).on('click', '.wpss-deliver-btn', function(e) {
			e.preventDefault();
			const orderId = $(this).data('order');
			WPSS.showDeliverModal(orderId);
		});

		// Revision button.
		$(document).on('click', '.wpss-revision-btn', function(e) {
			e.preventDefault();
			const orderId = $(this).data('order');

			const reason = prompt('What changes would you like?');
			if (reason && reason.trim()) {
				WPSS.requestRevision(orderId, reason);
			}
		});
	};

	/**
	 * Perform order action via AJAX.
	 */
	WPSS.performOrderAction = function(orderId, action, reason) {
		$.ajax({
			url: wpssData.apiUrl + 'orders/' + orderId + '/' + action,
			type: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', wpssData.nonce);
			},
			data: {
				reason: reason || ''
			},
			success: function(response) {
				// Reload page to show updated state.
				location.reload();
			},
			error: function(xhr) {
				const response = xhr.responseJSON || {};
				alert(response.message || 'Action failed. Please try again.');
			}
		});
	};

	/**
	 * Get action confirmation text.
	 */
	WPSS.getActionConfirm = function(action) {
		const confirms = {
			accept: 'Are you sure you want to accept this order?',
			start: 'Are you sure you want to start working on this order?',
			deliver: 'Are you sure you want to mark this order as delivered?',
			complete: 'Are you sure you want to mark this order as complete?'
		};

		return confirms[action] || 'Are you sure?';
	};

	/**
	 * Get action prompt text.
	 */
	WPSS.getActionPrompt = function(action) {
		const prompts = {
			reject: 'Please provide a reason for declining:',
			cancel: 'Please provide a reason for cancellation:',
			dispute: 'Please describe your issue:'
		};

		return prompts[action] || 'Please provide details:';
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

			$btn.prop('disabled', true).text('Loading...');

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
							$btn.data('page', page + 1).text('Load More Reviews');
						}
					} else {
						$btn.hide();
					}
				},
				error: function() {
					alert('Failed to load reviews.');
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
						<strong>Seller Response:</strong>
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
					<span class="wpss-review-date">${review.time_ago || review.created_at}</span>
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
					action: 'wpss_add_to_cart',
					service_id: options.serviceId,
					package_index: options.packageIndex,
					nonce: wpssData.nonce
				},
				success: function(response) {
					if (response.success) {
						if (response.data.redirect) {
							window.location.href = response.data.redirect;
						} else {
							alert('Added to cart!');
						}
					} else {
						alert(response.data.message || 'Failed to add to cart.');
					}
				},
				error: function() {
					alert('An error occurred. Please try again.');
				}
			});
		}
	};

	/**
	 * Show deliver modal.
	 */
	WPSS.showDeliverModal = function(orderId) {
		// Simple implementation - can be enhanced with proper modal.
		const description = prompt('Describe your delivery:');

		if (!description || !description.trim()) {
			return;
		}

		$.ajax({
			url: wpssData.apiUrl + 'orders/' + orderId + '/deliverables',
			type: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', wpssData.nonce);
			},
			data: {
				description: description,
				files: []
			},
			success: function() {
				// Now mark as delivered.
				WPSS.performOrderAction(orderId, 'deliver');
			},
			error: function(xhr) {
				const response = xhr.responseJSON || {};
				alert(response.message || 'Failed to create delivery.');
			}
		});
	};

	/**
	 * Show review modal.
	 */
	WPSS.showReviewModal = function(orderId) {
		// Simple implementation.
		const rating = prompt('Rating (1-5 stars):');

		if (!rating || rating < 1 || rating > 5) {
			alert('Please enter a rating between 1 and 5.');
			return;
		}

		const review = prompt('Write your review:');

		if (!review || !review.trim()) {
			return;
		}

		$.ajax({
			url: wpssData.apiUrl + 'orders/' + orderId + '/review',
			type: 'POST',
			beforeSend: function(xhr) {
				xhr.setRequestHeader('X-WP-Nonce', wpssData.nonce);
			},
			data: {
				rating: parseInt(rating),
				review: review
			},
			success: function() {
				alert('Thank you for your review!');
				location.reload();
			},
			error: function(xhr) {
				const response = xhr.responseJSON || {};
				alert(response.message || 'Failed to submit review.');
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
				nonce: wpssData.nonce
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert(response.data.message || 'Failed to request revision.');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
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

	// Initialize on DOM ready.
	$(document).ready(function() {
		WPSS.init();
	});

})(jQuery);
