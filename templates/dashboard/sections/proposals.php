<?php
/**
 * Dashboard Section: Proposals (vendor only)
 *
 * Every proposal the vendor has sent, with its outcome. Before this section a
 * vendor had no list of what they had bid on and learned they had lost a job
 * only by re-opening the request (Basecamp 10264294123).
 *
 * Rows reuse the request-card markup, so the list stacks under 480px with the
 * same rules as Buyer Requests and needs no stylesheet of its own.
 *
 * @package WPSellServices\Templates
 * @since   1.7.1
 *
 * @var int           $user_id        Current user ID.
 * @var VendorService $vendor_service Vendor service instance.
 * @var bool          $is_vendor      Whether user is a vendor.
 */

use WPSellServices\Services\ProposalService;

defined( 'ABSPATH' ) || exit;

do_action( 'wpss_dashboard_section_before', 'proposals', $user_id );

$proposal_service = new ProposalService();
$per_page         = 20;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination param.
$proposals_page = isset( $_GET['proposals_page'] ) ? max( 1, absint( $_GET['proposals_page'] ) ) : 1;
$counts         = $proposal_service->count_by_vendor( $user_id );
$total_pages    = (int) ceil( $counts['total'] / $per_page );
$proposals      = $proposal_service->get_by_vendor(
	$user_id,
	array(
		'limit'  => $per_page,
		'offset' => ( $proposals_page - 1 ) * $per_page,
	)
);
$status_labels  = ProposalService::get_statuses();
// The buyer never wrote a reason on these; they hired someone else. Say so.
$status_labels[ ProposalService::STATUS_REJECTED ] = __( 'Declined', 'wp-sell-services' );

// One query for the request posts and one for their meta, instead of two per row.
$request_ids = array_values( array_unique( array_map( static fn( $p ) => (int) $p->request_id, $proposals ) ) );
if ( $request_ids ) {
	_prime_post_caches( $request_ids, false, false );
	update_meta_cache( 'post', $request_ids );
}
?>

