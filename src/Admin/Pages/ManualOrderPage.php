<?php
/**
 * Manual Order Creation Page
 *
 * Allows admins to create orders on behalf of customers (phone orders,
 * offline sales, migration from other systems, etc.).
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Admin\Pages;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Services\CommissionService;
use WPSellServices\Services\ConversationService;
use WPSellServices\Services\ServiceAddonService;

/**
 * Manual Order Page Class.
 *
 * @since 1.0.0
 */
class ManualOrderPage {

	/**
	 * Initialize the page.
	 *
	 * @return void
	 */
	public function init(): void {
		// Priority 20 to ensure parent menu is registered first (default is 10).
		add_action( 'admin_menu', array( $this, 'add_menu_page' ), 20 );
		add_action( 'wp_ajax_wpss_create_manual_order', array( $this, 'handle_create_order' ) );
		add_action( 'wp_ajax_wpss_get_service_addons', array( $this, 'ajax_get_service_addons' ) );
		// Priority 20 ensures this runs after Admin::enqueue_scripts registers wpss-admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		// Hidden page — accessible via "Create Order" button on the Orders list page.
		add_submenu_page(
			'wpss-orders',
			__( 'Create Order', 'wp-sell-services' ),
			__( 'Create Order', 'wp-sell-services' ),
			'manage_options',
			'wpss-create-order',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue page scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'admin_page_wpss-create-order' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wpss-admin' );
		wp_enqueue_script( 'wpss-admin' );

		wp_enqueue_style(
			'wpss-admin-manual-order',
			\WPSS_PLUGIN_URL . 'assets/css/admin-manual-order.css',
			array( 'wpss-admin' ),
			\WPSS_VERSION
		);

		wp_enqueue_script(
			'wpss-admin-manual-order',
			\WPSS_PLUGIN_URL . 'assets/js/admin-manual-order.js',
			array( 'jquery', 'wpss-admin' ),
			\WPSS_VERSION,
			true
		);

		$default_rate = CommissionService::get_global_commission_rate();

		wp_localize_script(
			'wpss-admin-manual-order',
			'wpssManualOrder',
			array(
				'ajaxUrl'               => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'wpss_create_manual_order' ),
				'defaultCommissionRate' => $default_rate,
				'currencyFormat'        => wpss_get_currency_format(),
				'i18n'                  => array(
					'selectPackage'       => __( '-- Select Package --', 'wp-sell-services' ),
					'loadingPackages'     => __( 'Loading packages...', 'wp-sell-services' ),
					'loadingAddons'       => __( 'Loading addons...', 'wp-sell-services' ),
					'noAddons'            => __( 'No addons available for this service.', 'wp-sell-services' ),
					/* translators: 1: order number, 2: order ID */
					'orderCreated'        => __( 'Order #%1$s has been created. Order ID: %2$d', 'wp-sell-services' ),
					'requirementsSkipped' => __( 'Note: This service has no requirements defined. Order was set to "In Progress" automatically.', 'wp-sell-services' ),
					'createFailed'        => __( 'Failed to create order.', 'wp-sell-services' ),
					'createError'         => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
				),
			)
		);
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		// Get all published services.
		$services = get_posts(
			array(
				'post_type'      => 'wpss_service',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		// Get users.
		$users = get_users(
			array(
				'orderby' => 'display_name',
				'order'   => 'ASC',
			)
		);

		$default_commission = CommissionService::get_global_commission_rate();
		$default_currency   = wpss_get_currency();
		$order_statuses     = $this->get_initial_statuses();
		$currencies         = $this->get_currencies();
		?>
		<div class="wrap wpss-manual-order-wrap">
			<h1><?php esc_html_e( 'Create Order', 'wp-sell-services' ); ?></h1>

			<?php if ( empty( $services ) ) : ?>
				<div class="wpss-no-services-notice">
					<p>
						<?php esc_html_e( 'No services found. Please create at least one service before creating an order.', 'wp-sell-services' ); ?>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="button">
							<?php esc_html_e( 'Create Service', 'wp-sell-services' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>

				<form id="wpss-manual-order-form" method="post">
					<?php wp_nonce_field( 'wpss_create_manual_order', 'wpss_manual_order_nonce' ); ?>

					<div class="wpss-manual-order-columns">
						<!-- Left Column: Main Content -->
						<div class="wpss-manual-order-main">

							<!-- Section A: Order Details -->
							<div class="postbox">
								<h2 class="hndle"><?php esc_html_e( 'Order Details', 'wp-sell-services' ); ?></h2>
								<div class="inside">

									<!-- Service -->
									<div class="wpss-form-row">
										<label for="wpss-service-id">
											<?php esc_html_e( 'Service', 'wp-sell-services' ); ?>
											<span class="required">*</span>
										</label>
										<select name="service_id" id="wpss-service-id" required>
											<option value=""><?php esc_html_e( '-- Select a Service --', 'wp-sell-services' ); ?></option>
											<?php foreach ( $services as $service ) : ?>
												<?php
												$vendor_id = (int) $service->post_author;
												$vendor    = get_userdata( $vendor_id );
												$price     = get_post_meta( $service->ID, '_wpss_starting_price', true );
												?>
												<option value="<?php echo esc_attr( $service->ID ); ?>"
														data-vendor="<?php echo esc_attr( $vendor_id ); ?>"
														data-price="<?php echo esc_attr( $price ); ?>">
													<?php echo esc_html( $service->post_title ); ?>
													<?php if ( $vendor ) : ?>
														(<?php echo esc_html( $vendor->display_name ); ?>)
													<?php endif; ?>
													<?php if ( $price ) : ?>
														- <?php echo esc_html( wpss_format_price( (float) $price ) ); ?>
													<?php endif; ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>

									<!-- Package -->
									<div class="wpss-form-row" id="wpss-package-row" style="display: none;">
										<label for="wpss-package-id"><?php esc_html_e( 'Package', 'wp-sell-services' ); ?></label>
										<select name="package_id" id="wpss-package-id">
											<option value=""><?php esc_html_e( '-- Select Package --', 'wp-sell-services' ); ?></option>
										</select>
									</div>

									<!-- Addons -->
									<div class="wpss-form-row" id="wpss-addons-container" style="display: none;">
										<label><?php esc_html_e( 'Addons', 'wp-sell-services' ); ?></label>
										<div class="wpss-addons-list" id="wpss-addons-list">
											<div class="wpss-addons-empty">
												<?php esc_html_e( 'Select a service to see available addons.', 'wp-sell-services' ); ?>
											</div>
										</div>
									</div>

									<!-- Customer -->
									<div class="wpss-form-row">
										<label for="wpss-customer-id">
											<?php esc_html_e( 'Customer (Buyer)', 'wp-sell-services' ); ?>
											<span class="required">*</span>
										</label>
										<select name="customer_id" id="wpss-customer-id" required>
											<option value=""><?php esc_html_e( '-- Select Customer --', 'wp-sell-services' ); ?></option>
											<?php foreach ( $users as $user ) : ?>
												<option value="<?php echo esc_attr( $user->ID ); ?>">
													<?php echo esc_html( $user->display_name ); ?>
													(<?php echo esc_html( $user->user_email ); ?>)
												</option>
											<?php endforeach; ?>
										</select>
									</div>

									<!-- Vendor Override -->
									<div class="wpss-form-row">
										<label for="wpss-vendor-id"><?php esc_html_e( 'Vendor (Override)', 'wp-sell-services' ); ?></label>
										<select name="vendor_id" id="wpss-vendor-id">
											<option value=""><?php esc_html_e( '-- Use Service Author --', 'wp-sell-services' ); ?></option>
											<?php foreach ( $users as $user ) : ?>
												<option value="<?php echo esc_attr( $user->ID ); ?>">
													<?php echo esc_html( $user->display_name ); ?>
													(<?php echo esc_html( $user->user_email ); ?>)
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Leave empty to use the service author as vendor.', 'wp-sell-services' ); ?></p>
									</div>
								</div>
							</div>

							<!-- Section B: Pricing -->
							<div class="postbox">
								<h2 class="hndle"><?php esc_html_e( 'Pricing', 'wp-sell-services' ); ?></h2>
								<div class="inside">
									<table class="wpss-pricing-summary">
										<tr>
											<td><?php esc_html_e( 'Subtotal (Package)', 'wp-sell-services' ); ?></td>
											<td id="wpss-summary-subtotal"><?php echo esc_html( wpss_format_price( 0.00 ) ); ?></td>
										</tr>
										<tr id="wpss-pricing-addons-row" style="display: none;">
											<td><?php esc_html_e( 'Addons Total', 'wp-sell-services' ); ?></td>
											<td id="wpss-summary-addons"><?php echo esc_html( wpss_format_price( 0.00 ) ); ?></td>
										</tr>
										<tr class="wpss-pricing-editable">
											<td colspan="2">
												<div class="wpss-override-toggle">
													<input type="checkbox" id="wpss-override-total" value="1">
													<label for="wpss-override-total"><?php esc_html_e( 'Override total manually', 'wp-sell-services' ); ?></label>
												</div>
												<input type="number"
														name="total_override"
														id="wpss-total-override"
														step="0.01"
														min="0"
														placeholder="<?php esc_attr_e( 'Auto-calculated', 'wp-sell-services' ); ?>"
														disabled
														class="wpss-disabled">
											</td>
										</tr>
										<tr class="wpss-pricing-total">
											<td><?php esc_html_e( 'Order Total', 'wp-sell-services' ); ?></td>
											<td id="wpss-summary-total"><?php echo esc_html( wpss_format_price( 0.00 ) ); ?></td>
										</tr>
										<tr class="wpss-pricing-commission">
											<td>
												<?php esc_html_e( 'Commission Rate', 'wp-sell-services' ); ?>
												<input type="number"
														name="commission_rate"
														id="wpss-commission-rate"
														class="wpss-small-input"
														step="0.1"
														min="0"
														max="100"
														value="<?php echo esc_attr( $default_commission ); ?>"
														style="width: 70px; margin-left: 8px;">%
											</td>
											<td></td>
										</tr>
										<tr class="wpss-pricing-commission">
											<td><?php esc_html_e( 'Platform Fee', 'wp-sell-services' ); ?></td>
											<td id="wpss-summary-platform-fee"><?php echo esc_html( wpss_format_price( 0.00 ) ); ?></td>
										</tr>
										<tr class="wpss-pricing-commission">
											<td><?php esc_html_e( 'Vendor Earnings', 'wp-sell-services' ); ?></td>
											<td id="wpss-summary-vendor-earnings"><?php echo esc_html( wpss_format_price( 0.00 ) ); ?></td>
										</tr>
									</table>

									<!-- Currency -->
									<div class="wpss-form-row" style="margin-top: 16px;">
										<label for="wpss-currency"><?php esc_html_e( 'Currency', 'wp-sell-services' ); ?></label>
										<select name="currency" id="wpss-currency">
											<?php foreach ( $currencies as $code => $label ) : ?>
												<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $default_currency, $code ); ?>>
													<?php echo esc_html( $label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>

									<!-- Hidden calculated fields -->
									<input type="hidden" name="subtotal" id="wpss-calculated-subtotal" value="0">
									<input type="hidden" name="addons_total" id="wpss-calculated-addons-total" value="0">
									<input type="hidden" name="total" id="wpss-calculated-total" value="0">
									<input type="hidden" name="platform_fee" id="wpss-calculated-platform-fee" value="0">
									<input type="hidden" name="vendor_earnings" id="wpss-calculated-vendor-earnings" value="0">
								</div>
							</div>
						</div>

						<!-- Right Column: Sidebar -->
						<div class="wpss-manual-order-sidebar">

							<!-- Status & Payment -->
							<div class="postbox">
								<h2 class="hndle"><?php esc_html_e( 'Status & Payment', 'wp-sell-services' ); ?></h2>
								<div class="inside">

									<div class="wpss-form-row">
										<label for="wpss-status"><?php esc_html_e( 'Order Status', 'wp-sell-services' ); ?></label>
										<select name="status" id="wpss-status">
											<?php foreach ( $order_statuses as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>" <?php selected( 'pending_requirements', $value ); ?>>
													<?php echo esc_html( $label ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</div>

									<div class="wpss-form-row">
										<label for="wpss-payment-status"><?php esc_html_e( 'Payment Status', 'wp-sell-services' ); ?></label>
										<select name="payment_status" id="wpss-payment-status">
											<option value="pending"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></option>
											<option value="paid" selected><?php esc_html_e( 'Paid', 'wp-sell-services' ); ?></option>
											<option value="failed"><?php esc_html_e( 'Failed', 'wp-sell-services' ); ?></option>
											<option value="refunded"><?php esc_html_e( 'Refunded', 'wp-sell-services' ); ?></option>
										</select>
									</div>

									<div class="wpss-form-row">
										<label for="wpss-payment-method"><?php esc_html_e( 'Payment Method', 'wp-sell-services' ); ?></label>
										<select name="payment_method" id="wpss-payment-method">
											<option value="manual" selected><?php esc_html_e( 'Manual', 'wp-sell-services' ); ?></option>
											<option value="bank_transfer"><?php esc_html_e( 'Bank Transfer', 'wp-sell-services' ); ?></option>
											<option value="cash"><?php esc_html_e( 'Cash', 'wp-sell-services' ); ?></option>
											<option value="other"><?php esc_html_e( 'Other', 'wp-sell-services' ); ?></option>
										</select>
									</div>

									<div class="wpss-form-row">
										<label for="wpss-transaction-id"><?php esc_html_e( 'Transaction ID', 'wp-sell-services' ); ?></label>
										<input type="text"
												name="transaction_id"
												id="wpss-transaction-id"
												placeholder="<?php esc_attr_e( 'Optional reference number', 'wp-sell-services' ); ?>">
									</div>

									<div class="wpss-form-row">
										<label for="wpss-delivery-days"><?php esc_html_e( 'Delivery Days', 'wp-sell-services' ); ?></label>
										<input type="number"
												name="delivery_days"
												id="wpss-delivery-days"
												min="1"
												value="7">
										<p class="description"><?php esc_html_e( 'Auto-filled from package selection.', 'wp-sell-services' ); ?></p>
									</div>

									<div class="wpss-form-row">
										<label for="wpss-revisions"><?php esc_html_e( 'Revisions Included', 'wp-sell-services' ); ?></label>
										<input type="number"
												name="revisions_included"
												id="wpss-revisions"
												min="0"
												value="2">
										<p class="description"><?php esc_html_e( 'Auto-filled from package selection.', 'wp-sell-services' ); ?></p>
									</div>
								</div>
							</div>

							<!-- Notes -->
							<div class="postbox">
								<h2 class="hndle"><?php esc_html_e( 'Admin Notes', 'wp-sell-services' ); ?></h2>
								<div class="inside">
									<div class="wpss-form-row">
										<textarea name="notes"
													id="wpss-notes"
													rows="4"
													placeholder="<?php esc_attr_e( 'Internal notes about this order...', 'wp-sell-services' ); ?>"></textarea>
										<p class="description"><?php esc_html_e( 'Stored as a system message in the order conversation.', 'wp-sell-services' ); ?></p>
									</div>
								</div>
							</div>

							<!-- Submit -->
							<div class="postbox">
								<div class="inside wpss-submit-section">
									<button type="submit" class="button button-primary button-large" id="wpss-create-order-btn">
										<?php esc_html_e( 'Create Order', 'wp-sell-services' ); ?>
									</button>
									<span class="spinner"></span>
								</div>
							</div>
						</div>
					</div>
				</form>

				<!-- Success Result -->
				<div id="wpss-order-result" class="wpss-order-result" style="display: none;">
					<h3><?php esc_html_e( 'Order Created Successfully!', 'wp-sell-services' ); ?></h3>
					<p id="wpss-result-message"></p>
					<div class="wpss-result-actions">
						<a href="#" id="wpss-view-order-link" class="button button-primary" target="_blank">
							<?php esc_html_e( 'View Order', 'wp-sell-services' ); ?>
						</a>
						<a href="#" id="wpss-requirements-link" class="button" target="_blank" style="display: none;">
							<?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?>
						</a>
						<button type="button" class="button" id="wpss-create-another-btn">
							<?php esc_html_e( 'Create Another Order', 'wp-sell-services' ); ?>
						</button>
					</div>
				</div>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * AJAX handler to get service addons.
	 *
	 * @return void
	 */
	public function ajax_get_service_addons(): void {
		check_ajax_referer( 'wpss_create_manual_order', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$service_id = absint( $_POST['service_id'] ?? 0 );

		if ( ! $service_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service ID.', 'wp-sell-services' ) ) );
		}

		$addon_service = new ServiceAddonService();
		$addons        = $addon_service->get_service_addons( $service_id );

		$formatted = array();
		foreach ( $addons as $addon ) {
			$formatted[] = array(
				'id'                  => (int) $addon->id,
				'title'               => $addon->title,
				'description'         => $addon->description ?? '',
				'field_type'          => $addon->field_type,
				'price'               => $addon->price,
				'formatted_price'     => wpss_format_price( $addon->price ),
				'price_type'          => $addon->price_type,
				'min_quantity'        => $addon->min_quantity,
				'max_quantity'        => $addon->max_quantity,
				'is_required'         => $addon->is_required,
				'delivery_days_extra' => $addon->delivery_days_extra,
			);
		}

		wp_send_json_success( array( 'addons' => $formatted ) );
	}

	/**
	 * Handle AJAX order creation.
	 *
	 * @return void
	 */
	public function handle_create_order(): void {
		check_ajax_referer( 'wpss_create_manual_order', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		// --- 1. Collect and sanitize inputs ---
		$service_id      = absint( $_POST['service_id'] ?? 0 );
		$package_id      = absint( $_POST['package_id'] ?? 0 );
		$customer_id     = absint( $_POST['customer_id'] ?? 0 );
		$vendor_id_input = absint( $_POST['vendor_id'] ?? 0 );
		$status          = sanitize_key( $_POST['status'] ?? 'pending_requirements' );
		$payment_status  = sanitize_key( $_POST['payment_status'] ?? 'paid' );
		$payment_method  = sanitize_key( $_POST['payment_method'] ?? 'manual' );
		$transaction_id  = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';
		$delivery_days   = absint( $_POST['delivery_days'] ?? 7 );
		$revisions_input = absint( $_POST['revisions_included'] ?? 2 );
		$currency        = isset( $_POST['currency'] ) ? sanitize_text_field( wp_unslash( $_POST['currency'] ) ) : wpss_get_currency();
		$commission_rate = isset( $_POST['commission_rate'] ) ? (float) $_POST['commission_rate'] : CommissionService::get_global_commission_rate();
		$notes           = isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '';

		// Pricing from JS calculations (hidden fields).
		$subtotal_input = isset( $_POST['subtotal'] ) ? (float) $_POST['subtotal'] : 0;
		$total_input    = isset( $_POST['total'] ) ? (float) $_POST['total'] : 0;

		// Addons from form.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$addons_raw = isset( $_POST['addons'] ) ? (array) wp_unslash( $_POST['addons'] ) : array();

		// --- 2. Validate required ---
		if ( ! $service_id || ! $customer_id ) {
			wp_send_json_error( array( 'message' => __( 'Service and Customer are required.', 'wp-sell-services' ) ) );
		}

		$service = get_post( $service_id );
		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'wp-sell-services' ) ) );
		}

		// --- 3. Determine vendor ---
		$vendor_id = $vendor_id_input ? $vendor_id_input : (int) $service->post_author;

		if ( $customer_id === $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'Customer cannot be the same as the vendor.', 'wp-sell-services' ) ) );
		}

		// --- 4. Load package data ---
		$revisions_included = $revisions_input;
		$subtotal           = $subtotal_input;

		if ( $package_id ) {
			$packages = get_post_meta( $service_id, '_wpss_packages', true );
			if ( is_array( $packages ) && isset( $packages[ $package_id ] ) ) {
				$package = $packages[ $package_id ];
				if ( ! $subtotal ) {
					$subtotal      = (float) ( $package['price'] ?? 0 );
					$delivery_days = (int) ( $package['delivery_days'] ?? $delivery_days );
				}
				if ( ! $revisions_input ) {
					$revisions_included = (int) ( $package['revisions'] ?? 2 );
				}
			}
		}

		// Fallback to starting price.
		if ( ! $subtotal ) {
			$subtotal = (float) get_post_meta( $service_id, '_wpss_starting_price', true );
		}

		// --- 5. Process addons ---
		$addon_service   = new ServiceAddonService();
		$selected_addons = array();
		$addons_total    = 0;

		foreach ( $addons_raw as $addon_id => $addon_data ) {
			$addon_id = absint( $addon_id );
			if ( empty( $addon_data['selected'] ) ) {
				continue;
			}

			$addon = $addon_service->get( $addon_id );
			if ( ! $addon ) {
				continue;
			}

			$quantity    = absint( $addon_data['quantity'] ?? 1 );
			$addon_price = $addon_service->calculate_price( $addon, $subtotal, $quantity );

			$selected_addons[] = array(
				'id'       => $addon_id,
				'title'    => $addon->title,
				'price'    => $addon_price,
				'quantity' => $quantity,
			);

			$addons_total += $addon_price;
		}

		// --- 6. Calculate total ---
		$total = $total_input;
		if ( ! $total || $total <= 0 ) {
			$total = $subtotal + $addons_total;
		}

		if ( ! $total || $total <= 0 ) {
			$total = 10.00; // Minimum fallback.
		}

		// --- 7. Calculate commission ---
		$commission_rate = max( 0, min( 100, $commission_rate ) );
		$platform_fee    = round( $total * ( $commission_rate / 100 ), 2 );
		$vendor_earnings = round( $total - $platform_fee, 2 );

		// --- 8. Smart status ---
		$service_requirements     = get_post_meta( $service_id, '_wpss_requirements', true );
		$service_has_requirements = ! empty( $service_requirements ) && is_array( $service_requirements );

		$requirements_skipped = false;
		if ( 'pending_requirements' === $status && ! $service_has_requirements ) {
			$status               = 'in_progress';
			$requirements_skipped = true;
		}

		// --- 9. Calculate deadline ---
		$deadline = null;
		if ( $delivery_days && 'in_progress' === $status ) {
			$deadline = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delivery_days} days" ) );
		}

		// Generate order number.
		$order_number = 'WPSS-' . strtoupper( wp_generate_password( 8, false ) );

		// --- 10. Insert order ---
		// Build data/format arrays dynamically to avoid passing null values
		// to $wpdb->insert(), which triggers PHP 8.1+ deprecation notices
		// in wp_slash() → addslashes() and str_replace().
		global $wpdb;

		$data   = array(
			'order_number'       => $order_number,
			'customer_id'        => $customer_id,
			'vendor_id'          => $vendor_id,
			'service_id'         => $service_id,
			'platform'           => 'manual',
			'addons'             => wp_json_encode( $selected_addons ),
			'subtotal'           => $subtotal,
			'addons_total'       => $addons_total,
			'total'              => $total,
			'currency'           => $currency,
			'status'             => $status,
			'payment_method'     => $payment_method,
			'payment_status'     => $payment_status,
			'commission_rate'    => $commission_rate,
			'platform_fee'       => $platform_fee,
			'vendor_earnings'    => $vendor_earnings,
			'revisions_included' => $revisions_included,
			'revisions_used'     => 0,
			'created_at'         => current_time( 'mysql', true ),
			'updated_at'         => current_time( 'mysql', true ),
		);
		$format = array(
			'%s', // order_number.
			'%d', // customer_id.
			'%d', // vendor_id.
			'%d', // service_id.
			'%s', // platform.
			'%s', // addons.
			'%f', // subtotal.
			'%f', // addons_total.
			'%f', // total.
			'%s', // currency.
			'%s', // status.
			'%s', // payment_method.
			'%s', // payment_status.
			'%f', // commission_rate.
			'%f', // platform_fee.
			'%f', // vendor_earnings.
			'%d', // revisions_included.
			'%d', // revisions_used.
			'%s', // created_at.
			'%s', // updated_at.
		);

		// Only include nullable columns when they have non-null values.
		if ( $package_id ) {
			$data['package_id'] = $package_id;
			$format[]           = '%d';
		}
		if ( $transaction_id ) {
			$data['transaction_id'] = $transaction_id;
			$format[]               = '%s';
		}
		if ( $deadline ) {
			$data['delivery_deadline'] = $deadline;
			$format[]                  = '%s';
			$data['original_deadline'] = $deadline;
			$format[]                  = '%s';
		}
		if ( 'in_progress' === $status ) {
			$data['started_at'] = current_time( 'mysql', true );
			$format[]           = '%s';
		}
		if ( 'paid' === $payment_status ) {
			$data['paid_at'] = current_time( 'mysql', true );
			$format[]        = '%s';
		}
		if ( 'completed' === $status ) {
			$data['completed_at'] = current_time( 'mysql', true );
			$format[]             = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'wpss_orders',
			$data,
			$format
		);

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create order.', 'wp-sell-services' ) ) );
		}

