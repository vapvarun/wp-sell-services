<?php
/**
 * Template Partial: Service Packages Widget
 *
 * Displays the pricing packages/tiers for a service.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var WPSellServices\Models\Service $service Service object.
 * @var int                            $service_id Service post ID.
 * @var array                          $packages Service packages array.
 */

defined( 'ABSPATH' ) || exit;

$service_id = get_the_ID();
$packages   = get_post_meta( $service_id, '_wpss_packages', true ) ?: [];

// If no packages, show single price.
if ( empty( $packages ) ) {
	$price = (float) get_post_meta( $service_id, '_wpss_starting_price', true );
	$delivery_time = get_post_meta( $service_id, '_wpss_delivery_days', true );

	$packages = [
		[
			'name'          => __( 'Standard', 'wp-sell-services' ),
			'description'   => get_the_excerpt(),
			'price'         => $price,
			'delivery_time' => $delivery_time,
			'revisions'     => get_post_meta( $service_id, '_wpss_revisions', true ) ?: 1,
			'features'      => [],
		],
	];
}

$first_package_key = array_key_first( $packages );

/**
 * Fires before the service packages widget.
 *
 * @since 1.0.0
 *
 * @param int $service_id Service post ID.
 */
do_action( 'wpss_before_service_packages', $service_id );
?>

