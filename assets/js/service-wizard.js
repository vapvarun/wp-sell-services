/**
 * WP Sell Services - Service Creation Wizard
 *
 * Alpine.js component for the 6-step service creation wizard.
 *
 * @package WPSellServices
 * @since   1.0.0
 */

/* global wpssWizard, wp */

/**
 * Service Wizard Alpine.js Component
 *
 * @param {Object} existingData - Existing service data for editing.
 * @return {Object} Alpine.js component.
 */
function wpssServiceWizard(existingData = {}) {
	return {
		// Current step
		currentStep: 'basic',

		// Step order
		steps: ['basic', 'pricing', 'gallery', 'requirements', 'extras', 'review'],

		// Completed steps tracking
		completedSteps: new Set(),

		// Loading states
		saving: false,
		publishing: false,

		// Active package tab
		activePackage: 'basic',

		// Validation errors
		validationErrors: [],

		// Has unsaved changes
		isDirty: false,

		// Service data
		data: {
			title: '',
			description: '',
			category: '',
			subcategory: '',
			tags: '',
			packages: {
				basic: {
					enabled: true,
					name: 'Basic',
					description: '',
					price: '',
					delivery_time: '',
					revisions: '1',
					features: []
				},
				standard: {
					enabled: false,
					name: 'Standard',
					description: '',
					price: '',
					delivery_time: '',
					revisions: '2',
					features: []
				},
				premium: {
					enabled: false,
					name: 'Premium',
					description: '',
					price: '',
					delivery_time: '',
					revisions: '3',
					features: []
				}
			},
			gallery: {
				main: null,
				images: [],
				video: ''
			},
			requirements: [],
			extras: [],
			faqs: [],
			...existingData
		},

		/**
		 * Initialize the wizard.
		 */
		init() {
			// Mark steps as completed based on existing data
			if (existingData.id) {
				this.markCompletedSteps();
			}

			// Watch for changes
			this.$watch('data', () => {
				this.isDirty = true;
			}, { deep: true });

			// Warn on page leave if dirty
			window.addEventListener('beforeunload', (e) => {
				if (this.isDirty) {
					e.preventDefault();
					e.returnValue = wpssWizard.strings.unsavedChanges;
				}
			});
		},

		/**
		 * Mark steps as completed based on existing data.
		 */
		markCompletedSteps() {
			if (this.data.title && this.data.category && this.data.description) {
				this.completedSteps.add('basic');
			}
			if (this.isPackageValid('basic')) {
				this.completedSteps.add('pricing');
			}
			if (this.data.gallery.main) {
				this.completedSteps.add('gallery');
			}
			// Requirements, extras, and review are optional
		},

		/**
		 * Check if a step is completed.
		 *
		 * @param {string} step - Step key.
		 * @return {boolean} Is completed.
		 */
		isStepCompleted(step) {
			return this.completedSteps.has(step);
		},

		/**
		 * Navigate to next step.
		 */
		nextStep() {
			const currentIndex = this.steps.indexOf(this.currentStep);

			if (currentIndex < this.steps.length - 1) {
				// Validate current step before proceeding
				if (this.validateStep(this.currentStep)) {
					this.completedSteps.add(this.currentStep);
					this.currentStep = this.steps[currentIndex + 1];
					this.scrollToTop();
				}
			}
		},

		/**
		 * Navigate to previous step.
		 */
		prevStep() {
			const currentIndex = this.steps.indexOf(this.currentStep);

			if (currentIndex > 0) {
				this.currentStep = this.steps[currentIndex - 1];
				this.scrollToTop();
			}
		},

		/**
		 * Navigate to specific step.
		 *
		 * @param {string} step - Step key.
		 */
		goToStep(step) {
			const targetIndex = this.steps.indexOf(step);
			const currentIndex = this.steps.indexOf(this.currentStep);

			// Allow going back or to completed steps
			if (targetIndex < currentIndex || this.completedSteps.has(step)) {
				this.currentStep = step;
				this.scrollToTop();
			}
		},

		/**
		 * Scroll to top of wizard.
		 */
		scrollToTop() {
			const wizard = document.getElementById('wpss-service-wizard');
			if (wizard) {
				wizard.scrollIntoView({ behavior: 'smooth', block: 'start' });
			}
		},

		/**
		 * Validate a step.
		 *
		 * @param {string} step - Step key.
		 * @return {boolean} Is valid.
		 */
		validateStep(step) {
			this.validationErrors = [];

			switch (step) {
				case 'basic':
					if (!this.data.title || this.data.title.length < 10) {
						this.validationErrors.push(wpssWizard.strings.validationTitle);
					}
					if (!this.data.category) {
						this.validationErrors.push(wpssWizard.strings.validationCat);
					}
					if (!this.data.description || this.data.description.length < 120) {
						this.validationErrors.push(wpssWizard.strings.validationDesc);
					}
					break;

				case 'pricing':
					if (!this.isPackageValid('basic')) {
						this.validationErrors.push(wpssWizard.strings.validationPrice);
					}
					break;

				case 'gallery':
					// Gallery validation is optional for draft saves
					break;
			}

			if (this.validationErrors.length > 0) {
				this.showNotice(this.validationErrors[0], 'error');
				return false;
			}

			return true;
		},

		/**
		 * Check if a package is valid.
		 *
		 * @param {string} tier - Package tier.
		 * @return {boolean} Is valid.
		 */
		isPackageValid(tier) {
			const pkg = this.data.packages[tier];

			if (tier !== 'basic' && !pkg.enabled) {
				return true; // Disabled packages are valid
			}

			return pkg.price > 0 && pkg.delivery_time > 0;
		},

		/**
		 * Add a feature to a package.
		 *
		 * @param {string} tier - Package tier.
		 */
		addFeature(tier) {
			this.data.packages[tier].features.push('');
		},

		/**
		 * Remove a feature from a package.
		 *
		 * @param {string} tier  - Package tier.
		 * @param {number} index - Feature index.
		 */
		removeFeature(tier, index) {
			this.data.packages[tier].features.splice(index, 1);
		},

		/**
		 * Add a requirement.
		 */
		addRequirement() {
			this.data.requirements.push({
				question: '',
				type: 'text',
				required: false,
				options: ''
			});
		},

		/**
		 * Remove a requirement.
		 *
		 * @param {number} index - Requirement index.
		 */
		removeRequirement(index) {
			if (confirm(wpssWizard.strings.confirmDelete)) {
				this.data.requirements.splice(index, 1);
			}
		},

		/**
		 * Add an extra.
		 */
		addExtra() {
			this.data.extras.push({
				title: '',
				description: '',
				price: '',
				extra_days: 0
			});
		},

		/**
		 * Remove an extra.
		 *
		 * @param {number} index - Extra index.
		 */
		removeExtra(index) {
			if (confirm(wpssWizard.strings.confirmDelete)) {
				this.data.extras.splice(index, 1);
			}
		},

		/**
		 * Add a FAQ.
		 */
		addFaq() {
			this.data.faqs.push({
				question: '',
				answer: ''
			});
		},

		/**
		 * Remove a FAQ.
		 *
		 * @param {number} index - FAQ index.
		 */
		removeFaq(index) {
			if (confirm(wpssWizard.strings.confirmDelete)) {
				this.data.faqs.splice(index, 1);
			}
		},

		/**
		 * Open WordPress media uploader.
		 *
		 * @param {string} type - 'main' or 'images'.
		 */
		openMediaUploader(type) {
			const frame = wp.media({
				title: type === 'main' ? 'Select Main Image' : 'Add Gallery Image',
				multiple: false,
				library: {
					type: 'image'
				}
			});

			frame.on('select', () => {
				const attachment = frame.state().get('selection').first().toJSON();

				if (type === 'main') {
					this.data.gallery.main = {
						id: attachment.id,
						url: attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url
					};
				} else if (type === 'images') {
					if (this.data.gallery.images.length < 4) {
						this.data.gallery.images.push({
							id: attachment.id,
							url: attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url
						});
					}
				}

				this.isDirty = true;
			});

			frame.open();
		},

		/**
		 * Remove a gallery item.
		 *
		 * @param {string} type  - 'main' or 'images'.
		 * @param {number} index - Index for images array.
		 */
		removeGalleryItem(type, index = null) {
			if (type === 'main') {
				this.data.gallery.main = null;
			} else if (type === 'images' && index !== null) {
				this.data.gallery.images.splice(index, 1);
			}
		},

		/**
		 * Save draft via AJAX.
		 */
		async saveDraft() {
			if (this.saving) {
				return;
			}

			this.saving = true;

			try {
				const serviceId = document.getElementById('wpss-service-wizard').dataset.serviceId || 0;
				const formData = new FormData();

				formData.append('action', 'wpss_wizard_save_draft');
				formData.append('nonce', wpssWizard.nonce);
				formData.append('service_id', serviceId);
				formData.append('data', JSON.stringify(this.data));

				const response = await fetch(wpssWizard.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const result = await response.json();

				if (result.success) {
					this.isDirty = false;

					// Update service ID if new
					if (result.data.service_id && !serviceId) {
						document.getElementById('wpss-service-wizard').dataset.serviceId = result.data.service_id;

						// Update URL without reload
						const url = new URL(window.location);
						url.searchParams.set('id', result.data.service_id);
						window.history.replaceState({}, '', url);
					}

					this.showNotice(wpssWizard.strings.saved, 'success');
				} else {
					this.showNotice(result.data.message || wpssWizard.strings.error, 'error');
				}
			} catch (error) {
				console.error('Save draft error:', error);
				this.showNotice(wpssWizard.strings.error, 'error');
			} finally {
				this.saving = false;
			}
		},

		/**
		 * Publish service via AJAX.
		 */
		async publishService() {
			if (this.publishing) {
				return;
			}

			// Validate all required fields
			this.validationErrors = [];

			if (!this.data.title || this.data.title.length < 10) {
				this.validationErrors.push(wpssWizard.strings.validationTitle);
			}
			if (!this.data.category) {
				this.validationErrors.push(wpssWizard.strings.validationCat);
			}
			if (!this.data.description || this.data.description.length < 120) {
				this.validationErrors.push(wpssWizard.strings.validationDesc);
			}
			if (!this.isPackageValid('basic')) {
				this.validationErrors.push(wpssWizard.strings.validationPrice);
			}
			if (!this.data.gallery.main) {
				this.validationErrors.push(wpssWizard.strings.validationImage);
			}

			if (this.validationErrors.length > 0) {
				return;
			}

			this.publishing = true;

			try {
				const serviceId = document.getElementById('wpss-service-wizard').dataset.serviceId || 0;
				const formData = new FormData();

				formData.append('action', 'wpss_wizard_publish');
				formData.append('nonce', wpssWizard.nonce);
				formData.append('service_id', serviceId);
				formData.append('data', JSON.stringify(this.data));

				const response = await fetch(wpssWizard.ajaxUrl, {
					method: 'POST',
					body: formData
				});

				const result = await response.json();

				if (result.success) {
					this.isDirty = false;
					this.showNotice(wpssWizard.strings.published, 'success');

					// Redirect to service page after short delay
					setTimeout(() => {
						window.location.href = result.data.redirect_url;
					}, 1500);
				} else {
					if (result.data.errors) {
						this.validationErrors = result.data.errors;
					}
					this.showNotice(result.data.message || wpssWizard.strings.error, 'error');
				}
			} catch (error) {
				console.error('Publish error:', error);
				this.showNotice(wpssWizard.strings.error, 'error');
			} finally {
				this.publishing = false;
			}
		},

		/**
		 * Show a temporary notice.
		 *
		 * @param {string} message - Notice message.
		 * @param {string} type    - 'success' or 'error'.
		 */
		showNotice(message, type = 'success') {
			// Create notice element
			const notice = document.createElement('div');
			notice.className = `wpss-wizard-notice wpss-wizard-notice--${type}`;
			notice.textContent = message;

			// Style the notice
			Object.assign(notice.style, {
				position: 'fixed',
				top: '20px',
				right: '20px',
				padding: '12px 20px',
				borderRadius: '8px',
				fontSize: '14px',
				fontWeight: '500',
				zIndex: '10000',
				animation: 'wpss-notice-slide-in 0.3s ease'
			});

			if (type === 'success') {
				notice.style.backgroundColor = '#d1fae5';
				notice.style.color = '#065f46';
			} else {
				notice.style.backgroundColor = '#fee2e2';
				notice.style.color = '#991b1b';
			}

			document.body.appendChild(notice);

			// Remove after 3 seconds
			setTimeout(() => {
				notice.style.animation = 'wpss-notice-slide-out 0.3s ease';
				setTimeout(() => {
					notice.remove();
				}, 300);
			}, 3000);
		}
	};
}

// Add notice animations to document
const style = document.createElement('style');
style.textContent = `
	@keyframes wpss-notice-slide-in {
		from {
			opacity: 0;
			transform: translateX(20px);
		}
		to {
			opacity: 1;
			transform: translateX(0);
		}
	}
	@keyframes wpss-notice-slide-out {
		from {
			opacity: 1;
			transform: translateX(0);
		}
		to {
			opacity: 0;
			transform: translateX(20px);
		}
	}
`;
document.head.appendChild(style);

// Expose globally for x-data="wpssServiceWizard({})" syntax.
// This script loads BEFORE Alpine (without defer), so the function
// is available when Alpine auto-initializes with defer.
window.wpssServiceWizard = wpssServiceWizard;

// Also register with Alpine.data() for component-style usage.
document.addEventListener('alpine:init', () => {
	Alpine.data('wpssServiceWizard', wpssServiceWizard);
});
