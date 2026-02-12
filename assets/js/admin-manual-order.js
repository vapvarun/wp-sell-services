/**
 * Admin Manual Order - AJAX interactions and live pricing.
 *
 * @package WPSellServices
 * @since   1.4.0
 */

/* global jQuery, wpssManualOrder */
(function ($) {
	'use strict';

	var state = {
		subtotal: 0,
		addonsTotal: 0,
		deliveryDays: 0,
		revisions: 0,
		overrideTotal: false,
		commissionRate: parseFloat(wpssManualOrder.defaultCommissionRate) || 10,
	};

	var $form = $('#wpss-manual-order-form');
	var $result = $('#wpss-order-result');

	/**
	 * Format price using site currency format.
	 */
	function formatPrice(amount) {
		var num = parseFloat(amount) || 0;
		return wpssManualOrder.currencyFormat.replace('%s', num.toFixed(2));
	}

	/**
	 * Update pricing summary display.
	 */
	function updatePricingSummary() {
		var total;

		if (state.overrideTotal) {
			total = parseFloat($('#wpss-total-override').val()) || 0;
		} else {
			total = state.subtotal + state.addonsTotal;
		}

		var commissionRate = parseFloat($('#wpss-commission-rate').val()) || state.commissionRate;
		var platformFee = Math.round(total * (commissionRate / 100) * 100) / 100;
		var vendorEarnings = Math.round((total - platformFee) * 100) / 100;

		$('#wpss-summary-subtotal').text(formatPrice(state.subtotal));
		$('#wpss-summary-addons').text(formatPrice(state.addonsTotal));
		$('#wpss-summary-total').text(formatPrice(total));
		$('#wpss-summary-platform-fee').text(formatPrice(platformFee));
		$('#wpss-summary-vendor-earnings').text(formatPrice(vendorEarnings));

		// Update hidden fields.
		$('#wpss-calculated-subtotal').val(state.subtotal.toFixed(2));
		$('#wpss-calculated-addons-total').val(state.addonsTotal.toFixed(2));
		$('#wpss-calculated-total').val(total.toFixed(2));
		$('#wpss-calculated-platform-fee').val(platformFee.toFixed(2));
		$('#wpss-calculated-vendor-earnings').val(vendorEarnings.toFixed(2));

		// Show/hide addons row.
		$('#wpss-pricing-addons-row').toggle(state.addonsTotal > 0);
	}

	/**
	 * Recalculate addon totals from checked items.
	 */
	function recalculateAddons() {
		var total = 0;

		$('.wpss-addon-item').each(function () {
			var $item = $(this);
			var $checkbox = $item.find('.wpss-addon-checkbox input');

			if (!$checkbox.is(':checked')) {
				return;
			}

			var price = parseFloat($item.data('price')) || 0;
			var fieldType = $item.data('field-type');
			var priceType = $item.data('price-type');

			if (fieldType === 'quantity') {
				var qty = parseInt($item.find('.wpss-addon-qty input').val(), 10) || 1;
				price = price * qty;
			}

			if (priceType === 'percentage') {
				price = (state.subtotal * price) / 100;
			}

			total += price;
		});

		state.addonsTotal = Math.round(total * 100) / 100;
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
			state.addonsTotal = 0;
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
		var priceDisplay = addon.formatted_price;
		if (addon.price_type === 'percentage') {
			priceDisplay = addon.price + '%';
		}

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

		html +=
			'<div class="wpss-addon-checkbox">' +
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
		state.addonsTotal = 0;

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
	$(document).on('change input', '.wpss-addon-qty input', function () {
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
					var message =
						wpssManualOrder.i18n.orderCreated
							.replace('%1$s', response.data.order_number)
							.replace('%2$d', response.data.order_id);

					if (response.data.requirements_skipped) {
						message +=
							'<br><br><strong>' +
							wpssManualOrder.i18n.requirementsSkipped +
							'</strong>';
					}

					$('#wpss-result-message').html(message);
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
					alert(
						response.data.message ||
							wpssManualOrder.i18n.createFailed
					);
				}
			},
			error: function () {
				alert(wpssManualOrder.i18n.createError);
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
		state.addonsTotal = 0;
		state.overrideTotal = false;
		$('#wpss-total-override').prop('disabled', true);
		updatePricingSummary();
		$result.hide();
		$form.show();
	});

	// Initialize.
	updatePricingSummary();
})(jQuery);
