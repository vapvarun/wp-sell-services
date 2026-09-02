<?php
/**
 * Shortcodes
 *
 * Registers all frontend shortcodes.
 *
 * @package WPSellServices\Frontend
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Frontend;

defined( 'ABSPATH' ) || exit;

use WPSellServices\Services\SearchService;
use WPSellServices\Services\VendorService;

/**
 * Handles all shortcode registrations and rendering.
 *
 * @since 1.0.0
 */
class Shortcodes {

	/**
	 * Initialize shortcodes.
	 *
	 * @return void
	 */
	public function init(): void {
		// Service shortcodes.
		add_shortcode( 'wpss_services', array( $this, 'services_grid' ) );
		add_shortcode( 'wpss_service_search', array( $this, 'service_search' ) );
		add_shortcode( 'wpss_featured_services', array( $this, 'featured_services' ) );
		add_shortcode( 'wpss_service_categories', array( $this, 'service_categories' ) );

		// Vendor shortcodes.
		add_shortcode( 'wpss_vendors', array( $this, 'vendors_grid' ) );
		add_shortcode( 'wpss_vendor_profile', array( $this, 'vendor_profile' ) );
		add_shortcode( 'wpss_top_vendors', array( $this, 'top_vendors' ) );
		add_shortcode( 'wpss_seller_card', array( $this, 'seller_card' ) );

		// Buyer request shortcodes.
		add_shortcode( 'wpss_buyer_requests', array( $this, 'buyer_requests' ) );
		add_shortcode( 'wpss_post_request', array( $this, 'post_request_form' ) );

		// Dashboard shortcodes.
		add_shortcode( 'wpss_my_orders', array( $this, 'my_orders' ) );
		add_shortcode( 'wpss_order_details', array( $this, 'order_details' ) );

		// Vendor registration.
		add_shortcode( 'wpss_vendor_registration', array( $this, 'vendor_registration' ) );

		// Account shortcodes.
		add_shortcode( 'wpss_login', array( $this, 'login_form' ) );
		add_shortcode( 'wpss_register', array( $this, 'register_form' ) );

		// Cart shortcode.
		add_shortcode( 'wpss_cart', array( $this, 'cart_page' ) );

		// Checkout fallback — only registers if no adapter has claimed it.
		if ( ! shortcode_exists( 'wpss_checkout' ) ) {
			add_shortcode( 'wpss_checkout', array( $this, 'checkout_fallback' ) );
		}
	}

