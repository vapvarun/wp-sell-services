<?php
/**
 * Template: Single Buyer Request
 *
 * This template displays a single buyer request page.
 * Vendors can view details and submit proposals.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/single-request.php
 *
 * Available Hooks:
 *
 * - wpss_single_request_layout (filter)
 *   Allows changing the layout type. Default: 'default'.
 *   @param string $layout     Layout type ('default', 'wide', 'minimal').
 *   @param int    $request_id Request post ID.
 *
 * - wpss_before_single_request (action)
 *   Fires before the single request wrapper.
 *   @param int $request_id Request post ID.
 *
 * - wpss_single_request_header (action)
 *   Header area - breadcrumb, title, status badge.
 *   @param int $request_id Request post ID.
 *   @hooked wpss_request_breadcrumb - 5
 *
 * - wpss_single_request_content (action)
 *   Content area - description, skills, attachments.
 *   @param int $request_id Request post ID.
 *
 * - wpss_single_request_proposals (action)
 *   Proposals section (visible to buyer only).
 *   @param int $request_id Request post ID.
 *
 * - wpss_single_request_sidebar (action)
 *   Sidebar area - request details, buyer info.
 *   @param int $request_id Request post ID.
 *
 * - wpss_after_single_request (action)
 *   Fires after the single request wrapper.
 *   @param int $request_id Request post ID.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$request_id         = get_the_ID();
$buyer_id           = (int) get_post_field( 'post_author', $request_id );
$buyer              = get_userdata( $buyer_id );
$current_user_id    = get_current_user_id();
$is_buyer           = $current_user_id === $buyer_id;
$is_vendor          = wpss_is_vendor( $current_user_id );
$request_status_raw = get_post_meta( $request_id, '_wpss_status', true );
$request_status     = $request_status_raw ? $request_status_raw : 'open';
$budget_type_raw    = get_post_meta( $request_id, '_wpss_budget_type', true );
$budget_type        = $budget_type_raw ? $budget_type_raw : 'fixed';
$budget_min         = (float) get_post_meta( $request_id, '_wpss_budget_min', true );
$budget_max         = (float) get_post_meta( $request_id, '_wpss_budget_max', true );
$delivery_days      = (int) get_post_meta( $request_id, '_wpss_delivery_days', true );
$expires_at         = get_post_meta( $request_id, '_wpss_expires_at', true );
$skills_raw         = get_post_meta( $request_id, '_wpss_skills_required', true );
$skills             = $skills_raw ? $skills_raw : array();
$attachments_raw    = get_post_meta( $request_id, '_wpss_attachments', true );
$attachments        = $attachments_raw ? $attachments_raw : array();
$categories         = wp_get_post_terms( $request_id, 'wpss_service_category' );

// Check if current vendor has already submitted proposal.
$has_proposed = false;
if ( $is_vendor && ! $is_buyer ) {
	$proposal_service = new \WPSellServices\Services\ProposalService();
	$has_proposed     = $proposal_service->vendor_has_proposed( $request_id, $current_user_id );
	$vendor_proposal  = $has_proposed ? $proposal_service->get_vendor_proposal( $request_id, $current_user_id ) : null;
}

// Get proposals for buyer.
$proposals = array();
if ( $is_buyer ) {
	$proposal_service = new \WPSellServices\Services\ProposalService();
	$proposals        = $proposal_service->get_by_request( $request_id );
}

// Format budget display.
if ( 'range' === $budget_type && $budget_min && $budget_max ) {
	$budget_display = wpss_format_price( $budget_min ) . ' - ' . wpss_format_price( $budget_max );
} elseif ( $budget_min ) {
	$budget_display = wpss_format_price( $budget_min );
} else {
	$budget_display = __( 'Negotiable', 'wp-sell-services' );
}

// Check if request is still open.
$is_open = 'open' === $request_status;
if ( $expires_at && strtotime( $expires_at ) < time() ) {
	$is_open = false;
}

/**
 * Filter: wpss_single_request_layout
 *
 * Allows changing the layout type for single request page.
 *
 * @since 1.0.0
 *
 * @param string $layout     Layout type. Default 'default'. Accepts 'default', 'wide', 'minimal'.
 * @param int    $request_id Request post ID.
 */
