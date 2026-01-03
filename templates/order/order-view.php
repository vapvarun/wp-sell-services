<?php
/**
 * Template: Order View
 *
 * Displays a single order with conversation and actions.
 * Uses CSS classes from orders.css design system.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var int $order_id Order ID passed from parent template.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $order_id ) ) {
	return;
}

// Enqueue orders styles.
wp_enqueue_style( 'wpss-orders', WPSS_PLUGIN_URL . 'assets/css/orders.css', array( 'wpss-design-system' ), WPSS_VERSION );

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

// Get messages.
global $wpdb;
$messages_table = $wpdb->prefix . 'wpss_order_messages';
$messages       = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$messages_table} WHERE order_id = %d ORDER BY created_at ASC",
		$order_id
	)
);

// Get deliverables.
$deliverables_table = $wpdb->prefix . 'wpss_order_deliverables';
$deliverables       = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$deliverables_table} WHERE order_id = %d ORDER BY created_at DESC",
		$order_id
	)
);
?>

<div class="wpss-order-detail">
	<div class="wpss-order-detail__header">
		<div class="wpss-order-detail__info">
			<a href="<?php echo esc_url( wpss_get_dashboard_url( 'orders' ) ); ?>" class="wpss-btn wpss-btn--text">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<line x1="19" y1="12" x2="5" y2="12"></line>
					<polyline points="12 19 5 12 12 5"></polyline>
				</svg>
				<?php esc_html_e( 'Back to Orders', 'wp-sell-services' ); ?>
			</a>
			<h1>
				<?php
				printf(
					/* translators: %s: order number */
					esc_html__( 'Order #%s', 'wp-sell-services' ),
					esc_html( $order->order_number )
				);
				?>
			</h1>
			<span class="wpss-badge wpss-badge--status-<?php echo esc_attr( str_replace( '_', '-', $order->status ) ); ?>">
				<?php echo esc_html( wpss_get_order_status_label( $order->status ) ); ?>
			</span>
		</div>

		<?php if ( in_array( $order->status, array( 'pending', 'accepted', 'in_progress', 'pending_approval' ), true ) ) : ?>
			<div class="wpss-order-detail__actions">
				<?php if ( $is_vendor ) : ?>
					<?php if ( 'pending' === $order->status ) : ?>
						<button type="button" class="wpss-btn wpss-btn--success wpss-order-action"
								data-action="accept" data-order="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'Accept Order', 'wp-sell-services' ); ?>
						</button>
						<button type="button" class="wpss-btn wpss-btn--danger-outline wpss-order-action"
								data-action="reject" data-order="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'Decline', 'wp-sell-services' ); ?>
						</button>
					<?php endif; ?>

					<?php if ( in_array( $order->status, array( 'accepted', 'requirements_submitted' ), true ) ) : ?>
						<button type="button" class="wpss-btn wpss-btn--primary wpss-order-action"
								data-action="start" data-order="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'Start Working', 'wp-sell-services' ); ?>
						</button>
					<?php endif; ?>

					<?php if ( 'in_progress' === $order->status ) : ?>
						<button type="button" class="wpss-btn wpss-btn--success wpss-deliver-btn"
								data-order="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'Deliver Work', 'wp-sell-services' ); ?>
						</button>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $is_customer ) : ?>
					<?php if ( 'pending_approval' === $order->status ) : ?>
						<button type="button" class="wpss-btn wpss-btn--success wpss-order-action"
								data-action="complete" data-order="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'Accept & Complete', 'wp-sell-services' ); ?>
						</button>
						<button type="button" class="wpss-btn wpss-btn--secondary wpss-revision-btn"
								data-order="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'Request Revision', 'wp-sell-services' ); ?>
						</button>
					<?php endif; ?>

					<?php if ( in_array( $order->status, array( 'pending', 'accepted' ), true ) ) : ?>
						<button type="button" class="wpss-btn wpss-btn--secondary wpss-order-action"
								data-action="cancel" data-order="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'Cancel Order', 'wp-sell-services' ); ?>
						</button>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( in_array( $order->status, array( 'in_progress', 'pending_approval' ), true ) ) : ?>
					<button type="button" class="wpss-btn wpss-btn--danger-outline wpss-dispute-btn"
							data-order="<?php echo esc_attr( $order_id ); ?>">
						<?php esc_html_e( 'Open Dispute', 'wp-sell-services' ); ?>
					</button>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="wpss-order-detail__grid">
		<div class="wpss-order-detail__main">
			<!-- Service Info -->
			<div class="wpss-order-card">
				<div class="wpss-order-card__body">
					<div class="wpss-order-summary__service">
						<?php if ( $service && has_post_thumbnail( $service->ID ) ) : ?>
							<img src="<?php echo esc_url( get_the_post_thumbnail_url( $service->ID, 'thumbnail' ) ); ?>"
								alt="<?php echo esc_attr( $service->post_title ); ?>"
								class="wpss-order-summary__service-thumb">
						<?php endif; ?>
						<div>
							<h3 class="wpss-order-summary__service-title">
								<?php echo esc_html( $service ? $service->post_title : __( 'Deleted Service', 'wp-sell-services' ) ); ?>
							</h3>
							<p class="wpss-order-summary__service-package">
								<?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?>
							</p>
						</div>
					</div>
				</div>
			</div>

			<!-- Deliverables -->
			<?php if ( ! empty( $deliverables ) ) : ?>
				<div class="wpss-order-card">
					<div class="wpss-order-card__header">
						<h3 class="wpss-order-card__title"><?php esc_html_e( 'Deliverables', 'wp-sell-services' ); ?></h3>
					</div>
					<div class="wpss-order-card__body">
						<?php foreach ( $deliverables as $deliverable ) : ?>
							<div class="wpss-deliverable">
								<div class="wpss-deliverable__header">
									<span class="wpss-deliverable__date">
										<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $deliverable->created_at ) ) ); ?>
									</span>
									<span class="wpss-badge wpss-badge--status-<?php echo esc_attr( $deliverable->status ); ?>">
										<?php echo esc_html( ucfirst( $deliverable->status ) ); ?>
									</span>
								</div>
								<div class="wpss-deliverable__content">
									<?php echo wp_kses_post( wpautop( $deliverable->description ) ); ?>
								</div>
								<?php
								$files = maybe_unserialize( $deliverable->files );
								if ( ! empty( $files ) ) :
									?>
									<div class="wpss-deliverable__files">
										<?php foreach ( $files as $file_id ) : ?>
											<?php
											$file_url  = wp_get_attachment_url( $file_id );
											$file_name = get_the_title( $file_id );
											?>
											<a href="<?php echo esc_url( $file_url ); ?>"
												class="wpss-deliverable__file"
												target="_blank"
												download>
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
				</div>
			<?php endif; ?>

			<!-- Conversation -->
			<div class="wpss-order-card">
				<div class="wpss-order-card__header">
					<h3 class="wpss-order-card__title"><?php esc_html_e( 'Conversation', 'wp-sell-services' ); ?></h3>
				</div>
				<div class="wpss-order-card__body">
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
			</div>
		</div>

		<aside class="wpss-order-detail__sidebar">
			<!-- Order Details -->
			<div class="wpss-order-card">
				<div class="wpss-order-card__header">
					<h4 class="wpss-order-card__title"><?php esc_html_e( 'Order Details', 'wp-sell-services' ); ?></h4>
				</div>
				<div class="wpss-order-summary">
					<div class="wpss-order-summary__row">
						<span class="wpss-order-summary__label"><?php esc_html_e( 'Order Number', 'wp-sell-services' ); ?></span>
						<span class="wpss-order-summary__value">#<?php echo esc_html( $order->order_number ); ?></span>
					</div>

					<div class="wpss-order-summary__row">
						<span class="wpss-order-summary__label"><?php esc_html_e( 'Order Date', 'wp-sell-services' ); ?></span>
						<span class="wpss-order-summary__value"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $order->created_at ) ) ); ?></span>
					</div>

					<?php if ( $order->due_date ) : ?>
						<div class="wpss-order-summary__row">
							<span class="wpss-order-summary__label"><?php esc_html_e( 'Due Date', 'wp-sell-services' ); ?></span>
							<span class="wpss-order-summary__value"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $order->due_date ) ) ); ?></span>
						</div>
					<?php endif; ?>

					<div class="wpss-order-summary__row wpss-order-summary__total">
						<span class="wpss-order-summary__label"><?php esc_html_e( 'Total Amount', 'wp-sell-services' ); ?></span>
						<span class="wpss-order-summary__value"><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></span>
					</div>
				</div>
			</div>

			<!-- Other Party Info -->
			<div class="wpss-order-card">
				<div class="wpss-order-card__header">
					<h4 class="wpss-order-card__title">
						<?php
						if ( $is_vendor ) {
							esc_html_e( 'Buyer', 'wp-sell-services' );
						} else {
							esc_html_e( 'Seller', 'wp-sell-services' );
						}
						?>
					</h4>
				</div>
				<div class="wpss-order-card__body">
					<?php $other_party = $is_vendor ? $customer : $vendor; ?>
					<?php if ( $other_party ) : ?>
						<div class="wpss-orders__user-cell">
							<img src="<?php echo esc_url( get_avatar_url( $other_party->ID, array( 'size' => 48 ) ) ); ?>"
								alt="<?php echo esc_attr( $other_party->display_name ); ?>"
								class="wpss-orders__user-avatar" style="width:48px;height:48px;">
							<div>
								<strong class="wpss-orders__user-name"><?php echo esc_html( $other_party->display_name ); ?></strong>
								<?php if ( ! $is_vendor ) : ?>
									<?php
									$vendor_rating = (float) get_user_meta( $other_party->ID, '_wpss_rating_average', true );
									$vendor_count  = (int) get_user_meta( $other_party->ID, '_wpss_rating_count', true );
									?>
									<?php if ( $vendor_count > 0 ) : ?>
										<div class="wpss-rating">
											<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="color:var(--wpss-warning,#f59e0b)">
												<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
											</svg>
											<?php echo esc_html( number_format( $vendor_rating, 1 ) ); ?>
											<span style="color:var(--wpss-text-muted,#6b7280)">(<?php echo esc_html( $vendor_count ); ?>)</span>
										</div>
									<?php endif; ?>
								<?php endif; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Order Timeline -->
			<div class="wpss-order-card">
				<div class="wpss-order-card__header">
					<h4 class="wpss-order-card__title"><?php esc_html_e( 'Order Timeline', 'wp-sell-services' ); ?></h4>
				</div>
				<div class="wpss-order-card__body">
					<ul class="wpss-order-activity__list">
						<li class="wpss-order-activity__item wpss-order-activity__item--completed">
							<span class="wpss-order-activity__dot"></span>
							<span class="wpss-order-activity__title"><?php esc_html_e( 'Order Placed', 'wp-sell-services' ); ?></span>
							<span class="wpss-order-activity__meta"><?php echo esc_html( wp_date( 'M j, g:i A', strtotime( $order->created_at ) ) ); ?></span>
						</li>

						<?php if ( $order->started_at ) : ?>
							<li class="wpss-order-activity__item wpss-order-activity__item--completed">
								<span class="wpss-order-activity__dot"></span>
								<span class="wpss-order-activity__title"><?php esc_html_e( 'Work Started', 'wp-sell-services' ); ?></span>
								<span class="wpss-order-activity__meta"><?php echo esc_html( wp_date( 'M j, g:i A', strtotime( $order->started_at ) ) ); ?></span>
							</li>
						<?php endif; ?>

						<?php if ( $order->delivered_at ) : ?>
							<li class="wpss-order-activity__item wpss-order-activity__item--completed">
								<span class="wpss-order-activity__dot"></span>
								<span class="wpss-order-activity__title"><?php esc_html_e( 'Delivered', 'wp-sell-services' ); ?></span>
								<span class="wpss-order-activity__meta"><?php echo esc_html( wp_date( 'M j, g:i A', strtotime( $order->delivered_at ) ) ); ?></span>
							</li>
						<?php endif; ?>

						<?php if ( $order->completed_at ) : ?>
							<li class="wpss-order-activity__item wpss-order-activity__item--completed">
								<span class="wpss-order-activity__dot"></span>
								<span class="wpss-order-activity__title"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
								<span class="wpss-order-activity__meta"><?php echo esc_html( wp_date( 'M j, g:i A', strtotime( $order->completed_at ) ) ); ?></span>
							</li>
						<?php endif; ?>
					</ul>
				</div>
			</div>

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
					<div class="wpss-order-card">
						<div class="wpss-order-card__body" style="text-align:center;">
							<svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" style="color:var(--wpss-warning,#f59e0b);margin-bottom:0.5rem;">
								<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
							</svg>
							<h4 style="margin:0 0 0.5rem;"><?php esc_html_e( 'Rate Your Experience', 'wp-sell-services' ); ?></h4>
							<p style="color:var(--wpss-text-muted,#6b7280);margin:0 0 1rem;font-size:0.875rem;"><?php esc_html_e( 'How was your experience with this order?', 'wp-sell-services' ); ?></p>
							<button type="button" class="wpss-btn wpss-btn--primary wpss-btn--block wpss-write-review-btn"
									data-order="<?php echo esc_attr( $order_id ); ?>">
								<?php esc_html_e( 'Write a Review', 'wp-sell-services' ); ?>
							</button>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</aside>
	</div>
</div>

<?php
// Check if review modal should be available.
$can_review       = 'completed' === $order->status && $is_customer && empty( $review_exists );
$can_open_dispute = $is_customer && in_array( $order->status, array( 'in_progress', 'pending_approval' ), true );
?>

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

<?php
/**
 * Hook: wpss_after_order_view
 *
 * @param object $order Order object.
 */
do_action( 'wpss_after_order_view', $order );
