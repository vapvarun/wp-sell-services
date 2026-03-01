<?php
/**
 * Service Wizard
 *
 * Handles the 6-step frontend service creation wizard.
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

use WPSellServices\Services\VendorService;
use WPSellServices\Services\ServiceManager;
use WPSellServices\Services\ModerationService;

/**
 * Multi-step service creation wizard for vendors.
 *
 * Steps:
 * 1. Basic Info - Title, category, description
 * 2. Pricing - Package tiers (Basic/Standard/Premium)
 * 3. Gallery - Images and video
 * 4. Requirements - Buyer requirement fields
 * 5. Extras & FAQs - Add-ons and FAQs
 * 6. Review - Preview and publish
 *
 * @since 1.0.0
 */
class ServiceWizard {

	/**
	 * Vendor service.
	 *
	 * @var VendorService
	 */
	private VendorService $vendor_service;

	/**
	 * Service manager.
	 *
	 * @var ServiceManager
	 */
	private ServiceManager $service_manager;

	/**
	 * Wizard steps.
	 *
	 * @var array
	 */
	private array $steps = array();

	/**
	 * Wizard limits (filterable by Pro).
	 *
	 * @var array
	 */
	private array $limits = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->vendor_service  = new VendorService();
		$this->service_manager = new ServiceManager();
		$this->init_limits();
	}

	/**
	 * Get wizard steps. Deferred to avoid calling __() before init.
	 *
	 * @return array<string, array{title: string, icon: string}>
	 */
	private function get_steps(): array {
		if ( empty( $this->steps ) ) {
			$this->steps = array(
				'basic'        => array(
					'title' => __( 'Basic Info', 'wp-sell-services' ),
					'icon'  => 'dashicons-edit',
				),
				'pricing'      => array(
					'title' => __( 'Pricing', 'wp-sell-services' ),
					'icon'  => 'dashicons-tag',
				),
				'gallery'      => array(
					'title' => __( 'Gallery', 'wp-sell-services' ),
					'icon'  => 'dashicons-format-gallery',
				),
				'requirements' => array(
					'title' => __( 'Requirements', 'wp-sell-services' ),
					'icon'  => 'dashicons-list-view',
				),
				'extras'       => array(
					'title' => __( 'Extras & FAQs', 'wp-sell-services' ),
					'icon'  => 'dashicons-plus-alt',
				),
				'review'       => array(
					'title' => __( 'Review', 'wp-sell-services' ),
					'icon'  => 'dashicons-visibility',
				),
			);
		}

		return $this->steps;
	}

	/**
	 * Initialize wizard limits.
	 *
	 * Free version has conservative limits. Pro removes these via filters.
	 *
	 * @return void
	 */
	private function init_limits(): void {
		$this->limits = array(
			/**
			 * Max pricing packages (tiers).
			 *
			 * Free: 3 (Basic, Standard, Premium)
			 * Pro: 3 (same, but more flexibility)
			 *
			 * @param int $max Maximum packages.
			 */
			'max_packages'     => apply_filters( 'wpss_service_max_packages', 3 ),

			/**
			 * Max gallery images (additional, not including main).
			 *
			 * Free: 4
			 * Pro: Unlimited (-1)
			 *
			 * @param int $max Maximum gallery images. -1 for unlimited.
			 */
			'max_gallery'      => apply_filters( 'wpss_service_max_gallery', 4 ),

			/**
			 * Max video URLs.
			 *
			 * Free: 1
			 * Pro: 3
			 *
			 * @param int $max Maximum videos.
			 */
			'max_videos'       => apply_filters( 'wpss_service_max_videos', 1 ),

			/**
			 * Max service extras (add-ons).
			 *
			 * Free: 3
			 * Pro: Unlimited (-1)
			 *
			 * @param int $max Maximum extras. -1 for unlimited.
			 */
			'max_extras'       => apply_filters( 'wpss_service_max_extras', 3 ),

			/**
			 * Max FAQs.
			 *
			 * Free: 5
			 * Pro: Unlimited (-1)
			 *
			 * @param int $max Maximum FAQs. -1 for unlimited.
			 */
			'max_faq'          => apply_filters( 'wpss_service_max_faq', 5 ),

			/**
			 * Max buyer requirements.
			 *
			 * Free: 5
			 * Pro: Unlimited (-1)
			 *
			 * @param int $max Maximum requirements. -1 for unlimited.
			 */
			'max_requirements' => apply_filters( 'wpss_service_max_requirements', 5 ),

			/**
			 * Wizard features enabled.
			 *
			 * Pro can add features like AI title suggestions, templates, etc.
			 *
			 * @param array $features Array of enabled features.
			 */
			'features'         => apply_filters(
				'wpss_service_wizard_features',
				array(
					'ai_title'          => false, // AI-powered title suggestions.
					'templates'         => false, // Service templates.
					'bulk_upload'       => false, // Bulk image upload.
					'video_upload'      => false, // Direct video upload (vs URL only).
					'custom_fields'     => false, // Custom fields in packages.
					'scheduled_publish' => false, // Schedule service publishing.
				)
			),
		);
	}

	/**
	 * Get wizard limits.
	 *
	 * @return array Limits array.
	 */
	public function get_limits(): array {
		return $this->limits;
	}

	/**
	 * Get a specific limit.
	 *
	 * @param string $key Limit key.
	 * @return mixed Limit value or null if not found.
	 */
	public function get_limit( string $key ) {
		return $this->limits[ $key ] ?? null;
	}

	/**
	 * Check if a feature is enabled.
	 *
	 * @param string $feature Feature key.
	 * @return bool Whether feature is enabled.
	 */
	public function is_feature_enabled( string $feature ): bool {
		return ! empty( $this->limits['features'][ $feature ] );
	}

	/**
	 * Initialize wizard hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'wpss_service_wizard', array( $this, 'render_wizard' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_wpss_wizard_save_draft', array( $this, 'ajax_save_draft' ) );
		add_action( 'wp_ajax_wpss_wizard_publish', array( $this, 'ajax_publish_service' ) );
		add_action( 'wp_ajax_wpss_wizard_upload_gallery', array( $this, 'ajax_upload_gallery' ) );
		add_action( 'wp_ajax_wpss_wizard_remove_gallery', array( $this, 'ajax_remove_gallery' ) );
	}

	/**
	 * Render the service wizard.
	 *
	 * [wpss_service_wizard] - Create new service
	 * [wpss_service_wizard id="123"] - Edit existing service
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Wizard HTML.
	 */
	public function render_wizard( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_required();
		}

		$user_id = get_current_user_id();

		if ( ! $this->vendor_service->is_vendor( $user_id ) ) {
			return $this->render_not_vendor();
		}

		// Check vendor account status - block suspended/pending vendors early.
		$vendor_profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $user_id );
		if ( $vendor_profile && ! $vendor_profile->can_create_services() ) {
			return $this->render_vendor_status_notice( $vendor_profile );
		}

		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'wpss_service_wizard'
		);

		$service_id = absint( $atts['id'] );
		$service    = null;

		// Check max services limit for NEW services only (not editing existing).
		if ( ! $service_id && $vendor_profile && $vendor_profile->has_reached_service_limit() ) {
			return $this->render_service_limit_notice( $vendor_profile );
		}

		// If editing, verify ownership.
		if ( $service_id ) {
			$service = get_post( $service_id );

			if ( ! $service || 'wpss_service' !== $service->post_type ) {
				return '<div class="wpss-notice wpss-notice-error">' . esc_html__( 'Service not found.', 'wp-sell-services' ) . '</div>';
			}

			if ( (int) $service->post_author !== $user_id && ! current_user_can( 'manage_options' ) ) {
				return '<div class="wpss-notice wpss-notice-error">' . esc_html__( 'You do not have permission to edit this service.', 'wp-sell-services' ) . '</div>';
			}
		}

		// Enqueue wizard assets.
		$this->enqueue_assets();

		ob_start();
		?>
		<div class="wpss-wizard" id="wpss-service-wizard" data-service-id="<?php echo esc_attr( $service_id ); ?>" x-data="wpssServiceWizard(<?php echo $service_id ? esc_attr( wp_json_encode( $this->get_service_data( $service_id ) ) ) : '{}'; ?>)">
			<?php wp_nonce_field( 'wpss_service_wizard', 'wpss_wizard_nonce' ); ?>

			<!-- Progress Steps -->
			<div class="wpss-wizard__progress">
				<?php $this->render_progress_steps(); ?>
			</div>

			<!-- Step Container -->
			<div class="wpss-wizard__content">
				<div class="wpss-wizard__steps">

					<!-- Step 1: Basic Info -->
					<div class="wpss-wizard__step" data-step="basic" x-show="currentStep === 'basic'" x-cloak>
						<?php $this->render_step_basic( $service ); ?>
					</div>

					<!-- Step 2: Pricing -->
					<div class="wpss-wizard__step" data-step="pricing" x-show="currentStep === 'pricing'" x-cloak>
						<?php $this->render_step_pricing( $service ); ?>
					</div>

					<!-- Step 3: Gallery -->
					<div class="wpss-wizard__step" data-step="gallery" x-show="currentStep === 'gallery'" x-cloak>
						<?php $this->render_step_gallery( $service ); ?>
					</div>

					<!-- Step 4: Requirements -->
					<div class="wpss-wizard__step" data-step="requirements" x-show="currentStep === 'requirements'" x-cloak>
						<?php $this->render_step_requirements( $service ); ?>
					</div>

					<!-- Step 5: Extras & FAQs -->
					<div class="wpss-wizard__step" data-step="extras" x-show="currentStep === 'extras'" x-cloak>
						<?php $this->render_step_extras( $service ); ?>
					</div>

					<!-- Step 6: Review -->
					<div class="wpss-wizard__step" data-step="review" x-show="currentStep === 'review'" x-cloak>
						<?php $this->render_step_review( $service ); ?>
					</div>

				</div>
			</div>

			<!-- Navigation -->
			<div class="wpss-wizard__nav">
				<div class="wpss-wizard__nav-left">
					<button type="button" class="wpss-btn wpss-btn--outline wpss-wizard__btn-prev" x-show="currentStep !== 'basic'" @click="prevStep()" x-cloak>
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Previous', 'wp-sell-services' ); ?>
					</button>
				</div>
				<div class="wpss-wizard__nav-center">
					<button type="button" class="wpss-btn wpss-btn--ghost wpss-wizard__btn-save" @click="saveDraft()" :disabled="saving">
						<span class="dashicons dashicons-cloud" x-show="!saving"></span>
						<span class="wpss-spinner" x-show="saving" x-cloak></span>
						<span x-text="saving ? '<?php esc_attr_e( 'Saving...', 'wp-sell-services' ); ?>' : '<?php esc_attr_e( 'Save Draft', 'wp-sell-services' ); ?>'"></span>
					</button>
				</div>
				<div class="wpss-wizard__nav-right">
					<button type="button" class="wpss-btn wpss-btn--primary wpss-wizard__btn-next" x-show="currentStep !== 'review'" @click="nextStep()" x-cloak>
						<?php esc_html_e( 'Continue', 'wp-sell-services' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
					<button type="button" class="wpss-btn wpss-btn--success wpss-wizard__btn-publish" x-show="currentStep === 'review'" @click="publishService()" :disabled="publishing" x-cloak>
						<span class="dashicons dashicons-yes-alt" x-show="!publishing"></span>
						<span class="wpss-spinner" x-show="publishing" x-cloak></span>
						<span x-text="publishing ? '<?php esc_attr_e( 'Publishing...', 'wp-sell-services' ); ?>' : '<?php esc_attr_e( 'Publish Service', 'wp-sell-services' ); ?>'"></span>
					</button>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render progress steps.
	 *
	 * @return void
	 */
	private function render_progress_steps(): void {
		$steps     = $this->get_steps();
		$step_keys = array_keys( $steps );
		?>
		<ol class="wpss-wizard__progress-list">
			<?php foreach ( $steps as $key => $step ) : ?>
				<li class="wpss-wizard__progress-item"
					:class="{
						'wpss-wizard__progress-item--active': currentStep === '<?php echo esc_attr( $key ); ?>',
						'wpss-wizard__progress-item--completed': isStepCompleted('<?php echo esc_attr( $key ); ?>')
					}"
					@click="goToStep('<?php echo esc_attr( $key ); ?>')">
					<span class="wpss-wizard__progress-icon">
						<span class="dashicons <?php echo esc_attr( $step['icon'] ); ?>"></span>
					</span>
					<span class="wpss-wizard__progress-label"><?php echo esc_html( $step['title'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php
	}

	/**
	 * Render Step 1: Basic Info.
	 *
	 * @param \WP_Post|null $_service Existing service post (unused, data from Alpine.js).
	 * @return void
	 */
	private function render_step_basic( ?\WP_Post $_service ): void {
		$categories = get_terms(
			array(
				'taxonomy'   => 'wpss_service_category',
				'hide_empty' => false,
			)
		);
		?>
		<div class="wpss-wizard__step-header">
			<h2 class="wpss-wizard__step-title"><?php esc_html_e( 'Basic Information', 'wp-sell-services' ); ?></h2>
			<p class="wpss-wizard__step-desc"><?php esc_html_e( 'Start by giving your service a catchy title and detailed description.', 'wp-sell-services' ); ?></p>
		</div>

		<div class="wpss-wizard__step-body">
			<div class="wpss-form-group">
				<label for="service_title" class="wpss-form-label">
					<?php esc_html_e( 'Service Title', 'wp-sell-services' ); ?>
					<span class="wpss-required">*</span>
				</label>
				<input type="text"
					id="service_title"
					class="wpss-form-input"
					x-model="data.title"
					placeholder="<?php esc_attr_e( 'I will...', 'wp-sell-services' ); ?>"
					maxlength="80"
					required>
				<div class="wpss-form-hint">
					<span x-text="data.title?.length || 0"></span>/80 <?php esc_html_e( 'characters', 'wp-sell-services' ); ?>
				</div>
			</div>

			<div class="wpss-form-group">
				<label for="service_category" class="wpss-form-label">
					<?php esc_html_e( 'Category', 'wp-sell-services' ); ?>
					<span class="wpss-required">*</span>
				</label>
				<select id="service_category" class="wpss-form-select" x-model="data.category" required>
					<option value=""><?php esc_html_e( 'Select a category', 'wp-sell-services' ); ?></option>
					<?php
					if ( ! is_wp_error( $categories ) ) :
						$this->render_category_options( $categories );
					endif;
					?>
				</select>
			</div>

			<div class="wpss-form-group">
				<label for="service_subcategory" class="wpss-form-label">
					<?php esc_html_e( 'Subcategory', 'wp-sell-services' ); ?>
				</label>
				<select id="service_subcategory" class="wpss-form-select" x-model="data.subcategory" :disabled="!data.category">
					<option value=""><?php esc_html_e( 'Select a subcategory', 'wp-sell-services' ); ?></option>
					<!-- Populated dynamically based on category -->
				</select>
			</div>

			<div class="wpss-form-group">
				<label for="service_description" class="wpss-form-label">
					<?php esc_html_e( 'Description', 'wp-sell-services' ); ?>
					<span class="wpss-required">*</span>
				</label>
				<textarea id="service_description"
					class="wpss-form-textarea"
					x-model="data.description"
					rows="8"
					maxlength="5000"
					placeholder="<?php esc_attr_e( 'Describe your service in detail. What makes you unique? What\'s included?', 'wp-sell-services' ); ?>"
					required></textarea>
				<div class="wpss-form-hint" style="display: flex; justify-content: space-between;">
					<span><?php esc_html_e( 'Minimum 120 characters. Be detailed and specific.', 'wp-sell-services' ); ?></span>
					<span x-text="(data.description || '').length + ' / 5000'" :class="{ 'wpss-text-danger': (data.description || '').length < 120 }"></span>
				</div>
			</div>

			<div class="wpss-form-group">
				<label for="service_tags" class="wpss-form-label">
					<?php esc_html_e( 'Tags', 'wp-sell-services' ); ?>
				</label>
				<input type="text"
					id="service_tags"
					class="wpss-form-input"
					x-model="data.tags"
					placeholder="<?php esc_attr_e( 'e.g., web design, responsive, WordPress', 'wp-sell-services' ); ?>">
				<div class="wpss-form-hint"><?php esc_html_e( 'Separate tags with commas. Max 5 tags.', 'wp-sell-services' ); ?></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Step 2: Pricing.
	 *
	 * @param \WP_Post|null $_service Existing service post (unused, data from Alpine.js).
	 * @return void
	 */
	private function render_step_pricing( ?\WP_Post $_service ): void {
		?>
		<div class="wpss-wizard__step-header">
			<h2 class="wpss-wizard__step-title"><?php esc_html_e( 'Pricing Packages', 'wp-sell-services' ); ?></h2>
			<p class="wpss-wizard__step-desc"><?php esc_html_e( 'Create up to 3 pricing tiers to offer buyers different options.', 'wp-sell-services' ); ?></p>
		</div>

		<div class="wpss-wizard__step-body">
			<div class="wpss-pricing-tabs">
				<button type="button" class="wpss-pricing-tab" :class="{ 'active': activePackage === 'basic' }" @click="activePackage = 'basic'">
					<?php esc_html_e( 'Basic', 'wp-sell-services' ); ?>
					<span class="wpss-required-badge" x-show="!isPackageValid('basic')" x-cloak>!</span>
				</button>
				<button type="button" class="wpss-pricing-tab" :class="{ 'active': activePackage === 'standard' }" @click="activePackage = 'standard'">
					<?php esc_html_e( 'Standard', 'wp-sell-services' ); ?>
				</button>
				<button type="button" class="wpss-pricing-tab" :class="{ 'active': activePackage === 'premium' }" @click="activePackage = 'premium'">
					<?php esc_html_e( 'Premium', 'wp-sell-services' ); ?>
				</button>
			</div>

			<?php foreach ( array( 'basic', 'standard', 'premium' ) as $tier ) : ?>
				<div class="wpss-pricing-panel" x-show="activePackage === '<?php echo esc_attr( $tier ); ?>'" x-cloak>
					<?php if ( 'basic' !== $tier ) : ?>
						<div class="wpss-form-group wpss-form-group--toggle">
							<label class="wpss-toggle">
								<input type="checkbox" x-model="data.packages.<?php echo esc_attr( $tier ); ?>.enabled">
								<span class="wpss-toggle__slider"></span>
								<span class="wpss-toggle__label"><?php esc_html_e( 'Enable this package', 'wp-sell-services' ); ?></span>
							</label>
						</div>
					<?php endif; ?>

					<div class="wpss-pricing-fields" <?php echo 'basic' !== $tier ? ':class="{ \'disabled\': !data.packages.' . esc_attr( $tier ) . '.enabled }"' : ''; ?>>
						<div class="wpss-form-group">
							<label class="wpss-form-label">
								<?php esc_html_e( 'Package Name', 'wp-sell-services' ); ?>
								<?php if ( 'basic' === $tier ) : ?>
									<span class="wpss-required">*</span>
								<?php endif; ?>
							</label>
							<input type="text"
								class="wpss-form-input"
								x-model="data.packages.<?php echo esc_attr( $tier ); ?>.name"
								placeholder="<?php echo esc_attr( ucfirst( $tier ) ); ?>">
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-label">
								<?php esc_html_e( 'Package Description', 'wp-sell-services' ); ?>
								<?php if ( 'basic' === $tier ) : ?>
									<span class="wpss-required">*</span>
								<?php endif; ?>
							</label>
							<textarea class="wpss-form-textarea"
								x-model="data.packages.<?php echo esc_attr( $tier ); ?>.description"
								rows="3"
								placeholder="<?php esc_attr_e( 'What\'s included in this package?', 'wp-sell-services' ); ?>"></textarea>
						</div>

						<div class="wpss-form-row wpss-form-row--2col">
							<div class="wpss-form-group">
								<label class="wpss-form-label">
									<?php esc_html_e( 'Price', 'wp-sell-services' ); ?>
									<?php if ( 'basic' === $tier ) : ?>
										<span class="wpss-required">*</span>
									<?php endif; ?>
								</label>
								<div class="wpss-input-group">
									<span class="wpss-input-prefix"><?php echo esc_html( wpss_get_currency_symbol() ); ?></span>
									<input type="number"
										class="wpss-form-input"
										x-model="data.packages.<?php echo esc_attr( $tier ); ?>.price"
										min="5"
										step="0.01">
								</div>
							</div>

							<div class="wpss-form-group">
								<label class="wpss-form-label">
									<?php esc_html_e( 'Delivery Time', 'wp-sell-services' ); ?>
									<?php if ( 'basic' === $tier ) : ?>
										<span class="wpss-required">*</span>
									<?php endif; ?>
								</label>
								<select class="wpss-form-select" x-model="data.packages.<?php echo esc_attr( $tier ); ?>.delivery_time">
									<option value=""><?php esc_html_e( 'Select', 'wp-sell-services' ); ?></option>
									<option value="1"><?php esc_html_e( '1 day', 'wp-sell-services' ); ?></option>
									<option value="2"><?php esc_html_e( '2 days', 'wp-sell-services' ); ?></option>
									<option value="3"><?php esc_html_e( '3 days', 'wp-sell-services' ); ?></option>
									<option value="5"><?php esc_html_e( '5 days', 'wp-sell-services' ); ?></option>
									<option value="7"><?php esc_html_e( '7 days', 'wp-sell-services' ); ?></option>
									<option value="14"><?php esc_html_e( '14 days', 'wp-sell-services' ); ?></option>
									<option value="21"><?php esc_html_e( '21 days', 'wp-sell-services' ); ?></option>
									<option value="30"><?php esc_html_e( '30 days', 'wp-sell-services' ); ?></option>
								</select>
							</div>
						</div>

						<div class="wpss-form-row wpss-form-row--2col">
							<div class="wpss-form-group">
								<label class="wpss-form-label"><?php esc_html_e( 'Revisions', 'wp-sell-services' ); ?></label>
								<select class="wpss-form-select" x-model="data.packages.<?php echo esc_attr( $tier ); ?>.revisions">
									<option value="0"><?php esc_html_e( 'No revisions', 'wp-sell-services' ); ?></option>
									<option value="1">1</option>
									<option value="2">2</option>
									<option value="3">3</option>
									<option value="5">5</option>
									<option value="-1"><?php esc_html_e( 'Unlimited', 'wp-sell-services' ); ?></option>
								</select>
							</div>
						</div>

						<!-- Custom Features -->
						<div class="wpss-form-group">
							<label class="wpss-form-label"><?php esc_html_e( 'Features Included', 'wp-sell-services' ); ?></label>
							<div class="wpss-features-list">
								<template x-for="(feature, index) in data.packages.<?php echo esc_attr( $tier ); ?>.features" :key="index">
									<div class="wpss-feature-item">
										<input type="text"
											class="wpss-form-input"
											x-model="data.packages.<?php echo esc_attr( $tier ); ?>.features[index]"
											placeholder="<?php esc_attr_e( 'Feature description', 'wp-sell-services' ); ?>">
										<button type="button" class="wpss-btn--icon" @click="removeFeature('<?php echo esc_attr( $tier ); ?>', index)">
											<span class="dashicons dashicons-no-alt"></span>
										</button>
									</div>
								</template>
								<button type="button" class="wpss-btn wpss-btn--outline wpss-btn--sm" @click="addFeature('<?php echo esc_attr( $tier ); ?>')">
									<span class="dashicons dashicons-plus-alt2"></span>
									<?php esc_html_e( 'Add Feature', 'wp-sell-services' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Render Step 3: Gallery.
	 *
	 * @param \WP_Post|null $_service Existing service post (unused, data from Alpine.js).
	 * @return void
	 */
	private function render_step_gallery( ?\WP_Post $_service ): void {
		?>
		<div class="wpss-wizard__step-header">
			<h2 class="wpss-wizard__step-title"><?php esc_html_e( 'Gallery', 'wp-sell-services' ); ?></h2>
			<p class="wpss-wizard__step-desc"><?php esc_html_e( 'Add images and videos to showcase your work. High-quality visuals increase conversions.', 'wp-sell-services' ); ?></p>
		</div>

		<div class="wpss-wizard__step-body">
			<!-- Main Image -->
			<div class="wpss-form-group">
				<label class="wpss-form-label">
					<?php esc_html_e( 'Main Image', 'wp-sell-services' ); ?>
					<span class="wpss-required">*</span>
				</label>
				<div class="wpss-gallery-upload wpss-gallery-upload--main" @click="openMediaUploader('main')">
					<template x-if="data.gallery.main">
						<div class="wpss-gallery-preview">
							<img :src="data.gallery.main.url" alt="">
							<button type="button" class="wpss-gallery-remove" @click.stop="removeGalleryItem('main')">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
						</div>
					</template>
					<template x-if="!data.gallery.main">
						<div class="wpss-gallery-placeholder">
							<span class="dashicons dashicons-format-image"></span>
							<span><?php esc_html_e( 'Click to upload main image', 'wp-sell-services' ); ?></span>
							<span class="wpss-gallery-hint"><?php esc_html_e( 'Recommended: 800x600px', 'wp-sell-services' ); ?></span>
						</div>
					</template>
				</div>
			</div>

			<!-- Additional Images -->
			<div class="wpss-form-group">
				<label class="wpss-form-label"><?php esc_html_e( 'Additional Images', 'wp-sell-services' ); ?></label>
				<div class="wpss-gallery-grid">
					<template x-for="(image, index) in data.gallery.images" :key="image.id">
						<div class="wpss-gallery-item">
							<img :src="image.url" alt="">
							<button type="button" class="wpss-gallery-remove" @click="removeGalleryItem('images', index)">
								<span class="dashicons dashicons-no-alt"></span>
							</button>
						</div>
					</template>
					<div class="wpss-gallery-add" @click="openMediaUploader('images')" x-show="canAddGalleryImage()">
						<span class="dashicons dashicons-plus-alt2"></span>
						<span><?php esc_html_e( 'Add Image', 'wp-sell-services' ); ?></span>
					</div>
				</div>
				<div class="wpss-form-hint">
					<span x-text="limits.max_gallery === -1 ? '<?php esc_attr_e( 'Unlimited additional images', 'wp-sell-services' ); ?>' : '<?php esc_attr_e( 'Up to', 'wp-sell-services' ); ?> ' + limits.max_gallery + ' <?php esc_attr_e( 'additional images', 'wp-sell-services' ); ?>'"></span>
				</div>
			</div>

			<!-- Video -->
			<div class="wpss-form-group">
				<label class="wpss-form-label"><?php esc_html_e( 'Video (Optional)', 'wp-sell-services' ); ?></label>
				<div class="wpss-form-hint wpss-form-hint--top"><?php esc_html_e( 'Add a YouTube or Vimeo URL to showcase your service.', 'wp-sell-services' ); ?></div>
				<input type="url"
					class="wpss-form-input"
					x-model="data.gallery.video"
					placeholder="<?php esc_attr_e( 'https://www.youtube.com/watch?v=...', 'wp-sell-services' ); ?>">
			</div>
		</div>
		<?php
	}

	/**
	 * Render Step 4: Requirements.
	 *
	 * @param \WP_Post|null $_service Existing service post (unused, data from Alpine.js).
	 * @return void
	 */
	private function render_step_requirements( ?\WP_Post $_service ): void {
		?>
		<div class="wpss-wizard__step-header">
			<h2 class="wpss-wizard__step-title"><?php esc_html_e( 'Buyer Requirements', 'wp-sell-services' ); ?></h2>
			<p class="wpss-wizard__step-desc"><?php esc_html_e( 'Define what information you need from buyers before starting work.', 'wp-sell-services' ); ?></p>
		</div>

		<div class="wpss-wizard__step-body">
			<div class="wpss-requirements-list">
				<template x-for="(req, index) in data.requirements" :key="index">
					<div class="wpss-requirement-item">
						<div class="wpss-requirement-header">
							<span class="wpss-requirement-number" x-text="index + 1"></span>
							<button type="button" class="wpss-btn--icon" @click="removeRequirement(index)">
								<span class="dashicons dashicons-trash"></span>
							</button>
						</div>
						<div class="wpss-requirement-fields">
							<div class="wpss-form-group">
								<label class="wpss-form-label"><?php esc_html_e( 'Question', 'wp-sell-services' ); ?></label>
								<input type="text"
									class="wpss-form-input"
									x-model="data.requirements[index].question"
									placeholder="<?php esc_attr_e( 'What do you need from the buyer?', 'wp-sell-services' ); ?>">
							</div>
							<div class="wpss-form-row wpss-form-row--2col">
								<div class="wpss-form-group">
									<label class="wpss-form-label"><?php esc_html_e( 'Answer Type', 'wp-sell-services' ); ?></label>
									<select class="wpss-form-select" x-model="data.requirements[index].type">
										<option value="text"><?php esc_html_e( 'Short Text', 'wp-sell-services' ); ?></option>
										<option value="textarea"><?php esc_html_e( 'Long Text', 'wp-sell-services' ); ?></option>
										<option value="file"><?php esc_html_e( 'File Upload', 'wp-sell-services' ); ?></option>
										<option value="select"><?php esc_html_e( 'Multiple Choice', 'wp-sell-services' ); ?></option>
									</select>
								</div>
								<div class="wpss-form-group">
									<label class="wpss-toggle wpss-toggle--inline">
										<input type="checkbox" x-model="data.requirements[index].required">
										<span class="wpss-toggle__slider"></span>
										<span class="wpss-toggle__label"><?php esc_html_e( 'Required', 'wp-sell-services' ); ?></span>
									</label>
								</div>
							</div>
							<!-- Options for select type -->
							<div class="wpss-form-group" x-show="data.requirements[index].type === 'select'" x-cloak>
								<label class="wpss-form-label"><?php esc_html_e( 'Options', 'wp-sell-services' ); ?></label>
								<input type="text"
									class="wpss-form-input"
									x-model="data.requirements[index].options"
									placeholder="<?php esc_attr_e( 'Option 1, Option 2, Option 3', 'wp-sell-services' ); ?>">
								<div class="wpss-form-hint"><?php esc_html_e( 'Separate options with commas', 'wp-sell-services' ); ?></div>
							</div>
						</div>
					</div>
				</template>
			</div>

			<button type="button" class="wpss-btn wpss-btn--outline" @click="addRequirement()" x-show="canAddRequirement()">
				<span class="dashicons dashicons-plus-alt2"></span>
				<?php esc_html_e( 'Add Requirement', 'wp-sell-services' ); ?>
			</button>

			<div class="wpss-form-hint" x-show="!canAddRequirement()" x-cloak>
				<span class="wpss-limit-notice"><?php esc_html_e( 'Requirement limit reached.', 'wp-sell-services' ); ?></span>
				<?php if ( ! $this->is_pro_active() ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings&tab=pro' ) ); ?>" class="wpss-upgrade-link">
					<?php esc_html_e( 'Upgrade to Pro for unlimited', 'wp-sell-services' ); ?>
				</a>
				<?php endif; ?>
			</div>

			<div class="wpss-form-hint wpss-form-hint--block">
				<strong><?php esc_html_e( 'Tip:', 'wp-sell-services' ); ?></strong>
				<?php esc_html_e( 'Ask clear, specific questions to avoid delays. Requirements help you deliver exactly what the buyer needs.', 'wp-sell-services' ); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Step 5: Extras & FAQs.
	 *
	 * @param \WP_Post|null $_service Existing service post (unused, data from Alpine.js).
	 * @return void
	 */
	private function render_step_extras( ?\WP_Post $_service ): void {
		?>
		<div class="wpss-wizard__step-header">
			<h2 class="wpss-wizard__step-title"><?php esc_html_e( 'Extras & FAQs', 'wp-sell-services' ); ?></h2>
			<p class="wpss-wizard__step-desc"><?php esc_html_e( 'Offer add-ons and answer common questions.', 'wp-sell-services' ); ?></p>
		</div>

		<div class="wpss-wizard__step-body">
			<!-- Service Extras -->
			<div class="wpss-section">
				<h3 class="wpss-section__title"><?php esc_html_e( 'Service Extras', 'wp-sell-services' ); ?></h3>
				<p class="wpss-section__desc"><?php esc_html_e( 'Offer additional services buyers can add to their order.', 'wp-sell-services' ); ?></p>

				<div class="wpss-extras-list">
					<template x-for="(extra, index) in data.extras" :key="index">
						<div class="wpss-extra-item">
							<div class="wpss-extra-header">
								<span class="wpss-extra-title" x-text="extra.title || '<?php esc_attr_e( 'New Extra', 'wp-sell-services' ); ?>'"></span>
								<button type="button" class="wpss-btn--icon" @click="removeExtra(index)">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
							<div class="wpss-extra-fields">
								<div class="wpss-form-group">
									<label class="wpss-form-label"><?php esc_html_e( 'Extra Title', 'wp-sell-services' ); ?></label>
									<input type="text"
										class="wpss-form-input"
										x-model="data.extras[index].title"
										placeholder="<?php esc_attr_e( 'e.g., Express Delivery', 'wp-sell-services' ); ?>">
								</div>
								<div class="wpss-form-row wpss-form-row--2col">
									<div class="wpss-form-group">
										<label class="wpss-form-label"><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></label>
										<div class="wpss-input-group">
											<span class="wpss-input-prefix"><?php echo esc_html( wpss_get_currency_symbol() ); ?></span>
											<input type="number"
												class="wpss-form-input"
												x-model="data.extras[index].price"
												min="0"
												step="0.01">
										</div>
									</div>
									<div class="wpss-form-group">
										<label class="wpss-form-label"><?php esc_html_e( 'Extra Days', 'wp-sell-services' ); ?></label>
										<input type="number"
											class="wpss-form-input"
											x-model="data.extras[index].extra_days"
											min="0"
											placeholder="0">
									</div>
								</div>
								<div class="wpss-form-group">
									<label class="wpss-form-label"><?php esc_html_e( 'Description', 'wp-sell-services' ); ?></label>
									<textarea class="wpss-form-textarea"
										x-model="data.extras[index].description"
										rows="2"
										placeholder="<?php esc_attr_e( 'Describe what\'s included in this extra', 'wp-sell-services' ); ?>"></textarea>
								</div>
							</div>
						</div>
					</template>
				</div>

				<button type="button" class="wpss-btn wpss-btn--outline" @click="addExtra()" x-show="canAddExtra()">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Extra', 'wp-sell-services' ); ?>
				</button>

				<div class="wpss-form-hint" x-show="!canAddExtra()" x-cloak>
					<span class="wpss-limit-notice"><?php esc_html_e( 'Extras limit reached.', 'wp-sell-services' ); ?></span>
					<?php if ( ! $this->is_pro_active() ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings&tab=pro' ) ); ?>" class="wpss-upgrade-link">
						<?php esc_html_e( 'Upgrade to Pro for unlimited', 'wp-sell-services' ); ?>
					</a>
					<?php endif; ?>
				</div>
			</div>

			<!-- FAQs -->
			<div class="wpss-section">
				<h3 class="wpss-section__title"><?php esc_html_e( 'Frequently Asked Questions', 'wp-sell-services' ); ?></h3>
				<p class="wpss-section__desc"><?php esc_html_e( 'Answer common questions to help buyers make decisions.', 'wp-sell-services' ); ?></p>

				<div class="wpss-faqs-list">
					<template x-for="(faq, index) in data.faqs" :key="index">
						<div class="wpss-faq-item">
							<div class="wpss-faq-header">
								<span class="wpss-faq-number" x-text="'Q' + (index + 1)"></span>
								<button type="button" class="wpss-btn--icon" @click="removeFaq(index)">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
							<div class="wpss-faq-fields">
								<div class="wpss-form-group">
									<label class="wpss-form-label"><?php esc_html_e( 'Question', 'wp-sell-services' ); ?></label>
									<input type="text"
										class="wpss-form-input"
										x-model="data.faqs[index].question"
										placeholder="<?php esc_attr_e( 'What question do buyers often ask?', 'wp-sell-services' ); ?>">
								</div>
								<div class="wpss-form-group">
									<label class="wpss-form-label"><?php esc_html_e( 'Answer', 'wp-sell-services' ); ?></label>
									<textarea class="wpss-form-textarea"
										x-model="data.faqs[index].answer"
										rows="3"
										placeholder="<?php esc_attr_e( 'Provide a helpful answer...', 'wp-sell-services' ); ?>"></textarea>
								</div>
							</div>
						</div>
					</template>
				</div>

				<button type="button" class="wpss-btn wpss-btn--outline" @click="addFaq()" x-show="canAddFaq()">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add FAQ', 'wp-sell-services' ); ?>
				</button>

				<div class="wpss-form-hint" x-show="!canAddFaq()" x-cloak>
					<span class="wpss-limit-notice"><?php esc_html_e( 'FAQ limit reached.', 'wp-sell-services' ); ?></span>
					<?php if ( ! $this->is_pro_active() ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings&tab=pro' ) ); ?>" class="wpss-upgrade-link">
						<?php esc_html_e( 'Upgrade to Pro for unlimited', 'wp-sell-services' ); ?>
					</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Step 6: Review.
	 *
	 * @param \WP_Post|null $_service Existing service post (unused, data from Alpine.js).
	 * @return void
	 */
	private function render_step_review( ?\WP_Post $_service ): void {
		?>
		<div class="wpss-wizard__step-header">
			<h2 class="wpss-wizard__step-title"><?php esc_html_e( 'Review & Publish', 'wp-sell-services' ); ?></h2>
			<p class="wpss-wizard__step-desc"><?php esc_html_e( 'Review your service details before publishing.', 'wp-sell-services' ); ?></p>
		</div>

		<div class="wpss-wizard__step-body">
			<div class="wpss-review-grid">
				<!-- Service Preview Card -->
				<div class="wpss-review-section">
					<h4 class="wpss-review-section__title"><?php esc_html_e( 'Service Preview', 'wp-sell-services' ); ?></h4>
					<div class="wpss-service-preview-card">
						<div class="wpss-service-preview-image">
							<template x-if="data.gallery.main">
								<img :src="data.gallery.main.url" alt="">
							</template>
							<template x-if="!data.gallery.main">
								<div class="wpss-service-preview-placeholder">
									<span class="dashicons dashicons-format-image"></span>
								</div>
							</template>
						</div>
						<div class="wpss-service-preview-info">
							<h3 class="wpss-service-preview-title" x-text="data.title || '<?php esc_attr_e( 'Untitled Service', 'wp-sell-services' ); ?>'"></h3>
							<div class="wpss-service-preview-price">
								<?php esc_html_e( 'Starting at', 'wp-sell-services' ); ?>
								<strong x-text="'<?php echo esc_html( wpss_get_currency_symbol() ); ?>' + (data.packages.basic.price || '0')"></strong>
							</div>
						</div>
					</div>
				</div>

				<!-- Checklist -->
				<div class="wpss-review-section">
					<h4 class="wpss-review-section__title"><?php esc_html_e( 'Completion Checklist', 'wp-sell-services' ); ?></h4>
					<ul class="wpss-review-checklist">
						<li :class="{ 'completed': data.title?.length >= 10 }">
							<span class="dashicons" :class="data.title?.length >= 10 ? 'dashicons-yes-alt' : 'dashicons-marker'"></span>
							<?php esc_html_e( 'Service title (10+ characters)', 'wp-sell-services' ); ?>
						</li>
						<li :class="{ 'completed': data.category }">
							<span class="dashicons" :class="data.category ? 'dashicons-yes-alt' : 'dashicons-marker'"></span>
							<?php esc_html_e( 'Category selected', 'wp-sell-services' ); ?>
						</li>
						<li :class="{ 'completed': data.description?.length >= 120 }">
							<span class="dashicons" :class="data.description?.length >= 120 ? 'dashicons-yes-alt' : 'dashicons-marker'"></span>
							<?php esc_html_e( 'Description (120+ characters)', 'wp-sell-services' ); ?>
						</li>
						<li :class="{ 'completed': isPackageValid('basic') }">
							<span class="dashicons" :class="isPackageValid('basic') ? 'dashicons-yes-alt' : 'dashicons-marker'"></span>
							<?php esc_html_e( 'Basic package pricing complete', 'wp-sell-services' ); ?>
						</li>
						<li :class="{ 'completed': data.gallery.main }">
							<span class="dashicons" :class="data.gallery.main ? 'dashicons-yes-alt' : 'dashicons-marker'"></span>
							<?php esc_html_e( 'Main image uploaded', 'wp-sell-services' ); ?>
						</li>
					</ul>
				</div>

				<!-- Summary Sections -->
				<div class="wpss-review-section wpss-review-section--full">
					<h4 class="wpss-review-section__title"><?php esc_html_e( 'Pricing Summary', 'wp-sell-services' ); ?></h4>
					<div class="wpss-pricing-summary">
						<template x-for="tier in ['basic', 'standard', 'premium']" :key="tier">
							<div class="wpss-pricing-summary-item" x-show="tier === 'basic' || data.packages[tier].enabled" x-cloak>
								<span class="wpss-pricing-summary-name" x-text="data.packages[tier].name || tier.charAt(0).toUpperCase() + tier.slice(1)"></span>
								<span class="wpss-pricing-summary-price" x-text="'<?php echo esc_html( wpss_get_currency_symbol() ); ?>' + (data.packages[tier].price || '0')"></span>
								<span class="wpss-pricing-summary-delivery" x-text="(data.packages[tier].delivery_time || '?') + ' <?php esc_attr_e( 'days', 'wp-sell-services' ); ?>'"></span>
							</div>
						</template>
					</div>
				</div>
			</div>

			<!-- Validation Errors -->
			<div class="wpss-review-errors" x-show="validationErrors.length > 0" x-cloak>
				<h4><?php esc_html_e( 'Please fix these issues before publishing:', 'wp-sell-services' ); ?></h4>
				<ul>
					<template x-for="error in validationErrors" :key="error">
						<li x-text="error"></li>
					</template>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render category options recursively.
	 *
	 * @param array $categories Categories array.
	 * @param int   $parent Parent term ID.
	 * @param int   $depth Current depth.
	 * @return void
	 */
	private function render_category_options( array $categories, int $parent = 0, int $depth = 0 ): void {
		foreach ( $categories as $category ) {
			if ( (int) $category->parent !== $parent ) {
				continue;
			}

			$indent = str_repeat( '&mdash; ', $depth );
			?>
			<option value="<?php echo esc_attr( $category->term_id ); ?>">
				<?php echo $indent . esc_html( $category->name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</option>
			<?php
			$this->render_category_options( $categories, $category->term_id, $depth + 1 );
		}
	}

	/**
	 * Get service data for editing.
	 *
	 * @param int $service_id Service post ID.
	 * @return array Service data.
	 */
	private function get_service_data( int $service_id ): array {
		$service = get_post( $service_id );

		if ( ! $service ) {
			return array();
		}

		$packages     = get_post_meta( $service_id, '_wpss_packages', true );
		$packages     = ! empty( $packages ) ? $packages : array();
		$gallery      = get_post_meta( $service_id, '_wpss_gallery', true );
		$gallery      = ! empty( $gallery ) ? $gallery : array();
		$requirements = get_post_meta( $service_id, '_wpss_requirements', true );
		$requirements = ! empty( $requirements ) ? $requirements : array();
		$extras       = get_post_meta( $service_id, '_wpss_extras', true );
		$extras       = ! empty( $extras ) ? $extras : array();
		$faqs         = get_post_meta( $service_id, '_wpss_faqs', true );
		$faqs         = ! empty( $faqs ) ? $faqs : array();
		$categories   = wp_get_post_terms( $service_id, 'wpss_service_category', array( 'fields' => 'ids' ) );
		$categories   = is_wp_error( $categories ) ? array() : $categories;
		$tags         = wp_get_post_terms( $service_id, 'wpss_service_tag', array( 'fields' => 'names' ) );
		$tags         = is_wp_error( $tags ) ? array() : $tags;

		return array(
			'id'           => $service_id,
			'title'        => $service->post_title,
			'description'  => $service->post_content,
			'category'     => ! empty( $categories ) ? $categories[0] : '',
			'subcategory'  => count( $categories ) > 1 ? $categories[1] : '',
			'tags'         => implode( ', ', $tags ),
			'packages'     => $this->normalize_packages( $packages ),
			'gallery'      => $this->normalize_gallery( $gallery, $service_id ),
			'requirements' => $requirements,
			'extras'       => $extras,
			'faqs'         => $faqs,
		);
	}

	/**
	 * Normalize packages data.
	 *
	 * @param array $packages Packages data from meta.
	 * @return array Normalized packages.
	 */
	private function normalize_packages( array $packages ): array {
		$defaults = array(
			'basic'    => array(
				'enabled'       => true,
				'name'          => __( 'Basic', 'wp-sell-services' ),
				'description'   => '',
				'price'         => '',
				'delivery_time' => '',
				'revisions'     => '1',
				'features'      => array(),
			),
			'standard' => array(
				'enabled'       => false,
				'name'          => __( 'Standard', 'wp-sell-services' ),
				'description'   => '',
				'price'         => '',
				'delivery_time' => '',
				'revisions'     => '2',
				'features'      => array(),
			),
			'premium'  => array(
				'enabled'       => false,
				'name'          => __( 'Premium', 'wp-sell-services' ),
				'description'   => '',
				'price'         => '',
				'delivery_time' => '',
				'revisions'     => '3',
				'features'      => array(),
			),
		);

		return array_merge( $defaults, $packages );
	}

	/**
	 * Normalize gallery data.
	 *
	 * @param array $gallery Gallery data from meta.
	 * @param int   $service_id Service post ID.
	 * @return array Normalized gallery.
	 */
	private function normalize_gallery( array $gallery, int $service_id ): array {
		$result = array(
			'main'   => null,
			'images' => array(),
			'video'  => wpss_get_gallery_video_url( $gallery ),
		);

		// Get main image from featured image.
		$thumbnail_id = get_post_thumbnail_id( $service_id );
		if ( $thumbnail_id ) {
			$result['main'] = array(
				'id'  => $thumbnail_id,
				'url' => wp_get_attachment_image_url( $thumbnail_id, 'medium' ),
			);
		}

		// Get additional images using the shared helper to handle all formats.
		$image_ids = wpss_get_gallery_ids( $gallery );
		foreach ( $image_ids as $image_id ) {
			$url = wp_get_attachment_image_url( $image_id, 'medium' );
			if ( $url ) {
				$result['images'][] = array(
					'id'  => $image_id,
					'url' => $url,
				);
			}
		}

		return $result;
	}

	/**
	 * Enqueue wizard assets.
	 *
	 * @return void
	 */
	private function enqueue_assets(): void {
		wp_enqueue_media();
		wp_enqueue_style( 'dashicons' );

		wp_enqueue_style(
			'wpss-service-wizard',
			WPSS_PLUGIN_URL . 'assets/css/service-wizard.css',
			array( 'wpss-design-system' ),
			WPSS_VERSION
		);

		// Enqueue without alpinejs dependency so it loads BEFORE Alpine.
		// This ensures wpssServiceWizard is defined when Alpine auto-starts.
		wp_enqueue_script(
			'wpss-service-wizard',
			WPSS_PLUGIN_URL . 'assets/js/service-wizard.js',
			array(),
			WPSS_VERSION,
			true
		);

		// Make sure Alpine loads after service-wizard.
		wp_enqueue_script( 'alpinejs' );

		wp_localize_script(
			'wpss-service-wizard',
			'wpssWizard',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wpss_service_wizard' ),
				'dashboardUrl'   => $this->get_dashboard_url(),
				'currencySymbol' => wpss_get_currency_symbol(),
				'limits'         => $this->limits,
				'isPro'          => $this->is_pro_active(),
				'strings'        => array(
					'saving'            => __( 'Saving...', 'wp-sell-services' ),
					'saved'             => __( 'Draft saved!', 'wp-sell-services' ),
					'publishing'        => __( 'Publishing...', 'wp-sell-services' ),
					'published'         => __( 'Service published!', 'wp-sell-services' ),
					'error'             => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'unsavedChanges'    => __( 'You have unsaved changes. Are you sure you want to leave?', 'wp-sell-services' ),
					'confirmDelete'     => __( 'Are you sure you want to remove this item?', 'wp-sell-services' ),
					'validationTitle'   => __( 'Please enter a service title', 'wp-sell-services' ),
					'validationCat'     => __( 'Please select a category', 'wp-sell-services' ),
					'validationDesc'    => __( 'Please add a description (minimum 120 characters)', 'wp-sell-services' ),
					'validationPrice'   => __( 'Please set a price for the Basic package', 'wp-sell-services' ),
					'validationImage'   => __( 'Please upload a main image', 'wp-sell-services' ),
					'limitGallery'      => __( 'You have reached the maximum number of gallery images. Upgrade to Pro for unlimited images.', 'wp-sell-services' ),
					'limitExtras'       => __( 'You have reached the maximum number of extras. Upgrade to Pro for unlimited extras.', 'wp-sell-services' ),
					'limitFaq'          => __( 'You have reached the maximum number of FAQs. Upgrade to Pro for unlimited FAQs.', 'wp-sell-services' ),
					'limitRequirements' => __( 'You have reached the maximum number of requirements. Upgrade to Pro for unlimited requirements.', 'wp-sell-services' ),
				),
			)
		);
	}

	/**
	 * Check if Pro plugin is active.
	 *
	 * @return bool Whether Pro is active with valid license.
	 */
	private function is_pro_active(): bool {
		return defined( 'WPSS_PRO_VERSION' ) && function_exists( 'wpss_pro' );
	}

	/**
	 * AJAX: Save draft.
	 *
	 * @return void
	 */
	public function ajax_save_draft(): void {
		check_ajax_referer( 'wpss_service_wizard', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'wp-sell-services' ) ) );
		}

		$user_id = get_current_user_id();

		// Verify user is an approved vendor.
		$vendor_service = new \WPSellServices\Services\VendorService();
		if ( ! $vendor_service->is_vendor( $user_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be an approved vendor to create services.', 'wp-sell-services' ) ) );
		}

		// Check vendor status - suspended or pending vendors cannot create services.
		if ( ! current_user_can( 'manage_options' ) ) {
			$vendor_profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $user_id );
			if ( $vendor_profile && ! $vendor_profile->can_create_services() ) {
				if ( $vendor_profile->is_suspended() ) {
					wp_send_json_error( array( 'message' => __( 'Your vendor account is suspended. You cannot create services.', 'wp-sell-services' ) ) );
				} elseif ( $vendor_profile->is_pending() ) {
					wp_send_json_error( array( 'message' => __( 'Your vendor account is pending approval. You cannot create services yet.', 'wp-sell-services' ) ) );
				} else {
					wp_send_json_error( array( 'message' => __( 'You are not authorized to create services.', 'wp-sell-services' ) ) );
				}
			}
		}

		$service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;

		// Check max services limit for NEW services only (not editing existing).
		if ( ! $service_id && ! current_user_can( 'manage_options' ) ) {
			$limit_profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $user_id );
			if ( $limit_profile && $limit_profile->has_reached_service_limit() ) {
				$max = $limit_profile->get_max_services();
				wp_send_json_error(
					array(
						'message' => sprintf(
							/* translators: %d: maximum number of services allowed */
							__( 'You have reached the maximum limit of %d services. Please remove an existing service before creating a new one.', 'wp-sell-services' ),
							$max
						),
					)
				);
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string sanitized after decode.
		$raw_data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
		$data     = ! empty( $raw_data ) ? json_decode( $raw_data, true ) : array();

		if ( empty( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wp-sell-services' ) ) );
		}

		// Verify ownership if editing.
		if ( $service_id ) {
			$service = get_post( $service_id );
			if ( ! $service || $user_id !== (int) $service->post_author ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this service.', 'wp-sell-services' ) ) );
			}
		}

		// Sanitize data.
		$sanitized = $this->sanitize_service_data( $data );

		// Create or update post.
		$post_data = array(
			'post_type'    => 'wpss_service',
			'post_title'   => $sanitized['title'],
			'post_content' => $sanitized['description'],
			'post_status'  => 'draft',
			'post_author'  => $user_id,
		);

		if ( $service_id ) {
			$post_data['ID'] = $service_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$service_id = $result;

		// Save meta data.
		$this->save_service_meta( $service_id, $sanitized );

		// Save taxonomy terms.
		if ( ! empty( $sanitized['category'] ) ) {
			$terms = array( absint( $sanitized['category'] ) );
			if ( ! empty( $sanitized['subcategory'] ) ) {
				$terms[] = absint( $sanitized['subcategory'] );
			}
			wp_set_object_terms( $service_id, $terms, 'wpss_service_category' );
		}

		if ( ! empty( $sanitized['tags'] ) ) {
			$tags = array_map( 'trim', explode( ',', $sanitized['tags'] ) );
			wp_set_object_terms( $service_id, array_slice( $tags, 0, 5 ), 'wpss_service_tag' );
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Draft saved successfully.', 'wp-sell-services' ),
				'service_id' => $service_id,
			)
		);
	}

	/**
	 * AJAX: Publish service.
	 *
	 * @return void
	 */
	public function ajax_publish_service(): void {
		check_ajax_referer( 'wpss_service_wizard', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Please log in to continue.', 'wp-sell-services' ) ) );
		}

		$user_id = get_current_user_id();

		// Verify user is an approved vendor.
		$vendor_service = new \WPSellServices\Services\VendorService();
		if ( ! $vendor_service->is_vendor( $user_id ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You must be an approved vendor to create services.', 'wp-sell-services' ) ) );
		}

		// Check vendor status - suspended or pending vendors cannot create services.
		if ( ! current_user_can( 'manage_options' ) ) {
			$vendor_profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $user_id );
			if ( $vendor_profile && ! $vendor_profile->can_create_services() ) {
				if ( $vendor_profile->is_suspended() ) {
					wp_send_json_error( array( 'message' => __( 'Your vendor account is suspended. You cannot create services.', 'wp-sell-services' ) ) );
				} elseif ( $vendor_profile->is_pending() ) {
					wp_send_json_error( array( 'message' => __( 'Your vendor account is pending approval. You cannot create services yet.', 'wp-sell-services' ) ) );
				} else {
					wp_send_json_error( array( 'message' => __( 'You are not authorized to create services.', 'wp-sell-services' ) ) );
				}
			}
		}

		$service_id = isset( $_POST['service_id'] ) ? absint( $_POST['service_id'] ) : 0;

		// Server-side duplicate publish prevention.
		// Uses a short-lived transient lock to prevent concurrent publish requests
		// from the same user creating multiple services (e.g., double-click race condition).
		$lock_key = 'wpss_publishing_' . $user_id . '_' . $service_id;
		if ( get_transient( $lock_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Your service is already being published. Please wait.', 'wp-sell-services' ) ) );
		}
		set_transient( $lock_key, true, 30 );

		// Check max services limit when publishing would add a new service to the count.
		// This applies to: new services (no service_id) or drafts being published for the first time.
		if ( ! current_user_can( 'manage_options' ) ) {
			$is_new_to_count = false;
			if ( ! $service_id ) {
				$is_new_to_count = true;
			} else {
				$existing = get_post( $service_id );
				if ( $existing && 'draft' === $existing->post_status ) {
					$is_new_to_count = true;
				}
			}

			if ( $is_new_to_count ) {
				$limit_profile = \WPSellServices\Models\VendorProfile::get_by_user_id( $user_id );
				if ( $limit_profile && $limit_profile->has_reached_service_limit() ) {
					$max = $limit_profile->get_max_services();
					delete_transient( $lock_key );
					wp_send_json_error(
						array(
							'message' => sprintf(
								/* translators: %d: maximum number of services allowed */
								__( 'You have reached the maximum limit of %d services. Please remove an existing service before creating a new one.', 'wp-sell-services' ),
								$max
							),
						)
					);
				}
			}
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string sanitized after decode.
		$raw_data = isset( $_POST['data'] ) ? wp_unslash( $_POST['data'] ) : '';
		$data     = ! empty( $raw_data ) ? json_decode( $raw_data, true ) : array();

		if ( empty( $data ) ) {
			delete_transient( $lock_key );
			wp_send_json_error( array( 'message' => __( 'Invalid data.', 'wp-sell-services' ) ) );
		}

		// Validate required fields.
		$errors = $this->validate_service_data( $data );
		if ( ! empty( $errors ) ) {
			delete_transient( $lock_key );
			wp_send_json_error(
				array(
					'message' => __( 'Please complete all required fields.', 'wp-sell-services' ),
					'errors'  => $errors,
				)
			);
		}

		// Sanitize data.
		$sanitized = $this->sanitize_service_data( $data );

		// Determine post status based on moderation setting.
		$post_status = ModerationService::is_enabled() ? 'pending' : 'publish';

		// Create or update post.
		$post_data = array(
			'post_type'    => 'wpss_service',
			'post_title'   => $sanitized['title'],
			'post_content' => $sanitized['description'],
			'post_status'  => $post_status,
			'post_author'  => $user_id,
		);

		if ( $service_id ) {
			$service = get_post( $service_id );
			if ( ! $service || (int) $service->post_author !== $user_id ) {
				delete_transient( $lock_key );
				wp_send_json_error( array( 'message' => __( 'You do not have permission to edit this service.', 'wp-sell-services' ) ) );
			}
			$post_data['ID'] = $service_id;
			$result          = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			delete_transient( $lock_key );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$service_id = $result;

		// Save meta and taxonomy.
		$this->save_service_meta( $service_id, $sanitized );

		if ( ! empty( $sanitized['category'] ) ) {
			$terms = array( absint( $sanitized['category'] ) );
			if ( ! empty( $sanitized['subcategory'] ) ) {
				$terms[] = absint( $sanitized['subcategory'] );
			}
			wp_set_object_terms( $service_id, $terms, 'wpss_service_category' );
		}

		if ( ! empty( $sanitized['tags'] ) ) {
			$tags = array_map( 'trim', explode( ',', $sanitized['tags'] ) );
			wp_set_object_terms( $service_id, array_slice( $tags, 0, 5 ), 'wpss_service_tag' );
		}

		/**
		 * Fires after a service is saved via the wizard.
		 *
		 * Pro uses this to sync to WooCommerce product when WC adapter is active.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $service_id Service post ID.
		 * @param array $sanitized  Sanitized form data.
		 */
		do_action( 'wpss_service_wizard_saved', $service_id, $sanitized );

		// Prepare success response based on post status.
		if ( 'pending' === $post_status ) {
			$message      = __( 'Service submitted for review. You will be notified once it is approved.', 'wp-sell-services' );
			$redirect_url = wpss_get_dashboard_url( 'services' );
		} else {
			$message      = __( 'Service published successfully!', 'wp-sell-services' );
			$redirect_url = get_permalink( $service_id );
		}

		delete_transient( $lock_key );

		wp_send_json_success(
			array(
				'message'      => $message,
				'service_id'   => $service_id,
				'redirect_url' => $redirect_url,
			)
		);
	}

	/**
	 * AJAX: Upload gallery image.
	 *
	 * @return void
	 */
	public function ajax_upload_gallery(): void {
		check_ajax_referer( 'wpss_service_wizard', 'nonce' );

		if ( ! is_user_logged_in() || empty( $_FILES['file'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$file = $_FILES['file'];

		// Validate file type - only images allowed for gallery.
		$allowed_types = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
		$ext           = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed_types, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Only image files (JPG, PNG, GIF, WebP) are allowed.', 'wp-sell-services' ) ) );
		}

		// Validate MIME type to prevent extension spoofing.
		$file_info = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
		$mime_type = $file_info['type'] ?? '';

		$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );
		if ( ! in_array( $mime_type, $allowed_mimes, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid file type. Please upload a valid image.', 'wp-sell-services' ) ) );
		}

		// Check file size (max 5MB for gallery images).
		$max_size = 5 * 1024 * 1024;
		if ( $file['size'] > $max_size ) {
			wp_send_json_error( array( 'message' => __( 'Image file size exceeds 5MB limit.', 'wp-sell-services' ) ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'file', 0 );

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( array( 'message' => $attachment_id->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'id'  => $attachment_id,
				'url' => wp_get_attachment_image_url( $attachment_id, 'medium' ),
			)
		);
	}

	/**
	 * AJAX: Remove gallery image.
	 *
	 * @return void
	 */
	public function ajax_remove_gallery(): void {
		check_ajax_referer( 'wpss_service_wizard', 'nonce' );

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;

		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid attachment.', 'wp-sell-services' ) ) );
		}

		// Only allow deleting own attachments.
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || get_current_user_id() !== (int) $attachment->post_author ) {
			wp_send_json_error( array( 'message' => __( 'You cannot delete this attachment.', 'wp-sell-services' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * Sanitize service data.
	 *
	 * @param array $data Raw data.
	 * @return array Sanitized data.
	 */
	private function sanitize_service_data( array $data ): array {
		return array(
			'title'        => sanitize_text_field( $data['title'] ?? '' ),
			'description'  => wp_kses_post( $data['description'] ?? '' ),
			'category'     => absint( $data['category'] ?? 0 ),
			'subcategory'  => absint( $data['subcategory'] ?? 0 ),
			'tags'         => sanitize_text_field( $data['tags'] ?? '' ),
			'packages'     => $this->sanitize_packages( $data['packages'] ?? array() ),
			'gallery'      => $this->sanitize_gallery( $data['gallery'] ?? array() ),
			'requirements' => $this->sanitize_requirements( $data['requirements'] ?? array() ),
			'extras'       => $this->sanitize_extras( $data['extras'] ?? array() ),
			'faqs'         => $this->sanitize_faqs( $data['faqs'] ?? array() ),
		);
	}

	/**
	 * Sanitize packages data.
	 *
	 * @param array $packages Raw packages.
	 * @return array Sanitized packages.
	 */
	private function sanitize_packages( array $packages ): array {
		$sanitized = array();

		foreach ( array( 'basic', 'standard', 'premium' ) as $tier ) {
			if ( ! isset( $packages[ $tier ] ) ) {
				continue;
			}

			$pkg = $packages[ $tier ];

			$sanitized[ $tier ] = array(
				'enabled'       => 'basic' === $tier ? true : ! empty( $pkg['enabled'] ),
				'name'          => sanitize_text_field( $pkg['name'] ?? '' ),
				'description'   => sanitize_textarea_field( $pkg['description'] ?? '' ),
				'price'         => floatval( $pkg['price'] ?? 0 ),
				'delivery_time' => absint( $pkg['delivery_time'] ?? 0 ),
				'revisions'     => intval( $pkg['revisions'] ?? 0 ),
				'features'      => array_map( 'sanitize_text_field', $pkg['features'] ?? array() ),
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize gallery data.
	 *
	 * @param array $gallery Raw gallery.
	 * @return array Sanitized gallery.
	 */
	private function sanitize_gallery( array $gallery ): array {
		$sanitized = array(
			'main'   => null,
			'images' => array(),
			'video'  => '',
		);

		// Verify main image ownership before accepting.
		if ( ! empty( $gallery['main']['id'] ) ) {
			$main_id = absint( $gallery['main']['id'] );
			if ( $this->user_can_use_attachment( $main_id ) ) {
				$sanitized['main'] = array(
					'id'  => $main_id,
					'url' => esc_url( $gallery['main']['url'] ?? '' ),
				);
			}
		}

		// Verify each gallery image ownership before accepting.
		if ( ! empty( $gallery['images'] ) && is_array( $gallery['images'] ) ) {
			foreach ( $gallery['images'] as $image ) {
				if ( ! empty( $image['id'] ) ) {
					$image_id = absint( $image['id'] );
					if ( $this->user_can_use_attachment( $image_id ) ) {
						$sanitized['images'][] = array(
							'id'  => $image_id,
							'url' => esc_url( $image['url'] ?? '' ),
						);
					}
				}
			}
		}

		if ( ! empty( $gallery['video'] ) ) {
			$sanitized['video'] = esc_url_raw( $gallery['video'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize requirements data.
	 *
	 * @param array $requirements Raw requirements.
	 * @return array Sanitized requirements.
	 */
	private function sanitize_requirements( array $requirements ): array {
		$sanitized = array();

		foreach ( $requirements as $req ) {
			if ( empty( $req['question'] ) ) {
				continue;
			}

			$sanitized[] = array(
				'question' => sanitize_text_field( $req['question'] ),
				'type'     => in_array( $req['type'] ?? 'text', array( 'text', 'textarea', 'file', 'select' ), true ) ? $req['type'] : 'text',
				'required' => ! empty( $req['required'] ),
				'options'  => sanitize_text_field( $req['options'] ?? '' ),
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize extras data.
	 *
	 * @param array $extras Raw extras.
	 * @return array Sanitized extras.
	 */
	private function sanitize_extras( array $extras ): array {
		$sanitized = array();

		foreach ( $extras as $extra ) {
			if ( empty( $extra['title'] ) ) {
				continue;
			}

			$sanitized[] = array(
				'title'       => sanitize_text_field( $extra['title'] ),
				'description' => sanitize_textarea_field( $extra['description'] ?? '' ),
				'price'       => floatval( $extra['price'] ?? 0 ),
				'extra_days'  => absint( $extra['extra_days'] ?? 0 ),
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize FAQs data.
	 *
	 * @param array $faqs Raw FAQs.
	 * @return array Sanitized FAQs.
	 */
	private function sanitize_faqs( array $faqs ): array {
		$sanitized = array();

		foreach ( $faqs as $faq ) {
			if ( empty( $faq['question'] ) || empty( $faq['answer'] ) ) {
				continue;
			}

			$sanitized[] = array(
				'question' => sanitize_text_field( $faq['question'] ),
				'answer'   => wp_kses_post( $faq['answer'] ),
			);
		}

		return $sanitized;
	}

	/**
	 * Validate service data.
	 *
	 * @param array $data Service data.
	 * @return array Validation errors.
	 */
	private function validate_service_data( array $data ): array {
		$errors = array();

		if ( empty( $data['title'] ) || strlen( $data['title'] ) < 10 ) {
			$errors[] = __( 'Service title must be at least 10 characters.', 'wp-sell-services' );
		}

		if ( empty( $data['category'] ) ) {
			$errors[] = __( 'Please select a category.', 'wp-sell-services' );
		}

		if ( empty( $data['description'] ) || strlen( $data['description'] ) < 120 ) {
			$errors[] = __( 'Description must be at least 120 characters.', 'wp-sell-services' );
		}

		if ( empty( $data['packages']['basic']['price'] ) || floatval( $data['packages']['basic']['price'] ) < 5 ) {
			$errors[] = __( 'Basic package price must be at least $5.', 'wp-sell-services' );
		}

		if ( empty( $data['packages']['basic']['delivery_time'] ) ) {
			$errors[] = __( 'Please set a delivery time for the Basic package.', 'wp-sell-services' );
		}

		if ( empty( $data['gallery']['main'] ) ) {
			$errors[] = __( 'Please upload a main image.', 'wp-sell-services' );
		}

		return $errors;
	}

	/**
	 * Save service meta data.
	 *
	 * @param int   $service_id Service post ID.
	 * @param array $data Sanitized data.
	 * @return void
	 */
	private function save_service_meta( int $service_id, array $data ): void {
		// Save packages.
		update_post_meta( $service_id, '_wpss_packages', $data['packages'] );

		// Save flat meta for backward compatibility (used by WCOrderProvider fallback).
		$basic = $data['packages']['basic'] ?? reset( $data['packages'] ) ?? array();
		update_post_meta( $service_id, '_wpss_delivery_days', (int) ( $basic['delivery_days'] ?? $basic['delivery_time'] ?? 7 ) );
		update_post_meta( $service_id, '_wpss_revisions', (int) ( $basic['revisions'] ?? 0 ) );

		// Save starting price (from basic package).
		update_post_meta( $service_id, '_wpss_starting_price', $data['packages']['basic']['price'] ?? 0 );

		// Save gallery images.
		$gallery_ids = array();
		foreach ( $data['gallery']['images'] as $image ) {
			$gallery_ids[] = $image['id'];
		}

		update_post_meta(
			$service_id,
			'_wpss_gallery',
			array(
				'images' => $gallery_ids,
				'video'  => $data['gallery']['video'] ?? '',
			)
		);

		// Set featured image from main gallery image, or fallback to first gallery image.
		if ( ! empty( $data['gallery']['main']['id'] ) ) {
			set_post_thumbnail( $service_id, $data['gallery']['main']['id'] );
		} elseif ( ! empty( $gallery_ids[0] ) ) {
			// Fallback: use first gallery image as featured image.
			set_post_thumbnail( $service_id, $gallery_ids[0] );
		}

		// Save requirements.
		update_post_meta( $service_id, '_wpss_requirements', $data['requirements'] );

		// Save extras.
		update_post_meta( $service_id, '_wpss_extras', $data['extras'] );

		// Save FAQs.
		update_post_meta( $service_id, '_wpss_faqs', $data['faqs'] );
	}

	/**
	 * Render login required message.
	 *
	 * @return string Login message HTML.
	 */
	private function render_login_required(): string {
		return '<div class="wpss-notice wpss-notice-warning">' .
			esc_html__( 'Please log in to create a service.', 'wp-sell-services' ) .
			' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log In', 'wp-sell-services' ) . '</a></div>';
	}

	/**
	 * Render not a vendor message.
	 *
	 * @return string Not vendor message HTML.
	 */
	private function render_not_vendor(): string {
		$registration_url = wpss_get_page_url( 'become_vendor' );
		if ( ! $registration_url ) {
			$registration_url = home_url();
		}

		return '<div class="wpss-notice wpss-notice-info">' .
			esc_html__( 'You need to be a registered vendor to create services.', 'wp-sell-services' ) .
			' <a href="' . esc_url( $registration_url ) . '">' . esc_html__( 'Become a Vendor', 'wp-sell-services' ) . '</a></div>';
	}

	/**
	 * Render vendor status notice for non-active vendors.
	 *
	 * @param object $vendor_profile Vendor profile object.
	 * @return string Notice HTML.
	 */
	private function render_vendor_status_notice( object $vendor_profile ): string {
		if ( $vendor_profile->is_suspended() ) {
			return '<div class="wpss-notice wpss-notice-error">' .
				esc_html__( 'Your vendor account is suspended. You cannot create services at this time.', 'wp-sell-services' ) .
				'</div>';
		}

		if ( $vendor_profile->is_pending() ) {
			return '<div class="wpss-notice wpss-notice-warning">' .
				esc_html__( 'Your vendor account is pending approval. You will be able to create services once approved.', 'wp-sell-services' ) .
				'</div>';
		}

		return '<div class="wpss-notice wpss-notice-error">' .
			esc_html__( 'You are not authorized to create services.', 'wp-sell-services' ) .
			'</div>';
	}

	/**
	 * Render the service limit reached notice.
	 *
	 * @param \WPSellServices\Models\VendorProfile $vendor_profile Vendor profile.
	 * @return string HTML notice.
	 */
	private function render_service_limit_notice( \WPSellServices\Models\VendorProfile $vendor_profile ): string {
		$max_services  = $vendor_profile->get_max_services();
		$services_url  = wpss_get_dashboard_url( 'services' );
		$services_link = $services_url
			? ' <a href="' . esc_url( $services_url ) . '">' . esc_html__( 'Manage your services', 'wp-sell-services' ) . '</a>'
			: '';

		return '<div class="wpss-notice wpss-notice-warning">' .
			sprintf(
				/* translators: %d: maximum number of services allowed */
				esc_html__( 'You have reached the maximum limit of %d services. Please remove an existing service before creating a new one.', 'wp-sell-services' ),
				$max_services
			) .
			$services_link .
			'</div>';
	}

	/**
	 * Get dashboard URL.
	 *
	 * @return string Dashboard URL.
	 */
	private function get_dashboard_url(): string {
		$url = wpss_get_page_url( 'dashboard' );
		return $url ? $url : home_url();
	}

	/**
	 * Verify current user can use an attachment.
	 *
	 * Checks if the attachment exists and belongs to the current user,
	 * or if the current user is an administrator.
	 *
	 * @param int $attachment_id Attachment ID to verify.
	 * @return bool True if user can use this attachment.
	 */
	private function user_can_use_attachment( int $attachment_id ): bool {
		if ( $attachment_id <= 0 ) {
			return false;
		}

		$attachment = get_post( $attachment_id );

		// Attachment must exist and be an attachment post type.
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return false;
		}

		// Admins can use any attachment.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Check if current user is the attachment author.
		return get_current_user_id() === (int) $attachment->post_author;
	}
}
