/**
 * Setup Wizard — step navigation, per-step save, category creation, demo import.
 *
 * Extracted from a 262-line inline <script> in SetupWizardPage.php (ux-audit
 * F2). All PHP interpolation (4 nonces/URL + 8 button labels) now arrives via
 * wp_localize_script as window.wpssWizard.
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
		var ajaxUrl = wpssWizard.ajaxUrl;
		var wizardNonce = wpssWizard.wizardNonce;
		var settingsNonce = wpssWizard.settingsNonce;
		var demoNonce = wpssWizard.demoNonce;
		var totalSteps = 5;
		var currentStep = 1;

		function updateIndicator() {
			var html = '';
			for (var i = 1; i <= totalSteps; i++) {
				var cls = 'step-dot';
				if (i === currentStep) cls += ' active';
				else if (i < currentStep) cls += ' done';
				html += '<div class="' + cls + '"></div>';
			}
			$('#wpss-steps-indicator').html(html);
		}

		function goToStep(step) {
			$('.wpss-wizard-step').removeClass('active');
			$('.wpss-wizard-step[data-step="' + step + '"]').addClass('active');
			currentStep = step;
			updateIndicator();
			$('#wpss-wizard-wrap').scrollTop(0);

			// If arriving at the final step, mark complete (with validation).
			if (step === 5) {
				$.post(ajaxUrl, { action: 'wpss_wizard_complete', nonce: wizardNonce }, function(response) {
					if (!response.success && response.data && response.data.message) {
						wpssAdminNotice(response.data.message, 'error');
						goToStep(2);
					}
				});
			}
		}

		updateIndicator();

		// Skip buttons: just advance step (no AJAX).
		$(document).on('click', '.wpss-wizard-skip', function() {
			goToStep(parseInt($(this).data('skip'), 10) + 1);
		});

		// Back buttons.
		$(document).on('click', '.wpss-wizard-back', function() {
			goToStep(parseInt($(this).data('back'), 10));
		});

		// Next buttons (no save, just advance).
		$(document).on('click', '.wpss-wizard-next', function() {
			goToStep(parseInt($(this).data('next'), 10));
		});

		// Save & Continue: steps 1 (basics) and 4 (vendor) via wpss_wizard_save_step;
		// step 3 (categories) uses its own category-create AJAX.
		$(document).on('click', '.wpss-wizard-save', function() {
			var btn = $(this);
			var step = parseInt(btn.data('step'), 10);
			var data = { action: 'wpss_wizard_save_step', nonce: wizardNonce, step: step };

			btn.prop('disabled', true);

			if (step === 1) {
				data.platform_name = $('#wpss-wiz-name').val();
				data.currency = $('#wpss-wiz-currency').val();
				data.commission_rate = $('#wpss-wiz-commission').val();
			} else if (step === 3) {
				// Categories: collect selected chip names.
				var cats = [];
				$('.wpss-wizard-chip.active:not(.disabled)').each(function() {
					cats.push($(this).data('name'));
				});
				if (cats.length === 0) {
					btn.prop('disabled', false);
					goToStep(4);
					return;
				}
				// Use category-specific AJAX.
				$.post(ajaxUrl, {
					action: 'wpss_wizard_create_categories',
					nonce: wizardNonce,
					categories: cats
				}, function() {
					btn.prop('disabled', false);
					goToStep(4);
				}).fail(function() {
					btn.prop('disabled', false);
				});
				return;
			} else if (step === 4) {
				data.vendor_registration = $('input[name="wpss_vendor_reg"]:checked').val();
				data.max_services_per_vendor = $('#wpss-wiz-max-services').val();
				data.require_service_moderation = $('#wpss-wiz-moderation').is(':checked') ? 1 : 0;
			}

			$.post(ajaxUrl, data, function() {
				btn.prop('disabled', false);
				goToStep(step + 1);
			}).fail(function() {
				btn.prop('disabled', false);
			});
		});

		// Gateway radio: show/hide panels.
		$('input[name="wpss_gateway"]').on('change', function() {
			$('.wpss-wizard-gateway-panel').hide();
			$('.wpss-wizard-gateway-panel[data-gateway="' + $(this).val() + '"]').show();
		});

		// Category chips: toggle selection.
		$(document).on('click', '.wpss-wizard-chip:not(.disabled)', function() {
			$(this).toggleClass('active');
		});

		// Custom category add.
		$('#wpss-wiz-add-cat').on('click', function() {
			var input = $('#wpss-wiz-custom-cat');
			var name = $.trim(input.val());
			if (!name) return;

			// Check if chip already exists.
			var exists = false;
			$('.wpss-wizard-chip').each(function() {
				if ($(this).data('name').toLowerCase() === name.toLowerCase()) {
					exists = true;
					$(this).addClass('active');
					return false;
				}
			});

			if (!exists) {
				$('#wpss-wizard-chips').append(
					'<button type="button" class="wpss-wizard-chip active" data-name="' + $('<div>').text(name).html() + '">' +
					$('<span>').text(name).html() +
					'</button>'
				);
			}
			input.val('');
		});

		// Enter key for custom category.
		$('#wpss-wiz-custom-cat').on('keypress', function(e) {
			if (e.which === 13) {
				e.preventDefault();
				$('#wpss-wiz-add-cat').click();
			}
		});

		// Create single page (reuses existing wpss_create_page handler).
		$(document).on('click', '.wpss-wizard-create-page:not(:disabled)', function() {
			var btn = $(this);
			var row = btn.closest('.wpss-wizard-page-row');
			var field = btn.data('field');
			var title = btn.data('title');

			btn.prop('disabled', true).text(wpssWizard.i18n.creating);

			$.post(ajaxUrl, {
				action: 'wpss_create_page',
				nonce: settingsNonce,
				field: field,
				title: title
			}, function(response) {
				if (response.success) {
					btn.text(wpssWizard.i18n.done);
					row.find('.wpss-wizard-badge')
						.removeClass('wpss-badge-pending')
						.addClass('wpss-badge-success')
						.text(wpssWizard.i18n.created);
				} else {
					btn.prop('disabled', false).text(wpssWizard.i18n.create);
				}
			}).fail(function() {
				btn.prop('disabled', false).text(wpssWizard.i18n.create);
			});
		});

		// Create all pages (sequentially to avoid race condition on wpss_pages option).
		$('#wpss-wizard-create-all-pages').on('click', function() {
			var allBtn = $(this);
			var buttons = $('.wpss-wizard-create-page:not(:disabled)').toArray();
			allBtn.prop('disabled', true).text(wpssWizard.i18n.creating);

			function createNext(index) {
				if (index >= buttons.length) {
					allBtn.text(wpssWizard.i18n.allCreated);
					return;
				}
				var btn = $(buttons[index]);
				var row = btn.closest('.wpss-wizard-page-row');
				var field = btn.data('field');
				var title = btn.data('title');

				btn.prop('disabled', true).text(wpssWizard.i18n.creating);

				$.post(ajaxUrl, {
					action: 'wpss_create_page',
					nonce: settingsNonce,
					field: field,
					title: title
				}, function(response) {
					if (response.success) {
						btn.text(wpssWizard.i18n.done);
						row.find('.wpss-wizard-badge')
							.removeClass('wpss-badge-pending')
							.addClass('wpss-badge-success')
							.text(wpssWizard.i18n.created);
					} else {
						btn.prop('disabled', false).text(wpssWizard.i18n.create);
					}
					createNext(index + 1);
				}).fail(function() {
					btn.prop('disabled', false).text(wpssWizard.i18n.create);
					createNext(index + 1);
				});
			}

			createNext(0);
		});

		// Import demo content.
		$('#wpss-wizard-import-demo').on('click', function(e) {
			e.preventDefault();
			var card = $(this);
			card.find('strong').text(wpssWizard.i18n.importing);

			$.post(ajaxUrl, {
				action: 'wpss_import_demo_content',
				nonce: demoNonce
			}, function(response) {
				if (response.success) {
					card.find('strong').text(wpssWizard.i18n.demoImported);
					card.find('span:last').text(response.data.message);
				} else {
					card.find('strong').text(wpssWizard.i18n.importFailed);
				}
			}).fail(function() {
				card.find('strong').text(wpssWizard.i18n.importFailed);
			});
		});
	});

}( jQuery ) );
