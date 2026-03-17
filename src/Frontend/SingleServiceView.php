<?php
/**
 * Single Service View Controller
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

use WPSellServices\Models\Service;

/**
 * Handles the single service page display.
 *
 * @since 1.0.0
 */
class SingleServiceView {

	/**
	 * Service model.
	 *
	 * @var Service|null
	 */
	private ?Service $service = null;

	/**
	 * Initialize the single service view.
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
		add_action( 'wpss_single_service_header', array( $this, 'render_breadcrumb' ), 5 );
		add_action( 'wpss_single_service_header', array( $this, 'render_title' ), 10 );
		add_action( 'wpss_single_service_header', array( $this, 'render_meta' ), 15 );

		// Gallery.
		add_action( 'wpss_single_service_gallery', array( $this, 'render_gallery' ), 10 );

		// Content.
		add_action( 'wpss_single_service_content', array( $this, 'render_description' ), 10 );
		add_action( 'wpss_single_service_content', array( $this, 'render_about_vendor' ), 20 );

		// FAQs.
		add_action( 'wpss_single_service_faqs', array( $this, 'render_faqs' ), 10 );

		// Reviews.
		add_action( 'wpss_single_service_reviews', array( $this, 'render_reviews' ), 10 );

		// Sidebar.
		add_action( 'wpss_single_service_sidebar', array( $this, 'render_packages' ), 10 );
		add_action( 'wpss_single_service_sidebar', array( $this, 'render_vendor_card' ), 20 );

		// Related services.
		add_action( 'wpss_single_service_related', array( $this, 'render_related_services' ), 10 );

		// Footer.
		add_action( 'wpss_after_single_service', array( $this, 'render_order_modal' ), 10 );
		add_action( 'wpss_after_single_service', array( $this, 'render_contact_modal' ), 20 );

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// Track views.
		add_action( 'wp_head', array( $this, 'track_view' ) );
	}

	/**
	 * Enqueue assets for single service page.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		if ( ! is_singular( 'wpss_service' ) ) {
			return;
		}

		// Single service CSS.
		wp_enqueue_style(
			'wpss-single-service',
			\WPSS_PLUGIN_URL . 'assets/css/single-service.css',
			array( 'wpss-frontend' ),
			\WPSS_VERSION
		);

		// Single service JS.
		wp_enqueue_script(
			'wpss-single-service',
			\WPSS_PLUGIN_URL . 'assets/js/single-service.js',
			array( 'jquery', 'wpss-frontend' ),
			\WPSS_VERSION,
			true
		);

		// Get checkout URL from settings.
		$checkout_url = wpss_get_page_url( 'checkout' ) ?: home_url( '/checkout/' );
		$cart_url     = $checkout_url;

		wp_localize_script(
			'wpss-single-service',
			'wpssService',
			array(
				'serviceId'   => get_the_ID(),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'wpss_service_nonce' ),
				'checkoutUrl' => $checkout_url,
				'cartUrl'     => $cart_url,
				'i18n'        => array(
					'addingToCart' => __( 'Adding to cart...', 'wp-sell-services' ),
					'added'        => __( 'Added to cart!', 'wp-sell-services' ),
					'viewCart'     => __( 'View Cart', 'wp-sell-services' ),
					'checkout'     => __( 'Checkout', 'wp-sell-services' ),
					'error'        => __( 'Could not add to cart. Please try again.', 'wp-sell-services' ),
					'selectExtra'  => __( 'Select extras', 'wp-sell-services' ),
					'total'        => __( 'Total', 'wp-sell-services' ),
				),
			)
		);

		// Note: Lightbox functionality uses built-in fallback in single-service.js.
		// No external library required.
	}

	/**
	 * Track service view.
	 *
	 * @return void
	 */
	public function track_view(): void {
		if ( ! is_singular( 'wpss_service' ) ) {
			return;
		}

		// Don't track own views.
		$service_id = get_the_ID();
		$vendor_id  = (int) get_post_field( 'post_author', $service_id );

		if ( is_user_logged_in() && get_current_user_id() === $vendor_id ) {
			return;
		}

		// Check if already tracked in this session.
		$viewed_key = 'wpss_viewed_' . $service_id;
		if ( isset( $_COOKIE[ $viewed_key ] ) ) {
			return;
		}

		// Increment view count.
		$views = (int) get_post_meta( $service_id, '_wpss_views', true );
		update_post_meta( $service_id, '_wpss_views', $views + 1 );

		// Set cookie to prevent duplicate tracking (24 hours).
		setcookie( $viewed_key, '1', time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
	}

	/**
	 * Render breadcrumb navigation.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_breadcrumb( Service $service ): void {
		$service_id = $service->id;
		$categories = get_the_terms( $service_id, 'wpss_service_category' );
		?>
		<nav class="wpss-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'wp-sell-services' ); ?>">
			<ol class="wpss-breadcrumb-list">
				<li class="wpss-breadcrumb-item">
					<a href="<?php echo esc_url( home_url() ); ?>">
						<?php esc_html_e( 'Home', 'wp-sell-services' ); ?>
					</a>
				</li>
				<li class="wpss-breadcrumb-item">
					<a href="<?php echo esc_url( get_post_type_archive_link( 'wpss_service' ) ); ?>">
						<?php esc_html_e( 'Services', 'wp-sell-services' ); ?>
					</a>
				</li>
				<?php if ( $categories && ! is_wp_error( $categories ) ) : ?>
					<?php
					$category = $categories[0];
					$parent   = $category->parent;

					// Build parent hierarchy.
					$parents = array();
					while ( $parent > 0 ) {
						$parent_term = get_term( $parent, 'wpss_service_category' );
						if ( $parent_term && ! is_wp_error( $parent_term ) ) {
							$parents[] = $parent_term;
							$parent    = $parent_term->parent;
						} else {
							break;
						}
					}

					// Display parents first.
					foreach ( array_reverse( $parents ) as $parent_term ) :
						?>
						<li class="wpss-breadcrumb-item">
							<a href="<?php echo esc_url( get_term_link( $parent_term ) ); ?>">
								<?php echo esc_html( $parent_term->name ); ?>
							</a>
						</li>
						<?php
					endforeach;
					?>
					<li class="wpss-breadcrumb-item">
						<a href="<?php echo esc_url( get_term_link( $category ) ); ?>">
							<?php echo esc_html( $category->name ); ?>
						</a>
					</li>
				<?php endif; ?>
				<li class="wpss-breadcrumb-item wpss-breadcrumb-current" aria-current="page">
					<?php echo esc_html( get_the_title( $service_id ) ); ?>
				</li>
			</ol>
		</nav>
		<?php
	}

	/**
	 * Render service title.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_title( Service $service ): void {
		?>
		<h1 class="wpss-service-title"><?php echo esc_html( $service->title ); ?></h1>
		<?php
	}

	/**
	 * Render service meta (rating, orders, views).
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_meta( Service $service ): void {
		$service_id   = $service->id;
		$vendor_id    = $service->vendor_id;
		$vendor       = get_userdata( $vendor_id );
		$rating_count = (int) get_post_meta( $service_id, '_wpss_rating_count', true );
		$rating_avg   = (float) get_post_meta( $service_id, '_wpss_rating_average', true );
		$order_count  = (int) get_post_meta( $service_id, '_wpss_order_count', true );
		?>
		<div class="wpss-service-meta">
			<div class="wpss-meta-item wpss-meta-vendor">
				<img src="<?php echo esc_url( get_avatar_url( $vendor_id, array( 'size' => 32 ) ) ); ?>"
					alt="<?php echo esc_attr( $vendor->display_name ?? '' ); ?>"
					class="wpss-vendor-mini-avatar">
				<a href="<?php echo esc_url( wpss_get_vendor_url( $vendor_id ) ); ?>" class="wpss-vendor-name">
					<?php echo esc_html( $vendor->display_name ?? __( 'Unknown Vendor', 'wp-sell-services' ) ); ?>
				</a>
				<?php if ( get_user_meta( $vendor_id, '_wpss_vendor_verified', true ) ) : ?>
					<span class="wpss-verified-badge" title="<?php esc_attr_e( 'Verified', 'wp-sell-services' ); ?>">✓</span>
				<?php endif; ?>
			</div>

			<?php if ( $rating_count > 0 ) : ?>
				<div class="wpss-meta-item wpss-meta-rating">
					<a href="#reviews" class="wpss-rating-link">
						<span class="wpss-star filled">★</span>
						<span class="wpss-rating-value"><?php echo esc_html( number_format( $rating_avg, 1 ) ); ?></span>
						<span class="wpss-rating-count">(<?php echo esc_html( $rating_count ); ?>)</span>
					</a>
				</div>
			<?php endif; ?>

			<?php if ( $order_count > 0 ) : ?>
				<div class="wpss-meta-item wpss-meta-orders">
					<span class="wpss-orders-icon">📦</span>
					<span class="wpss-orders-count">
						<?php
						printf(
							/* translators: %d: number of orders */
							esc_html( _n( '%d order', '%d orders', $order_count, 'wp-sell-services' ) ),
							$order_count
						);
						?>
					</span>
				</div>
			<?php endif; ?>

