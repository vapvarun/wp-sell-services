<?php
/**
 * Service Archive View Controller
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

/**
 * Handles the service archive page display.
 *
 * @since 1.0.0
 */
class ServiceArchiveView {

	/**
	 * Initialize the archive view.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_hooks();
	}

	/**
	 * Register template hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Header hooks.
		add_action( 'wpss_service_archive_header', array( $this, 'render_header' ), 10 );
		add_action( 'wpss_service_archive_header', array( $this, 'render_filters_bar' ), 20 );

		// Before loop.
		add_action( 'wpss_before_service_loop', array( $this, 'render_results_info' ), 10 );

		// After loop.
		add_action( 'wpss_after_service_loop', array( $this, 'render_pagination' ), 10 );

		// Sidebar.
		add_action( 'wpss_service_archive_sidebar', array( $this, 'render_sidebar' ), 10 );

		// Enqueue assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue archive-specific assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! is_post_type_archive( 'wpss_service' ) && ! is_tax( 'wpss_service_category' ) && ! is_tax( 'wpss_service_tag' ) ) {
			return;
		}

		wp_enqueue_style(
			'wpss-archive',
			\WPSS_PLUGIN_URL . 'assets/css/archive-service.css',
			array( 'wpss-frontend' ),
			\WPSS_VERSION
		);
	}

	/**
	 * Render archive header.
	 *
	 * @return void
	 */
	public function render_header(): void {
		$title       = $this->get_archive_title();
		$description = $this->get_archive_description();
		?>
		<header class="wpss-archive-header">
			<h1 class="wpss-archive-title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( $description ) : ?>
				<p class="wpss-archive-description"><?php echo esc_html( $description ); ?></p>
			<?php endif; ?>
		</header>
		<?php
	}

