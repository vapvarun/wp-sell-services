<?php
/**
 * Dashboard Section: Favorites
 *
 * Shows the services the current buyer has saved via the heart button on
 * service cards / detail pages. Provides a one-click unfavorite action
 * that refreshes the grid without a full page reload.
 *
 * @package WPSellServices\Templates
 * @since   1.1.0
 *
 * @var int $user_id Current user ID.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fires before the favorites dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('favorites').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_before', 'favorites', $user_id );

$favorite_ids = \WPSellServices\Services\FavoritesService::get_ids( $user_id );

// Paginated (20 per page) so a long favourites list doesn't load every row.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination param.
$favorites_page  = isset( $_GET['favorites_page'] ) ? max( 1, absint( $_GET['favorites_page'] ) ) : 1;
$favorites_query = null;
$services        = array();
if ( ! empty( $favorite_ids ) ) {
	$favorites_query = new WP_Query(
		array(
			'post_type'      => 'wpss_service',
			'post_status'    => 'publish',
			'post__in'       => $favorite_ids,
			'orderby'        => 'post__in',
			'posts_per_page' => 20,
			'paged'          => $favorites_page,
		)
	);
	$services        = $favorites_query->posts;
}
$favorites_total = $favorites_query ? (int) $favorites_query->found_posts : 0;
$favorites_pages = $favorites_query ? (int) $favorites_query->max_num_pages : 0;
?>

<div class="wpss-section wpss-section--favorites wpss-card wpss-favorites" data-wpss-favorites>
	<?php if ( empty( $services ) ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon" aria-hidden="true">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon::render() returns hand-built SVG with internally-escaped attributes.
				echo \WPSellServices\Services\Icon::render( 'heart', array( 'class' => 'wpss-icon--lg' ) );
				?>
			</div>
			<h3 class="wpss-empty-state__title"><?php esc_html_e( 'No favorites yet', 'wp-sell-services' ); ?></h3>
			<p class="wpss-empty-state__text">
				<?php esc_html_e( 'Tap the heart on any service to save it here for later.', 'wp-sell-services' ); ?>
			</p>
			<?php
			$services_page_id = get_option( 'wpss_pages', array() )['services_page'] ?? 0;
			$browse_url       = $services_page_id ? get_permalink( $services_page_id ) : home_url( '/' );
			?>
			<a href="<?php echo esc_url( $browse_url ); ?>" class="wpss-btn wpss-btn--primary">
				<?php esc_html_e( 'Browse services', 'wp-sell-services' ); ?>
			</a>
		</div>
	<?php else : ?>
		<p class="wpss-favorites__count">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: number of saved services */
					_n( '%d saved service', '%d saved services', $favorites_total, 'wp-sell-services' ),
					$favorites_total
				)
			);
			?>
		</p>

		<div class="wpss-favorites__grid">
			<?php
			foreach ( $services as $service ) :
				$price_cents = (int) get_post_meta( $service->ID, '_wpss_starting_price', true );
				$currency    = wpss_get_currency();
				$vendor_id   = (int) $service->post_author;
				$vendor      = get_userdata( $vendor_id );
				$thumbnail   = get_the_post_thumbnail_url( $service->ID, 'medium_large' );
				?>
				<article class="wpss-favorites__card" data-service-id="<?php echo esc_attr( (string) $service->ID ); ?>">
					<a href="<?php echo esc_url( get_permalink( $service->ID ) ); ?>" class="wpss-favorites__card-link">
						<div class="wpss-favorites__card-media">
							<?php if ( $thumbnail ) : ?>
								<img src="<?php echo esc_url( $thumbnail ); ?>" alt="<?php echo esc_attr( get_the_title( $service->ID ) ); ?>" loading="lazy">
							<?php else : ?>
								<div class="wpss-favorites__card-media-placeholder" aria-hidden="true"></div>
							<?php endif; ?>
						</div>
						<div class="wpss-favorites__card-body">
							<?php if ( $vendor ) : ?>
								<p class="wpss-favorites__card-vendor"><?php echo esc_html( $vendor->display_name ); ?></p>
							<?php endif; ?>
							<h3 class="wpss-favorites__card-title"><?php echo esc_html( get_the_title( $service->ID ) ); ?></h3>
							<?php if ( $price_cents > 0 ) : ?>
								<p class="wpss-favorites__card-price">
									<?php
									/* translators: %s: starting price with currency */
									echo esc_html(
										sprintf(
											__( 'From %s', 'wp-sell-services' ),
											function_exists( 'wpss_format_price' )
												? wpss_format_price( $price_cents, $currency )
												: number_format_i18n( $price_cents / 100, 2 ) . ' ' . $currency
										)
									);
									?>
								</p>
							<?php endif; ?>
						</div>
					</a>
					<button
						type="button"
						class="wpss-favorites__remove"
						data-service-id="<?php echo esc_attr( (string) $service->ID ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: service title */ __( 'Remove %s from favorites', 'wp-sell-services' ), get_the_title( $service->ID ) ) ); ?>"
					>
						<span aria-hidden="true">×</span>
						<span class="screen-reader-text"><?php esc_html_e( 'Remove from favorites', 'wp-sell-services' ); ?></span>
					</button>
				</article>
			<?php endforeach; ?>
		</div>

		<?php if ( $favorites_pages > 1 ) : ?>
			<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Favorite pages', 'wp-sell-services' ); ?>">
				<?php
				// Paginate relative to the current section URL (see orders.php).
				$favorites_page_url = static function ( int $page ): string {
					return $page > 1 ? add_query_arg( 'favorites_page', $page ) : remove_query_arg( 'favorites_page' );
				};
	?>
				<?php if ( $favorites_page > 1 ) : ?>
					<a href="<?php echo esc_url( $favorites_page_url( $favorites_page - 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--prev">
						<i data-lucide="chevron-left" class="wpss-icon" aria-hidden="true"></i>
						<?php esc_html_e( 'Previous', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
				<span class="wpss-pagination__current">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Page %1$d of %2$d', 'wp-sell-services' ),
						(int) $favorites_page,
						(int) $favorites_pages
					);
					?>
				</span>
				<?php if ( $favorites_page < $favorites_pages ) : ?>
					<a href="<?php echo esc_url( $favorites_page_url( $favorites_page + 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--next">
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
 * Fires after the favorites dashboard section content.
 *
 * @since 1.1.0
 *
 * @param string $section_name Section identifier ('favorites').
 * @param int    $user_id      Current user ID.
 */
do_action( 'wpss_dashboard_section_after', 'favorites', $user_id );