<div class="wpss-section wpss-section--proposals wpss-card">
	<div class="wpss-stats-grid">
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( (string) $counts[ ProposalService::STATUS_PENDING ] ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( (string) $counts[ ProposalService::STATUS_ACCEPTED ] ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Accepted', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( (string) $counts[ ProposalService::STATUS_REJECTED ] ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Declined', 'wp-sell-services' ); ?></span>
		</div>
	</div>

	<?php if ( empty( $proposals ) ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon">
				<i data-lucide="send" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
			</div>
			<h3><?php esc_html_e( 'No proposals yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'Proposals you send on buyer requests will appear here with their status.', 'wp-sell-services' ); ?></p>
			<?php $requests_url = get_post_type_archive_link( 'wpss_request' ); ?>
			<?php if ( $requests_url ) : ?>
				<a href="<?php echo esc_url( $requests_url ); ?>" class="wpss-btn wpss-btn--primary">
					<?php esc_html_e( 'Browse Requests', 'wp-sell-services' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="wpss-requests-list">
			<?php
			foreach ( $proposals as $proposal ) :
				$request_id  = (int) $proposal->request_id;
				$status      = (string) $proposal->status;
				$hired_id    = (int) get_post_meta( $request_id, '_wpss_accepted_proposal_id', true );
				$status_text = $status_labels[ $status ] ?? ucfirst( $status );
				if ( ProposalService::STATUS_REJECTED === $status && $hired_id && $hired_id !== (int) $proposal->id ) {
					$status_text = __( 'Not selected', 'wp-sell-services' );
				}
				$request_title = get_the_title( $request_id );
				$sent_on       = $proposal->created_at ? wp_date( get_option( 'date_format' ), strtotime( (string) $proposal->created_at ) ) : '';
				?>
				<div class="wpss-request-card">
					<div class="wpss-request-card__main">
						<h4 class="wpss-request-card__title">
							<?php echo esc_html( '' !== $request_title ? $request_title : __( '(request removed)', 'wp-sell-services' ) ); ?>
						</h4>
						<p class="wpss-request-card__excerpt"><?php echo esc_html( wp_trim_words( (string) $proposal->cover_letter, 20 ) ); ?></p>
						<div class="wpss-request-card__meta">
							<span>
								<?php
								printf(
									/* translators: %s: proposed price */
									esc_html__( 'Offer: %s', 'wp-sell-services' ),
									esc_html( wpss_format_price( (float) $proposal->proposed_price ) )
								);
								?>
							</span>
							<span class="wpss-request-card__sep">&bull;</span>
							<span>
								<?php
								printf(
									/* translators: %d: proposed delivery time in days */
									esc_html( _n( 'Delivery: %d day', 'Delivery: %d days', (int) $proposal->proposed_days, 'wp-sell-services' ) ),
									(int) $proposal->proposed_days
								);
								?>
							</span>
							<?php if ( $sent_on ) : ?>
								<span class="wpss-request-card__sep">&bull;</span>
								<span>
									<?php
									printf(
										/* translators: %s: date the proposal was sent */
										esc_html__( 'Sent %s', 'wp-sell-services' ),
										esc_html( $sent_on )
									);
									?>
								</span>
							<?php endif; ?>
						</div>
					</div>
					<div class="wpss-request-card__actions">
						<span class="wpss-status-badge wpss-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_text ); ?></span>
						<?php if ( '' !== $request_title ) : ?>
							<?php $view_label = __( 'View Request', 'wp-sell-services' ); ?>
							<a href="<?php echo esc_url( (string) get_permalink( $request_id ) ); ?>" class="wpss-btn wpss-btn--outline wpss-btn--sm wpss-btn--action" aria-label="<?php echo esc_attr( $view_label ); ?>" title="<?php echo esc_attr( $view_label ); ?>">
								<i data-lucide="megaphone" class="wpss-icon" aria-hidden="true"></i>
								<span class="wpss-btn__label"><?php echo esc_html( $view_label ); ?></span>
							</a>
						<?php endif; ?>
						<?php if ( ! empty( $proposal->order_id ) ) : ?>
							<?php $order_label = __( 'View Order', 'wp-sell-services' ); ?>
							<a href="<?php echo esc_url( wpss_get_order_url( (int) $proposal->order_id, 'sales' ) ); ?>" class="wpss-btn wpss-btn--outline wpss-btn--sm wpss-btn--action" aria-label="<?php echo esc_attr( $order_label ); ?>" title="<?php echo esc_attr( $order_label ); ?>">
								<i data-lucide="banknote" class="wpss-icon" aria-hidden="true"></i>
								<span class="wpss-btn__label"><?php echo esc_html( $order_label ); ?></span>
							</a>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Proposal pages', 'wp-sell-services' ); ?>">
				<?php
				$proposals_page_url = static function ( int $page ): string {
					return $page > 1 ? add_query_arg( 'proposals_page', $page ) : remove_query_arg( 'proposals_page' );
				};
	?>
				<?php if ( $proposals_page > 1 ) : ?>
					<a href="<?php echo esc_url( $proposals_page_url( $proposals_page - 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--prev">
						<i data-lucide="chevron-left" class="wpss-icon" aria-hidden="true"></i>
						<?php esc_html_e( 'Previous', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
				<span class="wpss-pagination__current">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Page %1$d of %2$d', 'wp-sell-services' ),
						(int) $proposals_page,
						(int) $total_pages
					);
					?>
				</span>
				<?php if ( $proposals_page < $total_pages ) : ?>
					<a href="<?php echo esc_url( $proposals_page_url( $proposals_page + 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--next">
						<?php esc_html_e( 'Next', 'wp-sell-services' ); ?>
						<i data-lucide="chevron-right" class="wpss-icon" aria-hidden="true"></i>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php
do_action( 'wpss_dashboard_section_after', 'proposals', $user_id );