<div class="wpss-packages-widget">
	<?php if ( count( $packages ) > 1 ) : ?>
		<div class="wpss-packages-tabs">
			<?php foreach ( $packages as $index => $package ) : ?>
				<button type="button"
						class="wpss-package-tab <?php echo $first_package_key === $index ? 'active' : ''; ?>"
						data-package="<?php echo esc_attr( $index ); ?>">
					<?php echo esc_html( $package['name'] ?? __( 'Package', 'wp-sell-services' ) ); ?>
				</button>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="wpss-packages-content">
		<?php foreach ( $packages as $index => $package ) : ?>
			<?php
			/**
			 * Fires before a single package tab.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $service_id   Service post ID.
			 * @param string $package_type Package index/type.
			 * @param array  $package      Package data.
			 */
			do_action( 'wpss_before_package_tab', $service_id, $index, $package );
			?>

			<div class="wpss-package <?php echo $first_package_key === $index ? 'active' : ''; ?>"
				 data-package="<?php echo esc_attr( $index ); ?>">

				<div class="wpss-package-header">
					<h3 class="wpss-package-name"><?php echo esc_html( $package['name'] ?? '' ); ?></h3>
					<div class="wpss-package-price">
						<?php
						$price_html = wpss_format_price( (float) ( $package['price'] ?? 0 ) );

						/**
						 * Filters the package price HTML.
						 *
						 * @since 1.0.0
						 *
						 * @param string $price_html Price HTML output.
						 * @param array  $package    Package data.
						 * @param int    $service_id Service post ID.
						 */
						$price_html = apply_filters( 'wpss_package_price_html', $price_html, $package, $service_id );

						echo esc_html( $price_html );
						?>
					</div>
				</div>

				<?php if ( ! empty( $package['description'] ) ) : ?>
					<p class="wpss-package-description">
						<?php echo esc_html( $package['description'] ); ?>
					</p>
				<?php endif; ?>

				<ul class="wpss-package-details">
					<?php if ( ! empty( $package['delivery_time'] ) ) : ?>
						<li>
							<span class="wpss-detail-icon wpss-icon-clock"></span>
							<span class="wpss-detail-label"><?php esc_html_e( 'Delivery Time', 'wp-sell-services' ); ?></span>
							<span class="wpss-detail-value">
								<?php
								$days = (int) $package['delivery_time'];
								printf(
									/* translators: %d: number of days */
									esc_html( _n( '%d Day', '%d Days', $days, 'wp-sell-services' ) ),
									$days
								);
								?>
							</span>
						</li>
					<?php endif; ?>

					<?php if ( isset( $package['revisions'] ) ) : ?>
						<li>
							<span class="wpss-detail-icon wpss-icon-revision"></span>
							<span class="wpss-detail-label"><?php esc_html_e( 'Revisions', 'wp-sell-services' ); ?></span>
							<span class="wpss-detail-value">
								<?php
								$revisions = $package['revisions'];
								if ( -1 === (int) $revisions || 'unlimited' === $revisions ) {
									esc_html_e( 'Unlimited', 'wp-sell-services' );
								} else {
									echo esc_html( $revisions );
								}
								?>
							</span>
						</li>
					<?php endif; ?>
				</ul>

				<?php if ( ! empty( $package['features'] ) ) : ?>
					<ul class="wpss-package-features">
						<?php foreach ( $package['features'] as $feature ) : ?>
							<li class="<?php echo ! empty( $feature['included'] ) ? 'included' : 'not-included'; ?>">
								<span class="wpss-feature-icon"></span>
								<?php echo esc_html( $feature['text'] ?? $feature ); ?>
							</li>
						<?php endforeach; ?>

						<?php
						/**
						 * Fires inside the package features list.
						 *
						 * @since 1.0.0
						 *
						 * @param int    $service_id   Service post ID.
						 * @param string $package_type Package index/type.
						 * @param array  $package      Package data.
						 */
						do_action( 'wpss_package_features', $service_id, $index, $package );
						?>
					</ul>
				<?php endif; ?>

				<div class="wpss-package-action">
					<?php
					$vendor_id = (int) get_post_field( 'post_author', $service_id );
					$is_own_service = get_current_user_id() === $vendor_id;
					?>

					<?php if ( $is_own_service ) : ?>
						<?php
						$dashboard_edit_url = wpss_get_page_url( 'dashboard' );
						if ( $dashboard_edit_url ) {
							$dashboard_edit_url = add_query_arg(
								array(
									'section' => 'create',
									'id'      => $service_id,
								),
								$dashboard_edit_url
							);
						} else {
							$dashboard_edit_url = admin_url( 'post.php?post=' . $service_id . '&action=edit' );
						}
						?>
						<a href="<?php echo esc_url( $dashboard_edit_url ); ?>"
						   class="wpss-btn wpss-btn-secondary wpss-btn-block">
							<?php esc_html_e( 'Edit Service', 'wp-sell-services' ); ?>
						</a>
					<?php else : ?>
						<?php
						$button_text = __( 'Continue', 'wp-sell-services' );

						/**
						 * Filters the package button text.
						 *
						 * @since 1.0.0
						 *
						 * @param string $button_text  Button text.
						 * @param string $package_type Package index/type.
						 */
						$button_text = apply_filters( 'wpss_package_button_text', $button_text, $index );
						?>

						<button type="button"
								class="wpss-btn wpss-btn-primary wpss-btn-block wpss-order-btn"
								data-service="<?php echo esc_attr( $service_id ); ?>"
								data-package="<?php echo esc_attr( $index ); ?>"
								data-price="<?php echo esc_attr( $package['price'] ?? 0 ); ?>">
							<?php echo esc_html( $button_text ); ?>
							<span class="wpss-btn-price">(<?php echo esc_html( wpss_format_price( (float) ( $package['price'] ?? 0 ) ) ); ?>)</span>
						</button>
					<?php endif; ?>
				</div>
			</div>

			<?php
			/**
			 * Fires after a single package tab.
			 *
			 * @since 1.0.0
			 *
			 * @param int    $service_id   Service post ID.
			 * @param string $package_type Package index/type.
			 * @param array  $package      Package data.
			 */
			do_action( 'wpss_after_package_tab', $service_id, $index, $package );
			?>
		<?php endforeach; ?>
	</div>

	<?php if ( ! $is_own_service ) : ?>
		<div class="wpss-contact-seller">
			<a href="#" class="wpss-contact-link" data-vendor="<?php echo esc_attr( $vendor_id ); ?>">
				<?php esc_html_e( 'Contact Seller', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * Fires after the service packages widget.
 *
 * @since 1.0.0
 *
 * @param int $service_id Service post ID.
 */
do_action( 'wpss_after_service_packages', $service_id );
?>
