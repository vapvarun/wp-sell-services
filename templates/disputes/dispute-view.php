<?php
/**
 * Template: Dispute View
 *
 * Displays a single dispute with evidence and resolution details.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/disputes/dispute-view.php
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var object $dispute     Dispute object.
 * @var object $order       Order object.
 * @var object $service     Service post object.
 * @var array  $evidence    Array of evidence objects.
 * @var int    $user_id     Current user ID.
 * @var bool   $is_customer Whether current user is customer.
 * @var bool   $is_vendor   Whether current user is vendor.
 */

defined( 'ABSPATH' ) || exit;

use WPSellServices\Services\DisputeService;

$statuses         = DisputeService::get_statuses();
$resolution_types = DisputeService::get_resolution_types();
$status_label     = $statuses[ $dispute->status ] ?? $dispute->status;
$can_add_evidence = in_array( $dispute->status, array( DisputeService::STATUS_OPEN, DisputeService::STATUS_PENDING ), true );

// Get participants.
$customer = get_userdata( $order->customer_id );
$vendor   = get_userdata( $order->vendor_id );
$opener   = get_userdata( $dispute->opened_by );

// Back link.
$disputes_url = wc_get_endpoint_url( 'service-disputes', '', wc_get_page_permalink( 'myaccount' ) );
$order_url    = wc_get_endpoint_url( 'service-orders', $order->id, wc_get_page_permalink( 'myaccount' ) );
?>