		$order_id = (int) $wpdb->insert_id;

		// --- 11. Create conversation ---
		$conversation_service = new ConversationService();
		$conversation         = $conversation_service->create_for_order( $order_id );

		// --- 12. Add admin notes as system message ---
		if ( $notes && $conversation ) {
			$conversation_service->add_system_message(
				$conversation->id,
				sprintf(
					/* translators: %s: admin notes */
					__( '[Admin Note] %s', 'wp-sell-services' ),
					$notes
				)
			);
		}

		// --- 13. Fire hooks ---
		// Wrap in output buffer to prevent PHP deprecation notices or stray
		// output from notification handlers from corrupting the JSON response.
		$order_data = array(
			'service_id'     => $service_id,
			'package_id'     => $package_id,
			'customer_id'    => $customer_id,
			'vendor_id'      => $vendor_id,
			'subtotal'       => $subtotal,
			'addons_total'   => $addons_total,
			'total'          => $total,
			'currency'       => $currency,
			'status'         => $status,
			'payment_method' => $payment_method,
			'platform'       => 'manual',
		);
		ob_start();
		do_action( 'wpss_order_created', $order_id, $order_data );
		do_action( 'wpss_order_status_changed', $order_id, $status, '' );
		do_action( "wpss_order_status_{$status}", $order_id, '' );
		ob_end_clean();

