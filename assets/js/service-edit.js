/**
 * Service Edit Screen JavaScript
 *
 * Handles tabbed interface and wizard mode for service editing.
 *
 * @package WPSellServices
 * @since 1.0.0
 */

(function($) {
	'use strict';

	/**
	 * Service Data Tabs Controller
	 */
	var ServiceDataTabs = {

		/**
		 * Initialize the tabs/wizard system.
		 */
		init: function() {
			var $wrap = $('.wpss-service-data-wrap');
			if (!$wrap.length) {
				return;
			}

			var viewMode = $wrap.data('view-mode');

			if (viewMode === 'wizard') {
				this.initWizardMode($wrap);
			} else {
				this.initTabsMode($wrap);
			}
		},

		/**
		 * Initialize tabs mode for existing services.
		 *
		 * @param {jQuery} $wrap Main wrapper element.
		 */
		initTabsMode: function($wrap) {
			var self = this;
			var $tabs = $wrap.find('.wpss-service-tabs li');
			var $panels = $wrap.find('.wpss-panel');
			var storageKey = 'wpss_active_tab';

			// Get saved tab or default to first.
			var activeTab = localStorage.getItem(storageKey) || 'overview';

			// Validate the saved tab exists.
			if (!$tabs.filter('[data-tab="' + activeTab + '"]').length) {
				activeTab = 'overview';
			}

			// Activate initial tab.
			self.activateTab($tabs, $panels, activeTab);

			// Tab click handler.
			$tabs.on('click', 'a', function(e) {
				e.preventDefault();
				var tabKey = $(this).closest('li').data('tab');
				self.activateTab($tabs, $panels, tabKey);
				localStorage.setItem(storageKey, tabKey);
			});

			// Keyboard navigation.
			$tabs.on('keydown', 'a', function(e) {
				var $current = $(this).closest('li');
				var $target = null;

				if (e.keyCode === 40) { // Down arrow
					e.preventDefault();
					$target = $current.next('li');
				} else if (e.keyCode === 38) { // Up arrow
					e.preventDefault();
					$target = $current.prev('li');
				}

				if ($target && $target.length) {
					$target.find('a').focus().trigger('click');
				}
			});
		},

		/**
		 * Activate a specific tab.
		 *
		 * @param {jQuery} $tabs   All tab elements.
		 * @param {jQuery} $panels All panel elements.
		 * @param {string} tabKey  Tab key to activate.
		 */
		activateTab: function($tabs, $panels, tabKey) {
			$tabs.removeClass('active');
			$tabs.filter('[data-tab="' + tabKey + '"]').addClass('active');

			$panels.removeClass('active');
			$panels.filter('#wpss_' + tabKey + '_panel').addClass('active');

			// Trigger custom event for other scripts.
			$(document).trigger('wpss_tab_changed', [tabKey]);
		},

		/**
		 * Initialize wizard mode for new services.
		 *
		 * @param {jQuery} $wrap Main wrapper element.
		 */
		initWizardMode: function($wrap) {
			var self = this;
			var $steps = $wrap.find('.wpss-wizard-step');
			var $panels = $wrap.find('.wpss-panel');
			var $prevBtn = $wrap.find('.wpss-wizard-prev');
			var $nextBtn = $wrap.find('.wpss-wizard-next');
			var $skipBtn = $wrap.find('.wpss-wizard-skip');

			var stepKeys = [];
			$steps.each(function() {
				stepKeys.push($(this).data('step'));
			});

			var currentStep = 0;
			var totalSteps = stepKeys.length;

			// Initial step.
			self.updateWizardStep($steps, $panels, stepKeys, currentStep, $prevBtn, $nextBtn);

			// Previous button.
			$prevBtn.on('click', function() {
				if (currentStep > 0) {
					currentStep--;
					self.updateWizardStep($steps, $panels, stepKeys, currentStep, $prevBtn, $nextBtn);
				}
			});

			// Next button.
			$nextBtn.on('click', function() {
				// Mark current step as completed.
				$steps.eq(currentStep).addClass('completed');

				if (currentStep < totalSteps - 1) {
					currentStep++;
					self.updateWizardStep($steps, $panels, stepKeys, currentStep, $prevBtn, $nextBtn);
				} else {
					// On last step, change button text to indicate completion.
					self.completeWizard($wrap);
				}
			});

			// Skip to full editor.
			$skipBtn.on('click', function() {
				// Save the post to convert from auto-draft.
				$('#publish').trigger('click');
			});
		},

		/**
		 * Update wizard step display.
		 *
		 * @param {jQuery} $steps   All step elements.
		 * @param {jQuery} $panels  All panel elements.
		 * @param {Array}  stepKeys Array of step keys.
		 * @param {number} index    Current step index.
		 * @param {jQuery} $prevBtn Previous button.
		 * @param {jQuery} $nextBtn Next button.
		 */
		updateWizardStep: function($steps, $panels, stepKeys, index, $prevBtn, $nextBtn) {
			var currentKey = stepKeys[index];
			var isLastStep = index === stepKeys.length - 1;

			// Update step indicators.
			$steps.removeClass('active');
			$steps.eq(index).addClass('active');

			// Update panels.
			$panels.removeClass('active');
			$panels.filter('#wpss_' + currentKey + '_panel').addClass('active');

			// Update navigation buttons.
			$prevBtn.prop('disabled', index === 0);

			if (isLastStep) {
				$nextBtn.text(wpssServiceEdit.i18n.finish || 'Finish');
			} else {
				$nextBtn.text(wpssServiceEdit.i18n.next || 'Next');
			}

			// Trigger custom event.
			$(document).trigger('wpss_wizard_step_changed', [currentKey, index]);
		},

		/**
		 * Complete the wizard and trigger save.
		 *
		 * @param {jQuery} $wrap Main wrapper element.
		 */
		completeWizard: function($wrap) {
			// Trigger the publish button to save the service.
			$('#publish').trigger('click');
		}
	};

	/**
	 * DOM Ready
	 */
	$(document).ready(function() {
		ServiceDataTabs.init();
	});

	// Expose for external access.
	window.WPSSServiceDataTabs = ServiceDataTabs;

})(jQuery);