<div class="wpss-dispute-view">
	<!-- Breadcrumb -->
	<div class="wpss-dispute-breadcrumb">
		<a href="<?php echo esc_url( $disputes_url ); ?>">
			<span class="dashicons dashicons-arrow-left-alt2"></span>
			<?php esc_html_e( 'Back to Disputes', 'wp-sell-services' ); ?>
		</a>
	</div>

	<!-- Dispute Header -->
	<div class="wpss-dispute-header">
		<div class="wpss-dispute-title-row">
			<h2 class="wpss-dispute-title">
				<?php
				printf(
					/* translators: %d: dispute ID */
					esc_html__( 'Dispute #%d', 'wp-sell-services' ),
					esc_html( $dispute->id )
				);
				?>
			</h2>
			<span class="wpss-dispute-status wpss-status-<?php echo esc_attr( $dispute->status ); ?>">
				<?php echo esc_html( $status_label ); ?>
			</span>
		</div>
		<div class="wpss-dispute-meta">
			<span class="wpss-dispute-date">
				<?php
				printf(
					/* translators: %s: date */
					esc_html__( 'Opened on %s', 'wp-sell-services' ),
					esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $dispute->created_at ) ) )
				);
				?>
			</span>
			<span class="wpss-dispute-opener">
				<?php
				printf(
					/* translators: %s: user name */
					esc_html__( 'by %s', 'wp-sell-services' ),
					esc_html( $opener ? $opener->display_name : __( 'Unknown', 'wp-sell-services' ) )
				);
				?>
			</span>
		</div>
	</div>

	<div class="wpss-dispute-content">
		<!-- Left Column: Dispute Details -->
		<div class="wpss-dispute-main">
			<!-- Reason & Description -->
			<div class="wpss-dispute-section">
				<h3 class="wpss-section-title"><?php esc_html_e( 'Dispute Reason', 'wp-sell-services' ); ?></h3>
				<div class="wpss-dispute-reason">
					<strong><?php echo esc_html( $dispute->reason ); ?></strong>
				</div>
				<?php if ( ! empty( $dispute->description ) ) : ?>
					<div class="wpss-dispute-description">
						<?php echo wp_kses_post( nl2br( $dispute->description ) ); ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Evidence Section -->
			<div class="wpss-dispute-section">
				<h3 class="wpss-section-title">
					<?php esc_html_e( 'Evidence & Messages', 'wp-sell-services' ); ?>
					<span class="wpss-evidence-count">(<?php echo count( $evidence ); ?>)</span>
				</h3>

				<div class="wpss-evidence-list" id="wpss-evidence-list">
					<?php if ( empty( $evidence ) ) : ?>
						<div class="wpss-no-evidence">
							<p><?php esc_html_e( 'No evidence has been submitted yet.', 'wp-sell-services' ); ?></p>
						</div>
					<?php else : ?>
						<?php
						$current_date = '';
						foreach ( $evidence as $item ) :
							$evidence_date = wp_date( get_option( 'date_format' ), strtotime( $item->created_at ) );
							$evidence_user = get_userdata( $item->user_id );
							$is_own        = (int) $item->user_id === $user_id;
							$is_admin      = user_can( $item->user_id, 'manage_options' );

							// Date separator.
							if ( $evidence_date !== $current_date ) :
								$current_date = $evidence_date;
								?>
								<div class="wpss-evidence-date-separator">
									<span><?php echo esc_html( $evidence_date ); ?></span>
								</div>
							<?php endif; ?>

							<div class="wpss-evidence-item <?php echo $is_own ? 'wpss-evidence-own' : 'wpss-evidence-other'; ?> <?php echo $is_admin ? 'wpss-evidence-admin' : ''; ?>">
								<?php if ( ! $is_own ) : ?>
									<div class="wpss-evidence-avatar">
										<?php echo get_avatar( $item->user_id, 40 ); ?>
									</div>
								<?php endif; ?>
								<div class="wpss-evidence-bubble">
									<?php if ( ! $is_own ) : ?>
										<span class="wpss-evidence-sender">
											<?php echo esc_html( $evidence_user ? $evidence_user->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?>
											<?php if ( $is_admin ) : ?>
												<span class="wpss-admin-badge"><?php esc_html_e( 'Admin', 'wp-sell-services' ); ?></span>
											<?php endif; ?>
										</span>
									<?php endif; ?>

									<div class="wpss-evidence-content">
										<?php if ( ! empty( $item->description ) ) : ?>
											<div class="wpss-evidence-text">
												<?php echo wp_kses_post( nl2br( $item->description ) ); ?>
											</div>
										<?php endif; ?>

										<?php if ( 'image' === $item->type && ! empty( $item->content ) ) : ?>
											<div class="wpss-evidence-image">
												<a href="<?php echo esc_url( $item->content ); ?>" target="_blank">
													<img src="<?php echo esc_url( $item->content ); ?>" alt="<?php esc_attr_e( 'Evidence image', 'wp-sell-services' ); ?>">
												</a>
											</div>
										<?php elseif ( 'file' === $item->type && ! empty( $item->content ) ) : ?>
											<div class="wpss-evidence-file">
												<a href="<?php echo esc_url( $item->content ); ?>" target="_blank" class="wpss-file-link">
													<span class="dashicons dashicons-media-default"></span>
													<span><?php echo esc_html( basename( $item->content ) ); ?></span>
												</a>
											</div>
										<?php elseif ( 'link' === $item->type && ! empty( $item->content ) ) : ?>
											<div class="wpss-evidence-link">
												<a href="<?php echo esc_url( $item->content ); ?>" target="_blank" rel="noopener noreferrer">
													<?php echo esc_html( $item->content ); ?>
												</a>
											</div>
										<?php elseif ( 'text' === $item->type && ! empty( $item->content ) ) : ?>
											<div class="wpss-evidence-text">
												<?php echo wp_kses_post( nl2br( $item->content ) ); ?>
											</div>
										<?php endif; ?>
									</div>

									<span class="wpss-evidence-time">
										<?php echo esc_html( wp_date( get_option( 'time_format' ), strtotime( $item->created_at ) ) ); ?>
									</span>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<!-- Add Evidence Form -->
				<?php if ( $can_add_evidence ) : ?>
					<div class="wpss-add-evidence">
						<form id="wpss-evidence-form" class="wpss-evidence-form" method="post" enctype="multipart/form-data">
							<?php wp_nonce_field( 'wpss_add_evidence', 'wpss_evidence_nonce' ); ?>
							<input type="hidden" name="dispute_id" value="<?php echo esc_attr( $dispute->id ); ?>">

							<div class="wpss-evidence-input-wrapper">
								<div class="wpss-evidence-attachments-preview" id="wpss-evidence-attachments-preview"></div>

								<div class="wpss-evidence-input-row">
									<label for="wpss-evidence-file-input" class="wpss-attach-btn" title="<?php esc_attr_e( 'Attach files', 'wp-sell-services' ); ?>">
										<span class="dashicons dashicons-paperclip"></span>
										<input type="file" id="wpss-evidence-file-input" name="evidence_file" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.zip,.txt" style="display: none;">
									</label>

									<textarea name="evidence_description"
												id="wpss-evidence-input"
												class="wpss-evidence-textarea"
												placeholder="<?php esc_attr_e( 'Add a message or describe your evidence...', 'wp-sell-services' ); ?>"
												rows="2"
												maxlength="5000"></textarea>

									<button type="submit" class="wpss-submit-evidence-btn" id="wpss-submit-evidence-btn">
										<span class="dashicons dashicons-arrow-right-alt"></span>
										<span class="wpss-btn-text"><?php esc_html_e( 'Submit', 'wp-sell-services' ); ?></span>
									</button>
								</div>
							</div>

							<div class="wpss-evidence-hint">
								<?php esc_html_e( 'You can attach files as evidence to support your case.', 'wp-sell-services' ); ?>
							</div>
						</form>
					</div>
				<?php else : ?>
					<div class="wpss-evidence-closed">
						<p>
							<?php
							if ( DisputeService::STATUS_RESOLVED === $dispute->status ) {
								esc_html_e( 'This dispute has been resolved. You can no longer submit evidence.', 'wp-sell-services' );
							} elseif ( DisputeService::STATUS_CLOSED === $dispute->status ) {
								esc_html_e( 'This dispute has been closed.', 'wp-sell-services' );
							} else {
								esc_html_e( 'You cannot submit evidence at this time.', 'wp-sell-services' );
							}
							?>
						</p>
					</div>
				<?php endif; ?>
			</div>

			<!-- Resolution Section -->
			<?php if ( DisputeService::STATUS_RESOLVED === $dispute->status && ! empty( $dispute->meta['resolution'] ) ) : ?>
				<?php $resolution = $dispute->meta['resolution']; ?>
				<div class="wpss-dispute-section wpss-resolution-section">
					<h3 class="wpss-section-title"><?php esc_html_e( 'Resolution', 'wp-sell-services' ); ?></h3>
					<div class="wpss-resolution-card">
						<div class="wpss-resolution-type">
							<strong><?php esc_html_e( 'Decision:', 'wp-sell-services' ); ?></strong>
							<?php echo esc_html( $resolution_types[ $resolution['type'] ] ?? $resolution['type'] ); ?>
						</div>
						<?php if ( ! empty( $resolution['refund_amount'] ) && $resolution['refund_amount'] > 0 ) : ?>
							<div class="wpss-resolution-refund">
								<strong><?php esc_html_e( 'Refund Amount:', 'wp-sell-services' ); ?></strong>
								<?php echo wp_kses_post( wpss_format_price( (float) $resolution['refund_amount'] ) ); ?>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $resolution['notes'] ) ) : ?>
							<div class="wpss-resolution-notes">
								<strong><?php esc_html_e( 'Notes:', 'wp-sell-services' ); ?></strong>
								<p><?php echo wp_kses_post( nl2br( $resolution['notes'] ) ); ?></p>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $resolution['resolved_at'] ) ) : ?>
							<div class="wpss-resolution-date">
								<?php
								printf(
									/* translators: %s: date */
									esc_html__( 'Resolved on %s', 'wp-sell-services' ),
									esc_html( wp_date( get_option( 'date_format' ), strtotime( $resolution['resolved_at'] ) ) )
								);
								?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<!-- Right Column: Order Info -->
		<div class="wpss-dispute-sidebar">
			<!-- Order Details -->
			<div class="wpss-sidebar-section">
				<h4 class="wpss-sidebar-title"><?php esc_html_e( 'Order Details', 'wp-sell-services' ); ?></h4>
				<div class="wpss-order-card">
					<div class="wpss-order-service">
						<?php if ( has_post_thumbnail( $service ) ) : ?>
							<img src="<?php echo esc_url( get_the_post_thumbnail_url( $service, 'thumbnail' ) ); ?>"
								alt="<?php echo esc_attr( $service->post_title ); ?>"
								class="wpss-service-thumb">
						<?php endif; ?>
						<div class="wpss-service-info">
							<a href="<?php echo esc_url( get_permalink( $service ) ); ?>" class="wpss-service-title">
								<?php echo esc_html( $service->post_title ); ?>
							</a>
							<span class="wpss-order-id">
								<?php
								printf(
									/* translators: %d: order ID */
									esc_html__( 'Order #%d', 'wp-sell-services' ),
									esc_html( $order->id )
								);
								?>
							</span>
						</div>
					</div>
					<div class="wpss-order-amount">
						<span class="wpss-label"><?php esc_html_e( 'Order Total', 'wp-sell-services' ); ?></span>
						<span class="wpss-value"><?php echo wp_kses_post( wpss_format_price( (float) $order->total ) ); ?></span>
					</div>
					<a href="<?php echo esc_url( $order_url ); ?>" class="wpss-view-order-link">
						<?php esc_html_e( 'View Order', 'wp-sell-services' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</a>
				</div>
			</div>

			<!-- Participants -->
			<div class="wpss-sidebar-section">
				<h4 class="wpss-sidebar-title"><?php esc_html_e( 'Participants', 'wp-sell-services' ); ?></h4>
				<div class="wpss-participants-list">
					<div class="wpss-participant">
						<?php echo get_avatar( $customer->ID, 40 ); ?>
						<div class="wpss-participant-info">
							<span class="wpss-participant-name"><?php echo esc_html( $customer->display_name ); ?></span>
							<span class="wpss-participant-role"><?php esc_html_e( 'Buyer', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-participant">
						<?php echo get_avatar( $vendor->ID, 40 ); ?>
						<div class="wpss-participant-info">
							<span class="wpss-participant-name"><?php echo esc_html( $vendor->display_name ); ?></span>
							<span class="wpss-participant-role"><?php esc_html_e( 'Seller', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<!-- Status History -->
			<?php if ( ! empty( $dispute->meta['notes'] ) ) : ?>
				<div class="wpss-sidebar-section">
					<h4 class="wpss-sidebar-title"><?php esc_html_e( 'Status History', 'wp-sell-services' ); ?></h4>
					<div class="wpss-status-history">
						<?php foreach ( array_reverse( $dispute->meta['notes'] ) as $note ) : ?>
							<div class="wpss-status-entry">
								<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $note['status'] ); ?>">
									<?php echo esc_html( $statuses[ $note['status'] ] ?? $note['status'] ); ?>
								</span>
								<span class="wpss-status-date">
									<?php echo esc_html( wp_date( 'M j, Y', strtotime( $note['created_at'] ) ) ); ?>
								</span>
								<?php if ( ! empty( $note['note'] ) ) : ?>
									<p class="wpss-status-note"><?php echo esc_html( $note['note'] ); ?></p>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<style>
.wpss-dispute-view {
	max-width: 1200px;
	margin: 0 auto;
}

.wpss-dispute-breadcrumb {
	margin-bottom: 20px;
}

.wpss-dispute-breadcrumb a {
	display: inline-flex;
	align-items: center;
	gap: 5px;
	color: var(--wpss-primary-color, #2271b1);
	text-decoration: none;
	font-size: 14px;
}

.wpss-dispute-breadcrumb a:hover {
	text-decoration: underline;
}

.wpss-dispute-header {
	margin-bottom: 30px;
	padding-bottom: 20px;
	border-bottom: 1px solid var(--wpss-border-color, #dcdcde);
}

.wpss-dispute-title-row {
	display: flex;
	align-items: center;
	gap: 15px;
	flex-wrap: wrap;
}

.wpss-dispute-title {
	margin: 0;
	font-size: 24px;
	font-weight: 600;
}

.wpss-dispute-status {
	padding: 6px 12px;
	border-radius: 20px;
	font-size: 13px;
	font-weight: 500;
	text-transform: capitalize;
}

.wpss-status-open {
	background: #fcf0e3;
	color: #9a6700;
}

.wpss-status-pending_review {
	background: #e8f4fd;
	color: #0a4b78;
}

.wpss-status-escalated {
	background: #fce4e4;
	color: #8a1f1f;
}

.wpss-status-resolved {
	background: #e6f4ea;
	color: #1e4620;
}

.wpss-status-closed {
	background: #f0f0f1;
	color: #50575e;
}

.wpss-dispute-meta {
	margin-top: 10px;
	color: var(--wpss-text-secondary, #646970);
	font-size: 14px;
}

.wpss-dispute-meta span + span::before {
	content: ' \2022 ';
	margin: 0 8px;
}

.wpss-dispute-content {
	display: grid;
	grid-template-columns: 1fr 350px;
	gap: 30px;
}

.wpss-dispute-section {
	background: var(--wpss-card-bg, #fff);
	border: 1px solid var(--wpss-border-color, #dcdcde);
	border-radius: var(--wpss-border-radius, 8px);
	padding: 20px;
	margin-bottom: 20px;
}

.wpss-section-title {
	display: flex;
	align-items: center;
	gap: 8px;
	margin: 0 0 15px;
	font-size: 16px;
	font-weight: 600;
	color: var(--wpss-text-primary, #1d2327);
}

.wpss-evidence-count {
	font-weight: normal;
	color: var(--wpss-text-secondary, #646970);
}

.wpss-dispute-reason {
	font-size: 18px;
	margin-bottom: 10px;
	color: var(--wpss-text-primary, #1d2327);
}

.wpss-dispute-description {
	color: var(--wpss-text-secondary, #646970);
	line-height: 1.6;
}

/* Evidence list */
.wpss-evidence-list {
	max-height: 500px;
	overflow-y: auto;
	padding: 10px 0;
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.wpss-no-evidence {
	text-align: center;
	padding: 40px 20px;
	color: var(--wpss-text-muted, #8c8f94);
}

.wpss-evidence-date-separator {
	text-align: center;
	margin: 10px 0;
}

.wpss-evidence-date-separator span {
	padding: 4px 12px;
	font-size: 12px;
	color: var(--wpss-text-secondary, #646970);
	background: var(--wpss-border-color, #dcdcde);
	border-radius: 12px;
}

.wpss-evidence-item {
	display: flex;
	gap: 10px;
	max-width: 85%;
}

.wpss-evidence-own {
	align-self: flex-end;
	flex-direction: row-reverse;
}

.wpss-evidence-other {
	align-self: flex-start;
}

.wpss-evidence-admin .wpss-evidence-bubble {
	border-left: 3px solid var(--wpss-primary-color, #2271b1);
}

.wpss-evidence-avatar img {
	width: 40px;
	height: 40px;
	border-radius: 50%;
}

.wpss-evidence-bubble {
	background: var(--wpss-bg-light, #f6f7f7);
	padding: 12px 16px;
	border-radius: 16px;
}

.wpss-evidence-own .wpss-evidence-bubble {
	background: var(--wpss-primary-color, #2271b1);
	color: #fff;
}

.wpss-evidence-sender {
	display: flex;
	align-items: center;
	gap: 8px;
	font-size: 12px;
	font-weight: 600;
	margin-bottom: 6px;
	color: var(--wpss-primary-color, #2271b1);
}

.wpss-admin-badge {
	padding: 2px 6px;
	background: var(--wpss-primary-color, #2271b1);
	color: #fff;
	font-size: 10px;
	border-radius: 4px;
	text-transform: uppercase;
}

.wpss-evidence-content {
	line-height: 1.5;
}

.wpss-evidence-image img {
	max-width: 300px;
	max-height: 200px;
	border-radius: 8px;
	margin-top: 8px;
}

.wpss-evidence-file,
.wpss-evidence-link {
	margin-top: 8px;
}

.wpss-file-link {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 8px 12px;
	background: rgba(0, 0, 0, 0.05);
	border-radius: 6px;
	text-decoration: none;
	color: inherit;
}

.wpss-evidence-own .wpss-file-link {
	background: rgba(255, 255, 255, 0.2);
}

.wpss-evidence-time {
	display: block;
	font-size: 11px;
	color: var(--wpss-text-muted, #8c8f94);
	margin-top: 6px;
}

.wpss-evidence-own .wpss-evidence-time {
	color: rgba(255, 255, 255, 0.7);
	text-align: right;
}

/* Add evidence form */
.wpss-add-evidence {
	margin-top: 20px;
	padding-top: 20px;
	border-top: 1px solid var(--wpss-border-color, #dcdcde);
}

.wpss-evidence-input-wrapper {
	display: flex;
	flex-direction: column;
	gap: 8px;
}

.wpss-evidence-input-row {
	display: flex;
	align-items: flex-end;
	gap: 10px;
}

.wpss-add-evidence .wpss-attach-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 44px;
	height: 44px;
	border-radius: 50%;
	background: var(--wpss-bg-light, #f6f7f7);
	color: var(--wpss-text-secondary, #646970);
	cursor: pointer;
	transition: all 0.2s;
	flex-shrink: 0;
}

.wpss-add-evidence .wpss-attach-btn:hover {
	background: var(--wpss-border-color, #dcdcde);
	color: var(--wpss-text-primary, #1d2327);
}

.wpss-evidence-textarea {
	flex: 1;
	padding: 12px 16px;
	border: 1px solid var(--wpss-border-color, #dcdcde);
	border-radius: 22px;
	resize: none;
	font-size: 14px;
	line-height: 1.5;
	max-height: 120px;
}

.wpss-evidence-textarea:focus {
	outline: none;
	border-color: var(--wpss-primary-color, #2271b1);
}

.wpss-submit-evidence-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 5px;
	padding: 12px 20px;
	background: var(--wpss-primary-color, #2271b1);
	color: #fff;
	border: none;
	border-radius: 22px;
	cursor: pointer;
	font-weight: 500;
	transition: background 0.2s;
	flex-shrink: 0;
}

.wpss-submit-evidence-btn:hover {
	background: var(--wpss-primary-dark, #135e96);
}

.wpss-submit-evidence-btn:disabled {
	opacity: 0.6;
	cursor: not-allowed;
}

.wpss-evidence-hint {
	font-size: 12px;
	color: var(--wpss-text-muted, #8c8f94);
	margin-top: 8px;
}

.wpss-evidence-attachments-preview {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
}

.wpss-attachment-preview {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 6px 10px;
	background: var(--wpss-bg-light, #f6f7f7);
	border-radius: 6px;
	font-size: 12px;
}

.wpss-attachment-preview .wpss-remove-attachment {
	cursor: pointer;
	color: var(--wpss-danger-color, #d63638);
}

.wpss-evidence-closed {
	padding: 20px;
	text-align: center;
	background: var(--wpss-bg-light, #f6f7f7);
	border-radius: 8px;
	color: var(--wpss-text-secondary, #646970);
	margin-top: 20px;
}

/* Resolution section */
.wpss-resolution-section {
	background: #e6f4ea;
	border-color: #c8e6c9;
}

.wpss-resolution-card {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.wpss-resolution-type,
.wpss-resolution-refund,
.wpss-resolution-notes,
.wpss-resolution-date {
	font-size: 14px;
}

.wpss-resolution-notes p {
	margin: 8px 0 0;
	color: var(--wpss-text-secondary, #646970);
}

.wpss-resolution-date {
	color: var(--wpss-text-muted, #8c8f94);
	font-size: 13px;
}

/* Sidebar */
.wpss-sidebar-section {
	background: var(--wpss-card-bg, #fff);
	border: 1px solid var(--wpss-border-color, #dcdcde);
	border-radius: var(--wpss-border-radius, 8px);
	padding: 20px;
	margin-bottom: 20px;
}

.wpss-sidebar-title {
	margin: 0 0 15px;
	font-size: 14px;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.5px;
	color: var(--wpss-text-secondary, #646970);
}

.wpss-order-card {
	display: flex;
	flex-direction: column;
	gap: 15px;
}

.wpss-order-service {
	display: flex;
	gap: 12px;
}

.wpss-service-thumb {
	width: 60px;
	height: 60px;
	border-radius: 8px;
	object-fit: cover;
}

.wpss-service-info {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.wpss-service-title {
	font-weight: 600;
	color: var(--wpss-text-primary, #1d2327);
	text-decoration: none;
}

.wpss-service-title:hover {
	color: var(--wpss-primary-color, #2271b1);
}

.wpss-order-id {
	font-size: 13px;
	color: var(--wpss-text-secondary, #646970);
}

.wpss-order-amount {
	display: flex;
	justify-content: space-between;
	padding-top: 12px;
	border-top: 1px solid var(--wpss-border-color, #dcdcde);
}

.wpss-order-amount .wpss-label {
	color: var(--wpss-text-secondary, #646970);
}

.wpss-order-amount .wpss-value {
	font-weight: 600;
}

.wpss-view-order-link {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 5px;
	padding: 10px;
	background: var(--wpss-bg-light, #f6f7f7);
	border-radius: 6px;
	text-decoration: none;
	color: var(--wpss-primary-color, #2271b1);
	font-weight: 500;
	font-size: 14px;
	transition: background 0.2s;
}

.wpss-view-order-link:hover {
	background: var(--wpss-border-color, #dcdcde);
}

/* Participants */
.wpss-participants-list {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.wpss-participant {
	display: flex;
	align-items: center;
	gap: 12px;
}

.wpss-participant img {
	width: 40px;
	height: 40px;
	border-radius: 50%;
}

.wpss-participant-info {
	display: flex;
	flex-direction: column;
}

.wpss-participant-name {
	font-weight: 500;
	color: var(--wpss-text-primary, #1d2327);
}

.wpss-participant-role {
	font-size: 12px;
	color: var(--wpss-text-secondary, #646970);
}

/* Status history */
.wpss-status-history {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.wpss-status-entry {
	padding-bottom: 12px;
	border-bottom: 1px solid var(--wpss-border-color, #dcdcde);
}

.wpss-status-entry:last-child {
	padding-bottom: 0;
	border-bottom: none;
}

.wpss-status-badge {
	display: inline-block;
	padding: 3px 8px;
	border-radius: 4px;
	font-size: 11px;
	font-weight: 500;
	text-transform: capitalize;
}

.wpss-status-date {
	font-size: 12px;
	color: var(--wpss-text-muted, #8c8f94);
	margin-left: 8px;
}

.wpss-status-note {
	margin: 8px 0 0;
	font-size: 13px;
	color: var(--wpss-text-secondary, #646970);
}

@media (max-width: 900px) {
	.wpss-dispute-content {
		grid-template-columns: 1fr;
	}

	.wpss-dispute-sidebar {
		order: -1;
	}
}

@media (max-width: 600px) {
	.wpss-evidence-item {
		max-width: 95%;
	}

	.wpss-submit-evidence-btn .wpss-btn-text {
		display: none;
	}

	.wpss-submit-evidence-btn {
		padding: 12px;
		border-radius: 50%;
	}
}
</style>

<script>
(function($) {
	'use strict';

	var $evidenceForm = $('#wpss-evidence-form');
	var $evidenceInput = $('#wpss-evidence-input');
	var $fileInput = $('#wpss-evidence-file-input');
	var $attachmentsPreview = $('#wpss-evidence-attachments-preview');
	var $submitBtn = $('#wpss-submit-evidence-btn');
	var $evidenceList = $('#wpss-evidence-list');
	var selectedFile = null;

	// Scroll to bottom of evidence list.
	function scrollToBottom() {
		$evidenceList.scrollTop($evidenceList[0].scrollHeight);
	}
	scrollToBottom();

	// File selection.
	$fileInput.on('change', function() {
		selectedFile = this.files[0] || null;
		updateAttachmentPreview();
	});

	function updateAttachmentPreview() {
		$attachmentsPreview.empty();
		if (selectedFile) {
			var $preview = $('<div class="wpss-attachment-preview">')
				.append('<span class="wpss-attachment-name">' + selectedFile.name + '</span>')
				.append('<span class="wpss-remove-attachment dashicons dashicons-no-alt"></span>');
			$attachmentsPreview.append($preview);
		}
	}

	// Remove attachment.
	$attachmentsPreview.on('click', '.wpss-remove-attachment', function() {
		selectedFile = null;
		$fileInput.val('');
		updateAttachmentPreview();
	});

	// Submit evidence.
	$evidenceForm.on('submit', function(e) {
		e.preventDefault();

		var description = $evidenceInput.val().trim();
		if (!description && !selectedFile) {
			return;
		}

		var formData = new FormData();
		formData.append('action', 'wpss_add_dispute_evidence');
		formData.append('nonce', $('#wpss_evidence_nonce').val());
		formData.append('dispute_id', $('input[name="dispute_id"]').val());
		formData.append('description', description);

		if (selectedFile) {
			formData.append('evidence_file', selectedFile);
		}

		$submitBtn.prop('disabled', true);

		$.ajax({
			url: wpss_ajax.ajax_url,
			type: 'POST',
			data: formData,
			processData: false,
			contentType: false,
			success: function(response) {
				if (response.success) {
					// Add new evidence to the list.
					$evidenceList.find('.wpss-no-evidence').remove();
					$evidenceList.append(response.data.html);
					scrollToBottom();

					// Clear form.
					$evidenceInput.val('');
					selectedFile = null;
					$fileInput.val('');
					updateAttachmentPreview();
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Failed to submit evidence.', 'wp-sell-services' ); ?>');
				}
			},
			error: function() {
				alert('<?php esc_html_e( 'An error occurred. Please try again.', 'wp-sell-services' ); ?>');
			},
			complete: function() {
				$submitBtn.prop('disabled', false);
			}
		});
	});

})(jQuery);
</script>
