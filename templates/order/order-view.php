<?php
/**
 * Template: Order View
 *
 * Displays a single order with all details in a single-column layout.
 * Uses CSS classes from orders.css design system.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int $order_id Order ID passed from parent template.
 *
 * Available Hooks:
 * - wpss_before_order_view
 * - wpss_order_view_header
 * - wpss_order_view_details
 * - wpss_order_view_actions
 * - wpss_order_view_sidebar
 * - wpss_after_order_view
 * - wpss_order_status_label (filter)
 * - wpss_order_actions (filter)
 */

use WPSellServices\Services\DeliveryService;

defined( 'ABSPATH' ) || exit;

if ( empty( $order_id ) ) {
	return;
}

// Enqueue orders styles.
wp_enqueue_style( 'wpss-orders', WPSS_PLUGIN_URL . 'assets/css/orders.css', array( 'wpss-design-system' ), WPSS_VERSION );

// Enqueue requirements form script.
wp_enqueue_script( 'wpss-requirements-form', WPSS_PLUGIN_URL . 'assets/js/requirements-form.js', array( 'jquery' ), WPSS_VERSION, true );
wp_localize_script(
	'wpss-requirements-form',
	'wpss_ajax',
	array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'i18n'     => array(
			'submit_error' => __( 'Failed to submit requirements.', 'wp-sell-services' ),
			'ajax_error'   => __( 'An error occurred. Please try again.', 'wp-sell-services' ),
		),
	)
);

// Localize REST API data for inline JS.
wp_enqueue_script( 'wp-api-fetch' );
wp_add_inline_script(
	'wp-api-fetch',
	'var wpssApi = ' . wp_json_encode( array(
		'root'  => esc_url_raw( rest_url() ),
		'nonce' => wp_create_nonce( 'wp_rest' ),
	) ) . ';',
	'before'
);

$order = wpss_get_order( $order_id );

if ( ! $order ) {
	echo '<div class="wpss-alert wpss-alert--error">' . esc_html__( 'Order not found.', 'wp-sell-services' ) . '</div>';
	return;
}

$user_id     = get_current_user_id();
$is_vendor   = (int) $order->vendor_id === $user_id;
$is_customer = (int) $order->customer_id === $user_id;
$service     = get_post( $order->service_id );
$vendor      = get_userdata( $order->vendor_id );
$customer    = get_userdata( $order->customer_id );

// Handle deleted users gracefully.
$vendor_name  = $vendor ? $vendor->display_name : __( 'Deleted User', 'wp-sell-services' );
$customer_name = $customer ? $customer->display_name : __( 'Deleted User', 'wp-sell-services' );

// Get deliveries via service layer.
$delivery_service = new DeliveryService();
$deliveries       = $delivery_service->get_order_deliveries( $order_id );

/**
 * Hook: wpss_before_order_view
 *
 * Fires before the order view content is displayed.
 *
 * @since 1.0.0
 *
 * @param object $order Order object.
 */
do_action( 'wpss_before_order_view', $order );
?>