	/**
	 * Services grid shortcode.
	 *
	 * [wpss_services category="5" limit="12" columns="3" orderby="rating"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function services_grid( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		$atts = shortcode_atts(
			array(
				'category' => '',
				'tag'      => '',
				'vendor'   => '',
				'limit'    => 12,
				'columns'  => 4,
				'orderby'  => 'date',
				'order'    => 'DESC',
				'featured' => '',
			),
			$atts,
			'wpss_services'
		);

		// ONE grid implementation.
		//
		// This method used to build its own WP_Query and render through a
		// private render_service_card(), while the wpss/service-grid block built
		// a second query with its own inline markup, and wpss_render_services_grid()
		// - the only one that renders the theme-overridable
		// templates/content-service-card.php, fires the wpss_service_card_*
		// hooks and shows the favourites toggle - was reachable only from REST
		// and one AJAX handler. Three implementations of the same grid, and the
		// two a visitor actually saw were the two missing those features.
		//
		// The shortcode keeps its documented attribute names; they are mapped
		// onto the shared renderer's vocabulary here.
		$grid = wpss_render_services_grid(
			array(
				'postsPerPage' => absint( $atts['limit'] ),
				'orderBy'      => $atts['orderby'],
				'order'        => $atts['order'],
				'category'     => $atts['category'],
				'tag'          => $atts['tag'],
				'vendor'       => $atts['vendor'],
				'featured'     => $atts['featured'],
			),
			max( 1, (int) get_query_var( 'paged' ) )
		);

		return sprintf(
			'<div class="wpss-services-grid wpss-columns-%s">%s</div>%s',
			esc_attr( (string) $atts['columns'] ),
			$grid['html'],
			$grid['pagination']
		);
	}

	/**
	 * Service search shortcode.
	 *
	 * [wpss_service_search placeholder="Search services..." show_categories="true"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function service_search( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		$atts = shortcode_atts(
			array(
				'placeholder'     => '',
				'show_categories' => 'true',
				'button_text'     => '',
				'action'          => '',
			),
			$atts,
			'wpss_service_search'
		);

		// ONE search form.
		//
		// The shortcode and the wpss/service-search block rendered two forms
		// with the same field contract (name="search", name="category") but
		// different class vocabularies - wpss-search-category here against
		// wpss-category-select-wrap / wpss-category-select in the block - so
		// the stylesheet had to carry both, and the block's search icon and
		// input wrapper never appeared on the shortcode. Delegating leaves one
		// markup to style and one place to change.
		//
		// Empty placeholder/button_text are passed through deliberately: the
		// block substitutes its own translated defaults, so the copy is defined
		// once rather than differing per surface as it did.
		return ( new \WPSellServices\Blocks\ServiceSearch() )->render(
			array(
				'placeholder'        => (string) $atts['placeholder'],
				'buttonText'         => (string) $atts['button_text'],
				'showCategoryFilter' => filter_var( $atts['show_categories'], FILTER_VALIDATE_BOOLEAN ),
				'action'             => (string) $atts['action'],
			)
		);
	}

	/**
	 * Featured services shortcode.
	 *
	 * [wpss_featured_services limit="6" columns="3"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function featured_services( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		$atts['featured'] = 'true';
		return $this->services_grid( $atts );
	}

	/**
	 * Service categories shortcode.
	 *
	 * [wpss_service_categories parent="0" show_count="true" columns="4"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function service_categories( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		$atts = shortcode_atts(
			array(
				'parent'     => 0,
				'show_count' => 'true',
				'columns'    => 4,
				'hide_empty' => 'true',
				'limit'      => 12,
			),
			$atts,
			'wpss_service_categories'
		);

		$categories = get_terms(
			array(
				'taxonomy'   => 'wpss_service_category',
				'parent'     => absint( $atts['parent'] ),
				'hide_empty' => 'true' === $atts['hide_empty'],
				'number'     => absint( $atts['limit'] ),
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);

		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			return '<p class="wpss-no-results">' . esc_html__( 'No categories found.', 'wp-sell-services' ) . '</p>';
		}

		// One query for the whole grid; the card falls back to its own lookup only
		// when a caller does not prime.
		$service_counts = wpss_get_category_service_counts( wp_list_pluck( $categories, 'term_id' ) );

		ob_start();
		?>
		<div class="wpss-categories-grid wpss-columns-<?php echo esc_attr( $atts['columns'] ); ?>">
			<?php
			foreach ( $categories as $category ) :
				// ONE category card, the theme-overridable partial.
				//
				// This used to emit its own markup, and the wpss/service-categories
				// block emitted a second version: <h3> with a bare
				// <span class="{icon}"> here against <h4>, a dashicons-prefixed
				// span, a Lucide folder fallback and a lazy-loaded image there. The
				// card CSS lives in blocks.css and targets .wpss-category-name /
				// .wpss-category-content, so the block's structure was the one the
				// stylesheet was written for - the shortcode was the odd one out.
				wpss_get_template_part(
					'partials/category-card',
					'',
					array(
						'category'       => $category,
						'show_count'     => 'true' === $atts['show_count'],
						'show_icon'      => true,
						'show_image'     => true,
						'service_counts' => $service_counts,
					)
				);
			endforeach;
			?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Vendors grid shortcode.
	 *
	 * [wpss_vendors limit="12" columns="4" orderby="rating"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function vendors_grid( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		// wpss_get_vendor_url() points every vendor link on the site at
		// `{vendors page}?vendor={nicename}` whenever a vendors page exists, but
		// this grid never read that parameter — so following any vendor link
		// landed you back on the full directory instead of the profile you
		// clicked. Renders the profile through the existing shortcode rather
		// than a second copy of that markup.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public link.
		$requested_vendor = isset( $_GET['vendor'] ) ? sanitize_title( wp_unslash( $_GET['vendor'] ) ) : '';

		if ( '' !== $requested_vendor ) {
			$vendor_user = get_user_by( 'slug', $requested_vendor );

			// Fall through to the directory for an unknown slug: a stale link
			// should show the list, not an error page.
			if ( $vendor_user && wpss_is_vendor( (int) $vendor_user->ID ) ) {
				return $this->vendor_profile( array( 'id' => (int) $vendor_user->ID ) );
			}
		}

		$atts = shortcode_atts(
			array(
				'limit'     => 12, // Alias of per_page kept for existing shortcodes.
				'per_page'  => 0,
				'paged'     => 0,
				'columns'   => 4,
				'orderby'   => 'rating',
				'order'     => 'DESC',
				'available' => '',
				'country'   => '',
				'toolbar'   => 'true', // Sort/filter selects + pagination.
			),
			$atts,
			'wpss_vendors'
		);

		$toolbar  = 'true' === (string) $atts['toolbar'];
		$per_page = absint( $atts['per_page'] ) ?: absint( $atts['limit'] ) ?: 12;
		$sorts    = array(
			'rating' => __( 'Sort: Top rated', 'wp-sell-services' ),
			'newest' => __( 'Sort: Newest', 'wp-sell-services' ),
			'orders' => __( 'Sort: Most orders', 'wp-sell-services' ),
		);

		// The toolbar's selects and pagination links round-trip through the URL,
		// so URL parameters win over attributes when the toolbar is shown.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$orderby   = (string) $atts['orderby'];
		$available = 'true' === (string) $atts['available'];
		$country   = (string) $atts['country'];
		$paged     = max( 1, absint( $atts['paged'] ) );
		if ( $toolbar ) {
			$orderby   = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : $orderby;
			$available = isset( $_GET['available'] ) ? '1' === sanitize_key( wp_unslash( $_GET['available'] ) ) : $available;
			$country   = isset( $_GET['country'] ) ? sanitize_text_field( wp_unslash( $_GET['country'] ) ) : $country;
			$paged     = max( $paged, absint( $_GET['vpage'] ?? 0 ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $sorts[ $orderby ] ) ) {
			$orderby = (string) $atts['orderby'];
		}

		$filters = array(
			'available' => $available,
			'country'   => $country,
		);

		$vendor_service = new VendorService();
		$total          = $vendor_service->count_all( $filters );
		$pages          = max( 1, (int) ceil( $total / $per_page ) );
		$paged          = min( $paged, $pages );
		$vendors        = $vendor_service->get_all(
			$filters + array(
				'limit'   => $per_page,
				'offset'  => ( $paged - 1 ) * $per_page,
				'orderby' => $orderby,
				'order'   => $atts['order'],
			)
		);

		// Four queries for the whole grid instead of four PER CARD.
		wpss_prime_vendor_card_caches(
			array_map( static fn ( $vendor ) => (int) $vendor->user_id, $vendors )
		);

		ob_start();
		if ( $toolbar ) :
			// Option values are URLs: .wpss-url-select navigates on change (frontend.js).
			$link = static fn ( array $args ): string => add_query_arg( $args + array( 'vpage' => false ) );
			?>
			<div class="wpss-vendors-toolbar">
				<p class="wpss-vendors-toolbar__count">
					<?php
					printf(
						/* translators: %s: number of vendors */
						esc_html( _n( '%s vendor found', '%s vendors found', $total, 'wp-sell-services' ) ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</p>
				<div class="wpss-vendors-toolbar__controls">
					<select class="wpss-url-select" aria-label="<?php esc_attr_e( 'Sort vendors', 'wp-sell-services' ); ?>">
						<?php foreach ( $sorts as $sort_key => $sort_label ) : ?>
							<option value="<?php echo esc_url( $link( array( 'sort' => 'rating' === $sort_key ? false : $sort_key ) ) ); ?>" <?php selected( $orderby, $sort_key ); ?>>
								<?php echo esc_html( $sort_label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<select class="wpss-url-select" aria-label="<?php esc_attr_e( 'Filter by availability', 'wp-sell-services' ); ?>">
						<option value="<?php echo esc_url( $link( array( 'available' => false ) ) ); ?>" <?php selected( $available, false ); ?>>
							<?php esc_html_e( 'All vendors', 'wp-sell-services' ); ?>
						</option>
						<option value="<?php echo esc_url( $link( array( 'available' => '1' ) ) ); ?>" <?php selected( $available, true ); ?>>
							<?php esc_html_e( 'Available now', 'wp-sell-services' ); ?>
						</option>
					</select>
					<?php $countries = $vendor_service->get_countries(); ?>
					<?php if ( ! empty( $countries ) ) : ?>
						<select class="wpss-url-select" aria-label="<?php esc_attr_e( 'Filter by country', 'wp-sell-services' ); ?>">
							<option value="<?php echo esc_url( $link( array( 'country' => false ) ) ); ?>" <?php selected( $country, '' ); ?>>
								<?php esc_html_e( 'All countries', 'wp-sell-services' ); ?>
							</option>
							<?php foreach ( $countries as $country_code ) : ?>
								<option value="<?php echo esc_url( $link( array( 'country' => $country_code ) ) ); ?>" <?php selected( $country, $country_code ); ?>>
									<?php echo esc_html( wpss_get_country_name( (string) $country_code ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>
		<div class="wpss-vendors-grid wpss-columns-<?php echo esc_attr( $atts['columns'] ); ?>">
			<?php
			if ( ! empty( $vendors ) ) :
				foreach ( $vendors as $vendor ) :
					// ONE vendor card, the theme-overridable partial.
					//
					// This used to call a private render_vendor_card() that
					// duplicated templates/partials/vendor-card.php - same class
					// vocabulary, different markup, and neither fired the card's
					// hooks or honoured a theme override. The partial derives
					// everything it needs from the vendor id, so nothing has to
					// be reshaped for it here.
					wpss_get_template_part(
						'partials/vendor-card',
						'',
						array( 'vendor_id' => (int) $vendor->user_id )
					);
				endforeach;
			else :
				?>
				<p class="wpss-no-results"><?php esc_html_e( 'No vendors found.', 'wp-sell-services' ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( $toolbar && $pages > 1 ) : ?>
			<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Vendor pages', 'wp-sell-services' ); ?>">
				<?php
				echo wp_kses_post(
					(string) paginate_links(
						array(
							'base'      => str_replace( '999999999', '%#%', add_query_arg( 'vpage', 999999999 ) ),
							'format'    => '',
							'current'   => $paged,
							'total'     => $pages,
							'prev_text' => '&larr; ' . __( 'Previous', 'wp-sell-services' ),
							'next_text' => __( 'Next', 'wp-sell-services' ) . ' &rarr;',
						)
					)
				);
				?>
			</nav>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}

	/**
	 * Top vendors shortcode.
	 *
	 * [wpss_top_vendors limit="6"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function top_vendors( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		$atts['orderby'] = 'rating';
		$atts['order']   = 'DESC';
		$atts['toolbar'] = 'false';
		return $this->vendors_grid( $atts );
	}

	/**
	 * Vendor profile shortcode.
	 *
	 * [wpss_vendor_profile id="123"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function vendor_profile( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		$atts = shortcode_atts(
			array(
				'id' => get_query_var( 'vendor_id', 0 ),
			),
			$atts,
			'wpss_vendor_profile'
		);

		$vendor_id = absint( $atts['id'] );

		if ( ! $vendor_id ) {
			return '<p class="wpss-error">' . esc_html__( 'Vendor not found.', 'wp-sell-services' ) . '</p>';
		}

		// "No profile row" is not "no such vendor". A member can hold the vendor
		// role with no row at all - granted by an admin, promoted by a filter, or
		// seeded - and this used to tell a visitor the seller did not exist.
		// wpss_is_vendor() is the canonical answer; a missing row is an empty
		// profile, which the template already renders empty states for.
		$profile = wpss_get_vendor_profile_or_default( $vendor_id );

		if ( ! $profile ) {
			return '<p class="wpss-error">' . esc_html__( 'Vendor not found.', 'wp-sell-services' ) . '</p>';
		}

		$template = locate_template( 'wp-sell-services/vendor/profile.php' );
		if ( ! $template ) {
			$template = WPSS_PLUGIN_DIR . 'templates/vendor/profile.php';
		}

		ob_start();
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			$this->render_vendor_profile_fallback( $profile, $vendor_id );
		}
		return ob_get_clean();
	}

	/**
	 * Seller card shortcode.
	 *
	 * [wpss_seller_card user_id="12" show_bio="true" layout="vertical"]
	 *
	 * The wpss/seller-card block existed with no shortcode equivalent, so a
	 * classic-editor or page-builder site had no way to place a single seller
	 * card - the only routes to one were the block or the single-service
	 * sidebar. This is a wrapper around that block, not a second renderer.
	 *
	 * Defaults to the vendor whose profile is being viewed, so
	 * [wpss_seller_card] with no attributes works inside a vendor template.
	 *
	 * @since 1.5.1
	 *
	 * @param array<string, mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function seller_card( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		$atts = shortcode_atts(
			array(
				'user_id'       => 0,
				'show_bio'      => 'true',
				'show_stats'    => 'true',
				'show_rating'   => 'true',
				'show_services' => 'true',
				'show_button'   => 'true',
				'layout'        => 'vertical',
			),
			$atts,
			'wpss_seller_card'
		);

		$user_id = absint( $atts['user_id'] );

		if ( ! $user_id ) {
			$user_id = (int) get_query_var( 'author' );
		}

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return '';
		}

		$block = \WPSellServices\Blocks\BlocksManager::instance()->get_block( 'seller-card' );

		if ( ! $block instanceof \WPSellServices\Blocks\AbstractBlock ) {
			return '';
		}

		return $block->render(
			array(
				'userId'       => $user_id,
				'showBio'      => filter_var( $atts['show_bio'], FILTER_VALIDATE_BOOLEAN ),
				'showStats'    => filter_var( $atts['show_stats'], FILTER_VALIDATE_BOOLEAN ),
				'showRating'   => filter_var( $atts['show_rating'], FILTER_VALIDATE_BOOLEAN ),
				'showServices' => filter_var( $atts['show_services'], FILTER_VALIDATE_BOOLEAN ),
				'showButton'   => filter_var( $atts['show_button'], FILTER_VALIDATE_BOOLEAN ),
				'layout'       => sanitize_key( (string) $atts['layout'] ),
			)
		);
	}

	/**
	 * Buyer requests shortcode.
	 *
	 * [wpss_buyer_requests limit="10" category="5"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function buyer_requests( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		$atts = shortcode_atts(
			array(
				'limit'      => 10,
				'category'   => '',
				'budget_min' => '',
				'budget_max' => '',
			),
			$atts,
			'wpss_buyer_requests'
		);

		// The shortcode is a wrapper around the block, not a second renderer.
		//
		// It used to run its own get_open() call and its own card markup, which
		// is how the two surfaces drifted: the block showed expired requests,
		// and only the shortcode's card was currency-aware. Rendering through
		// the block means one query, one card template (content-request-card.php,
		// so a theme override applies to both), and one set of hooks.
		//
		// The shortcode's own attribute names are kept - `limit`, `budget_min`,
		// `budget_max` are the published API - and mapped onto the block's.
		$block = \WPSellServices\Blocks\BlocksManager::instance()->get_block( 'buyer-requests' );

		if ( ! $block instanceof \WPSellServices\Blocks\AbstractBlock ) {
			return '';
		}

		$output = $block->render(
			array(
				'perPage'   => absint( $atts['limit'] ),
				'category'  => absint( $atts['category'] ),
				'budgetMin' => (float) $atts['budget_min'],
				'budgetMax' => (float) $atts['budget_max'],
			)
		);

		return '<div class="wpss-app-shell"><div class="wpss-app-shell__container">' . $output . '</div></div>';
	}

	/**
	 * Post request form shortcode.
	 *
	 * [wpss_post_request]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function post_request_form( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		if ( ! is_user_logged_in() ) {
			return '<div class="wpss-notice">' . sprintf(
				/* translators: %s: login URL */
				__( 'Please <a href="%s">log in</a> to post a request.', 'wp-sell-services' ),
				esc_url( wp_login_url( get_permalink() ) )
			) . '</div>';
		}

		// Where to send the buyer after a successful post. Buyer requests
		// archive when one exists, otherwise the requests page, otherwise home.
		$success_redirect = get_post_type_archive_link( 'wpss_request' );
		if ( ! $success_redirect ) {
			$success_redirect = wpss_get_page_url( 'requests' );
		}

		ob_start();
		?>
		<div class="wpss-post-request" data-wpss-post-request>
			<form id="wpss-post-request-form" class="wpss-form" novalidate
				data-success-redirect="<?php echo esc_url( $success_redirect ); ?>">
				<?php wp_nonce_field( 'wpss_post_request', 'wpss_request_nonce' ); ?>

				<div
					class="wpss-form-feedback wpss-form-feedback--error"
					data-request-form-error
					role="alert"
					aria-live="assertive"
					hidden></div>

				<div class="wpss-form-row">
					<label for="request_title"><?php esc_html_e( 'Title', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<input type="text" name="title" id="request_title" required maxlength="100" placeholder="<?php esc_attr_e( 'e.g., I need a WordPress website designed', 'wp-sell-services' ); ?>" data-field="title" aria-describedby="request_title_error">
					<p class="wpss-field-error" id="request_title_error" data-field-error="title" role="alert" hidden></p>
				</div>

				<div class="wpss-form-row">
					<label for="request_description"><?php esc_html_e( 'Description', 'wp-sell-services' ); ?> <span class="required">*</span></label>
					<textarea name="description" id="request_description" rows="5" required placeholder="<?php esc_attr_e( 'Describe what you need in detail...', 'wp-sell-services' ); ?>" data-field="description" aria-describedby="request_description_error"></textarea>
					<p class="wpss-field-error" id="request_description_error" data-field-error="description" role="alert" hidden></p>
				</div>

				<div class="wpss-form-row">
					<label for="request_category"><?php esc_html_e( 'Category', 'wp-sell-services' ); ?></label>
					<select name="category" id="request_category" data-field="category">
						<option value=""><?php esc_html_e( 'Select a category', 'wp-sell-services' ); ?></option>
						<?php
						$categories = get_terms(
							array(
								'taxonomy'   => 'wpss_service_category',
								'hide_empty' => false,
							)
						);

						if ( ! is_wp_error( $categories ) ) :
							foreach ( $categories as $category ) :
								?>
								<option value="<?php echo esc_attr( (string) $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
								<?php
							endforeach;
						endif;
						?>
					</select>
				</div>

				<div class="wpss-form-row wpss-form-row-double">
					<div class="wpss-form-col">
						<label for="request_budget_min"><?php esc_html_e( 'Budget Min', 'wp-sell-services' ); ?></label>
						<input type="number" name="budget_min" id="request_budget_min" min="0" step="<?php echo esc_attr( wpss_get_price_input_attrs()['step'] ); ?>" placeholder="0" data-field="budget_min" aria-describedby="request_budget_error">
					</div>
					<div class="wpss-form-col">
						<label for="request_budget_max"><?php esc_html_e( 'Budget Max', 'wp-sell-services' ); ?></label>
						<input type="number" name="budget_max" id="request_budget_max" min="0" step="<?php echo esc_attr( wpss_get_price_input_attrs()['step'] ); ?>" placeholder="0" data-field="budget_max" aria-describedby="request_budget_error">
					</div>
					<p class="wpss-field-error" id="request_budget_error" data-field-error="budget_max" role="alert" hidden></p>
				</div>

				<div class="wpss-form-row">
					<label for="request_deadline"><?php esc_html_e( 'Deadline', 'wp-sell-services' ); ?></label>
					<input type="date" name="deadline" id="request_deadline" min="<?php echo esc_attr( wp_date( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>" data-field="deadline">
				</div>

				<div class="wpss-form-row">
					<label for="request_skills"><?php esc_html_e( 'Required Skills', 'wp-sell-services' ); ?></label>
					<input type="text" name="skills_required" id="request_skills" placeholder="<?php esc_attr_e( 'e.g., WordPress, PHP, JavaScript (comma-separated)', 'wp-sell-services' ); ?>" data-field="skills_required">
					<p class="wpss-form-hint"><?php esc_html_e( 'Separate multiple skills with commas.', 'wp-sell-services' ); ?></p>
				</div>

				<div class="wpss-form-actions">
					<button type="submit" class="wpss-btn wpss-btn-primary" data-request-submit><?php esc_html_e( 'Post Request', 'wp-sell-services' ); ?></button>
				</div>
			</form>

			<div class="wpss-post-request__success wpss-empty-state" data-request-success hidden>
				<div class="wpss-empty-state__icon">
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon::render() returns hand-built SVG with internally-escaped attributes.
					echo \WPSellServices\Services\Icon::render(
						'badge-check',
						array(
							'width'  => '48',
							'height' => '48',
						)
					);
					?>
				</div>
				<h3 class="wpss-empty-state__title"><?php esc_html_e( 'Request posted', 'wp-sell-services' ); ?></h3>
				<p class="wpss-empty-state__body"><?php esc_html_e( 'Your request is now live. Vendors can browse it and send you proposals.', 'wp-sell-services' ); ?></p>
				<a href="#" class="wpss-btn wpss-btn-primary" data-request-success-link><?php esc_html_e( 'View buyer requests', 'wp-sell-services' ); ?></a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * My orders shortcode.
	 *
	 * [wpss_my_orders]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function my_orders( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		if ( ! is_user_logged_in() ) {
			return '<div class="wpss-notice">' . sprintf(
				/* translators: %s: login URL */
				__( 'Please <a href="%s">log in</a> to view your orders.', 'wp-sell-services' ),
				esc_url( wp_login_url( get_permalink() ) )
			) . '</div>';
		}

		$atts = shortcode_atts(
			array(
				'type'   => 'customer', // customer or vendor.
				'status' => '',
				'limit'  => 20,
			),
			$atts,
			'wpss_my_orders'
		);

		$user_id        = get_current_user_id();
		$is_vendor_view = 'vendor' === $atts['type'];
		$status         = sanitize_key( (string) $atts['status'] );
		$per_page       = max( 1, absint( $atts['limit'] ) );
		$paged          = max( 1, (int) get_query_var( 'paged', 1 ) );

		// Paginated, counted, and hydrated - none of which this did before.
		//
		// It ran its own raw $wpdb SELECT with `LIMIT 20` and no OFFSET, no
		// COUNT(*) and no navigation, so a buyer with more than 20 orders saw
		// the first 20 and had no route to the rest. It also duplicated a query
		// wpss_get_user_orders()/wpss_get_vendor_orders() already own - and
		// those return hydrated ServiceOrder models, so the raw-row reads below
		// become model reads.
		$total = $is_vendor_view
			? wpss_count_vendor_orders( $user_id, $status )
			: wpss_count_user_orders( $user_id, $status );

		$query_args = array(
			'limit'  => $per_page,
			'offset' => ( $paged - 1 ) * $per_page,
			'status' => $status,
		);

		$orders = $is_vendor_view
			? wpss_get_vendor_orders( $user_id, $query_args )
			: wpss_get_user_orders( $user_id, $query_args );

		// One query for every service title on the page instead of one per row.
		// get_the_title() in the loop below was an N+1: 20 rows meant 20 extra
		// post lookups.
		$service_ids = array_values(
			array_filter( array_map( static fn ( $order ) => (int) $order->service_id, $orders ) )
		);

		if ( $service_ids ) {
			_prime_post_caches( $service_ids, false, false );
		}

		ob_start();
		?>
		<div class="wpss-my-orders">
			<?php if ( ! empty( $orders ) ) : ?>
				<table class="wpss-orders-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Order', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Service', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Status', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Date', 'wp-sell-services' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'wp-sell-services' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $orders as $order ) : ?>
							<tr>
								<td>#<?php echo esc_html( $order->order_number ?: $order->id ); ?></td>
								<td><?php echo esc_html( get_the_title( $order->service_id ) ); ?></td>
								<td><?php echo wp_kses_post( function_exists( 'wpss_format_currency' ) ? wpss_format_currency( (float) $order->total, $order->currency ) : '$' . number_format( (float) $order->total, 2 ) ); ?></td>
								<td><span class="wpss-status wpss-status-<?php echo esc_attr( $order->status ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $order->status ) ) ); ?></span></td>
								<td>
									<?php
									// ServiceOrder hydrates created_at to a DateTimeImmutable, so
									// strtotime() - which this used while it read raw $wpdb rows -
									// throws a TypeError. Same accessor the admin order screen uses.
									echo $order->created_at
										? esc_html( wp_date( get_option( 'date_format' ), $order->created_at->getTimestamp() ) )
										: '&mdash;';
									?>
								</td>
								<td><a href="<?php echo esc_url( wpss_get_order_url( (int) $order->id ) ?: '#' ); ?>" class="button button-small"><?php esc_html_e( 'View', 'wp-sell-services' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php
				// The route to orders 21+, which did not exist before.
				wpss_render_pagination( (int) ceil( $total / $per_page ) );
				?>
			<?php else : ?>
				<div class="wpss-empty-state">
					<div class="wpss-empty-state__icon">
						<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Icon::render() returns hand-built SVG with internally-escaped attributes.
						echo \WPSellServices\Services\Icon::render(
							'shopping-bag',
							array(
								'width'  => '48',
								'height' => '48',
							)
						);
						?>
					</div>
					<h3 class="wpss-empty-state__title"><?php esc_html_e( 'No orders yet', 'wp-sell-services' ); ?></h3>
					<p class="wpss-empty-state__body"><?php esc_html_e( 'Your orders will show here once you purchase a service.', 'wp-sell-services' ); ?></p>
					<a href="<?php echo esc_url( home_url( '/services/' ) ); ?>" class="wpss-btn wpss-btn--primary"><?php esc_html_e( 'Browse services', 'wp-sell-services' ); ?></a>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Order details shortcode.
	 *
	 * [wpss_order_details]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function order_details( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		if ( ! is_user_logged_in() ) {
			return '<div class="wpss-notice">' . sprintf(
				/* translators: %s: login URL */
				__( 'Please <a href="%s">log in</a> to view order details.', 'wp-sell-services' ),
				esc_url( wp_login_url( get_permalink() ) )
			) . '</div>';
		}

		$order_id = function_exists( 'wpss_resolve_request_order_id' ) ? wpss_resolve_request_order_id() : 0;

		if ( ! $order_id ) {
			return '<div class="wpss-error">' . esc_html__( 'Order not found.', 'wp-sell-services' ) . '</div>';
		}

		global $wpdb;

		$order = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}wpss_orders WHERE id = %d",
				$order_id
			)
		);

		if ( ! $order ) {
			return '<div class="wpss-error">' . esc_html__( 'Order not found.', 'wp-sell-services' ) . '</div>';
		}

		$user_id = get_current_user_id();

		// Check permission.
		if ( (int) $order->customer_id !== $user_id && (int) $order->vendor_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return '<div class="wpss-error">' . esc_html__( 'You do not have permission to view this order.', 'wp-sell-services' ) . '</div>';
		}

		// Render the canonical full order view. order-view.php is self-contained
		// (it resolves the order from $order_id, which is set above). The old
		// lookup targeted order/details.php, which never existed, so the shortcode
		// always fell back to a reduced inline copy (Basecamp #10110742943).
		$template = locate_template( 'wp-sell-services/order/order-view.php' );
		if ( ! $template ) {
			$template = WPSS_PLUGIN_DIR . 'templates/order/order-view.php';
		}

		ob_start();
		if ( file_exists( $template ) ) {
			include $template;
		} else {
			$this->render_order_details_fallback( $order, $user_id );
		}
		return ob_get_clean();
	}

	/**
	 * Login form shortcode.
	 *
	 * [wpss_login redirect="/dashboard"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function login_form( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		if ( is_user_logged_in() ) {
			return '<div class="wpss-notice">' . esc_html__( 'You are already logged in.', 'wp-sell-services' ) . '</div>';
		}

		$atts = shortcode_atts(
			array(
				'redirect' => '',
			),
			$atts,
			'wpss_login'
		);

		$redirect = $atts['redirect'] ?: home_url();

		return wp_login_form(
			array(
				'echo'     => false,
				'redirect' => $redirect,
			)
		);
	}

	/**
	 * Register form shortcode.
	 *
	 * [wpss_register]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function register_form( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		if ( is_user_logged_in() ) {
			return '<div class="wpss-notice">' . esc_html__( 'You are already logged in.', 'wp-sell-services' ) . '</div>';
		}

		if ( ! get_option( 'users_can_register' ) ) {
			return '<div class="wpss-error">' . esc_html__( 'Registration is currently disabled.', 'wp-sell-services' ) . '</div>';
		}

		/*
		 * This used to render its own form: username / email / password, a
		 * wpss_register nonce, method="post" and no action. Nothing anywhere
		 * handled that nonce, and no script bound the form - so submitting it
		 * posted the page back to itself and silently did nothing. It looked
		 * like a working signup and was not.
		 *
		 * PublicSignup::render_form() is the real one. It posts to
		 * wpss_public_signup (registered for nopriv), validates, creates the
		 * user, signs them in and fires wpss_public_signup_complete for Pro -
		 * and it carries its own submit script, so it works wherever it is
		 * rendered. The 'buyer' intent existed from the start and had never
		 * been used: [wpss_vendor_registration] only ever asked for 'vendor'.
		 */
		ob_start();
		?>
		<div class="wpss-card wpss-signup-card">
			<h2 class="wpss-heading-2"><?php esc_html_e( 'Create your account', 'wp-sell-services' ); ?></h2>
			<p class="wpss-caption">
				<?php esc_html_e( 'Buy services, message sellers, and follow your orders.', 'wp-sell-services' ); ?>
			</p>
			<?php ( new \WPSellServices\Frontend\PublicSignup() )->render_form( 'buyer' ); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render vendor profile fallback.
	 *
	 * Takes the profile ROW as VendorService::get_profile() returns it - an
	 * object. It previously declared `array`, matching a get_vendor_profile()
	 * method that never existed, so this could only ever have been reached by
	 * code that already fataled one line earlier.
	 *
	 * `display_name` is not a column on wpss_vendor_profiles; it belongs to the
	 * WP user, so it is resolved through the shared accessor rather than read
	 * off the row.
	 *
	 * @param object $profile   Vendor profile row.
	 * @param int    $vendor_id Vendor ID.
	 * @return void
	 */
	private function render_vendor_profile_fallback( object $profile, int $vendor_id ): void {
		$display_name = wpss_get_member_display_name( $vendor_id );
		$tagline      = (string) ( $profile->tagline ?? '' );
		$bio          = (string) ( $profile->bio ?? '' );
		?>
		<div class="wpss-app-shell"><div class="wpss-app-shell__container">
		<div class="wpss-vendor-profile">
			<div class="wpss-vendor-header">
				<?php echo get_avatar( $vendor_id, 120 ); ?>
				<h1><?php echo esc_html( $display_name ); ?></h1>
				<?php if ( '' !== $tagline ) : ?>
					<p class="wpss-vendor-tagline"><?php echo esc_html( $tagline ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $bio ) : ?>
				<div class="wpss-vendor-bio"><?php echo wp_kses_post( $bio ); ?></div>
			<?php endif; ?>
		</div>
		</div></div>
		<?php
	}

	/**
	 * Render order details fallback.
	 *
	 * @param object $order Order data.
	 * @param int    $user_id Current user ID.
	 * @return void
	 */
	private function render_order_details_fallback( object $order, int $user_id ): void {
		$is_vendor = (int) $order->vendor_id === $user_id;
		?>
		<div class="wpss-order-details">
			<h2><?php printf( esc_html__( 'Order #%s', 'wp-sell-services' ), esc_html( $order->order_number ?: $order->id ) ); ?></h2>
			<div class="wpss-order-status wpss-status-<?php echo esc_attr( $order->status ); ?>">
				<?php echo esc_html( ucwords( str_replace( '_', ' ', $order->status ) ) ); ?>
			</div>
			<div class="wpss-order-info">
				<p><strong><?php esc_html_e( 'Service:', 'wp-sell-services' ); ?></strong> <?php echo esc_html( get_the_title( $order->service_id ) ); ?></p>
				<p><strong><?php esc_html_e( 'Total:', 'wp-sell-services' ); ?></strong> <?php echo wp_kses_post( function_exists( 'wpss_format_currency' ) ? wpss_format_currency( (float) $order->total, $order->currency ) : '$' . number_format( (float) $order->total, 2 ) ); ?></p>
				<p><strong><?php esc_html_e( 'Date:', 'wp-sell-services' ); ?></strong> <?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $order->created_at ) ) ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Vendor registration shortcode.
	 *
	 * Renders a standalone "Become a Vendor" form. If the user is already a vendor,
	 * redirects to the dashboard. If not logged in, shows login prompt.
	 *
	 * @param array $atts Shortcode attributes (unused).
	 * @return string Shortcode HTML.
	 */
	public function vendor_registration( $atts ): string {
		wpss_enqueue_frontend_assets();
		// Provides window.wpssToast for the become-vendor error path below.
		\WPSellServices\Assets\ScriptRegistry::enqueue_ui();

		ob_start();
		$this->render_vendor_registration_styles();

		if ( ! is_user_logged_in() ) {
			?>
			<div class="wpss-vr wpss-vr--pitch">
				<?php $this->render_vendor_pitch_hero(); ?>

				<div class="wpss-vr__body">
					<div class="wpss-vr__pitch">

						<?php
						// One partial, both branches. These bullets used to be
						// written out here and again below, so a copy correction
						// had to be made twice or the page told two different
						// stories depending on who was reading it.
						require WPSS_PLUGIN_DIR . 'templates/partials/vendor-benefits.php';

						$this->render_vendor_pitch_steps();
						?>
					</div>

					<aside class="wpss-vr__action">
						<div class="wpss-vr__card">
							<h2 class="wpss-vr__card-title"><?php esc_html_e( 'Create your seller account', 'wp-sell-services' ); ?></h2>
							<p class="wpss-vr__card-sub"><?php esc_html_e( 'Takes a minute. No card, no listing fee.', 'wp-sell-services' ); ?></p>
							<?php
							// B1 (baseline-2026-04-25.md): inline signup form replaces
							// the previous "Log In / Create Account" buttons that
							// punted visitors to the bare wp-login.php screen.
							( new \WPSellServices\Frontend\PublicSignup() )->render_form( 'vendor' );
							?>
						</div>
					</aside>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$user_id = get_current_user_id();

		// Canonical check, not the raw `_wpss_is_vendor` meta.
		//
		// That meta is a LEGACY marker. Vendors created by role assignment, by an
		// admin, by the demo seeder, or by anything that grants the wpss_vendor
		// role do not carry it — on a normal install that is every vendor. Reading
		// the meta directly therefore answered "not a vendor" for real sellers, and
		// this page offered them "Register as Vendor" while their dashboard,
		// services and earnings all worked (Basecamp 10208142467).
		//
		// wpss_is_vendor() is the one answer: capability, then role, then the
		// legacy meta, then the wpss_is_vendor filter.
		$is_vendor = wpss_is_vendor( $user_id );

		if ( $is_vendor ) {
			$dashboard_url = wpss_get_page_url( 'dashboard' );
			?>
			<div class="wpss-vr">
				<div class="wpss-vr__card">
					<div class="wpss-vr__hero-icon wpss-vr__hero-icon--success">
						<i data-lucide="badge-check" class="wpss-icon" aria-hidden="true"></i>
					</div>
					<h2 class="wpss-vr__title"><?php esc_html_e( 'You\'re already a vendor', 'wp-sell-services' ); ?></h2>
					<p class="wpss-vr__desc"><?php esc_html_e( 'Your vendor account is active. Head to your dashboard to manage services, view orders, and track earnings.', 'wp-sell-services' ); ?></p>
					<div class="wpss-vr__actions">
						<a href="<?php echo esc_url( $dashboard_url ); ?>" class="wpss-vr__btn wpss-vr__btn--primary">
							<?php esc_html_e( 'Go to Dashboard', 'wp-sell-services' ); ?>
						</a>
					</div>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		// Check if registration is open.
		$registration_mode = wpss_get_option( 'vendor', 'vendor_registration' );

		if ( 'closed' === $registration_mode ) {
			?>
			<div class="wpss-vr">
				<div class="wpss-vr__card">
					<div class="wpss-vr__hero-icon wpss-vr__hero-icon--muted">
						<i data-lucide="lock" class="wpss-icon" aria-hidden="true"></i>
					</div>
					<h2 class="wpss-vr__title"><?php esc_html_e( 'Registration is closed', 'wp-sell-services' ); ?></h2>
					<p class="wpss-vr__desc"><?php esc_html_e( 'We\'re not accepting new vendors at the moment. Please check back later.', 'wp-sell-services' ); ?></p>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$approval_required = 'approval' === $registration_mode;
		?>
		<div class="wpss-vr wpss-vr--pitch">
			<?php $this->render_vendor_pitch_hero(); ?>

			<div class="wpss-vr__body">
				<div class="wpss-vr__pitch">
					<?php
					// One partial, both branches. These bullets used to be written
					// out here and again above, so a copy correction had to be made
					// twice or the page told two different stories depending on who
					// was reading it.
					require WPSS_PLUGIN_DIR . 'templates/partials/vendor-benefits.php';

					$this->render_vendor_pitch_steps();
					?>
				</div>

				<aside class="wpss-vr__action">
					<div class="wpss-vr__card">
						<h2 class="wpss-vr__card-title"><?php esc_html_e( 'Start selling', 'wp-sell-services' ); ?></h2>
						<p class="wpss-vr__card-sub">
							<?php
							if ( $approval_required ) {
								esc_html_e( 'Applications are reviewed by our team. You will be notified once approved.', 'wp-sell-services' );
							} else {
								esc_html_e( 'You are signed in already, so this is one click.', 'wp-sell-services' );
							}
							?>
						</p>

						<div class="wpss-vr__actions">
							<button type="button" class="wpss-vr__btn wpss-vr__btn--primary wpss-vr__btn--lg" data-action="become-vendor">
								<?php esc_html_e( 'Register as Vendor', 'wp-sell-services' ); ?>
							</button>
						</div>
					</div>
				</aside>
			</div>
		</div>
		<script>
		(function() {
			var btn = document.querySelector('[data-action="become-vendor"]');
			if ( ! btn ) return;
			var dashboardUrl = <?php echo wp_json_encode( wpss_get_page_url( 'dashboard' ) ?: home_url() ); ?>;

			// window.wpssToast is the canonical notice surface (assets/js/wpss-ui.js),
			// enqueued for this page above. The hand-rolled version this replaced
			// painted its own hex colours inline, so it stayed light-mode red on a
			// dark page.
			function showMessage(msg, type) {
				window.wpssToast(msg, type);
			}

			btn.addEventListener('click', function() {
				btn.disabled = true;
				btn.textContent = <?php echo wp_json_encode( __( 'Processing...', 'wp-sell-services' ) ); ?>;
				var xhr = new XMLHttpRequest();
				xhr.open('POST', <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>);
				xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
				xhr.onload = function() {
					try {
						var r = JSON.parse(xhr.responseText);
						if (r.success) {
							window.location.href = r.data.redirect || dashboardUrl;
						} else {
							showMessage(r.data.message || <?php echo wp_json_encode( __( 'An error occurred.', 'wp-sell-services' ) ); ?>, 'error');
							btn.disabled = false;
							btn.textContent = <?php echo wp_json_encode( __( 'Register as Vendor', 'wp-sell-services' ) ); ?>;
						}
					} catch(e) {
						showMessage(<?php echo wp_json_encode( __( 'An error occurred. Please try again.', 'wp-sell-services' ) ); ?>, 'error');
						btn.disabled = false;
						btn.textContent = <?php echo wp_json_encode( __( 'Register as Vendor', 'wp-sell-services' ) ); ?>;
					}
				};
				xhr.send('action=wpss_become_vendor&nonce=' + <?php echo wp_json_encode( wp_create_nonce( 'wpss_dashboard_nonce' ) ); ?>);
			});
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Hero band for the Become a Vendor page.
	 *
	 * This page has one job - persuade somebody to sell here - and it used to
	 * do it with a ~520px card marooned in a full-width layout: no hero, no
	 * proof, no "what happens next". The hero opens with this marketplace's own
	 * numbers, which is the only social proof we actually have.
	 *
	 * Stats come from wpss_get_vendor_pitch_stats(), which drops any figure
	 * that would argue against us - a new site must not advertise "0 sellers".
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	private function render_vendor_pitch_hero(): void {
		$stats = wpss_get_vendor_pitch_stats();
		?>
		<section class="wpss-vr__hero">
			<p class="wpss-vr__eyebrow"><?php esc_html_e( 'Sell on this marketplace', 'wp-sell-services' ); ?></p>
			<h1 class="wpss-vr__hero-title"><?php esc_html_e( 'Turn what you are good at into income', 'wp-sell-services' ); ?></h1>
			<p class="wpss-vr__hero-sub">
				<?php esc_html_e( 'List a service, set your own prices and delivery times, and get paid once the work is approved. You choose what you take on.', 'wp-sell-services' ); ?>
			</p>
			<?php if ( $stats ) : ?>
				<ul class="wpss-vr__stats">
					<?php foreach ( $stats as $stat ) : ?>
						<li class="wpss-vr__stat">
							<span class="wpss-vr__stat-value"><?php echo esc_html( $stat['value'] ); ?></span>
							<span class="wpss-vr__stat-label"><?php echo esc_html( $stat['label'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * "How it works" steps.
	 *
	 * Somebody deciding whether to sell here wants to know what happens after
	 * they click, and the page answered that with nothing. Numbered because it
	 * genuinely is a sequence, which is the one case where numbering carries
	 * information rather than decoration.
	 *
	 * @since 1.7.0
	 *
	 * @return void
	 */
	private function render_vendor_pitch_steps(): void {
		$steps = array(
			array(
				'title' => __( 'Create your seller profile', 'wp-sell-services' ),
				'text'  => __( 'Say who you are and what you do. It is what buyers read before they order.', 'wp-sell-services' ),
			),
			array(
				'title' => __( 'Publish your first service', 'wp-sell-services' ),
				'text'  => __( 'Describe the work, set your packages and delivery time, and ask for anything you need from the buyer up front.', 'wp-sell-services' ),
			),
			array(
				'title' => __( 'Deliver and get paid', 'wp-sell-services' ),
				'text'  => __( 'Talk to the buyer on the order, deliver your work, and withdraw your earnings once it is approved.', 'wp-sell-services' ),
			),
		);

		/**
		 * Filter the "how it works" steps on the vendor registration page.
		 *
		 * @since 1.7.0
		 *
		 * @param array $steps Ordered steps, each with a `title` and `text`.
		 */
		$steps = (array) apply_filters( 'wpss_vendor_pitch_steps', $steps );

		if ( ! $steps ) {
			return;
		}
		?>
		<section class="wpss-vr__steps">
			<h2 class="wpss-vr__section-title"><?php esc_html_e( 'How it works', 'wp-sell-services' ); ?></h2>
			<ol class="wpss-vr__step-list">
				<?php foreach ( array_values( $steps ) as $index => $step ) : ?>
					<li class="wpss-vr__step">
						<span class="wpss-vr__step-num" aria-hidden="true"><?php echo esc_html( number_format_i18n( $index + 1 ) ); ?></span>
						<span class="wpss-vr__step-body">
							<strong><?php echo esc_html( $step['title'] ); ?></strong>
							<span><?php echo esc_html( $step['text'] ); ?></span>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
		</section>
		<?php
	}

	/**
	 * Render self-contained CSS for vendor registration shortcode.
	 *
	 * Uses a static flag to only output once per page.
	 *
	 * @return void
	 */
	private function render_vendor_registration_styles(): void {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;
		?>
		<style>
		/* B2 (baseline-2026-04-25.md): position:relative + isolation:isolate
			+ contain:layout creates a stacking context so any sticky/fixed
			elements from the host theme cannot bleed into the card. Same
			fix the wpss-app-shell primitive applies on bigger frontend
			surfaces (dashboard, single service, archive). */
		.wpss-vr { max-width: 560px; margin: 40px auto; padding: 0 20px; position: relative; isolation: isolate; contain: layout; }
		.wpss-vr__card--wide { max-width: 640px; }
		.wpss-vr__card {
			background: var(--wpss-white, #fff);
			border: 1px solid var(--wpss-border, #e5e7eb);
			border-radius: 16px;
			padding: 48px 40px;
			text-align: center;
			box-shadow: 0 1px 3px rgba(0,0,0,0.04);
		}
		/* Hero icon container — Lucide via <i data-lucide> at 48x48. */
		.wpss-vr__hero-icon {
			display: inline-flex; align-items: center; justify-content: center;
			width: 64px; height: 64px;
			margin: 0 auto 16px;
			border-radius: 16px;
			background: var(--wpss-primary-light, #eef2ff);
			color: var(--wpss-primary, #4f46e5);
		}
		.wpss-vr__hero-icon .wpss-icon { width: 32px; height: 32px; }
		.wpss-vr__hero-icon--success { background: var(--wpss-success-border, #dcfce7); color: var(--wpss-success, #16a34a); }
		.wpss-vr__hero-icon--muted { background: var(--wpss-bg-muted, #f3f4f6); color: var(--wpss-text-muted, #6b7280); }
		.wpss-vr__title {
			font-size: 24px; font-weight: 700; color: var(--wpss-text, #111827);
			margin: 0 0 12px; line-height: 1.3;
		}
		.wpss-vr__desc {
			font-size: 15px; color: var(--wpss-text-muted, #6b7280); line-height: 1.6;
			margin: 0 0 32px; max-width: 440px; margin-left: auto; margin-right: auto;
		}
		.wpss-vr__features {
			display: flex; flex-direction: column; gap: 16px;
			text-align: left; margin-bottom: 32px;
			background: var(--wpss-bg-subtle, #f9fafb); border-radius: 12px; padding: 24px;
		}
		.wpss-vr__feature {
			display: flex; align-items: flex-start; gap: 14px;
		}
		.wpss-vr__feature-icon {
			display: inline-flex; align-items: center; justify-content: center;
			width: 32px; height: 32px;
			flex-shrink: 0; margin-top: 2px;
			border-radius: 8px;
			background: var(--wpss-primary-light, #eef2ff);
			color: var(--wpss-primary, #4f46e5);
		}
		.wpss-vr__feature-icon .wpss-icon { width: 18px; height: 18px; }
		.wpss-vr__feature strong {
			display: block; font-size: 14px; font-weight: 600; color: var(--wpss-text, #111827); margin-bottom: 2px;
		}
		.wpss-vr__feature span { font-size: 13px; color: var(--wpss-text-muted, #6b7280); line-height: 1.4; }
		.wpss-vr__note {
			background: var(--wpss-badge-extension-bg, #fef3c7); color: var(--wpss-warning-dark, #92400e); padding: 12px 16px;
			border-radius: 8px; font-size: 13px; margin-bottom: 24px;
		}
		.wpss-vr__actions { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
		.wpss-vr__btn {
			display: inline-flex; align-items: center; justify-content: center;
			padding: 12px 28px; font-size: 15px; font-weight: 600;
			border-radius: 10px; text-decoration: none; cursor: pointer;
			transition: all 0.15s ease; border: 2px solid transparent;
		}
		.wpss-vr__btn--primary {
			background: var(--wpss-primary, #4f46e5); color: var(--wpss-white, #fff); border-color: var(--wpss-primary, #4f46e5);
		}
		.wpss-vr__btn--primary:hover { background: var(--wpss-primary-dark, #4338ca); border-color: var(--wpss-primary-dark, #4338ca); color: var(--wpss-white, #fff); }
		.wpss-vr__btn--outline {
			background: transparent; color: var(--wpss-text-secondary, #374151); border-color: var(--wpss-gray-300, #d1d5db);
		}
		.wpss-vr__btn--outline:hover { border-color: var(--wpss-text-hint, #9ca3af); color: var(--wpss-text, #111827); }
		.wpss-vr__btn--lg { padding: 14px 36px; font-size: 16px; }

		/* Inline public signup form (B1 from baseline-2026-04-25.md). */
		.wpss-signup-form { text-align: left; max-width: 440px; margin: 0 auto; }
		.wpss-signup-form .wpss-form-group { margin-bottom: 16px; }
		.wpss-signup-form .wpss-form-label { display: block; font-size: 13px; font-weight: 600; color: var(--wpss-text-secondary, #374151); margin-bottom: 6px; }
		.wpss-signup-form .wpss-form-input {
			width: 100%; padding: 10px 14px; font-size: 14px;
			border: 1px solid var(--wpss-gray-300, #d1d5db); border-radius: 8px;
			background: var(--wpss-white, #fff); color: var(--wpss-text, #111827);
			transition: border-color 0.15s ease, box-shadow 0.15s ease;
			box-sizing: border-box;
		}
		.wpss-signup-form .wpss-form-input:focus {
			outline: none; border-color: var(--wpss-primary, #4f46e5);
			box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
		}
		.wpss-signup-form .wpss-form-hint { font-size: 12px; color: var(--wpss-text-muted, #6b7280); margin: 4px 0 0; }
		.wpss-signup-form .wpss-required { color: var(--wpss-danger, #ef4444); }
		.wpss-signup-form__submit {
			display: block; width: 100%; padding: 14px 24px;
			font-size: 15px; font-weight: 600;
			background: var(--wpss-primary, #4f46e5); color: var(--wpss-white, #fff);
			border: 0; border-radius: 10px; cursor: pointer;
			transition: background-color 0.15s ease;
			margin-top: 8px;
		}
		.wpss-signup-form__submit:hover:not(:disabled) { background: var(--wpss-primary-dark, #4338ca); }
		.wpss-signup-form__submit:disabled { opacity: 0.6; cursor: not-allowed; }
		.wpss-signup-form__signin {
			text-align: center; font-size: 13px; color: var(--wpss-text-muted, #6b7280);
			margin: 16px 0 0; padding-top: 16px; border-top: 1px solid var(--wpss-border, #e5e7eb);
		}
		.wpss-signup-form__signin a { color: var(--wpss-primary, #4f46e5); font-weight: 600; text-decoration: none; }
		.wpss-signup-form__signin a:hover { text-decoration: underline; }

		/* Pitch layout: hero + two columns. The narrow single-card branches
			(already a vendor, registration closed) keep the 560px width above. */
		.wpss-vr--pitch { max-width: 1080px; }
		.wpss-vr__hero { text-align: center; margin-bottom: 40px; }
		.wpss-vr__eyebrow {
			font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
			color: var(--wpss-primary, #4f46e5); margin: 0 0 12px;
		}
		.wpss-vr__hero-title {
			font-size: clamp(28px, 4vw, 42px); font-weight: 700; line-height: 1.15;
			color: var(--wpss-text, #111827); margin: 0 0 16px; text-wrap: balance;
		}
		.wpss-vr__hero-sub {
			font-size: 17px; line-height: 1.6; color: var(--wpss-text-muted, #6b7280);
			max-width: 620px; margin: 0 auto;
		}
		.wpss-vr__stats {
			display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;
			list-style: none; margin: 32px 0 0; padding: 0;
		}
		.wpss-vr__stat {
			display: flex; flex-direction: column; gap: 4px;
			flex: 1 1 180px; max-width: 260px;
			padding: 18px 20px; border-radius: 12px;
			background: var(--wpss-bg-subtle, #f9fafb);
			border: 1px solid var(--wpss-border, #e5e7eb);
		}
		.wpss-vr__stat-value {
			font-size: 24px; font-weight: 700; color: var(--wpss-text, #111827);
			font-variant-numeric: tabular-nums;
		}
		.wpss-vr__stat-label { font-size: 13px; color: var(--wpss-text-muted, #6b7280); line-height: 1.4; }

		.wpss-vr__body { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 400px); gap: 32px; align-items: start; }
		.wpss-vr__pitch { display: flex; flex-direction: column; gap: 32px; }
		/* The shared benefits partial centres itself in the narrow branches. */
		.wpss-vr__pitch .wpss-vr__features { margin-bottom: 0; }
		.wpss-vr__section-title {
			font-size: 18px; font-weight: 700; color: var(--wpss-text, #111827); margin: 0 0 16px;
		}
		.wpss-vr__step-list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 20px; }
		.wpss-vr__step { display: flex; align-items: flex-start; gap: 14px; }
		.wpss-vr__step-num {
			display: inline-flex; align-items: center; justify-content: center;
			width: 32px; height: 32px; flex-shrink: 0;
			border-radius: 50%; font-size: 14px; font-weight: 700;
			background: var(--wpss-primary-light, #eef2ff); color: var(--wpss-primary, #4f46e5);
			font-variant-numeric: tabular-nums;
		}
		.wpss-vr__step-body { display: flex; flex-direction: column; gap: 2px; }
		.wpss-vr__step-body strong { font-size: 15px; font-weight: 600; color: var(--wpss-text, #111827); }
		.wpss-vr__step-body span { font-size: 14px; color: var(--wpss-text-muted, #6b7280); line-height: 1.5; }

		.wpss-vr__action { position: sticky; top: 24px; }
		.wpss-vr--pitch .wpss-vr__card { padding: 32px 28px; text-align: start; }
		.wpss-vr__card-title { font-size: 20px; font-weight: 700; color: var(--wpss-text, #111827); margin: 0 0 6px; }
		.wpss-vr__card-sub { font-size: 14px; color: var(--wpss-text-muted, #6b7280); line-height: 1.5; margin: 0 0 24px; }
		.wpss-vr--pitch .wpss-signup-form { max-width: none; }
		.wpss-vr--pitch .wpss-vr__actions { justify-content: stretch; }
		.wpss-vr--pitch .wpss-vr__actions .wpss-vr__btn { width: 100%; }

		@media (max-width: 900px) {
			.wpss-vr__body { grid-template-columns: minmax(0, 1fr); }
			/* Sticky is pointless once stacked - and the sign-up card leads,
				because stacked behind the full pitch it sat below three
				screenfuls of scroll. */
			.wpss-vr__action { position: static; order: -1; }
		}

		@media (max-width: 480px) {
			.wpss-vr__card { padding: 32px 24px; }
			.wpss-vr__actions { flex-direction: column; }
			.wpss-vr__btn { width: 100%; }
			.wpss-vr__stat { flex-basis: 100%; max-width: none; }
		}
		</style>
		<?php
	}

	/**
	 * Cart page shortcode.
	 *
	 * [wpss_cart]
	 *
	 * Renders the standalone cart page. If WooCommerce is the active adapter,
	 * redirects to the WooCommerce cart page instead.
	 *
	 * @since 1.6.0
	 *
	 * @param array $atts Shortcode attributes (unused).
	 * @return string
	 */
	public function cart_page( array $atts = array() ): string {
		wpss_enqueue_frontend_assets();

		// Defence in depth. The mapped cart PAGE is redirected on
		// template_redirect (see Plugin::redirect_dormant_store_pages()), which
		// is the only point at which a header can still be sent; this branch
		// catches the shortcode pasted onto some other page. It runs inside
		// the_content, so by now the theme has emitted the document head —
		// wp_safe_redirect() would be refused on any host without output
		// buffering, and the visitor would be left with a truncated page. Fall
		// back to a real link when the headers have already gone out.
		$adapter = wpss_get_active_adapter();
		if ( $adapter && 'standalone' !== $adapter->get_id() ) {
			$target = wpss_get_cart_url();

			// Never bounce a page at itself: a rail that resolves back to this
			// very page (misconfigured, or mapped onto the WPSS page) would
			// otherwise loop forever.
			if ( '' !== $target && (int) url_to_postid( $target ) !== get_queried_object_id() ) {
				if ( ! headers_sent() ) {
					wp_safe_redirect( $target );
					exit;
				}

				return self::cart_heading()
					. '<div class="wpss-cart-redirect"><p>'
					. wp_kses_post(
						sprintf(
							/* translators: %s: cart page link */
							__( 'Your cart is handled by the store. <a href="%s">Go to cart</a>.', 'wp-sell-services' ),
							esc_url( $target )
						)
					)
					. '</p></div>';
			}
		}

		if ( ! is_user_logged_in() ) {
			return self::cart_heading()
				. '<p class="wpss-alert">' . esc_html__( 'Please log in to view your cart.', 'wp-sell-services' ) . '</p>';
		}

		$cart_items = get_user_meta( get_current_user_id(), '_wpss_cart', true );
		if ( ! is_array( $cart_items ) ) {
			$cart_items = array();
		}

		ob_start();
		wpss_get_template( 'cart/cart.php', array( 'cart_items' => $cart_items ) );
		return ob_get_clean();
	}

	/**
	 * The cart page's heading, for the branches that render no template.
	 *
	 * The cart page is a plugin-shell surface, which means ShellHeader suppresses
	 * the theme's own <h1> in favour of the plugin's. templates/cart/cart.php
	 * prints one - but the logged-out branch and the "another rail owns the cart"
	 * branch return before it, so those two ended up with NO heading at all once
	 * suppression started working. A page with zero H1s is worse than the
	 * duplicate that was reported (Basecamp 10208511245).
	 *
	 * Same wording as the template's heading, so the page reads identically
	 * whichever branch runs.
	 *
	 * @since 1.6.0
	 *
	 * @return string Header markup.
	 */
	private static function cart_heading(): string {
		return \WPSellServices\Frontend\ShellHeader::render(
			array(
				'title' => __( 'Your Cart', 'wp-sell-services' ),
				'echo'  => false,
			)
		);
	}

	/**
	 * Checkout fallback when the active e-commerce adapter does not register wpss_checkout.
	 *
	 * When WooCommerce or another adapter handles checkout, this shortcode redirects
	 * to the adapter's checkout page instead of rendering raw shortcode text.
	 *
	 * @return string
	 */
	public function checkout_fallback(): string {
		wpss_enqueue_frontend_assets();

		// When another rail owns the store, this page is a dead standalone
		// funnel — send the buyer to the rail that can actually take money.
		//
		// Asked through the adapter rather than by re-reading
		// `wpss_general['ecommerce_platform']` and hard-coding WooCommerce: the
		// adapter is the one thing that already knows which rail resolved
		// (including 'auto'), and EDD / SureCart / FluentCart sites had exactly
		// the same dead page with no branch to catch them.
		//
		// Defence in depth only — the mapped checkout PAGE is redirected on
		// template_redirect, before a byte of the theme is emitted (see
		// Plugin::redirect_dormant_store_pages()).
		$adapter = wpss_get_active_adapter();

		if ( $adapter && 'standalone' !== $adapter->get_id() ) {
			// `?pay_order=N` is the standalone way to pay one order, and we
			// have already emailed those links to buyers. Resolve the id
			// through the shared seam so an old link lands on that order's
			// real payment page instead of a generic (empty) checkout.
			$pay_order = isset( $_GET['pay_order'] ) ? absint( wp_unslash( $_GET['pay_order'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect of a public link.

			$target = $pay_order > 0 && function_exists( 'wpss_ensure_pay_order' )
				? wpss_ensure_pay_order( $pay_order )
				: wpss_get_checkout_base_url();

			// Never bounce a page at itself — see cart_page().
			if ( '' !== $target && (int) url_to_postid( $target ) !== get_queried_object_id() ) {
				if ( ! headers_sent() ) {
					wp_safe_redirect( $target );
					exit;
				}

				return '<div class="wpss-checkout-redirect"><p>'
					. wp_kses_post(
						sprintf(
							/* translators: %s: checkout page link */
							__( 'Checkout is handled by the store. <a href="%s">Go to checkout</a>.', 'wp-sell-services' ),
							esc_url( $target )
						)
					)
					. '</p></div>';
			}
		}

		// For any other adapter or misconfigured state.
		return '<div class="wpss-checkout-notice"><p>'
			. __( 'Checkout is not available. Please configure an e-commerce platform in settings.', 'wp-sell-services' )
			. '</p></div>';
	}
}