$layout = apply_filters( 'wpss_single_request_layout', 'default', $request_id );

/**
 * Hook: wpss_before_single_request
 *
 * @param int $request_id Request post ID.
 */
do_action( 'wpss_before_single_request', $request_id );
?>

<div class="wpss-single-request wpss-layout-<?php echo esc_attr( $layout ); ?>">
	<div class="wpss-container">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<div class="wpss-request-layout">
				<div class="wpss-request-main">
					<?php
					/**
					 * Hook: wpss_single_request_header
					 *
					 * @hooked wpss_request_breadcrumb - 5
					 */
					do_action( 'wpss_single_request_header', $request_id );
					?>

					<header class="wpss-request-header">
						<div class="wpss-request-status-badge wpss-status-<?php echo esc_attr( $request_status ); ?>">
							<?php echo esc_html( ucfirst( str_replace( '_', ' ', $request_status ) ) ); ?>
						</div>

						<h1 class="wpss-request-title"><?php the_title(); ?></h1>

						<div class="wpss-request-meta-bar">
							<?php if ( ! empty( $categories ) ) : ?>
								<span class="wpss-request-category">
									<a href="<?php echo esc_url( get_term_link( $categories[0] ) ); ?>">
										<?php echo esc_html( $categories[0]->name ); ?>
									</a>
								</span>
							<?php endif; ?>

							<span class="wpss-request-date">
								<?php
								printf(
									/* translators: %s: human readable time difference */
									esc_html__( 'Posted %s ago', 'wp-sell-services' ),
									esc_html( human_time_diff( get_the_time( 'U' ), time() ) )
								);
								?>
							</span>
						</div>
					</header>

					<div class="wpss-request-content">
						<section class="wpss-request-section wpss-request-description">
							<h2><?php esc_html_e( 'Project Description', 'wp-sell-services' ); ?></h2>
							<div class="wpss-request-text">
								<?php the_content(); ?>
							</div>
						</section>

						<?php if ( ! empty( $skills ) ) : ?>
							<section class="wpss-request-section wpss-request-skills">
								<h2><?php esc_html_e( 'Skills Required', 'wp-sell-services' ); ?></h2>
								<div class="wpss-skills-list">
									<?php foreach ( $skills as $skill ) : ?>
										<span class="wpss-skill-tag"><?php echo esc_html( $skill ); ?></span>
									<?php endforeach; ?>
								</div>
							</section>
						<?php endif; ?>

						<?php if ( ! empty( $attachments ) ) : ?>
							<section class="wpss-request-section wpss-request-attachments">
								<h2><?php esc_html_e( 'Attachments', 'wp-sell-services' ); ?></h2>
								<div class="wpss-attachments-list">
									<?php foreach ( $attachments as $attachment_id ) : ?>
										<?php
										$attachment_url  = wp_get_attachment_url( $attachment_id );
										$attachment_name = basename( get_attached_file( $attachment_id ) );
										?>
										<a href="<?php echo esc_url( $attachment_url ); ?>" class="wpss-attachment-item" target="_blank" rel="noopener">
											<span class="wpss-icon-file"></span>
											<span class="wpss-attachment-name"><?php echo esc_html( $attachment_name ); ?></span>
										</a>
									<?php endforeach; ?>
								</div>
							</section>
						<?php endif; ?>

						<?php
						/**
						 * Hook: wpss_single_request_content
						 *
						 * @param int $request_id Request post ID.
						 */
						do_action( 'wpss_single_request_content', $request_id );
						?>
					</div>

					<?php
					/**
					 * Hook: wpss_single_request_proposals
					 *
					 * Fires in the proposals section area.
					 * Use this to add custom proposal displays or filters.
					 *
					 * @since 1.0.0
					 *
					 * @param int $request_id Request post ID.
					 */
					do_action( 'wpss_single_request_proposals', $request_id );
					?>

					<?php if ( $is_buyer && ! empty( $proposals ) ) : ?>
						<section class="wpss-request-section wpss-request-proposals">
							<h2>
								<?php
								printf(
									/* translators: %d: number of proposals */
									esc_html__( 'Proposals (%d)', 'wp-sell-services' ),
									count( $proposals )
								);
								?>
							</h2>

							<div class="wpss-proposals-list">
								<?php foreach ( $proposals as $proposal ) : ?>
									<?php
									$vendor        = get_userdata( $proposal->vendor_id );
									$vendor_rating = (float) get_user_meta( $proposal->vendor_id, '_wpss_vendor_rating', true );
									$vendor_orders = (int) get_user_meta( $proposal->vendor_id, '_wpss_completed_orders', true );
									?>
									<div class="wpss-proposal-item" data-proposal="<?php echo esc_attr( $proposal->id ); ?>">
										<div class="wpss-proposal-header">
											<div class="wpss-proposal-vendor">
												<img src="<?php echo esc_url( get_avatar_url( $proposal->vendor_id, array( 'size' => 48 ) ) ); ?>"
													alt="<?php echo esc_attr( $vendor ? $vendor->display_name : '' ); ?>"
													class="wpss-vendor-avatar">
												<div class="wpss-vendor-info">
													<a href="<?php echo esc_url( wpss_get_vendor_url( $proposal->vendor_id ) ); ?>" class="wpss-vendor-name">
														<?php echo esc_html( $vendor ? $vendor->display_name : __( 'Unknown', 'wp-sell-services' ) ); ?>
													</a>
													<div class="wpss-vendor-stats">
														<?php if ( $vendor_rating > 0 ) : ?>
															<span class="wpss-vendor-rating">
																<span class="wpss-star">&#9733;</span>
																<?php echo esc_html( number_format( $vendor_rating, 1 ) ); ?>
															</span>
														<?php endif; ?>
														<span class="wpss-vendor-orders">
															<?php
															printf(
																/* translators: %d: number of completed orders */
																esc_html( _n( '%d order', '%d orders', $vendor_orders, 'wp-sell-services' ) ),
																esc_html( $vendor_orders )
															);
															?>
														</span>
													</div>
												</div>
											</div>

											<div class="wpss-proposal-meta">
												<div class="wpss-proposal-price">
													<?php echo esc_html( wpss_format_price( (float) ( $proposal->proposed_price ?? 0.0 ) ) ); ?>
												</div>
												<div class="wpss-proposal-delivery">
													<?php
													$days = (int) ( $proposal->proposed_days ?? 0 );
													printf(
														/* translators: %d: number of days */
														esc_html( _n( '%d day delivery', '%d days delivery', $days, 'wp-sell-services' ) ),
														esc_html( $days )
													);
													?>
												</div>
											</div>
										</div>

										<div class="wpss-proposal-description">
											<?php echo wp_kses_post( nl2br( $proposal->cover_letter ?? '' ) ); ?>
										</div>

										<?php if ( 'pending' === $proposal->status && 'open' === $request_status ) : ?>
											<div class="wpss-proposal-actions">
												<button type="button" class="wpss-btn wpss-btn-primary wpss-accept-proposal" data-proposal="<?php echo esc_attr( $proposal->id ); ?>">
													<?php esc_html_e( 'Accept Proposal', 'wp-sell-services' ); ?>
												</button>
												<button type="button" class="wpss-btn wpss-btn-outline wpss-reject-proposal" data-proposal="<?php echo esc_attr( $proposal->id ); ?>">
													<?php esc_html_e( 'Decline', 'wp-sell-services' ); ?>
												</button>
												<a href="<?php echo esc_url( wpss_get_vendor_url( $proposal->vendor_id ) ); ?>" class="wpss-btn wpss-btn-text">
													<?php esc_html_e( 'View Profile', 'wp-sell-services' ); ?>
												</a>
											</div>
										<?php else : ?>
											<div class="wpss-proposal-status">
												<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $proposal->status ); ?>">
													<?php echo esc_html( ucfirst( $proposal->status ) ); ?>
												</span>
											</div>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						</section>
					<?php endif; ?>
				</div>

				<aside class="wpss-request-details-sidebar">
					<div class="wpss-request-details-card">
						<h3><?php esc_html_e( 'Request Details', 'wp-sell-services' ); ?></h3>

						<div class="wpss-details-list">
							<div class="wpss-detail-item">
								<span class="wpss-detail-label"><?php esc_html_e( 'Budget', 'wp-sell-services' ); ?></span>
								<span class="wpss-detail-value wpss-detail-budget"><?php echo esc_html( $budget_display ); ?></span>
							</div>

							<?php if ( $delivery_days ) : ?>
								<div class="wpss-detail-item">
									<span class="wpss-detail-label"><?php esc_html_e( 'Expected Delivery', 'wp-sell-services' ); ?></span>
									<span class="wpss-detail-value">
										<?php
										printf(
											/* translators: %d: number of days */
											esc_html( _n( '%d day', '%d days', $delivery_days, 'wp-sell-services' ) ),
											esc_html( $delivery_days )
										);
										?>
									</span>
								</div>
							<?php endif; ?>

							<?php if ( $expires_at ) : ?>
								<div class="wpss-detail-item">
									<span class="wpss-detail-label"><?php esc_html_e( 'Deadline', 'wp-sell-services' ); ?></span>
									<span class="wpss-detail-value">
										<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $expires_at ) ) ); ?>
									</span>
								</div>
							<?php endif; ?>

							<div class="wpss-detail-item">
								<span class="wpss-detail-label"><?php esc_html_e( 'Proposals', 'wp-sell-services' ); ?></span>
								<span class="wpss-detail-value"><?php echo esc_html( count( $proposals ) ); ?></span>
							</div>
						</div>

						<?php if ( $is_vendor && ! $is_buyer && $is_open ) : ?>
							<?php if ( $has_proposed ) : ?>
								<div class="wpss-proposal-submitted">
									<div class="wpss-proposal-submitted-icon">&#10003;</div>
									<p><?php esc_html_e( 'You have already submitted a proposal for this request.', 'wp-sell-services' ); ?></p>
									<?php if ( $vendor_proposal && 'pending' === $vendor_proposal->status ) : ?>
										<button type="button" class="wpss-btn wpss-btn-outline wpss-btn-block wpss-withdraw-proposal" data-proposal-id="<?php echo esc_attr( $vendor_proposal->id ); ?>">
											<?php esc_html_e( 'Withdraw Proposal', 'wp-sell-services' ); ?>
										</button>
									<?php endif; ?>
								</div>
							<?php else : ?>
								<button type="button" class="wpss-btn wpss-btn-primary wpss-btn-block wpss-submit-proposal-btn" data-request-id="<?php echo esc_attr( $request_id ); ?>">
									<?php esc_html_e( 'Submit Proposal', 'wp-sell-services' ); ?>
								</button>
							<?php endif; ?>
						<?php elseif ( ! is_user_logged_in() ) : ?>
							<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="wpss-btn wpss-btn-primary wpss-btn-block">
								<?php esc_html_e( 'Login to Submit Proposal', 'wp-sell-services' ); ?>
							</a>
						<?php elseif ( ! $is_vendor && ! $is_buyer ) : ?>
							<a href="<?php echo esc_url( wpss_get_become_vendor_url() ); ?>" class="wpss-btn wpss-btn-primary wpss-btn-block">
								<?php esc_html_e( 'Become a Vendor', 'wp-sell-services' ); ?>
							</a>
						<?php elseif ( ! $is_open ) : ?>
							<div class="wpss-request-closed">
								<p><?php esc_html_e( 'This request is no longer accepting proposals.', 'wp-sell-services' ); ?></p>
							</div>
						<?php endif; ?>
					</div>

					<div class="wpss-buyer-card">
						<h3><?php esc_html_e( 'About the Buyer', 'wp-sell-services' ); ?></h3>

						<div class="wpss-buyer-profile">
							<img src="<?php echo esc_url( get_avatar_url( $buyer_id, array( 'size' => 64 ) ) ); ?>"
								alt="<?php echo esc_attr( $buyer ? $buyer->display_name : '' ); ?>"
								class="wpss-buyer-avatar">
							<div class="wpss-buyer-info">
								<span class="wpss-buyer-name">
									<?php echo esc_html( $buyer ? $buyer->display_name : __( 'Anonymous', 'wp-sell-services' ) ); ?>
								</span>
								<span class="wpss-buyer-member-since">
									<?php
									printf(
										/* translators: %s: date when user registered */
										esc_html__( 'Member since %s', 'wp-sell-services' ),
										esc_html( date_i18n( 'M Y', strtotime( $buyer->user_registered ) ) )
									);
									?>
								</span>
							</div>
						</div>
					</div>

					<?php
					/**
					 * Hook: wpss_single_request_sidebar
					 *
					 * @param int $request_id Request post ID.
					 */
					do_action( 'wpss_single_request_sidebar', $request_id );
					?>
				</aside>
			</div>
		<?php endwhile; ?>
	</div>