<div class="wpss-order-view">
	<!-- Header with Back Button -->
	<div class="wpss-order-view__header">
		<a href="<?php echo esc_url( wpss_get_dashboard_url( 'orders' ) ); ?>" class="wpss-order-view__back">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
				<line x1="19" y1="12" x2="5" y2="12"></line>
				<polyline points="12 19 5 12 12 5"></polyline>
			</svg>
			<?php esc_html_e( 'Back to Orders', 'wp-sell-services' ); ?>
		</a>
	</div>

	<!-- Order Title & Status -->
	<div class="wpss-order-view__title-bar">
		<div class="wpss-order-view__title-info">
			<h1 class="wpss-order-view__title">
				<?php
				printf(
					/* translators: %s: order number */
					esc_html__( 'Order #%s', 'wp-sell-services' ),
					esc_html( $order->order_number )
				);
				?>
			</h1>
			<?php
			$status_label = wpss_get_order_status_label( $order->status );
			/**
			 * Filter: wpss_order_status_label
			 *
			 * Filters the display label for the order status.
			 *
			 * @since 1.0.0
			 *
			 * @param string $status_label The status label to display.
			 * @param string $status       The order status.
			 * @param object $order        Order object.
			 */
			$status_label = apply_filters( 'wpss_order_status_label', $status_label, $order->status, $order );
			?>
			<span class="wpss-badge wpss-badge--lg wpss-badge--status-<?php echo esc_attr( str_replace( '_', '-', $order->status ) ); ?>">
				<?php echo esc_html( $status_label ); ?>
			</span>
		</div>

		<?php
		/**
		 * Hook: wpss_order_view_header
		 *
		 * Fires after the order number and status are displayed.
		 *
		 * @since 1.0.0
		 *
		 * @param object $order Order object.
		 */
		do_action( 'wpss_order_view_header', $order );
		?>

		<?php if ( in_array( $order->status, array( 'pending', 'accepted', 'in_progress', 'pending_approval', 'pending_requirements', 'pending_payment', 'revision_requested', 'late', 'cancellation_requested' ), true ) ) : ?>
			<?php
			// Build actions array for filtering.
			$actions = array();

			if ( $is_vendor ) {
				if ( 'pending' === $order->status ) {
					$actions['accept'] = array(
						'label' => __( 'Accept Order', 'wp-sell-services' ),
						'class' => 'wpss-btn wpss-btn--success wpss-order-action',
						'attrs' => 'data-action="accept" data-order="' . esc_attr( $order_id ) . '"',
					);
					$actions['reject'] = array(
						'label' => __( 'Decline', 'wp-sell-services' ),
						'class' => 'wpss-btn wpss-btn--danger-outline wpss-order-action',
						'attrs' => 'data-action="reject" data-order="' . esc_attr( $order_id ) . '"',
					);
				}

				if ( in_array( $order->status, array( 'accepted', 'requirements_submitted' ), true ) ) {
					$actions['start'] = array(
						'label' => __( 'Start Working', 'wp-sell-services' ),
						'class' => 'wpss-btn wpss-btn--primary wpss-order-action',
						'attrs' => 'data-action="start" data-order="' . esc_attr( $order_id ) . '"',
					);
				}

				if ( in_array( $order->status, array( 'in_progress', 'revision_requested', 'late' ), true ) ) {
					$actions['deliver'] = array(
						'label' => 'revision_requested' === $order->status ? __( 'Deliver Revision', 'wp-sell-services' ) : __( 'Deliver Work', 'wp-sell-services' ),
						'class' => 'wpss-btn wpss-btn--success wpss-deliver-btn',
						'attrs' => 'data-order="' . esc_attr( $order_id ) . '"',
					);
				}
			}

			if ( $is_customer ) {
				// Pay Now button for unpaid orders (e.g., from accepted proposals).
				if ( 'pending_payment' === $order->status ) {
					$pay_url = add_query_arg( 'pay_order', $order_id, home_url( '/service-checkout/' . $order->service_id . '/' ) );
					$actions['pay'] = array(
						'label' => sprintf(
							/* translators: %s: formatted price */
							__( 'Pay %s', 'wp-sell-services' ),
							wpss_format_price( $order->total )
						),
						'class' => 'wpss-btn wpss-btn--success',
						'attrs' => 'onclick="window.location.href=\'' . esc_url( $pay_url ) . '\'" data-order="' . esc_attr( $order_id ) . '"',
					);
				}

				if ( 'pending_approval' === $order->status ) {
					$actions['complete'] = array(
						'label' => __( 'Accept & Complete', 'wp-sell-services' ),
						'class' => 'wpss-btn wpss-btn--success wpss-order-action',
						'attrs' => 'data-action="complete" data-order="' . esc_attr( $order_id ) . '"',
					);

					if ( $order->can_request_revision() ) {
						$remaining = $order->get_remaining_revisions();
						$rev_label = -1 === $remaining
							? __( 'Request Revision', 'wp-sell-services' )
							/* translators: %d: number of revisions remaining */
							: sprintf( __( 'Request Revision (%d left)', 'wp-sell-services' ), $remaining );

						$actions['revision'] = array(
							'label' => $rev_label,
							'class' => 'wpss-btn wpss-btn--secondary wpss-revision-btn',
							'attrs' => 'data-order="' . esc_attr( $order_id ) . '"',
						);
					}
				}

				if ( 'revision_requested' === $order->status ) {
					$actions['revision_notice'] = array(
						'label' => __( 'Waiting for Revised Delivery', 'wp-sell-services' ),
						'class' => 'wpss-btn wpss-btn--secondary wpss-btn--disabled',
						'attrs' => 'disabled="disabled" title="' . esc_attr__( 'The vendor is working on your requested revision.', 'wp-sell-services' ) . '"',
					);
				}

				if ( 'cancellation_requested' === $order->status ) {
					$actions['cancel_pending_notice'] = array(
						'label' => __( 'Cancellation Pending', 'wp-sell-services' ),
						'class' => 'wpss-btn wpss-btn--secondary wpss-btn--disabled',
						'attrs' => 'disabled="disabled" title="' . esc_attr__( 'Waiting for vendor response to your cancellation request.', 'wp-sell-services' ) . '"',
					);
				}

				// Immediate cancel for pre-work statuses.
				$buyer_cancel_statuses = array( 'pending_payment', 'pending_requirements', 'pending', 'accepted' );
				if ( in_array( $order->status, $buyer_cancel_statuses, true ) ) {
					$actions['cancel'] = array(
						'label' => __( 'Cancel Order', 'wp-sell-services' ),
						'class' => 'wpss-btn wpss-btn--secondary wpss-cancel-btn',
						'attrs' => 'data-order="' . esc_attr( $order_id ) . '"',
					);
				}

				// In-progress cancel (requires 24h window + no delivery).
				if ( 'in_progress' === $order->status && $order->started_at ) {
					$hours_since_start = ( time() - $order->started_at->getTimestamp() ) / 3600;
					$has_deliveries    = ! empty( $deliveries );

					if ( $hours_since_start <= 24 && ! $has_deliveries ) {
						$actions['cancel'] = array(
							'label' => __( 'Request Cancellation', 'wp-sell-services' ),
							'class' => 'wpss-btn wpss-btn--secondary wpss-cancel-btn',
							'attrs' => 'data-order="' . esc_attr( $order_id ) . '"',
						);
					}
				}
			}

			// Vendor: accept/reject cancellation.
			if ( $is_vendor && 'cancellation_requested' === $order->status ) {
				$actions['accept-cancellation'] = array(
					'label' => __( 'Accept Cancellation', 'wp-sell-services' ),
					'class' => 'wpss-btn wpss-btn--success wpss-order-action',
					'attrs' => 'data-action="accept-cancellation" data-order="' . esc_attr( $order_id ) . '"',
				);
				$actions['reject-cancellation'] = array(
					'label' => __( 'Dispute Cancellation', 'wp-sell-services' ),
					'class' => 'wpss-btn wpss-btn--danger-outline wpss-order-action',
					'attrs' => 'data-action="reject-cancellation" data-order="' . esc_attr( $order_id ) . '"',
				);
			}

			$order_settings    = get_option( 'wpss_orders', array() );
			$disputes_allowed  = ! empty( $order_settings['allow_disputes'] );
			if ( $disputes_allowed && in_array( $order->status, array( 'in_progress', 'pending_approval', 'revision_requested' ), true ) ) {
				$actions['dispute'] = array(
					'label' => __( 'Open Dispute', 'wp-sell-services' ),
					'class' => 'wpss-btn wpss-btn--danger-outline wpss-dispute-btn',
					'attrs' => 'data-order="' . esc_attr( $order_id ) . '"',
				);
			}

			/**
			 * Filter: wpss_order_actions
			 *
			 * Filters the array of order action buttons.
			 *
			 * @since 1.0.0
			 *
			 * @param array  $actions Array of action button data.
			 * @param object $order   Order object.
			 */
			$actions = apply_filters( 'wpss_order_actions', $actions, $order );
			?>

			<?php if ( ! empty( $actions ) ) : ?>
				<div class="wpss-order-view__actions">
					<?php
					/**
					 * Hook: wpss_order_view_actions
					 *
					 * Fires where action buttons are displayed.
					 *
					 * @since 1.0.0
					 *
					 * @param object $order Order object.
					 */
					do_action( 'wpss_order_view_actions', $order );

					foreach ( $actions as $action_key => $action_data ) :
						?>
						<button type="button" class="<?php echo esc_attr( $action_data['class'] ); ?>" <?php echo $action_data['attrs']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
							<?php echo esc_html( $action_data['label'] ); ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<!-- Order Summary Section -->
	<section class="wpss-order-section">
		<div class="wpss-order-section__header">
			<h2 class="wpss-order-section__title">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
					<polyline points="14 2 14 8 20 8"></polyline>
					<line x1="16" y1="13" x2="8" y2="13"></line>
					<line x1="16" y1="17" x2="8" y2="17"></line>
					<polyline points="10 9 9 9 8 9"></polyline>
				</svg>
				<?php esc_html_e( 'Order Summary', 'wp-sell-services' ); ?>
			</h2>
		</div>
		<div class="wpss-order-section__body">
			<div class="wpss-order-details-grid">
				<div class="wpss-order-detail-item">
					<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Order Number', 'wp-sell-services' ); ?></span>
					<span class="wpss-order-detail-item__value">#<?php echo esc_html( $order->order_number ); ?></span>
				</div>
				<div class="wpss-order-detail-item">
					<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Order Date', 'wp-sell-services' ); ?></span>
					<span class="wpss-order-detail-item__value"><?php echo esc_html( $order->created_at ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $order->created_at->getTimestamp() ) : '—' ); ?></span>
				</div>
				<?php if ( $order->delivery_deadline ) : ?>
					<div class="wpss-order-detail-item">
						<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Due Date', 'wp-sell-services' ); ?></span>
						<span class="wpss-order-detail-item__value"><?php echo esc_html( wp_date( get_option( 'date_format' ), $order->delivery_deadline->getTimestamp() ) ); ?></span>
					</div>
				<?php endif; ?>
				<div class="wpss-order-detail-item wpss-order-detail-item--highlight">
					<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Total Amount', 'wp-sell-services' ); ?></span>
					<span class="wpss-order-detail-item__value"><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></span>
				</div>
			</div>
		</div>
	</section>

	<?php
	/**
	 * Hook: wpss_order_view_details
	 *
	 * Fires after the order details table is displayed.
	 *
	 * @since 1.0.0
	 *
	 * @param object $order Order object.
	 */
	do_action( 'wpss_order_view_details', $order );
	?>

	<!-- Service & Seller Section -->
	<section class="wpss-order-section">
		<div class="wpss-order-section__header">
			<h2 class="wpss-order-section__title">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
					<circle cx="8.5" cy="8.5" r="1.5"></circle>
					<polyline points="21 15 16 10 5 21"></polyline>
				</svg>
				<?php esc_html_e( 'Service Details', 'wp-sell-services' ); ?>
			</h2>
		</div>
		<div class="wpss-order-section__body">
			<div class="wpss-service-info">
				<?php if ( $service && has_post_thumbnail( $service->ID ) ) : ?>
					<img src="<?php echo esc_url( get_the_post_thumbnail_url( $service->ID, 'medium' ) ); ?>"
						alt="<?php echo esc_attr( $service->post_title ); ?>"
						class="wpss-service-info__image">
				<?php endif; ?>
				<div class="wpss-service-info__content">
					<h3 class="wpss-service-info__title">
						<?php if ( $service ) : ?>
							<a href="<?php echo esc_url( get_permalink( $service->ID ) ); ?>">
								<?php echo esc_html( $service->post_title ); ?>
							</a>
						<?php else : ?>
							<?php esc_html_e( 'Deleted Service', 'wp-sell-services' ); ?>
						<?php endif; ?>
					</h3>
					<p class="wpss-service-info__price">
						<?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?>
					</p>
				</div>
			</div>

			<!-- Seller/Buyer Info -->
			<div class="wpss-party-info">
				<?php
				$other_party      = $is_vendor ? $customer : $vendor;
				$other_party_name = $is_vendor ? $customer_name : $vendor_name;
				$other_party_id   = $is_vendor ? $order->customer_id : $order->vendor_id;
				?>
				<div class="wpss-party-info__card">
					<img src="<?php echo esc_url( get_avatar_url( $other_party_id, array( 'size' => 64 ) ) ); ?>"
						alt="<?php echo esc_attr( $other_party_name ); ?>"
						class="wpss-party-info__avatar">
					<div class="wpss-party-info__details">
						<span class="wpss-party-info__role">
							<?php echo $is_vendor ? esc_html__( 'Buyer', 'wp-sell-services' ) : esc_html__( 'Seller', 'wp-sell-services' ); ?>
						</span>
						<strong class="wpss-party-info__name"><?php echo esc_html( $other_party_name ); ?></strong>
						<?php if ( ! $is_vendor && $other_party ) : ?>
							<?php
							$vendor_rating = (float) get_user_meta( $other_party->ID, '_wpss_rating_average', true );
							$vendor_count  = (int) get_user_meta( $other_party->ID, '_wpss_rating_count', true );
							?>
							<?php if ( $vendor_count > 0 ) : ?>
								<div class="wpss-party-info__rating">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="wpss-star-icon">
										<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
									</svg>
									<?php echo esc_html( number_format( $vendor_rating, 1 ) ); ?>
									<span class="wpss-party-info__rating-count">(<?php echo esc_html( $vendor_count ); ?> <?php esc_html_e( 'reviews', 'wp-sell-services' ); ?>)</span>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</section>

	<?php
	// Get service requirements for pending_requirements status.
	$service_requirements  = array();
	$submitted_data        = array();
	$submitted_attachments = array();
	$submitted_at          = null;
	if ( $service ) {
		$service_requirements = get_post_meta( $service->ID, '_wpss_requirements', true );
		if ( ! is_array( $service_requirements ) ) {
			$service_requirements = array();
		}
	}

	// Get submitted requirements from database.
	global $wpdb;
	$requirements_table = $wpdb->prefix . 'wpss_order_requirements';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$submitted_row = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$requirements_table} WHERE order_id = %d ORDER BY id DESC LIMIT 1",
			$order_id
		)
	);
	if ( $submitted_row ) {
		$submitted_data        = json_decode( $submitted_row->field_data, true ) ?: array();
		$submitted_attachments = json_decode( $submitted_row->attachments, true ) ?: array();
		$submitted_at          = $submitted_row->submitted_at ?? null;
	}
	$has_submitted_requirements = ! empty( $submitted_data ) || ! empty( $submitted_attachments );
	$service_has_requirements   = ! empty( $service_requirements );

	// Determine what requirements UI to show:
	// 1. FORM: Status is pending_requirements + user is customer + service has requirements + not yet submitted
	// 2. READ-ONLY VIEW: Requirements have been submitted (show to both vendor and customer)
	// 3. NOT PROVIDED: Service has requirements but none submitted and order is past pending_requirements
	// 4. NO REQUIREMENTS: Service has no requirements defined
	$show_requirements_form     = 'pending_requirements' === $order->status && $is_customer && $service_has_requirements && ! $has_submitted_requirements;
	$show_submitted_readonly    = $has_submitted_requirements && ( $is_vendor || $is_customer );
	$show_not_provided_notice   = ! $has_submitted_requirements && $service_has_requirements && in_array( $order->status, array( 'in_progress', 'pending_approval', 'completed', 'delivered', 'late', 'revision_requested' ), true );
	$show_no_requirements_msg   = ! $service_has_requirements && in_array( $order->status, array( 'in_progress', 'pending_approval', 'completed', 'delivered', 'late', 'revision_requested' ), true );

	// Allow late requirements submission if enabled in settings and order is in_progress without requirements.
	$allow_late_submission      = apply_filters( 'wpss_allow_late_requirements_submission', false );
	$show_late_requirements_form = $allow_late_submission && 'in_progress' === $order->status && $is_customer && $service_has_requirements && ! $has_submitted_requirements;
	?>

	<!-- Requirements Section (for pending_requirements status OR late submission) -->
	<?php if ( $show_requirements_form || $show_late_requirements_form ) : ?>
		<section class="wpss-order-section wpss-order-section--requirements">
			<div class="wpss-order-section__header">
				<h2 class="wpss-order-section__title">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M9 11l3 3L22 4"/>
						<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
					</svg>
					<?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?>
				</h2>
			</div>
			<div class="wpss-order-section__body">
				<?php if ( $show_late_requirements_form ) : ?>
					<div class="wpss-alert wpss-alert--warning" style="margin-bottom: 1.5rem;">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
							<line x1="12" y1="9" x2="12" y2="13"/>
							<line x1="12" y1="17" x2="12.01" y2="17"/>
						</svg>
						<p><?php esc_html_e( 'Work has already started, but you can still submit requirements to help the seller complete your order.', 'wp-sell-services' ); ?></p>
					</div>
				<?php else : ?>
					<div class="wpss-alert wpss-alert--info" style="margin-bottom: 1.5rem;">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<circle cx="12" cy="12" r="10"/>
							<line x1="12" y1="16" x2="12" y2="12"/>
							<line x1="12" y1="8" x2="12.01" y2="8"/>
						</svg>
						<p><?php esc_html_e( 'Please provide the following information so the seller can start working on your order.', 'wp-sell-services' ); ?></p>
					</div>
				<?php endif; ?>

				<form id="wpss-requirements-form" class="wpss-requirements-form" enctype="multipart/form-data">
					<?php wp_nonce_field( 'wpss_submit_requirements', 'wpss_requirements_nonce' ); ?>
					<input type="hidden" name="action" value="wpss_submit_requirements">
					<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">
					<?php if ( $show_late_requirements_form ) : ?>
						<input type="hidden" name="late_submission" value="1">
					<?php endif; ?>

					<?php foreach ( $service_requirements as $index => $requirement ) : ?>
						<?php
						$question    = $requirement['question'] ?? '';
						$type        = $requirement['type'] ?? 'textarea';
						$is_required = ! empty( $requirement['required'] );
						$field_name  = 'requirements[' . $index . ']';
						$field_id    = 'requirement-' . $index;
						?>
						<div class="wpss-form-group wpss-requirements-form__field" data-index="<?php echo esc_attr( $index ); ?>">
							<label for="<?php echo esc_attr( $field_id ); ?>" class="wpss-label wpss-requirements-form__label">
								<?php echo esc_html( $question ); ?>
								<?php if ( $is_required ) : ?>
									<span class="wpss-required">*</span>
								<?php endif; ?>
							</label>

							<?php if ( 'file' === $type ) : ?>
								<div class="wpss-file-upload wpss-requirements-form__upload" data-max-files="1">
									<input type="file"
											name="<?php echo esc_attr( $field_name ); ?>"
											id="<?php echo esc_attr( $field_id ); ?>"
											class="wpss-file-input wpss-requirements-form__upload-input"
											<?php echo $is_required ? 'required' : ''; ?>>
									<label for="<?php echo esc_attr( $field_id ); ?>" class="wpss-file-upload__label wpss-requirements-form__upload-label">
										<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
											<polyline points="17 8 12 3 7 8"/>
											<line x1="12" y1="3" x2="12" y2="15"/>
										</svg>
										<span class="wpss-file-upload__text"><?php esc_html_e( 'Choose a file or drag it here', 'wp-sell-services' ); ?></span>
									</label>
									<div class="wpss-requirements-form__file-list"></div>
								</div>
							<?php elseif ( 'text' === $type ) : ?>
								<input type="text"
										name="<?php echo esc_attr( $field_name ); ?>"
										id="<?php echo esc_attr( $field_id ); ?>"
										class="wpss-input wpss-requirements-form__input"
										<?php echo $is_required ? 'required' : ''; ?>>
							<?php else : ?>
								<textarea name="<?php echo esc_attr( $field_name ); ?>"
											id="<?php echo esc_attr( $field_id ); ?>"
											class="wpss-textarea wpss-requirements-form__textarea"
											rows="4"
											<?php echo $is_required ? 'required' : ''; ?>></textarea>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>

					<div class="wpss-form-actions">
						<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--lg wpss-requirements-form__submit-btn">
							<span class="wpss-requirements-form__submit-text">
								<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
									<polyline points="9 11 12 14 22 4"/>
									<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
								</svg>
								<?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?>
							</span>
							<span class="wpss-requirements-form__submit-loading" style="display: none;">
								<svg width="20" height="20" viewBox="0 0 24 24" class="wpss-spinner">
									<circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" opacity="0.25"/>
									<path d="M12 2a10 10 0 0 1 10 10" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round"/>
								</svg>
								<?php esc_html_e( 'Submitting...', 'wp-sell-services' ); ?>
							</span>
						</button>
					</div>
				</form>
			</div>
		</section>
	<?php endif; ?>

	<!-- Submitted Requirements (for vendor or customer after submission) -->
	<?php if ( $show_submitted_readonly ) : ?>
		<section class="wpss-order-section wpss-order-section--requirements-view">
			<div class="wpss-order-section__header">
				<h2 class="wpss-order-section__title">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M9 11l3 3L22 4"/>
						<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
					</svg>
					<?php esc_html_e( 'Order Requirements', 'wp-sell-services' ); ?>
				</h2>
				<?php if ( $submitted_at ) : ?>
					<span class="wpss-order-section__timestamp">
						<?php
						printf(
							/* translators: %s: submission date/time */
							esc_html__( 'Submitted %s', 'wp-sell-services' ),
							esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $submitted_at ) ) )
						);
						?>
					</span>
				<?php endif; ?>
			</div>
			<div class="wpss-order-section__body">
				<?php foreach ( $service_requirements as $index => $requirement ) : ?>
					<?php
					$question       = $requirement['question'] ?? '';
					$type           = $requirement['type'] ?? 'textarea';
					$field_key      = $question; // Data is keyed by question.
					$response_value = $submitted_data[ $field_key ] ?? '';

					// Find attachment for this field (if file type).
					$field_attachment = null;
					if ( 'file' === $type && ! empty( $submitted_attachments ) ) {
						foreach ( $submitted_attachments as $att ) {
							if ( isset( $att['key'] ) && $att['key'] === $field_key ) {
								$field_attachment = $att;
								break;
							}
						}
					}

					// Determine if text is long (for expand/collapse).
					$is_long_text = is_string( $response_value ) && strlen( $response_value ) > 300;
					?>
					<div class="wpss-requirement-view <?php echo $is_long_text ? 'wpss-requirement-view--expandable' : ''; ?>">
						<h4 class="wpss-requirement-view__question"><?php echo esc_html( $question ); ?></h4>
						<div class="wpss-requirement-view__answer <?php echo $is_long_text ? 'wpss-requirement-view__answer--collapsed' : ''; ?>">
							<?php if ( 'file' === $type && $field_attachment ) : ?>
								<?php
								// Check if it's an image for preview.
								$is_image = in_array( strtolower( pathinfo( $field_attachment['name'], PATHINFO_EXTENSION ) ), array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true );
								?>
								<?php if ( $is_image ) : ?>
									<div class="wpss-requirement-view__image-preview">
										<img src="<?php echo esc_url( $field_attachment['url'] ); ?>" alt="<?php echo esc_attr( $field_attachment['name'] ); ?>" class="wpss-requirement-view__thumbnail" loading="lazy">
									</div>
								<?php endif; ?>
								<a href="<?php echo esc_url( $field_attachment['url'] ); ?>" class="wpss-file-link" target="_blank" download>
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
										<polyline points="7 10 12 15 17 10"/>
										<line x1="12" y1="15" x2="12" y2="3"/>
									</svg>
									<?php echo esc_html( $field_attachment['name'] ); ?>
								</a>
							<?php elseif ( $response_value ) : ?>
								<div class="wpss-requirement-view__text-content">
									<?php echo wp_kses_post( wpautop( $response_value ) ); ?>
								</div>
								<?php if ( $is_long_text ) : ?>
									<button type="button" class="wpss-requirement-view__expand-btn" aria-expanded="false">
										<span class="wpss-expand-text"><?php esc_html_e( 'Show more', 'wp-sell-services' ); ?></span>
										<span class="wpss-collapse-text" style="display:none;"><?php esc_html_e( 'Show less', 'wp-sell-services' ); ?></span>
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="wpss-expand-icon">
											<polyline points="6 9 12 15 18 9"></polyline>
										</svg>
									</button>
								<?php endif; ?>
								<button type="button" class="wpss-requirement-view__copy-btn" data-copy-text="<?php echo esc_attr( $response_value ); ?>" title="<?php esc_attr_e( 'Copy to clipboard', 'wp-sell-services' ); ?>">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
										<rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
										<path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
									</svg>
								</button>
							<?php else : ?>
								<span class="wpss-text-muted"><?php esc_html_e( 'No response provided', 'wp-sell-services' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- Requirements Section (when service has requirements but none submitted) -->
	<?php if ( $show_not_provided_notice && ! $show_late_requirements_form ) : ?>
		<section class="wpss-order-section wpss-order-section--requirements-view">
			<div class="wpss-order-section__header">
				<h2 class="wpss-order-section__title">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M9 11l3 3L22 4"/>
						<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
					</svg>
					<?php esc_html_e( 'Order Requirements', 'wp-sell-services' ); ?>
				</h2>
			</div>
			<div class="wpss-order-section__body">
				<div class="wpss-notice wpss-notice--warning" style="margin-bottom: 20px; padding: 15px; background: #fff8e1; border-radius: 8px; border-left: 4px solid #f59e0b;">
					<p style="margin: 0; color: #92400e;">
						<strong><?php esc_html_e( 'Note:', 'wp-sell-services' ); ?></strong>
						<?php esc_html_e( 'No requirements were formally submitted for this order. Below are the questions the service requires:', 'wp-sell-services' ); ?>
					</p>
				</div>
				<?php foreach ( $service_requirements as $index => $requirement ) : ?>
					<?php
					$question = $requirement['question'] ?? '';
					$type     = $requirement['type'] ?? 'textarea';
					$required = ! empty( $requirement['required'] );
					?>
					<div class="wpss-requirement-view">
						<h4 class="wpss-requirement-view__question">
							<?php echo esc_html( $question ); ?>
							<?php if ( $required ) : ?>
								<span class="wpss-required" style="color: #dc3545;">*</span>
							<?php endif; ?>
						</h4>
						<div class="wpss-requirement-view__answer">
							<span class="wpss-text-muted" style="color: #6c757d; font-style: italic;">
								<?php esc_html_e( 'Not provided', 'wp-sell-services' ); ?>
							</span>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<!-- No Requirements Message (when service has no requirements) -->
	<?php if ( $show_no_requirements_msg ) : ?>
		<section class="wpss-order-section wpss-order-section--requirements-view">
			<div class="wpss-order-section__header">
				<h2 class="wpss-order-section__title">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M9 11l3 3L22 4"/>
						<path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
					</svg>
					<?php esc_html_e( 'Order Requirements', 'wp-sell-services' ); ?>
				</h2>
			</div>
			<div class="wpss-order-section__body">
				<div class="wpss-notice wpss-notice--info" style="padding: 15px; background: #f0f7ff; border-radius: 8px; border-left: 4px solid #3b82f6;">
					<p style="margin: 0; color: #1e3a5f;">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
							<circle cx="12" cy="12" r="10"/>
							<line x1="12" y1="16" x2="12" y2="12"/>
							<line x1="12" y1="8" x2="12.01" y2="8"/>
						</svg>
						<?php esc_html_e( 'This service does not require any specific information from the buyer.', 'wp-sell-services' ); ?>
					</p>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- Cancellation Request Banner -->
	<?php if ( 'cancellation_requested' === $order->status ) : ?>
		<?php
		$cancel_data   = json_decode( $order->vendor_notes ?? '', true );
		$cancel_reason = $cancel_data['reason'] ?? '';
		$cancel_note   = $cancel_data['note'] ?? '';

		$reason_labels = array(
			'changed_mind'         => __( 'Changed my mind', 'wp-sell-services' ),
			'found_alternative'    => __( 'Found an alternative', 'wp-sell-services' ),
			'taking_too_long'      => __( 'Taking too long', 'wp-sell-services' ),
			'wrong_order'          => __( 'Ordered by mistake', 'wp-sell-services' ),
			'communication_issues' => __( 'Communication issues with vendor', 'wp-sell-services' ),
			'other'                => __( 'Other', 'wp-sell-services' ),
		);
		$reason_label = $reason_labels[ $cancel_reason ] ?? $cancel_reason;
		?>
		<section class="wpss-order-section">
			<div class="wpss-order-section__body">
				<div class="wpss-alert wpss-alert--warning" style="margin: 0;">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
						<line x1="12" y1="9" x2="12" y2="13"/>
						<line x1="12" y1="17" x2="12.01" y2="17"/>
					</svg>
					<div>
						<p style="margin: 0 0 8px 0; font-weight: 600;">
							<?php
							if ( $is_vendor ) {
								esc_html_e( 'The buyer has requested to cancel this order.', 'wp-sell-services' );
							} else {
								esc_html_e( 'Your cancellation request has been submitted. Waiting for vendor response.', 'wp-sell-services' );
							}
							?>
						</p>
						<?php if ( $reason_label ) : ?>
							<p style="margin: 0 0 4px 0;">
								<strong><?php esc_html_e( 'Reason:', 'wp-sell-services' ); ?></strong>
								<?php echo esc_html( $reason_label ); ?>
							</p>
						<?php endif; ?>
						<?php if ( $cancel_note ) : ?>
							<p style="margin: 0;">
								<strong><?php esc_html_e( 'Details:', 'wp-sell-services' ); ?></strong>
								<?php echo esc_html( $cancel_note ); ?>
							</p>
						<?php endif; ?>
						<?php if ( $is_vendor ) : ?>
							<p style="margin: 8px 0 0 0; font-size: 0.875rem; color: #92400e;">
								<?php esc_html_e( 'You have 48 hours to respond. If no action is taken, the order will be automatically cancelled.', 'wp-sell-services' ); ?>
							</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<!-- Order Timeline Section -->
	<section class="wpss-order-section">
		<div class="wpss-order-section__header">
			<h2 class="wpss-order-section__title">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<circle cx="12" cy="12" r="10"></circle>
					<polyline points="12 6 12 12 16 14"></polyline>
				</svg>
				<?php esc_html_e( 'Order Timeline', 'wp-sell-services' ); ?>
			</h2>
		</div>
		<div class="wpss-order-section__body">
			<div class="wpss-timeline">
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title"><?php esc_html_e( 'Order Placed', 'wp-sell-services' ); ?></span>
						<span class="wpss-timeline__date"><?php echo esc_html( $order->created_at ? wp_date( 'M j, Y \a\t g:i A', $order->created_at->getTimestamp() ) : '' ); ?></span>
					</div>
				</div>

				<?php if ( $order->started_at ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--completed">
						<div class="wpss-timeline__marker"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Work Started', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline__date"><?php echo esc_html( wp_date( 'M j, Y \a\t g:i A', $order->started_at->getTimestamp() ) ); ?></span>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( in_array( $order->status, array( 'delivered', 'completed' ), true ) ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--completed">
						<div class="wpss-timeline__marker"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Delivered', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $order->completed_at ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--completed">
						<div class="wpss-timeline__marker"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline__date"><?php echo esc_html( wp_date( 'M j, Y \a\t g:i A', $order->completed_at->getTimestamp() ) ); ?></span>
						</div>
					</div>
				<?php elseif ( in_array( $order->status, array( 'cancelled', 'refunded', 'partially_refunded' ), true ) ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--completed">
						<div class="wpss-timeline__marker" style="background: var(--wpss-danger, #ef4444);"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title">
								<?php
								if ( 'refunded' === $order->status ) {
									esc_html_e( 'Refunded', 'wp-sell-services' );
								} elseif ( 'partially_refunded' === $order->status ) {
									esc_html_e( 'Partially Refunded', 'wp-sell-services' );
								} else {
									esc_html_e( 'Cancelled', 'wp-sell-services' );
								}
								?>
							</span>
							<span class="wpss-timeline__date"><?php echo esc_html( $order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $order->updated_at->getTimestamp() ) : '' ); ?></span>
						</div>
					</div>
				<?php elseif ( 'cancellation_requested' === $order->status ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--completed">
						<div class="wpss-timeline__marker" style="background: var(--wpss-warning, #f59e0b);"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Cancellation Requested', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline__date"><?php echo esc_html( $order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $order->updated_at->getTimestamp() ) : '' ); ?></span>
						</div>
					</div>
				<?php else : ?>
					<!-- Pending steps -->
					<?php if ( ! $order->started_at && in_array( $order->status, array( 'pending', 'accepted', 'pending_requirements' ), true ) ) : ?>
						<div class="wpss-timeline__item wpss-timeline__item--pending">
							<div class="wpss-timeline__marker"></div>
							<div class="wpss-timeline__content">
								<span class="wpss-timeline__title"><?php esc_html_e( 'Work Started', 'wp-sell-services' ); ?></span>
								<span class="wpss-timeline__date"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></span>
							</div>
						</div>
					<?php endif; ?>
					<?php if ( in_array( $order->status, array( 'pending', 'accepted', 'pending_requirements', 'in_progress' ), true ) ) : ?>
						<div class="wpss-timeline__item wpss-timeline__item--pending">
							<div class="wpss-timeline__marker"></div>
							<div class="wpss-timeline__content">
								<span class="wpss-timeline__title"><?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?></span>
								<span class="wpss-timeline__date"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></span>
							</div>
						</div>
						<div class="wpss-timeline__item wpss-timeline__item--pending">
							<div class="wpss-timeline__marker"></div>
							<div class="wpss-timeline__content">
								<span class="wpss-timeline__title"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
								<span class="wpss-timeline__date"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></span>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>

	<!-- Deliveries Section -->
	<?php if ( ! empty( $deliveries ) ) : ?>
		<section class="wpss-order-section">
			<div class="wpss-order-section__header">
				<h2 class="wpss-order-section__title">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
						<polyline points="17 8 12 3 7 8"/>
						<line x1="12" y1="3" x2="12" y2="15"/>
					</svg>
					<?php esc_html_e( 'Deliveries', 'wp-sell-services' ); ?>
				</h2>
			</div>
			<div class="wpss-order-section__body">
				<?php foreach ( $deliveries as $delivery ) : ?>
					<div class="wpss-delivery-item">
						<div class="wpss-delivery-item__header">
							<span class="wpss-delivery-item__date">
								<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $delivery->created_at ) ) ); ?>
							</span>
							<span class="wpss-badge wpss-badge--status-<?php echo esc_attr( $delivery->status ); ?>">
								<?php echo esc_html( ucfirst( $delivery->status ) ); ?>
							</span>
						</div>
						<div class="wpss-delivery-item__content">
							<?php echo wp_kses_post( wpautop( $delivery->message ) ); ?>
						</div>
						<?php
						$files = maybe_unserialize( $delivery->attachments );
						if ( is_string( $files ) ) {
							$decoded = json_decode( $files, true );
							$files   = is_array( $decoded ) ? $decoded : array();
						}
						if ( ! empty( $files ) && is_array( $files ) ) :
							?>
							<div class="wpss-delivery-item__files">
								<?php foreach ( $files as $file ) : ?>
									<?php
									// Support both formats: array with id/name/url keys, or plain attachment ID.
									if ( is_array( $file ) ) {
										$att_id    = $file['id'] ?? 0;
										$file_url  = $file['url'] ?? wp_get_attachment_url( $att_id );
										$file_name = $file['name'] ?? get_the_title( $att_id );
									} else {
										$file_url  = wp_get_attachment_url( (int) $file );
										$file_name = get_the_title( (int) $file );
									}
									if ( ! $file_url ) {
										continue;
									}
									?>
									<a href="<?php echo esc_url( $file_url ); ?>" class="wpss-file-link" target="_blank" download>
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
											<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
											<polyline points="7 10 12 15 17 10"/>
											<line x1="12" y1="15" x2="12" y2="3"/>
										</svg>
										<?php echo esc_html( $file_name ); ?>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php
	/**
	 * Hook: wpss_order_view_sidebar
	 *
	 * Fires where sidebar content can be added.
	 *
	 * @since 1.0.0
	 *
	 * @param object $order Order object.
	 */
	do_action( 'wpss_order_view_sidebar', $order );
	?>

	<!-- Conversation Section -->
	<section class="wpss-order-section wpss-order-section--conversation">
		<div class="wpss-order-section__header">
			<h2 class="wpss-order-section__title">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
				</svg>
				<?php esc_html_e( 'Conversation', 'wp-sell-services' ); ?>
			</h2>
		</div>
		<div class="wpss-order-section__body">
			<?php
			// Include the conversation template.
			wpss_get_template(
				'order/conversation.php',
				array(
					'order_id'    => $order_id,
					'order'       => $order,
					'is_vendor'   => $is_vendor,
					'is_customer' => $is_customer,
				)
			);
			?>
		</div>
	</section>

	<!-- Review CTA (for completed orders) -->
	<?php if ( 'completed' === $order->status && $is_customer ) : ?>
		<?php
		// Check if already reviewed.
		$review_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}wpss_reviews WHERE order_id = %d",
				$order_id
			)
		);
		?>
		<?php if ( ! $review_exists ) : ?>
			<section class="wpss-order-section wpss-order-section--review">
				<div class="wpss-review-cta">
					<svg width="40" height="40" viewBox="0 0 24 24" fill="currentColor" class="wpss-review-cta__icon">
						<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
					</svg>
					<h3 class="wpss-review-cta__title"><?php esc_html_e( 'Rate Your Experience', 'wp-sell-services' ); ?></h3>
					<p class="wpss-review-cta__text"><?php esc_html_e( 'How was your experience with this order? Your feedback helps other buyers.', 'wp-sell-services' ); ?></p>
					<button type="button" class="wpss-btn wpss-btn--primary wpss-btn--lg wpss-write-review-btn"
							data-order="<?php echo esc_attr( $order_id ); ?>">
						<?php esc_html_e( 'Write a Review', 'wp-sell-services' ); ?>
					</button>
				</div>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php
