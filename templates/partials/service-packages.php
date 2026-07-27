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
 * @var array|null                     $wpss_vacation Vendor vacation state
 *                                                    (message/return_date_display)
 *                                                    assembled in SingleServiceView,
 *                                                    or null when not on vacation.
 */

defined( 'ABSPATH' ) || exit;

$service_id = get_the_ID();
$packages   = get_post_meta( $service_id, '_wpss_packages', true ) ?: [];

// Vendor vacation state is resolved in the view (no DB queries in templates).
$wpss_vacation = ( isset( $wpss_vacation ) && is_array( $wpss_vacation ) ) ? $wpss_vacation : null;
$wpss_on_vacation = null !== $wpss_vacation;

// If no packages, show single price (omit description to avoid duplicating "About This Service").
if ( empty( $packages ) ) {
	$price         = (float) get_post_meta( $service_id, '_wpss_starting_price', true );
	$delivery_time = wpss_get_service_delivery_days( $service_id );

	$packages = [
		[
			'name'          => __( 'Standard', 'wp-sell-services' ),
			'description'   => '',
			'price'         => $price,
			'delivery_time' => $delivery_time,
			'revisions'     => wpss_get_service_revisions( $service_id ) ?: 1,
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
						$price_html = wpss_catalog_price_html( (float) ( $package['price'] ?? 0 ), 'package' );

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

						echo wp_kses_post( $price_html );
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
								<i data-lucide="clock" class="wpss-icon" aria-hidden="true"></i>
							</span>
							<span class="wpss-detail-label"><?php esc_html_e( 'Delivery Time', 'wp-sell-services' ); ?></span>
							<span class="wpss-detail-value">
								<?php
								$days = (int) $delivery_days_value;
								printf(
									/* translators: %d: number of days */
									esc_html( _n( '%d Day', '%d Days', $days, 'wp-sell-services' ) ),
									absint( $days )
								);
								?>
							</span>
						</li>
					<?php endif; ?>

					<?php if ( isset( $package['revisions'] ) ) : ?>
						<li>
							<span class="wpss-detail-icon wpss-icon-revision">
								<i data-lucide="refresh-cw" class="wpss-icon" aria-hidden="true"></i>
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
					$vendor_id      = (int) get_post_field( 'post_author', $service_id );
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

						<?php if ( $wpss_on_vacation ) : ?>
							<?php
							// Seller on vacation: render the CTA visually disabled and
							// non-interactive. Crucially this button does NOT carry the
							// `wpss-order-btn` class, so the single-service JS click
							// handler never matches it and the order modal cannot open.
							$wpss_vacation_label_id = 'wpss-vacation-cta-note-' . esc_attr( $index );
							?>
							<button type="button"
									class="wpss-btn wpss-btn-primary wpss-btn-block wpss-order-btn--disabled"
									disabled
									aria-disabled="true"
									aria-describedby="<?php echo esc_attr( $wpss_vacation_label_id ); ?>">
								<?php echo esc_html( $button_text ); ?>
								<span class="wpss-btn-price">(<?php echo esc_html( wpss_format_price( (float) ( $package['price'] ?? 0 ) ) ); ?>)</span>
							</button>
							<p id="<?php echo esc_attr( $wpss_vacation_label_id ); ?>" class="wpss-package-vacation-note">
								<?php
								if ( ! empty( $wpss_vacation['return_date_display'] ) ) {
									printf(
										/* translators: %s: formatted return date */
										esc_html__( 'Seller is on vacation. Orders resume on %s.', 'wp-sell-services' ),
										esc_html( $wpss_vacation['return_date_display'] )
									);
								} else {
									esc_html_e( 'Seller is on vacation and not accepting new orders right now.', 'wp-sell-services' );
								}
								?>
							</p>
						<?php else : ?>
							<button type="button"
									class="wpss-btn wpss-btn-primary wpss-btn-block wpss-order-btn"
									data-service="<?php echo esc_attr( $service_id ); ?>"
									data-package="<?php echo esc_attr( $index ); ?>"
									data-price="<?php echo esc_attr( $package['price'] ?? 0 ); ?>">
								<?php echo esc_html( $button_text ); ?>
								<span class="wpss-btn-price">(<?php echo esc_html( wpss_format_price( (float) ( $package['price'] ?? 0 ) ) ); ?>)</span>
							</button>
						<?php endif; ?>
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

	<?php
	if ( ! $is_own_service ) :
		// Per-service favourite toggle, in the order sidebar next to the CTA
		// (moved here from the main column). Same classes/attributes as every
		// other favourite toggle so frontend.js drives it and the state stays in
		// sync with the archive card and the buyer dashboard.
		$wpss_pkg_logged_in = is_user_logged_in();
		$wpss_pkg_favorited = false;
		if ( $wpss_pkg_logged_in ) {
			$wpss_pkg_favs      = \WPSellServices\Services\FavoritesService::get_ids( get_current_user_id() );
			$wpss_pkg_favorited = is_array( $wpss_pkg_favs ) && in_array( (int) $service_id, array_map( 'intval', $wpss_pkg_favs ), true );
		}
		$wpss_pkg_fav_label = $wpss_pkg_favorited
			? __( 'Saved to favorites', 'wp-sell-services' )
			: __( 'Save to favorites', 'wp-sell-services' );
		?>
		<div class="wpss-package-actions">
			<button
				type="button"
				class="wpss-btn wpss-btn-ghost wpss-btn-block wpss-fav-toggle wpss-fav-toggle--inline wpss-package-fav<?php echo $wpss_pkg_favorited ? ' is-favorited' : ''; ?>"
				data-service-id="<?php echo esc_attr( (string) $service_id ); ?>"
				data-logged-in="<?php echo $wpss_pkg_logged_in ? '1' : '0'; ?>"
				aria-pressed="<?php echo $wpss_pkg_favorited ? 'true' : 'false'; ?>"
			>
				<i data-lucide="heart" class="wpss-icon wpss-icon--sm wpss-fav-toggle__icon" aria-hidden="true"></i>
				<span class="wpss-fav-toggle__label"><?php echo esc_html( $wpss_pkg_fav_label ); ?></span>
			</button>
			<div class="wpss-contact-seller">
				<a href="#" class="wpss-contact-link" data-vendor="<?php echo esc_attr( $vendor_id ); ?>">
					<?php esc_html_e( 'Contact Seller', 'wp-sell-services' ); ?>
				</a>
			</div>
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
