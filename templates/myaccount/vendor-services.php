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
			<?php foreach ( $services as $post ) : ?>
				<?php
				$service = \WPSellServices\Models\Service::from_post( $post );
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
									printf( esc_html__( 'From %s', 'wp-sell-services' ), wpss_format_price( $price ) );
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

<style>
.wpss-vendor-services {
	padding: 20px 0;
}

.wpss-services-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20px;
}

.wpss-services-header h2 {
	margin: 0;
}

.wpss-services-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
	gap: 20px;
}

.wpss-service-card {
	border: 1px solid #e5e5e5;
	border-radius: 8px;
	overflow: hidden;
	background: #fff;
}

.wpss-service-image {
	position: relative;
	height: 160px;
	background: #f5f5f5;
}

.wpss-service-image img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.wpss-no-image {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	color: #999;
}

.wpss-service-status {
	position: absolute;
	top: 10px;
	left: 10px;
	padding: 4px 8px;
	border-radius: 4px;
	font-size: 11px;
	font-weight: 500;
	background: #fff;
}

.wpss-status-publish { background: #00b894; color: #fff; }
.wpss-status-pending { background: #fdcb6e; color: #333; }
.wpss-status-draft { background: #636e72; color: #fff; }

.wpss-service-info {
	padding: 15px;
}

.wpss-service-title {
	margin: 0 0 10px;
	font-size: 16px;
}

.wpss-service-title a {
	color: inherit;
	text-decoration: none;
}

.wpss-service-meta {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 15px;
	font-size: 13px;
}

.wpss-service-price {
	font-weight: 600;
	color: #00b894;
}

.wpss-service-stats {
	color: #636e72;
}

.wpss-rating {
	color: #f39c12;
}

.wpss-service-actions {
	display: flex;
	gap: 8px;
}

.wpss-no-services {
	text-align: center;
	padding: 40px 20px;
}
</style>
