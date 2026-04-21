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

// Enqueue frontend assets to ensure wpssData is available.
wpss_enqueue_frontend_assets();

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
	'var wpssApi = ' . wp_json_encode(
		array(
			'root'  => esc_url_raw( rest_url() ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		)
	) . ';',
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
$vendor_name   = $vendor ? $vendor->display_name : __( 'Deleted User', 'wp-sell-services' );
$customer_name = $customer ? $customer->display_name : __( 'Deleted User', 'wp-sell-services' );

// Get deliveries via service layer.
$delivery_service = new DeliveryService();
$deliveries       = $delivery_service->get_order_deliveries( $order_id );

// Dispute eligibility check via service layer.
$dispute_service  = new \WPSellServices\Services\DisputeService();
$can_open_dispute = $dispute_service->can_open_dispute( $order );

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
			<i data-lucide="arrow-left" class="wpss-icon" aria-hidden="true"></i>
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

		<?php if ( in_array( $order->status, array( 'pending', 'accepted', 'in_progress', 'pending_approval', 'pending_requirements', 'pending_payment', 'revision_requested', 'late', 'cancellation_requested', 'completed' ), true ) ) : ?>
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
					// Use base checkout URL when service_id is 0 to avoid service-checkout/0/ URLs
					if ( $order->service_id > 0 ) {
						$checkout_url = wpss_get_service_checkout_url( $order->service_id );
					} else {
						$checkout_url = wpss_get_checkout_base_url();
					}
					$pay_url        = add_query_arg( 'pay_order', $order_id, $checkout_url );
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

			$order_settings = get_option( 'wpss_orders', array() );
			if ( $can_open_dispute && ( $is_customer || $is_vendor ) ) {
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
				<i data-lucide="file-text" class="wpss-icon" aria-hidden="true"></i>
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
				<?php
				// Vendor-only NET earnings breakdown. Buyers see the gross "Total Amount"
				// above (which is what they paid); vendors additionally see what they
				// actually receive after the platform commission, so the detail view
				// reconciles with the Revenue stat on the sales list and the wallet.
				if ( $is_vendor ) :
					$detail_gross = (float) $order->total;
					$detail_net   = isset( $order->vendor_earnings ) && null !== $order->vendor_earnings
						? (float) $order->vendor_earnings
						: $detail_gross;
					$detail_fee   = isset( $order->platform_fee ) && null !== $order->platform_fee
						? (float) $order->platform_fee
						: max( 0.0, $detail_gross - $detail_net );
					if ( abs( $detail_gross - $detail_net ) > 0.005 ) :
						?>
						<div class="wpss-order-detail-item">
							<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Platform Fee', 'wp-sell-services' ); ?></span>
							<span class="wpss-order-detail-item__value">−<?php echo esc_html( wpss_format_price( $detail_fee, $order->currency ) ); ?></span>
						</div>
						<div class="wpss-order-detail-item wpss-order-detail-item--highlight">
							<span class="wpss-order-detail-item__label"><?php esc_html_e( 'Your Earnings', 'wp-sell-services' ); ?></span>
							<span class="wpss-order-detail-item__value"><?php echo esc_html( wpss_format_price( $detail_net, $order->currency ) ); ?></span>
						</div>
						<?php
					endif;
				endif;
				?>
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
				<i data-lucide="image" class="wpss-icon" aria-hidden="true"></i>
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
									<i data-lucide="star" class="wpss-icon wpss-star-icon" aria-hidden="true"></i>
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
	// Requirements are defined at the service level. If the seller never
	// configured any, the whole section is hidden — an order-level "no
	// requirements" notice just reads like missing data to the buyer /
	// seller looking at a completed order. When the service DOES define
	// requirements, the section is always shown so both parties can see
	// the form, the submitted answers, or the 'not yet provided' notice.
	$show_requirements_form   = 'pending_requirements' === $order->status && $is_customer && $service_has_requirements && ! $has_submitted_requirements;
	$show_submitted_readonly  = $has_submitted_requirements && ( $is_vendor || $is_customer );
	$show_not_provided_notice = ! $has_submitted_requirements && $service_has_requirements && in_array( $order->status, array( 'in_progress', 'pending_approval', 'completed', 'delivered', 'late', 'revision_requested' ), true );
	$show_no_requirements_msg = false;

	// Allow late requirements submission if enabled in settings and order is in_progress without requirements.
	$allow_late_submission       = apply_filters( 'wpss_allow_late_requirements_submission', false );
	$show_late_requirements_form = $allow_late_submission && 'in_progress' === $order->status && $is_customer && $service_has_requirements && ! $has_submitted_requirements;
	?>

	<!-- Requirements Section (for pending_requirements status OR late submission) -->
	<?php if ( $show_requirements_form || $show_late_requirements_form ) : ?>
		<section class="wpss-order-section wpss-order-section--requirements">
			<div class="wpss-order-section__header">
				<h2 class="wpss-order-section__title">
					<i data-lucide="clipboard-check" class="wpss-icon" aria-hidden="true"></i>
					<?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?>
				</h2>
			</div>
			<div class="wpss-order-section__body">
				<?php if ( $show_late_requirements_form ) : ?>
					<div class="wpss-alert wpss-alert--warning" style="margin-bottom: 1.5rem;">
						<i data-lucide="triangle-alert" class="wpss-icon" aria-hidden="true"></i>
						<p><?php esc_html_e( 'Work has already started, but you can still submit requirements to help the seller complete your order.', 'wp-sell-services' ); ?></p>
					</div>
				<?php else : ?>
					<div class="wpss-alert wpss-alert--info" style="margin-bottom: 1.5rem;">
						<i data-lucide="info" class="wpss-icon" aria-hidden="true"></i>
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
										<i data-lucide="upload" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
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
								<i data-lucide="clipboard-check" class="wpss-icon" aria-hidden="true"></i>
								<?php esc_html_e( 'Submit Requirements', 'wp-sell-services' ); ?>
							</span>
							<span class="wpss-requirements-form__submit-loading" style="display: none;">
								<i data-lucide="loader-2" class="wpss-icon wpss-spinner" aria-hidden="true"></i>
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
					<i data-lucide="clipboard-check" class="wpss-icon" aria-hidden="true"></i>
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
									<i data-lucide="download" class="wpss-icon" aria-hidden="true"></i>
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
										<i data-lucide="chevron-down" class="wpss-icon wpss-expand-icon" aria-hidden="true"></i>
									</button>
								<?php endif; ?>
								<button type="button" class="wpss-requirement-view__copy-btn" data-copy-text="<?php echo esc_attr( $response_value ); ?>" title="<?php esc_attr_e( 'Copy to clipboard', 'wp-sell-services' ); ?>">
									<i data-lucide="copy" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
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
					<i data-lucide="clipboard-check" class="wpss-icon" aria-hidden="true"></i>
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
					<i data-lucide="clipboard-check" class="wpss-icon" aria-hidden="true"></i>
					<?php esc_html_e( 'Order Requirements', 'wp-sell-services' ); ?>
				</h2>
			</div>
			<div class="wpss-order-section__body">
				<div class="wpss-notice wpss-notice--info" style="padding: 15px; background: #f0f7ff; border-radius: 8px; border-left: 4px solid #3b82f6;">
					<p style="margin: 0; color: #1e3a5f;">
						<i data-lucide="info" class="wpss-icon" aria-hidden="true" style="vertical-align: middle; margin-right: 8px;"></i>
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
		$reason_label  = $reason_labels[ $cancel_reason ] ?? $cancel_reason;
		?>
		<section class="wpss-order-section">
			<div class="wpss-order-section__body">
				<div class="wpss-alert wpss-alert--warning" style="margin: 0;">
					<i data-lucide="triangle-alert" class="wpss-icon" aria-hidden="true"></i>
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
				<i data-lucide="clock" class="wpss-icon" aria-hidden="true"></i>
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

				<?php elseif ( 'disputed' === $order->status ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--completed">
						<div class="wpss-timeline__marker" style="background: var(--wpss-danger, #ef4444);"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Disputed', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline__date"><?php echo esc_html( $order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $order->updated_at->getTimestamp() ) : '' ); ?></span>
						</div>
					</div>

				<?php elseif ( 'rejected' === $order->status ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--completed">
						<div class="wpss-timeline__marker" style="background: var(--wpss-danger, #ef4444);"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Rejected', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline__date"><?php echo esc_html( $order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $order->updated_at->getTimestamp() ) : '' ); ?></span>
						</div>
					</div>

				<?php elseif ( 'revision_requested' === $order->status ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--completed">
						<div class="wpss-timeline__marker" style="background: var(--wpss-warning, #f59e0b);"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Revision Requested', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline__date"><?php echo esc_html( $order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $order->updated_at->getTimestamp() ) : '' ); ?></span>
						</div>
					</div>

				<?php elseif ( 'late' === $order->status ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--completed">
						<div class="wpss-timeline__marker" style="background: var(--wpss-warning, #f59e0b);"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Order Late', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline__date"><?php echo esc_html( $order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $order->updated_at->getTimestamp() ) : '' ); ?></span>
						</div>
					</div>

				<?php elseif ( 'pending_approval' === $order->status ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--completed">
						<div class="wpss-timeline__marker" style="background: var(--wpss-info, #3b82f6);"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Awaiting Approval', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline__date"><?php echo esc_html( $order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $order->updated_at->getTimestamp() ) : '' ); ?></span>
						</div>
					</div>

				<?php elseif ( 'on_hold' === $order->status ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--completed">
						<div class="wpss-timeline__marker" style="background: var(--wpss-warning, #f59e0b);"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'On Hold', 'wp-sell-services' ); ?></span>
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
					<i data-lucide="upload" class="wpss-icon" aria-hidden="true"></i>
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
										<i data-lucide="download" class="wpss-icon" aria-hidden="true"></i>
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
				<i data-lucide="message-square" class="wpss-icon" aria-hidden="true"></i>
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
					<i data-lucide="star" class="wpss-icon wpss-icon--lg wpss-review-cta__icon" aria-hidden="true"></i>
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

	<!-- Milestones timeline (parent order only — request-mode orders) -->
	<?php
	// Milestones are reserved for custom buyer-posted projects. Fixed-
	// price catalog orders use Extensions; the server-side guards back
	// this up, this just keeps the CTA from appearing where it doesn't
	// apply so the seller never has to ask 'which one do I use?'.
	$is_request_order          = 'request' === ( $order->platform ?? '' );
	$milestone_service         = new \WPSellServices\Services\MilestoneService();
	$milestones                = $is_request_order ? $milestone_service->get_for_parent( (int) $order_id ) : array();
	$milestone_active_statuses = array(
		\WPSellServices\Models\ServiceOrder::STATUS_PENDING_REQUIREMENTS,
		\WPSellServices\Models\ServiceOrder::STATUS_IN_PROGRESS,
		\WPSellServices\Models\ServiceOrder::STATUS_LATE,
		\WPSellServices\Models\ServiceOrder::STATUS_REVISION_REQUESTED,
		\WPSellServices\Models\ServiceOrder::STATUS_PENDING_APPROVAL,
	);
	$can_propose_milestone  = $is_vendor && $is_request_order && in_array( $order->status, $milestone_active_statuses, true );
	$show_milestone_section = $is_request_order && ( ! empty( $milestones ) || $can_propose_milestone );
	$milestone_currency = $order->currency ?? ( get_option( 'wpss_general', array() )['currency'] ?? 'USD' );

	if ( $show_milestone_section ) :
		$ms_approved_count  = 0;
		$ms_total_paid      = 0.0;
		foreach ( $milestones as $_m ) {
			if ( 'completed' === $_m['status'] ) {
				++$ms_approved_count;
				$ms_total_paid += (float) $_m['amount'];
			}
		}
		$ms_all_done_banner = ! empty( $milestones ) && $ms_approved_count === count( $milestones ) && 'completed' === $order->status;
		?>
		<?php if ( $ms_all_done_banner ) : ?>
			<section class="wpss-order-section wpss-order-section--milestone-wrap">
				<div class="wpss-milestone-wrap">
					<div class="wpss-milestone-wrap__icon" aria-hidden="true">
						<i data-lucide="check-circle-2" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
					</div>
					<div>
						<h3 class="wpss-milestone-wrap__title"><?php esc_html_e( 'Project complete', 'wp-sell-services' ); ?></h3>
						<p class="wpss-milestone-wrap__body">
							<?php
							printf(
								/* translators: 1: approved phase count, 2: total paid */
								esc_html__( 'All %1$d phases approved. Total paid: %2$s.', 'wp-sell-services' ),
								(int) $ms_approved_count,
								esc_html( wpss_format_price( $ms_total_paid, $milestone_currency ) )
							);
							?>
						</p>
					</div>
				</div>
			</section>
		<?php endif; ?>
		<section class="wpss-order-section wpss-order-section--milestones">
			<div class="wpss-order-section__header">
				<h2 class="wpss-order-section__title">
					<i data-lucide="flag" class="wpss-icon" aria-hidden="true"></i>
					<?php
					printf(
						/* translators: %d: milestone count */
						esc_html__( 'Milestones (%d)', 'wp-sell-services' ),
						count( $milestones )
					);
					?>
				</h2>
				<?php if ( $can_propose_milestone ) : ?>
					<button type="button" class="wpss-btn wpss-btn--primary wpss-btn--sm wpss-open-milestone-modal" data-order="<?php echo esc_attr( (int) $order_id ); ?>">
						<?php esc_html_e( '+ Propose a phase', 'wp-sell-services' ); ?>
					</button>
				<?php endif; ?>
			</div>
			<div class="wpss-order-section__body">
				<?php if ( empty( $milestones ) ) : ?>
					<p class="wpss-milestone-empty">
						<?php
						if ( $is_vendor ) {
							esc_html_e( 'This is a custom project — break it into paid phases so the buyer can approve each stage as you deliver.', 'wp-sell-services' );
						} else {
							esc_html_e( 'Your seller will propose the first phase. You pay each phase up front; they deliver and you approve.', 'wp-sell-services' );
						}
						?>
					</p>
				<?php else : ?>
					<ol class="wpss-milestone-list">
						<?php
						foreach ( $milestones as $index => $m ) :
							$ms_status     = $m['status'];
							$ms_sub_id     = (int) $m['id'];
							$ms_sub_url    = add_query_arg( 'order_id', $ms_sub_id, remove_query_arg( 'order_id' ) );
							$ms_pay_url    = add_query_arg( 'pay_order', $ms_sub_id, wpss_get_checkout_base_url() );
							$ms_state_label = '';
							$ms_state_class = 'wpss-ms-state--' . sanitize_html_class( $ms_status );

							switch ( $ms_status ) {
								case 'pending_payment':
									if ( ! empty( $m['is_locked'] ) ) {
										$ms_state_label = $is_buyer
											? __( 'Locked — finish the earlier phase first', 'wp-sell-services' )
											: __( 'Locked behind earlier phase', 'wp-sell-services' );
										$ms_state_class .= ' wpss-ms-state--locked';
									} else {
										$ms_state_label = $is_buyer ? __( 'Ready to pay', 'wp-sell-services' ) : __( 'Awaiting buyer payment', 'wp-sell-services' );
									}
									break;
								case 'in_progress':
									$ms_state_label = $is_vendor ? __( 'Paid · ready for delivery', 'wp-sell-services' ) : __( 'Paid · seller working', 'wp-sell-services' );
									break;
								case 'pending_approval':
									$ms_state_label = $is_buyer ? __( 'Delivered · awaiting your approval', 'wp-sell-services' ) : __( 'Submitted · awaiting buyer', 'wp-sell-services' );
									break;
								case 'completed':
									$ms_state_label = __( 'Approved · completed', 'wp-sell-services' );
									break;
								case 'cancelled':
									$ms_state_label = __( 'Cancelled', 'wp-sell-services' );
									break;
							}

							$ms_show_amount = $is_vendor && in_array( $ms_status, array( 'in_progress', 'pending_approval', 'completed' ), true ) && null !== $m['vendor_earnings']
								? (float) $m['vendor_earnings']
								: (float) $m['amount'];
							?>
							<li class="wpss-milestone-item <?php echo esc_attr( $ms_state_class ); ?>">
								<div class="wpss-milestone-item__head">
									<span class="wpss-milestone-item__num"><?php echo esc_html( (string) ( $index + 1 ) ); ?>.</span>
									<span class="wpss-milestone-item__title"><?php echo esc_html( '' !== $m['title'] ? $m['title'] : __( 'Untitled phase', 'wp-sell-services' ) ); ?></span>
									<span class="wpss-milestone-item__amount">
										<?php echo esc_html( wpss_format_price( $ms_show_amount, $milestone_currency ) ); ?>
										<?php if ( $is_vendor && in_array( $ms_status, array( 'in_progress', 'pending_approval', 'completed' ), true ) && null !== $m['vendor_earnings'] && abs( (float) $m['amount'] - (float) $m['vendor_earnings'] ) > 0.005 ) : ?>
											<small class="wpss-milestone-item__gross">
												<?php
												printf(
													/* translators: %s: buyer-paid amount */
													esc_html__( '(buyer paid %s)', 'wp-sell-services' ),
													esc_html( wpss_format_price( (float) $m['amount'], $milestone_currency ) )
												);
												?>
											</small>
										<?php endif; ?>
									</span>
								</div>
								<?php if ( '' !== $m['description'] ) : ?>
									<p class="wpss-milestone-item__description"><?php echo esc_html( $m['description'] ); ?></p>
								<?php endif; ?>
								<?php if ( '' !== $m['deliverables'] && $is_buyer && 'pending_payment' === $ms_status ) : ?>
									<p class="wpss-milestone-item__deliverables">
										<strong><?php esc_html_e( 'Deliverables:', 'wp-sell-services' ); ?></strong>
										<?php echo esc_html( $m['deliverables'] ); ?>
									</p>
								<?php endif; ?>
								<div class="wpss-milestone-item__meta">
									<span class="wpss-milestone-item__state"><?php echo esc_html( $ms_state_label ); ?></span>
								</div>
								<div class="wpss-milestone-item__actions">
									<?php if ( $is_buyer && 'pending_payment' === $ms_status ) : ?>
										<?php if ( ! empty( $m['is_locked'] ) ) : ?>
											<?php
											// Phase is locked until earlier phases finish. Show a
											// disabled-looking pill + locked icon + explicit copy
											// so the buyer understands why the Pay button is not
											// available — the server-side backstop in
											// StandaloneCheckoutProvider would otherwise throw a
											// generic error if they tried to hand-craft the URL.
											?>
											<span class="wpss-btn wpss-btn--locked wpss-btn--sm" aria-disabled="true" title="<?php esc_attr_e( 'This phase unlocks once the earlier phase is approved or cancelled.', 'wp-sell-services' ); ?>">
												<span class="wpss-btn__lock-icon" aria-hidden="true">
													<i data-lucide="lock" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
												</span>
												<?php esc_html_e( 'Locked — finish the earlier phase first', 'wp-sell-services' ); ?>
											</span>
										<?php else : ?>
											<a href="<?php echo esc_url( $ms_pay_url ); ?>" class="wpss-btn wpss-btn--primary wpss-btn--sm">
												<?php
												printf(
													/* translators: %s: amount */
													esc_html__( 'Accept & Pay %s', 'wp-sell-services' ),
													esc_html( wpss_format_price( (float) $m['amount'], $milestone_currency ) )
												);
												?>
											</a>
											<button type="button" class="wpss-btn wpss-btn--secondary wpss-btn--sm wpss-milestone-decline-btn" data-milestone="<?php echo esc_attr( $ms_sub_id ); ?>">
												<?php esc_html_e( 'Decline', 'wp-sell-services' ); ?>
											</button>
										<?php endif; ?>
									<?php endif; ?>
									<?php if ( $is_vendor && 'pending_payment' === $ms_status ) : ?>
										<button type="button" class="wpss-btn wpss-btn--secondary wpss-btn--sm wpss-milestone-delete-btn" data-milestone="<?php echo esc_attr( $ms_sub_id ); ?>">
											<?php esc_html_e( 'Cancel proposal', 'wp-sell-services' ); ?>
										</button>
									<?php endif; ?>
									<?php if ( $is_vendor && in_array( $ms_status, array( 'in_progress', 'pending_approval' ), true ) ) : ?>
										<a href="<?php echo esc_url( $ms_sub_url ); ?>" class="wpss-btn wpss-btn--primary wpss-btn--sm">
											<?php echo esc_html( 'pending_approval' === $ms_status ? __( 'View / resubmit', 'wp-sell-services' ) : __( 'Submit delivery', 'wp-sell-services' ) ); ?>
										</a>
									<?php endif; ?>
									<?php if ( $is_buyer && 'pending_approval' === $ms_status ) : ?>
										<a href="<?php echo esc_url( $ms_sub_url ); ?>" class="wpss-btn wpss-btn--primary wpss-btn--sm">
											<?php esc_html_e( 'Review & approve', 'wp-sell-services' ); ?>
										</a>
									<?php endif; ?>
									<a href="<?php echo esc_url( $ms_sub_url ); ?>" class="wpss-btn wpss-btn--outline wpss-btn--sm">
										<?php esc_html_e( 'View phase', 'wp-sell-services' ); ?>
									</a>
								</div>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php endif; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $can_propose_milestone ) : ?>
		<!-- Propose Milestone Modal (vendor only) -->
		<div class="wpss-modal wpss-extension-modal" id="wpss-milestone-modal" data-order="<?php echo esc_attr( (int) $order_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="wpss-ms-modal-title" hidden>
			<div class="wpss-modal__backdrop"></div>
			<div class="wpss-modal__dialog">
				<div class="wpss-modal__header">
					<h3 id="wpss-ms-modal-title" class="wpss-modal__title"><?php esc_html_e( 'Propose a phase', 'wp-sell-services' ); ?></h3>
					<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
						<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
					</button>
				</div>
				<div class="wpss-modal__body">
					<p class="wpss-modal__intro"><?php esc_html_e( 'Break the work into a named phase the buyer can pay for up front. Once paid, you can start work and submit delivery here when done.', 'wp-sell-services' ); ?></p>
					<form class="wpss-milestone-form" data-order="<?php echo esc_attr( (int) $order_id ); ?>">
						<?php wp_nonce_field( 'wpss_propose_milestone', 'wpss_milestone_nonce' ); ?>
						<div class="wpss-form-row">
							<label for="wpss-ms-title"><?php esc_html_e( 'Phase title', 'wp-sell-services' ); ?></label>
							<input type="text" id="wpss-ms-title" name="title" required maxlength="120" class="wpss-input" placeholder="<?php esc_attr_e( 'e.g. Phase 1 — Concepts & Wireframes', 'wp-sell-services' ); ?>">
						</div>
						<div class="wpss-form-row">
							<label for="wpss-ms-description"><?php esc_html_e( 'Description (buyer sees this)', 'wp-sell-services' ); ?></label>
							<textarea id="wpss-ms-description" name="description" rows="3" class="wpss-textarea" placeholder="<?php esc_attr_e( 'What the buyer is paying for in this phase.', 'wp-sell-services' ); ?>"></textarea>
						</div>
						<div class="wpss-field-row">
							<div class="wpss-form-row">
								<label for="wpss-ms-amount"><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></label>
								<input type="number" step="0.01" min="1" id="wpss-ms-amount" name="amount" required class="wpss-input" placeholder="50.00">
							</div>
							<div class="wpss-form-row">
								<label for="wpss-ms-days"><?php esc_html_e( 'Days to deliver', 'wp-sell-services' ); ?></label>
								<input type="number" min="0" id="wpss-ms-days" name="days" class="wpss-input" placeholder="3">
							</div>
						</div>
						<div class="wpss-form-row">
							<label for="wpss-ms-deliverables"><?php esc_html_e( 'Deliverables (optional)', 'wp-sell-services' ); ?></label>
							<textarea id="wpss-ms-deliverables" name="deliverables" rows="3" class="wpss-textarea" placeholder="<?php esc_attr_e( 'e.g. Low-fi sketches + 2 direction options + Figma file', 'wp-sell-services' ); ?>"></textarea>
						</div>
						<div class="wpss-modal__feedback" role="status" aria-live="polite" hidden></div>
						<div class="wpss-modal__footer">
							<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__cancel"><?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?></button>
							<button type="submit" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Send to buyer', 'wp-sell-services' ); ?></button>
						</div>
					</form>
				</div>
			</div>
		</div>

		<script>
		(function () {
			var modal = document.getElementById('wpss-milestone-modal');
			if (!modal) return;
			var form = modal.querySelector('.wpss-milestone-form');
			var feedback = modal.querySelector('.wpss-modal__feedback');

			// Dashboard CSS gates .wpss-modal visibility on the
			// .wpss-modal-open class, not the [hidden] attribute — toggling
			// .hidden alone leaves 'display: none' winning from the default
			// rule, which is why the Propose button appeared to do nothing.
			document.querySelectorAll('.wpss-open-milestone-modal').forEach(function (b) { b.addEventListener('click', function () { modal.hidden = false; modal.classList.add('wpss-modal-open'); try { document.dispatchEvent(new CustomEvent('wpss:icons:refresh')); } catch(_e){} }); });
			modal.querySelectorAll('.wpss-modal__close, .wpss-modal__cancel, .wpss-modal__backdrop').forEach(function (b) { b.addEventListener('click', function () { modal.hidden = true; modal.classList.remove('wpss-modal-open'); }); });

			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var data = new FormData(form);
				data.append('action', 'wpss_propose_milestone');
				data.append('order_id', form.dataset.order);
				data.append('_ajax_nonce', data.get('wpss_milestone_nonce'));
				var submitBtn = form.querySelector('button[type=submit]');
				submitBtn.disabled = true;
				fetch(window.ajaxurl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', credentials: 'include', body: data })
					.then(function (r) { return r.json(); })
					.then(function (res) {
						submitBtn.disabled = false;
						if (res && res.success) {
							feedback.hidden = false;
							feedback.className = 'wpss-modal__feedback wpss-modal__feedback--success';
							feedback.textContent = (res.data && res.data.message) || '<?php echo esc_js( __( 'Sent.', 'wp-sell-services' ) ); ?>';
							setTimeout(function () { window.location.reload(); }, 700);
						} else {
							feedback.hidden = false;
							feedback.className = 'wpss-modal__feedback wpss-modal__feedback--error';
							feedback.textContent = (res && res.data && res.data.message) || '<?php echo esc_js( __( 'Error', 'wp-sell-services' ) ); ?>';
						}
					})
					.catch(function () {
						submitBtn.disabled = false;
						feedback.hidden = false;
						feedback.className = 'wpss-modal__feedback wpss-modal__feedback--error';
						feedback.textContent = '<?php echo esc_js( __( 'Network error.', 'wp-sell-services' ) ); ?>';
					});
			});
		}());
		</script>
	<?php endif; ?>

	<?php if ( ! empty( $milestones ) && $is_buyer ) : ?>
		<script>
		(function () {
			var ajaxurl = window.ajaxurl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
			var nonce = '<?php echo esc_js( wp_create_nonce( 'wpss_milestone_action' ) ); ?>';
			document.querySelectorAll('.wpss-milestone-decline-btn').forEach(function (btn) {
				btn.addEventListener('click', function () {
					if (!confirm('<?php echo esc_js( __( "Decline this phase? Your seller can propose a revised one.", 'wp-sell-services' ) ); ?>')) return;
					btn.disabled = true;
					var data = new FormData();
					data.append('action', 'wpss_decline_milestone');
					data.append('milestone_id', btn.dataset.milestone);
					data.append('_ajax_nonce', nonce);
					fetch(ajaxurl, { method: 'POST', credentials: 'include', body: data })
						.then(function (r) { return r.json(); })
						.then(function (res) {
							if (res && res.success) window.location.reload();
							else { btn.disabled = false; alert((res && res.data && res.data.message) || 'Error'); }
						});
				});
			});
		}());
		</script>
	<?php endif; ?>

	<?php if ( ! empty( $milestones ) && $is_vendor ) : ?>
		<script>
		(function () {
			var ajaxurl = window.ajaxurl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
			var nonce = '<?php echo esc_js( wp_create_nonce( 'wpss_milestone_action' ) ); ?>';
			document.querySelectorAll('.wpss-milestone-delete-btn').forEach(function (btn) {
				btn.addEventListener('click', function () {
					if (!confirm('<?php echo esc_js( __( "Cancel this phase proposal?", 'wp-sell-services' ) ); ?>')) return;
					btn.disabled = true;
					var data = new FormData();
					data.append('action', 'wpss_delete_milestone');
					data.append('milestone_id', btn.dataset.milestone);
					data.append('_ajax_nonce', nonce);
					fetch(ajaxurl, { method: 'POST', credentials: 'include', body: data })
						.then(function (r) { return r.json(); })
						.then(function (res) {
							if (res && res.success) window.location.reload();
							else { btn.disabled = false; alert((res && res.data && res.data.message) || 'Error'); }
						});
				});
			});
		}());
		</script>
	<?php endif; ?>

	<!-- Extension Request (active order only) -->
	<?php
	$extension_service = new \WPSellServices\Services\ExtensionOrderService();
	$pending_extension = $extension_service->get_pending_request( (int) $order_id );
	$extension_active_statuses = array(
		\WPSellServices\Models\ServiceOrder::STATUS_IN_PROGRESS,
		\WPSellServices\Models\ServiceOrder::STATUS_LATE,
		\WPSellServices\Models\ServiceOrder::STATUS_REVISION_REQUESTED,
		\WPSellServices\Models\ServiceOrder::STATUS_PENDING_APPROVAL,
	);
	// Extensions are for fixed-price catalog orders only. Request-mode
	// orders run on the milestone payment model instead, so the two CTAs
	// never appear on the same order.
	$can_request_extension = $is_vendor
		&& ! $is_request_order
		&& null === $pending_extension
		&& in_array( $order->status, $extension_active_statuses, true );
	$buyer_sees_pending_extension = $is_customer && ! $is_request_order && null !== $pending_extension;

	if ( $buyer_sees_pending_extension ) :
		$ext_pay_url = $pending_extension->pay_order_id
			? add_query_arg( 'pay_order', (int) $pending_extension->pay_order_id, wpss_get_checkout_base_url() )
			: '';
		$ext_currency = $order->currency ?? ( get_option( 'wpss_general', array() )['currency'] ?? 'USD' );
		?>
		<section class="wpss-order-section">
			<div class="wpss-extension-pending-card">
				<h3 class="wpss-extension-pending-card__title">
					<i data-lucide="clock" class="wpss-icon" aria-hidden="true"></i>
					<?php esc_html_e( 'Quote for extra work', 'wp-sell-services' ); ?>
				</h3>
				<p class="wpss-extension-pending-card__body">
					<?php esc_html_e( 'Your seller sent a quote for additional work on this order. Review the details — pay to approve and they will continue with the expanded scope, or decline and discuss first in chat.', 'wp-sell-services' ); ?>
				</p>
				<div class="wpss-extension-pending-card__meta">
					<div>
						<strong><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></strong>
						<?php echo esc_html( wpss_format_price( (float) $pending_extension->amount, $ext_currency ) ); ?>
					</div>
					<div>
						<strong><?php esc_html_e( 'Extra days', 'wp-sell-services' ); ?></strong>
						<?php
						printf(
							/* translators: %d: extra days */
							esc_html( _n( '%d day', '%d days', (int) $pending_extension->extra_days, 'wp-sell-services' ) ),
							absint( $pending_extension->extra_days )
						);
						?>
					</div>
					<?php if ( ! empty( $pending_extension->reason ) ) : ?>
						<div style="grid-column:1/-1;">
							<strong><?php esc_html_e( 'Reason', 'wp-sell-services' ); ?></strong>
							<?php echo esc_html( $pending_extension->reason ); ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="wpss-extension-pending-card__actions">
					<?php if ( $ext_pay_url ) : ?>
						<a href="<?php echo esc_url( $ext_pay_url ); ?>" class="wpss-btn wpss-btn--primary">
							<?php
							printf(
								/* translators: %s: formatted amount */
								esc_html__( 'Accept & Pay %s', 'wp-sell-services' ),
								esc_html( wpss_format_price( (float) $pending_extension->amount, $ext_currency ) )
							);
							?>
						</a>
					<?php endif; ?>
					<button type="button" class="wpss-btn wpss-btn--secondary wpss-extension-decline-btn"
						data-request="<?php echo esc_attr( (int) $pending_extension->id ); ?>"
						data-order="<?php echo esc_attr( (int) $order_id ); ?>">
						<?php esc_html_e( 'Decline', 'wp-sell-services' ); ?>
					</button>
				</div>
			</div>
		</section>
	<?php elseif ( $is_vendor && null !== $pending_extension ) : ?>
		<section class="wpss-order-section">
			<div class="wpss-extension-pending-card">
				<h3 class="wpss-extension-pending-card__title">
					<i data-lucide="clock" class="wpss-icon" aria-hidden="true"></i>
					<?php esc_html_e( 'Extra work awaiting buyer payment', 'wp-sell-services' ); ?>
				</h3>
				<p class="wpss-extension-pending-card__body">
					<?php
					$ext_currency = $order->currency ?? ( get_option( 'wpss_general', array() )['currency'] ?? 'USD' );
					printf(
						/* translators: 1: amount, 2: days */
						esc_html__( 'You requested %1$s / %2$s. Buyer has not responded yet.', 'wp-sell-services' ),
						esc_html( wpss_format_price( (float) $pending_extension->amount, $ext_currency ) ),
						esc_html( sprintf( _n( '%d day', '%d days', (int) $pending_extension->extra_days, 'wp-sell-services' ), absint( $pending_extension->extra_days ) ) )
					);
					?>
				</p>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $can_request_extension ) : ?>
		<section class="wpss-order-section wpss-order-section--extension-cta">
			<div class="wpss-tip-cta">
				<i data-lucide="clock" class="wpss-icon wpss-icon--lg wpss-tip-cta__icon" aria-hidden="true"></i>
				<h3 class="wpss-tip-cta__title"><?php esc_html_e( 'Need to quote extra work?', 'wp-sell-services' ); ?></h3>
				<p class="wpss-tip-cta__text"><?php esc_html_e( 'Fixed-price order, buyer already paid the base. For small add-ons on top, quote the extra amount and time here. Once they pay, keep going on the expanded scope.', 'wp-sell-services' ); ?></p>
				<button type="button" class="wpss-btn wpss-btn--primary wpss-btn--lg wpss-open-extension-modal"
					data-order="<?php echo esc_attr( (int) $order_id ); ?>">
					<?php esc_html_e( 'Send quote to buyer', 'wp-sell-services' ); ?>
				</button>
			</div>
		</section>
	<?php endif; ?>

	<!-- Tip CTA (for completed orders, buyer only, once per order) -->
	<?php
	if ( 'completed' === $order->status && $is_customer ) :
		$tipping_service = new \WPSellServices\Services\TippingService();
		$already_tipped  = $tipping_service->has_tipped( $order_id, get_current_user_id() );
		$currency        = get_option( 'wpss_general', array() )['currency'] ?? 'USD';

		// Buyer-facing receipt should show the gross amount the buyer paid
		// (tip order total), not the net the vendor received. Fetch the
		// related tip order by parent order ID.
		$tip_order_row = null;
		if ( $already_tipped ) {
			$tip_order_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT total, status FROM {$wpdb->prefix}wpss_orders
					WHERE platform = %s AND platform_order_id = %d AND customer_id = %d
					ORDER BY id DESC LIMIT 1",
					\WPSellServices\Services\TippingService::ORDER_TYPE,
					$order_id,
					get_current_user_id()
				)
			);
		}
		?>
		<section class="wpss-order-section wpss-order-section--tip">
			<?php if ( $already_tipped && $tip_order_row ) : ?>
				<?php $is_paid = 'completed' === $tip_order_row->status; ?>
				<div class="wpss-tip-receipt">
					<i data-lucide="check-circle-2" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
					<div>
						<h3 class="wpss-tip-receipt__title">
							<?php echo esc_html( $is_paid ? __( 'Tip sent', 'wp-sell-services' ) : __( "Tip not sent yet — payment didn't complete", 'wp-sell-services' ) ); ?>
						</h3>
						<p class="wpss-tip-receipt__amount">
							<?php
							echo esc_html(
								function_exists( 'wpss_format_price' )
									? wpss_format_price( (float) $tip_order_row->total, $currency )
									: number_format_i18n( (float) $tip_order_row->total, 2 ) . ' ' . $currency
							);
							?>
						</p>
					</div>
				</div>
			<?php else : ?>
				<div class="wpss-tip-cta">
					<i data-lucide="heart" class="wpss-icon wpss-icon--lg wpss-tip-cta__icon" aria-hidden="true"></i>
					<h3 class="wpss-tip-cta__title"><?php esc_html_e( 'Say thanks with a tip', 'wp-sell-services' ); ?></h3>
					<p class="wpss-tip-cta__text"><?php esc_html_e( 'Loved the work? A tip goes straight to the vendor on top of the order total.', 'wp-sell-services' ); ?></p>
					<button type="button" class="wpss-btn wpss-btn--primary wpss-btn--lg wpss-open-tip-modal"
							data-order="<?php echo esc_attr( (string) $order_id ); ?>">
						<?php esc_html_e( 'Send a tip', 'wp-sell-services' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>

<?php if ( 'completed' === $order->status && $is_customer && empty( $already_tipped ) ) : ?>
<!-- Tip Modal -->
<div class="wpss-modal" id="wpss-tip-modal" data-order="<?php echo esc_attr( (string) $order_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="wpss-tip-modal-title">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 id="wpss-tip-modal-title" class="wpss-modal__title"><?php esc_html_e( 'Send a tip', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
			</button>
		</div>
		<form class="wpss-tip-form" id="wpss-tip-form">
			<div class="wpss-modal__body">
				<p class="wpss-tip-form__lead">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: vendor display name */
							__( 'Send a tip to %s — it is credited to their wallet after the platform fee.', 'wp-sell-services' ),
							$other_party ? $other_party->display_name : __( 'the vendor', 'wp-sell-services' )
						)
					);
					?>
				</p>

				<div class="wpss-tip-form__amounts">
					<?php
					$quick_amounts = apply_filters( 'wpss_tip_quick_amounts', array( 5, 10, 20, 50 ), $order );
					foreach ( $quick_amounts as $preset ) :
						?>
						<button type="button" class="wpss-tip-form__preset" data-amount="<?php echo esc_attr( (string) $preset ); ?>">
							<?php
							echo esc_html(
								function_exists( 'wpss_format_price' )
									? wpss_format_price( (float) $preset, $currency )
									: number_format_i18n( (float) $preset, 0 ) . ' ' . $currency
							);
							?>
						</button>
					<?php endforeach; ?>
				</div>

				<div class="wpss-tip-form__field">
					<label for="wpss-tip-amount"><?php esc_html_e( 'Custom amount', 'wp-sell-services' ); ?></label>
					<input type="number" id="wpss-tip-amount" name="amount" class="wpss-input" min="1" step="0.01" required>
				</div>

				<div class="wpss-tip-form__field">
					<label for="wpss-tip-message"><?php esc_html_e( 'Message (optional)', 'wp-sell-services' ); ?></label>
					<textarea id="wpss-tip-message" name="message" class="wpss-textarea" rows="3" maxlength="500" placeholder="<?php esc_attr_e( 'Thanks for the great work!', 'wp-sell-services' ); ?>"></textarea>
				</div>

				<div class="wpss-tip-form__error" hidden></div>
			</div>
			<div class="wpss-modal__footer">
				<button type="button" class="wpss-btn wpss-modal__close-btn"><?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?></button>
				<button type="submit" class="wpss-btn wpss-btn--primary wpss-tip-form__submit">
					<?php esc_html_e( 'Send tip', 'wp-sell-services' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<?php
// Check if delivery modal should be available.
$can_deliver = $is_vendor && in_array( $order->status, array( 'in_progress', 'revision_requested', 'late' ), true );

// Check if review modal should be available.
$can_review           = 'completed' === $order->status && $is_customer && empty( $review_exists );
$can_open_dispute     = $can_open_dispute && ( $is_customer || $is_vendor );
$can_request_revision = $is_customer && 'pending_approval' === $order->status && $order->can_request_revision();

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
<div class="wpss-modal" id="wpss-deliver-modal" data-order="<?php echo esc_attr( $order_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="wpss-deliver-modal-title">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 id="wpss-deliver-modal-title" class="wpss-modal__title"><?php esc_html_e( 'Submit Delivery', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
			</button>
		</div>
		<form class="wpss-deliver-form" id="wpss-deliver-form">
			<?php wp_nonce_field( 'wpss_order_action', 'nonce' ); ?>
			<input type="hidden" name="action" value="wpss_deliver_order">
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

			<div class="wpss-modal__body">
				<div class="wpss-alert wpss-alert--info">
					<i data-lucide="info" class="wpss-icon" aria-hidden="true"></i>
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
							<i data-lucide="upload" class="wpss-icon" aria-hidden="true"></i>
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
					<i data-lucide="check" class="wpss-icon" aria-hidden="true"></i>
					<?php esc_html_e( 'Submit Delivery', 'wp-sell-services' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<?php if ( $can_review ) : ?>
<!-- Review Modal -->
<div class="wpss-modal" id="wpss-review-modal" data-order="<?php echo esc_attr( $order_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="wpss-review-modal-title">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 id="wpss-review-modal-title" class="wpss-modal__title"><?php esc_html_e( 'Write a Review', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
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
								<i data-lucide="star" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
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
<div class="wpss-modal" id="wpss-revision-modal" data-order="<?php echo esc_attr( $order_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="wpss-revision-modal-title">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 id="wpss-revision-modal-title" class="wpss-modal__title"><?php esc_html_e( 'Request Revision', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
			</button>
		</div>
		<form class="wpss-revision-form" id="wpss-revision-form">
			<?php wp_nonce_field( 'wpss_order_action', 'nonce' ); ?>
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

			<div class="wpss-modal__body">
				<div class="wpss-alert wpss-alert--info">
					<i data-lucide="info" class="wpss-icon" aria-hidden="true"></i>
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
<div class="wpss-modal" id="wpss-dispute-modal" data-order="<?php echo esc_attr( $order_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="wpss-dispute-modal-title">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 id="wpss-dispute-modal-title" class="wpss-modal__title"><?php esc_html_e( 'Open Dispute', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
			</button>
		</div>
		<form class="wpss-dispute-form" id="wpss-dispute-form">
			<?php wp_nonce_field( 'wpss_open_dispute', 'wpss_dispute_nonce' ); ?>
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

			<div class="wpss-modal__body">
				<div class="wpss-alert wpss-alert--warning">
					<i data-lucide="triangle-alert" class="wpss-icon" aria-hidden="true"></i>
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
<div class="wpss-modal" id="wpss-cancel-modal" data-order="<?php echo esc_attr( $order_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="wpss-cancel-modal-title">
	<div class="wpss-modal__backdrop"></div>
	<div class="wpss-modal__dialog">
		<div class="wpss-modal__header">
			<h3 id="wpss-cancel-modal-title" class="wpss-modal__title"><?php esc_html_e( 'Cancel Order', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
				<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
			</button>
		</div>
		<form class="wpss-cancel-form" id="wpss-cancel-form">
			<?php wp_nonce_field( 'wpss_order_action', 'nonce' ); ?>
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

			<div class="wpss-modal__body">
				<?php if ( $can_cancel_request ) : ?>
					<div class="wpss-alert wpss-alert--info">
						<i data-lucide="info" class="wpss-icon" aria-hidden="true"></i>
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

<?php if ( $is_vendor && in_array( $order->status, $extension_active_statuses, true ) ) : ?>
	<!-- Request Extension Modal -->
	<div class="wpss-modal wpss-extension-modal" id="wpss-extension-modal" data-order="<?php echo esc_attr( (int) $order_id ); ?>" role="dialog" aria-modal="true" aria-labelledby="wpss-extension-modal-title" hidden>
		<div class="wpss-modal__backdrop"></div>
		<div class="wpss-modal__dialog">
			<div class="wpss-modal__header">
				<h3 id="wpss-extension-modal-title" class="wpss-modal__title"><?php esc_html_e( 'Quote Extra Work', 'wp-sell-services' ); ?></h3>
				<button type="button" class="wpss-modal__close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
					<i data-lucide="x" class="wpss-icon" aria-hidden="true"></i>
				</button>
			</div>
			<div class="wpss-modal__body">
				<p class="wpss-modal__intro">
					<?php esc_html_e( 'The buyer requested extra work on top of the original scope. Quote how much and how many more days you need — they pay through the same checkout, and once payment clears you can continue on the extended scope.', 'wp-sell-services' ); ?>
				</p>
				<form class="wpss-extension-form" data-order="<?php echo esc_attr( (int) $order_id ); ?>">
					<?php wp_nonce_field( 'wpss_request_extension', 'wpss_extension_nonce' ); ?>
					<div class="wpss-field-row">
						<div class="wpss-form-row">
							<label for="wpss-ext-amount"><?php esc_html_e( 'Extra amount', 'wp-sell-services' ); ?></label>
							<input type="number" step="0.01" min="1" id="wpss-ext-amount" name="amount" required class="wpss-input" placeholder="50.00">
						</div>
						<div class="wpss-form-row">
							<label for="wpss-ext-days"><?php esc_html_e( 'Extra days', 'wp-sell-services' ); ?></label>
							<input type="number" min="1" max="<?php echo esc_attr( (int) get_option( 'wpss_max_extension_days', 14 ) ); ?>" id="wpss-ext-days" name="extra_days" required class="wpss-input" placeholder="3">
						</div>
					</div>
					<div class="wpss-form-row">
						<label for="wpss-ext-reason"><?php esc_html_e( 'Describe the extra work (buyer sees this)', 'wp-sell-services' ); ?></label>
						<textarea id="wpss-ext-reason" name="reason" rows="4" required minlength="10" class="wpss-textarea" placeholder="<?php esc_attr_e( 'e.g. Added a second logo variation and source files, per your message on Mar 12.', 'wp-sell-services' ); ?>"></textarea>
					</div>
					<div class="wpss-modal__feedback" role="status" aria-live="polite" hidden></div>
					<div class="wpss-modal__footer">
						<button type="button" class="wpss-btn wpss-btn--secondary wpss-modal__cancel"><?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?></button>
						<button type="submit" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Send Payment Link', 'wp-sell-services' ); ?></button>
					</div>
				</form>
			</div>
		</div>
	</div>

	<script>
	(function () {
		var modal = document.getElementById('wpss-extension-modal');
		if (!modal) return;
		var openBtns = document.querySelectorAll('.wpss-open-extension-modal');
		var closeBtns = modal.querySelectorAll('.wpss-modal__close, .wpss-modal__cancel, .wpss-modal__backdrop');
		var form = modal.querySelector('.wpss-extension-form');
		var feedback = modal.querySelector('.wpss-modal__feedback');

		// Same class-based visibility gate the milestone modal uses: the
		// dashboard CSS default is display:none and the .wpss-modal-open
		// class is what flips it to flex. Toggling .hidden alone silently
		// no-ops because display:none wins specificity.
		function show() { modal.hidden = false; modal.classList.add('wpss-modal-open'); try { document.dispatchEvent(new CustomEvent('wpss:icons:refresh')); } catch(_e){} }
		function hide() { modal.hidden = true; modal.classList.remove('wpss-modal-open'); if (feedback) { feedback.hidden = true; feedback.textContent = ''; feedback.className = 'wpss-modal__feedback'; } }

		openBtns.forEach(function (btn) { btn.addEventListener('click', show); });
		closeBtns.forEach(function (btn) { btn.addEventListener('click', hide); });

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var data = new FormData(form);
			data.append('action', 'wpss_request_extension');
			data.append('order_id', modal.dataset.order);
			data.append('_ajax_nonce', data.get('wpss_extension_nonce'));
			var submitBtn = form.querySelector('button[type=submit]');
			submitBtn.disabled = true;

			fetch(window.ajaxurl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
				method: 'POST',
				credentials: 'include',
				body: data
			}).then(function (r) { return r.json(); }).then(function (res) {
				submitBtn.disabled = false;
				if (res && res.success) {
					feedback.hidden = false;
					feedback.className = 'wpss-modal__feedback wpss-modal__feedback--success';
					feedback.textContent = (res.data && res.data.message) || '<?php echo esc_js( __( 'Quote sent.', 'wp-sell-services' ) ); ?>';
					setTimeout(function () { window.location.reload(); }, 800);
				} else {
					feedback.hidden = false;
					feedback.className = 'wpss-modal__feedback wpss-modal__feedback--error';
					feedback.textContent = (res && res.data && res.data.message) || '<?php echo esc_js( __( 'Could not send quote.', 'wp-sell-services' ) ); ?>';
				}
			}).catch(function () {
				submitBtn.disabled = false;
				feedback.hidden = false;
				feedback.className = 'wpss-modal__feedback wpss-modal__feedback--error';
				feedback.textContent = '<?php echo esc_js( __( 'Network error. Try again.', 'wp-sell-services' ) ); ?>';
			});
		});
	}());
	</script>
<?php endif; ?>

<?php if ( $is_customer && null !== ( $pending_extension ?? null ) ) : ?>
	<script>
	(function () {
		var buttons = document.querySelectorAll('.wpss-extension-decline-btn');
		if (!buttons.length) return;
		buttons.forEach(function (btn) {
			btn.addEventListener('click', function () {
				if (!confirm('<?php echo esc_js( __( 'Decline this extra-work quote?', 'wp-sell-services' ) ); ?>')) return;
				btn.disabled = true;
				var data = new FormData();
				data.append('action', 'wpss_decline_extension');
				data.append('request_id', btn.dataset.request || '');
				data.append('_ajax_nonce', '<?php echo esc_js( wp_create_nonce( 'wpss_decline_extension' ) ); ?>');
				fetch(window.ajaxurl || '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					credentials: 'include',
					body: data
				}).then(function (r) { return r.json(); }).then(function (res) {
					if (res && res.success) {
						window.location.reload();
					} else {
						btn.disabled = false;
						alert((res && res.data && res.data.message) || '<?php echo esc_js( __( 'Could not decline.', 'wp-sell-services' ) ); ?>');
					}
				}).catch(function () {
					btn.disabled = false;
					alert('<?php echo esc_js( __( 'Network error.', 'wp-sell-services' ) ); ?>');
				});
			});
		});
	}());
	</script>
<?php endif; ?>

<?php
/**
 * Hook: wpss_after_order_view
 *
 * @param object $order Order object.
 */
do_action( 'wpss_after_order_view', $order );
?>
