<?php
/**
 * Dashboard Section: Buyer Requests
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int            $user_id        Current user ID.
 * @var VendorService  $vendor_service Vendor service instance.
 * @var bool           $is_vendor      Whether user is a vendor.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fires before the requests dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('requests').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'requests', $user_id );

// Get user's buyer requests (paginated so the list doesn't hard-cap at 20).
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination param.
$requests_page = isset( $_GET['requests_page'] ) ? max( 1, absint( $_GET['requests_page'] ) ) : 1;
$args          = array(
	'post_type'      => 'wpss_request',
	'author'         => $user_id,
	'posts_per_page' => 20,
	'paged'          => $requests_page,
	'post_status'    => array( 'publish', 'draft', 'pending' ),
	'orderby'        => 'date',
	'order'          => 'DESC',
);

$requests = new WP_Query( $args );

// Get stats.
$active_count = count(
	get_posts(
		array(
			'post_type'   => 'wpss_request',
			'author'      => $user_id,
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	)
);
?>

<div class="wpss-section wpss-section--requests wpss-card">
	<div class="wpss-stats-grid">
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $active_count ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Active Requests', 'wp-sell-services' ); ?></span>
		</div>
	</div>

	<?php if ( ! $requests->have_posts() ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon">
				<i data-lucide="megaphone" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
			</div>
			<h3><?php esc_html_e( 'No requests yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( "Can't find the right service? Post a request and let sellers come to you.", 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( wpss_append_dashboard_section( wpss_get_page_url( 'dashboard' ), 'create-request' ) ); ?>" class="wpss-btn wpss-btn--primary">
				<?php esc_html_e( 'Post a Request', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wpss-requests-list">
			<?php
			while ( $requests->have_posts() ) :
				$requests->the_post();
				$request_id = get_the_ID();
				// Real requests store budget as _wpss_budget_min/_max (+ _wpss_budget_type)
				// and the timeframe as _wpss_delivery_days. The old singular _wpss_budget
				// and _wpss_deadline keys are never written by the create flow, so the
				// card showed both blank for every real request.
				$budget_min    = (float) get_post_meta( $request_id, '_wpss_budget_min', true );
				$budget_max    = (float) get_post_meta( $request_id, '_wpss_budget_max', true );
				$delivery_days = (int) get_post_meta( $request_id, '_wpss_delivery_days', true );

				$budget_display = '';
				if ( $budget_min > 0 && $budget_max > 0 && $budget_min !== $budget_max ) {
					$budget_display = wpss_format_price( $budget_min ) . ' – ' . wpss_format_price( $budget_max );
				} elseif ( $budget_max > 0 ) {
					$budget_display = wpss_format_price( $budget_max );
				} elseif ( $budget_min > 0 ) {
					$budget_display = wpss_format_price( $budget_min );
				}
				// Query actual proposal count from DB instead of potentially stale meta.
				global $wpdb;
				$offers      = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_proposals WHERE request_id = %d",
						$request_id
					)
				);
				$item_status = get_post_status();
				?>
				<div class="wpss-request-card">
					<div class="wpss-request-card__main">
						<h4 class="wpss-request-card__title"><?php the_title(); ?></h4>
						<p class="wpss-request-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></p>
						<div class="wpss-request-card__meta">
							<?php if ( $budget_display ) : ?>
								<span>
									<?php
									printf(
										/* translators: %s: budget amount or range */
										esc_html__( 'Budget: %s', 'wp-sell-services' ),
										esc_html( $budget_display )
									);
									?>
								</span>
							<?php endif; ?>
							<?php if ( $delivery_days > 0 ) : ?>
								<span class="wpss-request-card__sep">&bull;</span>
								<span>
									<?php
									printf(
										/* translators: %d: desired delivery time in days */
										esc_html( _n( 'Delivery: %d day', 'Delivery: %d days', $delivery_days, 'wp-sell-services' ) ),
										esc_html( $delivery_days )
									);
									?>
								</span>
							<?php endif; ?>
							<span class="wpss-request-card__sep">&bull;</span>
							<span>
								<?php
								printf(
									/* translators: %d: number of offers */
									esc_html( _n( '%d offer', '%d offers', $offers, 'wp-sell-services' ) ),
									esc_html( $offers )
								);
								?>
							</span>
						</div>
					</div>
					<div class="wpss-request-card__actions">
						<span class="wpss-status wpss-status--<?php echo esc_attr( $item_status ); ?>">
							<?php
							$status_obj = get_post_status_object( $item_status );
							echo esc_html( $status_obj ? $status_obj->label : ucfirst( $item_status ) );
							?>
						</span>
						<?php
						// Icon action buttons: label is visible on desktop and collapses to
						// an icon + tooltip on mobile/tablet so the full action set stays on
						// one line at every width (Basecamp #9985554351). aria-label + title
						// keep every control named for assistive tech and hover tooltips.
						$view_offers_label = __( 'View Offers', 'wp-sell-services' );
						?>
						<a href="<?php the_permalink(); ?>" class="wpss-btn wpss-btn--outline wpss-btn--sm wpss-btn--action" aria-label="<?php echo esc_attr( $view_offers_label ); ?>" title="<?php echo esc_attr( $view_offers_label ); ?>">
							<i data-lucide="inbox" class="wpss-icon" aria-hidden="true"></i>
							<span class="wpss-btn__label"><?php esc_html_e( 'View Offers', 'wp-sell-services' ); ?></span>
						</a>
						<?php if ( 'publish' === $item_status ) : ?>
							<?php $close_label = __( 'Close', 'wp-sell-services' ); ?>
							<button type="button" class="wpss-btn wpss-btn--ghost wpss-btn--sm wpss-btn--action wpss-close-request" data-request-id="<?php echo esc_attr( $request_id ); ?>" aria-label="<?php echo esc_attr( $close_label ); ?>" title="<?php echo esc_attr( $close_label ); ?>">
								<i data-lucide="lock" class="wpss-icon" aria-hidden="true"></i>
								<span class="wpss-btn__label"><?php esc_html_e( 'Close', 'wp-sell-services' ); ?></span>
							</button>
						<?php elseif ( 'draft' === $item_status ) : ?>
							<?php $reopen_label = __( 'Reopen', 'wp-sell-services' ); ?>
							<button type="button" class="wpss-btn wpss-btn--ghost wpss-btn--sm wpss-btn--action wpss-reopen-request" data-request-id="<?php echo esc_attr( $request_id ); ?>" aria-label="<?php echo esc_attr( $reopen_label ); ?>" title="<?php echo esc_attr( $reopen_label ); ?>">
								<i data-lucide="rotate-ccw" class="wpss-icon" aria-hidden="true"></i>
								<span class="wpss-btn__label"><?php esc_html_e( 'Reopen', 'wp-sell-services' ); ?></span>
							</button>
						<?php endif; ?>
						<?php $edit_label = __( 'Edit', 'wp-sell-services' ); ?>
						<a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'section'    => 'edit-request',
									'request_id' => $request_id,
								),
								wpss_get_page_url( 'dashboard' ) ?: get_permalink()
							)
						);
						?>
									" class="wpss-btn wpss-btn--outline wpss-btn--sm wpss-btn--action" aria-label="<?php echo esc_attr( $edit_label ); ?>" title="<?php echo esc_attr( $edit_label ); ?>">
							<i data-lucide="pencil" class="wpss-icon" aria-hidden="true"></i>
							<span class="wpss-btn__label"><?php esc_html_e( 'Edit', 'wp-sell-services' ); ?></span>
						</a>
						<?php $delete_label = __( 'Delete', 'wp-sell-services' ); ?>
						<button type="button" class="wpss-btn wpss-btn--ghost wpss-btn--sm wpss-btn--danger wpss-btn--action wpss-delete-request" data-request-id="<?php echo esc_attr( $request_id ); ?>" aria-label="<?php echo esc_attr( $delete_label ); ?>" title="<?php echo esc_attr( $delete_label ); ?>">
							<i data-lucide="trash-2" class="wpss-icon" aria-hidden="true"></i>
							<span class="wpss-btn__label"><?php esc_html_e( 'Delete', 'wp-sell-services' ); ?></span>
						</button>
					</div>
				</div>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		</div>

		<?php if ( (int) $requests->max_num_pages > 1 ) : ?>
			<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Request pages', 'wp-sell-services' ); ?>">
				<?php
				// Paginate relative to the current section URL (see orders.php).
				$requests_page_url = static function ( int $page ): string {
					return $page > 1 ? add_query_arg( 'requests_page', $page ) : remove_query_arg( 'requests_page' );
				};
	?>
				<?php if ( $requests_page > 1 ) : ?>
					<a href="<?php echo esc_url( $requests_page_url( $requests_page - 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--prev">
						<i data-lucide="chevron-left" class="wpss-icon" aria-hidden="true"></i>
						<?php esc_html_e( 'Previous', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
				<span class="wpss-pagination__current">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Page %1$d of %2$d', 'wp-sell-services' ),
						(int) $requests_page,
						(int) $requests->max_num_pages
					);
					?>
				</span>
				<?php if ( $requests_page < (int) $requests->max_num_pages ) : ?>
					<a href="<?php echo esc_url( $requests_page_url( $requests_page + 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--next">
						<?php esc_html_e( 'Next', 'wp-sell-services' ); ?>
						<i data-lucide="chevron-right" class="wpss-icon" aria-hidden="true"></i>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the requests dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('requests').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'requests', $user_id );
?>
