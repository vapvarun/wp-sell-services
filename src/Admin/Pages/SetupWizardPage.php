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
	 * Admin page hook suffix returned by add_submenu_page().
	 *
	 * Always set now: the page is registered whether or not it appears in the
	 * menu, so its assets can still be enqueued when an owner reaches it
	 * directly after setup is complete.
	 *
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
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
		// Registered ALWAYS, listed only while it is worth listing.
		//
		// Menu visibility and page accessibility are different questions, and
		// tying them together made the wizard unreachable the moment setup was
		// marked complete: admin.php?page=wpss-setup-wizard answered 403, so an
		// owner could never re-run it, revisit a step, or follow an old link or
		// a support instruction to it.
		//
		// Passing a null parent registers the page without putting it in any
		// menu, which is the standard way to keep a screen addressable. The
		// capability check is unchanged, so this grants nobody new access.
		$parent = $this->should_show_in_menu() ? 'wp-sell-services' : null;

		$hook = add_submenu_page(
			$parent,
			__( 'Setup Wizard', 'wp-sell-services' ),
			__( 'Setup Wizard', 'wp-sell-services' ),
			'manage_options',
			'wpss-setup-wizard',
			array( $this, 'render' )
		);

		if ( $hook ) {
			$this->hook_suffix = $hook;

			// Give core a title to work with.
			//
			// A null parent (above) is what keeps this screen addressable, but it
			// also means the page is in no $submenu, so core's
			// get_admin_page_title() cannot match it and leaves $GLOBALS['title']
			// null. admin-header.php then runs strip_tags( null ), which is a
			// PHP 8.1+ deprecation, and the browser tab renders as a bare
			// "<separator> Site Name" with no page name at all.
			//
			// The fix belongs here rather than in the menu: keep the screen
			// reachable AND name it. `load-{$hook}` fires before admin-header.php
			// is included, so the title is set by the time core reads it.
			add_action(
				'load-' . $hook,
				static function (): void {
					$GLOBALS['title'] = __( 'Setup Wizard', 'wp-sell-services' );
				}
			);
		}
	}

	/**
	 * Enqueue the wizard stylesheet + script on the wizard screen only.
	 *
	 * Matches on the page query arg rather than only the stored hook suffix:
	 * once setup is complete the submenu is no longer registered, so there IS
	 * no hook suffix — but the screen stays reachable by direct URL and by the
	 * "Re-Run Setup Wizard" button in Settings, and it must still be styled.
	 *
	 * @since 1.3.0
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_styles( string $hook ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen check.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'wpss-setup-wizard' !== $page && ( '' === $this->hook_suffix || $this->hook_suffix !== $hook ) ) {
			return;
		}

		// The wizard renders standalone (no admin chrome), so it cannot rely on
		// another screen's enqueue having run. Register the tokens explicitly.
		wpss_register_design_system( true );

		wp_enqueue_style(
			'wpss-admin-wizard',
			\WPSS_PLUGIN_URL . 'assets/css/admin-wizard.css',
			array( 'wpss-design-system' ),
			\WPSS_VERSION
		);
		wp_style_add_data( 'wpss-admin-wizard', 'rtl', 'replace' );

		wp_enqueue_script(
			'wpss-admin-wizard',
			\WPSS_PLUGIN_URL . 'assets/js/admin-wizard.js',
			array( 'jquery' ),
			\WPSS_VERSION,
			true
		);
		wp_set_script_translations( 'wpss-admin-wizard', 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );

		// Config + button labels the wizard JS used to interpolate inline.
		// wp_localize_script prints before the file it attaches to.
		wp_localize_script(
			'wpss-admin-wizard',
			'wpssWizard',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'wizardNonce'   => wp_create_nonce( 'wpss_wizard_nonce' ),
				'settingsNonce' => wp_create_nonce( 'wpss_settings_nonce' ),
				'demoNonce'     => wp_create_nonce( 'wpss_demo_content' ),
				'i18n'          => array(
					'creating'     => __( 'Creating...', 'wp-sell-services' ),
					'create'       => __( 'Create', 'wp-sell-services' ),
					'created'      => __( 'Created', 'wp-sell-services' ),
					'done'         => __( 'Done', 'wp-sell-services' ),
					'allCreated'   => __( 'All Created', 'wp-sell-services' ),
					'importing'    => __( 'Importing...', 'wp-sell-services' ),
					'demoImported' => __( 'Demo Imported!', 'wp-sell-services' ),
					'importFailed' => __( 'Import Failed', 'wp-sell-services' ),
				),
			)
		);
	}

	/**
	 * Whether to show the wizard link in the admin menu.
	 *
	 * Visible while setup is unfinished, and again afterwards if the site still
	 * has no published services - a marketplace with nothing to sell is not
	 * really set up, so the nudge stays.
	 *
	 * The completion flag is written in ONE place: step 5 of the wizard. An
	 * owner who set the site up and left before reaching that step - or who
	 * clicked Exit Wizard, which goes to Settings without marking anything -
	 * never got the flag, so the menu entry stayed forever. Measured on a site
	 * with 6 mapped pages and 52 published services: the flag was still false
	 * and "Setup Wizard" was still in the menu.
	 *
	 * The check below was already written to notice a configured site; it was
	 * simply unreachable, because the early return above it fired first
	 * whenever the flag was empty - which is exactly the case it needed to
	 * catch. Reordering makes it work, and recording the result means the
	 * question is asked once rather than on every admin page load.
	 *
	 * @return bool
	 */
	private function should_show_in_menu(): bool {
		$completed = (bool) get_option( 'wpss_setup_wizard_completed' );

		if ( ! $completed && $this->site_is_configured() ) {
			// Self-heal rather than nag. Deliberately not autoloaded: it is read
			// on admin screens only.
			update_option( 'wpss_setup_wizard_completed', time(), false );
			$completed = true;
		}

		if ( ! $completed ) {
			return true;
		}

		$service_count = wp_count_posts( 'wpss_service' );

		return ! $service_count || 0 === (int) ( $service_count->publish ?? 0 );
	}

	/**
	 * Whether the site plainly has been set up already.
	 *
	 * Both halves are required. Pages alone are not enough - the installer
	 * creates those on activation, so every site has them from minute one and
	 * treating that as "configured" would suppress the wizard for the very
	 * owners who need it. A published service is the signal that a human has
	 * actually used the thing.
	 *
	 * @since 1.5.1
	 *
	 * @return bool
	 */
	private function site_is_configured(): bool {
		$pages = (array) get_option( 'wpss_pages', array() );

		$has_core_pages = ! empty( $pages['services_page'] ) && ! empty( $pages['dashboard'] );

		if ( ! $has_core_pages ) {
			return false;
		}

		$service_count = wp_count_posts( 'wpss_service' );

		return $service_count && (int) ( $service_count->publish ?? 0 ) > 0;
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
	 * Handles steps 1 (basics) and 4 (vendor). Payment gateway is no longer a
	 * wizard step — it is guided from the finish screen and configured on the
	 * Payments settings page.
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
			case 4:
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
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in ajax_save_step().
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
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Save step 4 — Vendor Settings.
	 *
	 * @return void
	 */
	private function save_step_vendor(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified in ajax_save_step().
		$vendor = get_option( 'wpss_vendor', array() );

		if ( isset( $_POST['vendor_registration'] ) ) {
			$vendor['vendor_registration'] = in_array( $_POST['vendor_registration'], array( 'open', 'approval' ), true )
				? sanitize_key( $_POST['vendor_registration'] )
				: 'open';
		}
		if ( isset( $_POST['max_services_per_vendor'] ) ) {
			$vendor['max_services_per_vendor'] = absint( $_POST['max_services_per_vendor'] );
		}

		// `require_verification` is deliberately NOT written. Nothing in free or
		// Pro reads it to gate anything, the Vendor settings panel has no field
		// for it, and sanitize_vendor_settings() drops it the first time an
		// admin saves that panel — so the wizard was promising a control that
		// did nothing and then losing it anyway. Its checkbox is gone from the
		// wizard too; a toggle that changes no behaviour is worse than none.
		$vendor['require_service_moderation'] = ! empty( $_POST['require_service_moderation'] );

		update_option( 'wpss_vendor', $vendor );

		wp_send_json_success( array( 'message' => __( 'Vendor settings saved.', 'wp-sell-services' ) ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
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

		// Validate that all required pages exist before completing.
		$required_pages = wpss_get_required_pages();

		$pages   = get_option( 'wpss_pages', array() );
		$missing = array();

		foreach ( $required_pages as $key => $label ) {
			$page_id = (int) ( $pages[ $key ] ?? 0 );
			if ( ! $page_id || ! get_post( $page_id ) || 'publish' !== get_post_status( $page_id ) ) {
				$missing[] = $label;
			}
		}

		if ( ! empty( $missing ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: comma-separated list of missing page names */
						__( 'Required pages are missing: %s. Please go back to Step 3 and create them.', 'wp-sell-services' ),
						implode( ', ', $missing )
					),
				)
			);
		}

		update_option( 'wpss_setup_wizard_completed', time() );

		// Clear the pages notice dismissal so it doesn't linger from a previous state.
		delete_metadata( 'user', 0, '_wpss_pages_notice_dismissed', '', true );

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

		$vendor_registration = $vendor['vendor_registration'] ?? 'open';
		$max_services        = $vendor['max_services_per_vendor'] ?? 20;
		$require_moderation  = ! empty( $vendor['require_service_moderation'] );

		// The wizard's Create buttons post these titles, so they must be the
		// registry's titles: "Checkout" here (against the installer's "Service
		// Checkout") is how sites running WooCommerce ended up with the WPSS
		// page on /checkout-2/ instead of /service-checkout/.
		$page_fields = wpss_get_setup_pages();
		?>
		<div id="wpss-wizard-wrap">
			<!-- Header -->
			<div class="wpss-wizard-header">
				<div class="wpss-wizard-logo">
					<i data-lucide="store" class="wpss-icon" aria-hidden="true"></i>
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

			<!-- Step 2: Create Pages -->
			<div class="wpss-wizard-step" data-step="2">
				<h2><?php esc_html_e( 'Create Pages', 'wp-sell-services' ); ?></h2>
				<p class="wpss-wizard-desc"><?php esc_html_e( 'These pages are required for your marketplace to work. We\'ll create them with the right shortcodes.', 'wp-sell-services' ); ?></p>

				<div class="wpss-wizard-pages-list">
					<?php foreach ( $page_fields as $field => $page_def ) : ?>
						<?php
						$label    = $page_def['title'];
						$optional = empty( $page_def['required'] );
						$page_id  = $pages[ $field ] ?? 0;
						?>
						<div class="wpss-wizard-page-row" data-field="<?php echo esc_attr( $field ); ?>">
							<div class="wpss-wizard-page-info">
								<strong><?php echo esc_html( $label ); ?></strong>
								<?php if ( $optional ) : ?>
									<span class="wpss-wizard-badge"><?php esc_html_e( 'Optional', 'wp-sell-services' ); ?></span>
								<?php endif; ?>
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
					<button type="button" class="button wpss-wizard-back" data-back="1"><?php esc_html_e( 'Back', 'wp-sell-services' ); ?></button>
					<div>
						<button type="button" class="button wpss-wizard-skip" data-skip="2"><?php esc_html_e( 'Skip', 'wp-sell-services' ); ?></button>
						<button type="button" class="button button-primary wpss-wizard-next" data-next="3"><?php esc_html_e( 'Continue', 'wp-sell-services' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Step 3: Service Categories -->
			<div class="wpss-wizard-step" data-step="3">
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
								<i data-lucide="check-circle-2" class="wpss-icon" aria-hidden="true"></i>
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
					<button type="button" class="button wpss-wizard-back" data-back="2"><?php esc_html_e( 'Back', 'wp-sell-services' ); ?></button>
					<div>
						<button type="button" class="button wpss-wizard-skip" data-skip="3"><?php esc_html_e( 'Skip', 'wp-sell-services' ); ?></button>
						<button type="button" class="button button-primary wpss-wizard-save" data-step="3"><?php esc_html_e( 'Save & Continue', 'wp-sell-services' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Step 4: Vendor Settings -->
			<div class="wpss-wizard-step" data-step="4">
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

		<?php // "Require vendor verification" removed: no code gated on it, so it promised a control that changed nothing. See the note in the vendor-step save handler. ?>

				<div class="wpss-wizard-actions">
					<button type="button" class="button wpss-wizard-back" data-back="3"><?php esc_html_e( 'Back', 'wp-sell-services' ); ?></button>
					<div>
						<button type="button" class="button wpss-wizard-skip" data-skip="4"><?php esc_html_e( 'Skip', 'wp-sell-services' ); ?></button>
						<button type="button" class="button button-primary wpss-wizard-save" data-step="4"><?php esc_html_e( 'Save & Continue', 'wp-sell-services' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Step 5: Done -->
			<div class="wpss-wizard-step" data-step="5">
				<div class="wpss-wizard-done">
					<i data-lucide="check-circle-2" class="wpss-icon" aria-hidden="true"></i>
					<h2><?php esc_html_e( 'Your Marketplace is Ready!', 'wp-sell-services' ); ?></h2>
					<p><?php esc_html_e( 'You\'ve completed the initial setup. Here\'s what to do next:', 'wp-sell-services' ); ?></p>
				</div>

				<div class="wpss-wizard-cards">
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="wpss-wizard-card">
						<i data-lucide="plus" class="wpss-icon" aria-hidden="true"></i>
						<strong><?php esc_html_e( 'Create Your First Service', 'wp-sell-services' ); ?></strong>
						<span><?php esc_html_e( 'Add a service listing to your marketplace.', 'wp-sell-services' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings#payments' ) ); ?>" class="wpss-wizard-card">
						<i data-lucide="credit-card" class="wpss-icon" aria-hidden="true"></i>
						<strong><?php esc_html_e( 'Set Up Payment Methods', 'wp-sell-services' ); ?></strong>
						<span><?php esc_html_e( 'Offline payment works out of the box. Add Stripe, PayPal or your gateway keys to accept card payments.', 'wp-sell-services' ); ?></span>
					</a>
					<a href="#" class="wpss-wizard-card" id="wpss-wizard-import-demo">
						<i data-lucide="download" class="wpss-icon" aria-hidden="true"></i>
						<strong><?php esc_html_e( 'Import Demo Content', 'wp-sell-services' ); ?></strong>
						<span><?php esc_html_e( 'Get started with sample services and vendors.', 'wp-sell-services' ); ?></span>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings' ) ); ?>" class="wpss-wizard-card">
						<i data-lucide="settings" class="wpss-icon" aria-hidden="true"></i>
						<strong><?php esc_html_e( 'Go to Settings', 'wp-sell-services' ); ?></strong>
						<span><?php esc_html_e( 'Fine-tune your marketplace configuration.', 'wp-sell-services' ); ?></span>
					</a>
				</div>

				<div class="wpss-wizard-actions" style="justify-content: center;">
					<button type="button" class="button wpss-wizard-back" data-back="4"><?php esc_html_e( 'Back', 'wp-sell-services' ); ?></button>
				</div>
			</div>
		</div>

		<?php
		// Styles + scripts are enqueued (admin-wizard.css / .js), not printed
		// here — see enqueue_styles().
	}
}
