/**
 * Admin Manual Order - AJAX interactions and live pricing.
 *
 * @package WPSellServices
 * @since   1.4.0
 */

/* global jQuery, wpssManualOrder */
(function ($) {
	'use strict';

	// Admin notice helper - replaces alert() with WordPress-style notices.
	function wpssAdminNotice(msg, type) {
		type = type || 'error';
		var cls = type === 'success' ? 'notice-success' : 'notice-error';
		var $notice = $('<div class="notice ' + cls + ' is-dismissible"><p>' + msg + '</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button></div>');
		$('.wrap h1, .wrap h2').first().after($notice);
		$notice.find('.notice-dismiss').on('click', function() { $notice.fadeOut(200, function() { $notice.remove(); }); });
		setTimeout(function() { $notice.fadeOut(400, function() { $notice.remove(); }); }, 6000);
	}

	var state = {
		subtotal: 0,
		deliveryDays: 0,
		revisions: 0,
		overrideTotal: false,
		commissionRate: parseFloat(wpssManualOrder.defaultCommissionRate) || 10,
	};

	var $form = $('#wpss-manual-order-form');
	var $result = $('#wpss-order-result');


	/**
	 * Update the pricing summary from the server.
	 *
	 * Priced by ManualOrderPage::price_manual_order() - the same calculation
	 * Create Order saves - so the screen shows the tax and the total the order
	 * will carry. It used to add prices up here, without tax, and said $55.00
	 * for an order saved at $64.90.
	 */
	var quoteTimer = null;
	var quoteRequest = 0;

	function updatePricingSummary() {
		$('#wpss-calculated-subtotal').val(state.subtotal.toFixed(2));

		clearTimeout(quoteTimer);
		quoteTimer = setTimeout(function () {
			if (!$('#wpss-service-id').val()) {
				return;
			}

			var request = ++quoteRequest;
			var data = $form.serializeArray().filter(function (field) {
				return field.name !== 'action' && field.name !== 'nonce';
			});
			data.push({ name: 'action', value: 'wpss_manual_order_quote' });
			data.push({ name: 'nonce', value: wpssManualOrder.nonce });

			$('#wpss-summary-total').attr('aria-busy', 'true');

			$.post(wpssManualOrder.ajaxUrl, $.param(data), function (response) {
				if (request !== quoteRequest) {
					return; // A newer change is already being priced.
				}

				$('#wpss-summary-total').removeAttr('aria-busy');

				if (!response.success) {
					$('#wpss-summary-total').text('-');
					return;
				}

				var q = response.data;
				$('#wpss-summary-subtotal').text(q.subtotal);
				$('#wpss-summary-addons').text(q.addons_total);
				$('#wpss-pricing-addons-row').toggle(!!q.has_addons);
				$('#wpss-summary-tax-label').text(q.tax_label);
				$('#wpss-summary-tax').text(q.tax);
				$('#wpss-pricing-tax-row').toggle(!!q.has_tax);
				$('#wpss-summary-total').text(q.total);
				$('#wpss-summary-platform-fee').text(q.platform_fee);
				$('#wpss-summary-vendor-earnings').text(q.vendor_earnings);
			});
		}, 250);
	}

	/**
	 * Add-on choices changed: re-price on the server.
	 */
	function recalculateAddons() {
		updatePricingSummary();
	}

	/**
	 * Load packages for selected service.
	 */
	function loadPackages(serviceId) {
		var $packageRow = $('#wpss-package-row');
		var $packageSelect = $('#wpss-package-id');
		var $addonsContainer = $('#wpss-addons-container');

		if (!serviceId) {
			$packageRow.hide();
			$addonsContainer.hide();
			state.subtotal = 0;
			updatePricingSummary();
			return;
		}

		$packageSelect.html(
			'<option value="">' + wpssManualOrder.i18n.loadingPackages + '</option>'
		);
		$packageRow.show();

		$.ajax({
			url: wpssManualOrder.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpss_get_service_packages',
				service_id: serviceId,
				nonce: wpssManualOrder.nonce,
			},
			success: function (response) {
				if (
					response.success &&
					response.data.packages &&
					response.data.packages.length > 0
				) {
					var options =
						'<option value="">' +
						wpssManualOrder.i18n.selectPackage +
						'</option>';
					$.each(response.data.packages, function (i, pkg) {
						options +=
							'<option value="' +
							pkg.id +
							'"' +
							' data-price="' +
							pkg.price +
							'"' +
							' data-delivery="' +
							pkg.delivery_days +
							'"' +
							' data-revisions="' +
							pkg.revisions +
							'">' +
							pkg.name +
							' - ' +
							pkg.formatted_price +
							' (' +
							pkg.delivery_days +
							' days)' +
							'</option>';
					});
					$packageSelect.html(options);
					$packageRow.show();
				} else {
					$packageRow.hide();
					// Use starting price from service.
					var startPrice =
						parseFloat(
							$('#wpss-service-id option:selected').data('price')
						) || 0;
					state.subtotal = startPrice;
					updatePricingSummary();
				}
			},
		});

		// Load addons.
		loadAddons(serviceId);
	}

	/**
	 * Load addons for selected service.
	 */
	function loadAddons(serviceId) {
		var $container = $('#wpss-addons-container');
		var $list = $('#wpss-addons-list');

		if (!serviceId) {
			$container.hide();
			return;
		}

		$list.html(
			'<div class="wpss-addons-loading">' +
				wpssManualOrder.i18n.loadingAddons +
				'</div>'
		);
		$container.show();

		$.ajax({
			url: wpssManualOrder.ajaxUrl,
			type: 'POST',
			data: {
				action: 'wpss_get_service_addons',
				service_id: serviceId,
				nonce: wpssManualOrder.nonce,
			},
			success: function (response) {
				if (
					response.success &&
					response.data.addons &&
					response.data.addons.length > 0
				) {
					var html = '';
					$.each(response.data.addons, function (i, addon) {
						html += buildAddonHtml(addon);
					});
					$list.html(html);
				} else {
					$list.html(
						'<div class="wpss-addons-empty">' +
							wpssManualOrder.i18n.noAddons +
							'</div>'
					);
				}
			},
			error: function () {
				$list.html(
					'<div class="wpss-addons-empty">' +
						wpssManualOrder.i18n.noAddons +
						'</div>'
				);
			},
		});
	}

	/**
	 * Build HTML for a single addon item.
	 */
	function buildAddonHtml(addon) {
		var priceDisplay = addon.price_label || addon.formatted_price;
		var choosesByValue = addon.field_type === 'dropdown' || addon.field_type === 'text';

		var html =
			'<div class="wpss-addon-item"' +
			' data-addon-id="' +
			addon.id +
			'"' +
			' data-price="' +
			addon.price +
			'"' +
			' data-field-type="' +
			addon.field_type +
			'"' +
			' data-price-type="' +
			addon.price_type +
			'"' +
			' data-delivery-extra="' +
			addon.delivery_days_extra +
			'">';

		// A dropdown or text add-on is chosen by its value: the server charges
		// it only when an option or text is given.
		html += choosesByValue
			? '<input type="hidden" name="addons[' + addon.id + '][selected]" value="1">'
			: '<div class="wpss-addon-checkbox">' +
				'<input type="checkbox" name="addons[' +
				addon.id +
				'][selected]" value="1"' +
				(addon.is_required ? ' checked disabled' : '') +
				'>' +
				(addon.is_required
					? '<input type="hidden" name="addons[' +
						addon.id +
						'][selected]" value="1">'
					: '') +
				'</div>';

		html +=
			'<div class="wpss-addon-info">' +
			'<div class="wpss-addon-title">' +
			escapeHtml(addon.title) +
			(addon.is_required ? ' <em>(Required)</em>' : '') +
			'</div>';

		if (addon.description) {
			html +=
				'<div class="wpss-addon-desc">' +
				escapeHtml(addon.description) +
				'</div>';
		}

		html += '</div>';

		if (addon.field_type === 'quantity') {
			html +=
				'<div class="wpss-addon-qty">' +
				'<input type="number" name="addons[' +
				addon.id +
				'][quantity]"' +
				' value="' +
				(addon.min_quantity || 1) +
				'"' +
				' min="' +
				(addon.min_quantity || 1) +
				'"' +
				' max="' +
				(addon.max_quantity || 10) +
				'"' +
				' step="1">' +
				'</div>';
		}

		if (addon.field_type === 'dropdown') {
			var options = String(addon.options || '').split(',').map(function (o) { return o.trim(); }).filter(Boolean);
			html += '<div class="wpss-addon-qty"><select name="addons[' + addon.id + '][option]">' +
				'<option value="">' + escapeHtml(addon.is_required ? wpssManualOrder.i18n.chooseOne : wpssManualOrder.i18n.none) + '</option>' +
				options.map(function (o) { return '<option value="' + escapeHtml(o) + '">' + escapeHtml(o) + '</option>'; }).join('') +
				'</select></div>';
		} else if (addon.field_type === 'text') {
			html += '<div class="wpss-addon-qty"><textarea rows="2" name="addons[' + addon.id + '][text]"></textarea></div>';
		}

		html +=
			'<div class="wpss-addon-price">' +
			escapeHtml(priceDisplay) +
			'</div>';

		html += '</div>';
		return html;
	}

	/**
	 * Escape HTML entities.
	 */
	function escapeHtml(str) {
		var div = document.createElement('div');
		div.appendChild(document.createTextNode(str));
		return div.innerHTML;
	}

	/**
	 * Load vendor commission rate.
	 */
	function loadVendorCommissionRate(vendorId) {
		if (!vendorId) {
			$('#wpss-commission-rate').val(state.commissionRate);
			updatePricingSummary();
			return;
		}

		// For manual order, we just use the default. If vendor override selected,
		// we could load their custom rate, but for simplicity keep it editable.
		updatePricingSummary();
	}

	// --- Event Handlers ---

	// Service change → load packages + addons + auto-fill vendor.
	$('#wpss-service-id').on('change', function () {
		var $selected = $(this).find(':selected');
		var serviceId = $(this).val();
		var vendorId = $selected.data('vendor');

		// Auto-fill vendor.
		if (vendorId && $('#wpss-vendor-id').length) {
			$('#wpss-vendor-id').val(vendorId);
		}

		// Reset state.
		state.subtotal = 0;

		loadPackages(serviceId);
		loadVendorCommissionRate(vendorId);
	});

	// Package change → update subtotal, delivery_days, revisions.
	$('#wpss-package-id').on('change', function () {
		var $selected = $(this).find(':selected');

		if (!$selected.val()) {
			// No package selected, use service starting price.
			var startPrice =
				parseFloat(
					$('#wpss-service-id option:selected').data('price')
				) || 0;
			state.subtotal = startPrice;
		} else {
			state.subtotal = parseFloat($selected.data('price')) || 0;
			state.deliveryDays = parseInt($selected.data('delivery'), 10) || 0;
			state.revisions = parseInt($selected.data('revisions'), 10) || 0;

			$('#wpss-delivery-days').val(state.deliveryDays);
			$('#wpss-revisions').val(state.revisions);
		}

		recalculateAddons(); // This also calls updatePricingSummary.
	});

	// Addon checkbox toggle → recalculate.
	$(document).on('change', '.wpss-addon-checkbox input', function () {
		recalculateAddons();
	});

	// Addon quantity change → recalculate.
	$(document).on('change input', '.wpss-addon-qty input, .wpss-addon-qty select, .wpss-addon-qty textarea', function () {
		recalculateAddons();
	});

	// Total override toggle.
	$('#wpss-override-total').on('change', function () {
		state.overrideTotal = $(this).is(':checked');
		$('#wpss-total-override')
			.prop('disabled', !state.overrideTotal)
			.toggleClass('wpss-disabled', !state.overrideTotal);
		updatePricingSummary();
	});

	// Manual total change.
	$('#wpss-total-override').on('input change', function () {
		if (state.overrideTotal) {
			updatePricingSummary();
		}
	});

	// Commission rate change → recalculate.
	$('#wpss-commission-rate').on('input change', function () {
		updatePricingSummary();
	});

	// Vendor override change → update commission.
	$('#wpss-vendor-id').on('change', function () {
		loadVendorCommissionRate($(this).val());
	});

	// Form submit.
	$form.on('submit', function (e) {
		e.preventDefault();

		var $submitBtn = $('#wpss-create-order-btn');
		var $spinner = $form.find('.spinner');

		$submitBtn.prop('disabled', true);
		$spinner.addClass('is-active');

		$.ajax({
			url: wpssManualOrder.ajaxUrl,
			type: 'POST',
			data: $form.serialize() + '&action=wpss_create_manual_order&nonce=' + wpssManualOrder.nonce,
			success: function (response) {
				if (response.success) {
					// Build the success message via safe DOM construction —
					// `order_number` and `order_id` come from the server response
					// and would normally be safe, but rendering them via `.text()`
					// rather than `.html()` keeps this hardened against any future
					// breach where a malicious order_number could land here. The
					// "<br><br><strong>…</strong>" structural HTML is added via
					// jQuery element creation, not string concatenation.
					var $msg = $('#wpss-result-message').empty();
					$msg.text(
						wpssManualOrder.i18n.orderCreated
							.replace('%1$s', response.data.order_number)
							.replace('%2$d', response.data.order_id)
					);

					if (response.data.requirements_skipped) {
						$msg.append('<br><br>').append(
							$('<strong/>').text(
								wpssManualOrder.i18n.requirementsSkipped
							)
						);
					}
					$('#wpss-view-order-link').attr(
						'href',
						response.data.view_url
					);

					if (response.data.requirements_url) {
						$('#wpss-requirements-link')
							.attr('href', response.data.requirements_url)
							.toggle(
								response.data.status ===
									'pending_requirements' &&
									response.data.has_requirements
							);
					}

					$form.hide();
					$result.show();
				} else {
					wpssAdminNotice(
						response.data.message ||
							wpssManualOrder.i18n.createFailed,
						'error'
					);
				}
			},
			error: function () {
				wpssAdminNotice(wpssManualOrder.i18n.createError, 'error');
			},
			complete: function () {
				$submitBtn.prop('disabled', false);
				$spinner.removeClass('is-active');
			},
		});
	});

	// Create another.
	$('#wpss-create-another-btn').on('click', function () {
		$form[0].reset();
		$('#wpss-package-row').hide();
		$('#wpss-addons-container').hide();
		state.subtotal = 0;
		state.overrideTotal = false;
		$('#wpss-total-override').prop('disabled', true);
		updatePricingSummary();
		$result.hide();
		$form.show();
	});

	// Initialize.
	updatePricingSummary();
})(jQuery);
