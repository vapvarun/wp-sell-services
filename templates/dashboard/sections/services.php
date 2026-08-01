<?php
/**
 * Dashboard Section: My Services (vendor only)
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
 * Fires before the services dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('services').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'services', $user_id );

// Save dashboard URL before custom query changes the global post.
$dashboard_url = get_permalink();

// Get vendor's services (paginated so the list doesn't hard-cap at 20).
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination param.
$services_page = isset( $_GET['services_page'] ) ? max( 1, absint( $_GET['services_page'] ) ) : 1;
$args          = array(
	'post_type'      => 'wpss_service',
	'author'         => $user_id,
	'posts_per_page' => 20,
	'paged'          => $services_page,
	'post_status'    => array( 'publish', 'draft', 'pending' ),
	'orderby'        => 'date',
	'order'          => 'DESC',
);

$services = new WP_Query( $args );

// Pre-fetch order counts for all displayed services in a single query.
$service_order_counts = array();
if ( $services->have_posts() ) {
	$displayed_ids = wp_list_pluck( $services->posts, 'ID' );
	if ( ! empty( $displayed_ids ) ) {
		global $wpdb;
		$orders_table = $wpdb->prefix . 'wpss_orders';
		$placeholders = implode( ',', array_fill( 0, count( $displayed_ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$counts = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT service_id, COUNT(*) AS order_count FROM {$orders_table} WHERE service_id IN ({$placeholders}) GROUP BY service_id", ...$displayed_ids ),
			OBJECT_K
		);
		foreach ( $displayed_ids as $sid ) {
			$service_order_counts[ $sid ] = isset( $counts[ $sid ] ) ? (int) $counts[ $sid ]->order_count : 0;
		}
	}
}

// Get stats.
$published_count = count(
	get_posts(
		array(
			'post_type'   => 'wpss_service',
			'author'      => $user_id,
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	)
);

// Draft count excludes rejected services (which also have draft post_status).
$all_draft_ids = get_posts(
	array(
		'post_type'   => 'wpss_service',
		'author'      => $user_id,
		'post_status' => 'draft',
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);

$rejected_count = 0;
$draft_count    = 0;

foreach ( $all_draft_ids as $draft_id ) {
	$mod_status = get_post_meta( $draft_id, '_wpss_moderation_status', true );
	if ( 'rejected' === $mod_status ) {
		++$rejected_count;
	} else {
		++$draft_count;
	}
}

$pending_count = count(
	get_posts(
		array(
			'post_type'   => 'wpss_service',
			'author'      => $user_id,
			'post_status' => 'pending',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	)
);
?>

<div class="wpss-section wpss-section--services wpss-card">
	<div class="wpss-stats-grid">
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $published_count ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Active', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $draft_count ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Drafts', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( $pending_count ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Pending Review', 'wp-sell-services' ); ?></span>
		</div>
		<?php if ( $rejected_count > 0 ) : ?>
			<div class="wpss-stat-card">
				<span class="wpss-stat-card__value"><?php echo esc_html( $rejected_count ); ?></span>
				<span class="wpss-stat-card__label"><?php esc_html_e( 'Rejected', 'wp-sell-services' ); ?></span>
			</div>
		<?php endif; ?>
	</div>

	<?php
	// Check service limit and display notice.
	$vendor_profile_obj = \WPSellServices\Models\VendorProfile::get_by_user_id( $user_id );
	$at_service_limit   = $vendor_profile_obj
		&& ! current_user_can( 'manage_options' )
		&& $vendor_profile_obj->has_reached_service_limit();

	if ( $at_service_limit ) :
		$max_services_allowed = $vendor_profile_obj->get_max_services();
		?>
		<div class="wpss-notice wpss-notice-warning" style="margin-bottom: 16px;">
			<?php
			printf(
				/* translators: %1$d: current count, %2$d: max allowed */
				esc_html__( 'You have reached your service limit (%1$d of %2$d). Remove an existing service to create a new one.', 'wp-sell-services' ),
				absint( $vendor_profile_obj->get_service_count() ),
				absint( $max_services_allowed )
			);
			?>
		</div>
	<?php endif; ?>

	<?php
	/**
	 * Fires in the services list area for bulk actions or filters.
	 *
	 * Allows developers to add bulk action buttons or filtering options.
	 *
	 * @since 1.1.0
	 *
	 * @param int $user_id Current user ID.
	 */
	do_action( 'wpss_services_list_actions', $user_id );
	?>

	<?php if ( ! $services->have_posts() ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon">
				<i data-lucide="briefcase" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
			</div>
			<h3><?php esc_html_e( 'No services yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'Create your first service to start receiving orders from buyers.', 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( wpss_append_dashboard_section( $dashboard_url, 'create' ) ); ?>" class="wpss-btn wpss-btn--primary">
				<?php esc_html_e( 'Create Your First Service', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wpss-service-grid">
			<?php
			while ( $services->have_posts() ) :
				$services->the_post();
				$service_id  = get_the_ID();
				$price       = get_post_meta( $service_id, '_wpss_starting_price', true );
				$views       = (int) get_post_meta( $service_id, '_wpss_views', true );
				$orders      = $service_order_counts[ $service_id ] ?? 0;
				$item_status = get_post_status();

				// Check moderation meta for rejected services (stored as draft post_status).
				$moderation_status = get_post_meta( $service_id, '_wpss_moderation_status', true );
				$is_rejected       = false;
				$rejection_reason  = '';
				if ( 'draft' === $item_status && 'rejected' === $moderation_status ) {
					$item_status = 'rejected';
					$is_rejected = true;
					// Surface the reviewer's reason so the vendor knows what to fix.
					$rejection_reason = (string) get_post_meta( $service_id, '_wpss_rejection_reason', true );
					if ( '' === $rejection_reason ) {
						$rejection_reason = (string) get_post_meta( $service_id, '_wpss_moderation_notes', true );
					}
				}
				?>
				<div class="wpss-service-card wpss-service-card--dashboard<?php echo $is_rejected ? ' wpss-service-card--rejected' : ''; ?>">
					<div class="wpss-service-card__image">
						<?php
						$has_thumb     = has_post_thumbnail();
						$gallery_thumb = null;

						// Fallback to first gallery image if no featured image.
						if ( ! $has_thumb ) {
							$gallery_raw = get_post_meta( $service_id, '_wpss_gallery', true );
							$gallery_ids = wpss_get_gallery_ids( $gallery_raw );
							if ( ! empty( $gallery_ids[0] ) ) {
								$gallery_thumb = $gallery_ids[0];
							}
						}
						?>
						<?php if ( $has_thumb ) : ?>
							<?php the_post_thumbnail( 'medium' ); ?>
						<?php elseif ( $gallery_thumb ) : ?>
							<?php echo wp_get_attachment_image( $gallery_thumb, 'medium' ); ?>
						<?php else : ?>
							<div class="wpss-service-card__placeholder"></div>
						<?php endif; ?>
						<span class="wpss-service-card__status wpss-service-card__status--<?php echo esc_attr( $item_status ); ?>">
							<?php
							if ( 'rejected' === $item_status ) {
								esc_html_e( 'Rejected', 'wp-sell-services' );
							} else {
								$status_obj = get_post_status_object( $item_status );
								echo esc_html( $status_obj ? $status_obj->label : ucfirst( $item_status ) );
							}
							?>
						</span>
					</div>
					<div class="wpss-service-card__body">
						<h4 class="wpss-service-card__title"><?php the_title(); ?></h4>
						<div class="wpss-service-card__stats">
							<span><?php echo esc_html( $views ); ?> <?php echo esc_html( _n( 'view', 'views', $views, 'wp-sell-services' ) ); ?></span>
							<span class="wpss-service-card__sep">&bull;</span>
							<span><?php echo esc_html( $orders ); ?> <?php echo esc_html( _n( 'order', 'orders', $orders, 'wp-sell-services' ) ); ?></span>
						</div>
						<?php if ( $price ) : ?>
							<div class="wpss-service-card__price">
								<?php
								printf(
									/* translators: %s: price */
									esc_html__( 'From %s', 'wp-sell-services' ),
									esc_html( wpss_format_price( $price ) )
								);
								?>
							</div>
						<?php endif; ?>

						<?php
						if ( $is_rejected ) :
							$wpss_resubmit_url = add_query_arg(
								array(
									'section' => 'create',
									'id'      => $service_id,
								),
								$dashboard_url
							);
							?>
							<div class="wpss-service-card__rejection wpss-notice wpss-notice--error" role="status">
								<p class="wpss-service-card__rejection-title">
									<i data-lucide="alert-triangle" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
									<?php esc_html_e( 'This service was not approved.', 'wp-sell-services' ); ?>
								</p>
								<?php if ( '' !== $rejection_reason ) : ?>
									<p class="wpss-service-card__rejection-reason">
										<strong><?php esc_html_e( 'Reviewer feedback:', 'wp-sell-services' ); ?></strong>
										<?php echo esc_html( $rejection_reason ); ?>
									</p>
								<?php endif; ?>
								<p class="wpss-service-card__rejection-help">
									<?php esc_html_e( 'Edit your service to address the feedback, then resubmit it for review. A reviewer will check it again before it goes live.', 'wp-sell-services' ); ?>
								</p>
								<a href="<?php echo esc_url( $wpss_resubmit_url ); ?>" class="wpss-btn wpss-btn--primary wpss-btn--sm wpss-service-card__resubmit">
									<i data-lucide="refresh-cw" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
									<?php esc_html_e( 'Resubmit for review', 'wp-sell-services' ); ?>
								</a>
							</div>
						<?php endif; ?>
					</div>
					<div class="wpss-service-card__actions">
						<?php
						$wpss_edit_url = add_query_arg(
							array(
								'section' => 'create',
								'id'      => $service_id,
							),
							$dashboard_url
						);
						?>
						<?php if ( ! $is_rejected ) : ?>
							<a href="<?php echo esc_url( $wpss_edit_url ); ?>" class="wpss-btn wpss-btn--outline wpss-btn--sm">
								<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
							</a>
						<?php endif; ?>
						<a href="<?php the_permalink(); ?>" class="wpss-btn wpss-btn--ghost wpss-btn--sm" target="_blank">
							<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
						</a>
						<?php
						// Pause/Publish toggle - only for the two vendor-controllable
						// states. Pending + rejected services route through moderation
						// (use the Resubmit flow), so no direct toggle for those.
						$wpss_real_status = get_post_status( $service_id );
						if ( in_array( $wpss_real_status, array( 'publish', 'draft' ), true ) && 'rejected' !== $item_status ) :
							?>
							<button type="button"
								class="wpss-btn wpss-btn--outline wpss-btn--sm wpss-toggle-status"
								data-service-id="<?php echo esc_attr( $service_id ); ?>"
								data-current-status="<?php echo esc_attr( $wpss_real_status ); ?>">
								<?php
								echo 'publish' === $wpss_real_status
									? esc_html__( 'Pause', 'wp-sell-services' )
									: esc_html__( 'Publish', 'wp-sell-services' );
								?>
							</button>
						<?php endif; ?>
						<button type="button" class="wpss-btn wpss-btn--danger wpss-btn--sm wpss-delete-service" data-service-id="<?php echo esc_attr( $service_id ); ?>">
							<?php esc_html_e( 'Delete', 'wp-sell-services' ); ?>
						</button>
					</div>
				</div>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		</div>

		<?php if ( (int) $services->max_num_pages > 1 ) : ?>
			<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Service pages', 'wp-sell-services' ); ?>">
				<?php
				// Paginate relative to the current section URL (see orders.php).
				$services_page_url = static function ( int $page ): string {
					return $page > 1 ? add_query_arg( 'services_page', $page ) : remove_query_arg( 'services_page' );
				};
	?>
				<?php if ( $services_page > 1 ) : ?>
					<a href="<?php echo esc_url( $services_page_url( $services_page - 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--prev">
						<i data-lucide="chevron-left" class="wpss-icon" aria-hidden="true"></i>
						<?php esc_html_e( 'Previous', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
				<span class="wpss-pagination__current">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Page %1$d of %2$d', 'wp-sell-services' ),
						(int) $services_page,
						(int) $services->max_num_pages
					);
					?>
				</span>
				<?php if ( $services_page < (int) $services->max_num_pages ) : ?>
					<a href="<?php echo esc_url( $services_page_url( $services_page + 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--next">
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
 * Fires after the services dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('services').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'services', $user_id );
?>