		// --- 14. Send response ---
		wp_send_json_success(
			array(
				'order_id'             => $order_id,
				'order_number'         => $order_number,
				'status'               => $status,
				'view_url'             => wpss_get_order_url( $order_id ),
				'requirements_url'     => wpss_get_order_requirements_url( $order_id ),
				'requirements_skipped' => $requirements_skipped,
				'has_requirements'     => $service_has_requirements,
			)
		);
	}

	/**
	 * Get initial order statuses available for manual creation.
	 *
	 * @return array<string, string>
	 */
	private function get_initial_statuses(): array {
		return array(
			'pending_payment'      => __( 'Pending Payment', 'wp-sell-services' ),
			'pending_requirements' => __( 'Pending Requirements (Payment Complete)', 'wp-sell-services' ),
			'in_progress'          => __( 'In Progress (Skip Requirements)', 'wp-sell-services' ),
			'delivered'            => __( 'Delivered', 'wp-sell-services' ),
			'completed'            => __( 'Completed', 'wp-sell-services' ),
		);
	}

	/**
	 * Get supported currencies.
	 *
	 * @return array<string, string>
	 */
	private function get_currencies(): array {
		return array(
			'USD' => 'USD ($)',
			'EUR' => 'EUR (€)',
			'GBP' => 'GBP (£)',
			'INR' => 'INR (₹)',
			'AUD' => 'AUD (A$)',
			'CAD' => 'CAD (C$)',
			'JPY' => 'JPY (¥)',
			'CHF' => 'CHF',
			'CNY' => 'CNY (¥)',
			'BRL' => 'BRL (R$)',
			'MXN' => 'MXN (MX$)',
			'SGD' => 'SGD (S$)',
			'HKD' => 'HKD (HK$)',
			'NZD' => 'NZD (NZ$)',
			'KRW' => 'KRW (₩)',
			'TRY' => 'TRY (₺)',
			'ZAR' => 'ZAR (R)',
			'AED' => 'AED (د.إ)',
			'SAR' => 'SAR (﷼)',
			'PLN' => 'PLN (zł)',
			'THB' => 'THB (฿)',
			'MYR' => 'MYR (RM)',
			'PHP' => 'PHP (₱)',
			'IDR' => 'IDR (Rp)',
			'VND' => 'VND (₫)',
		);
	}
}
