<?php
/**
 * Template: Order View
 *
 * Displays a single order with conversation and actions.
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

$order = wpss_get_order( $order_id );

if ( ! $order ) {
	echo '<div class="wpss-notice wpss-notice-error">' . esc_html__( 'Order not found.', 'wp-sell-services' ) . '</div>';
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

<div class="wpss-order-view">
	<div class="wpss-order-header">
		<a href="<?php echo esc_url( wpss_get_dashboard_url( 'orders' ) ); ?>" class="wpss-back-link">
			&larr; <?php esc_html_e( 'Back to Orders', 'wp-sell-services' ); ?>
		</a>

		<div class="wpss-order-title">
			<h2>
				<?php
				printf(
					/* translators: %s: order number */
					esc_html__( 'Order %s', 'wp-sell-services' ),
					esc_html( $order->order_number )
				);
				?>
			</h2>
			<span class="wpss-status wpss-status-<?php echo esc_attr( $order->status ); ?>">
				<?php echo esc_html( wpss_get_order_status_label( $order->status ) ); ?>
			</span>
		</div>
	</div>

	<div class="wpss-order-layout">
		<div class="wpss-order-main">
			<!-- Service Info -->
			<div class="wpss-order-section wpss-order-service-info">
				<div class="wpss-service-card-mini">
					<?php if ( $service && has_post_thumbnail( $service->ID ) ) : ?>
						<img src="<?php echo esc_url( get_the_post_thumbnail_url( $service->ID, 'thumbnail' ) ); ?>"
							alt="<?php echo esc_attr( $service->post_title ); ?>"
							class="wpss-service-thumb">
					<?php endif; ?>
					<div class="wpss-service-details">
						<h3><?php echo esc_html( $service ? $service->post_title : __( 'Deleted Service', 'wp-sell-services' ) ); ?></h3>
						<p class="wpss-order-amount">
							<?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?>
						</p>
					</div>
				</div>
			</div>

			<!-- Order Actions -->
			<?php if ( in_array( $order->status, array( 'pending', 'accepted', 'in_progress', 'pending_approval' ), true ) ) : ?>
				<div class="wpss-order-section wpss-order-actions-section">
					<h3><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></h3>

					<div class="wpss-action-buttons">
						<?php if ( $is_vendor ) : ?>
							<?php if ( 'pending' === $order->status ) : ?>
								<button type="button" class="wpss-btn wpss-btn-success wpss-order-action"
										data-action="accept" data-order="<?php echo esc_attr( $order_id ); ?>">
									<?php esc_html_e( 'Accept Order', 'wp-sell-services' ); ?>
								</button>
								<button type="button" class="wpss-btn wpss-btn-danger wpss-order-action"
										data-action="reject" data-order="<?php echo esc_attr( $order_id ); ?>">
									<?php esc_html_e( 'Decline', 'wp-sell-services' ); ?>
								</button>
							<?php endif; ?>

							<?php if ( in_array( $order->status, array( 'accepted', 'requirements_submitted' ), true ) ) : ?>
								<button type="button" class="wpss-btn wpss-btn-primary wpss-order-action"
										data-action="start" data-order="<?php echo esc_attr( $order_id ); ?>">
									<?php esc_html_e( 'Start Working', 'wp-sell-services' ); ?>
								</button>
							<?php endif; ?>

							<?php if ( 'in_progress' === $order->status ) : ?>
								<button type="button" class="wpss-btn wpss-btn-success wpss-deliver-btn"
										data-order="<?php echo esc_attr( $order_id ); ?>">
									<?php esc_html_e( 'Deliver Work', 'wp-sell-services' ); ?>
								</button>
							<?php endif; ?>
						<?php endif; ?>

						<?php if ( $is_customer ) : ?>
							<?php if ( 'pending_approval' === $order->status ) : ?>
								<button type="button" class="wpss-btn wpss-btn-success wpss-order-action"
										data-action="complete" data-order="<?php echo esc_attr( $order_id ); ?>">
									<?php esc_html_e( 'Accept & Complete', 'wp-sell-services' ); ?>
								</button>
								<button type="button" class="wpss-btn wpss-btn-secondary wpss-revision-btn"
										data-order="<?php echo esc_attr( $order_id ); ?>">
									<?php esc_html_e( 'Request Revision', 'wp-sell-services' ); ?>
								</button>
							<?php endif; ?>

							<?php if ( in_array( $order->status, array( 'pending', 'accepted' ), true ) ) : ?>
								<button type="button" class="wpss-btn wpss-btn-outline wpss-order-action"
										data-action="cancel" data-order="<?php echo esc_attr( $order_id ); ?>">
									<?php esc_html_e( 'Cancel Order', 'wp-sell-services' ); ?>
								</button>
							<?php endif; ?>
						<?php endif; ?>

						<?php if ( in_array( $order->status, array( 'in_progress', 'pending_approval' ), true ) ) : ?>
							<button type="button" class="wpss-btn wpss-btn-outline wpss-btn-danger wpss-dispute-btn"
									data-order="<?php echo esc_attr( $order_id ); ?>">
								<?php esc_html_e( 'Open Dispute', 'wp-sell-services' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Deliverables -->
			<?php if ( ! empty( $deliverables ) ) : ?>
				<div class="wpss-order-section wpss-order-deliverables">
					<h3><?php esc_html_e( 'Deliverables', 'wp-sell-services' ); ?></h3>

					<?php foreach ( $deliverables as $deliverable ) : ?>
						<div class="wpss-deliverable">
							<div class="wpss-deliverable-header">
								<span class="wpss-deliverable-date">
									<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $deliverable->created_at ) ) ); ?>
								</span>
								<span class="wpss-deliverable-status wpss-status-<?php echo esc_attr( $deliverable->status ); ?>">
									<?php echo esc_html( ucfirst( $deliverable->status ) ); ?>
								</span>
							</div>
							<div class="wpss-deliverable-content">
								<?php echo wp_kses_post( wpautop( $deliverable->description ) ); ?>
							</div>
							<?php
							$files = maybe_unserialize( $deliverable->files );
							if ( ! empty( $files ) ) :
								?>
								<div class="wpss-deliverable-files">
									<?php foreach ( $files as $file_id ) : ?>
										<?php
										$file_url  = wp_get_attachment_url( $file_id );
										$file_name = get_the_title( $file_id );
										$file_type = get_post_mime_type( $file_id );
										?>
										<a href="<?php echo esc_url( $file_url ); ?>"
											class="wpss-file-download"
											target="_blank"
											download>
											<span class="wpss-file-icon"></span>
											<span class="wpss-file-name"><?php echo esc_html( $file_name ); ?></span>
										</a>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<!-- Messages/Conversation -->
			<div class="wpss-order-section wpss-order-conversation">
				<h3><?php esc_html_e( 'Conversation', 'wp-sell-services' ); ?></h3>

				<div class="wpss-messages" id="wpss-messages-container">
					<?php if ( ! empty( $messages ) ) : ?>
						<?php foreach ( $messages as $message ) : ?>
							<?php
							$is_own_message = (int) $message->user_id === $user_id;
							$message_user   = get_userdata( $message->user_id );
							?>
							<div class="wpss-message <?php echo $is_own_message ? 'wpss-message-own' : ''; ?> <?php echo $message->is_system ? 'wpss-message-system' : ''; ?>">
								<?php if ( ! $message->is_system ) : ?>
									<img src="<?php echo esc_url( get_avatar_url( $message->user_id, array( 'size' => 40 ) ) ); ?>"
										alt="<?php echo esc_attr( $message_user ? $message_user->display_name : '' ); ?>"
										class="wpss-message-avatar">
								<?php endif; ?>
								<div class="wpss-message-content">
									<?php if ( ! $message->is_system ) : ?>
										<span class="wpss-message-author">
											<?php echo esc_html( $message_user ? $message_user->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?>
										</span>
									<?php endif; ?>
									<div class="wpss-message-text">
										<?php echo wp_kses_post( wpautop( $message->message ) ); ?>
									</div>
									<span class="wpss-message-time">
										<?php echo esc_html( wpss_time_ago( $message->created_at ) ); ?>
									</span>
								</div>
							</div>
						<?php endforeach; ?>
					<?php else : ?>
						<p class="wpss-no-messages"><?php esc_html_e( 'No messages yet. Start the conversation!', 'wp-sell-services' ); ?></p>
					<?php endif; ?>
				</div>

				<?php if ( ! in_array( $order->status, array( 'completed', 'cancelled', 'refunded' ), true ) ) : ?>
					<form class="wpss-message-form" id="wpss-message-form" data-order="<?php echo esc_attr( $order_id ); ?>">
						<?php wp_nonce_field( 'wpss_send_message', 'wpss_message_nonce' ); ?>
						<textarea name="message"
									placeholder="<?php esc_attr_e( 'Type your message...', 'wp-sell-services' ); ?>"
									rows="3"
									required></textarea>
						<div class="wpss-message-form-footer">
							<button type="button" class="wpss-btn wpss-btn-icon wpss-attach-btn" title="<?php esc_attr_e( 'Attach File', 'wp-sell-services' ); ?>">
								<span class="wpss-icon-attach"></span>
							</button>
							<button type="submit" class="wpss-btn wpss-btn-primary">
								<?php esc_html_e( 'Send', 'wp-sell-services' ); ?>
							</button>
						</div>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<aside class="wpss-order-sidebar">
			<!-- Order Details -->
			<div class="wpss-sidebar-card">
				<h4><?php esc_html_e( 'Order Details', 'wp-sell-services' ); ?></h4>
				<dl class="wpss-details-list">
					<dt><?php esc_html_e( 'Order Number', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( $order->order_number ); ?></dd>

					<dt><?php esc_html_e( 'Order Date', 'wp-sell-services' ); ?></dt>
					<dd><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $order->created_at ) ) ); ?></dd>

					<?php if ( $order->due_date ) : ?>
						<dt><?php esc_html_e( 'Due Date', 'wp-sell-services' ); ?></dt>
						<dd><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $order->due_date ) ) ); ?></dd>
					<?php endif; ?>

					<dt><?php esc_html_e( 'Total Amount', 'wp-sell-services' ); ?></dt>
					<dd><strong><?php echo esc_html( wpss_format_price( (float) $order->total, $order->currency ) ); ?></strong></dd>
				</dl>
			</div>

			<!-- Other Party Info -->
			<div class="wpss-sidebar-card">
				<h4>
					<?php
					if ( $is_vendor ) {
						esc_html_e( 'Buyer', 'wp-sell-services' );
					} else {
						esc_html_e( 'Seller', 'wp-sell-services' );
					}
					?>
				</h4>
				<?php $other_party = $is_vendor ? $customer : $vendor; ?>
				<?php if ( $other_party ) : ?>
					<div class="wpss-user-card">
						<img src="<?php echo esc_url( get_avatar_url( $other_party->ID, array( 'size' => 60 ) ) ); ?>"
							alt="<?php echo esc_attr( $other_party->display_name ); ?>"
							class="wpss-user-avatar">
						<div class="wpss-user-info">
							<strong><?php echo esc_html( $other_party->display_name ); ?></strong>
							<?php if ( ! $is_vendor ) : ?>
								<?php
								$vendor_rating = (float) get_user_meta( $other_party->ID, '_wpss_rating_average', true );
								$vendor_count  = (int) get_user_meta( $other_party->ID, '_wpss_rating_count', true );
								?>
								<?php if ( $vendor_count > 0 ) : ?>
									<span class="wpss-rating">
										<?php echo esc_html( number_format( $vendor_rating, 1 ) ); ?>
										<span class="wpss-star">★</span>
										(<?php echo esc_html( $vendor_count ); ?>)
									</span>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<!-- Order Timeline -->
			<div class="wpss-sidebar-card">
				<h4><?php esc_html_e( 'Order Timeline', 'wp-sell-services' ); ?></h4>
				<ul class="wpss-timeline">
					<li class="wpss-timeline-item completed">
						<span class="wpss-timeline-dot"></span>
						<span class="wpss-timeline-label"><?php esc_html_e( 'Order Placed', 'wp-sell-services' ); ?></span>
						<span class="wpss-timeline-date"><?php echo esc_html( wp_date( 'M j, g:i A', strtotime( $order->created_at ) ) ); ?></span>
					</li>

					<?php if ( $order->started_at ) : ?>
						<li class="wpss-timeline-item completed">
							<span class="wpss-timeline-dot"></span>
							<span class="wpss-timeline-label"><?php esc_html_e( 'Work Started', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline-date"><?php echo esc_html( wp_date( 'M j, g:i A', strtotime( $order->started_at ) ) ); ?></span>
						</li>
					<?php endif; ?>

					<?php if ( $order->delivered_at ) : ?>
						<li class="wpss-timeline-item completed">
							<span class="wpss-timeline-dot"></span>
							<span class="wpss-timeline-label"><?php esc_html_e( 'Delivered', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline-date"><?php echo esc_html( wp_date( 'M j, g:i A', strtotime( $order->delivered_at ) ) ); ?></span>
						</li>
					<?php endif; ?>

					<?php if ( $order->completed_at ) : ?>
						<li class="wpss-timeline-item completed">
							<span class="wpss-timeline-dot"></span>
							<span class="wpss-timeline-label"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline-date"><?php echo esc_html( wp_date( 'M j, g:i A', strtotime( $order->completed_at ) ) ); ?></span>
						</li>
					<?php endif; ?>
				</ul>
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
					<div class="wpss-sidebar-card wpss-review-prompt">
						<h4><?php esc_html_e( 'Rate Your Experience', 'wp-sell-services' ); ?></h4>
						<p><?php esc_html_e( 'How was your experience with this order?', 'wp-sell-services' ); ?></p>
						<button type="button" class="wpss-btn wpss-btn-primary wpss-btn-block wpss-write-review-btn"
								data-order="<?php echo esc_attr( $order_id ); ?>">
							<?php esc_html_e( 'Write a Review', 'wp-sell-services' ); ?>
						</button>
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
	<div class="wpss-modal-backdrop"></div>
	<div class="wpss-modal-dialog">
		<div class="wpss-modal-header">
			<h3><?php esc_html_e( 'Write a Review', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">&times;</button>
		</div>
		<form class="wpss-review-form" id="wpss-review-form">
			<?php wp_nonce_field( 'wpss_submit_review', 'wpss_review_nonce' ); ?>
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

			<div class="wpss-modal-body">
				<div class="wpss-form-group wpss-rating-input">
					<label><?php esc_html_e( 'Overall Rating', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<div class="wpss-star-rating" role="group" aria-label="<?php esc_attr_e( 'Rating', 'wp-sell-services' ); ?>">
						<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
							<input type="radio" name="rating" id="star-<?php echo esc_attr( $i ); ?>" value="<?php echo esc_attr( $i ); ?>" required>
							<label for="star-<?php echo esc_attr( $i ); ?>" title="<?php echo esc_attr( $i ); ?> <?php esc_attr_e( 'stars', 'wp-sell-services' ); ?>">★</label>
						<?php endfor; ?>
					</div>
				</div>

				<div class="wpss-form-group">
					<label for="review-comment"><?php esc_html_e( 'Your Review', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<textarea name="comment" id="review-comment" rows="4" required
								placeholder="<?php esc_attr_e( 'Share your experience with this service...', 'wp-sell-services' ); ?>"></textarea>
				</div>
			</div>

			<div class="wpss-modal-footer">
				<button type="button" class="wpss-btn wpss-btn-secondary wpss-modal-close-btn">
					<?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?>
				</button>
				<button type="submit" class="wpss-btn wpss-btn-primary">
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
	<div class="wpss-modal-backdrop"></div>
	<div class="wpss-modal-dialog">
		<div class="wpss-modal-header">
			<h3><?php esc_html_e( 'Open Dispute', 'wp-sell-services' ); ?></h3>
			<button type="button" class="wpss-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">&times;</button>
		</div>
		<form class="wpss-dispute-form" id="wpss-dispute-form">
			<?php wp_nonce_field( 'wpss_open_dispute', 'wpss_dispute_nonce' ); ?>
			<input type="hidden" name="order_id" value="<?php echo esc_attr( $order_id ); ?>">

			<div class="wpss-modal-body">
				<div class="wpss-notice wpss-notice-warning">
					<p><?php esc_html_e( 'Opening a dispute will pause this order until resolved. Please try to resolve issues directly with the seller first.', 'wp-sell-services' ); ?></p>
				</div>

				<div class="wpss-form-group">
					<label for="dispute-reason"><?php esc_html_e( 'Reason for Dispute', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<select name="reason" id="dispute-reason" required>
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
					<label for="dispute-description"><?php esc_html_e( 'Description', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<textarea name="description" id="dispute-description" rows="4" required
								placeholder="<?php esc_attr_e( 'Please describe the issue in detail...', 'wp-sell-services' ); ?>"></textarea>
				</div>
			</div>

			<div class="wpss-modal-footer">
				<button type="button" class="wpss-btn wpss-btn-secondary wpss-modal-close-btn">
					<?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?>
				</button>
				<button type="submit" class="wpss-btn wpss-btn-danger">
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