			<div class="wpss-meta-item wpss-meta-queue">
				<?php
				$queue_count = $this->get_queue_count( $vendor_id );
				if ( $queue_count > 0 ) :
					?>
					<span class="wpss-queue-indicator">
						<?php
						printf(
							/* translators: %d: number of orders in queue */
							esc_html( _n( '%d order in queue', '%d orders in queue', $queue_count, 'wp-sell-services' ) ),
							$queue_count
						);
						?>
					</span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render service gallery.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_gallery( Service $service ): void {
		wpss_get_template_part( 'partials/service', 'gallery', array( 'service' => $service ) );
	}

	/**
	 * Render service description.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_description( Service $service ): void {
		?>
		<div class="wpss-service-description">
			<h2><?php esc_html_e( 'About This Service', 'wp-sell-services' ); ?></h2>
			<div class="wpss-description-content">
				<?php echo wp_kses_post( apply_filters( 'the_content', $service->description ) ); ?>
			</div>

			<?php $this->render_service_highlights( $service ); ?>
			<?php $this->render_requirements( $service ); ?>
		</div>
		<?php
	}

	/**
	 * Render service highlights.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	private function render_service_highlights( Service $service ): void {
		$highlights = get_post_meta( $service->id, '_wpss_highlights', true );

		if ( empty( $highlights ) || ! is_array( $highlights ) ) {
			return;
		}
		?>
		<div class="wpss-service-highlights">
			<h3><?php esc_html_e( 'Service Highlights', 'wp-sell-services' ); ?></h3>
			<ul class="wpss-highlights-list">
				<?php foreach ( $highlights as $highlight ) : ?>
					<?php if ( ! empty( $highlight ) ) : ?>
						<li class="wpss-highlight-item">
							<span class="wpss-highlight-icon">✓</span>
							<?php echo esc_html( $highlight ); ?>
						</li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Render service requirements.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	private function render_requirements( Service $service ): void {
		$requirements = get_post_meta( $service->id, '_wpss_requirements', true );

		if ( empty( $requirements ) ) {
			return;
		}
		?>
		<div class="wpss-service-requirements">
			<h3><?php esc_html_e( 'Requirements', 'wp-sell-services' ); ?></h3>
			<p class="wpss-requirements-intro">
				<?php esc_html_e( 'To get started, the seller needs:', 'wp-sell-services' ); ?>
			</p>
			<div class="wpss-requirements-content">
				<?php
				if ( is_array( $requirements ) ) {
					echo '<ul class="wpss-requirements-list">';
					foreach ( $requirements as $req ) {
						$text = is_array( $req ) ? ( $req['question'] ?? $req['text'] ?? '' ) : $req;
						if ( ! empty( $text ) ) {
							echo '<li>' . esc_html( $text ) . '</li>';
						}
					}
					echo '</ul>';
				} else {
					echo wp_kses_post( wpautop( $requirements ) );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render about vendor section.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_about_vendor( Service $service ): void {
		$vendor_id = $service->vendor_id;
		$vendor    = get_userdata( $vendor_id );

		if ( ! $vendor ) {
			return;
		}

		$bio              = get_user_meta( $vendor_id, 'description', true );
		$languages        = get_user_meta( $vendor_id, '_wpss_vendor_languages', true );
		$skills           = get_user_meta( $vendor_id, '_wpss_vendor_skills', true );
		$completed_orders = (int) get_user_meta( $vendor_id, '_wpss_completed_orders', true );
		?>
		<div class="wpss-about-vendor">
			<h2><?php esc_html_e( 'About The Seller', 'wp-sell-services' ); ?></h2>

			<div class="wpss-vendor-profile">
				<div class="wpss-vendor-header">
					<img src="<?php echo esc_url( get_avatar_url( $vendor_id, array( 'size' => 100 ) ) ); ?>"
						alt="<?php echo esc_attr( $vendor->display_name ); ?>"
						class="wpss-vendor-avatar">
					<div class="wpss-vendor-info">
						<h3 class="wpss-vendor-name">
							<a href="<?php echo esc_url( wpss_get_vendor_url( $vendor_id ) ); ?>">
								<?php echo esc_html( $vendor->display_name ); ?>
							</a>
						</h3>
						<?php
						$tagline = get_user_meta( $vendor_id, '_wpss_vendor_tagline', true );
						if ( $tagline ) :
							?>
							<p class="wpss-vendor-tagline"><?php echo esc_html( $tagline ); ?></p>
						<?php endif; ?>

						<div class="wpss-vendor-quick-stats">
							<?php
							$rating_avg   = (float) get_user_meta( $vendor_id, '_wpss_rating_average', true );
							$rating_count = (int) get_user_meta( $vendor_id, '_wpss_rating_count', true );
							if ( $rating_count > 0 ) :
								?>
								<span class="wpss-quick-stat">
									<span class="wpss-star filled">★</span>
									<?php echo esc_html( number_format( $rating_avg, 1 ) ); ?>
									(<?php echo esc_html( $rating_count ); ?>)
								</span>
							<?php endif; ?>

							<?php if ( $completed_orders > 0 ) : ?>
								<span class="wpss-quick-stat">
									<?php
									printf(
										/* translators: %d: number of completed orders */
										esc_html( _n( '%d order completed', '%d orders completed', $completed_orders, 'wp-sell-services' ) ),
										$completed_orders
									);
									?>
								</span>
							<?php endif; ?>
						</div>
					</div>

					<a href="#" class="wpss-btn wpss-btn-outline wpss-contact-btn"
						data-vendor="<?php echo esc_attr( $vendor_id ); ?>">
						<?php esc_html_e( 'Contact Me', 'wp-sell-services' ); ?>
					</a>
				</div>

				<?php if ( $bio ) : ?>
					<div class="wpss-vendor-bio">
						<?php echo wp_kses_post( wpautop( $bio ) ); ?>
					</div>
				<?php endif; ?>

				<div class="wpss-vendor-meta-grid">
					<?php
					$country = get_user_meta( $vendor_id, '_wpss_vendor_country', true );
					if ( $country ) :
						?>
						<div class="wpss-vendor-meta-item">
							<span class="wpss-meta-label"><?php esc_html_e( 'From', 'wp-sell-services' ); ?></span>
							<span class="wpss-meta-value"><?php echo esc_html( $country ); ?></span>
						</div>
					<?php endif; ?>

					<div class="wpss-vendor-meta-item">
						<span class="wpss-meta-label"><?php esc_html_e( 'Member since', 'wp-sell-services' ); ?></span>
						<span class="wpss-meta-value"><?php echo esc_html( wp_date( 'M Y', strtotime( $vendor->user_registered ) ) ); ?></span>
					</div>

					<?php
					$response_time = get_user_meta( $vendor_id, '_wpss_vendor_response_time', true );
					if ( $response_time ) :
						?>
						<div class="wpss-vendor-meta-item">
							<span class="wpss-meta-label"><?php esc_html_e( 'Avg. Response Time', 'wp-sell-services' ); ?></span>
							<span class="wpss-meta-value"><?php echo esc_html( $response_time ); ?></span>
						</div>
					<?php endif; ?>

					<?php
					$last_delivery = get_user_meta( $vendor_id, '_wpss_last_delivery', true );
					if ( $last_delivery ) :
						?>
						<div class="wpss-vendor-meta-item">
							<span class="wpss-meta-label"><?php esc_html_e( 'Last Delivery', 'wp-sell-services' ); ?></span>
							<span class="wpss-meta-value"><?php echo esc_html( wpss_time_ago( $last_delivery ) ); ?></span>
						</div>
					<?php endif; ?>
				</div>

				<?php if ( $languages && is_array( $languages ) ) : ?>
					<div class="wpss-vendor-languages">
						<h4><?php esc_html_e( 'Languages', 'wp-sell-services' ); ?></h4>
						<ul class="wpss-languages-list">
							<?php foreach ( $languages as $language ) : ?>
								<li>
									<?php echo esc_html( $language['name'] ?? $language ); ?>
									<?php if ( ! empty( $language['level'] ) ) : ?>
										<span class="wpss-language-level">(<?php echo esc_html( $language['level'] ); ?>)</span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( $skills && is_array( $skills ) ) : ?>
					<div class="wpss-vendor-skills">
						<h4><?php esc_html_e( 'Skills', 'wp-sell-services' ); ?></h4>
						<div class="wpss-skills-list">
							<?php foreach ( $skills as $skill ) : ?>
								<span class="wpss-skill-tag"><?php echo esc_html( $skill ); ?></span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render service FAQs.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_faqs( Service $service ): void {
		wpss_get_template_part( 'partials/service', 'faqs', array( 'service' => $service ) );
	}

	/**
	 * Render service reviews.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_reviews( Service $service ): void {
		wpss_get_template_part( 'partials/service', 'reviews', array( 'service' => $service ) );
	}

	/**
	 * Render packages widget.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_packages( Service $service ): void {
		wpss_get_template_part( 'partials/service', 'packages', array( 'service' => $service ) );
	}

	/**
	 * Render vendor card.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_vendor_card( Service $service ): void {
		$vendor_id = $service->vendor_id;
		wpss_get_template_part( 'partials/vendor', 'card', array( 'vendor_id' => $vendor_id ) );
	}

	/**
	 * Render related services.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_related_services( Service $service ): void {
		$service_id = $service->id;

		// Get related services based on category and tags.
		$categories = get_the_terms( $service_id, 'wpss_service_category' );
		$tags       = get_the_terms( $service_id, 'wpss_service_tag' );

		$tax_query = array( 'relation' => 'OR' );

		if ( $categories && ! is_wp_error( $categories ) ) {
			$tax_query[] = array(
				'taxonomy' => 'wpss_service_category',
				'field'    => 'term_id',
				'terms'    => wp_list_pluck( $categories, 'term_id' ),
			);
		}

		if ( $tags && ! is_wp_error( $tags ) ) {
			$tax_query[] = array(
				'taxonomy' => 'wpss_service_tag',
				'field'    => 'term_id',
				'terms'    => wp_list_pluck( $tags, 'term_id' ),
			);
		}

		$related_args = array(
			'post_type'      => 'wpss_service',
			'post_status'    => 'publish',
			'posts_per_page' => 4,
			'post__not_in'   => array( $service_id ),
			'orderby'        => 'rand',
			'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		);

		/**
		 * Filter related services query args.
		 *
		 * @param array   $related_args Query arguments.
		 * @param Service $service      Current service.
		 */
		$related_args = apply_filters( 'wpss_related_services_args', $related_args, $service );

		$related_services = new \WP_Query( $related_args );

		if ( ! $related_services->have_posts() ) {
			wp_reset_postdata();
			return;
		}
		?>
		<div class="wpss-related-services">
			<h2><?php esc_html_e( 'Related Services', 'wp-sell-services' ); ?></h2>

			<div class="wpss-services-grid wpss-services-grid--4">
				<?php
				while ( $related_services->have_posts() ) :
					$related_services->the_post();
					wpss_get_template_part( 'content', 'service-card' );
				endwhile;
				?>
			</div>
		</div>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Render order modal.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_order_modal( Service $service ): void {
		$service_id = $service->id;
		$packages   = get_post_meta( $service_id, '_wpss_packages', true ) ?: array();
		$extras     = get_post_meta( $service_id, '_wpss_extras', true ) ?: array();

		// Don't show modal for own services.
		$vendor_id = (int) get_post_field( 'post_author', $service_id );
		if ( get_current_user_id() === $vendor_id ) {
			return;
		}
		?>
		<div id="wpss-order-modal" class="wpss-modal" hidden>
			<div class="wpss-modal-overlay"></div>
			<div class="wpss-modal-content">
				<button type="button" class="wpss-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
					&times;
				</button>

				<div class="wpss-modal-header">
					<h3><?php esc_html_e( 'Order Options', 'wp-sell-services' ); ?></h3>
				</div>

				<div class="wpss-modal-body">
					<form id="wpss-order-form" class="wpss-order-form">
						<input type="hidden" name="service_id" value="<?php echo esc_attr( $service_id ); ?>">
						<input type="hidden" name="package_index" value="0">

						<?php if ( ! empty( $extras ) ) : ?>
							<div class="wpss-order-extras">
								<h4><?php esc_html_e( 'Add Extras', 'wp-sell-services' ); ?></h4>
								<?php foreach ( $extras as $index => $extra ) : ?>
									<label class="wpss-extra-option">
										<input type="checkbox"
												name="extras[]"
												value="<?php echo esc_attr( $index ); ?>"
												data-price="<?php echo esc_attr( $extra['price'] ?? 0 ); ?>"
												data-time="<?php echo esc_attr( $extra['delivery_time'] ?? 0 ); ?>">
										<span class="wpss-extra-info">
											<span class="wpss-extra-title"><?php echo esc_html( $extra['title'] ?? '' ); ?></span>
											<?php if ( ! empty( $extra['description'] ) ) : ?>
												<span class="wpss-extra-desc"><?php echo esc_html( $extra['description'] ); ?></span>
											<?php endif; ?>
										</span>
										<span class="wpss-extra-price">
											+<?php echo esc_html( wpss_format_price( (float) ( $extra['price'] ?? 0 ) ) ); ?>
											<?php if ( ! empty( $extra['delivery_time'] ) ) : ?>
												<span class="wpss-extra-time">
													(+<?php echo esc_html( $extra['delivery_time'] ); ?> <?php esc_html_e( 'days', 'wp-sell-services' ); ?>)
												</span>
											<?php endif; ?>
										</span>
									</label>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<div class="wpss-order-quantity">
							<label for="wpss-quantity"><?php esc_html_e( 'Quantity', 'wp-sell-services' ); ?></label>
							<div class="wpss-quantity-input">
								<button type="button" class="wpss-quantity-btn wpss-quantity-minus">-</button>
								<input type="number"
										id="wpss-quantity"
										name="quantity"
										value="1"
										min="1"
										max="<?php echo esc_attr( apply_filters( 'wpss_max_order_quantity', 10 ) ); ?>">
								<button type="button" class="wpss-quantity-btn wpss-quantity-plus">+</button>
							</div>
						</div>
					</form>
				</div>

				<div class="wpss-modal-footer">
					<div class="wpss-order-summary">
						<div class="wpss-summary-row wpss-summary-package">
							<span class="wpss-summary-label"><?php esc_html_e( 'Package:', 'wp-sell-services' ); ?></span>
							<span class="wpss-summary-value wpss-package-name"></span>
						</div>
						<div class="wpss-summary-row wpss-summary-delivery">
							<span class="wpss-summary-label"><?php esc_html_e( 'Delivery:', 'wp-sell-services' ); ?></span>
							<span class="wpss-summary-value wpss-delivery-time"></span>
						</div>
						<div class="wpss-summary-row wpss-summary-total">
							<span class="wpss-summary-label"><?php esc_html_e( 'Total:', 'wp-sell-services' ); ?></span>
							<span class="wpss-summary-value wpss-total-price"></span>
						</div>
					</div>

					<button type="button" class="wpss-btn wpss-btn-primary wpss-btn-block wpss-add-to-cart-btn">
						<?php esc_html_e( 'Continue to Checkout', 'wp-sell-services' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render contact modal.
	 *
	 * @param Service $service Service object.
	 * @return void
	 */
	public function render_contact_modal( Service $service ): void {
		$vendor_id = $service->vendor_id;

		// Don't show for own services.
		if ( get_current_user_id() === $vendor_id ) {
			return;
		}

		$vendor = get_userdata( $vendor_id );
		if ( ! $vendor ) {
			return;
		}
		?>
		<div id="wpss-contact-modal" class="wpss-modal" hidden>
			<div class="wpss-modal-backdrop wpss-modal-overlay"></div>
			<div class="wpss-modal-dialog wpss-modal-content">
				<button type="button" class="wpss-modal-close" aria-label="<?php esc_attr_e( 'Close', 'wp-sell-services' ); ?>">
					&times;
				</button>

				<div class="wpss-modal-header">
					<h3><?php esc_html_e( 'Contact Seller', 'wp-sell-services' ); ?></h3>
				</div>

				<div class="wpss-modal-body">
					<div class="wpss-contact-vendor-info">
						<img src="<?php echo esc_url( get_avatar_url( $vendor_id, array( 'size' => 50 ) ) ); ?>"
							alt="<?php echo esc_attr( $vendor->display_name ); ?>"
							class="wpss-vendor-avatar">
						<div class="wpss-vendor-details">
							<strong><?php echo esc_html( $vendor->display_name ); ?></strong>
							<?php
							$response_time = get_user_meta( $vendor_id, '_wpss_vendor_response_time', true );
							if ( $response_time ) :
								?>
								<span class="wpss-response-time">
									<?php
									printf(
										/* translators: %s: response time */
										esc_html__( 'Usually responds in %s', 'wp-sell-services' ),
										esc_html( $response_time )
									);
									?>
								</span>
							<?php endif; ?>
						</div>
					</div>

					<?php if ( ! is_user_logged_in() ) : ?>
						<div class="wpss-login-notice">
							<p>
								<?php
								printf(
									/* translators: 1: login URL, 2: register URL */
									wp_kses(
										__( 'Please <a href="%1$s">log in</a> or <a href="%2$s">register</a> to contact this seller.', 'wp-sell-services' ),
										array( 'a' => array( 'href' => array() ) )
									),
									esc_url( wp_login_url( get_permalink() ) ),
									esc_url( wp_registration_url() )
								);
								?>
							</p>
						</div>
					<?php else : ?>
						<form id="wpss-contact-form" class="wpss-contact-form">
							<input type="hidden" name="vendor_id" value="<?php echo esc_attr( $vendor_id ); ?>">
							<input type="hidden" name="service_id" value="<?php echo esc_attr( $service->id ); ?>">

							<div class="wpss-form-field">
								<label for="wpss-contact-message"><?php esc_html_e( 'Your Message', 'wp-sell-services' ); ?></label>
								<textarea id="wpss-contact-message"
											name="message"
											rows="5"
											placeholder="<?php esc_attr_e( 'Tell the seller what you need...', 'wp-sell-services' ); ?>"
											required></textarea>
								<p class="wpss-field-hint">
									<?php esc_html_e( 'Be specific about your requirements for better assistance.', 'wp-sell-services' ); ?>
								</p>
							</div>

							<div class="wpss-form-field">
								<label for="wpss-contact-attachment"><?php esc_html_e( 'Attach Files (optional)', 'wp-sell-services' ); ?></label>
								<input type="file"
										id="wpss-contact-attachment"
										name="attachments[]"
										multiple
										accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.zip">
								<p class="wpss-field-hint">
									<?php esc_html_e( 'Max 5 files. Allowed: images, PDF, Word, ZIP.', 'wp-sell-services' ); ?>
								</p>
							</div>

							<button type="submit" class="wpss-btn wpss-btn-primary wpss-btn-block">
								<?php esc_html_e( 'Send Message', 'wp-sell-services' ); ?>
							</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Get vendor's current queue count.
	 *
	 * @param int $vendor_id Vendor user ID.
	 * @return int Queue count.
	 */
	private function get_queue_count( int $vendor_id ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'wpss_orders';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE vendor_id = %d AND status IN ('pending', 'in_progress')",
				$vendor_id
			)
		);
	}
}
