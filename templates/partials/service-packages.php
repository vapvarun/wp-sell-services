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
					<?php
					// Support both 'delivery_days' (saved by wizard) and 'delivery_time' (legacy).
					$delivery_days_value = $package['delivery_days'] ?? ( $package['delivery_time'] ?? 0 );
					?>
					<?php if ( ! empty( $delivery_days_value ) ) : ?>
						<li>
							<span class="wpss-detail-icon wpss-icon-clock">
								<svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22ZM12 20C16.4183 20 20 16.4183 20 12C20 7.58172 16.4183 4 12 4C7.58172 4 4 7.58172 4 12C4 16.4183 7.58172 20 12 20ZM13 12H17V14H11V7H13V12Z"></path></svg>
							</span>
							<span class="wpss-detail-label"><?php esc_html_e( 'Delivery Time', 'wp-sell-services' ); ?></span>
							<span class="wpss-detail-value">
								<?php
								$days = (int) $delivery_days_value;
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
							<span class="wpss-detail-icon wpss-icon-revision">
								<svg width="18" height="18" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M5.46257 4.43262C7.21556 2.91688 9.5007 2 12 2C17.5228 2 22 6.47715 22 12C22 14.1361 21.3302 16.1158 20.1892 17.7406L17 12H20C20 7.58172 16.4183 4 12 4C9.84982 4 7.89777 4.84827 6.46023 6.22842L5.46257 4.43262ZM18.5374 19.5674C16.7844 21.0831 14.4993 22 12 22C6.47715 22 2 17.5228 2 12C2 9.86386 2.66979 7.88416 3.8108 6.25944L7 12H4C4 16.4183 7.58172 20 12 20C14.1502 20 16.1022 19.1517 17.5398 17.7716L18.5374 19.5674Z"></path></svg>
							</span>
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
							<?php
							// Features saved as plain strings are always included.
							// Features saved as arrays use the 'included' key.
							$is_included = is_string( $feature ) || ! empty( $feature['included'] );
							?>
							<li class="<?php echo $is_included ? 'included' : 'not-included'; ?>">
								<span class="wpss-feature-icon"></span>
								<?php echo esc_html( is_string( $feature ) ? $feature : ( $feature['text'] ?? '' ) ); ?>
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
