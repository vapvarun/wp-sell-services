<?php
/**
 * Settings Class
 *
 * Handles plugin settings registration and rendering.
 *
 * @package WPSellServices\Admin
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Settings class.
 *
 * @since 1.0.0
 */
class Settings {

	/**
	 * Settings tabs.
	 *
	 * @var array<string, string>
	 */
	private array $tabs = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->tabs = array(
			'general'       => __( 'General', 'wp-sell-services' ),
			'vendor'        => __( 'Vendor', 'wp-sell-services' ),
			'orders'        => __( 'Orders', 'wp-sell-services' ),
			'notifications' => __( 'Notifications', 'wp-sell-services' ),
			'pages'         => __( 'Pages', 'wp-sell-services' ),
			'advanced'      => __( 'Advanced', 'wp-sell-services' ),
		);
	}

	/**
	 * Initialize settings.
	 *
	 * @return void
	 */
	public function init(): void {
		/**
		 * Filter the settings tabs.
		 *
		 * @since 1.0.0
		 *
		 * @param array $tabs Settings tabs (slug => label).
		 */
		$this->tabs = apply_filters( 'wpss_settings_tabs', $this->tabs );

		$this->register_settings();
		add_action( 'wp_ajax_wpss_create_page', array( $this, 'ajax_create_page' ) );
	}

	/**
	 * AJAX handler to create a page.
	 *
	 * @return void
	 */
	public function ajax_create_page(): void {
		check_ajax_referer( 'wpss_settings_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$field = sanitize_key( $_POST['field'] ?? '' );
		$title = sanitize_text_field( $_POST['title'] ?? '' );

		if ( ! $field || ! $title ) {
			wp_send_json_error( array( 'message' => __( 'Missing required data.', 'wp-sell-services' ) ) );
		}

		// Check if a page with this shortcode already exists.
		$page_content     = $this->get_page_content( $field );
		$existing_page_id = $this->find_existing_page( $field, $page_content );

		if ( $existing_page_id ) {
			// Page already exists - update option and return existing page.
			$options           = get_option( 'wpss_pages', array() );
			$options[ $field ] = $existing_page_id;
			update_option( 'wpss_pages', $options );

			wp_send_json_success(
				array(
					'page_id'  => $existing_page_id,
					'title'    => get_the_title( $existing_page_id ),
					'view_url' => get_permalink( $existing_page_id ),
					'edit_url' => get_edit_post_link( $existing_page_id, 'raw' ),
					'existing' => true,
					'message'  => __( 'Existing page found and linked.', 'wp-sell-services' ),
				)
			);
		}

		// Create the page.
		$page_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_content' => $page_content,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);

		if ( is_wp_error( $page_id ) ) {
			wp_send_json_error( array( 'message' => $page_id->get_error_message() ) );
		}

		// Update the option.
		$options           = get_option( 'wpss_pages', array() );
		$options[ $field ] = $page_id;
		update_option( 'wpss_pages', $options );

		wp_send_json_success(
			array(
				'page_id'  => $page_id,
				'title'    => $title,
				'view_url' => get_permalink( $page_id ),
				'edit_url' => get_edit_post_link( $page_id, 'raw' ),
			)
		);
	}

	/**
	 * Find an existing page with the WPSS shortcode.
	 *
	 * @param string $field        Page field key.
	 * @param string $page_content Expected shortcode content.
	 * @return int|null Page ID if found, null otherwise.
	 */
	private function find_existing_page( string $field, string $page_content ): ?int {
		// First check if we already have a valid page ID stored.
		$options = get_option( 'wpss_pages', array() );
		if ( ! empty( $options[ $field ] ) ) {
			$stored_page = get_post( $options[ $field ] );
			if ( $stored_page && 'page' === $stored_page->post_type && 'trash' !== $stored_page->post_status ) {
				return (int) $stored_page->ID;
			}
		}

		// If no shortcode, skip search.
		if ( empty( $page_content ) ) {
			return null;
		}

		// Search for pages containing this shortcode.
		$pages = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private' ),
				'posts_per_page' => 1,
				's'              => $page_content,
			)
		);

		if ( ! empty( $pages ) ) {
			return (int) $pages[0]->ID;
		}

		return null;
	}

	/**
	 * Get default page content for a page type.
	 *
	 * @param string $field Page field key.
	 * @return string Page content.
	 */
	private function get_page_content( string $field ): string {
		$shortcodes = array(
			'services_page' => '[wpss_services]',
			'dashboard'     => '[wpss_dashboard]',
		);

		return $shortcodes[ $field ] ?? '';
	}

	/**
	 * Register all settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		// General settings.
		register_setting(
			'wpss_general',
			'wpss_general',
			array( $this, 'sanitize_general_settings' )
		);

		add_settings_section(
			'wpss_general_section',
			__( 'General Settings', 'wp-sell-services' ),
			array( $this, 'render_general_section' ),
			'wpss_general'
		);

		add_settings_field(
			'platform_name',
			__( 'Platform Name', 'wp-sell-services' ),
			array( $this, 'render_text_field' ),
			'wpss_general',
			'wpss_general_section',
			array(
				'option_name' => 'wpss_general',
				'field'       => 'platform_name',
				'description' => __( 'Name displayed to users.', 'wp-sell-services' ),
				'default'     => get_bloginfo( 'name' ),
			)
		);

		add_settings_field(
			'currency',
			__( 'Currency', 'wp-sell-services' ),
			array( $this, 'render_select_field' ),
			'wpss_general',
			'wpss_general_section',
			array(
				'option_name' => 'wpss_general',
				'field'       => 'currency',
				'options'     => $this->get_currencies(),
				'default'     => 'USD',
			)
		);

		add_settings_field(
			'platform_fee_percentage',
			__( 'Platform Fee (%)', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_general',
			'wpss_general_section',
			array(
				'option_name' => 'wpss_general',
				'field'       => 'platform_fee_percentage',
				'min'         => 0,
				'max'         => 50,
				'step'        => 0.1,
				'default'     => 10,
				'description' => __( 'Percentage deducted from vendor earnings.', 'wp-sell-services' ),
			)
		);

		// Vendor settings.
		register_setting(
			'wpss_vendor',
			'wpss_vendor',
			array( $this, 'sanitize_vendor_settings' )
		);

		add_settings_section(
			'wpss_vendor_section',
			__( 'Vendor Settings', 'wp-sell-services' ),
			array( $this, 'render_vendor_section' ),
			'wpss_vendor'
		);

		add_settings_field(
			'vendor_registration',
			__( 'Vendor Registration', 'wp-sell-services' ),
			array( $this, 'render_select_field' ),
			'wpss_vendor',
			'wpss_vendor_section',
			array(
				'option_name' => 'wpss_vendor',
				'field'       => 'vendor_registration',
				'options'     => array(
					'open'     => __( 'Open (anyone can register)', 'wp-sell-services' ),
					'approval' => __( 'Requires Approval', 'wp-sell-services' ),
					'closed'   => __( 'Closed (admin only)', 'wp-sell-services' ),
				),
				'default'     => 'open',
			)
		);

		add_settings_field(
			'max_services_per_vendor',
			__( 'Max Services per Vendor', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_vendor',
			'wpss_vendor_section',
			array(
				'option_name' => 'wpss_vendor',
				'field'       => 'max_services_per_vendor',
				'min'         => 1,
				'max'         => 100,
				'default'     => 20,
				'description' => __( '0 for unlimited.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'require_verification',
			__( 'Require Verification', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_vendor',
			'wpss_vendor_section',
			array(
				'option_name' => 'wpss_vendor',
				'field'       => 'require_verification',
				'label'       => __( 'Require vendors to verify identity before selling', 'wp-sell-services' ),
				'default'     => false,
			)
		);

		add_settings_field(
			'min_payout_amount',
			__( 'Minimum Payout Amount', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_vendor',
			'wpss_vendor_section',
			array(
				'option_name' => 'wpss_vendor',
				'field'       => 'min_payout_amount',
				'min'         => 0,
				'max'         => 1000,
				'step'        => 1,
				'default'     => 50,
			)
		);

		add_settings_field(
			'require_service_moderation',
			__( 'Service Moderation', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_vendor',
			'wpss_vendor_section',
			array(
				'option_name' => 'wpss_vendor',
				'field'       => 'require_service_moderation',
				'label'       => __( 'Require admin approval before services are published', 'wp-sell-services' ),
				'default'     => false,
			)
		);

		// Order settings.
		register_setting(
			'wpss_orders',
			'wpss_orders',
			array( $this, 'sanitize_order_settings' )
		);

		add_settings_section(
			'wpss_orders_section',
			__( 'Order Settings', 'wp-sell-services' ),
			array( $this, 'render_orders_section' ),
			'wpss_orders'
		);

		add_settings_field(
			'auto_complete_days',
			__( 'Auto-Complete Days', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'auto_complete_days',
				'min'         => 0,
				'max'         => 30,
				'default'     => 3,
				'description' => __( 'Days after delivery to auto-complete if buyer does not respond. 0 to disable.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'revision_limit',
			__( 'Default Revision Limit', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'revision_limit',
				'min'         => 0,
				'max'         => 10,
				'default'     => 2,
				'description' => __( 'Default revisions per order. Can be overridden per service.', 'wp-sell-services' ),
			)
		);

		add_settings_field(
			'allow_disputes',
			__( 'Allow Disputes', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'allow_disputes',
				'label'       => __( 'Allow buyers to open disputes on orders', 'wp-sell-services' ),
				'default'     => true,
			)
		);

		add_settings_field(
			'dispute_window_days',
			__( 'Dispute Window (Days)', 'wp-sell-services' ),
			array( $this, 'render_number_field' ),
			'wpss_orders',
			'wpss_orders_section',
			array(
				'option_name' => 'wpss_orders',
				'field'       => 'dispute_window_days',
				'min'         => 1,
				'max'         => 90,
				'default'     => 14,
				'description' => __( 'Days after completion within which disputes can be opened.', 'wp-sell-services' ),
			)
		);

		// Notification settings.
		register_setting(
			'wpss_notifications',
			'wpss_notifications',
			array( $this, 'sanitize_notification_settings' )
		);

		add_settings_section(
			'wpss_notifications_section',
			__( 'Email Notifications', 'wp-sell-services' ),
			array( $this, 'render_notifications_section' ),
			'wpss_notifications'
		);

		$notification_types = array(
			'new_order'          => __( 'New Order', 'wp-sell-services' ),
			'order_completed'    => __( 'Order Completed', 'wp-sell-services' ),
			'order_cancelled'    => __( 'Order Cancelled', 'wp-sell-services' ),
			'delivery_submitted' => __( 'Delivery Submitted', 'wp-sell-services' ),
			'revision_requested' => __( 'Revision Requested', 'wp-sell-services' ),
			'new_message'        => __( 'New Message', 'wp-sell-services' ),
			'new_review'         => __( 'New Review', 'wp-sell-services' ),
			'dispute_opened'     => __( 'Dispute Opened', 'wp-sell-services' ),
		);

		foreach ( $notification_types as $key => $label ) {
			add_settings_field(
				'notify_' . $key,
				$label,
				array( $this, 'render_checkbox_field' ),
				'wpss_notifications',
				'wpss_notifications_section',
				array(
					'option_name' => 'wpss_notifications',
					'field'       => 'notify_' . $key,
					'label'       => sprintf(
						/* translators: %s: notification type */
						__( 'Send email for %s', 'wp-sell-services' ),
						strtolower( $label )
					),
					'default'     => true,
				)
			);
		}

		// Pages settings.
		register_setting(
			'wpss_pages',
			'wpss_pages',
			array( $this, 'sanitize_pages_settings' )
		);

		add_settings_section(
			'wpss_pages_section',
			__( 'Page Settings', 'wp-sell-services' ),
			array( $this, 'render_pages_section' ),
			'wpss_pages'
		);

		$pages = array(
			'services_page' => __( 'Services Page', 'wp-sell-services' ),
			'dashboard'     => __( 'Dashboard', 'wp-sell-services' ),
		);

		foreach ( $pages as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_page_select_field' ),
				'wpss_pages',
				'wpss_pages_section',
				array(
					'option_name' => 'wpss_pages',
					'field'       => $key,
				)
			);
		}

		// Advanced settings.
		register_setting(
			'wpss_advanced',
			'wpss_advanced',
			array( $this, 'sanitize_advanced_settings' )
		);

		add_settings_section(
			'wpss_advanced_section',
			__( 'Advanced Settings', 'wp-sell-services' ),
			array( $this, 'render_advanced_section' ),
			'wpss_advanced'
		);

		add_settings_field(
			'delete_data_on_uninstall',
			__( 'Delete Data on Uninstall', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_advanced',
			'wpss_advanced_section',
			array(
				'option_name' => 'wpss_advanced',
				'field'       => 'delete_data_on_uninstall',
				'label'       => __( 'Delete all plugin data when uninstalling', 'wp-sell-services' ),
				'default'     => false,
			)
		);

		add_settings_field(
			'enable_debug_mode',
			__( 'Debug Mode', 'wp-sell-services' ),
			array( $this, 'render_checkbox_field' ),
			'wpss_advanced',
			'wpss_advanced_section',
			array(
				'option_name' => 'wpss_advanced',
				'field'       => 'enable_debug_mode',
				'label'       => __( 'Enable debug logging', 'wp-sell-services' ),
				'default'     => false,
			)
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! array_key_exists( $active_tab, $this->tabs ) ) {
			$active_tab = 'general';
		}

		?>
		<div class="wrap wpss-settings">
			<h1><?php echo esc_html__( 'WP Sell Services Settings', 'wp-sell-services' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $this->tabs as $tab_key => $tab_label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-settings&tab=' . $tab_key ) ); ?>"
						class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php
				switch ( $active_tab ) {
					case 'vendor':
						settings_fields( 'wpss_vendor' );
						do_settings_sections( 'wpss_vendor' );
						break;

					case 'orders':
						settings_fields( 'wpss_orders' );
						do_settings_sections( 'wpss_orders' );
						break;

					case 'notifications':
						settings_fields( 'wpss_notifications' );
						do_settings_sections( 'wpss_notifications' );
						break;

					case 'pages':
						settings_fields( 'wpss_pages' );
						do_settings_sections( 'wpss_pages' );
						break;

					case 'advanced':
						settings_fields( 'wpss_advanced' );
						do_settings_sections( 'wpss_advanced' );
						break;

					default:
						settings_fields( 'wpss_general' );
						do_settings_sections( 'wpss_general' );
						break;
				}

				submit_button();
				?>
			</form>

			<?php if ( 'pages' === $active_tab ) : ?>
			<script>
			jQuery(function($) {
				var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
				var nonce = '<?php echo esc_js( wp_create_nonce( 'wpss_settings_nonce' ) ); ?>';

				$('.wpss-create-page').on('click', function() {
					var $btn = $(this);
					var field = $btn.data('field');
					var title = $btn.data('title');
					var $wrap = $btn.closest('.wpss-page-select-wrap');
					var $select = $wrap.find('select');
					var $viewBtn = $wrap.find('.wpss-view-page');

					if (!title) {
						alert('<?php echo esc_js( __( 'Page title not defined.', 'wp-sell-services' ) ); ?>');
						return;
					}

					if (!confirm('<?php echo esc_js( __( 'Create a new page titled', 'wp-sell-services' ) ); ?> "' + title + '"?')) {
						return;
					}

					$btn.addClass('creating').text('<?php echo esc_js( __( 'Creating...', 'wp-sell-services' ) ); ?>');

					$.ajax({
						url: ajaxUrl,
						type: 'POST',
						data: {
							action: 'wpss_create_page',
							nonce: nonce,
							field: field,
							title: title
						},
						success: function(response) {
							if (response.success) {
								// Add new option and select it
								$select.append('<option value="' + response.data.page_id + '">' + response.data.title + '</option>');
								$select.val(response.data.page_id);

								// Show and update view link
								$viewBtn.attr('href', response.data.view_url).show();

								// Change button to success state
								$btn.removeClass('creating').text('<?php echo esc_js( __( 'Page Created!', 'wp-sell-services' ) ); ?>').addClass('button-primary');

								setTimeout(function() {
									$btn.removeClass('button-primary').text('<?php echo esc_js( __( 'Create Page', 'wp-sell-services' ) ); ?>');
								}, 2000);
							} else {
								alert(response.data.message || '<?php echo esc_js( __( 'Failed to create page.', 'wp-sell-services' ) ); ?>');
								$btn.removeClass('creating').text('<?php echo esc_js( __( 'Create Page', 'wp-sell-services' ) ); ?>');
							}
						},
						error: function() {
							alert('<?php echo esc_js( __( 'An error occurred. Please try again.', 'wp-sell-services' ) ); ?>');
							$btn.removeClass('creating').text('<?php echo esc_js( __( 'Create Page', 'wp-sell-services' ) ); ?>');
						}
					});
				});

				// Update view link when dropdown changes
				$('.wpss-page-dropdown').on('change', function() {
					var $select = $(this);
					var pageId = $select.val();
					var $viewBtn = $select.closest('.wpss-page-select-wrap').find('.wpss-view-page');

					if (pageId) {
						// Construct view URL - we'll just reload to get proper URL
						$viewBtn.attr('href', '<?php echo esc_url( home_url( '/' ) ); ?>?page_id=' + pageId).show();
					} else {
						$viewBtn.hide();
					}
				});
			});
			</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render general section description.
	 *
	 * @return void
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure general platform settings.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render vendor section description.
	 *
	 * @return void
	 */
	public function render_vendor_section(): void {
		echo '<p>' . esc_html__( 'Configure vendor registration and capabilities.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render orders section description.
	 *
	 * @return void
	 */
	public function render_orders_section(): void {
		echo '<p>' . esc_html__( 'Configure order workflow and policies.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render notifications section description.
	 *
	 * @return void
	 */
	public function render_notifications_section(): void {
		echo '<p>' . esc_html__( 'Configure which email notifications are sent.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render pages section description.
	 *
	 * @return void
	 */
	public function render_pages_section(): void {
		echo '<p>' . esc_html__( 'Assign pages for plugin functionality.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render advanced section description.
	 *
	 * @return void
	 */
	public function render_advanced_section(): void {
		echo '<p>' . esc_html__( 'Advanced configuration options.', 'wp-sell-services' ) . '</p>';
	}

	/**
	 * Render text field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_text_field( array $args ): void {
		$options = get_option( $args['option_name'], array() );
		$value   = $options[ $args['field'] ] ?? ( $args['default'] ?? '' );

		printf(
			'<input type="text" id="%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text">',
			esc_attr( $args['field'] ),
			esc_attr( $args['option_name'] ),
			esc_attr( $value )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render number field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_number_field( array $args ): void {
		$options = get_option( $args['option_name'], array() );
		$value   = $options[ $args['field'] ] ?? ( $args['default'] ?? 0 );

		printf(
			'<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" min="%4$s" max="%5$s" step="%6$s" class="small-text">',
			esc_attr( $args['field'] ),
			esc_attr( $args['option_name'] ),
			esc_attr( (string) $value ),
			esc_attr( (string) ( $args['min'] ?? 0 ) ),
			esc_attr( (string) ( $args['max'] ?? 100 ) ),
			esc_attr( (string) ( $args['step'] ?? 1 ) )
		);

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render select field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_select_field( array $args ): void {
		$options = get_option( $args['option_name'], array() );
		$value   = $options[ $args['field'] ] ?? ( $args['default'] ?? '' );

		printf(
			'<select id="%1$s" name="%2$s[%1$s]">',
			esc_attr( $args['field'] ),
			esc_attr( $args['option_name'] )
		);

		foreach ( $args['options'] as $option_value => $option_label ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $option_label )
			);
		}

		echo '</select>';

		if ( ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Render checkbox field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_checkbox_field( array $args ): void {
		$options = get_option( $args['option_name'], array() );
		$value   = $options[ $args['field'] ] ?? ( $args['default'] ?? false );

		printf(
			'<label><input type="checkbox" id="%1$s" name="%2$s[%1$s]" value="1" %3$s> %4$s</label>',
			esc_attr( $args['field'] ),
			esc_attr( $args['option_name'] ),
			checked( $value, true, false ),
			esc_html( $args['label'] ?? '' )
		);
	}

	/**
	 * Render page select field.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 * @return void
	 */
	public function render_page_select_field( array $args ): void {
		$options     = get_option( $args['option_name'], array() );
		$value       = $options[ $args['field'] ] ?? '';
		$page_titles = array(
			'services_page' => __( 'Services', 'wp-sell-services' ),
			'dashboard'     => __( 'Dashboard', 'wp-sell-services' ),
		);

		echo '<div class="wpss-page-select-wrap">';

		wp_dropdown_pages(
			array(
				'name'              => $args['option_name'] . '[' . $args['field'] . ']',
				'id'                => $args['field'],
				'show_option_none'  => __( '— Select —', 'wp-sell-services' ),
				'option_none_value' => '',
				'selected'          => $value,
				'class'             => 'wpss-page-dropdown',
			)
		);

		// Create page button.
		printf(
			'<button type="button" class="button wpss-create-page" data-field="%s" data-title="%s">%s</button>',
			esc_attr( $args['field'] ),
			esc_attr( $page_titles[ $args['field'] ] ?? '' ),
			esc_html__( 'Create Page', 'wp-sell-services' )
		);

		// View page link (only show if page is selected).
		if ( $value ) {
			printf(
				'<a href="%s" class="button wpss-view-page" target="_blank">%s</a>',
				esc_url( get_permalink( $value ) ),
				esc_html__( 'View', 'wp-sell-services' )
			);
		} else {
			printf(
				'<a href="#" class="button wpss-view-page" target="_blank" style="display:none;">%s</a>',
				esc_html__( 'View', 'wp-sell-services' )
			);
		}

		echo '</div>';
	}

	/**
	 * Sanitize general settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_general_settings( array $input ): array {
		$sanitized = array();

		$sanitized['platform_name']           = sanitize_text_field( $input['platform_name'] ?? '' );
		$sanitized['currency']                = sanitize_text_field( $input['currency'] ?? 'USD' );
		$sanitized['platform_fee_percentage'] = min( 50, max( 0, (float) ( $input['platform_fee_percentage'] ?? 10 ) ) );

		return $sanitized;
	}

	/**
	 * Sanitize vendor settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_vendor_settings( array $input ): array {
		$sanitized = array();

		$sanitized['vendor_registration']        = sanitize_key( $input['vendor_registration'] ?? 'open' );
		$sanitized['max_services_per_vendor']    = absint( $input['max_services_per_vendor'] ?? 20 );
		$sanitized['require_verification']       = ! empty( $input['require_verification'] );
		$sanitized['min_payout_amount']          = absint( $input['min_payout_amount'] ?? 50 );
		$sanitized['require_service_moderation'] = ! empty( $input['require_service_moderation'] );

		return $sanitized;
	}

	/**
	 * Sanitize order settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_order_settings( array $input ): array {
		$sanitized = array();

		$sanitized['auto_complete_days']  = absint( $input['auto_complete_days'] ?? 3 );
		$sanitized['revision_limit']      = absint( $input['revision_limit'] ?? 2 );
		$sanitized['allow_disputes']      = ! empty( $input['allow_disputes'] );
		$sanitized['dispute_window_days'] = absint( $input['dispute_window_days'] ?? 14 );

		return $sanitized;
	}

	/**
	 * Sanitize notification settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_notification_settings( array $input ): array {
		$sanitized = array();

		$notification_keys = array(
			'notify_new_order',
			'notify_order_completed',
			'notify_order_cancelled',
			'notify_delivery_submitted',
			'notify_revision_requested',
			'notify_new_message',
			'notify_new_review',
			'notify_dispute_opened',
		);

		foreach ( $notification_keys as $key ) {
			$sanitized[ $key ] = ! empty( $input[ $key ] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize pages settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_pages_settings( array $input ): array {
		$sanitized = array();

		$page_keys = array(
			'services_page',
			'dashboard',
		);

		foreach ( $page_keys as $key ) {
			$sanitized[ $key ] = absint( $input[ $key ] ?? 0 );
		}

		return $sanitized;
	}

	/**
	 * Sanitize advanced settings.
	 *
	 * @param array<string, mixed> $input Raw input.
	 * @return array<string, mixed> Sanitized input.
	 */
	public function sanitize_advanced_settings( array $input ): array {
		$sanitized = array();

		$sanitized['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );
		$sanitized['enable_debug_mode']        = ! empty( $input['enable_debug_mode'] );

		return $sanitized;
	}

	/**
	 * Get available currencies.
	 *
	 * @return array<string, string> Currency codes and labels.
	 */
	private function get_currencies(): array {
		return array(
			'USD' => __( 'US Dollar ($)', 'wp-sell-services' ),
			'EUR' => __( 'Euro (€)', 'wp-sell-services' ),
			'GBP' => __( 'British Pound (£)', 'wp-sell-services' ),
			'CAD' => __( 'Canadian Dollar (C$)', 'wp-sell-services' ),
			'AUD' => __( 'Australian Dollar (A$)', 'wp-sell-services' ),
			'INR' => __( 'Indian Rupee (₹)', 'wp-sell-services' ),
			'JPY' => __( 'Japanese Yen (¥)', 'wp-sell-services' ),
			'CNY' => __( 'Chinese Yuan (¥)', 'wp-sell-services' ),
			'BRL' => __( 'Brazilian Real (R$)', 'wp-sell-services' ),
			'MXN' => __( 'Mexican Peso ($)', 'wp-sell-services' ),
		);
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $group Setting group.
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed Setting value.
	 */
	public static function get( string $group, string $key, mixed $default = null ): mixed {
		$options = get_option( 'wpss_' . $group, array() );
		return $options[ $key ] ?? $default;
	}
}
