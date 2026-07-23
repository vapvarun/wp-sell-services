/**
 * Vendors admin — list page (bulk actions, status change, modal detail) and the
 * vendor detail drawer (tabbed AJAX content, per-vendor commission, vacation,
 * availability, seller level, portfolio moderation).
 *
 * Extracted from two inline <script> blocks in VendorsPage.php (ux-audit F2),
 * 695 lines total. Both shared window.wpssVendors; it now arrives once via
 * wp_localize_script. The per-vendor id, the only genuinely dynamic value, is
 * added to that object by the detail render.
 *
 * @package WPSellServices
 * @since   1.5.1
 */

( function( $ ) {
	'use strict';

	function wpssAdminNotice(msg, type) {
		type = type || 'error';
		var cls = type === 'success' ? 'notice-success' : 'notice-error';
		var $notice = jQuery('<div class="notice ' + cls + ' is-dismissible"><p>' + msg + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
		jQuery('.wrap h1, .wrap h2').first().after($notice);
		$notice.find('.notice-dismiss').on('click', function() { $notice.fadeOut(200, function() { $notice.remove(); }); });
		setTimeout(function() { $notice.fadeOut(400, function() { $notice.remove(); }); }, 6000);
	}

	jQuery(function($) {
		var $modal = $('#wpss-vendor-modal');
		var $modalBody = $('#wpss-vendor-modal-body');

		// Bulk actions: select-all + apply.
		$('#cb-select-all-1, #cb-select-all-2').on('change', function() {
			$('input[name="vendor_ids[]"]').prop('checked', $(this).prop('checked'));
		});

		$('.wpss-vendors-bulk-apply').on('click', function(e) {
			e.preventDefault();
			var bulkAction = $('.wpss-vendors-bulk-select').val();
			if ( ! bulkAction ) {
				return;
			}
			var ids = $('input[name="vendor_ids[]"]:checked').map(function() { return this.value; }).get();
			if ( ids.length === 0 ) {
				wpssAdminNotice(wpssVendors.i18n.selectAtLeastOneVendorFirst, 'error');
				return;
			}
			var labels = {
				'approve':    wpssVendors.i18n.approve,
				'suspend':    wpssVendors.i18n.suspend,
				'reactivate': wpssVendors.i18n.reactivate
			};
			/* translators: 1: action label, 2: count */
			var confirmMsg = wpssVendors.i18n.bulkConfirm;
			confirmMsg = confirmMsg.replace('%1$s', labels[bulkAction] || bulkAction).replace('%2$d', ids.length);
			if ( ! confirm( confirmMsg ) ) {
				return;
			}
			var $btn = $(this);
			$btn.prop('disabled', true);
			$.ajax({
				url: wpssVendors.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_bulk_update_vendor_status',
					bulk_action: bulkAction,
					vendor_ids: ids,
					nonce: $('input[name="wpss_vendors_bulk_nonce"]').val()
				},
				success: function(response) {
					if ( response.success ) {
						location.reload();
					} else {
						wpssAdminNotice( response.data && response.data.message ? response.data.message : i18n.error, 'error' );
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					wpssAdminNotice( i18n.error, 'error' );
					$btn.prop('disabled', false);
				}
			});
		});

		// View vendor details
		$('.wpss-view-vendor').on('click', function(e) {
			e.preventDefault();
			var vendorId = $(this).data('vendor-id');

			$modalBody.html('<div class="wpss-modal-loading"><span class="spinner is-active"></span> ' + wpssVendors.i18n.loadingVendorDetails + '</div>');
			$modal.show();

			$.ajax({
				url: wpssVendors.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_get_vendor_details',
					nonce: wpssVendors.nonce,
					vendor_id: vendorId
				},
				success: function(response) {
					if (response.success) {
						$modalBody.html(response.data.html);
					} else {
						$modalBody.html('<div class="notice notice-error"><p>' + (response.data.message || i18n.error) + '</p></div>');
					}
				},
				error: function() {
					$modalBody.html('<div class="notice notice-error"><p>' + i18n.error + '</p></div>');
				}
			});
		});

		// Close modal
		$('.wpss-modal-close, .wpss-modal').on('click', function(e) {
			if (e.target === this) {
				$modal.hide();
			}
		});

		// Update vendor status
		$('.wpss-change-status').on('click', function(e) {
			e.preventDefault();

			if (!confirm(wpssVendors.i18n.confirmStatusChange)) {
				return;
			}

			var $btn = $(this);
			var vendorId = $btn.data('vendor-id');
			var newStatus = $btn.data('status');
			var $row = $btn.closest('tr');

			$btn.prop('disabled', true);

			$.ajax({
				url: wpssVendors.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_update_vendor_status',
					nonce: wpssVendors.nonce,
					vendor_id: vendorId,
					status: newStatus
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						wpssAdminNotice(response.data.message || i18n.error, 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					wpssAdminNotice(i18n.error, 'error');
					$btn.prop('disabled', false);
				}
			});
		});

		// Save vendor commission rate
		$(document).on('click', '#wpss-save-commission', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var vendorId = $btn.data('vendor-id');
			var rate = $('#wpss-vendor-commission-rate').val();

			if (rate === '') {
				wpssAdminNotice(wpssVendors.i18n.pleaseEnterACommissionRate, 'error');
				return;
			}

			$btn.prop('disabled', true);

			$.ajax({
				url: wpssVendors.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_update_vendor_commission',
					nonce: wpssVendors.nonce,
					vendor_id: vendorId,
					rate: rate
				},
				success: function(response) {
					if (response.success) {
						$('#wpss-commission-status').html('<span style="color: #00a32a;">' + response.data.message + '</span>');
						// Reload modal content to update UI
						$('.wpss-view-vendor[data-vendor-id="' + vendorId + '"]').click();
					} else {
						wpssAdminNotice(response.data.message || i18n.error, 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					wpssAdminNotice(i18n.error, 'error');
					$btn.prop('disabled', false);
				}
			});
		});

		// Reset vendor commission to global rate
		$(document).on('click', '#wpss-reset-commission', function(e) {
			e.preventDefault();
			if (!confirm(wpssVendors.i18n.resetThisVendorsCommission)) {
				return;
			}

			var $btn = $(this);
			var vendorId = $btn.data('vendor-id');

			$btn.prop('disabled', true);

			$.ajax({
				url: wpssVendors.ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_update_vendor_commission',
					nonce: wpssVendors.nonce,
					vendor_id: vendorId,
					reset: 'true'
				},
				success: function(response) {
					if (response.success) {
						$('#wpss-commission-status').html('<span style="color: #00a32a;">' + response.data.message + '</span>');
						// Reload modal content to update UI
						$('.wpss-view-vendor[data-vendor-id="' + vendorId + '"]').click();
					} else {
						wpssAdminNotice(response.data.message || i18n.error, 'error');
						$btn.prop('disabled', false);
					}
				},
				error: function() {
					wpssAdminNotice(i18n.error, 'error');
					$btn.prop('disabled', false);
				}
			});
		});
	});


	jQuery(function($) {
		// Define local config (script runs before footer where wpssVendors is defined).
		var ajaxUrl = wpssVendors.ajaxUrl;
		var nonce = wpssVendors.nonce;
		var i18n = {
			confirmStatusChange: wpssVendors.i18n.areYouSureYouWantToChangeThisVendorSStatus,
			error: wpssVendors.i18n.anErrorOccurredPleaseTryAgain
		};

		var vendorId = wpssVendors.vendorId || 0;
		var currentTab = 'overview';
		var tabCache = {};

		// Load initial tab.
		loadTab('overview');

		// Tab click handler.
		$('.wpss-detail-tab').on('click', function() {
			var tab = $(this).data('tab');
			if (tab === currentTab) {
				return;
			}

			$('.wpss-detail-tab').removeClass('active');
			$(this).addClass('active');
			currentTab = tab;

			loadTab(tab);
		});

		// Load tab content via AJAX.
		function loadTab(tab) {
			var $content = $('#wpss-tab-content');

			// Check cache.
			if (tabCache[tab]) {
				$content.html(tabCache[tab]);
				initTabHandlers(tab);
				return;
			}

			$content.html('<div class="wpss-tab-loading"><span class="spinner is-active"></span> ' + wpssVendors.i18n.loading + '</div>');

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_vendor_tab_content',
					nonce: nonce,
					vendor_id: vendorId,
					tab: tab
				},
				success: function(response) {
					if (response.success) {
						tabCache[tab] = response.data.html;
						$content.html(response.data.html);
						initTabHandlers(tab);
					} else {
						$content.html('<div class="notice notice-error"><p>' + (response.data.message || wpssVendors.i18n.failedToLoadContent) + '</p></div>');
					}
				},
				error: function() {
					$content.html('<div class="notice notice-error"><p>' + wpssVendors.i18n.failedToLoadContent + '</p></div>');
				}
			});
		}

		// Initialize handlers for specific tabs.
		function initTabHandlers(tab) {
			if (tab === 'settings') {
				initSettingsHandlers();
			} else if (tab === 'earnings') {
				initEarningsHandlers();
			} else if (tab === 'services') {
				initServicesHandlers();
			} else if (tab === 'orders') {
				initOrdersHandlers();
			} else if (tab === 'reviews') {
				initReviewsHandlers();
			} else if (tab === 'portfolio') {
				initPortfolioHandlers();
			}
		}

		// Settings tab handlers.
		function initSettingsHandlers() {
			// Commission rate save.
			$('#wpss-save-commission-detail').off('click').on('click', function() {
				var rate = $('#wpss-commission-rate-detail').val();
				var $btn = $(this);

				$btn.prop('disabled', true);

				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_update_vendor_commission',
						nonce: nonce,
						vendor_id: vendorId,
						rate: rate
					},
					success: function(response) {
						if (response.success) {
							$('#wpss-commission-detail-status').html('<span style="color: #00a32a;">' + response.message + '</span>');
							delete tabCache['settings'];
							delete tabCache['earnings'];
						} else {
							wpssAdminNotice(response.data.message || wpssVendors.i18n.errorUpdatingCommissionRate, 'error');
						}
						$btn.prop('disabled', false);
					},
					error: function() {
						wpssAdminNotice(wpssVendors.i18n.errorUpdatingCommissionRate, 'error');
						$btn.prop('disabled', false);
					}
				});
			});

			// Reset commission.
			$('#wpss-reset-commission-detail').off('click').on('click', function() {
				if (!confirm(wpssVendors.i18n.resetToGlobalCommissionRate)) {
					return;
				}

				var $btn = $(this);
				$btn.prop('disabled', true);

				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_update_vendor_commission',
						nonce: nonce,
						vendor_id: vendorId,
						reset: 'true'
					},
					success: function(response) {
						if (response.success) {
							delete tabCache['settings'];
							delete tabCache['earnings'];
							loadTab('settings');
						} else {
							wpssAdminNotice(response.data.message || wpssVendors.i18n.errorResettingCommissionRate, 'error');
						}
						$btn.prop('disabled', false);
					},
					error: function() {
						wpssAdminNotice(wpssVendors.i18n.errorResettingCommissionRate, 'error');
						$btn.prop('disabled', false);
					}
				});
			});

			// Vacation mode toggle (and return-date change re-saves with the
			// current toggle state so admins can set/clear the date directly).
			var saveVacation = function() {
				var enabled = $('#wpss-vacation-mode-toggle').is(':checked');
				var message = $('#wpss-vacation-message').val() || '';
				var returnDate = $('#wpss-vacation-return-date').val() || '';

				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_update_vendor_vacation',
						nonce: nonce,
						vendor_id: vendorId,
						enabled: enabled ? 1 : 0,
						message: message,
						return_date: returnDate
					},
					success: function(response) {
						if (response.success) {
							$('#wpss-vacation-status').html('<span style="color: #00a32a;">' + response.data.message + '</span>');
							delete tabCache['settings'];
							delete tabCache['overview'];
						} else {
							wpssAdminNotice(response.data.message || wpssVendors.i18n.errorUpdatingVacationMode, 'error');
						}
					}
				});
			};

			$('#wpss-vacation-mode-toggle').off('change').on('change', saveVacation);
			$('#wpss-vacation-return-date').off('change').on('change', saveVacation);

			// Availability toggle.
			$('#wpss-availability-toggle').off('change').on('change', function() {
				var available = $(this).is(':checked');

				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_update_vendor_availability',
						nonce: nonce,
						vendor_id: vendorId,
						available: available ? 1 : 0
					},
					success: function(response) {
						if (response.success) {
							$('#wpss-availability-status').html('<span style="color: #00a32a;">' + response.data.message + '</span>');
							delete tabCache['settings'];
							delete tabCache['overview'];
						} else {
							wpssAdminNotice(response.data.message || wpssVendors.i18n.errorUpdatingAvailability, 'error');
						}
					}
				});
			});

			// Seller-level override (admin manually sets the verification tier; bypasses auto-calc).
			$('#wpss-save-level-detail').off('click').on('click', function() {
				var level = $('#wpss-level-select-detail').val();
				var $btn = $(this);

				$btn.prop('disabled', true);

				$.ajax({
					url: ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_update_vendor_level',
						nonce: nonce,
						vendor_id: vendorId,
						level: level
					},
					success: function(response) {
						if (response.success) {
							$('#wpss-level-status').html('<span class="wpss-status-line__success"></span>').find('span').text(response.data.message);
							delete tabCache['settings'];
							delete tabCache['overview'];
						} else {
							wpssAdminNotice(response.data.message || wpssVendors.i18n.errorUpdatingSellerLevel, 'error');
						}
						$btn.prop('disabled', false);
					},
					error: function() {
						wpssAdminNotice(wpssVendors.i18n.errorUpdatingSellerLevel, 'error');
						$btn.prop('disabled', false);
					}
				});
			});
		}

		// Portfolio tab handlers (moderation: feature/unfeature/delete).
		function initPortfolioHandlers() {
			$('.wpss-portfolio-action').off('click').on('click', function() {
				var $btn = $(this);
				var itemId = $btn.data('item-id');
				var modAction = $btn.data('mod-action');

				function proceed() {
					$btn.prop('disabled', true);

					$.ajax({
						url: ajaxUrl,
						type: 'POST',
						data: {
							action: 'wpss_moderate_portfolio_item',
							nonce: nonce,
							vendor_id: vendorId,
							item_id: itemId,
							mod_action: modAction
						},
						success: function(response) {
							if (response.success) {
								delete tabCache['portfolio'];
								$('#wpss-tab-content').html(response.data.html);
								initPortfolioHandlers();
							} else {
								wpssAdminNotice(response.data.message || wpssVendors.i18n.errorModeratingPortfolioItem, 'error');
								$btn.prop('disabled', false);
							}
						},
						error: function() {
							wpssAdminNotice(wpssVendors.i18n.errorModeratingPortfolioItem, 'error');
							$btn.prop('disabled', false);
						}
					});
				}

				if (modAction === 'delete') {
					window.wpssConfirm(wpssVendors.i18n.permanentlyRemoveThisPortfolioItem, { tone: 'danger' }).then(function(ok) {
						if (ok) { proceed(); }
					});
					return;
				}

				proceed();
			});
		}

		// Earnings tab handlers (pagination).
		function initEarningsHandlers() {
			$('.wpss-withdrawals-pagination a').off('click').on('click', function(e) {
				e.preventDefault();
				var page = $(this).data('page');
				loadWithdrawalsPage(page);
			});
		}

		function loadWithdrawalsPage(page) {
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_vendor_tab_content',
					nonce: nonce,
					vendor_id: vendorId,
					tab: 'earnings',
					withdrawals_page: page
				},
				success: function(response) {
					if (response.success) {
						$('#wpss-tab-content').html(response.data.html);
						initEarningsHandlers();
					}
				}
			});
		}

		// Services tab handlers (pagination).
		function initServicesHandlers() {
			$('.wpss-services-page').off('click').on('click', function(e) {
				e.preventDefault();
				var page = $(this).data('page');
				loadServicesPage(page);
			});
		}

		function loadServicesPage(page) {
			$('#wpss-tab-content').html('<div class="wpss-tab-loading"><span class="spinner is-active"></span> ' + wpssVendors.i18n.loading + '</div>');
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_vendor_tab_content',
					nonce: nonce,
					vendor_id: vendorId,
					tab: 'services',
					services_page: page
				},
				success: function(response) {
					if (response.success) {
						delete tabCache['services'];
						$('#wpss-tab-content').html(response.data.html);
						initServicesHandlers();
					}
				}
			});
		}

		// Orders tab handlers (pagination and filter).
		function initOrdersHandlers() {
			$('.wpss-orders-page').off('click').on('click', function(e) {
				e.preventDefault();
				var page = $(this).data('page');
				var status = $('#wpss-order-status-filter').val();
				loadOrdersPage(page, status);
			});

			$('#wpss-order-status-filter').off('change').on('change', function() {
				var status = $(this).val();
				loadOrdersPage(1, status);
			});
		}

		function loadOrdersPage(page, status) {
			$('#wpss-tab-content').html('<div class="wpss-tab-loading"><span class="spinner is-active"></span> ' + wpssVendors.i18n.loading + '</div>');
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_vendor_tab_content',
					nonce: nonce,
					vendor_id: vendorId,
					tab: 'orders',
					orders_page: page,
					order_status: status || ''
				},
				success: function(response) {
					if (response.success) {
						delete tabCache['orders'];
						$('#wpss-tab-content').html(response.data.html);
						initOrdersHandlers();
					}
				}
			});
		}

		// Reviews tab handlers (pagination).
		function initReviewsHandlers() {
			$('.wpss-reviews-page').off('click').on('click', function(e) {
				e.preventDefault();
				var page = $(this).data('page');
				loadReviewsPage(page);
			});
		}

		function loadReviewsPage(page) {
			$('#wpss-tab-content').html('<div class="wpss-tab-loading"><span class="spinner is-active"></span> ' + wpssVendors.i18n.loading + '</div>');
			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_vendor_tab_content',
					nonce: nonce,
					vendor_id: vendorId,
					tab: 'reviews',
					reviews_page: page
				},
				success: function(response) {
					if (response.success) {
						delete tabCache['reviews'];
						$('#wpss-tab-content').html(response.data.html);
						initReviewsHandlers();
					}
				}
			});
		}

		// Status change dropdown.
		$('#wpss-vendor-status-select').on('change', function() {
			var newStatus = $(this).val();
			if (!newStatus) {
				return;
			}

			if (!confirm(i18n.confirmStatusChange)) {
				$(this).val('');
				return;
			}

			$.ajax({
				url: ajaxUrl,
				type: 'POST',
				data: {
					action: 'wpss_update_vendor_status',
					nonce: nonce,
					vendor_id: vendorId,
					status: newStatus
				},
				success: function(response) {
					if (response.success) {
						location.reload();
					} else {
						wpssAdminNotice(response.data.message || i18n.error, 'error');
					}
				},
				error: function() {
					wpssAdminNotice(i18n.error, 'error');
				}
			});
		});
	});

}( jQuery ) );
