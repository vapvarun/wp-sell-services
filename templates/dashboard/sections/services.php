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

$draft_count = count(
	get_posts(
		array(
			'post_type'   => 'wpss_service',
			'author'      => $user_id,
			'post_status' => 'draft',
			'numberposts' => -1,
			'fields'      => 'ids',
		)
	)
);

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
	</div>

	<?php if ( ! $services->have_posts() ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon">
				<svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
			</div>
			<h3><?php esc_html_e( 'No services yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'Create your first service to start selling.', 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( add_query_arg( 'section', 'create', get_permalink() ) ); ?>" class="wpss-btn wpss-btn--primary">
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
				?>
				<div class="wpss-service-card wpss-service-card--dashboard">
					<div class="wpss-service-card__image">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'medium' ); ?>
						<?php else : ?>
							<div class="wpss-service-card__placeholder"></div>
						<?php endif; ?>
						<span class="wpss-service-card__status wpss-service-card__status--<?php echo esc_attr( $item_status ); ?>">
							<?php
							$status_obj = get_post_status_object( $item_status );
							echo esc_html( $status_obj ? $status_obj->label : ucfirst( $item_status ) );
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
								get_permalink()
							)
						);
						?>
									" class="wpss-btn wpss-btn--outline wpss-btn--sm">
							<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
						</a>
						<a href="<?php the_permalink(); ?>" class="wpss-btn wpss-btn--ghost wpss-btn--sm" target="_blank">
							<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
						</a>
					</div>
				</div>
			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>
		</div>
	<?php endif; ?>
</div>