// Check if delivery modal should be available.
$can_deliver = $is_vendor && in_array( $order->status, array( 'in_progress', 'revision_requested', 'late' ), true );

// Check if review modal should be available.
$can_review            = 'completed' === $order->status && $is_customer && empty( $review_exists );
$dispute_settings      = get_option( 'wpss_orders', array() );
$can_open_dispute      = ! empty( $dispute_settings['allow_disputes'] ) && ( $is_customer || $is_vendor ) && in_array( $order->status, array( 'in_progress', 'pending_approval', 'revision_requested' ), true );
$can_request_revision  = $is_customer && 'pending_approval' === $order->status && $order->can_request_revision();

// Check if cancel modal should be available.
$buyer_cancel_statuses = array( 'pending_payment', 'pending_requirements', 'pending', 'accepted' );
$can_cancel_immediate  = $is_customer && in_array( $order->status, $buyer_cancel_statuses, true );
$can_cancel_request    = false;
if ( $is_customer && 'in_progress' === $order->status && $order->started_at ) {
	$hours_since_start  = ( time() - $order->started_at->getTimestamp() ) / 3600;
	$has_any_deliveries = ! empty( $deliveries );
	$can_cancel_request = $hours_since_start <= 24 && ! $has_any_deliveries;
}
$can_cancel = $can_cancel_immediate || $can_cancel_request;
?>

