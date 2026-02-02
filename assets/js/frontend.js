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
		WPSS.initVendorRegistration();
		WPSS.initRequirementsView();
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
						WPSS.showNotification(response.data.message || 'Failed to send message.', 'error');
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
		// Map frontend actions to AJAX action names.
		const actionMap = {
			accept: 'wpss_accept_order',
			reject: 'wpss_decline_order',
			start: 'wpss_start_work',
			deliver: 'wpss_deliver_order',
			complete: 'wpss_accept_delivery',
			cancel: 'wpss_cancel_order'
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
					alert(response.data?.message || 'Action failed. Please try again.');
				}
			},
			error: function(xhr) {
				alert('An error occurred. Please try again.');
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
					action: 'wpss_add_service_to_cart',
					service_id: options.serviceId,
					package_id: options.packageIndex,
					nonce: wpssData.nonce
				},
				success: function(response) {
					if (response.success) {
						if (response.data.redirect) {
							window.location.href = response.data.redirect;
						} else {
							WPSS.showNotification('Added to cart!', 'success');
						}
					} else {
						WPSS.showNotification(response.data.message || 'Failed to add to cart.', 'error');
					}
				},
				error: function() {
					WPSS.showNotification('An error occurred. Please try again.', 'error');
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
			const message = prompt('Describe your delivery:');

			if (!message || !message.trim()) {
				return;
			}

			WPSS.submitDelivery(orderId, message, []);
		}
	};

	/**
	 * Submit delivery via AJAX.
	 */
	WPSS.submitDelivery = function(orderId, message, files) {
		$.ajax({
			url: wpssData.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpss_deliver_order',
				order_id: orderId,
				message: message,
				files: files || [],
				nonce: wpssData.orderNonce || wpssData.nonce
			},
			success: function(response) {
				if (response.success) {
					WPSS.showNotification(response.data?.message || 'Delivery submitted successfully!', 'success');
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					WPSS.showNotification(response.data?.message || 'Failed to submit delivery.', 'error');
				}
			},
			error: function() {
				WPSS.showNotification('An error occurred. Please try again.', 'error');
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
			$btn.prop('disabled', true).html('<span class="wpss-spinner"></span> Submitting...');

			const orderId = $form.find('input[name="order_id"]').val();
			const message = $form.find('#deliver-message').val();

			if (!message || !message.trim()) {
				WPSS.showNotification('Please provide a delivery message.', 'error');
				$btn.prop('disabled', false).html(originalText);
				return;
			}

			WPSS.submitDelivery(orderId, message, []);

			// Re-enable button after a delay (in case page doesn't reload)
			setTimeout(function() {
				$btn.prop('disabled', false).html(originalText);
			}, 5000);
		});

		// Deliver button click.
		$(document).on('click', '.wpss-deliver-btn', function(e) {
			e.preventDefault();
			const orderId = $(this).data('order');
			WPSS.showDeliverModal(orderId);
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
			url: wpssData.ajaxUrl,
			type: 'POST',
			data: $form.serialize() + '&action=wpss_submit_review',
			success: function(response) {
				if (response.success) {
					WPSS.hideModal();
					WPSS.showNotification(response.data.message || 'Review submitted successfully!', 'success');
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					WPSS.showNotification(response.data.message || 'Failed to submit review.', 'error');
				}
			},
			error: function() {
				WPSS.showNotification('An error occurred. Please try again.', 'error');
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
			url: wpssData.ajaxUrl,
			type: 'POST',
			data: $form.serialize() + '&action=wpss_open_dispute',
			success: function(response) {
				if (response.success) {
					WPSS.hideModal();
					WPSS.showNotification(response.data.message || 'Dispute opened successfully. Our team will review your case.', 'success');
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					WPSS.showNotification(response.data.message || 'Failed to open dispute.', 'error');
				}
			},
			error: function() {
				WPSS.showNotification('An error occurred. Please try again.', 'error');
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
					WPSS.showNotification(response.data.message || 'Revision requested successfully!', 'success');
					setTimeout(function() {
						location.reload();
					}, 1500);
				} else {
					WPSS.showNotification(response.data.message || 'Failed to request revision.', 'error');
				}
			},
			error: function() {
				WPSS.showNotification('An error occurred. Please try again.', 'error');
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
						WPSS.showNotification(response.data.message || 'Application submitted successfully!', 'success');

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

	// Initialize on DOM ready.
	$(document).ready(function() {
		WPSS.init();
	});

})(jQuery);