</div>

<?php if ( $is_vendor && ! $is_buyer && $is_open && ! $has_proposed ) : ?>
	<!-- Proposal Modal -->
	<div class="wpss-modal" id="wpss-proposal-modal" data-request-id="<?php echo esc_attr( $request_id ); ?>">
		<div class="wpss-modal-backdrop"></div>
		<div class="wpss-modal-dialog wpss-modal-lg">
			<div class="wpss-modal-header">
				<h3 class="wpss-modal-title"><?php esc_html_e( 'Submit Your Proposal', 'wp-sell-services' ); ?></h3>
				<button type="button" class="wpss-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">&times;</button>
			</div>
			<form id="wpss-proposal-form" class="wpss-proposal-form">
				<?php wp_nonce_field( 'wpss_submit_proposal', 'wpss_proposal_nonce' ); ?>
				<input type="hidden" name="request_id" value="<?php echo esc_attr( $request_id ); ?>">

				<div class="wpss-modal-body">
					<div class="wpss-form-group">
						<label for="proposal-description" class="wpss-form-label">
							<?php esc_html_e( 'Cover Letter', 'wp-sell-services' ); ?>
							<span class="wpss-required">*</span>
						</label>
						<textarea id="proposal-description" name="description" class="wpss-form-textarea" rows="6" required
									placeholder="<?php esc_attr_e( 'Introduce yourself and explain why you are the best fit for this project...', 'wp-sell-services' ); ?>"></textarea>
						<p class="wpss-form-help"><?php esc_html_e( 'Explain your relevant experience and how you would approach this project.', 'wp-sell-services' ); ?></p>
					</div>

					<div class="wpss-form-row">
						<div class="wpss-form-group wpss-form-col-6">
							<label for="proposal-price" class="wpss-form-label">
								<?php esc_html_e( 'Your Price', 'wp-sell-services' ); ?>
								<span class="wpss-input-prefix">(<?php echo esc_html( wpss_get_currency_symbol() ); ?>)</span>
								<span class="wpss-required">*</span>
							</label>
							<div class="wpss-input-group">
								<input type="number" id="proposal-price" name="price" class="wpss-form-input" min="1" step="0.01" required
										placeholder="<?php echo esc_attr( $budget_min ? $budget_min : '100' ); ?>">
							</div>
							<?php if ( $budget_min || $budget_max ) : ?>
								<p class="wpss-form-help">
									<?php
									printf(
										/* translators: %s: budget range */
										esc_html__( 'Budget: %s', 'wp-sell-services' ),
										esc_html( $budget_display )
									);
									?>
								</p>
							<?php endif; ?>
						</div>

						<div class="wpss-form-group wpss-form-col-6">
							<label for="proposal-delivery" class="wpss-form-label">
								<?php esc_html_e( 'Delivery Time', 'wp-sell-services' ); ?>
								<span class="wpss-required">*</span>
							</label>
							<div class="wpss-input-group">
								<input type="number" id="proposal-delivery" name="delivery_days" class="wpss-form-input" min="1" required
										value="<?php echo esc_attr( $delivery_days ? $delivery_days : 7 ); ?>">
								<span class="wpss-input-suffix"><?php esc_html_e( 'days', 'wp-sell-services' ); ?></span>
							</div>
						</div>
					</div>
				</div>

				<div class="wpss-modal-footer">
					<button type="button" class="wpss-btn wpss-btn-outline wpss-modal-close-btn">
						<?php esc_html_e( 'Cancel', 'wp-sell-services' ); ?>
					</button>
					<button type="submit" class="wpss-btn wpss-btn-primary">
						<?php esc_html_e( 'Submit Proposal', 'wp-sell-services' ); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
<?php endif; ?>

<?php
/**
 * Hook: wpss_after_single_request
 *
 * @param int $request_id Request post ID.
 */
do_action( 'wpss_after_single_request', $request_id );

get_footer();