	/**
	 * Render filters bar.
	 *
	 * @return void
	 */
	public function render_filters_bar(): void {
		$categories = get_terms(
			array(
				'taxonomy'   => 'wpss_service_category',
				'hide_empty' => true,
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_sort = isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : 'default';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_category = isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0;
		?>
		<div class="wpss-filters-bar">
			<button type="button" class="wpss-btn wpss-btn-outline wpss-filter-toggle" aria-expanded="false" aria-controls="wpss-sidebar">
				<span class="wpss-icon-filter"></span>
				<?php esc_html_e( 'Filters', 'wp-sell-services' ); ?>
			</button>

			<div class="wpss-filters-bar-controls">
				<?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
					<select class="wpss-category-filter" onchange="location.href=this.value">
						<option value="<?php echo esc_url( remove_query_arg( 'category' ) ); ?>">
							<?php esc_html_e( 'All Categories', 'wp-sell-services' ); ?>
						</option>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_url( add_query_arg( 'category', $category->term_id ) ); ?>"
								<?php selected( $current_category, $category->term_id ); ?>>
								<?php echo esc_html( $category->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>

				<select class="wpss-sort-filter" onchange="location.href=this.value">
					<option value="<?php echo esc_url( remove_query_arg( 'sort' ) ); ?>" <?php selected( $current_sort, 'default' ); ?>>
						<?php esc_html_e( 'Sort: Recommended', 'wp-sell-services' ); ?>
					</option>
					<option value="<?php echo esc_url( add_query_arg( 'sort', 'newest' ) ); ?>" <?php selected( $current_sort, 'newest' ); ?>>
						<?php esc_html_e( 'Sort: Newest', 'wp-sell-services' ); ?>
					</option>
					<option value="<?php echo esc_url( add_query_arg( 'sort', 'rating' ) ); ?>" <?php selected( $current_sort, 'rating' ); ?>>
						<?php esc_html_e( 'Sort: Best Rated', 'wp-sell-services' ); ?>
					</option>
					<option value="<?php echo esc_url( add_query_arg( 'sort', 'price_low' ) ); ?>" <?php selected( $current_sort, 'price_low' ); ?>>
						<?php esc_html_e( 'Sort: Price Low to High', 'wp-sell-services' ); ?>
					</option>
					<option value="<?php echo esc_url( add_query_arg( 'sort', 'price_high' ) ); ?>" <?php selected( $current_sort, 'price_high' ); ?>>
						<?php esc_html_e( 'Sort: Price High to Low', 'wp-sell-services' ); ?>
					</option>
				</select>
			</div>
		</div>
		<?php
	}

	/**
	 * Render results info.
	 *
	 * @return void
	 */
	public function render_results_info(): void {
		global $wp_query;
		$total = $wp_query->found_posts;
		?>
		<div class="wpss-results-info">
			<?php
			printf(
				/* translators: %s: number of services */
				esc_html( _n( '%s service found', '%s services found', $total, 'wp-sell-services' ) ),
				esc_html( number_format_i18n( $total ) )
			);
			?>
		</div>
		<?php
	}

	/**
	 * Render pagination.
	 *
	 * @return void
	 */
	public function render_pagination(): void {
		global $wp_query;

		if ( $wp_query->max_num_pages <= 1 ) {
			return;
		}

		$args = array(
			'prev_text' => '&larr; ' . __( 'Previous', 'wp-sell-services' ),
			'next_text' => __( 'Next', 'wp-sell-services' ) . ' &rarr;',
			'type'      => 'list',
		);
		?>
		<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Service navigation', 'wp-sell-services' ); ?>">
			<?php echo wp_kses_post( paginate_links( $args ) ); ?>
		</nav>
		<?php
	}

	/**
	 * Render sidebar with filters.
	 *
	 * @return void
	 */
	public function render_sidebar(): void {
		$categories = get_terms(
			array(
				'taxonomy'   => 'wpss_service_category',
				'hide_empty' => true,
				'parent'     => 0,
			)
		);

		// Get current filter values.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$min_price = isset( $_GET['min_price'] ) ? floatval( $_GET['min_price'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$max_price = isset( $_GET['max_price'] ) ? floatval( $_GET['max_price'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$min_rating = isset( $_GET['rating'] ) ? absint( $_GET['rating'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$delivery_time = isset( $_GET['delivery'] ) ? sanitize_text_field( wp_unslash( $_GET['delivery'] ) ) : '';
		?>
		<aside class="wpss-archive-sidebar" id="wpss-sidebar">
			<div class="wpss-sidebar-header">
				<h3><?php esc_html_e( 'Filters', 'wp-sell-services' ); ?></h3>
				<button type="button" class="wpss-sidebar-close wpss-btn-icon" aria-label="<?php esc_attr_e( 'Close filters', 'wp-sell-services' ); ?>">
					&times;
				</button>
			</div>

			<form class="wpss-filter-form" method="get">
				<!-- Categories -->
				<?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
					<div class="wpss-filter-section">
						<h4><?php esc_html_e( 'Category', 'wp-sell-services' ); ?></h4>
						<ul class="wpss-category-list">
							<?php foreach ( $categories as $category ) : ?>
								<li>
									<a href="<?php echo esc_url( get_term_link( $category ) ); ?>" class="wpss-category-link">
										<?php echo esc_html( $category->name ); ?>
										<span class="wpss-count">(<?php echo esc_html( $category->count ); ?>)</span>
									</a>
									<?php
									$children = get_terms(
										array(
											'taxonomy'   => 'wpss_service_category',
											'hide_empty' => true,
											'parent'     => $category->term_id,
										)
									);
									if ( ! is_wp_error( $children ) && ! empty( $children ) ) :
										?>
										<ul class="wpss-subcategory-list">
											<?php foreach ( $children as $child ) : ?>
												<li>
													<a href="<?php echo esc_url( get_term_link( $child ) ); ?>">
														<?php echo esc_html( $child->name ); ?>
														<span class="wpss-count">(<?php echo esc_html( $child->count ); ?>)</span>
													</a>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<!-- Price Range -->
				<div class="wpss-filter-section">
					<h4><?php esc_html_e( 'Price Range', 'wp-sell-services' ); ?></h4>
					<div class="wpss-price-inputs">
						<input type="number" name="min_price" placeholder="<?php esc_attr_e( 'Min', 'wp-sell-services' ); ?>"
								value="<?php echo esc_attr( $min_price ); ?>" min="0" step="1">
						<span class="wpss-price-separator">-</span>
						<input type="number" name="max_price" placeholder="<?php esc_attr_e( 'Max', 'wp-sell-services' ); ?>"
								value="<?php echo esc_attr( $max_price ); ?>" min="0" step="1">
					</div>
				</div>

				<!-- Seller Rating -->
				<div class="wpss-filter-section">
					<h4><?php esc_html_e( 'Seller Rating', 'wp-sell-services' ); ?></h4>
					<div class="wpss-rating-options">
						<?php for ( $i = 4; $i >= 1; $i-- ) : ?>
							<label class="wpss-rating-option">
								<input type="radio" name="rating" value="<?php echo esc_attr( $i ); ?>"
									<?php checked( $min_rating, $i ); ?>>
								<span class="wpss-stars">
									<?php
									for ( $s = 1; $s <= 5; $s++ ) {
										echo $s <= $i ? '<span class="wpss-star filled">&#9733;</span>' : '<span class="wpss-star">&#9733;</span>';
									}
									?>
								</span>
								<span class="wpss-label"><?php esc_html_e( '& Up', 'wp-sell-services' ); ?></span>
							</label>
						<?php endfor; ?>
					</div>
				</div>

				<!-- Delivery Time -->
				<div class="wpss-filter-section">
					<h4><?php esc_html_e( 'Delivery Time', 'wp-sell-services' ); ?></h4>
					<div class="wpss-delivery-options">
						<label class="wpss-checkbox-option">
							<input type="radio" name="delivery" value="1" <?php checked( $delivery_time, '1' ); ?>>
							<?php esc_html_e( 'Up to 24 hours', 'wp-sell-services' ); ?>
						</label>
						<label class="wpss-checkbox-option">
							<input type="radio" name="delivery" value="3" <?php checked( $delivery_time, '3' ); ?>>
							<?php esc_html_e( 'Up to 3 days', 'wp-sell-services' ); ?>
						</label>
						<label class="wpss-checkbox-option">
							<input type="radio" name="delivery" value="7" <?php checked( $delivery_time, '7' ); ?>>
							<?php esc_html_e( 'Up to 7 days', 'wp-sell-services' ); ?>
						</label>
						<label class="wpss-checkbox-option">
							<input type="radio" name="delivery" value="" <?php checked( $delivery_time, '' ); ?>>
							<?php esc_html_e( 'Any', 'wp-sell-services' ); ?>
						</label>
					</div>
				</div>

				<div class="wpss-filter-actions">
					<button type="submit" class="wpss-btn wpss-btn-primary wpss-btn-block">
						<?php esc_html_e( 'Apply Filters', 'wp-sell-services' ); ?>
					</button>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'wpss_service' ) ); ?>" class="wpss-btn wpss-btn-outline wpss-btn-block">
						<?php esc_html_e( 'Clear All', 'wp-sell-services' ); ?>
					</a>
				</div>
			</form>
		</aside>
		<?php
	}

	/**
	 * Get archive title.
	 *
	 * @return string
	 */
	private function get_archive_title(): string {
		if ( is_tax( 'wpss_service_category' ) ) {
			return single_term_title( '', false );
		}

		if ( is_tax( 'wpss_service_tag' ) ) {
			/* translators: %s: tag name */
			return sprintf( __( 'Services tagged: %s', 'wp-sell-services' ), single_term_title( '', false ) );
		}

		if ( is_search() ) {
			/* translators: %s: search query */
			return sprintf( __( 'Search results for: %s', 'wp-sell-services' ), get_search_query() );
		}

		return __( 'All Services', 'wp-sell-services' );
	}

	/**
	 * Get archive description.
	 *
	 * @return string
	 */
	private function get_archive_description(): string {
		if ( is_tax() ) {
			return term_description();
		}

		return '';
	}
}
