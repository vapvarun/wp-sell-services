<?php
/**
 * Buyer Request Archive View Controller
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

use WPSellServices\Services\BuyerRequestService;

/**
 * Handles the buyer request archive page display.
 *
 * @since 1.0.0
 */
class BuyerRequestArchiveView {

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
		add_action( 'wpss_request_archive_header', array( $this, 'render_header' ), 10 );
		add_action( 'wpss_request_archive_header', array( $this, 'render_filters_bar' ), 20 );

		// Before loop.
		add_action( 'wpss_before_request_loop', array( $this, 'render_results_info' ), 10 );

		// After loop.
		add_action( 'wpss_after_request_loop', array( $this, 'render_pagination' ), 10 );

		// Sidebar.
		add_action( 'wpss_request_archive_sidebar', array( $this, 'render_sidebar' ), 10 );

		// Enqueue assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Modify query.
		add_action( 'pre_get_posts', array( $this, 'modify_archive_query' ) );
	}

	/**
	 * Enqueue archive-specific assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! is_post_type_archive( 'wpss_request' ) && ! is_singular( 'wpss_request' ) ) {
			return;
		}

		wp_enqueue_style(
			'wpss-request',
			\WPSS_PLUGIN_URL . 'assets/css/buyer-request.css',
			array( 'wpss-frontend' ),
			\WPSS_VERSION
		);
	}

	/**
	 * Modify archive query for buyer requests.
	 *
	 * @param \WP_Query $query The query object.
	 * @return void
	 */
	public function modify_archive_query( \WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! $query->is_post_type_archive( 'wpss_request' ) ) {
			return;
		}

		// Only show open requests.
		$meta_query = array(
			'relation' => 'AND',
			array(
				'key'     => '_wpss_status',
				'value'   => BuyerRequestService::STATUS_OPEN,
				'compare' => '=',
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => '_wpss_expires_at',
					'value'   => current_time( 'mysql' ),
					'compare' => '>',
					'type'    => 'DATETIME',
				),
				array(
					'key'     => '_wpss_expires_at',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		// Filter by category.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['category'] ) && absint( $_GET['category'] ) > 0 ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => 'wpss_service_category',
						'field'    => 'term_id',
						// phpcs:ignore WordPress.Security.NonceVerification.Recommended
						'terms'    => array( absint( $_GET['category'] ) ),
					),
				)
			);
		}

		// Filter by budget range.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['min_budget'] ) && $_GET['min_budget'] > 0 ) {
			$meta_query[] = array(
				'key'     => '_wpss_budget_min',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'value'   => floatval( $_GET['min_budget'] ),
				'compare' => '>=',
				'type'    => 'DECIMAL',
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['max_budget'] ) && $_GET['max_budget'] > 0 ) {
			$meta_query[] = array(
				'key'     => '_wpss_budget_max',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'value'   => floatval( $_GET['max_budget'] ),
				'compare' => '<=',
				'type'    => 'DECIMAL',
			);
		}

		$query->set( 'meta_query', $meta_query );

		// Handle sorting.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sort = isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : 'newest';

		switch ( $sort ) {
			case 'budget_high':
				$query->set( 'meta_key', '_wpss_budget_max' );
				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'order', 'DESC' );
				break;

			case 'budget_low':
				$query->set( 'meta_key', '_wpss_budget_min' );
				$query->set( 'orderby', 'meta_value_num' );
				$query->set( 'order', 'ASC' );
				break;

			case 'oldest':
				$query->set( 'orderby', 'date' );
				$query->set( 'order', 'ASC' );
				break;

			case 'newest':
			default:
				$query->set( 'orderby', 'date' );
				$query->set( 'order', 'DESC' );
				break;
		}
	}

	/**
	 * Render archive header.
	 *
	 * @return void
	 */
	public function render_header(): void {
		$platform_name = wpss_get_platform_name();
		?>
		<header class="wpss-request-archive-header">
			<h1 class="wpss-archive-title"><?php esc_html_e( 'Buyer Requests', 'wp-sell-services' ); ?></h1>
			<p class="wpss-archive-description">
				<?php
				printf(
					/* translators: %s: platform name */
					esc_html__( 'Browse buyer job postings on %s and submit proposals for projects that match your skills.', 'wp-sell-services' ),
					esc_html( $platform_name )
				);
				?>
			</p>
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
		$current_sort = isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : 'newest';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_category = isset( $_GET['category'] ) ? absint( $_GET['category'] ) : 0;
		?>
		<div class="wpss-filters-bar">
			<button type="button" class="wpss-btn wpss-btn-outline wpss-filter-toggle" aria-expanded="false" aria-controls="wpss-request-sidebar">
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
					<option value="<?php echo esc_url( add_query_arg( 'sort', 'newest' ) ); ?>" <?php selected( $current_sort, 'newest' ); ?>>
						<?php esc_html_e( 'Sort: Newest', 'wp-sell-services' ); ?>
					</option>
					<option value="<?php echo esc_url( add_query_arg( 'sort', 'oldest' ) ); ?>" <?php selected( $current_sort, 'oldest' ); ?>>
						<?php esc_html_e( 'Sort: Oldest', 'wp-sell-services' ); ?>
					</option>
					<option value="<?php echo esc_url( add_query_arg( 'sort', 'budget_high' ) ); ?>" <?php selected( $current_sort, 'budget_high' ); ?>>
						<?php esc_html_e( 'Sort: Budget High to Low', 'wp-sell-services' ); ?>
					</option>
					<option value="<?php echo esc_url( add_query_arg( 'sort', 'budget_low' ) ); ?>" <?php selected( $current_sort, 'budget_low' ); ?>>
						<?php esc_html_e( 'Sort: Budget Low to High', 'wp-sell-services' ); ?>
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
				/* translators: %s: number of requests */
				esc_html( _n( '%s request found', '%s requests found', $total, 'wp-sell-services' ) ),
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
		<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Request navigation', 'wp-sell-services' ); ?>">
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
		$min_budget = isset( $_GET['min_budget'] ) ? floatval( $_GET['min_budget'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$max_budget = isset( $_GET['max_budget'] ) ? floatval( $_GET['max_budget'] ) : '';
		?>
		<aside class="wpss-request-sidebar" id="wpss-request-sidebar">
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
									<a href="<?php echo esc_url( add_query_arg( 'category', $category->term_id ) ); ?>" class="wpss-category-link">
										<?php echo esc_html( $category->name ); ?>
										<span class="wpss-count">(<?php echo esc_html( $category->count ); ?>)</span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<!-- Budget Range -->
				<div class="wpss-filter-section">
					<h4><?php esc_html_e( 'Budget Range', 'wp-sell-services' ); ?></h4>
					<div class="wpss-price-inputs">
						<input type="number" name="min_budget" placeholder="<?php esc_attr_e( 'Min', 'wp-sell-services' ); ?>"
								value="<?php echo esc_attr( $min_budget ); ?>" min="0" step="1">
						<span class="wpss-price-separator">-</span>
						<input type="number" name="max_budget" placeholder="<?php esc_attr_e( 'Max', 'wp-sell-services' ); ?>"
								value="<?php echo esc_attr( $max_budget ); ?>" min="0" step="1">
					</div>
				</div>

				<div class="wpss-filter-actions">
					<button type="submit" class="wpss-btn wpss-btn-primary wpss-btn-block">
						<?php esc_html_e( 'Apply Filters', 'wp-sell-services' ); ?>
					</button>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'wpss_request' ) ); ?>" class="wpss-btn wpss-btn-outline wpss-btn-block">
						<?php esc_html_e( 'Clear All', 'wp-sell-services' ); ?>
					</a>
				</div>
			</form>
		</aside>
		<?php
	}
}
