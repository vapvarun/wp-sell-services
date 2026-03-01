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

// Get vendor's services.
$args = array(
	'post_type'      => 'wpss_service',
	'author'         => $user_id,
	'posts_per_page' => 20,
	'post_status'    => array( 'publish', 'draft', 'pending' ),
	'orderby'        => 'date',
	'order'          => 'DESC',
);

$services = new WP_Query( $args );

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

<div class="wpss-section wpss-section--services">
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
	$at_service_limit   = $vendor_profile_obj && $vendor_profile_obj->has_reached_service_limit();

	if ( $at_service_limit ) :
		$max_services_allowed = $vendor_profile_obj->get_max_services();
		?>
		<div class="wpss-notice wpss-notice-warning" style="margin-bottom: 16px;">
			<?php
			printf(
				/* translators: %1$d: current count, %2$d: max allowed */
				esc_html__( 'You have reached your service limit (%1$d of %2$d). Remove an existing service to create a new one.', 'wp-sell-services' ),
				$vendor_profile_obj->get_service_count(),
				$max_services_allowed
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
				<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
			</div>
			<h3><?php esc_html_e( 'No services yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'Create your first service to start selling.', 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( add_query_arg( 'section', 'create', $dashboard_url ) ); ?>" class="wpss-btn wpss-btn--primary">
				<?php esc_html_e( 'Create Service', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wpss-service-grid">
			<?php
			while ( $services->have_posts() ) :
				$services->the_post();
				$service_id  = get_the_ID();
				$price       = get_post_meta( $service_id, '_wpss_starting_price', true );
				$views       = (int) get_post_meta( $service_id, 'wpss_views', true );
				$orders      = (int) get_post_meta( $service_id, 'wpss_orders', true );
				$item_status = get_post_status();

				// Check moderation meta for rejected services (stored as draft post_status).
				$moderation_status = get_post_meta( $service_id, '_wpss_moderation_status', true );
				if ( 'draft' === $item_status && 'rejected' === $moderation_status ) {
					$item_status = 'rejected';
				}
				?>
				<div class="wpss-service-card wpss-service-card--dashboard">
					<div class="wpss-service-card__image">
						<?php
						$has_thumb     = has_post_thumbnail();
						$gallery_thumb = null;

						// Fallback to first gallery image if no featured image.
						if ( ! $has_thumb ) {
							$gallery_raw = get_post_meta( $service_id, '_wpss_gallery', true );
							if ( is_array( $gallery_raw ) && isset( $gallery_raw['images'] ) && ! empty( $gallery_raw['images'][0] ) ) {
								$gallery_thumb = absint( $gallery_raw['images'][0] );
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
							<span><?php echo esc_html( $views ); ?> <?php esc_html_e( 'views', 'wp-sell-services' ); ?></span>
							<span class="wpss-service-card__sep">&bull;</span>
							<span><?php echo esc_html( $orders ); ?> <?php esc_html_e( 'orders', 'wp-sell-services' ); ?></span>
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
					</div>
					<div class="wpss-service-card__actions">
						<a href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'section' => 'create',
									'id'      => $service_id,
								),
								$dashboard_url
							)
						);
						?>
									" class="wpss-btn wpss-btn--outline wpss-btn--sm">
							<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
						</a>
						<a href="<?php the_permalink(); ?>" class="wpss-btn wpss-btn--ghost wpss-btn--sm" target="_blank">
							<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
						</a>
						<button type="button" class="wpss-btn wpss-btn--danger wpss-btn--sm wpss-delete-service" data-service-id="<?php echo esc_attr( $service_id ); ?>">
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
 * Fires after the services dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('services').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'services', $user_id );
?>