<?php if ( $can_deliver ) : ?>
<!-- Delivery Modal -->
<div class="wpss-modal" id="wpss-deliver-modal" data-order="<?php echo esc_attr( $order_id ); ?>">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 class="wpss-modal__title"><?php esc_html_e( 'Submit Delivery', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
		</div>
		<form class="wpss-deliver-form" id="wpss-deliver-form">
			<?php wp_nonce_field( 'wpss_order_action', 'nonce' ); ?>
			<input type="hidden" name="action" value="wpss_deliver_order">
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

			<div class="wpss-modal__body">
				<div class="wpss-alert wpss-alert--info">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<circle cx="12" cy="12" r="10"></circle>
						<path d="M12 16v-4"></path>
						<path d="M12 8h.01"></path>
					</svg>
					<p><?php esc_html_e( 'Describe what you are delivering. The buyer will review and can accept or request revisions.', 'wp-sell-services' ); ?></p>
				</div>

				<div class="wpss-form-group">
					<label class="wpss-label" for="deliver-message">
						<?php esc_html_e( 'Delivery Message', 'wp-sell-services' ); ?>
						<span class="wpss-required">*</span>
					</label>
					<textarea
						name="message"
						id="deliver-message"
						class="wpss-textarea"
						rows="5"
						required
						placeholder="<?php esc_attr_e( 'Describe what you are delivering and any important notes for the buyer...', 'wp-sell-services' ); ?>"
					></textarea>
				</div>

				<div class="wpss-form-group">
					<label class="wpss-label" for="deliver-files">
						<?php esc_html_e( 'Attachments (Optional)', 'wp-sell-services' ); ?>
					</label>
					<div class="wpss-file-upload">
						<input
							type="file"
							name="files[]"
							id="deliver-files"
							class="wpss-file-input"
							multiple
							accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.zip,.rar"
						>
						<label for="deliver-files" class="wpss-file-label">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
								<polyline points="17 8 12 3 7 8"/>
								<line x1="12" y1="3" x2="12" y2="15"/>
							</svg>
							<?php esc_html_e( 'Choose files or drag and drop', 'wp-sell-services' ); ?>
						</label>
						<div class="wpss-file-list" id="deliver-file-list"></div>
					</div>
					<p class="wpss-form-help"><?php esc_html_e( 'Max file size: 50MB. Supported: images, documents, archives.', 'wp-sell-services' ); ?></p>
				</div>
			</div>

			<div class="wpss-modal__footer">
				<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__close-btn">
					<?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?>
				</button>
				<button type="submit" class="wpss-btn wpss-btn--success">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<polyline points="20 6 9 17 4 12"/>
					</svg>
					<?php esc_html_e( 'Submit Delivery', 'wp-sell-services' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<?php if ( $can_review ) : ?>
<!-- Review Modal -->
<div class="wpss-modal" id="wpss-review-modal" data-order="<?php echo esc_attr( $order_id ); ?>">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 class="wpss-modal__title"><?php esc_html_e( 'Write a Review', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
		</div>
		<form class="wpss-review-form" id="wpss-review-form">
			<?php wp_nonce_field( 'wpss_submit_review', 'wpss_review_nonce' ); ?>
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

			<div class="wpss-modal__body">
				<div class="wpss-form-group">
					<label class="wpss-label"><?php esc_html_e( 'Overall Rating', 'wp-sell-services' ); ?> <span class="wpss-required">*</span></label>
					<div class="wpss-star-rating" role="group" aria-label="<?php esc_attr_e( 'Rating', 'wp-sell-services' ); ?>">
						<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
							<input type="radio" name="rating" id="star-<?php echo esc_attr( $i ); ?>" value="<?php echo esc_attr( $i ); ?>" required>
							<label for="star-<?php echo esc_attr( $i ); ?>" title="<?php echo esc_attr( $i ); ?> <?php esc_attr_e( 'stars', 'wp-sell-services' ); ?>">
								<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
									<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
								</svg>
							</label>
						<?php endfor; ?>
					</div>
				</div>

				<div class="wpss-form-group">
					<label for="review-comment" class="wpss-label"><?php esc_html_e( 'Your Review', 'wp-sell-services' ); ?> <span class="wpss-required">*</span></label>
					<textarea name="comment" id="review-comment" class="wpss-textarea" rows="4" required
								placeholder="<?php esc_attr_e( 'Share your experience with this service...', 'wp-sell-services' ); ?>"></textarea>
				</div>
			</div>

			<div class="wpss-modal__footer">
				<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__close-btn">
					<?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?>
				</button>
				<button type="submit" class="wpss-btn wpss-btn--primary">
					<?php esc_html_e( 'Submit Review', 'wp-sell-services' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<?php if ( $can_request_revision ) : ?>
<!-- Revision Modal -->
<div class="wpss-modal" id="wpss-revision-modal" data-order="<?php echo esc_attr( $order_id ); ?>">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 class="wpss-modal__title"><?php esc_html_e( 'Request Revision', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
		</div>
		<form class="wpss-revision-form" id="wpss-revision-form">
			<?php wp_nonce_field( 'wpss_order_action', 'nonce' ); ?>
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

			<div class="wpss-modal__body">
				<div class="wpss-alert wpss-alert--info">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<circle cx="12" cy="12" r="10"/>
						<line x1="12" y1="16" x2="12" y2="12"/>
						<line x1="12" y1="8" x2="12.01" y2="8"/>
					</svg>
					<p><?php esc_html_e( 'Please describe the changes you need. The seller will review your request and update the delivery.', 'wp-sell-services' ); ?></p>
				</div>

				<div class="wpss-form-group">
					<label for="revision-reason" class="wpss-label"><?php esc_html_e( 'What changes would you like?', 'wp-sell-services' ); ?> <span class="wpss-required">*</span></label>
					<textarea name="reason" id="revision-reason" class="wpss-textarea" rows="4" required
								placeholder="<?php esc_attr_e( 'Please be specific about what needs to be changed...', 'wp-sell-services' ); ?>"></textarea>
				</div>
			</div>

			<div class="wpss-modal__footer">
				<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__close-btn">
					<?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?>
				</button>
				<button type="submit" class="wpss-btn wpss-btn--primary">
					<?php esc_html_e( 'Submit Revision Request', 'wp-sell-services' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<?php if ( $can_open_dispute ) : ?>
<!-- Dispute Modal -->
<div class="wpss-modal" id="wpss-dispute-modal" data-order="<?php echo esc_attr( $order_id ); ?>">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 class="wpss-modal__title"><?php esc_html_e( 'Open Dispute', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
		</div>
		<form class="wpss-dispute-form" id="wpss-dispute-form">
			<?php wp_nonce_field( 'wpss_open_dispute', 'wpss_dispute_nonce' ); ?>
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

			<div class="wpss-modal__body">
				<div class="wpss-alert wpss-alert--warning">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
						<line x1="12" y1="9" x2="12" y2="13"/>
						<line x1="12" y1="17" x2="12.01" y2="17"/>
					</svg>
					<p><?php esc_html_e( 'Opening a dispute will pause this order until resolved. Please try to resolve issues directly with the seller first.', 'wp-sell-services' ); ?></p>
				</div>

				<div class="wpss-form-group">
					<label for="dispute-reason" class="wpss-label"><?php esc_html_e( 'Reason for Dispute', 'wp-sell-services' ); ?> <span class="wpss-required">*</span></label>
					<select name="reason" id="dispute-reason" class="wpss-select" required>
						<option value=""><?php esc_html_e( 'Select a reason', 'wp-sell-services' ); ?></option>
						<option value="not_delivered"><?php esc_html_e( 'Work not delivered', 'wp-sell-services' ); ?></option>
						<option value="poor_quality"><?php esc_html_e( 'Poor quality work', 'wp-sell-services' ); ?></option>
						<option value="not_as_described"><?php esc_html_e( 'Not as described', 'wp-sell-services' ); ?></option>
						<option value="communication"><?php esc_html_e( 'Communication issues', 'wp-sell-services' ); ?></option>
						<option value="deadline"><?php esc_html_e( 'Missed deadline', 'wp-sell-services' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', 'wp-sell-services' ); ?></option>
					</select>
				</div>

				<div class="wpss-form-group">
					<label for="dispute-description" class="wpss-label"><?php esc_html_e( 'Description', 'wp-sell-services' ); ?> <span class="wpss-required">*</span></label>
					<textarea name="description" id="dispute-description" class="wpss-textarea" rows="4" required
								placeholder="<?php esc_attr_e( 'Please describe the issue in detail...', 'wp-sell-services' ); ?>"></textarea>
				</div>
			</div>

			<div class="wpss-modal__footer">
				<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__close-btn">
					<?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?>
				</button>
				<button type="submit" class="wpss-btn wpss-btn--danger">
					<?php esc_html_e( 'Open Dispute', 'wp-sell-services' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<?php if ( $can_cancel ) : ?>
<!-- Cancellation Modal -->
<div class="wpss-modal" id="wpss-cancel-modal" data-order="<?php echo esc_attr( $order_id ); ?>">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 class="wpss-modal__title"><?php esc_html_e( 'Cancel Order', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
		</div>
		<form class="wpss-cancel-form" id="wpss-cancel-form">
			<?php wp_nonce_field( 'wpss_order_action', 'nonce' ); ?>
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

			<div class="wpss-modal__body">
				<?php if ( $can_cancel_request ) : ?>
					<div class="wpss-alert wpss-alert--info">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
							<circle cx="12" cy="12" r="10"></circle>
							<path d="M12 16v-4"></path>
							<path d="M12 8h.01"></path>
						</svg>
						<p><?php esc_html_e( 'Since work has already started, your cancellation request will be sent to the vendor for review. They have 48 hours to respond.', 'wp-sell-services' ); ?></p>
					</div>
				<?php endif; ?>

				<div class="wpss-form-group">
					<label for="cancel-reason" class="wpss-label">
						<?php esc_html_e( 'Reason for Cancellation', 'wp-sell-services' ); ?>
						<span class="wpss-required">*</span>
					</label>
					<select name="reason" id="cancel-reason" class="wpss-select" required>
						<option value=""><?php esc_html_e( 'Select a reason', 'wp-sell-services' ); ?></option>
						<option value="changed_mind"><?php esc_html_e( 'I changed my mind', 'wp-sell-services' ); ?></option>
						<option value="found_alternative"><?php esc_html_e( 'Found an alternative', 'wp-sell-services' ); ?></option>
						<option value="taking_too_long"><?php esc_html_e( 'Taking too long', 'wp-sell-services' ); ?></option>
						<option value="wrong_order"><?php esc_html_e( 'Ordered by mistake', 'wp-sell-services' ); ?></option>
						<option value="communication_issues"><?php esc_html_e( 'Communication issues with vendor', 'wp-sell-services' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other (please specify)', 'wp-sell-services' ); ?></option>
					</select>
				</div>

				<div class="wpss-form-group">
					<label for="cancel-note" class="wpss-label">
						<?php esc_html_e( 'Additional Details (optional)', 'wp-sell-services' ); ?>
					</label>
					<textarea name="note" id="cancel-note" class="wpss-textarea" rows="3"
						placeholder="<?php esc_attr_e( 'Any additional details about why you want to cancel...', 'wp-sell-services' ); ?>"></textarea>
				</div>
			</div>

			<div class="wpss-modal__footer">
				<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__close-btn">
					<?php esc_html_e( 'Keep Order', 'wp-sell-services' ); ?>
				</button>
				<button type="submit" class="wpss-btn wpss-btn--danger">
					<?php
					if ( $can_cancel_request ) {
						esc_html_e( 'Submit Cancellation Request', 'wp-sell-services' );
					} else {
						esc_html_e( 'Cancel Order', 'wp-sell-services' );
					}
					?>
				</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<style>
/* Single Column Order View Styles */
.wpss-order-view {
	max-width: 800px;
	margin: 0 auto;
}

.wpss-order-view__header {
	margin-bottom: 1rem;
}

.wpss-order-view__back {
	display: inline-flex;
	align-items: center;
	gap: 0.5rem;
	color: var(--wpss-text-muted, #6b7280);
	text-decoration: none;
	font-size: 0.875rem;
	transition: color 0.2s;
}

.wpss-order-view__back:hover {
	color: var(--wpss-primary, #3b82f6);
}

.wpss-order-view__title-bar {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	align-items: flex-start;
	gap: 1rem;
	padding-bottom: 1.5rem;
	margin-bottom: 1.5rem;
	border-bottom: 1px solid var(--wpss-border, #e5e7eb);
}

.wpss-order-view__title-info {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 1rem;
}

.wpss-order-view__title {
	margin: 0;
	font-size: 1.5rem;
	font-weight: 700;
	color: var(--wpss-text, #111827);
}

.wpss-order-view__actions {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
}

/* Order Sections */
.wpss-order-section {
	background: var(--wpss-card-bg, #fff);
	border: 1px solid var(--wpss-border, #e5e7eb);
	border-radius: 12px;
	margin-bottom: 1.5rem;
	overflow: hidden;
}

.wpss-order-section__header {
	padding: 1rem 1.5rem;
	border-bottom: 1px solid var(--wpss-border, #e5e7eb);
	background: var(--wpss-bg-subtle, #f9fafb);
}

.wpss-order-section__title {
	display: flex;
	align-items: center;
	gap: 0.75rem;
	margin: 0;
	font-size: 1rem;
	font-weight: 600;
	color: var(--wpss-text, #111827);
}

.wpss-order-section__title svg {
	color: var(--wpss-text-muted, #6b7280);
}

.wpss-order-section__body {
	padding: 1.5rem;
}

/* Order Details Grid */
.wpss-order-details-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
	gap: 1.5rem;
}

.wpss-order-detail-item {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
}

.wpss-order-detail-item__label {
	font-size: 0.8125rem;
	color: var(--wpss-text-muted, #6b7280);
	text-transform: uppercase;
	letter-spacing: 0.025em;
}

.wpss-order-detail-item__value {
	font-size: 1rem;
	font-weight: 600;
	color: var(--wpss-text, #111827);
}

.wpss-order-detail-item--highlight .wpss-order-detail-item__value {
	font-size: 1.25rem;
	color: var(--wpss-success, #10b981);
}

/* Service Info */
.wpss-service-info {
	display: flex;
	gap: 1rem;
	padding-bottom: 1.5rem;
	border-bottom: 1px solid var(--wpss-border, #e5e7eb);
	margin-bottom: 1.5rem;
}

.wpss-service-info__image {
	width: 120px;
	height: 80px;
	object-fit: cover;
	border-radius: 8px;
	flex-shrink: 0;
}

.wpss-service-info__content {
	flex: 1;
	min-width: 0;
}

.wpss-service-info__title {
	margin: 0 0 0.5rem;
	font-size: 1.125rem;
	font-weight: 600;
	line-height: 1.3;
}

.wpss-service-info__title a {
	color: var(--wpss-text, #111827);
	text-decoration: none;
}

.wpss-service-info__title a:hover {
	color: var(--wpss-primary, #3b82f6);
}

.wpss-service-info__price {
	margin: 0;
	font-size: 1.125rem;
	font-weight: 700;
	color: var(--wpss-success, #10b981);
}

/* Party Info */
.wpss-party-info__card {
	display: flex;
	align-items: center;
	gap: 1rem;
}

.wpss-party-info__avatar {
	width: 64px;
	height: 64px;
	border-radius: 50%;
	flex-shrink: 0;
}

.wpss-party-info__details {
	display: flex;
	flex-direction: column;
	gap: 0.25rem;
}

.wpss-party-info__role {
	font-size: 0.75rem;
	color: var(--wpss-text-muted, #6b7280);
	text-transform: uppercase;
	letter-spacing: 0.05em;
}

.wpss-party-info__name {
	font-size: 1rem;
	color: var(--wpss-text, #111827);
}

.wpss-party-info__rating {
	display: flex;
	align-items: center;
	gap: 0.25rem;
	font-size: 0.875rem;
	color: var(--wpss-text, #111827);
}

.wpss-party-info__rating .wpss-star-icon {
	color: var(--wpss-warning, #f59e0b);
}

.wpss-party-info__rating-count {
	color: var(--wpss-text-muted, #6b7280);
}

/* Timeline */
.wpss-timeline {
	position: relative;
	padding-left: 2rem;
}

.wpss-timeline::before {
	content: '';
	position: absolute;
	left: 7px;
	top: 0;
	bottom: 0;
	width: 2px;
	background: var(--wpss-border, #e5e7eb);
}

.wpss-timeline__item {
	position: relative;
	padding-bottom: 1.5rem;
}

.wpss-timeline__item:last-child {
	padding-bottom: 0;
}

.wpss-timeline__marker {
	position: absolute;
	left: -2rem;
	top: 2px;
	width: 16px;
	height: 16px;
	border-radius: 50%;
	background: var(--wpss-bg, #fff);
	border: 2px solid var(--wpss-border, #e5e7eb);
}

.wpss-timeline__item--completed .wpss-timeline__marker {
	background: var(--wpss-success, #10b981);
	border-color: var(--wpss-success, #10b981);
}

.wpss-timeline__item--pending .wpss-timeline__marker {
	background: var(--wpss-bg, #fff);
	border-color: var(--wpss-border, #d1d5db);
}

.wpss-timeline__content {
	display: flex;
	flex-direction: column;
	gap: 0.125rem;
}

.wpss-timeline__title {
	font-weight: 600;
	color: var(--wpss-text, #111827);
}

.wpss-timeline__item--pending .wpss-timeline__title {
	color: var(--wpss-text-muted, #6b7280);
}

.wpss-timeline__date {
	font-size: 0.875rem;
	color: var(--wpss-text-muted, #6b7280);
}

/* Delivery Items */
.wpss-delivery-item {
	padding: 1rem;
	background: var(--wpss-bg-subtle, #f9fafb);
	border-radius: 8px;
	margin-bottom: 1rem;
}

.wpss-delivery-item:last-child {
	margin-bottom: 0;
}

.wpss-delivery-item__header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 0.75rem;
}

.wpss-delivery-item__date {
	font-size: 0.875rem;
	color: var(--wpss-text-muted, #6b7280);
}

.wpss-delivery-item__content {
	color: var(--wpss-text, #111827);
	line-height: 1.6;
}

.wpss-delivery-item__content p:last-child {
	margin-bottom: 0;
}

.wpss-delivery-item__files {
	display: flex;
	flex-wrap: wrap;
	gap: 0.5rem;
	margin-top: 1rem;
	padding-top: 1rem;
	border-top: 1px solid var(--wpss-border, #e5e7eb);
}

.wpss-file-link {
	display: inline-flex;
	align-items: center;
	gap: 0.5rem;
	padding: 0.5rem 1rem;
	background: var(--wpss-bg, #fff);
	border: 1px solid var(--wpss-border, #e5e7eb);
	border-radius: 6px;
	color: var(--wpss-primary, #3b82f6);
	text-decoration: none;
	font-size: 0.875rem;
	transition: all 0.2s;
}

.wpss-file-link:hover {
	background: var(--wpss-primary, #3b82f6);
	color: #fff;
	border-color: var(--wpss-primary, #3b82f6);
}

/* Review CTA */
.wpss-review-cta {
	text-align: center;
	padding: 2rem;
}

.wpss-review-cta__icon {
	color: var(--wpss-warning, #f59e0b);
	margin-bottom: 1rem;
}

.wpss-review-cta__title {
	margin: 0 0 0.5rem;
	font-size: 1.25rem;
	font-weight: 600;
	color: var(--wpss-text, #111827);
}

.wpss-review-cta__text {
	margin: 0 0 1.5rem;
	color: var(--wpss-text-muted, #6b7280);
}

/* Badge sizes */
.wpss-badge--lg {
	padding: 0.375rem 0.875rem;
	font-size: 0.875rem;
}

/* Requirements Form */
.wpss-requirements-form .wpss-form-group {
	margin-bottom: 1.5rem;
}

.wpss-requirements-form .wpss-label {
	display: block;
	margin-bottom: 0.5rem;
	font-weight: 600;
	color: var(--wpss-text, #111827);
}

.wpss-requirements-form .wpss-required {
	color: var(--wpss-danger, #ef4444);
}

.wpss-requirements-form .wpss-input,
.wpss-requirements-form .wpss-textarea {
	width: 100%;
	padding: 0.75rem 1rem;
	border: 1px solid var(--wpss-border, #e5e7eb);
	border-radius: 8px;
	font-size: 1rem;
	color: var(--wpss-text, #111827);
	background: var(--wpss-bg, #fff);
	transition: border-color 0.2s, box-shadow 0.2s;
}

.wpss-requirements-form .wpss-input:focus,
.wpss-requirements-form .wpss-textarea:focus {
	outline: none;
	border-color: var(--wpss-primary, #3b82f6);
	box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.wpss-file-upload {
	position: relative;
}

.wpss-file-upload .wpss-file-input {
	position: absolute;
	width: 1px;
	height: 1px;
	padding: 0;
	margin: -1px;
	overflow: hidden;
	clip: rect(0, 0, 0, 0);
	border: 0;
}

.wpss-file-upload__label {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	gap: 0.75rem;
	padding: 2rem;
	border: 2px dashed var(--wpss-border, #e5e7eb);
	border-radius: 8px;
	cursor: pointer;
	transition: all 0.2s;
	background: var(--wpss-bg-subtle, #f9fafb);
}

.wpss-file-upload__label:hover {
	border-color: var(--wpss-primary, #3b82f6);
	background: rgba(59, 130, 246, 0.05);
}

.wpss-file-upload__label svg {
	color: var(--wpss-text-muted, #6b7280);
}

.wpss-file-upload__text {
	font-size: 0.875rem;
	color: var(--wpss-text-muted, #6b7280);
}

.wpss-file-upload__name {
	display: block;
	margin-top: 0.5rem;
	font-size: 0.875rem;
	color: var(--wpss-primary, #3b82f6);
	font-weight: 500;
}

.wpss-file-upload__name:empty {
	display: none;
}

.wpss-file-upload.has-file .wpss-file-upload__label {
	border-color: var(--wpss-success, #10b981);
	background: rgba(16, 185, 129, 0.05);
}

.wpss-form-actions {
	margin-top: 2rem;
	padding-top: 1.5rem;
	border-top: 1px solid var(--wpss-border, #e5e7eb);
}

.wpss-form-actions .wpss-btn {
	display: inline-flex;
	align-items: center;
	gap: 0.5rem;
}

/* Requirements View (Read-only) */
.wpss-requirement-view {
	padding: 1rem;
	background: var(--wpss-bg-subtle, #f9fafb);
	border-radius: 8px;
	margin-bottom: 1rem;
}

.wpss-requirement-view:last-child {
	margin-bottom: 0;
}

.wpss-requirement-view__question {
	margin: 0 0 0.5rem;
	font-size: 0.9375rem;
	font-weight: 600;
	color: var(--wpss-text, #111827);
}

.wpss-requirement-view__answer {
	color: var(--wpss-text, #374151);
	line-height: 1.6;
}

.wpss-requirement-view__answer p {
	margin: 0;
}

.wpss-text-muted {
	color: var(--wpss-text-muted, #6b7280);
	font-style: italic;
}

/* Requirements View Enhancements */
.wpss-order-section__timestamp {
	font-size: 0.8125rem;
	color: var(--wpss-text-muted, #6b7280);
	font-weight: 400;
}

.wpss-requirement-view__answer {
	position: relative;
}

.wpss-requirement-view__answer--collapsed .wpss-requirement-view__text-content {
	max-height: 120px;
	overflow: hidden;
	position: relative;
}

.wpss-requirement-view__answer--collapsed .wpss-requirement-view__text-content::after {
	content: '';
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	height: 40px;
	background: linear-gradient(transparent, var(--wpss-bg-subtle, #f9fafb));
}

.wpss-requirement-view__answer.wpss-expanded .wpss-requirement-view__text-content {
	max-height: none;
}

.wpss-requirement-view__answer.wpss-expanded .wpss-requirement-view__text-content::after {
	display: none;
}

.wpss-requirement-view__expand-btn {
	display: inline-flex;
	align-items: center;
	gap: 0.25rem;
	margin-top: 0.5rem;
	padding: 0;
	background: none;
	border: none;
	color: var(--wpss-primary, #3b82f6);
	font-size: 0.875rem;
	cursor: pointer;
	transition: color 0.2s;
}

.wpss-requirement-view__expand-btn:hover {
	color: var(--wpss-primary-dark, #2563eb);
}

.wpss-requirement-view__expand-btn .wpss-expand-icon {
	transition: transform 0.2s;
}

.wpss-requirement-view__answer.wpss-expanded .wpss-expand-icon {
	transform: rotate(180deg);
}

.wpss-requirement-view__copy-btn {
	position: absolute;
	top: 0;
	right: 0;
	padding: 0.25rem;
	background: var(--wpss-bg, #fff);
	border: 1px solid var(--wpss-border, #e5e7eb);
	border-radius: 4px;
	color: var(--wpss-text-muted, #6b7280);
	cursor: pointer;
	opacity: 0;
	transition: all 0.2s;
}

.wpss-requirement-view:hover .wpss-requirement-view__copy-btn {
	opacity: 1;
}

.wpss-requirement-view__copy-btn:hover {
	background: var(--wpss-primary, #3b82f6);
	border-color: var(--wpss-primary, #3b82f6);
	color: #fff;
}

.wpss-requirement-view__copy-btn.wpss-copied {
	background: var(--wpss-success, #10b981);
	border-color: var(--wpss-success, #10b981);
	color: #fff;
}

.wpss-requirement-view__image-preview {
	margin-bottom: 0.75rem;
}

.wpss-requirement-view__thumbnail {
	max-width: 200px;
	max-height: 150px;
	object-fit: cover;
	border-radius: 8px;
	border: 1px solid var(--wpss-border, #e5e7eb);
	cursor: pointer;
	transition: transform 0.2s;
}

.wpss-requirement-view__thumbnail:hover {
	transform: scale(1.02);
}

/* Cancellation Requested Status Badge */
.wpss-badge--status-cancellation-requested {
	background: rgba(245, 158, 11, 0.1);
	color: #92400e;
	border: 1px solid rgba(245, 158, 11, 0.3);
}

/* Alert Styles */
.wpss-alert--warning {
	display: flex;
	align-items: flex-start;
	gap: 0.75rem;
	padding: 1rem;
	background: #fff8e1;
	border: 1px solid rgba(245, 158, 11, 0.3);
	border-radius: 8px;
	color: #92400e;
}

.wpss-alert--warning svg {
	flex-shrink: 0;
	margin-top: 0.125rem;
}

.wpss-alert--warning p {
	margin: 0;
	font-size: 0.875rem;
}

.wpss-alert--info {
	display: flex;
	align-items: flex-start;
	gap: 0.75rem;
	padding: 1rem;
	background: rgba(59, 130, 246, 0.1);
	border: 1px solid rgba(59, 130, 246, 0.2);
	border-radius: 8px;
	color: var(--wpss-primary, #3b82f6);
}

.wpss-alert--info svg {
	flex-shrink: 0;
	margin-top: 0.125rem;
}

.wpss-alert--info p {
	margin: 0;
	font-size: 0.875rem;
}

/* Responsive */
@media (max-width: 640px) {
	.wpss-order-view__title-bar {
		flex-direction: column;
		align-items: stretch;
	}

	.wpss-order-view__actions {
		justify-content: flex-start;
	}

	.wpss-order-details-grid {
		grid-template-columns: repeat(2, 1fr);
	}

	.wpss-service-info {
		flex-direction: column;
	}

	.wpss-service-info__image {
		width: 100%;
		height: 160px;
	}
}
</style>

<script>
(function() {
	'use strict';

	// Expand/Collapse functionality for long text responses
	document.querySelectorAll('.wpss-requirement-view__expand-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var answer = this.closest('.wpss-requirement-view__answer');
			var isExpanded = answer.classList.toggle('wpss-expanded');
			var expandText = this.querySelector('.wpss-expand-text');
			var collapseText = this.querySelector('.wpss-collapse-text');

			this.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');

			if (isExpanded) {
				answer.classList.remove('wpss-requirement-view__answer--collapsed');
				expandText.style.display = 'none';
				collapseText.style.display = 'inline';
			} else {
				answer.classList.add('wpss-requirement-view__answer--collapsed');
				expandText.style.display = 'inline';
				collapseText.style.display = 'none';
			}
		});
	});

	// Copy to clipboard functionality
	document.querySelectorAll('.wpss-requirement-view__copy-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var textToCopy = this.getAttribute('data-copy-text');
			var button = this;

			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(textToCopy).then(function() {
					button.classList.add('wpss-copied');
					setTimeout(function() {
						button.classList.remove('wpss-copied');
					}, 2000);
				});
			} else {
				// Fallback for older browsers
				var textarea = document.createElement('textarea');
				textarea.value = textToCopy;
				textarea.style.position = 'fixed';
				textarea.style.opacity = '0';
				document.body.appendChild(textarea);
				textarea.select();
				try {
					document.execCommand('copy');
					button.classList.add('wpss-copied');
					setTimeout(function() {
						button.classList.remove('wpss-copied');
					}, 2000);
				} catch (err) {
					console.error('Copy failed', err);
				}
				document.body.removeChild(textarea);
			}
		});
	});

	// Image preview click to open in new tab
	document.querySelectorAll('.wpss-requirement-view__thumbnail').forEach(function(img) {
		img.addEventListener('click', function() {
			window.open(this.src, '_blank');
		});
	});

	// Cancel button: open modal instead of direct action.
	document.querySelectorAll('.wpss-cancel-btn').forEach(function(btn) {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			if (typeof jQuery !== 'undefined' && typeof WPSS !== 'undefined' && WPSS.showModal) {
				WPSS.showModal('wpss-cancel-modal');
			} else {
				var modal = document.getElementById('wpss-cancel-modal');
				if (modal) {
					modal.classList.add('wpss-modal-open');
					document.body.classList.add('wpss-modal-active');
				}
			}
		});
	});

	// Cancel form submission.
	var cancelForm = document.getElementById('wpss-cancel-form');
	if (cancelForm) {
		cancelForm.addEventListener('submit', function(e) {
			e.preventDefault();

			var orderId = cancelForm.querySelector('[name="order_id"]').value;
			var reason  = cancelForm.querySelector('[name="reason"]').value;
			var note    = cancelForm.querySelector('[name="note"]').value;
			var btn     = cancelForm.querySelector('[type="submit"]');

			if (!reason) {
				return;
			}

			btn.disabled = true;
			btn.textContent = btn.textContent.replace(/\S.+/, 'Processing...');

			fetch(wpssApi.root + 'wpss/v1/orders/' + orderId + '/cancel', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': wpssApi.nonce
				},
				body: JSON.stringify({
					reason: reason,
					note: note
				})
			})
			.then(function(response) { return response.json(); })
			.then(function(data) {
				if (data.code) {
					if (typeof WPSS !== 'undefined' && WPSS.showNotification) {
						WPSS.showNotification(data.message || 'Failed to cancel order.', 'error');
					}
					btn.disabled = false;
				} else {
					window.location.reload();
				}
			})
			.catch(function() {
				if (typeof WPSS !== 'undefined' && WPSS.showNotification) {
					WPSS.showNotification('An error occurred. Please try again.', 'error');
				}
				btn.disabled = false;
			});
		});
	}
})();
</script>

<?php
/**
 * Hook: wpss_after_order_view
 *
 * @param object $order Order object.
 */
do_action( 'wpss_after_order_view', $order );
?>
