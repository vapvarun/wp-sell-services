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

// Get user's buyer requests.
$args = array(
	'post_type'      => 'wpss_request',
	'author'         => $user_id,
	'posts_per_page' => 20,
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

<div class="wpss-section wpss-section--requests">
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
						<a href="<?php the_permalink(); ?>" class="wpss-btn wpss-btn--outline wpss-btn--sm">
							<?php esc_html_e( 'View Offers', 'wp-sell-services' ); ?>
						</a>
						<?php if ( 'publish' === $item_status ) : ?>
							<button type="button" class="wpss-btn wpss-btn--link wpss-btn--sm wpss-close-request" data-request-id="<?php echo esc_attr( $request_id ); ?>">
								<?php esc_html_e( 'Close', 'wp-sell-services' ); ?>
							</button>
						<?php elseif ( 'draft' === $item_status ) : ?>
							<button type="button" class="wpss-btn wpss-btn--link wpss-btn--sm wpss-reopen-request" data-request-id="<?php echo esc_attr( $request_id ); ?>">
								<?php esc_html_e( 'Reopen', 'wp-sell-services' ); ?>
							</button>
						<?php endif; ?>
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
									" class="wpss-btn wpss-btn--outline wpss-btn--sm">
							<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
						</a>
						<button type="button" class="wpss-btn wpss-btn--link wpss-btn--sm wpss-btn--danger wpss-delete-request" data-request-id="<?php echo esc_attr( $request_id ); ?>">
							<?php esc_html_e( 'Delete', 'wp-sell-services' ); ?>
						</button>
					</div>
				</div>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		</div>
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
