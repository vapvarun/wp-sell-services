<?php
/**
 * Service Archive View Controller
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Frontend;

defined( 'ABSPATH' ) || exit;
use WPSellServices\Services\ModerationService;

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

		// Modify archive query to apply filters.
		add_action( 'pre_get_posts', array( $this, 'modify_archive_query' ) );
	}

	/**
	 * Enqueue archive-specific assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! wpss_is_page( 'services_page' ) && ! is_post_type_archive( 'wpss_service' ) && ! is_tax( 'wpss_service_category' ) && ! is_tax( 'wpss_service_tag' ) ) {
			return;
		}

		wp_enqueue_style(
			'wpss-archive',
			\WPSS_PLUGIN_URL . 'assets/css/archive-service.css',
			array( 'wpss-frontend' ),
			\WPSS_VERSION
		);
		wp_style_add_data( 'wpss-archive', 'rtl', 'replace' );
	}

	/**
	 * Render archive header.
	 *
	 * @return void
	 */
	public function render_header(): void {
		$title         = $this->get_archive_title();
		$description   = $this->get_archive_description();
		$platform_name = wpss_get_platform_name();
		?>
		<header class="wpss-archive-header">
			<h1 class="wpss-archive-title"><?php echo esc_html( $title ); ?></h1>
			<?php if ( $description ) : ?>
				<p class="wpss-archive-description"><?php echo esc_html( $description ); ?></p>
			<?php elseif ( $platform_name && ( is_post_type_archive( 'wpss_service' ) || wpss_is_page( 'services_page' ) ) ) : ?>
				<p class="wpss-archive-description">
					<?php
					printf(
						/* translators: %s: platform name */
						esc_html__( 'Browse professional services on %s', 'wp-sell-services' ),
						esc_html( $platform_name )
					);
					?>
				</p>
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

		// Determine the current category from either the query param or the taxonomy archive term.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_category = isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0;
		if ( ! $current_category && is_tax( 'wpss_service_category' ) ) {
			$current_term     = get_queried_object();
			$current_category = $current_term instanceof \WP_Term ? $current_term->term_id : 0;
		}

		// Use the services page (or CPT archive) as the base URL for category filter links.
		// This prevents broken URLs when navigating categories from a taxonomy archive page.
		$base_url = wpss_get_page_url( 'services_page' ) ?: get_post_type_archive_link( 'wpss_service' );

		// Preserve current sort param when switching categories.
		$base_args = array();
		if ( 'default' !== $current_sort ) {
			$base_args['sort'] = $current_sort;
		}
		?>
		<div class="wpss-filters-bar">
			<button type="button" class="wpss-btn wpss-btn-outline wpss-filter-toggle" aria-expanded="false" aria-controls="wpss-sidebar">
				<span class="wpss-icon-filter"></span>
				<?php esc_html_e( 'Filters', 'wp-sell-services' ); ?>
			</button>

			<div class="wpss-filters-bar-controls">
				<?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
					<select class="wpss-category-filter wpss-url-select">
						<option value="<?php echo esc_url( add_query_arg( $base_args, $base_url ) ); ?>">
							<?php esc_html_e( 'All Categories', 'wp-sell-services' ); ?>
						</option>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_url( add_query_arg( array_merge( $base_args, array( 'category' => $category->term_id ) ), $base_url ) ); ?>"
								<?php selected( $current_category, $category->term_id ); ?>>
								<?php echo esc_html( $category->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>

				<select class="wpss-sort-filter wpss-url-select">
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

		// Preserve current filter parameters in pagination links.
		$filter_params = array( 'category', 'min_price', 'max_price', 'rating', 'delivery', 'sort' );
		$add_args      = array();

		foreach ( $filter_params as $param ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET[ $param ] ) && '' !== $_GET[ $param ] ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$add_args[ $param ] = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
			}
		}

		$args = array(
			'prev_text' => '&larr; ' . __( 'Previous', 'wp-sell-services' ),
			'next_text' => __( 'Next', 'wp-sell-services' ) . ' &rarr;',
			'type'      => 'list',
			'add_args'  => $add_args,
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
		// Fetch all categories in one query to avoid N+1.
		$all_categories = get_terms(
			array(
				'taxonomy'   => 'wpss_service_category',
				'hide_empty' => true,
			)
		);

		// Group by parent for efficient lookup.
		$categories         = array();
		$children_by_parent = array();
		if ( ! is_wp_error( $all_categories ) ) {
			foreach ( $all_categories as $term ) {
				if ( 0 === $term->parent ) {
					$categories[] = $term;
				} else {
					$children_by_parent[ $term->parent ][] = $term;
				}
			}
		}

		// Get current filter values.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$min_price = isset( $_GET['min_price'] ) && '' !== $_GET['min_price'] ? floatval( $_GET['min_price'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$max_price = isset( $_GET['max_price'] ) && '' !== $_GET['max_price'] ? floatval( $_GET['max_price'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$min_rating = isset( $_GET['rating'] ) ? absint( $_GET['rating'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$delivery_time = isset( $_GET['delivery'] ) ? sanitize_text_field( wp_unslash( $_GET['delivery'] ) ) : '';

		// Determine the active category for sidebar highlighting.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_category_id = isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0;
		if ( ! $active_category_id && is_tax( 'wpss_service_category' ) ) {
			$active_term        = get_queried_object();
			$active_category_id = $active_term instanceof \WP_Term ? $active_term->term_id : 0;
		}
		?>
		<aside class="wpss-archive-sidebar" id="wpss-sidebar">
			<div class="wpss-sidebar-header">
				<h3><?php esc_html_e( 'Filters', 'wp-sell-services' ); ?></h3>
				<button type="button" class="wpss-sidebar-close wpss-btn-icon" aria-label="<?php esc_attr_e( 'Close filters', 'wp-sell-services' ); ?>">
					&times;
				</button>
			</div>

			<form class="wpss-filter-form" method="get">
				<?php
				// Preserve category and sort params as hidden fields so the sidebar
				// form submission does not lose them (they are not form inputs).
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( isset( $_GET['category'] ) && absint( $_GET['category'] ) > 0 ) :
					?>
					<input type="hidden" name="category" value="<?php echo esc_attr( absint( $_GET['category'] ) ); ?>">
				<?php endif; ?>
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( isset( $_GET['sort'] ) && '' !== $_GET['sort'] ) :
					?>
					<input type="hidden" name="sort" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['sort'] ) ) ); ?>">
				<?php endif; ?>

				<!-- Categories -->
				<?php if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) : ?>
					<div class="wpss-filter-section">
						<h4><?php esc_html_e( 'Category', 'wp-sell-services' ); ?></h4>
						<ul class="wpss-category-list">
							<?php foreach ( $categories as $category ) : ?>
								<?php
								$is_active = ( $active_category_id === $category->term_id );
								$children  = $children_by_parent[ $category->term_id ] ?? array();
								// Also mark parent as active if a child category is active.
								if ( ! $is_active && ! empty( $children ) ) {
									foreach ( $children as $child ) {
										if ( $active_category_id === $child->term_id ) {
											$is_active = true;
											break;
										}
									}
								}
								?>
								<li>
									<a href="<?php echo esc_url( get_term_link( $category ) ); ?>"
										class="wpss-category-link<?php echo $is_active ? ' is-active' : ''; ?>"
										<?php echo $is_active ? ' aria-current="true"' : ''; ?>>
										<?php echo esc_html( $category->name ); ?>
										<span class="wpss-count">(<?php echo esc_html( $category->count ); ?>)</span>
									</a>
									<?php if ( ! empty( $children ) ) : ?>
										<ul class="wpss-subcategory-list">
											<?php foreach ( $children as $child ) : ?>
												<li>
													<a href="<?php echo esc_url( get_term_link( $child ) ); ?>"
														class="<?php echo $active_category_id === $child->term_id ? 'is-active' : ''; ?>"
														<?php echo $active_category_id === $child->term_id ? ' aria-current="true"' : ''; ?>>
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
					<?php
					$clear_url = wpss_get_page_url( 'services_page' ) ?: get_post_type_archive_link( 'wpss_service' );
					?>
					<a href="<?php echo esc_url( $clear_url ); ?>" class="wpss-btn wpss-btn-outline wpss-btn-block">
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

		// Show vendor name when filtering by vendor.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['vendor'] ) && absint( $_GET['vendor'] ) > 0 ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$vendor = get_userdata( absint( $_GET['vendor'] ) );
			if ( $vendor ) {
				/* translators: %s: vendor name */
				return sprintf( __( 'Services by %s', 'wp-sell-services' ), $vendor->display_name );
			}
		}

		// Use the mapped services page title if available.
		if ( wpss_is_page( 'services_page' ) ) {
			$page_id = wpss_get_page_id( 'services_page' );
			if ( $page_id ) {
				$page_title = get_the_title( $page_id );
				if ( $page_title ) {
					return $page_title;
				}
			}
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
			// term_description() returns HTML-wrapped content; strip tags
			// since render_header() wraps it in its own <p>.
			return wp_strip_all_tags( term_description() );
		}

		return '';
	}

	/**
	 * Modify the archive query to apply filters.
	 *
	 * @param \WP_Query $query The WP_Query instance.
	 * @return void
	 */
	public function modify_archive_query( \WP_Query $query ): void {
		// Only modify frontend main query for service archives.
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$is_services_page = wpss_is_page( 'services_page' );

		if ( ! $is_services_page && ! $query->is_post_type_archive( 'wpss_service' ) && ! $query->is_tax( 'wpss_service_category' ) && ! $query->is_tax( 'wpss_service_tag' ) ) {
			return;
		}

		// Convert the mapped services page query to fetch services.
		if ( $is_services_page ) {
			$query->set( 'post_type', 'wpss_service' );
			$query->set( 'page_id', '' );
			$query->set( 'pagename', '' );
			$query->set( 'posts_per_page', apply_filters( 'wpss_services_per_page', 12 ) );

			// Reset singular flags so WP_Query treats this as an archive query.
			// Without this, WordPress singular post status logic can allow draft/pending
			// posts to leak through for users with edit capabilities.
			$query->is_singular = false;
			$query->is_page     = false;

		}

		// Ensure only published services are shown (prevents rejected/draft services from leaking through).
		$query->set( 'post_status', 'publish' );

		// Exclude services from vendors who are on vacation mode.
		global $wpdb;
		$profiles_table = $wpdb->prefix . 'wpss_vendor_profiles';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$vacation_vendors = $wpdb->get_col(
			"SELECT user_id FROM {$profiles_table} WHERE vacation_mode = 1"
		);
		if ( ! empty( $vacation_vendors ) ) {
			$existing_excludes = $query->get( 'author__not_in' ) ?: array();
			$query->set( 'author__not_in', array_merge( $existing_excludes, array_map( 'intval', $vacation_vendors ) ) );
		}

		// Always filter out rejected/pending services regardless of moderation setting.
		// Services without moderation meta (legacy) are allowed through.
		$meta_query   = $query->get( 'meta_query' ) ?: array();
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => '_wpss_moderation_status',
				'value'   => 'approved',
				'compare' => '=',
			),
			array(
				'key'     => '_wpss_moderation_status',
				'compare' => 'NOT EXISTS',
			),
		);
		$query->set( 'meta_query', $meta_query );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended

		// Vendor filter (from "View all services" link on vendor profile).
		if ( isset( $_GET['vendor'] ) && absint( $_GET['vendor'] ) > 0 ) {
			$query->set( 'author', absint( $_GET['vendor'] ) );
		}

		// Category filter (dropdown in filters bar).
		if ( isset( $_GET['category'] ) && absint( $_GET['category'] ) > 0 ) {
			$tax_query   = $query->get( 'tax_query' ) ?: array();
			$tax_query[] = array(
				'taxonomy' => 'wpss_service_category',
				'field'    => 'term_id',
				'terms'    => array( absint( $_GET['category'] ) ),
			);
			$query->set( 'tax_query', $tax_query );
		}

		// Price range filters.
		$meta_query = $query->get( 'meta_query' ) ?: array();

		if ( isset( $_GET['min_price'] ) && '' !== $_GET['min_price'] ) {
			$meta_query[] = array(
				'key'     => '_wpss_starting_price',
				'value'   => floatval( $_GET['min_price'] ),
				'compare' => '>=',
				'type'    => 'DECIMAL',
			);
		}

		if ( isset( $_GET['max_price'] ) && '' !== $_GET['max_price'] ) {
			$meta_query[] = array(
				'key'     => '_wpss_starting_price',
				'value'   => floatval( $_GET['max_price'] ),
				'compare' => '<=',
				'type'    => 'DECIMAL',
			);
		}

		// Rating filter.
		if ( isset( $_GET['rating'] ) && absint( $_GET['rating'] ) > 0 ) {
			$meta_query[] = array(
				'key'     => '_wpss_rating_average',
				'value'   => absint( $_GET['rating'] ),
				'compare' => '>=',
				'type'    => 'DECIMAL',
			);
		}

		// Delivery time filter.
		// Uses OR with NOT EXISTS so services without the flat meta key
		// (created before the sync_delivery_days_meta hook was added) are
		// not silently excluded from results.
		if ( isset( $_GET['delivery'] ) && absint( $_GET['delivery'] ) > 0 ) {
			$delivery_days = absint( $_GET['delivery'] );

			// First, backfill any services missing the flat meta key.
			// This is a lightweight query that only runs when the filter is active.
			$this->backfill_delivery_days_meta();

			$meta_query[] = array(
				'key'     => '_wpss_delivery_days',
				'value'   => $delivery_days,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}

		if ( ! empty( $meta_query ) ) {
			$meta_query['relation'] = 'AND';
			$query->set( 'meta_query', $meta_query );
		}

		// Sort options.
		if ( isset( $_GET['sort'] ) ) {
			$sort = sanitize_text_field( wp_unslash( $_GET['sort'] ) );

			switch ( $sort ) {
				case 'newest':
					$query->set( 'orderby', 'date' );
					$query->set( 'order', 'DESC' );
					break;

				case 'rating':
					$query->set( 'orderby', 'meta_value_num' );
					$query->set( 'meta_key', '_wpss_rating_average' );
					$query->set( 'order', 'DESC' );
					break;

				case 'price_low':
					$query->set( 'orderby', 'meta_value_num' );
					$query->set( 'meta_key', '_wpss_starting_price' );
					$query->set( 'order', 'ASC' );
					break;

				case 'price_high':
					$query->set( 'orderby', 'meta_value_num' );
					$query->set( 'meta_key', '_wpss_starting_price' );
					$query->set( 'order', 'DESC' );
					break;
			}
		}

		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Backfill _wpss_delivery_days meta for services missing it.
	 *
	 * Extracts the delivery_days value from the first package in _wpss_packages
	 * and stores it as a flat meta key so the delivery time filter can query it.
	 * Only processes services that don't already have the meta set.
	 *
	 * @return void
	 */
	private function backfill_delivery_days_meta(): void {
		// Only run once per request.
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		global $wpdb;

		// Find published services that have packages but no _wpss_delivery_days meta.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$services = $wpdb->get_results(
			"SELECT p.ID, pm.meta_value AS packages
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wpss_packages'
			LEFT JOIN {$wpdb->postmeta} dd ON dd.post_id = p.ID AND dd.meta_key = '_wpss_delivery_days'
			WHERE p.post_type = 'wpss_service'
			AND p.post_status = 'publish'
			AND dd.meta_id IS NULL
			LIMIT 100"
		);

		foreach ( $services as $service ) {
			$packages = maybe_unserialize( $service->packages );

			if ( empty( $packages ) || ! is_array( $packages ) ) {
				continue;
			}

			$first         = reset( $packages );
			$delivery_days = (int) ( $first['delivery_days'] ?? $first['delivery_time'] ?? 0 );

			if ( $delivery_days > 0 ) {
				update_post_meta( (int) $service->ID, '_wpss_delivery_days', $delivery_days );
			}
		}
	}
}
