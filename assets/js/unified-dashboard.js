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

	if (typeof wpssUnifiedDashboard === 'undefined') {
		return;
	}

	const UnifiedDashboard = {
		/**
		 * Initialize the dashboard.
		 */
		init: function () {
			this.bindEvents();
			this.initWalletLedger();
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

			// Avatar upload
			$(document).on('click', '#wpss-avatar-upload-btn', this.handleAvatarUpload.bind(this));
			$(document).on('click', '#wpss-avatar-remove-btn', this.handleAvatarRemove.bind(this));

			// Cover image upload
			$(document).on('click', '#wpss-cover-upload-btn', this.handleCoverUpload.bind(this));
			$(document).on('click', '#wpss-cover-remove-btn', this.handleCoverRemove.bind(this));

			// Buyer request management
			$(document).on('click', '.wpss-close-request', this.handleCloseRequest.bind(this));
			$(document).on('click', '.wpss-reopen-request', this.handleReopenRequest.bind(this));
			$(document).on('click', '.wpss-delete-request', this.handleDeleteRequest.bind(this));

			// Portfolio
			$(document).on('click', '#wpss-portfolio-add-btn', this.handlePortfolioAdd.bind(this));
			$(document).on('click', '.wpss-portfolio-edit', this.handlePortfolioEdit.bind(this));
			$(document).on('click', '.wpss-portfolio-delete', this.handlePortfolioDelete.bind(this));
			$(document).on('click', '.wpss-portfolio-toggle-featured', this.handlePortfolioToggleFeatured.bind(this));
			$(document).on('submit', '#wpss-portfolio-form', this.handlePortfolioSave.bind(this));
			$(document).on('click', '#wpss-portfolio-upload-media', this.handlePortfolioMediaUpload.bind(this));
			$(document).on('click', '.wpss-modal__close, .wpss-modal__close-btn, .wpss-modal__overlay', this.handleModalClose.bind(this));

			// Favorites — unfavorite from the saved grid.
			$(document).on('click', '.wpss-favorites__remove', this.handleFavoriteRemove.bind(this));

			// Wallet ledger — load the next page of transactions.
			$(document).on('click', '#wpss-wallet-load-more', this.handleWalletLoadMore.bind(this));

			// Collapsed nav (under 480px): Menu opens the list, picking a section closes it.
			$(document).on('click', '.wpss-dashboard__nav-toggle', this.handleNavToggle);
			$(document).on('click', '.wpss-dashboard__nav-item', this.closeNav);

			// Reviews section: vendor reply, through the same REST route the app uses.
			$(document).on('submit', '.wpss-review-reply-form', this.handleReviewReply.bind(this));
		},

		/**
		 * Toggle the collapsed dashboard nav.
		 *
		 * @param {Event} e Click event.
		 */
		handleNavToggle: function (e) {
			var $btn = $(e.currentTarget);
			var open = $btn.attr('aria-expanded') !== 'true';

			$btn.attr('aria-expanded', open ? 'true' : 'false');
			$btn.closest('.wpss-dashboard__sidebar').toggleClass('wpss-dashboard__sidebar--open', open);
		},

		/**
		 * Close the collapsed dashboard nav.
		 */
		closeNav: function () {
			$('.wpss-dashboard__sidebar--open')
				.removeClass('wpss-dashboard__sidebar--open')
				.find('.wpss-dashboard__nav-toggle')
				.attr('aria-expanded', 'false');
		},

		// Current page already loaded into the wallet ledger.
		walletPage: 0,

		/**
		 * Boot the wallet transactions ledger on the earnings/wallet section.
		 *
		 * Reads the additive, read-only GET /wallet/transactions endpoint
		 * (standard list envelope: items at the response root, total in the
		 * X-WP-Total header) and renders the vendor's credit/debit history.
		 * No-ops on sections that don't contain the ledger container.
		 */
		initWalletLedger: function () {
			if (!$('#wpss-wallet-transactions').length) {
				return;
			}
			this.walletPage = 0;
			this.loadWalletTransactions(1);
		},

		/**
		 * Handle the "Load more" click in the wallet ledger.
		 *
		 * @param {Event} e Click event.
		 */
		handleWalletLoadMore: function (e) {
			e.preventDefault();
			this.loadWalletTransactions(this.walletPage + 1);
		},

		/**
		 * Fetch and render one page of wallet transactions.
		 *
		 * @param {number} page Page number to request (1-based).
		 */
		loadWalletTransactions: function (page) {
			var self = this;
			var $list = $('#wpss-wallet-transactions');
			var $more = $('#wpss-wallet-load-more');
			var i18n = (wpssUnifiedDashboard && wpssUnifiedDashboard.i18n) || {};
			var perPage = parseInt($list.data('per-page'), 10) || 10;
			var restPath = $list.data('rest-path') || 'wallet/transactions';

			$more.prop('disabled', true);
			$list.attr('aria-busy', 'true');

			$.ajax({
				url: wpssUnifiedDashboard.restUrl + restPath,
				type: 'GET',
				data: { page: page, per_page: perPage },
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce);
				}
			}).done(function (transactions, status, xhr) {
				var total = parseInt(xhr.getResponseHeader('X-WP-Total'), 10) || 0;
				self.renderWalletTransactions(transactions || [], page);
				self.walletPage = page;

				// Show "Load more" while there are more rows than rendered so far.
				if (page * perPage < total) {
					$more.show().prop('disabled', false);
				} else {
					$more.hide();
				}
			}).fail(function () {
				if (self.walletPage === 0) {
					$list.html(
						'<p class="wpss-text-muted">' +
						(i18n.walletLoadFailed) +
						'</p>'
					);
				} else {
					$more.prop('disabled', false);
					WPSS.showNotification(i18n.walletLoadFailed, 'error');
				}
			}).always(function () {
				$list.attr('aria-busy', 'false');
			});
		},

		/**
		 * Render wallet transaction rows into the ledger container.
		 *
		 * @param {Array}  transactions Rows from GET /wallet/transactions.
		 * @param {number} page         Page that produced these rows.
		 */
		renderWalletTransactions: function (transactions, page) {
			var i18n = (wpssUnifiedDashboard && wpssUnifiedDashboard.i18n) || {};
			var $list = $('#wpss-wallet-transactions');

			if (page === 1) {
				$list.empty();
			}

			if (page === 1 && !transactions.length) {
				$list.html(
					'<p class="wpss-text-muted">' +
					(i18n.walletEmpty) +
					'</p>'
				);
				return;
			}

			var $tbody;
			if (page === 1) {
				var $table = $(
					'<div class="wpss-table-responsive">' +
						'<table class="wpss-table wpss-wallet__table">' +
							'<thead><tr>' +
								'<th>' + (i18n.walletColDate) + '</th>' +
								'<th>' + (i18n.walletColType) + '</th>' +
								'<th>' + (i18n.walletColDescription) + '</th>' +
								'<th class="wpss-wallet__amount-col">' + (i18n.walletColAmount) + '</th>' +
								'<th class="wpss-wallet__amount-col">' + (i18n.walletColBalance) + '</th>' +
							'</tr></thead>' +
							'<tbody></tbody>' +
						'</table>' +
					'</div>'
				);
				$list.append($table);
				$tbody = $table.find('tbody');
			} else {
				$tbody = $list.find('.wpss-wallet__table tbody');
			}

			var self = this;
			transactions.forEach(function (txn) {
				$tbody.append(self.buildWalletRow(txn, i18n));
			});

			if (window.lucide && typeof window.lucide.createIcons === 'function') {
				window.lucide.createIcons();
			}
		},

		/**
		 * Build one wallet ledger <tr> from a transaction row. All dynamic
		 * values are inserted via jQuery .text() so they are escaped.
		 *
		 * @param {Object} txn  Transaction row.
		 * @param {Object} i18n Localized strings.
		 * @return {jQuery} The row element.
		 */
		buildWalletRow: function (txn, i18n) {
			var amount = parseFloat(txn.amount) || 0;
			// Trust the server's is_debit. Debits are stored POSITIVE — the sign
			// is applied on read from the debit-type list — so inferring it from
			// amount < 0 rendered every withdrawal as "+90.00", a payout that
			// looked like a credit. Falls back to the sign for older payloads.
			var isDebit = ( typeof txn.is_debit !== 'undefined' ) ? !! txn.is_debit : ( amount < 0 );
			var $row = $('<tr>').addClass(isDebit ? 'wpss-wallet__row--debit' : 'wpss-wallet__row--credit');

			var dateText = txn.created_at || '';
			if (dateText && window.Date) {
				var d = new Date(dateText);
				if (!isNaN(d.getTime())) {
					dateText = d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
				}
			}

			var typeLabel = (txn.type || '').replace(/[_-]+/g, ' ').replace(/\b\w/g, function (c) {
				return c.toUpperCase();
			});

			$('<td>').text(dateText).appendTo($row);
			$('<td>').append(
				$('<span>').addClass('wpss-badge wpss-badge--' + (txn.type || 'neutral')).text(typeLabel || i18n.walletTypeUnknown)
			).appendTo($row);

			// Description + an optional clickable reference link ("View Order" /
			// "View Tip" / ...). The server resolves reference_url (empty when the
			// reference is not a linkable order), so vendors get a real link to the
			// related order instead of an opaque internal ID.
			var $descCell = $('<td>');
			$('<span>').addClass('wpss-wallet__desc').text(txn.description || '').appendTo($descCell);
			if (txn.reference_url && txn.reference_label) {
				$('<a>')
					.addClass('wpss-wallet__reference-link')
					.attr('href', txn.reference_url)
					.text(txn.reference_label)
					.appendTo($descCell);
			}
			$descCell.appendTo($row);

			var symbol = isDebit ? '-' : '+';
			// Decimals depend on the transaction's own currency (the ledger can
			// mix currencies), so resolve per-row against the zero-decimal set.
			var cfg = window.wpssUnifiedDashboard || {};
			var zeroDecimal = cfg.zeroDecimalCurrencies || [];
			var txnDecimals = (txn.currency && zeroDecimal.indexOf(txn.currency) !== -1) ? 0
				: (typeof cfg.currencyDecimals !== 'undefined' ? cfg.currencyDecimals : 2);
			$('<td>')
				.addClass('wpss-wallet__amount-col wpss-wallet__amount')
				.text(symbol + Math.abs(amount).toFixed(txnDecimals) + ' ' + (txn.currency || ''))
				.appendTo($row);
			$('<td>')
				.addClass('wpss-wallet__amount-col')
				.text((parseFloat(txn.balance_after) || 0).toFixed(txnDecimals) + ' ' + (txn.currency || ''))
				.appendTo($row);

			return $row;
		},

		/**
		 * Remove a service from the favorites grid and update the empty state
		 * if the grid becomes empty.
		 *
		 * @param {Event} e Click event.
		 */
		handleFavoriteRemove: function (e) {
			e.preventDefault();
			e.stopPropagation();

			var $button = $(e.currentTarget);
			var serviceId = parseInt($button.data('service-id'), 10);
			if (!serviceId || $button.prop('disabled')) {
				return;
			}

			var $card = $button.closest('.wpss-favorites__card');
			$button.prop('disabled', true);

			var i18n = (wpssUnifiedDashboard && wpssUnifiedDashboard.i18n) || {};

			// Migrated from admin-ajax to the wpss/v1 favorites REST twin:
			// DELETE /favorites/{id}.
			$.ajax({
				url: wpssUnifiedDashboard.restUrl + 'favorites/' + serviceId,
				type: 'DELETE',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce);
				}
			}).done(function (response) {
				if (response && response.success) {
					$card.fadeOut(180, function () {
						$card.remove();
						// If the last card was removed, reload to show the empty state.
						if ($('.wpss-favorites__card').length === 0) {
							window.location.reload();
						} else {
							var $count = $('.wpss-favorites__count');
							if ($count.length && typeof response.count === 'number') {
								$count.text(
									(response.count === 1
										? (i18n.favoriteCountSingular)
										: (i18n.favoriteCountPlural)
									).replace('%d', response.count)
								);
							}
						}
					});
				} else {
					$button.prop('disabled', false);
					WPSS.showNotification((response && response.message) || i18n.favoriteRemoveFailed, 'error');
				}
			}).fail(function () {
				$button.prop('disabled', false);
				WPSS.showNotification(i18n.favoriteRemoveFailed, 'error');
			});
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
						WPSS.showNotification(response.data.message || wpssUnifiedDashboard.i18n.errorOccurred, 'error');
						$button
							.prop('disabled', false)
							.text(originalText);
					}
				},
				error: function () {
					WPSS.showNotification(wpssUnifiedDashboard.i18n.errorTryAgain, 'error');
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

			// Withdrawal form is handled inline in the earnings/wallet template.
			// Scroll to the withdrawal section if it exists.
			var $section = $('#wpss-withdrawal-form, #wpss-withdraw-form');
			if ($section.length) {
				$('html, body').animate({ scrollTop: $section.offset().top - 100 }, 300);
				$section.find('input[name="amount"]').focus();
			}
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

			// Build the field set from the form. The whole form is submitted, so
			// force vacation_mode to an explicit 0/1 - an unchecked checkbox is
			// absent from serialize() and would otherwise leave vacation on.
			const data = {};
			$form.serializeArray().forEach(function (f) { data[f.name] = f.value; });
			const $vac = $form.find('[name="vacation_mode"]');
			if ($vac.length && $vac.is(':checkbox')) {
				data.vacation_mode = $vac.is(':checked') ? 1 : 0;
			}

			// REST: PUT /vendors/me (via POST + method override for host
			// compatibility). Writes the wpss_vendor_profiles table - the single
			// canonical store - so intro video, country, city, website, vacation,
			// and cover all persist (the old AJAX twin split storage / dropped them).
			$.ajax({
				url: wpssUnifiedDashboard.restUrl + 'vendors/me',
				method: 'PUT',
				data: data,
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce);
				},
				success: function () {
					// Report through the shared toast rather than injecting an
					// alert at the top of the form: on a long profile form the
					// inline banner appears above the fold the user is not
					// looking at, so a save could look like it did nothing.
					wpssToast(wpssUnifiedDashboard.i18n.profileSaved, 'success');

					$button
						.prop('disabled', false)
						.text(originalText);
				},
				error: function (xhr) {
					const msg = (xhr.responseJSON && xhr.responseJSON.message)
						|| wpssUnifiedDashboard.i18n.errorTryAgain;
					WPSS.showNotification(msg, 'error');
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
			// The REST twin takes an explicit target state, so flip here
			// (the legacy AJAX handler flipped server-side).
			const targetStatus = currentStatus === 'publish' ? 'draft' : 'publish';

			$button.prop('disabled', true);

			// Migrated from admin-ajax wpss_update_service_status to the REST
			// twin PUT /services/{id} with the status param (6.0b).
			$.ajax({
				url: wpssUnifiedDashboard.restUrl + 'services/' + serviceId,
				type: 'PUT',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce);
				},
				data: { status: targetStatus },
				success: function (response) {
					var newStatus = response && response.status;
					if (newStatus) {
						$button.data('current-status', newStatus);

						// Match the actual card markup in
						// templates/dashboard/sections/services.php:
						// .wpss-service-card wrapper + .wpss-service-card__status badge.
						const $card = $button.closest('.wpss-service-card');
						const $badge = $card.find('.wpss-service-card__status');
						const newStatusText = newStatus === 'publish' ? wpssUnifiedDashboard.i18n.published : wpssUnifiedDashboard.i18n.draft;
						$badge.text(newStatusText);
						$badge.removeClass('wpss-service-card__status--publish wpss-service-card__status--draft');
						$badge.addClass(newStatus === 'publish' ? 'wpss-service-card__status--publish' : 'wpss-service-card__status--draft');

						// Button label is plain text ("Pause" / "Publish"),
						// matching how the template renders it.
						$button.text(newStatus === 'publish' ? wpssUnifiedDashboard.i18n.pause : wpssUnifiedDashboard.i18n.publish);
					} else {
						WPSS.showNotification((response && response.message) || wpssUnifiedDashboard.i18n.errorOccurred, 'error');
					}
					$button.prop('disabled', false);
				},
				error: function (xhr) {
					var msg = (xhr.responseJSON && xhr.responseJSON.message) || wpssUnifiedDashboard.i18n.errorTryAgain;
					WPSS.showNotification(msg, 'error');
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
			// Card wrapper is .wpss-service-card (the dashboard template), not
			// .wpss-card - without the correct selector the post-delete fadeOut
			// is a no-op and the deleted card stays on screen (twin of the
			// toggle-status selector bug).
			const $card = $button.closest('.wpss-service-card');

			WPSS.showConfirm(wpssUnifiedDashboard.i18n.confirmDelete, function () {
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
							WPSS.showNotification(response.data.message || wpssUnifiedDashboard.i18n.errorOccurred, 'error');
							$button.prop('disabled', false);
						}
					},
					error: function () {
						WPSS.showNotification(wpssUnifiedDashboard.i18n.errorTryAgain, 'error');
						$button.prop('disabled', false);
					}
				});
			}, { confirmText: 'Delete', tone: 'danger' });
		},

		/**
		 * Handle avatar upload via WP media library.
		 *
		 * @param {Event} e Click event.
		 */
		handleAvatarUpload: function (e) {
			e.preventDefault();

			if (this.avatarFrame) {
				this.avatarFrame.open();
				return;
			}

			this.avatarFrame = wp.media({
				title: wpssUnifiedDashboard.i18n.chooseProfilePhoto,
				button: { text: wpssUnifiedDashboard.i18n.useAsProfilePhoto },
				multiple: false,
				library: { type: 'image' }
			});

			this.avatarFrame.on('select', function () {
				var attachment = this.avatarFrame.state().get('selection').first().toJSON();
				var url = attachment.sizes && attachment.sizes.thumbnail
					? attachment.sizes.thumbnail.url
					: attachment.url;

				$('#wpss-avatar-preview').attr('src', url);
				$('#wpss-avatar-id').val(attachment.id);

				// Show remove button if not already visible.
				if ($('#wpss-avatar-remove-btn').length === 0) {
					$('#wpss-avatar-upload-btn').after(
						' <button type="button" class="wpss-btn wpss-btn--sm wpss-btn--link" id="wpss-avatar-remove-btn">' + wpssUnifiedDashboard.i18n.remove + '</button>'
					);
				}
			}.bind(this));

			this.avatarFrame.open();
		},

		/**
		 * Handle avatar removal.
		 *
		 * @param {Event} e Click event.
		 */
		handleAvatarRemove: function (e) {
			e.preventDefault();

			$('#wpss-avatar-id').val('0');
			// Fall back to Gravatar.
			var $img = $('#wpss-avatar-preview');
			var gravatarUrl = $img.data('gravatar') || '';
			if (gravatarUrl) {
				$img.attr('src', gravatarUrl);
			}
			$(e.currentTarget).remove();
		},

		/**
		 * Handle cover image upload via WP media library.
		 *
		 * @param {Event} e Click event.
		 */
		handleCoverUpload: function (e) {
			e.preventDefault();

			if (this.coverFrame) {
				this.coverFrame.open();
				return;
			}

			this.coverFrame = wp.media({
				title: wpssUnifiedDashboard.i18n.selectCoverImage,
				button: { text: wpssUnifiedDashboard.i18n.setCoverImage },
				multiple: false,
				library: { type: 'image' }
			});

			this.coverFrame.on('select', function () {
				var attachment = this.coverFrame.state().get('selection').first().toJSON();
				var url = attachment.sizes && attachment.sizes.large
					? attachment.sizes.large.url
					: attachment.url;

				$('#wpss-cover-preview').attr('src', url).show();
				$('#wpss-cover-placeholder').hide();
				$('#wpss-cover-id').val(attachment.id);

				// Show remove button if not already visible.
				if ($('#wpss-cover-remove-btn').length === 0) {
					$('#wpss-cover-upload-btn').after(
						' <button type="button" class="wpss-btn wpss-btn--sm wpss-btn--link" id="wpss-cover-remove-btn">' + wpssUnifiedDashboard.i18n.remove + '</button>'
					);
				}
			}.bind(this));

			this.coverFrame.open();
		},

		/**
		 * Handle cover image removal.
		 *
		 * @param {Event} e Click event.
		 */
		handleCoverRemove: function (e) {
			e.preventDefault();

			$('#wpss-cover-id').val('0');
			$('#wpss-cover-preview').hide();
			$('#wpss-cover-placeholder').show();
			$(e.currentTarget).remove();
		},

		/**
		 * Handle closing a buyer request (set to draft).
		 *
		 * @param {Event} e Click event.
		 */
		handleCloseRequest: function (e) {
			e.preventDefault();
			var requestId = $(e.currentTarget).data('request-id');

			WPSS.showConfirm(
				wpssUnifiedDashboard.i18n.closeRequestConfirm,
				function () {
					$.ajax({
						url: wpssUnifiedDashboard.ajaxUrl,
						type: 'POST',
						data: {
							action: 'wpss_update_request_status',
							request_id: requestId,
							status: 'draft',
							nonce: wpssUnifiedDashboard.nonce
						},
						success: function (response) {
							if (response.success) {
								WPSS.showNotification(response.data.message || wpssUnifiedDashboard.i18n.requestClosed, 'success');
								location.reload();
							} else {
								WPSS.showNotification(response.data.message || wpssUnifiedDashboard.i18n.requestCloseFailed, 'error');
							}
						}
					});
				}
			);
		},

		/**
		 * Handle reopening a closed buyer request (set to publish).
		 *
		 * @param {Event} e Click event.
		 */
		handleReopenRequest: function (e) {
			e.preventDefault();
			var requestId = $(e.currentTarget).data('request-id');

			WPSS.showConfirm(
				wpssUnifiedDashboard.i18n.reopenRequestConfirm,
				function () {
					$.ajax({
						url: wpssUnifiedDashboard.ajaxUrl,
						type: 'POST',
						data: {
							action: 'wpss_update_request_status',
							request_id: requestId,
							status: 'publish',
							nonce: wpssUnifiedDashboard.nonce
						},
						success: function (response) {
							if (response.success) {
								WPSS.showNotification(response.data.message || wpssUnifiedDashboard.i18n.requestReopened, 'success');
								location.reload();
							} else {
								WPSS.showNotification(response.data.message || wpssUnifiedDashboard.i18n.requestReopenFailed, 'error');
							}
						}
					});
				}
			);
		},

		/**
		 * Handle deleting a buyer request.
		 *
		 * @param {Event} e Click event.
		 */
		handleDeleteRequest: function (e) {
			e.preventDefault();
			var requestId = $(e.currentTarget).data('request-id');

			WPSS.showConfirm(
				wpssUnifiedDashboard.i18n.deleteRequestConfirm,
				function () {
					$.ajax({
						url: wpssUnifiedDashboard.ajaxUrl,
						type: 'POST',
						data: {
							action: 'wpss_delete_request',
							request_id: requestId,
							nonce: wpssUnifiedDashboard.nonce
						},
						success: function (response) {
							if (response.success) {
								WPSS.showNotification(response.data.message || wpssUnifiedDashboard.i18n.requestDeleted, 'success');
								location.reload();
							} else {
								WPSS.showNotification(response.data.message || wpssUnifiedDashboard.i18n.requestDeleteFailed, 'error');
							}
						}
					});
				},
				{ confirmText: wpssUnifiedDashboard.i18n.deleteConfirmBtn, tone: 'danger' }
			);
		},

		/**
		 * Open portfolio modal for adding.
		 */
		handlePortfolioAdd: function (e) {
			e.preventDefault();
			this.resetPortfolioForm();
			$('#wpss-portfolio-modal-title').text(wpssUnifiedDashboard.i18n.addPortfolioItem);
			$('#wpss-portfolio-modal').addClass('wpss-modal-open');
		},

		/**
		 * Open portfolio modal for editing.
		 *
		 * @param {Event} e Click event.
		 */
		handlePortfolioEdit: function (e) {
			e.preventDefault();
			var $item = $(e.currentTarget).closest('.wpss-portfolio__item');
			var itemId = $item.data('item-id');

			this.resetPortfolioForm();
			$('#wpss-portfolio-modal-title').text(wpssUnifiedDashboard.i18n.editPortfolioItem);
			$('#wpss-portfolio-item-id').val(itemId);

			// Populate form from data attributes.
			var title = $item.find('.wpss-portfolio__title').text();
			var description = $item.attr('data-description') || '';
			var externalUrl = $item.attr('data-external-url') || '';
			var tags = $item.attr('data-tags') || '';
			var serviceId = $item.attr('data-service-id') || '0';
			var isFeatured = $item.attr('data-is-featured') === '1';
			var mediaIds = [];
			var mediaThumbs = [];

			try {
				mediaIds = JSON.parse($item.attr('data-media') || '[]');
				mediaThumbs = JSON.parse($item.attr('data-media-thumbs') || '[]');
			} catch (ex) {
				mediaIds = [];
				mediaThumbs = [];
			}

			$('#portfolio-title').val(title);
			$('#portfolio-description').val(description);
			$('#portfolio-external-url').val(externalUrl);
			$('#portfolio-tags').val(tags);
			$('#portfolio-service').val(serviceId);
			$('#portfolio-featured').prop('checked', isFeatured);

			// Restore media preview and hidden field.
			if (mediaIds.length) {
				var $preview = $('#wpss-portfolio-media-preview');
				$preview.empty();
				mediaThumbs.forEach(function (thumbUrl) {
					$preview.append('<img src="' + thumbUrl + '" class="wpss-portfolio-media-thumb">');
				});
				$('#wpss-portfolio-media').val(JSON.stringify(mediaIds));
			}

			$('#wpss-portfolio-modal').addClass('wpss-modal-open');
		},

		/**
		 * Handle portfolio delete.
		 *
		 * @param {Event} e Click event.
		 */
		handlePortfolioDelete: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var itemId = $btn.data('item-id');

			WPSS.showConfirm(
				wpssUnifiedDashboard.i18n.deletePortfolioConfirm,
				function () {
					$btn.prop('disabled', true);

					$.ajax({
						url: wpssUnifiedDashboard.restUrl + 'portfolio/' + itemId,
						type: 'DELETE',
						beforeSend: function (xhr) {
							xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce);
						},
						success: function () {
							$btn.closest('.wpss-portfolio__item').fadeOut(300, function () {
								$(this).remove();
							});
						},
						error: function (xhr) {
							var msg = wpssUnifiedDashboard.i18n.deleteFailed;
							try { msg = JSON.parse(xhr.responseText).message || msg; } catch (ex) {}
							WPSS.showNotification(msg, 'error');
							$btn.prop('disabled', false);
						}
					});
				},
				{ confirmText: wpssUnifiedDashboard.i18n.deleteConfirmBtn, tone: 'danger' }
			);
		},

		/**
		 * Handle portfolio toggle featured.
		 *
		 * @param {Event} e Click event.
		 */
		handlePortfolioToggleFeatured: function (e) {
			e.preventDefault();
			var $btn = $(e.currentTarget);
			var itemId = $btn.data('item-id');

			$btn.prop('disabled', true);

			$.ajax({
				url: wpssUnifiedDashboard.restUrl + 'portfolio/' + itemId + '/featured',
				type: 'POST',
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce);
				},
				success: function () {
					window.location.reload();
				},
				error: function (xhr) {
					var msg = wpssUnifiedDashboard.i18n.failed;
					try { msg = JSON.parse(xhr.responseText).message || msg; } catch (ex) {}
					WPSS.showNotification(msg, 'error');
					$btn.prop('disabled', false);
				}
			});
		},

		/**
		 * Handle portfolio form save.
		 *
		 * @param {Event} e Submit event.
		 */
		handlePortfolioSave: function (e) {
			e.preventDefault();
			var $form = $(e.currentTarget);
			var $btn = $form.find('button[type="submit"]');
			var originalText = $btn.text();
			var itemId = $form.find('[name="item_id"]').val();
			var isEdit = itemId && itemId !== '0';

			$btn.prop('disabled', true).text(wpssUnifiedDashboard.i18n.processing);

			// Build REST API payload from form fields.
			var mediaVal = $form.find('[name="media"]').val();
			var data = {
				title: $form.find('[name="title"]').val(),
				description: $form.find('[name="description"]').val(),
				external_url: $form.find('[name="external_url"]').val() || '',
				media: mediaVal ? JSON.parse(mediaVal) : []
			};
			var serviceId = $form.find('[name="service_id"]').val();
			if (serviceId) {
				data.service_id = parseInt(serviceId, 10);
			}

			$.ajax({
				url: wpssUnifiedDashboard.restUrl + 'portfolio' + (isEdit ? '/' + itemId : ''),
				type: isEdit ? 'PUT' : 'POST',
				contentType: 'application/json',
				data: JSON.stringify(data),
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce);
				},
				success: function () {
					$('#wpss-portfolio-modal').removeClass('wpss-modal-open');
					window.location.reload();
				},
				error: function (xhr) {
					var msg = wpssUnifiedDashboard.i18n.saveFailed;
					try { msg = JSON.parse(xhr.responseText).message || msg; } catch (ex) {}
					WPSS.showNotification(msg, 'error');
					$btn.prop('disabled', false).text(originalText);
				}
			});
		},

		/**
		 * Handle portfolio media upload.
		 *
		 * @param {Event} e Click event.
		 */
		handlePortfolioMediaUpload: function (e) {
			e.preventDefault();

			if (this.portfolioMediaFrame) {
				this.portfolioMediaFrame.open();
				return;
			}

			this.portfolioMediaFrame = wp.media({
				title: wpssUnifiedDashboard.i18n.selectPortfolioImages,
				button: { text: wpssUnifiedDashboard.i18n.addToPortfolio },
				multiple: true,
				library: { type: 'image' }
			});

			this.portfolioMediaFrame.on('select', function () {
				var attachments = this.portfolioMediaFrame.state().get('selection').toJSON();
				var ids = attachments.map(function (a) { return a.id; });
				var $preview = $('#wpss-portfolio-media-preview');

				$preview.empty();
				attachments.forEach(function (att) {
					var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
					$preview.append('<img src="' + url + '" class="wpss-portfolio-media-thumb">');
				});
				$('#wpss-portfolio-media').val(JSON.stringify(ids));
			}.bind(this));

			this.portfolioMediaFrame.open();
		},

		/**
		 * Post a vendor reply to a review (POST /wpss/v1/reviews/{id}/reply)
		 * and swap the form for the stored response.
		 *
		 * @param {Event} e Submit event.
		 */
		handleReviewReply: function (e) {
			e.preventDefault();
			var $form = $(e.currentTarget);
			var $button = $form.find('button[type="submit"]');
			var i18n = wpssUnifiedDashboard.i18n;
			var reply = $.trim($form.find('textarea[name="reply"]').val());

			if (!reply) {
				return;
			}

			$button.prop('disabled', true);

			$.ajax({
				url: wpssUnifiedDashboard.restUrl + 'reviews/' + $form.data('review-id') + '/reply',
				type: 'POST',
				data: { reply: reply },
				beforeSend: function (xhr) {
					xhr.setRequestHeader('X-WP-Nonce', wpssUnifiedDashboard.restNonce);
				}
			}).done(function (review) {
				var $reply = $('<div class="wpss-review-reply"><div class="wpss-reply-header"><strong></strong><span class="wpss-reply-date"></span></div></div>');
				$reply.find('strong').text(i18n.sellerResponse);
				$reply.find('.wpss-reply-date').text(review.vendor_reply_human || '');
				$reply.append(review.vendor_reply_html || $('<p>').text(reply));
				$form.replaceWith($reply);
				WPSS.showNotification(i18n.reviewReplySent, 'success');
			}).fail(function (xhr) {
				var message = (xhr.responseJSON && xhr.responseJSON.message) || i18n.reviewReplyFailed;
				WPSS.showNotification(message, 'error');
				$button.prop('disabled', false);
			});
		},

		/**
		 * Close modal.
		 *
		 * @param {Event} e Click event.
		 */
		handleModalClose: function (e) {
			e.preventDefault();
			$(e.currentTarget).closest('.wpss-modal').removeClass('wpss-modal-open');
		},

		/**
		 * Reset portfolio form to defaults.
		 */
		resetPortfolioForm: function () {
			$('#wpss-portfolio-form')[0].reset();
			$('#wpss-portfolio-item-id').val('0');
			$('#wpss-portfolio-media').val('[]');
			$('#wpss-portfolio-media-preview').empty();
		}
	};

	// Initialize when DOM is ready
	$(function () {
		UnifiedDashboard.init();
	});

})(jQuery);
