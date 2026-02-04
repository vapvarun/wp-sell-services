<?php
/**
 * Vendor Dashboard
 *
 * Handles frontend vendor dashboard functionality.
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

use WPSellServices\Services\VendorService;
use WPSellServices\Services\EarningsService;
use WPSellServices\Services\PortfolioService;

/**
 * Manages the frontend vendor dashboard.
 *
 * @since 1.0.0
 */
class VendorDashboard {

	/**
	 * Vendor service.
	 *
	 * @var VendorService
	 */
	private VendorService $vendor_service;

	/**
	 * Earnings service.
	 *
	 * @var EarningsService
	 */
	private EarningsService $earnings_service;

	/**
	 * Portfolio service.
	 *
	 * @var PortfolioService
	 */
	private PortfolioService $portfolio_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->vendor_service    = new VendorService();
		$this->earnings_service  = new EarningsService();
		$this->portfolio_service = new PortfolioService();
	}

	/**
	 * Initialize dashboard hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_shortcode( 'wpss_vendor_dashboard', array( $this, 'render_dashboard' ) );
		add_shortcode( 'wpss_become_vendor', array( $this, 'render_registration_form' ) );
		add_shortcode( 'wpss_vendor_registration', array( $this, 'render_registration_form' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_wpss_update_vendor_profile', array( $this, 'ajax_update_profile' ) );
		add_action( 'wp_ajax_wpss_request_withdrawal', array( $this, 'ajax_request_withdrawal' ) );
		add_action( 'wp_ajax_wpss_add_portfolio_item', array( $this, 'ajax_add_portfolio_item' ) );
		add_action( 'wp_ajax_wpss_delete_portfolio_item', array( $this, 'ajax_delete_portfolio_item' ) );
		add_action( 'wp_ajax_wpss_toggle_featured_portfolio', array( $this, 'ajax_toggle_featured_portfolio' ) );
		add_action( 'wp_ajax_wpss_reorder_portfolio', array( $this, 'ajax_reorder_portfolio' ) );
		add_action( 'wp_ajax_wpss_update_service_status', array( $this, 'ajax_update_service_status' ) );
		add_action( 'wp_ajax_wpss_delete_service', array( $this, 'ajax_delete_service' ) );
		add_action( 'wp_ajax_wpss_vendor_registration', array( $this, 'ajax_vendor_registration' ) );
	}

	/**
	 * Render main vendor dashboard.
	 *
	 * @deprecated 1.1.0 Use [wpss_dashboard] shortcode instead.
	 * @param array $atts Shortcode attributes.
	 * @return string Dashboard HTML.
	 */
	public function render_dashboard( array $atts = array() ): string {
		// Redirect to unified dashboard.
		$unified_dashboard = new UnifiedDashboard();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just setting default section.
		$_GET['section'] = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'services';
		return $unified_dashboard->render( array() );
	}


	/**
	 * Render vendor registration form.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string Registration form HTML.
	 */
	public function render_registration_form( array $atts = array() ): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_login_required();
		}

		$user_id = get_current_user_id();

		if ( $this->vendor_service->is_vendor( $user_id ) ) {
			return '<div class="wpss-notice wpss-notice--info">' . esc_html__( 'You are already a registered vendor.', 'wp-sell-services' ) . ' <a href="' . esc_url( $this->get_dashboard_url() ) . '">' . esc_html__( 'Go to Dashboard', 'wp-sell-services' ) . '</a></div>';
		}

		// Enqueue dashboard styles for registration form.
		wp_enqueue_style( 'wpss-vendor-dashboard', WPSS_PLUGIN_URL . 'assets/css/vendor-dashboard.css', array( 'wpss-design-system' ), WPSS_VERSION );

		ob_start();
		?>
		<div class="wpss-registration">
			<div class="wpss-registration__header">
				<h1 class="wpss-registration__title"><?php esc_html_e( 'Become a Vendor', 'wp-sell-services' ); ?></h1>
				<p class="wpss-registration__intro"><?php esc_html_e( 'Start selling your services today! Complete the form below to become a vendor.', 'wp-sell-services' ); ?></p>
			</div>

			<form id="wpss-vendor-registration-form" class="wpss-form">
				<?php wp_nonce_field( 'wpss_vendor_registration', 'wpss_registration_nonce' ); ?>

				<div class="wpss-section">
					<div class="wpss-section__body">
						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="reg_display_name"><?php esc_html_e( 'Display Name', 'wp-sell-services' ); ?> <span class="wpss-form-group__required">*</span></label>
							<input type="text" name="display_name" id="reg_display_name" class="wpss-form-group__input" value="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>" required>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="reg_tagline"><?php esc_html_e( 'Professional Tagline', 'wp-sell-services' ); ?> <span class="wpss-form-group__required">*</span></label>
							<input type="text" name="tagline" id="reg_tagline" class="wpss-form-group__input" maxlength="100" placeholder="<?php esc_attr_e( 'e.g., Professional Web Developer', 'wp-sell-services' ); ?>" required>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="reg_bio"><?php esc_html_e( 'About You', 'wp-sell-services' ); ?> <span class="wpss-form-group__required">*</span></label>
							<textarea name="bio" id="reg_bio" class="wpss-form-group__textarea" rows="5" placeholder="<?php esc_attr_e( 'Tell us about your experience and expertise...', 'wp-sell-services' ); ?>" required></textarea>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__label" for="reg_skills"><?php esc_html_e( 'Skills', 'wp-sell-services' ); ?> <span class="wpss-form-group__required">*</span></label>
							<input type="text" name="skills" id="reg_skills" class="wpss-form-group__input" placeholder="<?php esc_attr_e( 'Enter skills separated by commas', 'wp-sell-services' ); ?>" required>
						</div>

						<div class="wpss-form-group">
							<label class="wpss-form-group__checkbox">
								<input type="checkbox" name="terms_agreed" id="reg_terms" value="1" required>
								<span>
									<?php
									printf(
										/* translators: %s: terms and conditions link */
										esc_html__( 'I agree to the %s', 'wp-sell-services' ),
										'<a href="' . esc_url( get_permalink( get_option( 'wpss_terms_page' ) ) ) . '" target="_blank">' . esc_html__( 'Terms and Conditions', 'wp-sell-services' ) . '</a>'
									);
									?>
								</span>
							</label>
						</div>

						<div class="wpss-form-group" style="margin-top: 1.5rem;">
							<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--lg"><?php esc_html_e( 'Submit Application', 'wp-sell-services' ); ?></button>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render login required message.
	 *
	 * @return string Login message HTML.
	 */
	private function render_login_required(): string {
		return '<div class="wpss-notice wpss-notice--warning">' .
			esc_html__( 'Please log in to access this page.', 'wp-sell-services' ) .
			' <a href="' . esc_url( wp_login_url( get_permalink() ) ) . '" class="wpss-notice__link">' . esc_html__( 'Log In', 'wp-sell-services' ) . '</a></div>';
	}

	/**
	 * AJAX: Update vendor profile.
	 *
	 * @return void
	 */
	public function ajax_update_profile(): void {
		check_ajax_referer( 'wpss_update_profile', 'wpss_profile_nonce' );

		$user_id = get_current_user_id();

		if ( ! $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$data = array(
			'display_name'       => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
			'tagline'            => sanitize_text_field( wp_unslash( $_POST['tagline'] ?? '' ) ),
			'bio'                => wp_kses_post( wp_unslash( $_POST['bio'] ?? '' ) ),
			'skills'             => array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['skills'] ?? '' ) ) ) ),
			'languages'          => array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['languages'] ?? '' ) ) ) ),
			'experience_level'   => sanitize_text_field( wp_unslash( $_POST['experience_level'] ?? '' ) ),
			'website'            => esc_url_raw( wp_unslash( $_POST['website'] ?? '' ) ),
			'location'           => sanitize_text_field( wp_unslash( $_POST['location'] ?? '' ) ),
			'timezone'           => sanitize_text_field( wp_unslash( $_POST['timezone'] ?? '' ) ),
			'available_for_work' => ! empty( $_POST['available_for_work'] ),
			'response_time'      => absint( $_POST['response_time'] ?? 24 ),
			'avatar_id'          => absint( $_POST['avatar_id'] ?? 0 ),
		);

		$result = $this->vendor_service->update_profile( $user_id, $data );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Profile updated successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update profile.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * AJAX: Request withdrawal.
	 *
	 * @return void
	 */
	public function ajax_request_withdrawal(): void {
		check_ajax_referer( 'wpss_request_withdrawal', 'wpss_withdrawal_nonce' );

		$user_id = get_current_user_id();

		if ( ! $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$amount  = floatval( $_POST['amount'] ?? 0 );
		$method  = sanitize_text_field( wp_unslash( $_POST['method'] ?? '' ) );
		$details = sanitize_textarea_field( wp_unslash( $_POST['details'] ?? '' ) );

		$result = $this->earnings_service->request_withdrawal( $user_id, $amount, $method, array( 'payment_details' => $details ) );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Add portfolio item.
	 *
	 * @return void
	 */
	public function ajax_add_portfolio_item(): void {
		check_ajax_referer( 'wpss_portfolio_nonce', 'portfolio_nonce' );

		$user_id = get_current_user_id();

		if ( ! $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$data = array(
			'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'description'  => wp_kses_post( wp_unslash( $_POST['description'] ?? '' ) ),
			'media'        => array_map( 'absint', json_decode( stripslashes( $_POST['media'] ?? '[]' ), true ) ?: array() ),
			'external_url' => esc_url_raw( wp_unslash( $_POST['external_url'] ?? '' ) ),
			'tags'         => array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['tags'] ?? '' ) ) ) ),
			'service_id'   => absint( $_POST['service_id'] ?? 0 ),
			'is_featured'  => ! empty( $_POST['is_featured'] ),
		);

		$item_id = absint( $_POST['item_id'] ?? 0 );

		if ( $item_id ) {
			$result = $this->portfolio_service->update( $item_id, $data );
		} else {
			$result = $this->portfolio_service->create( $user_id, $data );
		}

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Delete portfolio item.
	 *
	 * @return void
	 */
	public function ajax_delete_portfolio_item(): void {
		check_ajax_referer( 'wpss_portfolio_nonce', 'portfolio_nonce' );

		$user_id = get_current_user_id();
		$item_id = absint( $_POST['item_id'] ?? 0 );

		$result = $this->portfolio_service->delete( $item_id, $user_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Toggle featured portfolio item.
	 *
	 * @return void
	 */
	public function ajax_toggle_featured_portfolio(): void {
		check_ajax_referer( 'wpss_portfolio_nonce', 'portfolio_nonce' );

		$user_id = get_current_user_id();
		$item_id = absint( $_POST['item_id'] ?? 0 );

		$result = $this->portfolio_service->toggle_featured( $item_id, $user_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: Reorder portfolio items.
	 *
	 * @return void
	 */
	public function ajax_reorder_portfolio(): void {
		check_ajax_referer( 'wpss_portfolio_nonce', 'portfolio_nonce' );

		$user_id = get_current_user_id();

		if ( ! $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not authorized to perform this action.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$order = array_map( 'absint', $_POST['order'] ?? array() );

		if ( $this->portfolio_service->reorder( $user_id, $order ) ) {
			wp_send_json_success( array( 'message' => __( 'Portfolio reordered successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to reorder portfolio.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * AJAX: Update service status.
	 *
	 * @return void
	 */
	public function ajax_update_service_status(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$user_id    = get_current_user_id();
		$service_id = absint( $_POST['service_id'] ?? 0 );
		$status     = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );

		$service = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type || (int) $service->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Service not found.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$new_status = 'publish' === $status ? 'draft' : 'publish';

		$result = wp_update_post(
			array(
				'ID'          => $service_id,
				'post_status' => $new_status,
			)
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			return; // Explicit return for defensive coding.
		}

		wp_send_json_success(
			array(
				'message'    => __( 'Service status updated.', 'wp-sell-services' ),
				'new_status' => $new_status,
			)
		);
	}

	/**
	 * AJAX: Delete a service.
	 *
	 * @return void
	 */
	public function ajax_delete_service(): void {
		check_ajax_referer( 'wpss_dashboard_nonce', 'nonce' );

		$user_id    = get_current_user_id();
		$service_id = absint( $_POST['service_id'] ?? 0 );

		$service = get_post( $service_id );

		if ( ! $service || 'wpss_service' !== $service->post_type || (int) $service->post_author !== $user_id ) {
			wp_send_json_error( array( 'message' => __( 'Service not found or you do not have permission to delete it.', 'wp-sell-services' ) ) );
			return;
		}

		// Check if service has any active orders.
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$active_orders = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$orders_table} WHERE service_id = %d AND status IN ('pending_payment', 'pending_requirements', 'in_progress', 'revision_requested')",
				$service_id
			)
		);

		if ( (int) $active_orders > 0 ) {
			wp_send_json_error( array( 'message' => __( 'Cannot delete service with active orders.', 'wp-sell-services' ) ) );
			return;
		}

		$result = wp_trash_post( $service_id );

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete service.', 'wp-sell-services' ) ) );
			return;
		}

		wp_send_json_success( array( 'message' => __( 'Service deleted successfully.', 'wp-sell-services' ) ) );
	}

	/**
	 * AJAX: Vendor registration.
	 *
	 * @return void
	 */
	public function ajax_vendor_registration(): void {
		check_ajax_referer( 'wpss_vendor_registration', 'wpss_registration_nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'You must be logged in to register as a vendor.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$user_id = get_current_user_id();

		if ( $this->vendor_service->is_vendor( $user_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You are already registered as a vendor.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$data = array(
			'display_name' => sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) ),
			'tagline'      => sanitize_text_field( wp_unslash( $_POST['tagline'] ?? '' ) ),
			'bio'          => wp_kses_post( wp_unslash( $_POST['bio'] ?? '' ) ),
			'skills'       => array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['skills'] ?? '' ) ) ) ),
			'terms_agreed' => ! empty( $_POST['terms_agreed'] ),
		);

		if ( ! $data['terms_agreed'] ) {
			wp_send_json_error( array( 'message' => __( 'You must agree to the terms and conditions.', 'wp-sell-services' ) ) );
			return; // Explicit return for defensive coding.
		}

		$result = $this->vendor_service->register_vendor( $user_id, $data );

		if ( $result['success'] ) {
			wp_send_json_success(
				array_merge(
					$result,
					array(
						'redirect' => $this->get_dashboard_url(),
					)
				)
			);
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Get dashboard URL.
	 *
	 * @return string Dashboard URL.
	 */
	private function get_dashboard_url(): string {
		$url = wpss_get_page_url( 'dashboard' );

		if ( $url ) {
			return $url;
		}

		// Fallback to WooCommerce My Account page.
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$wc_url = wc_get_page_permalink( 'myaccount' );
			if ( $wc_url ) {
				return $wc_url;
			}
		}

		return home_url();
	}

	/**
	 * Get registration URL.
	 *
	 * @return string Registration URL.
	 */
	private function get_registration_url(): string {
		$url = wpss_get_page_url( 'become_vendor' );
		return $url ? $url : home_url();
	}

}
