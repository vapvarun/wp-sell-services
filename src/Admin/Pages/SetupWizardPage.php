<?php
/**
 * Setup Wizard Page
 *
 * Post-activation onboarding wizard that guides admins through
 * 6 steps to configure their marketplace.
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.4.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Setup Wizard Page Class.
 *
 * @since 1.4.0
 */
class SetupWizardPage {

	/**
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
		add_action( 'admin_init', array( $this, 'maybe_redirect' ) );
		add_action( 'wp_ajax_wpss_wizard_save_step', array( $this, 'ajax_save_step' ) );
		add_action( 'wp_ajax_wpss_wizard_create_categories', array( $this, 'ajax_create_categories' ) );
		add_action( 'wp_ajax_wpss_wizard_complete', array( $this, 'ajax_complete' ) );
	}

	/**
	 * Add wizard submenu page.
	 *
	 * Shows as a visible submenu when setup is incomplete,
	 * otherwise hides it (still accessible via direct URL).
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		$parent = $this->should_show_in_menu() ? 'wp-sell-services' : null;

		add_submenu_page(
			$parent,
			__( 'Setup Wizard', 'wp-sell-services' ),
			__( 'Setup Wizard', 'wp-sell-services' ),
			'manage_options',
			'wpss-setup-wizard',
			array( $this, 'render' )
		);
	}

	/**
	 * Whether to show the wizard link in the admin menu.
	 *
	 * Visible when wizard hasn't been completed or no services exist.
	 *
	 * @return bool
	 */
	private function should_show_in_menu(): bool {
		if ( ! get_option( 'wpss_setup_wizard_completed' ) ) {
			return true;
		}

		$service_count = wp_count_posts( 'wpss_service' );

		return ! $service_count || 0 === (int) ( $service_count->publish ?? 0 );
	}

