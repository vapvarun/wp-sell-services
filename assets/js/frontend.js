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
		WPSS.initFilterSidebar();
		WPSS.initProposals();
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
	 * Show modal utility.
	 */
	WPSS.showModal = function(modalId) {
		const $modal = $('#' + modalId);
		if ($modal.length) {
			$modal.addClass('wpss-modal-open');
			$('body').addClass('wpss-modal-active');
		}
	};

	/**
	 * Hide modal utility.
	 */
	WPSS.hideModal = function(modalId) {
		const $modal = modalId ? $('#' + modalId) : $('.wpss-modal.wpss-modal-open');
		$modal.removeClass('wpss-modal-open');
		$('body').removeClass('wpss-modal-active');
	};

	/**
	 * Initialize modal handlers.
	 */
	WPSS.initModals = function() {
		// Close modal on backdrop click.
		$(document).on('click', '.wpss-modal-backdrop', function() {
			WPSS.hideModal();
		});

		// Close modal on close button click.
		$(document).on('click', '.wpss-modal-close, .wpss-modal-close-btn', function() {
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

		$btn.prop('disabled', true).text('Submitting...');

		$.ajax({
			url: wpss.ajaxUrl,
			type: 'POST',
			data: $form.serialize() + '&action=wpss_submit_review',
			success: function(response) {
				if (response.success) {
					WPSS.hideModal();
					alert(response.data.message || 'Review submitted successfully!');
					location.reload();
				} else {
					alert(response.data.message || 'Failed to submit review.');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
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

		$btn.prop('disabled', true).text('Submitting...');

		$.ajax({
			url: wpss.ajaxUrl,
			type: 'POST',
			data: $form.serialize() + '&action=wpss_open_dispute',
			success: function(response) {
				if (response.success) {
					WPSS.hideModal();
					alert(response.data.message || 'Dispute opened successfully.');
					location.reload();
				} else {
					alert(response.data.message || 'Failed to open dispute.');
				}
			},
			error: function() {
				alert('An error occurred. Please try again.');
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
	 * Initialize filter sidebar toggle.
	 */
	WPSS.initFilterSidebar = function() {
		const $toggle = $('.wpss-filter-toggle');
		const $sidebar = $('#wpss-sidebar');
		const $close = $('.wpss-sidebar-close');

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
			const proposalId = $btn.data('proposal');

			if (!confirm(wpssData.i18n?.confirmAcceptProposal || 'Accept this proposal and create an order?')) {
				return;
			}

			WPSS.handleProposalAction($btn, proposalId, 'accept');
		});

		// Reject proposal.
		$(document).on('click', '.wpss-reject-proposal', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const proposalId = $btn.data('proposal');
			const reason = prompt(wpssData.i18n?.rejectProposalReason || 'Please provide a reason for rejection (optional):');

			if (reason === null) {
				return; // Cancelled.
			}

			WPSS.handleProposalAction($btn, proposalId, 'reject', reason);
		});

		// Withdraw proposal (vendor).
		$(document).on('click', '.wpss-withdraw-proposal', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const proposalId = $btn.data('proposal');

			if (!confirm(wpssData.i18n?.confirmWithdrawProposal || 'Withdraw this proposal?')) {
				return;
			}

			WPSS.handleProposalAction($btn, proposalId, 'withdraw');
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
			alert(wpssData.i18n?.proposalDescriptionRequired || 'Please provide a proposal description.');
			return;
		}

		if (price <= 0) {
			alert(wpssData.i18n?.proposalPriceRequired || 'Please enter a valid price.');
			return;
		}

		if (deliveryDays <= 0) {
			alert(wpssData.i18n?.proposalDeliveryRequired || 'Please enter delivery time in days.');
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
					alert(response.data.message || wpssData.i18n?.proposalSubmitted || 'Proposal submitted successfully!');
					location.reload();
				} else {
					alert(response.data.message || wpssData.i18n?.proposalFailed || 'Failed to submit proposal.');
				}
			},
			error: function() {
				alert(wpssData.i18n?.ajaxError || 'An error occurred. Please try again.');
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
					alert(response.data.message || 'Action failed.');
					$btn.prop('disabled', false).text(btnText);
				}
			},
			error: function() {
				alert(wpssData.i18n?.ajaxError || 'An error occurred. Please try again.');
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

	// Initialize on DOM ready.
	$(document).ready(function() {
		WPSS.init();
	});

})(jQuery);
