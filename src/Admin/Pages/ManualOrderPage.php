<?php
/**
 * Manual Order Creation Page
 *
 * Allows admins to create test orders without going through checkout.
 *
 * @package WPSellServices\Admin\Pages
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Pages;

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
		// Priority 20 ensures this runs after Admin::enqueue_scripts registers wpss-admin.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ), 20 );
	}

	/**
	 * Add submenu page.
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_submenu_page(
			'wp-sell-services',
			__( 'Create Test Order', 'wp-sell-services' ),
			__( 'Create Test Order', 'wp-sell-services' ),
			'manage_options',
			'wpss-create-order',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue page scripts.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'wp-sell-services_page_wpss-create-order' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wpss-admin' );
		wp_enqueue_script( 'wpss-admin' );

		wp_add_inline_script(
			'wpss-admin',
			'window.wpssManualOrder = ' . wp_json_encode(
				array(
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'wpss_create_manual_order' ),
				)
			) . ';'
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
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Create Test Order', 'wp-sell-services' ); ?></h1>

			<div class="wpss-admin-notice wpss-notice-info">
				<p>
					<strong><?php esc_html_e( 'Testing Tool', 'wp-sell-services' ); ?></strong>
					<?php esc_html_e( 'This page allows you to create test orders to verify the complete order workflow without going through the checkout process.', 'wp-sell-services' ); ?>
				</p>
			</div>

			<?php if ( empty( $services ) ) : ?>
				<div class="wpss-admin-notice wpss-notice-warning">
					<p>
						<?php esc_html_e( 'No services found. Please create at least one service before creating a test order.', 'wp-sell-services' ); ?>
						<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="button">
							<?php esc_html_e( 'Create Service', 'wp-sell-services' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>
				<div class="wpss-create-order-form">
					<form id="wpss-manual-order-form" method="post">
						<?php wp_nonce_field( 'wpss_create_manual_order', 'wpss_manual_order_nonce' ); ?>

						<table class="form-table">
							<tbody>
								<!-- Service Selection -->
								<tr>
									<th scope="row">
										<label for="service_id"><?php esc_html_e( 'Service', 'wp-sell-services' ); ?> <span class="required">*</span></label>
									</th>
									<td>
										<select name="service_id" id="service_id" class="regular-text" required>
											<option value=""><?php esc_html_e( '-- Select a Service --', 'wp-sell-services' ); ?></option>
											<?php foreach ( $services as $service ) : ?>
												<?php
												$vendor_id = $service->post_author;
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
										<p class="description"><?php esc_html_e( 'Select the service for this order.', 'wp-sell-services' ); ?></p>
									</td>
								</tr>

								<!-- Package Selection -->
								<tr id="package-row" style="display: none;">
									<th scope="row">
										<label for="package_id"><?php esc_html_e( 'Package', 'wp-sell-services' ); ?></label>
									</th>
									<td>
										<select name="package_id" id="package_id" class="regular-text">
											<option value=""><?php esc_html_e( '-- Select Package --', 'wp-sell-services' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Select a pricing package (if available).', 'wp-sell-services' ); ?></p>
									</td>
								</tr>

								<!-- Customer Selection -->
								<tr>
									<th scope="row">
										<label for="customer_id"><?php esc_html_e( 'Customer (Buyer)', 'wp-sell-services' ); ?> <span class="required">*</span></label>
									</th>
									<td>
										<select name="customer_id" id="customer_id" class="regular-text" required>
											<option value=""><?php esc_html_e( '-- Select Customer --', 'wp-sell-services' ); ?></option>
											<?php foreach ( $users as $user ) : ?>
												<option value="<?php echo esc_attr( $user->ID ); ?>">
													<?php echo esc_html( $user->display_name ); ?>
													(<?php echo esc_html( $user->user_email ); ?>)
												</option>
											<?php endforeach; ?>
										</select>
										<p class="description"><?php esc_html_e( 'The user who is buying this service.', 'wp-sell-services' ); ?></p>
									</td>
								</tr>

								<!-- Price Override -->
								<tr>
									<th scope="row">
										<label for="total"><?php esc_html_e( 'Total Amount', 'wp-sell-services' ); ?></label>
									</th>
									<td>
										<input type="number"
												name="total"
												id="total"
												class="regular-text"
												step="0.01"
												min="0"
												placeholder="<?php esc_attr_e( 'Auto-calculated from service', 'wp-sell-services' ); ?>">
										<p class="description"><?php esc_html_e( 'Leave empty to use the service/package price.', 'wp-sell-services' ); ?></p>
									</td>
								</tr>

								<!-- Initial Status -->
								<tr>
									<th scope="row">
										<label for="status"><?php esc_html_e( 'Initial Status', 'wp-sell-services' ); ?></label>
									</th>
									<td>
										<select name="status" id="status" class="regular-text">
											<option value="pending_payment"><?php esc_html_e( 'Pending Payment', 'wp-sell-services' ); ?></option>
											<option value="pending_requirements" selected><?php esc_html_e( 'Pending Requirements (Payment Complete)', 'wp-sell-services' ); ?></option>
											<option value="in_progress"><?php esc_html_e( 'In Progress (Skip requirements)', 'wp-sell-services' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Set the initial status. "Pending Requirements" simulates a paid order.', 'wp-sell-services' ); ?></p>
									</td>
								</tr>

								<!-- Delivery Days -->
								<tr>
									<th scope="row">
										<label for="delivery_days"><?php esc_html_e( 'Delivery Days', 'wp-sell-services' ); ?></label>
									</th>
									<td>
										<input type="number"
												name="delivery_days"
												id="delivery_days"
												class="small-text"
												min="1"
												value="7"
												placeholder="7">
										<p class="description"><?php esc_html_e( 'Number of days for delivery deadline from order start.', 'wp-sell-services' ); ?></p>
									</td>
								</tr>

								<!-- Notes -->
								<tr>
									<th scope="row">
										<label for="notes"><?php esc_html_e( 'Admin Notes', 'wp-sell-services' ); ?></label>
									</th>
									<td>
										<textarea name="notes"
													id="notes"
													rows="3"
													class="large-text"
													placeholder="<?php esc_attr_e( 'Optional notes about this test order...', 'wp-sell-services' ); ?>"></textarea>
									</td>
								</tr>
							</tbody>
						</table>

						<p class="submit">
							<button type="submit" class="button button-primary button-large" id="create-order-btn">
								<?php esc_html_e( 'Create Test Order', 'wp-sell-services' ); ?>
							</button>
							<span class="spinner" style="float: none; margin-top: 0;"></span>
						</p>
					</form>

					<div id="order-result" style="display: none;">
						<div class="wpss-admin-notice wpss-notice-success">
							<h3><?php esc_html_e( 'Order Created Successfully!', 'wp-sell-services' ); ?></h3>
							<p id="order-result-message"></p>
							<p>
								<a href="#" id="view-order-link" class="button" target="_blank">
									<?php esc_html_e( 'View Order', 'wp-sell-services' ); ?>
								</a>
								<a href="#" id="requirements-link" class="button" target="_blank">
									<?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?>
								</a>
								<button type="button" class="button" id="create-another-btn">
									<?php esc_html_e( 'Create Another', 'wp-sell-services' ); ?>
								</button>
							</p>
						</div>
					</div>
				</div>

				<!-- Quick Test Flow Guide -->
				<div class="wpss-test-guide">
					<h2><?php esc_html_e( 'Testing the Order Flow', 'wp-sell-services' ); ?></h2>
					<div class="wpss-flow-steps">
						<div class="wpss-flow-step">
							<span class="step-number">1</span>
							<h4><?php esc_html_e( 'Create Order', 'wp-sell-services' ); ?></h4>
							<p><?php esc_html_e( 'Use the form above to create a test order.', 'wp-sell-services' ); ?></p>
						</div>
						<div class="wpss-flow-step">
							<span class="step-number">2</span>
							<h4><?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?></h4>
							<p><?php esc_html_e( 'As the buyer, submit the order requirements.', 'wp-sell-services' ); ?></p>
						</div>
						<div class="wpss-flow-step">
							<span class="step-number">3</span>
							<h4><?php esc_html_e( 'Vendor Actions', 'wp-sell-services' ); ?></h4>
							<p><?php esc_html_e( 'Accept order, start work, and deliver.', 'wp-sell-services' ); ?></p>
						</div>
						<div class="wpss-flow-step">
							<span class="step-number">4</span>
							<h4><?php esc_html_e( 'Complete Order', 'wp-sell-services' ); ?></h4>
							<p><?php esc_html_e( 'Buyer accepts delivery or requests revision.', 'wp-sell-services' ); ?></p>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<style>
			.wpss-admin-notice {
				padding: 15px 20px;
				border-left: 4px solid #2271b1;
				background: #fff;
				margin: 20px 0;
				box-shadow: 0 1px 1px rgba(0,0,0,0.04);
			}
			.wpss-notice-info { border-left-color: #2271b1; }
			.wpss-notice-success { border-left-color: #00a32a; }
			.wpss-notice-warning { border-left-color: #dba617; }

			.wpss-create-order-form {
				background: #fff;
				padding: 20px;
				border: 1px solid #c3c4c7;
				margin: 20px 0;
			}
			.wpss-create-order-form .required { color: #d63638; }

			.wpss-test-guide {
				background: #fff;
				padding: 20px;
				border: 1px solid #c3c4c7;
				margin: 20px 0;
			}
			.wpss-flow-steps {
				display: grid;
				grid-template-columns: repeat(4, 1fr);
				gap: 20px;
				margin-top: 20px;
			}
			.wpss-flow-step {
				padding: 20px;
				background: #f6f7f7;
				border-radius: 4px;
				text-align: center;
			}
			.wpss-flow-step .step-number {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				width: 40px;
				height: 40px;
				background: #2271b1;
				color: #fff;
				border-radius: 50%;
				font-size: 18px;
				font-weight: 600;
				margin-bottom: 10px;
			}
			.wpss-flow-step h4 {
				margin: 0 0 8px;
			}
			.wpss-flow-step p {
				margin: 0;
				color: #646970;
				font-size: 13px;
			}

			@media (max-width: 1200px) {
				.wpss-flow-steps {
					grid-template-columns: repeat(2, 1fr);
				}
			}
			@media (max-width: 600px) {
				.wpss-flow-steps {
					grid-template-columns: 1fr;
				}
			}
		</style>

		<script>
		jQuery(function($) {
			var $form = $('#wpss-manual-order-form');
			var $result = $('#order-result');
			var $submitBtn = $('#create-order-btn');
			var $spinner = $form.find('.spinner');

			// Load packages when service changes
			$('#service_id').on('change', function() {
				var serviceId = $(this).val();
				var $packageRow = $('#package-row');
				var $packageSelect = $('#package_id');
				var $totalInput = $('#total');

				if (!serviceId) {
					$packageRow.hide();
					$packageSelect.html('<option value=""><?php esc_html_e( '-- Select Package --', 'wp-sell-services' ); ?></option>');
					return;
				}

				// Get packages via AJAX
				$.ajax({
					url: wpssManualOrder.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_get_service_packages',
						service_id: serviceId,
						nonce: wpssManualOrder.nonce
					},
					success: function(response) {
						if (response.success && response.data.packages && response.data.packages.length > 0) {
							var options = '<option value=""><?php esc_html_e( '-- Select Package --', 'wp-sell-services' ); ?></option>';
							$.each(response.data.packages, function(i, pkg) {
								options += '<option value="' + pkg.id + '" data-price="' + pkg.price + '">' +
									pkg.name + ' - ' + pkg.formatted_price + ' (' + pkg.delivery_days + ' days)</option>';
							});
							$packageSelect.html(options);
							$packageRow.show();
						} else {
							$packageRow.hide();
							// Use starting price
							var price = $('#service_id option:selected').data('price');
							if (price) {
								$totalInput.attr('placeholder', price);
							}
						}
					}
				});
			});

			// Update price when package changes
			$('#package_id').on('change', function() {
				var price = $(this).find(':selected').data('price');
				if (price) {
					$('#total').attr('placeholder', price);
				}
			});

			// Submit form
			$form.on('submit', function(e) {
				e.preventDefault();

				$submitBtn.prop('disabled', true);
				$spinner.addClass('is-active');

				$.ajax({
					url: wpssManualOrder.ajaxUrl,
					type: 'POST',
					data: {
						action: 'wpss_create_manual_order',
						nonce: wpssManualOrder.nonce,
						service_id: $('#service_id').val(),
						package_id: $('#package_id').val(),
						customer_id: $('#customer_id').val(),
						total: $('#total').val(),
						status: $('#status').val(),
						delivery_days: $('#delivery_days').val(),
						notes: $('#notes').val()
					},
					success: function(response) {
						if (response.success) {
							$('#order-result-message').html(
								'Order #' + response.data.order_number + ' has been created.<br>' +
								'Order ID: ' + response.data.order_id
							);
							$('#view-order-link').attr('href', response.data.view_url);
							$('#requirements-link').attr('href', response.data.requirements_url);

							if (response.data.status !== 'pending_requirements') {
								$('#requirements-link').hide();
							} else {
								$('#requirements-link').show();
							}

							$form.hide();
							$result.show();
						} else {
							alert(response.data.message || 'Failed to create order.');
						}
					},
					error: function() {
						alert('An error occurred. Please try again.');
					},
					complete: function() {
						$submitBtn.prop('disabled', false);
						$spinner.removeClass('is-active');
					}
				});
			});

			// Create another
			$('#create-another-btn').on('click', function() {
				$form[0].reset();
				$('#package-row').hide();
				$result.hide();
				$form.show();
			});
		});
		</script>
		<?php
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

		$service_id    = absint( $_POST['service_id'] ?? 0 );
		$package_id    = absint( $_POST['package_id'] ?? 0 );
		$customer_id   = absint( $_POST['customer_id'] ?? 0 );
		$total         = floatval( $_POST['total'] ?? 0 );
		$status        = sanitize_key( $_POST['status'] ?? 'pending_requirements' );
		$delivery_days = absint( $_POST['delivery_days'] ?? 7 );
		$notes         = sanitize_textarea_field( $_POST['notes'] ?? '' );

		if ( ! $service_id || ! $customer_id ) {
			wp_send_json_error( array( 'message' => __( 'Service and Customer are required.', 'wp-sell-services' ) ) );
		}

		$service = get_post( $service_id );
		if ( ! $service || 'wpss_service' !== $service->post_type ) {
			wp_send_json_error( array( 'message' => __( 'Invalid service.', 'wp-sell-services' ) ) );
		}

		$vendor_id = (int) $service->post_author;

		// Prevent customer = vendor.
		if ( $customer_id === $vendor_id ) {
			wp_send_json_error( array( 'message' => __( 'Customer cannot be the same as the vendor.', 'wp-sell-services' ) ) );
		}

		// Get price if not specified.
		if ( ! $total ) {
			if ( $package_id ) {
				$packages = get_post_meta( $service_id, '_wpss_packages', true );
				if ( is_array( $packages ) && isset( $packages[ $package_id ] ) ) {
					$package       = $packages[ $package_id ];
					$total         = (float) ( $package['price'] ?? 0 );
					$delivery_days = (int) ( $package['delivery_days'] ?? 0 );
				}
			}

			if ( ! $total ) {
				$total = (float) get_post_meta( $service_id, '_wpss_starting_price', true );
			}
		}

		if ( ! $total || $total <= 0 ) {
			$total = 10.00; // Default for testing.
		}

		// Generate order number.
		$order_number = 'WPSS-' . strtoupper( wp_generate_password( 8, false ) );

		// Calculate deadline.
		$deadline = null;
		if ( $delivery_days && 'in_progress' === $status ) {
			$deadline = gmdate( 'Y-m-d H:i:s', strtotime( "+{$delivery_days} days" ) );
		}

		// Insert order.
		global $wpdb;
		$result = $wpdb->insert(
			$wpdb->prefix . 'wpss_orders',
			array(
				'order_number'       => $order_number,
				'customer_id'        => $customer_id,
				'vendor_id'          => $vendor_id,
				'service_id'         => $service_id,
				'package_id'         => $package_id ?: null,
				'platform'           => 'manual',
				'platform_order_id'  => null,
				'subtotal'           => $total,
				'addons_total'       => 0,
				'total'              => $total,
				'currency'           => wpss_get_currency(),
				'status'             => $status,
				'payment_method'     => 'manual',
				'payment_status'     => 'in_progress' === $status || 'pending_requirements' === $status ? 'paid' : 'pending',
				'revisions_included' => 2,
				'revisions_used'     => 0,
				'delivery_deadline'  => $deadline,
				'original_deadline'  => $deadline,
				'started_at'         => 'in_progress' === $status ? current_time( 'mysql', true ) : null,
				'created_at'         => current_time( 'mysql', true ),
				'updated_at'         => current_time( 'mysql', true ),
			),
			array(
				'%s',
				'%d',
				'%d',
				'%d',
				'%d',
				'%s',
				'%d',
				'%f',
				'%f',
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create order.', 'wp-sell-services' ) ) );
		}

		$order_id = $wpdb->insert_id;

		// Add admin note if provided.
		if ( $notes ) {
			$wpdb->insert(
				$wpdb->prefix . 'wpss_conversations',
				array(
					'order_id'   => $order_id,
					'sender_id'  => get_current_user_id(),
					'message'    => sprintf(
						/* translators: %s: admin notes */
						__( '[Admin Note] %s', 'wp-sell-services' ),
						$notes
					),
					'type'       => 'system',
					'created_at' => current_time( 'mysql', true ),
				),
				array( '%d', '%d', '%s', '%s', '%s' )
			);
		}

		// Fire order created action.
		do_action( 'wpss_order_created', $order_id, $status );

		wp_send_json_success(
			array(
				'order_id'         => $order_id,
				'order_number'     => $order_number,
				'status'           => $status,
				'view_url'         => wpss_get_order_url( $order_id ),
				'requirements_url' => wpss_get_order_requirements_url( $order_id ),
			)
		);
	}
}
