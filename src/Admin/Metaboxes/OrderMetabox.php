<?php
/**
 * Order Metabox
 *
 * Admin metabox for displaying and managing service orders.
 *
 * @package WPSellServices\Admin\Metaboxes
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Admin\Metaboxes;

use WPSellServices\Database\Repositories\OrderRepository;
use WPSellServices\Database\Repositories\ConversationRepository;
use WPSellServices\Database\Repositories\DeliveryRepository;
use WPSellServices\Models\ServiceOrder;
use WPSellServices\Services\OrderService;

defined( 'ABSPATH' ) || exit;

/**
 * OrderMetabox class.
 *
 * Handles the admin order details metabox.
 *
 * @since 1.0.0
 */
class OrderMetabox {

	/**
	 * Order repository.
	 *
	 * @var OrderRepository
	 */
	private OrderRepository $order_repo;

	/**
	 * Conversation repository.
	 *
	 * @var ConversationRepository
	 */
	private ConversationRepository $conversation_repo;

	/**
	 * Delivery repository.
	 *
	 * @var DeliveryRepository
	 */
	private DeliveryRepository $delivery_repo;

	/**
	 * Order service.
	 *
	 * @var OrderService
	 */
	private OrderService $order_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->order_repo        = new OrderRepository();
		$this->conversation_repo = new ConversationRepository();
		$this->delivery_repo     = new DeliveryRepository();
		$this->order_service     = new OrderService();
	}

	/**
	 * Initialize metabox.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'add_meta_boxes', array( $this, 'register_metaboxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_wpss_admin_update_order_status', array( $this, 'ajax_update_status' ) );
		add_action( 'wp_ajax_wpss_admin_add_order_note', array( $this, 'ajax_add_note' ) );
		add_action( 'wp_ajax_wpss_admin_submit_requirements', array( $this, 'ajax_submit_requirements' ) );
	}

	/**
	 * Register metaboxes.
	 *
	 * @return void
	 */
	public function register_metaboxes(): void {
		// Only show on WPSS orders admin page.
		$screen = get_current_screen();

		if ( ! $screen || 'wpss_orders' !== $screen->id ) {
			return;
		}

		add_meta_box(
			'wpss_order_details',
			__( 'Order Details', 'wp-sell-services' ),
			array( $this, 'render_details_metabox' ),
			'wpss_orders',
			'normal',
			'high'
		);

		add_meta_box(
			'wpss_order_items',
			__( 'Order Items', 'wp-sell-services' ),
			array( $this, 'render_items_metabox' ),
			'wpss_orders',
			'normal',
			'high'
		);

		add_meta_box(
			'wpss_order_requirements',
			__( 'Buyer Requirements', 'wp-sell-services' ),
			array( $this, 'render_requirements_metabox' ),
			'wpss_orders',
			'normal',
			'default'
		);

		add_meta_box(
			'wpss_order_deliveries',
			__( 'Deliveries', 'wp-sell-services' ),
			array( $this, 'render_deliveries_metabox' ),
			'wpss_orders',
			'normal',
			'default'
		);

		add_meta_box(
			'wpss_order_messages',
			__( 'Messages', 'wp-sell-services' ),
			array( $this, 'render_messages_metabox' ),
			'wpss_orders',
			'normal',
			'default'
		);

		add_meta_box(
			'wpss_order_actions',
			__( 'Order Actions', 'wp-sell-services' ),
			array( $this, 'render_actions_metabox' ),
			'wpss_orders',
			'side',
			'high'
		);

		add_meta_box(
			'wpss_order_notes',
			__( 'Admin Notes', 'wp-sell-services' ),
			array( $this, 'render_notes_metabox' ),
			'wpss_orders',
			'side',
			'default'
		);

		add_meta_box(
			'wpss_order_timeline',
			__( 'Order Timeline', 'wp-sell-services' ),
			array( $this, 'render_timeline_metabox' ),
			'wpss_orders',
			'side',
			'default'
		);
	}

	/**
	 * Enqueue metabox assets.
	 *
	 * @param string $hook Current admin page.
	 * @return void
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'wpss_orders' !== get_current_screen()->id ) {
			return;
		}

		wp_enqueue_style(
			'wpss-order-metabox',
			\WPSS_PLUGIN_URL . 'assets/css/orders.css',
			array(),
			\WPSS_VERSION
		);
		wp_style_add_data( 'wpss-order-metabox', 'rtl', 'replace' );

		wp_enqueue_script(
			'wpss-order-metabox',
			\WPSS_PLUGIN_URL . 'assets/js/admin-order.js',
			array( 'jquery' ),
			\WPSS_VERSION,
			true
		);

		wp_localize_script(
			'wpss-order-metabox',
			'wpssOrderAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpss_order_admin' ),
				'i18n'    => array(
					'confirmStatusChange'     => __( 'Are you sure you want to change the order status?', 'wp-sell-services' ),
					'confirmRefund'           => __( 'Are you sure you want to process a refund?', 'wp-sell-services' ),
					'noteAdded'               => __( 'Note added successfully.', 'wp-sell-services' ),
					'requirementsSaved'       => __( 'Requirements saved successfully.', 'wp-sell-services' ),
					'error'                   => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
					'update'                  => __( 'Update', 'wp-sell-services' ),
					'updating'                => __( 'Updating...', 'wp-sell-services' ),
					'savingRequirements'      => __( 'Saving...', 'wp-sell-services' ),
				),
			)
		);
	}

	/**
	 * Get order from request.
	 *
	 * @return ServiceOrder|null
	 */
	private function get_order(): ?ServiceOrder {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		if ( ! $order_id ) {
			return null;
		}

		$row = $this->order_repo->find( $order_id );

		return $row ? ServiceOrder::from_db( $row ) : null;
	}

	/**
	 * Render order details metabox.
	 *
	 * @return void
	 */
	public function render_details_metabox(): void {
		$order = $this->get_order();

		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order not found.', 'wp-sell-services' ) . '</p>';
			return;
		}

		$buyer    = get_userdata( $order->get_buyer_id() );
		$vendor   = get_userdata( $order->get_vendor_id() );
		$service  = get_post( $order->get_service_id() );
		$wc_order = ( $order->get_wc_order_id() && function_exists( 'wc_get_order' ) ) ? wc_get_order( $order->get_wc_order_id() ) : null;
		?>
		<div class="wpss-order-details-grid">
			<div class="wpss-order-detail">
				<label><?php esc_html_e( 'Order ID', 'wp-sell-services' ); ?></label>
				<span>#<?php echo esc_html( $order->get_id() ); ?></span>
			</div>

			<div class="wpss-order-detail">
				<label><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></label>
				<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $order->get_status() ); ?>">
					<?php echo esc_html( wpss_get_order_status_label( $order->get_status() ) ); ?>
				</span>
			</div>

			<div class="wpss-order-detail">
				<label><?php esc_html_e( 'Created', 'wp-sell-services' ); ?></label>
				<span><?php echo esc_html( wp_date( 'F j, Y g:i a', strtotime( $order->get_created_at() ) ) ); ?></span>
			</div>

			<div class="wpss-order-detail">
				<label><?php esc_html_e( 'Due Date', 'wp-sell-services' ); ?></label>
				<span>
					<?php
					$due_date = $order->get_due_date();
					if ( $due_date ) {
						echo esc_html( wp_date( 'F j, Y', strtotime( $due_date ) ) );

						$days_left = $order->get_days_until_due();
						if ( $days_left < 0 ) {
							echo ' <span class="wpss-overdue">(' . esc_html__( 'Overdue', 'wp-sell-services' ) . ')</span>';
						} elseif ( $days_left <= 2 ) {
							/* translators: %d: days left */
							echo ' <span class="wpss-due-soon">(' . sprintf( esc_html__( '%d days left', 'wp-sell-services' ), $days_left ) . ')</span>';
						}
					} else {
						esc_html_e( 'Not set', 'wp-sell-services' );
					}
					?>
				</span>
			</div>

			<div class="wpss-order-detail">
				<label><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></label>
				<span class="wpss-order-total"><?php echo esc_html( wpss_format_price( $order->get_total() ) ); ?></span>
			</div>

			<?php if ( $wc_order ) : ?>
				<div class="wpss-order-detail">
					<label><?php esc_html_e( 'WooCommerce Order', 'wp-sell-services' ); ?></label>
					<span>
						<a href="<?php echo esc_url( $wc_order->get_edit_order_url() ); ?>" target="_blank">
							#<?php echo esc_html( $wc_order->get_id() ); ?>
						</a>
					</span>
				</div>
			<?php endif; ?>
		</div>

		<hr>

		<div class="wpss-order-parties">
			<div class="wpss-party wpss-buyer">
				<h4><?php esc_html_e( 'Buyer', 'wp-sell-services' ); ?></h4>
				<?php if ( $buyer ) : ?>
					<div class="wpss-party-info">
						<?php echo get_avatar( $buyer->ID, 48 ); ?>
						<div class="wpss-party-details">
							<strong>
								<a href="<?php echo esc_url( get_edit_user_link( $buyer->ID ) ); ?>">
									<?php echo esc_html( $buyer->display_name ); ?>
								</a>
							</strong>
							<span><?php echo esc_html( $buyer->user_email ); ?></span>
						</div>
					</div>
				<?php else : ?>
					<p><?php esc_html_e( 'User not found', 'wp-sell-services' ); ?></p>
				<?php endif; ?>
			</div>

			<div class="wpss-party wpss-vendor">
				<h4><?php esc_html_e( 'Vendor', 'wp-sell-services' ); ?></h4>
				<?php if ( $vendor ) : ?>
					<div class="wpss-party-info">
						<?php echo get_avatar( $vendor->ID, 48 ); ?>
						<div class="wpss-party-details">
							<strong>
								<a href="<?php echo esc_url( get_edit_user_link( $vendor->ID ) ); ?>">
									<?php echo esc_html( $vendor->display_name ); ?>
								</a>
							</strong>
							<span><?php echo esc_html( $vendor->user_email ); ?></span>
						</div>
					</div>
				<?php else : ?>
					<p><?php esc_html_e( 'User not found', 'wp-sell-services' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<hr>

		<div class="wpss-order-service">
			<h4><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></h4>
			<?php if ( $service ) : ?>
				<div class="wpss-service-info">
					<?php if ( has_post_thumbnail( $service->ID ) ) : ?>
						<div class="wpss-service-thumb">
							<?php echo get_the_post_thumbnail( $service->ID, 'thumbnail' ); ?>
						</div>
					<?php endif; ?>
					<div class="wpss-service-details">
						<strong>
							<a href="<?php echo esc_url( get_edit_post_link( $service->ID ) ); ?>">
								<?php echo esc_html( $service->post_title ); ?>
							</a>
						</strong>
						<span>
							<?php
							/* translators: %s: package name */
							printf( esc_html__( 'Package: %s', 'wp-sell-services' ), esc_html( $order->get_package_name() ) );
							?>
						</span>
					</div>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Service not found', 'wp-sell-services' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render order items metabox.
	 *
	 * @return void
	 */
	public function render_items_metabox(): void {
		$order = $this->get_order();

		if ( ! $order ) {
			return;
		}

		$items = $order->get_items();
		?>
		<table class="wpss-order-items-table widefat">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Item', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Quantity', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Price', 'wp-sell-services' ); ?></th>
					<th><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $items ) ) : ?>
					<?php foreach ( $items as $item ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $item['name'] ?? '' ); ?></strong>
								<?php if ( ! empty( $item['description'] ) ) : ?>
									<p class="item-description"><?php echo esc_html( $item['description'] ); ?></p>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $item['quantity'] ?? 1 ); ?></td>
							<td><?php echo esc_html( wpss_format_price( (float) ( $item['price'] ?? 0 ) ) ); ?></td>
							<td><?php echo esc_html( wpss_format_price( (float) ( $item['total'] ?? 0 ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="4"><?php esc_html_e( 'No items found.', 'wp-sell-services' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
			<tfoot>
				<tr>
					<th colspan="3" style="text-align: right;"><?php esc_html_e( 'Subtotal', 'wp-sell-services' ); ?></th>
					<th><?php echo esc_html( wpss_format_price( $order->get_subtotal() ) ); ?></th>
				</tr>
				<?php if ( $order->get_discount() > 0 ) : ?>
					<tr>
						<th colspan="3" style="text-align: right;"><?php esc_html_e( 'Discount', 'wp-sell-services' ); ?></th>
						<th>-<?php echo esc_html( wpss_format_price( $order->get_discount() ) ); ?></th>
					</tr>
				<?php endif; ?>
				<?php if ( $order->get_fee() > 0 ) : ?>
					<tr>
						<th colspan="3" style="text-align: right;"><?php esc_html_e( 'Fees', 'wp-sell-services' ); ?></th>
						<th><?php echo esc_html( wpss_format_price( $order->get_fee() ) ); ?></th>
					</tr>
				<?php endif; ?>
				<tr class="wpss-order-total-row">
					<th colspan="3" style="text-align: right;"><?php esc_html_e( 'Total', 'wp-sell-services' ); ?></th>
					<th><?php echo esc_html( wpss_format_price( $order->get_total() ) ); ?></th>
				</tr>
			</tfoot>
		</table>
		<?php
	}

	/**
	 * Render requirements metabox.
	 *
	 * @return void
	 */
	public function render_requirements_metabox(): void {
		$order = $this->get_order();

		if ( ! $order ) {
			return;
		}

		$requirements = $order->get_requirements();
		$service      = $order->get_service();

		// Get service requirement fields.
		$service_fields = array();
		if ( $service ) {
			$service_fields = get_post_meta( $service->id, '_wpss_requirements', true );
			if ( ! is_array( $service_fields ) ) {
				$service_fields = array();
			}
		}
		?>
		<?php if ( ! empty( $requirements ) ) : ?>
			<div class="wpss-requirements-list">
				<?php foreach ( $requirements as $field => $value ) : ?>
					<div class="wpss-requirement-item">
						<label><?php echo esc_html( ucwords( str_replace( '_', ' ', $field ) ) ); ?></label>
						<div class="wpss-requirement-value">
							<?php
							if ( is_array( $value ) ) {
								echo esc_html( implode( ', ', $value ) );
							} elseif ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
								echo '<a href="' . esc_url( $value ) . '" target="_blank">' . esc_html( $value ) . '</a>';
							} else {
								echo wp_kses_post( wpautop( $value ) );
							}
							?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php elseif ( ! empty( $service_fields ) ) : ?>
			<div class="wpss-admin-requirements-form">
				<p class="description">
					<?php esc_html_e( 'No requirements submitted yet. You can fill in requirements on behalf of the buyer.', 'wp-sell-services' ); ?>
				</p>
				<form id="wpss-admin-requirements-form" class="wpss-requirements-form">
					<input type="hidden" name="order_id" value="<?php echo esc_attr( $order->get_id() ); ?>">
					<?php foreach ( $service_fields as $index => $field ) : ?>
						<?php
						$field_key   = $field['label'] ?? $field['question'] ?? "field_{$index}";
						$field_label = $field['label'] ?? $field['question'] ?? "Field {$index}";
						$field_type  = $field['type'] ?? 'text';
						$required    = ! empty( $field['required'] );
						$choices     = $field['choices'] ?? '';
						?>
						<div class="wpss-form-field wpss-field-<?php echo esc_attr( $field_type ); ?>">
							<label for="wpss-req-<?php echo esc_attr( $index ); ?>">
								<?php echo esc_html( $field_label ); ?>
								<?php if ( $required ) : ?>
									<span class="required">*</span>
								<?php endif; ?>
							</label>

							<?php if ( 'textarea' === $field_type ) : ?>
								<textarea
									id="wpss-req-<?php echo esc_attr( $index ); ?>"
									name="field_data[<?php echo esc_attr( $field_key ); ?>]"
									rows="4"
									<?php echo $required ? 'required' : ''; ?>
								></textarea>

							<?php elseif ( 'select' === $field_type ) : ?>
								<select
									id="wpss-req-<?php echo esc_attr( $index ); ?>"
									name="field_data[<?php echo esc_attr( $field_key ); ?>]"
									<?php echo $required ? 'required' : ''; ?>
								>
									<option value=""><?php esc_html_e( 'Select...', 'wp-sell-services' ); ?></option>
									<?php
									$choice_list = is_array( $choices ) ? $choices : array_map( 'trim', explode( ',', $choices ) );
									foreach ( $choice_list as $choice ) :
										?>
										<option value="<?php echo esc_attr( $choice ); ?>">
											<?php echo esc_html( $choice ); ?>
										</option>
									<?php endforeach; ?>
								</select>

							<?php elseif ( 'radio' === $field_type ) : ?>
								<div class="wpss-radio-group">
									<?php
									$choice_list = is_array( $choices ) ? $choices : array_map( 'trim', explode( ',', $choices ) );
									foreach ( $choice_list as $ci => $choice ) :
										?>
										<label>
											<input
												type="radio"
												name="field_data[<?php echo esc_attr( $field_key ); ?>]"
												value="<?php echo esc_attr( $choice ); ?>"
												<?php echo ( $required && 0 === $ci ) ? '' : ''; ?>
											>
											<?php echo esc_html( $choice ); ?>
										</label>
									<?php endforeach; ?>
								</div>

							<?php elseif ( 'checkbox' === $field_type ) : ?>
								<?php if ( ! empty( $choices ) ) : ?>
									<div class="wpss-checkbox-group">
										<?php
										$choice_list = is_array( $choices ) ? $choices : array_map( 'trim', explode( ',', $choices ) );
										foreach ( $choice_list as $choice ) :
											?>
											<label>
												<input
													type="checkbox"
													name="field_data[<?php echo esc_attr( $field_key ); ?>][]"
													value="<?php echo esc_attr( $choice ); ?>"
												>
												<?php echo esc_html( $choice ); ?>
											</label>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<label>
										<input
											type="checkbox"
											name="field_data[<?php echo esc_attr( $field_key ); ?>]"
											value="1"
										>
										<?php esc_html_e( 'Yes', 'wp-sell-services' ); ?>
									</label>
								<?php endif; ?>

							<?php elseif ( 'number' === $field_type ) : ?>
								<input
									type="number"
									id="wpss-req-<?php echo esc_attr( $index ); ?>"
									name="field_data[<?php echo esc_attr( $field_key ); ?>]"
									<?php echo $required ? 'required' : ''; ?>
								>

							<?php else : ?>
								<input
									type="text"
									id="wpss-req-<?php echo esc_attr( $index ); ?>"
									name="field_data[<?php echo esc_attr( $field_key ); ?>]"
									<?php echo $required ? 'required' : ''; ?>
								>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<p class="wpss-form-actions">
						<button type="submit" class="button button-primary wpss-submit-requirements" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
							<?php esc_html_e( 'Save Requirements', 'wp-sell-services' ); ?>
						</button>
						<span class="spinner"></span>
					</p>

					<div class="wpss-form-errors" style="display: none;"></div>
				</form>
			</div>
		<?php else : ?>
			<p class="wpss-no-data"><?php esc_html_e( 'This service has no requirements defined.', 'wp-sell-services' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render deliveries metabox.
	 *
	 * @return void
	 */
	public function render_deliveries_metabox(): void {
		$order = $this->get_order();

		if ( ! $order ) {
			return;
		}

		$deliveries = $this->delivery_repo->find_by_order( $order->get_id() );
		?>
		<?php if ( ! empty( $deliveries ) ) : ?>
			<div class="wpss-deliveries-list">
				<?php foreach ( $deliveries as $delivery ) : ?>
					<div class="wpss-delivery-item wpss-delivery-<?php echo esc_attr( $delivery->status ); ?>">
						<div class="wpss-delivery-header">
							<span class="wpss-delivery-number">
								<?php
								/* translators: %d: delivery number */
								printf( esc_html__( 'Delivery #%d', 'wp-sell-services' ), $delivery->id );
								?>
							</span>
							<span class="wpss-delivery-status wpss-status-<?php echo esc_attr( $delivery->status ); ?>">
								<?php echo esc_html( ucfirst( $delivery->status ) ); ?>
							</span>
							<span class="wpss-delivery-date">
								<?php echo esc_html( wp_date( 'M j, Y g:i a', strtotime( $delivery->created_at ) ) ); ?>
							</span>
						</div>
						<div class="wpss-delivery-content">
							<?php echo wp_kses_post( wpautop( $delivery->description ) ); ?>
						</div>
						<?php if ( ! empty( $delivery->files ) ) : ?>
							<div class="wpss-delivery-files">
								<strong><?php esc_html_e( 'Files:', 'wp-sell-services' ); ?></strong>
								<ul>
									<?php
									$files = is_string( $delivery->files ) ? json_decode( $delivery->files, true ) : $delivery->files;
									if ( is_array( $files ) ) :
										foreach ( $files as $file ) :
											?>
											<li>
												<a href="<?php echo esc_url( $file['url'] ?? '' ); ?>" target="_blank">
													<?php echo esc_html( $file['name'] ?? 'File' ); ?>
												</a>
											</li>
											<?php
										endforeach;
									endif;
									?>
								</ul>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p class="wpss-no-data"><?php esc_html_e( 'No deliveries yet.', 'wp-sell-services' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render messages metabox.
	 *
	 * @return void
	 */
	public function render_messages_metabox(): void {
		$order = $this->get_order();

		if ( ! $order ) {
			return;
		}

		$conversation = $this->conversation_repo->find_by_order( $order->get_id() );
		?>
		<?php if ( $conversation ) : ?>
			<div class="wpss-messages-container">
				<?php
				$messages = $this->conversation_repo->get_messages( $conversation->id, 20 );

				if ( ! empty( $messages ) ) :
					foreach ( $messages as $message ) :
						$sender  = get_userdata( $message->sender_id );
						$is_own  = $message->sender_id === get_current_user_id();
						$is_buyer = $message->sender_id === $order->get_buyer_id();
						?>
						<div class="wpss-message <?php echo $is_own ? 'wpss-message-own' : ''; ?>">
							<div class="wpss-message-avatar">
								<?php echo get_avatar( $message->sender_id, 32 ); ?>
							</div>
							<div class="wpss-message-body">
								<div class="wpss-message-header">
									<strong class="wpss-message-sender">
										<?php echo esc_html( $sender ? $sender->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?>
									</strong>
									<span class="wpss-message-role">
										(<?php echo $is_buyer ? esc_html__( 'Buyer', 'wp-sell-services' ) : esc_html__( 'Vendor', 'wp-sell-services' ); ?>)
									</span>
									<span class="wpss-message-time">
										<?php echo esc_html( wp_date( 'M j, g:i a', strtotime( $message->created_at ) ) ); ?>
									</span>
								</div>
								<div class="wpss-message-content">
									<?php echo wp_kses_post( wpautop( $message->content ) ); ?>
								</div>
							</div>
						</div>
						<?php
					endforeach;
				else :
					?>
					<p class="wpss-no-data"><?php esc_html_e( 'No messages yet.', 'wp-sell-services' ); ?></p>
				<?php endif; ?>
			</div>
			<p class="wpss-view-all">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpss-conversations&id=' . $conversation->id ) ); ?>">
					<?php esc_html_e( 'View Full Conversation', 'wp-sell-services' ); ?>
				</a>
			</p>
		<?php else : ?>
			<p class="wpss-no-data"><?php esc_html_e( 'No conversation started.', 'wp-sell-services' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render actions metabox.
	 *
	 * @return void
	 */
	public function render_actions_metabox(): void {
		$order = $this->get_order();

		if ( ! $order ) {
			return;
		}

		$status           = $order->get_status();
		$available_actions = $this->get_available_actions( $status );
		?>
		<div class="wpss-order-actions">
			<div class="wpss-action-group">
				<label for="wpss-order-status"><?php esc_html_e( 'Change Status', 'wp-sell-services' ); ?></label>
				<select id="wpss-order-status" name="order_status" class="wpss-status-select">
					<option value=""><?php esc_html_e( 'Select status...', 'wp-sell-services' ); ?></option>
					<?php foreach ( wpss_get_order_statuses() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $status, $value ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button wpss-update-status" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
					<?php esc_html_e( 'Update', 'wp-sell-services' ); ?>
				</button>
			</div>

			<?php if ( ! empty( $available_actions ) ) : ?>
				<hr>
				<div class="wpss-quick-actions">
					<label><?php esc_html_e( 'Quick Actions', 'wp-sell-services' ); ?></label>
					<?php foreach ( $available_actions as $action => $label ) : ?>
						<button type="button"
								class="button wpss-quick-action"
								data-action="<?php echo esc_attr( $action ); ?>"
								data-order="<?php echo esc_attr( $order->get_id() ); ?>">
							<?php echo esc_html( $label ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<hr>

			<div class="wpss-admin-actions">
				<label><?php esc_html_e( 'Admin Actions', 'wp-sell-services' ); ?></label>

				<?php if ( $order->get_wc_order_id() ) : ?>
					<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order->get_wc_order_id() . '&action=edit' ) ); ?>"
					   class="button" target="_blank">
						<?php esc_html_e( 'View WC Order', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>

				<button type="button" class="button wpss-resend-notification" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
					<?php esc_html_e( 'Resend Notifications', 'wp-sell-services' ); ?>
				</button>

				<?php if ( in_array( $status, array( 'completed', 'cancelled' ), true ) ) : ?>
					<button type="button" class="button button-link-delete wpss-process-refund" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
						<?php esc_html_e( 'Process Refund', 'wp-sell-services' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<?php
			/**
			 * Fires in the admin order actions metabox for gateway-specific actions.
			 *
			 * @param ServiceOrder $order  The order object.
			 * @param string       $status Current order status.
			 */
			do_action( 'wpss_admin_order_actions', $order, $status );
			?>
		</div>
		<?php
	}

	/**
	 * Render notes metabox.
	 *
	 * @return void
	 */
	public function render_notes_metabox(): void {
		$order = $this->get_order();

		if ( ! $order ) {
			return;
		}

		$notes = $order->get_admin_notes();
		?>
		<div class="wpss-admin-notes">
			<?php if ( ! empty( $notes ) ) : ?>
				<ul class="wpss-notes-list">
					<?php foreach ( $notes as $note ) : ?>
						<li class="wpss-note">
							<div class="wpss-note-content"><?php echo wp_kses_post( $note['content'] ); ?></div>
							<div class="wpss-note-meta">
								<?php
								$author = get_userdata( $note['author_id'] ?? 0 );
								echo esc_html( $author ? $author->display_name : __( 'Admin', 'wp-sell-services' ) );
								echo ' - ';
								echo esc_html( wp_date( 'M j, g:i a', strtotime( $note['created_at'] ) ) );
								?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<div class="wpss-add-note">
				<textarea id="wpss-new-note" rows="3" placeholder="<?php esc_attr_e( 'Add a note...', 'wp-sell-services' ); ?>"></textarea>
				<button type="button" class="button wpss-add-note-btn" data-order="<?php echo esc_attr( $order->get_id() ); ?>">
					<?php esc_html_e( 'Add Note', 'wp-sell-services' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render timeline metabox.
	 *
	 * @return void
	 */
	public function render_timeline_metabox(): void {
		$order = $this->get_order();

		if ( ! $order ) {
			return;
		}

		$history = $order->get_status_history();
		?>
		<div class="wpss-order-timeline">
			<?php if ( ! empty( $history ) ) : ?>
				<ul class="wpss-timeline-list">
					<?php foreach ( array_reverse( $history ) as $event ) : ?>
						<li class="wpss-timeline-item">
							<span class="wpss-timeline-dot wpss-status-<?php echo esc_attr( $event['status'] ); ?>"></span>
							<div class="wpss-timeline-content">
								<strong><?php echo esc_html( wpss_get_order_status_label( $event['status'] ) ); ?></strong>
								<span class="wpss-timeline-date">
									<?php echo esc_html( wp_date( 'M j, g:i a', strtotime( $event['timestamp'] ) ) ); ?>
								</span>
								<?php if ( ! empty( $event['note'] ) ) : ?>
									<p class="wpss-timeline-note"><?php echo esc_html( $event['note'] ); ?></p>
								<?php endif; ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="wpss-no-data"><?php esc_html_e( 'No history available.', 'wp-sell-services' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get available actions for status.
	 *
	 * @param string $status Current status.
	 * @return array Available actions.
	 */
	private function get_available_actions( string $status ): array {
		$actions = array();

		switch ( $status ) {
			case 'pending_requirements':
				$actions['start'] = __( 'Force Start', 'wp-sell-services' );
				break;

			case 'in_progress':
				$actions['extend'] = __( 'Extend Deadline', 'wp-sell-services' );
				break;

			case 'delivered':
				$actions['complete'] = __( 'Force Complete', 'wp-sell-services' );
				$actions['revision'] = __( 'Request Revision', 'wp-sell-services' );
				break;

			case 'disputed':
				$actions['resolve_buyer']  = __( 'Resolve for Buyer', 'wp-sell-services' );
				$actions['resolve_vendor'] = __( 'Resolve for Vendor', 'wp-sell-services' );
				break;
		}

		// Cancel is always available for non-final statuses.
		if ( ! in_array( $status, array( 'completed', 'cancelled', 'refunded' ), true ) ) {
			$actions['cancel'] = __( 'Cancel Order', 'wp-sell-services' );
		}

		return $actions;
	}

	/**
	 * AJAX: Update order status.
	 *
	 * @return void
	 */
	public function ajax_update_status(): void {
		check_ajax_referer( 'wpss_order_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'wpss_manage_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$status   = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $order_id || ! $status ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		// Non-admin users must be the vendor on the order.
		if ( ! current_user_can( 'manage_options' ) ) {
			$order_check = $this->order_service->get( $order_id );
			if ( ! $order_check || (int) $order_check->vendor_id !== get_current_user_id() ) {
				wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
			}
		}

		// Use OrderService instead of repository to ensure hooks fire.
		// This triggers wpss_order_status_changed and wpss_order_status_{status} hooks
		// which are needed for commission recording, notifications, etc.
		$result = $this->order_service->update_status( $order_id, $status );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Status updated successfully.', 'wp-sell-services' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update status.', 'wp-sell-services' ) ) );
		}
	}

	/**
	 * AJAX: Submit requirements on behalf of buyer.
	 *
	 * @return void
	 */
	public function ajax_submit_requirements(): void {
		check_ajax_referer( 'wpss_order_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$order_id   = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$field_data = isset( $_POST['field_data'] ) ? array_map( 'sanitize_textarea_field', wp_unslash( (array) $_POST['field_data'] ) ) : array();

		if ( ! $order_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid order ID.', 'wp-sell-services' ) ) );
		}

		$row = $this->order_repo->find( $order_id );

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-sell-services' ) ) );
		}

		$order = ServiceOrder::from_db( $row );

		// Get service requirements fields.
		$service = $order->get_service();
		if ( ! $service ) {
			wp_send_json_error( array( 'message' => __( 'Service not found.', 'wp-sell-services' ) ) );
		}

		$requirements_service = new \WPSellServices\Services\RequirementsService();
		$fields               = $requirements_service->get_service_fields( $service->id );

		if ( empty( $fields ) ) {
			wp_send_json_error( array( 'message' => __( 'This service has no requirements defined.', 'wp-sell-services' ) ) );
		}

		// Validate required fields.
		$errors = array();
		foreach ( $fields as $field ) {
			$field_key = $field['label'] ?? $field['question'] ?? '';
			$value     = $field_data[ $field_key ] ?? '';
			$required  = ! empty( $field['required'] );

			if ( $required && '' === $value ) {
				$errors[ $field_key ] = sprintf(
					/* translators: %s: field label */
					__( '%s is required.', 'wp-sell-services' ),
					$field_key
				);
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please fix the following errors:', 'wp-sell-services' ),
					'errors'  => $errors,
				)
			);
		}

		// Save requirements directly to database (bypass status check for admin).
		global $wpdb;
		$table = $wpdb->prefix . 'wpss_order_requirements';

		// Delete existing requirements if any.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $table, array( 'order_id' => $order_id ) );

		// Insert new requirements.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert(
			$table,
			array(
				'order_id'     => $order_id,
				'field_data'   => wp_json_encode( $field_data ),
				'attachments'  => wp_json_encode( array() ),
				'submitted_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( ! $result ) {
			wp_send_json_error( array( 'message' => __( 'Failed to save requirements.', 'wp-sell-services' ) ) );
		}

		// Transition order status if needed (uses OrderService for hooks + timestamps).
		$status = $order->get_status();
		if ( 'pending_requirements' === $status ) {
			$this->order_service->update_status( $order_id, 'in_progress' );
		}

		/**
		 * Fires after admin submits requirements on behalf of buyer.
		 *
		 * @param int   $order_id   Order ID.
		 * @param array $field_data Submitted data.
		 */
		do_action( 'wpss_admin_requirements_submitted', $order_id, $field_data );

		wp_send_json_success(
			array(
				'message' => __( 'Requirements saved successfully.', 'wp-sell-services' ),
			)
		);
	}

	/**
	 * AJAX: Add admin note.
	 *
	 * @return void
	 */
	public function ajax_add_note(): void {
		check_ajax_referer( 'wpss_order_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-sell-services' ) ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

		if ( ! $order_id || ! $note ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'wp-sell-services' ) ) );
		}

		$row = $this->order_repo->find( $order_id );

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Order not found.', 'wp-sell-services' ) ) );
		}

		$order = ServiceOrder::from_db( $row );

		$notes   = $order->get_admin_notes() ?: array();
		$notes[] = array(
			'content'    => $note,
			'author_id'  => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
		);

		// Store admin notes in the meta JSON column (no admin_notes column exists).
		$meta                 = $order->meta;
		$meta['admin_notes']  = $notes;
		$this->order_repo->update( $order_id, array( 'meta' => wp_json_encode( $meta ) ) );

		wp_send_json_success(
			array(
				'message' => __( 'Note added successfully.', 'wp-sell-services' ),
				'note'    => array(
					'content'    => $note,
					'author'     => wp_get_current_user()->display_name,
					'created_at' => wp_date( 'M j, g:i a' ),
				),
			)
		);
	}
}
