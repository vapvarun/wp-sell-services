<?php
/**
 * Vendor Services - My Account Template
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var array $services WordPress posts.
 * @var int   $user_id  Current user ID.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fires before the vendor services content.
 *
 * @since 1.1.0
 *
 * @param int $user_id Current user ID.
 */
do_action( 'wpss_vendor_services_before', $user_id );
?>

<div class="wpss-vendor-services">
	<div class="wpss-services-header">
		<h2><?php esc_html_e( 'My Services', 'wp-sell-services' ); ?></h2>
		<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="button button-primary">
			<?php esc_html_e( 'Add New Service', 'wp-sell-services' ); ?>
		</a>
	</div>

	<?php if ( empty( $services ) ) : ?>
		<div class="wpss-no-services">
			<p><?php esc_html_e( 'You haven\'t created any services yet.', 'wp-sell-services' ); ?></p>
			<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Create Your First Service', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php else : ?>
		<div class="wpss-services-grid">
			<?php foreach ( $services as $service_post ) : ?>
				<?php
				$service = \WPSellServices\Models\Service::from_post( $service_post );
				?>
				<div class="wpss-service-card">
					<div class="wpss-service-image">
						<?php if ( $service->thumbnail_id ) : ?>
							<img src="<?php echo esc_url( $service->get_thumbnail_url( 'medium' ) ); ?>" alt="">
						<?php else : ?>
							<div class="wpss-no-image"><?php esc_html_e( 'No Image', 'wp-sell-services' ); ?></div>
						<?php endif; ?>
						<span class="wpss-service-status wpss-status-<?php echo esc_attr( $service->status ); ?>">
							<?php echo esc_html( get_post_status_object( $service->status )->label ); ?>
						</span>
					</div>
					<div class="wpss-service-info">
						<h3 class="wpss-service-title">
							<a href="<?php echo esc_url( get_edit_post_link( $service->id ) ); ?>">
								<?php echo esc_html( $service->title ); ?>
							</a>
						</h3>
						<div class="wpss-service-meta">
							<span class="wpss-service-price">
								<?php
								$price = $service->get_starting_price();
								if ( $price > 0 ) {
									/* translators: %s: price */
									printf( esc_html__( 'From %s', 'wp-sell-services' ), wp_kses_post( wpss_format_price( $price ) ) );
								} else {
									esc_html_e( 'Price not set', 'wp-sell-services' );
								}
								?>
							</span>
							<span class="wpss-service-stats">
								<?php if ( $service->rating > 0 ) : ?>
									<span class="wpss-rating">★ <?php echo esc_html( number_format( $service->rating, 1 ) ); ?></span>
									<span class="wpss-reviews">(<?php echo esc_html( $service->review_count ); ?>)</span>
								<?php endif; ?>
								<span class="wpss-orders"><?php echo esc_html( $service->orders_completed ); ?> <?php esc_html_e( 'orders', 'wp-sell-services' ); ?></span>
							</span>
						</div>
						<div class="wpss-service-actions">
							<a href="<?php echo esc_url( get_edit_post_link( $service->id ) ); ?>" class="button wpss-button-small">
								<?php esc_html_e( 'Edit', 'wp-sell-services' ); ?>
							</a>
							<a href="<?php echo esc_url( $service->get_permalink() ); ?>" class="button wpss-button-small" target="_blank">
								<?php esc_html_e( 'View', 'wp-sell-services' ); ?>
							</a>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the vendor services content.
 *
 * @since 1.1.0
 *
 * @param int $user_id Current user ID.
 */
do_action( 'wpss_vendor_services_after', $user_id );
?>