	/**
	 * Redirect to wizard after activation.
	 *
	 * @return void
	 */
	public function maybe_redirect(): void {
		if ( ! get_transient( 'wpss_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'wpss_activation_redirect' );

		// Bail if activating from network or bulk, or AJAX, or wrong cap.
		if ( wp_doing_ajax() || is_network_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=wpss-setup-wizard' ) );
		exit;
	}

	/**
	 * AJAX: Save a wizard step.
	 *
	 * Handles steps 1 (basics), 2 (gateway), and 5 (vendor).
	 *
	 * @return void
	 */
	public function ajax_save_step(): void {
		check_ajax_referer( 'wpss_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$step = absint( $_POST['step'] ?? 0 );

		switch ( $step ) {
			case 1:
				$this->save_step_basics();
				break;
			case 2:
				$this->save_step_gateway();
				break;
			case 5:
				$this->save_step_vendor();
				break;
			default:
				wp_send_json_error( array( 'message' => __( 'Invalid step.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * Save step 1 — Platform Basics.
	 *
	 * @return void
	 */
	private function save_step_basics(): void {
		$general    = get_option( 'wpss_general', array() );
		$commission = get_option( 'wpss_commission', array() );

		if ( isset( $_POST['platform_name'] ) ) {
			$general['platform_name'] = sanitize_text_field( wp_unslash( $_POST['platform_name'] ) );
		}
		if ( isset( $_POST['currency'] ) ) {
			$general['currency'] = sanitize_text_field( wp_unslash( $_POST['currency'] ) );
		}
		if ( isset( $_POST['commission_rate'] ) ) {
			$commission['commission_rate'] = max( 0, min( 100, (float) $_POST['commission_rate'] ) );
		}

		update_option( 'wpss_general', $general );
		update_option( 'wpss_commission', $commission );

		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'wp-sell-services' ) ) );
	}

	/**
	 * Save step 2 — Payment Gateway.
	 *
	 * @return void
	 */
	private function save_step_gateway(): void {
		$gateway = sanitize_key( $_POST['gateway'] ?? '' );

		if ( 'stripe' === $gateway ) {
			$settings = get_option( 'wpss_stripe_settings', array() );

			$settings['enabled']              = true;
			$settings['test_mode']            = ! empty( $_POST['stripe_test_mode'] );
			$settings['test_secret_key']      = sanitize_text_field( wp_unslash( $_POST['stripe_test_secret_key'] ?? '' ) );
			$settings['test_publishable_key'] = sanitize_text_field( wp_unslash( $_POST['stripe_test_publishable_key'] ?? '' ) );

			update_option( 'wpss_stripe_settings', $settings );
		} elseif ( 'paypal' === $gateway ) {
			$settings = get_option( 'wpss_paypal_settings', array() );

			$settings['enabled']       = true;
			$settings['sandbox']       = ! empty( $_POST['paypal_sandbox'] );
			$settings['client_id']     = sanitize_text_field( wp_unslash( $_POST['paypal_client_id'] ?? '' ) );
			$settings['client_secret'] = sanitize_text_field( wp_unslash( $_POST['paypal_client_secret'] ?? '' ) );

			update_option( 'wpss_paypal_settings', $settings );
		} elseif ( 'offline' === $gateway ) {
			$settings = get_option( 'wpss_offline_settings', array() );

			$settings['enabled']     = true;
			$settings['title']       = sanitize_text_field( wp_unslash( $_POST['offline_title'] ?? __( 'Offline Payment', 'wp-sell-services' ) ) );
			$settings['description'] = sanitize_textarea_field( wp_unslash( $_POST['offline_description'] ?? '' ) );

			update_option( 'wpss_offline_settings', $settings );
		}

		wp_send_json_success( array( 'message' => __( 'Gateway configured.', 'wp-sell-services' ) ) );
	}

	/**
	 * Save step 5 — Vendor Settings.
	 *
	 * @return void
	 */
	private function save_step_vendor(): void {
		$vendor = get_option( 'wpss_vendor', array() );

		if ( isset( $_POST['vendor_registration'] ) ) {
			$vendor['vendor_registration'] = in_array( $_POST['vendor_registration'], array( 'open', 'approval' ), true )
				? sanitize_key( $_POST['vendor_registration'] )
				: 'open';
		}
		if ( isset( $_POST['max_services_per_vendor'] ) ) {
			$vendor['max_services_per_vendor'] = absint( $_POST['max_services_per_vendor'] );
		}

		$vendor['require_service_moderation'] = ! empty( $_POST['require_service_moderation'] );
		$vendor['require_verification']       = ! empty( $_POST['require_verification'] );

		update_option( 'wpss_vendor', $vendor );

		wp_send_json_success( array( 'message' => __( 'Vendor settings saved.', 'wp-sell-services' ) ) );
	}

	/**
	 * AJAX: Create service categories.
	 *
	 * @return void
	 */
	public function ajax_create_categories(): void {
		check_ajax_referer( 'wpss_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$categories = isset( $_POST['categories'] ) ? array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['categories'] ) ) : array();
		$created    = 0;
		$skipped    = 0;

		foreach ( $categories as $name ) {
			$name = trim( $name );
			if ( empty( $name ) ) {
				continue;
			}

			if ( term_exists( $name, 'wpss_service_category' ) ) {
				++$skipped;
				continue;
			}

			$result = wp_insert_term( $name, 'wpss_service_category' );
			if ( ! is_wp_error( $result ) ) {
				++$created;
			}
		}

		wp_send_json_success(
			array(
				'created' => $created,
				'skipped' => $skipped,
				'message' => sprintf(
					/* translators: 1: created count, 2: skipped count */
					__( '%1$d categories created, %2$d already existed.', 'wp-sell-services' ),
					$created,
					$skipped
				),
			)
		);
	}

	/**
	 * AJAX: Mark wizard as complete.
	 *
	 * @return void
	 */
	public function ajax_complete(): void {
		check_ajax_referer( 'wpss_wizard_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		update_option( 'wpss_setup_wizard_completed', time() );

		wp_send_json_success( array( 'message' => __( 'Setup complete!', 'wp-sell-services' ) ) );
	}

	/**
	 * Render the wizard page.
	 *
	 * @return void
	 */
	public function render(): void {
		// Load current option values for pre-filling.
		$general    = get_option( 'wpss_general', array() );
		$commission = get_option( 'wpss_commission', array() );
		$vendor     = get_option( 'wpss_vendor', array() );
		$pages      = get_option( 'wpss_pages', array() );
		$currencies = wpss_get_currencies();

		$platform_name   = $general['platform_name'] ?? get_bloginfo( 'name' );
		$currency        = $general['currency'] ?? 'USD';
		$commission_rate = $commission['commission_rate'] ?? 10;

		$vendor_registration        = $vendor['vendor_registration'] ?? 'open';
		$max_services               = $vendor['max_services_per_vendor'] ?? 20;
		$require_moderation         = ! empty( $vendor['require_service_moderation'] );
		$require_verification       = ! empty( $vendor['require_verification'] );

		$page_fields = array(
			'services_page' => __( 'Services', 'wp-sell-services' ),
			'dashboard'     => __( 'Dashboard', 'wp-sell-services' ),
			'become_vendor' => __( 'Vendor Registration', 'wp-sell-services' ),
			'checkout'      => __( 'Checkout', 'wp-sell-services' ),
		);
		?>
		<div id="wpss-wizard-wrap">
			<!-- Header -->
			<div class="wpss-wizard-header">
				<div class="wpss-wizard-logo">
					<span class="dashicons dashicons-store"></span>
					<span><?php esc_html_e( 'WP Sell Services', 'wp-sell-services' ); ?></span>
				</div>
				<div class="wpss-wizard-steps-indicator" id="wpss-steps-indicator"></div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings' ) ); ?>" class="wpss-wizard-exit">
					<?php esc_html_e( 'Exit Wizard', 'wp-sell-services' ); ?>
				</a>
			</div>

			<!-- Step 1: Platform Basics -->
			<div class="wpss-wizard-step active" data-step="1">
				<h2><?php esc_html_e( 'Platform Basics', 'wp-sell-services' ); ?></h2>
				<p class="wpss-wizard-desc"><?php esc_html_e( 'Let\'s set up the foundation of your marketplace.', 'wp-sell-services' ); ?></p>

				<div class="wpss-wizard-field">
					<label for="wpss-wiz-name"><?php esc_html_e( 'Marketplace Name', 'wp-sell-services' ); ?></label>
					<input type="text" id="wpss-wiz-name" value="<?php echo esc_attr( $platform_name ); ?>">
				</div>

				<div class="wpss-wizard-field">
					<label for="wpss-wiz-currency"><?php esc_html_e( 'Currency', 'wp-sell-services' ); ?></label>
					<select id="wpss-wiz-currency">
						<?php foreach ( $currencies as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $currency, $code ); ?>>
								<?php echo esc_html( $code . ' — ' . $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="wpss-wizard-field">
					<label for="wpss-wiz-commission"><?php esc_html_e( 'Commission Rate (%)', 'wp-sell-services' ); ?></label>
					<input type="number" id="wpss-wiz-commission" value="<?php echo esc_attr( $commission_rate ); ?>" min="0" max="100" step="0.1">
					<p class="description"><?php esc_html_e( 'The percentage you keep from each transaction.', 'wp-sell-services' ); ?></p>
				</div>

				<div class="wpss-wizard-actions">
					<span></span>
					<div>
						<button type="button" class="button wpss-wizard-skip" data-skip="1"><?php esc_html_e( 'Skip', 'wp-sell-services' ); ?></button>
						<button type="button" class="button button-primary wpss-wizard-save" data-step="1"><?php esc_html_e( 'Save & Continue', 'wp-sell-services' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Step 2: Payment Gateway -->
			<div class="wpss-wizard-step" data-step="2">
				<h2><?php esc_html_e( 'Payment Gateway', 'wp-sell-services' ); ?></h2>
				<p class="wpss-wizard-desc"><?php esc_html_e( 'Choose how you\'ll accept payments. You can change this later in Settings.', 'wp-sell-services' ); ?></p>

				<div class="wpss-wizard-gateway-options">
					<label class="wpss-wizard-radio-card">
						<input type="radio" name="wpss_gateway" value="stripe">
						<div class="wpss-wizard-radio-content">
							<strong><?php esc_html_e( 'Stripe', 'wp-sell-services' ); ?></strong>
							<span><?php esc_html_e( 'Credit cards, Apple Pay, Google Pay', 'wp-sell-services' ); ?></span>
						</div>
					</label>
					<label class="wpss-wizard-radio-card">
						<input type="radio" name="wpss_gateway" value="paypal">
						<div class="wpss-wizard-radio-content">
							<strong><?php esc_html_e( 'PayPal', 'wp-sell-services' ); ?></strong>
							<span><?php esc_html_e( 'PayPal checkout and credit cards', 'wp-sell-services' ); ?></span>
						</div>
					</label>
					<label class="wpss-wizard-radio-card">
						<input type="radio" name="wpss_gateway" value="offline" checked>
						<div class="wpss-wizard-radio-content">
							<strong><?php esc_html_e( 'Offline Payment', 'wp-sell-services' ); ?></strong>
							<span><?php esc_html_e( 'Bank transfer, cash, or manual payments', 'wp-sell-services' ); ?></span>
						</div>
					</label>
				</div>

				<!-- Stripe panel -->
				<div class="wpss-wizard-gateway-panel" data-gateway="stripe" style="display:none;">
					<div class="wpss-wizard-field">
						<label>
							<input type="checkbox" id="wpss-wiz-stripe-test" checked>
							<?php esc_html_e( 'Test Mode', 'wp-sell-services' ); ?>
						</label>
					</div>
					<div class="wpss-wizard-field">
						<label for="wpss-wiz-stripe-sk"><?php esc_html_e( 'Test Secret Key', 'wp-sell-services' ); ?></label>
						<input type="text" id="wpss-wiz-stripe-sk" placeholder="sk_test_...">
					</div>
					<div class="wpss-wizard-field">
						<label for="wpss-wiz-stripe-pk"><?php esc_html_e( 'Test Publishable Key', 'wp-sell-services' ); ?></label>
						<input type="text" id="wpss-wiz-stripe-pk" placeholder="pk_test_...">
					</div>
				</div>

				<!-- PayPal panel -->
				<div class="wpss-wizard-gateway-panel" data-gateway="paypal" style="display:none;">
					<div class="wpss-wizard-field">
						<label>
							<input type="checkbox" id="wpss-wiz-paypal-sandbox" checked>
							<?php esc_html_e( 'Sandbox Mode', 'wp-sell-services' ); ?>
						</label>
					</div>
					<div class="wpss-wizard-field">
						<label for="wpss-wiz-paypal-id"><?php esc_html_e( 'Client ID', 'wp-sell-services' ); ?></label>
						<input type="text" id="wpss-wiz-paypal-id">
					</div>
					<div class="wpss-wizard-field">
						<label for="wpss-wiz-paypal-secret"><?php esc_html_e( 'Client Secret', 'wp-sell-services' ); ?></label>
						<input type="text" id="wpss-wiz-paypal-secret">
					</div>
				</div>

				<!-- Offline panel -->
				<div class="wpss-wizard-gateway-panel" data-gateway="offline">
					<div class="wpss-wizard-field">
						<label for="wpss-wiz-offline-title"><?php esc_html_e( 'Payment Title', 'wp-sell-services' ); ?></label>
						<input type="text" id="wpss-wiz-offline-title" value="<?php esc_attr_e( 'Offline Payment', 'wp-sell-services' ); ?>">
					</div>
					<div class="wpss-wizard-field">
						<label for="wpss-wiz-offline-desc"><?php esc_html_e( 'Instructions', 'wp-sell-services' ); ?></label>
						<textarea id="wpss-wiz-offline-desc" rows="3" placeholder="<?php esc_attr_e( 'Please transfer to our bank account...', 'wp-sell-services' ); ?>"></textarea>
					</div>
				</div>

				<div class="wpss-wizard-actions">
					<button type="button" class="button wpss-wizard-back" data-back="1"><?php esc_html_e( 'Back', 'wp-sell-services' ); ?></button>
					<div>
						<button type="button" class="button wpss-wizard-skip" data-skip="2"><?php esc_html_e( 'Skip', 'wp-sell-services' ); ?></button>
						<button type="button" class="button button-primary wpss-wizard-save" data-step="2"><?php esc_html_e( 'Save & Continue', 'wp-sell-services' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Step 3: Create Pages -->
			<div class="wpss-wizard-step" data-step="3">
				<h2><?php esc_html_e( 'Create Pages', 'wp-sell-services' ); ?></h2>
				<p class="wpss-wizard-desc"><?php esc_html_e( 'These pages are required for your marketplace to work. We\'ll create them with the right shortcodes.', 'wp-sell-services' ); ?></p>

				<div class="wpss-wizard-pages-list">
					<?php foreach ( $page_fields as $field => $label ) : ?>
						<?php $page_id = $pages[ $field ] ?? 0; ?>
						<div class="wpss-wizard-page-row" data-field="<?php echo esc_attr( $field ); ?>">
							<div class="wpss-wizard-page-info">
								<strong><?php echo esc_html( $label ); ?></strong>
								<?php if ( $page_id && get_post( $page_id ) ) : ?>
									<span class="wpss-wizard-badge wpss-badge-success"><?php esc_html_e( 'Created', 'wp-sell-services' ); ?></span>
								<?php else : ?>
									<span class="wpss-wizard-badge wpss-badge-pending"><?php esc_html_e( 'Not Created', 'wp-sell-services' ); ?></span>
								<?php endif; ?>
							</div>
							<button type="button" class="button wpss-wizard-create-page"
								data-field="<?php echo esc_attr( $field ); ?>"
								data-title="<?php echo esc_attr( $label ); ?>"
								<?php echo ( $page_id && get_post( $page_id ) ) ? 'disabled' : ''; ?>>
								<?php echo ( $page_id && get_post( $page_id ) ) ? esc_html__( 'Done', 'wp-sell-services' ) : esc_html__( 'Create', 'wp-sell-services' ); ?>
							</button>
						</div>
					<?php endforeach; ?>
				</div>

				<div style="margin-top: 16px;">
					<button type="button" class="button" id="wpss-wizard-create-all-pages"><?php esc_html_e( 'Create All Pages', 'wp-sell-services' ); ?></button>
				</div>

				<div class="wpss-wizard-actions">
					<button type="button" class="button wpss-wizard-back" data-back="2"><?php esc_html_e( 'Back', 'wp-sell-services' ); ?></button>
					<div>
						<button type="button" class="button wpss-wizard-skip" data-skip="3"><?php esc_html_e( 'Skip', 'wp-sell-services' ); ?></button>
						<button type="button" class="button button-primary wpss-wizard-next" data-next="4"><?php esc_html_e( 'Continue', 'wp-sell-services' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Step 4: Service Categories -->
			<div class="wpss-wizard-step" data-step="4">
				<h2><?php esc_html_e( 'Service Categories', 'wp-sell-services' ); ?></h2>
				<p class="wpss-wizard-desc"><?php esc_html_e( 'Select suggested categories or add your own. These help buyers find services.', 'wp-sell-services' ); ?></p>

				<div class="wpss-wizard-chips" id="wpss-wizard-chips">
					<?php
					$presets = array(
						__( 'Web Development', 'wp-sell-services' ),
						__( 'Graphic Design', 'wp-sell-services' ),
						__( 'Writing', 'wp-sell-services' ),
						__( 'Digital Marketing', 'wp-sell-services' ),
						__( 'Video', 'wp-sell-services' ),
						__( 'Music', 'wp-sell-services' ),
						__( 'Programming', 'wp-sell-services' ),
						__( 'Business', 'wp-sell-services' ),
						__( 'Photography', 'wp-sell-services' ),
						__( 'Data', 'wp-sell-services' ),
					);
					foreach ( $presets as $preset ) :
						$exists = term_exists( $preset, 'wpss_service_category' );
						?>
						<button type="button"
							class="wpss-wizard-chip <?php echo $exists ? 'active disabled' : ''; ?>"
							data-name="<?php echo esc_attr( $preset ); ?>"
							<?php echo $exists ? 'disabled' : ''; ?>>
							<?php echo esc_html( $preset ); ?>
							<?php if ( $exists ) : ?>
								<span class="dashicons dashicons-yes-alt"></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>

				<div class="wpss-wizard-field" style="margin-top: 16px;">
					<label for="wpss-wiz-custom-cat"><?php esc_html_e( 'Add Your Own', 'wp-sell-services' ); ?></label>
					<div style="display: flex; gap: 8px;">
						<input type="text" id="wpss-wiz-custom-cat" placeholder="<?php esc_attr_e( 'e.g. AI Services', 'wp-sell-services' ); ?>">
						<button type="button" class="button" id="wpss-wiz-add-cat"><?php esc_html_e( 'Add', 'wp-sell-services' ); ?></button>
					</div>
				</div>

				<div class="wpss-wizard-actions">
					<button type="button" class="button wpss-wizard-back" data-back="3"><?php esc_html_e( 'Back', 'wp-sell-services' ); ?></button>
					<div>
						<button type="button" class="button wpss-wizard-skip" data-skip="4"><?php esc_html_e( 'Skip', 'wp-sell-services' ); ?></button>
						<button type="button" class="button button-primary wpss-wizard-save" data-step="4"><?php esc_html_e( 'Save & Continue', 'wp-sell-services' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Step 5: Vendor Settings -->
			<div class="wpss-wizard-step" data-step="5">
				<h2><?php esc_html_e( 'Vendor Settings', 'wp-sell-services' ); ?></h2>
				<p class="wpss-wizard-desc"><?php esc_html_e( 'Configure how vendors can join and operate on your marketplace.', 'wp-sell-services' ); ?></p>

				<div class="wpss-wizard-field">
					<label><?php esc_html_e( 'Vendor Registration', 'wp-sell-services' ); ?></label>
					<div class="wpss-wizard-radio-group">
						<label class="wpss-wizard-radio-inline">
							<input type="radio" name="wpss_vendor_reg" value="open" <?php checked( 'open', $vendor_registration ); ?>>
							<?php esc_html_e( 'Open — Anyone can register', 'wp-sell-services' ); ?>
						</label>
						<label class="wpss-wizard-radio-inline">
							<input type="radio" name="wpss_vendor_reg" value="approval" <?php checked( 'approval', $vendor_registration ); ?>>
							<?php esc_html_e( 'Requires Approval — Admin must approve', 'wp-sell-services' ); ?>
						</label>
					</div>
				</div>

				<div class="wpss-wizard-field">
					<label for="wpss-wiz-max-services"><?php esc_html_e( 'Max Services Per Vendor', 'wp-sell-services' ); ?></label>
					<input type="number" id="wpss-wiz-max-services" value="<?php echo esc_attr( $max_services ); ?>" min="1" max="999">
				</div>

				<div class="wpss-wizard-field">
					<label>
						<input type="checkbox" id="wpss-wiz-moderation" <?php checked( $require_moderation ); ?>>
						<?php esc_html_e( 'Require service moderation before publishing', 'wp-sell-services' ); ?>
					</label>
				</div>

				<div class="wpss-wizard-field">
					<label>
						<input type="checkbox" id="wpss-wiz-verification" <?php checked( $require_verification ); ?>>
						<?php esc_html_e( 'Require vendor verification', 'wp-sell-services' ); ?>
					</label>
				</div>

				<div class="wpss-wizard-actions">
					<button type="button" class="button wpss-wizard-back" data-back="4"><?php esc_html_e( 'Back', 'wp-sell-services' ); ?></button>
					<div>
						<button type="button" class="button wpss-wizard-skip" data-skip="5"><?php esc_html_e( 'Skip', 'wp-sell-services' ); ?></button>
						<button type="button" class="button button-primary wpss-wizard-save" data-step="5"><?php esc_html_e( 'Save & Continue', 'wp-sell-services' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Step 6: Done -->
			<div class="wpss-wizard-step" data-step="6">
				<div class="wpss-wizard-done">
					<span class="dashicons dashicons-yes-alt"></span>
					<h2><?php esc_html_e( 'Your Marketplace is Ready!', 'wp-sell-services' ); ?></h2>
					<p><?php esc_html_e( 'You\'ve completed the initial setup. Here\'s what to do next:', 'wp-sell-services' ); ?></p>
				</div>

				<div class="wpss-wizard-cards">
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="wpss-wizard-card">
						<span class="dashicons dashicons-plus-alt"></span>
						<strong><?php esc_html_e( 'Create Your First Service', 'wp-sell-services' ); ?></strong>
						<span><?php esc_html_e( 'Add a service listing to your marketplace.', 'wp-sell-services' ); ?></span>
					</a>
					<a href="#" class="wpss-wizard-card" id="wpss-wizard-import-demo">
						<span class="dashicons dashicons-download"></span>
						<strong><?php esc_html_e( 'Import Demo Content', 'wp-sell-services' ); ?></strong>
						<span><?php esc_html_e( 'Get started with sample services and vendors.', 'wp-sell-services' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings' ) ); ?>" class="wpss-wizard-card">
						<span class="dashicons dashicons-admin-generic"></span>
						<strong><?php esc_html_e( 'Go to Settings', 'wp-sell-services' ); ?></strong>
						<span><?php esc_html_e( 'Fine-tune your marketplace configuration.', 'wp-sell-services' ); ?></span>
					</a>
				</div>

				<div class="wpss-wizard-actions" style="justify-content: center;">
					<button type="button" class="button wpss-wizard-back" data-back="5"><?php esc_html_e( 'Back', 'wp-sell-services' ); ?></button>
				</div>
			</div>
		</div>

		<?php $this->render_styles(); ?>
		<?php $this->render_scripts(); ?>
		<?php
	}

	/**
	 * Output inline styles.
	 *
	 * @return void
	 */
	private function render_styles(): void {
		?>
		<style>
			/* Hide WP admin chrome */
			#wpss-wizard-wrap { position: fixed; inset: 0; z-index: 100000; background: #f0f0f1; overflow-y: auto; }
			#wpadminbar, #adminmenumain, #adminmenuback, #wpfooter { display: none !important; }
			#wpcontent, #wpbody-content { margin-left: 0 !important; padding: 0 !important; }

			/* Header */
			.wpss-wizard-header {
				display: flex; align-items: center; justify-content: space-between;
				padding: 16px 32px; background: #fff; border-bottom: 1px solid #ddd;
			}
			.wpss-wizard-logo { display: flex; align-items: center; gap: 8px; font-size: 16px; font-weight: 600; color: #1e1e1e; }
			.wpss-wizard-logo .dashicons { font-size: 24px; width: 24px; height: 24px; color: #1dbf73; }
			.wpss-wizard-exit { text-decoration: none; color: #646970; font-size: 13px; }
			.wpss-wizard-exit:hover { color: #d63638; }

			/* Steps indicator */
			.wpss-wizard-steps-indicator { display: flex; gap: 6px; }
			.wpss-wizard-steps-indicator .step-dot {
				width: 10px; height: 10px; border-radius: 50%;
				background: #ddd; transition: background 0.2s;
			}
			.wpss-wizard-steps-indicator .step-dot.active { background: #1dbf73; }
			.wpss-wizard-steps-indicator .step-dot.done { background: #1dbf73; opacity: 0.5; }

			/* Step panels */
			.wpss-wizard-step {
				display: none; max-width: 640px; margin: 48px auto; padding: 40px;
				background: #fff; border: 1px solid #ddd; border-radius: 8px;
			}
			.wpss-wizard-step.active { display: block; }
			.wpss-wizard-step h2 { margin: 0 0 4px; font-size: 22px; }
			.wpss-wizard-desc { color: #646970; margin: 0 0 24px; font-size: 14px; }

			/* Fields */
			.wpss-wizard-field { margin-bottom: 16px; }
			.wpss-wizard-field > label { display: block; margin-bottom: 4px; font-weight: 500; font-size: 13px; }
			.wpss-wizard-field input[type="text"],
			.wpss-wizard-field input[type="number"],
			.wpss-wizard-field select,
			.wpss-wizard-field textarea { width: 100%; max-width: 100%; }
			.wpss-wizard-field .description { color: #646970; font-size: 12px; margin-top: 4px; }

			/* Radio cards (gateway) */
			.wpss-wizard-gateway-options { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
			.wpss-wizard-radio-card {
				display: flex; align-items: center; gap: 12px; padding: 14px 16px;
				border: 2px solid #ddd; border-radius: 6px; cursor: pointer; transition: border-color 0.2s;
			}
			.wpss-wizard-radio-card:hover { border-color: #1dbf73; }
			.wpss-wizard-radio-card input:checked ~ .wpss-wizard-radio-content { /* sibling highlight */ }
			.wpss-wizard-radio-card:has(input:checked) { border-color: #1dbf73; background: #f0faf5; }
			.wpss-wizard-radio-content strong { display: block; font-size: 14px; }
			.wpss-wizard-radio-content span { color: #646970; font-size: 12px; }

			/* Gateway panels */
			.wpss-wizard-gateway-panel { padding: 16px; background: #f9f9f9; border-radius: 6px; margin-bottom: 16px; }

			/* Radio inline (vendor reg) */
			.wpss-wizard-radio-group { display: flex; flex-direction: column; gap: 8px; margin-top: 4px; }
			.wpss-wizard-radio-inline { display: flex; align-items: center; gap: 6px; font-size: 13px; }

			/* Actions bar */
			.wpss-wizard-actions {
				display: flex; justify-content: space-between; align-items: center;
				margin-top: 32px; padding-top: 20px; border-top: 1px solid #eee;
			}
			.wpss-wizard-actions div { display: flex; gap: 8px; }
			.wpss-wizard-actions .button-primary { background: #1dbf73; border-color: #1dbf73; }
			.wpss-wizard-actions .button-primary:hover { background: #19a463; border-color: #19a463; }

			/* Pages list */
			.wpss-wizard-pages-list { display: flex; flex-direction: column; gap: 8px; }
			.wpss-wizard-page-row {
				display: flex; align-items: center; justify-content: space-between;
				padding: 12px 16px; background: #f9f9f9; border-radius: 6px;
			}
			.wpss-wizard-page-info { display: flex; align-items: center; gap: 10px; }
			.wpss-wizard-badge {
				font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 500;
			}
			.wpss-badge-success { background: #d4edda; color: #155724; }
			.wpss-badge-pending { background: #f8d7da; color: #721c24; }

			/* Category chips */
			.wpss-wizard-chips { display: flex; flex-wrap: wrap; gap: 8px; }
			.wpss-wizard-chip {
				padding: 8px 16px; border: 2px solid #ddd; border-radius: 20px;
				background: #fff; cursor: pointer; font-size: 13px; transition: all 0.2s;
				display: inline-flex; align-items: center; gap: 4px;
			}
			.wpss-wizard-chip:hover:not(.disabled) { border-color: #1dbf73; }
			.wpss-wizard-chip.active { border-color: #1dbf73; background: #f0faf5; color: #155724; }
			.wpss-wizard-chip.disabled { opacity: 0.6; cursor: default; }
			.wpss-wizard-chip .dashicons { font-size: 16px; width: 16px; height: 16px; color: #1dbf73; }

			/* Done screen */
			.wpss-wizard-done { text-align: center; margin-bottom: 32px; }
			.wpss-wizard-done > .dashicons {
				font-size: 64px; width: 64px; height: 64px; color: #1dbf73; margin-bottom: 16px;
			}
			.wpss-wizard-done h2 { margin: 0 0 8px; }
			.wpss-wizard-done p { color: #646970; }

			/* Action cards */
			.wpss-wizard-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
			.wpss-wizard-card {
				display: flex; flex-direction: column; align-items: center; text-align: center;
				padding: 24px 16px; border: 1px solid #ddd; border-radius: 8px;
				text-decoration: none; color: #1e1e1e; transition: all 0.2s; gap: 8px;
			}
			.wpss-wizard-card:hover { border-color: #1dbf73; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
			.wpss-wizard-card .dashicons { font-size: 32px; width: 32px; height: 32px; color: #1dbf73; }
			.wpss-wizard-card strong { font-size: 14px; }
			.wpss-wizard-card span:last-child { color: #646970; font-size: 12px; }

			/* Spinner override */
			.wpss-wizard-step .spinner { float: none; margin: 0; visibility: visible; display: none; }
			.wpss-wizard-step .spinner.is-active { display: inline-block; }

			/* Responsive */
			@media (max-width: 782px) {
				.wpss-wizard-step { margin: 16px; padding: 24px; }
				.wpss-wizard-cards { grid-template-columns: 1fr; }
				.wpss-wizard-header { padding: 12px 16px; }
			}
		</style>
		<?php
	}

	/**
	 * Output inline scripts.
	 *
	 * @return void
	 */
	private function render_scripts(): void {
		?>
		<script>
		jQuery(function($) {
			var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
			var wizardNonce = '<?php echo esc_js( wp_create_nonce( 'wpss_wizard_nonce' ) ); ?>';
			var settingsNonce = '<?php echo esc_js( wp_create_nonce( 'wpss_settings_nonce' ) ); ?>';
			var demoNonce = '<?php echo esc_js( wp_create_nonce( 'wpss_demo_content' ) ); ?>';
			var totalSteps = 6;
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

				// If arriving at step 6, mark complete.
				if (step === 6) {
					$.post(ajaxUrl, { action: 'wpss_wizard_complete', nonce: wizardNonce });
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

			// Save & Continue: steps 1, 2, 5 via wpss_wizard_save_step.
			$(document).on('click', '.wpss-wizard-save', function() {
				var btn = $(this);
				var step = parseInt(btn.data('step'), 10);
				var data = { action: 'wpss_wizard_save_step', nonce: wizardNonce, step: step };

				btn.prop('disabled', true);

				if (step === 1) {
					data.platform_name = $('#wpss-wiz-name').val();
					data.currency = $('#wpss-wiz-currency').val();
					data.commission_rate = $('#wpss-wiz-commission').val();
				} else if (step === 2) {
					data.gateway = $('input[name="wpss_gateway"]:checked').val();
					if (data.gateway === 'stripe') {
						data.stripe_test_mode = $('#wpss-wiz-stripe-test').is(':checked') ? 1 : 0;
						data.stripe_test_secret_key = $('#wpss-wiz-stripe-sk').val();
						data.stripe_test_publishable_key = $('#wpss-wiz-stripe-pk').val();
					} else if (data.gateway === 'paypal') {
						data.paypal_sandbox = $('#wpss-wiz-paypal-sandbox').is(':checked') ? 1 : 0;
						data.paypal_client_id = $('#wpss-wiz-paypal-id').val();
						data.paypal_client_secret = $('#wpss-wiz-paypal-secret').val();
					} else if (data.gateway === 'offline') {
						data.offline_title = $('#wpss-wiz-offline-title').val();
						data.offline_description = $('#wpss-wiz-offline-desc').val();
					}
				} else if (step === 4) {
					// Categories: collect selected chip names.
					var cats = [];
					$('.wpss-wizard-chip.active:not(.disabled)').each(function() {
						cats.push($(this).data('name'));
					});
					if (cats.length === 0) {
						btn.prop('disabled', false);
						goToStep(5);
						return;
					}
					// Use category-specific AJAX.
					$.post(ajaxUrl, {
						action: 'wpss_wizard_create_categories',
						nonce: wizardNonce,
						categories: cats
					}, function() {
						btn.prop('disabled', false);
						goToStep(5);
					}).fail(function() {
						btn.prop('disabled', false);
					});
					return;
				} else if (step === 5) {
					data.vendor_registration = $('input[name="wpss_vendor_reg"]:checked').val();
					data.max_services_per_vendor = $('#wpss-wiz-max-services').val();
					data.require_service_moderation = $('#wpss-wiz-moderation').is(':checked') ? 1 : 0;
					data.require_verification = $('#wpss-wiz-verification').is(':checked') ? 1 : 0;
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

				btn.prop('disabled', true).text('<?php echo esc_js( __( 'Creating...', 'wp-sell-services' ) ); ?>');

				$.post(ajaxUrl, {
					action: 'wpss_create_page',
					nonce: settingsNonce,
					field: field,
					title: title
				}, function(response) {
					if (response.success) {
						btn.text('<?php echo esc_js( __( 'Done', 'wp-sell-services' ) ); ?>');
						row.find('.wpss-wizard-badge')
							.removeClass('wpss-badge-pending')
							.addClass('wpss-badge-success')
							.text('<?php echo esc_js( __( 'Created', 'wp-sell-services' ) ); ?>');
					} else {
						btn.prop('disabled', false).text('<?php echo esc_js( __( 'Create', 'wp-sell-services' ) ); ?>');
					}
				}).fail(function() {
					btn.prop('disabled', false).text('<?php echo esc_js( __( 'Create', 'wp-sell-services' ) ); ?>');
				});
			});

			// Create all pages (sequentially to avoid race condition on wpss_pages option).
			$('#wpss-wizard-create-all-pages').on('click', function() {
				var allBtn = $(this);
				var buttons = $('.wpss-wizard-create-page:not(:disabled)').toArray();
				allBtn.prop('disabled', true).text('<?php echo esc_js( __( 'Creating...', 'wp-sell-services' ) ); ?>');

				function createNext(index) {
					if (index >= buttons.length) {
						allBtn.text('<?php echo esc_js( __( 'All Created', 'wp-sell-services' ) ); ?>');
						return;
					}
					var btn = $(buttons[index]);
					var row = btn.closest('.wpss-wizard-page-row');
					var field = btn.data('field');
					var title = btn.data('title');

					btn.prop('disabled', true).text('<?php echo esc_js( __( 'Creating...', 'wp-sell-services' ) ); ?>');

					$.post(ajaxUrl, {
						action: 'wpss_create_page',
						nonce: settingsNonce,
						field: field,
						title: title
					}, function(response) {
						if (response.success) {
							btn.text('<?php echo esc_js( __( 'Done', 'wp-sell-services' ) ); ?>');
							row.find('.wpss-wizard-badge')
								.removeClass('wpss-badge-pending')
								.addClass('wpss-badge-success')
								.text('<?php echo esc_js( __( 'Created', 'wp-sell-services' ) ); ?>');
						} else {
							btn.prop('disabled', false).text('<?php echo esc_js( __( 'Create', 'wp-sell-services' ) ); ?>');
						}
						createNext(index + 1);
					}).fail(function() {
						btn.prop('disabled', false).text('<?php echo esc_js( __( 'Create', 'wp-sell-services' ) ); ?>');
						createNext(index + 1);
					});
				}

				createNext(0);
			});

			// Import demo content.
			$('#wpss-wizard-import-demo').on('click', function(e) {
				e.preventDefault();
				var card = $(this);
				card.find('strong').text('<?php echo esc_js( __( 'Importing...', 'wp-sell-services' ) ); ?>');

				$.post(ajaxUrl, {
					action: 'wpss_import_demo_content',
					nonce: demoNonce
				}, function(response) {
					if (response.success) {
						card.find('strong').text('<?php echo esc_js( __( 'Demo Imported!', 'wp-sell-services' ) ); ?>');
						card.find('span:last').text(response.data.message);
					} else {
						card.find('strong').text('<?php echo esc_js( __( 'Import Failed', 'wp-sell-services' ) ); ?>');
					}
				}).fail(function() {
					card.find('strong').text('<?php echo esc_js( __( 'Import Failed', 'wp-sell-services' ) ); ?>');
				});
			});
		});
		</script>
		<?php
	}
}
