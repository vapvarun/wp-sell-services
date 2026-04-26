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
use WPSellServices\Services\BuyerRequestService;

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

		$args = array(
			'post_type'      => 'wpss_service',
			'post_status'    => 'publish',
			'posts_per_page' => absint( $atts['limit'] ),
			'orderby'        => $atts['orderby'],
			'order'          => $atts['order'],
		);

		// Category filter.
		if ( $atts['category'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'wpss_service_category',
					'field'    => is_numeric( $atts['category'] ) ? 'term_id' : 'slug',
					'terms'    => $atts['category'],
				),
			);
		}

		// Tag filter.
		if ( $atts['tag'] ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'wpss_service_tag',
				'field'    => is_numeric( $atts['tag'] ) ? 'term_id' : 'slug',
				'terms'    => $atts['tag'],
			);
		}

		// Vendor filter.
		if ( $atts['vendor'] ) {
			$args['author'] = absint( $atts['vendor'] );
		}

		// Featured filter.
		if ( 'true' === $atts['featured'] || '1' === $atts['featured'] ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_wpss_is_featured',
					'value' => '1',
				),
			);
		}

		// Custom ordering.
		if ( 'rating' === $atts['orderby'] ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_wpss_rating_average';
		} elseif ( 'sales' === $atts['orderby'] ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_wpss_total_sales';
		} elseif ( 'price' === $atts['orderby'] ) {
			$args['orderby']  = 'meta_value_num';
			$args['meta_key'] = '_wpss_starting_price';
		}

		$query = new \WP_Query( $args );

		ob_start();
		?>
		<div class="wpss-services-grid wpss-columns-<?php echo esc_attr( $atts['columns'] ); ?>">
			<?php
			if ( $query->have_posts() ) :
				while ( $query->have_posts() ) :
					$query->the_post();
					$this->render_service_card( get_the_ID() );
				endwhile;
				wp_reset_postdata();
			else :
				?>
				<p class="wpss-no-results"><?php esc_html_e( 'No services found.', 'wp-sell-services' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
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
				'placeholder'     => __( 'Search services...', 'wp-sell-services' ),
				'show_categories' => 'true',
				'button_text'     => __( 'Search', 'wp-sell-services' ),
				'action'          => '',
			),
			$atts,
			'wpss_service_search'
		);

		$action = $atts['action'] ?: get_post_type_archive_link( 'wpss_service' );

		ob_start();
		?>
		<form class="wpss-search-form" action="<?php echo esc_url( $action ); ?>" method="get">
			<div class="wpss-search-fields">
				<input type="text" name="s" class="wpss-search-input" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>">
				<input type="hidden" name="post_type" value="wpss_service">

				<?php if ( 'true' === $atts['show_categories'] ) : ?>
					<select name="service_category" class="wpss-search-category">
						<option value=""><?php esc_html_e( 'All Categories', 'wp-sell-services' ); ?></option>
						<?php
						$categories = get_terms(
							array(
								'taxonomy'   => 'wpss_service_category',
								'hide_empty' => true,
								'parent'     => 0,
							)
						);

						if ( ! is_wp_error( $categories ) ) :
							foreach ( $categories as $category ) :
								?>
								<option value="<?php echo esc_attr( $category->slug ); ?>"><?php echo esc_html( $category->name ); ?></option>
								<?php
							endforeach;
						endif;
						?>
					</select>
				<?php endif; ?>

				<button type="submit" class="wpss-search-button"><?php echo esc_html( $atts['button_text'] ); ?></button>
			</div>
		</form>
		<?php
		return ob_get_clean();
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

		ob_start();
		?>
		<div class="wpss-categories-grid wpss-columns-<?php echo esc_attr( $atts['columns'] ); ?>">
			<?php foreach ( $categories as $category ) : ?>
				<?php
				$icon  = get_term_meta( $category->term_id, '_wpss_icon', true );
				$image = get_term_meta( $category->term_id, '_wpss_image', true );
				?>
				<a href="<?php echo esc_url( get_term_link( $category ) ); ?>" class="wpss-category-card">
					<?php if ( $image ) : ?>
						<div class="wpss-category-image">
							<?php echo wp_get_attachment_image( $image, 'medium' ); ?>
						</div>
					<?php elseif ( $icon ) : ?>
						<div class="wpss-category-icon">
							<span class="<?php echo esc_attr( $icon ); ?>"></span>
						</div>
					<?php endif; ?>
					<h3 class="wpss-category-name"><?php echo esc_html( $category->name ); ?></h3>
					<?php if ( 'true' === $atts['show_count'] ) : ?>
						<span class="wpss-category-count">
							<?php
							printf(
								/* translators: %d: service count */
								esc_html( _n( '%d service', '%d services', $category->count, 'wp-sell-services' ) ),
								(int) $category->count
							);
							?>
						</span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
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

		$atts = shortcode_atts(
			array(
				'limit'   => 12,
				'columns' => 4,
				'orderby' => 'rating',
				'order'   => 'DESC',
			),
			$atts,
			'wpss_vendors'
		);

		$vendor_service = new VendorService();
		$vendors        = $vendor_service->get_all(
			array(
				'limit'   => absint( $atts['limit'] ),
				'orderby' => $atts['orderby'],
				'order'   => $atts['order'],
			)
		);

		ob_start();
		?>
		<div class="wpss-vendors-grid wpss-columns-<?php echo esc_attr( $atts['columns'] ); ?>">
			<?php
			if ( ! empty( $vendors ) ) :
				foreach ( $vendors as $vendor ) :
					$this->render_vendor_card( $vendor );
				endforeach;
			else :
				?>
				<p class="wpss-no-results"><?php esc_html_e( 'No vendors found.', 'wp-sell-services' ); ?></p>
			<?php endif; ?>
		</div>
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

		$vendor_service = new VendorService();
		$profile        = $vendor_service->get_vendor_profile( $vendor_id );

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

		$request_service = new BuyerRequestService();
		$args            = array(
			'posts_per_page' => absint( $atts['limit'] ),
		);

		if ( $atts['category'] ) {
			$args['category_id'] = absint( $atts['category'] );
		}

		if ( $atts['budget_min'] ) {
			$args['budget_min'] = floatval( $atts['budget_min'] );
		}

		if ( $atts['budget_max'] ) {
			$args['budget_max'] = floatval( $atts['budget_max'] );
		}

		$requests = $request_service->get_open( $args );

		ob_start();
		?>
		<div class="wpss-app-shell"><div class="wpss-app-shell__container">
		<div class="wpss-buyer-requests">
			<?php
			if ( ! empty( $requests ) ) :
				foreach ( $requests as $request ) :
					$this->render_request_card( $request );
				endforeach;
			else :
				?>
				<p class="wpss-no-results"><?php esc_html_e( 'No buyer requests found.', 'wp-sell-services' ); ?></p>
			<?php endif; ?>
		</div>
		</div></div>
		<?php
		return ob_get_clean();
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

		ob_start();
		?>
		<form id="wpss-post-request-form" class="wpss-form">
			<?php wp_nonce_field( 'wpss_post_request', 'wpss_request_nonce' ); ?>

			<div class="wpss-form-row">
				<label for="request_title"><?php esc_html_e( 'Title', 'wp-sell-services' ); ?> <span class="required">*</span></label>
				<input type="text" name="title" id="request_title" required maxlength="100" placeholder="<?php esc_attr_e( 'e.g., I need a WordPress website designed', 'wp-sell-services' ); ?>">
			</div>

			<div class="wpss-form-row">
				<label for="request_description"><?php esc_html_e( 'Description', 'wp-sell-services' ); ?> <span class="required">*</span></label>
				<textarea name="description" id="request_description" rows="5" required placeholder="<?php esc_attr_e( 'Describe what you need in detail...', 'wp-sell-services' ); ?>"></textarea>
			</div>

			<div class="wpss-form-row">
				<label for="request_category"><?php esc_html_e( 'Category', 'wp-sell-services' ); ?></label>
				<select name="category" id="request_category">
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
							<option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
							<?php
						endforeach;
					endif;
					?>
				</select>
			</div>

			<div class="wpss-form-row wpss-form-row-double">
				<div class="wpss-form-col">
					<label for="request_budget_min"><?php esc_html_e( 'Budget Min', 'wp-sell-services' ); ?></label>
					<input type="number" name="budget_min" id="request_budget_min" min="0" step="0.01" placeholder="0">
				</div>
				<div class="wpss-form-col">
					<label for="request_budget_max"><?php esc_html_e( 'Budget Max', 'wp-sell-services' ); ?></label>
					<input type="number" name="budget_max" id="request_budget_max" min="0" step="0.01" placeholder="0">
				</div>
			</div>

			<div class="wpss-form-row">
				<label for="request_deadline"><?php esc_html_e( 'Deadline', 'wp-sell-services' ); ?></label>
				<input type="date" name="deadline" id="request_deadline" min="<?php echo esc_attr( wp_date( 'Y-m-d', strtotime( '+1 day' ) ) ); ?>">
			</div>

			<div class="wpss-form-row">
				<label for="request_skills"><?php esc_html_e( 'Required Skills', 'wp-sell-services' ); ?></label>
				<input type="text" name="skills_required" id="request_skills" placeholder="<?php esc_attr_e( 'e.g., WordPress, PHP, JavaScript (comma-separated)', 'wp-sell-services' ); ?>">
				<p class="wpss-form-hint"><?php esc_html_e( 'Separate multiple skills with commas.', 'wp-sell-services' ); ?></p>
			</div>

			<div class="wpss-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Post Request', 'wp-sell-services' ); ?></button>
			</div>
		</form>
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

		global $wpdb;

		$user_id      = get_current_user_id();
		$orders_table = $wpdb->prefix . 'wpss_orders';

		$where  = array();
		$params = array();

		if ( 'vendor' === $atts['type'] ) {
			$where[] = 'vendor_id = %d';
		} else {
			$where[] = 'customer_id = %d';
		}
		$params[] = $user_id;

		if ( $atts['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $atts['status'];
		}

		$params[] = absint( $atts['limit'] );

		$orders = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$orders_table}
				WHERE " . implode( ' AND ', $where ) . '
				ORDER BY created_at DESC
				LIMIT %d',
				$params
			)
		);

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
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $order->created_at ) ) ); ?></td>
								<td><a href="<?php echo esc_url( wpss_get_dashboard_url( 'orders' ) ? add_query_arg( 'order_id', $order->id, wpss_get_dashboard_url() ) : '#' ); ?>" class="button button-small"><?php esc_html_e( 'View', 'wp-sell-services' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<div class="wpss-empty-state">
					<div class="wpss-empty-state__icon">
						<?php
						echo \WPSellServices\Services\Icon::render(
							'shopping-bag',
							array(
								'width'  => '48',
								'height' => '48',
							)
						); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
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

		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

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

		$template = locate_template( 'wp-sell-services/order/details.php' );
		if ( ! $template ) {
			$template = WPSS_PLUGIN_DIR . 'templates/order/details.php';
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

		ob_start();
		?>
		<form id="wpss-register-form" class="wpss-form" method="post">
			<?php wp_nonce_field( 'wpss_register', 'wpss_register_nonce' ); ?>

			<div class="wpss-form-row">
				<label for="register_username"><?php esc_html_e( 'Username', 'wp-sell-services' ); ?> <span class="required">*</span></label>
				<input type="text" name="username" id="register_username" required>
			</div>

			<div class="wpss-form-row">
				<label for="register_email"><?php esc_html_e( 'Email', 'wp-sell-services' ); ?> <span class="required">*</span></label>
				<input type="email" name="email" id="register_email" required>
			</div>

			<div class="wpss-form-row">
				<label for="register_password"><?php esc_html_e( 'Password', 'wp-sell-services' ); ?> <span class="required">*</span></label>
				<input type="password" name="password" id="register_password" required minlength="8">
			</div>

			<div class="wpss-form-actions">
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Register', 'wp-sell-services' ); ?></button>
			</div>

			<p class="wpss-form-link">
				<?php esc_html_e( 'Already have an account?', 'wp-sell-services' ); ?>
				<a href="<?php echo esc_url( wp_login_url() ); ?>"><?php esc_html_e( 'Log in', 'wp-sell-services' ); ?></a>
			</p>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render service card.
	 *
	 * @param int $service_id Service post ID.
	 * @return void
	 */
	private function render_service_card( int $service_id ): void {
		$template = locate_template( 'wp-sell-services/content-service-card.php' );
		if ( ! $template ) {
			$template = WPSS_PLUGIN_DIR . 'templates/content-service-card.php';
		}

		if ( file_exists( $template ) ) {
			include $template;
		} else {
			// Fallback rendering.
			$price  = get_post_meta( $service_id, '_wpss_starting_price', true );
			$rating = get_post_meta( $service_id, '_wpss_rating_average', true );
			$vendor = get_userdata( get_post_field( 'post_author', $service_id ) );
			?>
			<div class="wpss-service-card">
				<?php if ( has_post_thumbnail( $service_id ) ) : ?>
					<a href="<?php echo esc_url( get_permalink( $service_id ) ); ?>" class="wpss-service-thumbnail">
						<?php echo get_the_post_thumbnail( $service_id, 'medium' ); ?>
					</a>
				<?php endif; ?>
				<div class="wpss-service-info">
					<div class="wpss-service-vendor">
						<?php echo get_avatar( $vendor->ID, 24 ); ?>
						<span><?php echo esc_html( $vendor->display_name ); ?></span>
					</div>
					<h3 class="wpss-service-title">
						<a href="<?php echo esc_url( get_permalink( $service_id ) ); ?>"><?php echo esc_html( get_the_title( $service_id ) ); ?></a>
					</h3>
					<div class="wpss-service-meta">
						<?php if ( $rating ) : ?>
							<span class="wpss-service-rating"><?php echo esc_html( number_format( (float) $rating, 1 ) ); ?> ★</span>
						<?php endif; ?>
						<span class="wpss-service-price"><?php esc_html_e( 'From', 'wp-sell-services' ); ?> <?php echo wp_kses_post( function_exists( 'wpss_format_currency' ) ? wpss_format_currency( (float) $price ) : '$' . number_format( (float) $price, 2 ) ); ?></span>
					</div>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Render vendor card.
	 *
	 * @param array $vendor Vendor data.
	 * @return void
	 */
	private function render_vendor_card( array $vendor ): void {
		?>
		<div class="wpss-vendor-card">
			<div class="wpss-vendor-avatar">
				<?php echo get_avatar( $vendor['id'], 80 ); ?>
			</div>
			<div class="wpss-vendor-info">
				<h3 class="wpss-vendor-name">
					<a href="<?php echo esc_url( wpss_get_vendor_url( $vendor['id'] ) ); ?>">
						<?php echo esc_html( $vendor['display_name'] ); ?>
					</a>
				</h3>
				<?php if ( ! empty( $vendor['tagline'] ) ) : ?>
					<p class="wpss-vendor-tagline"><?php echo esc_html( $vendor['tagline'] ); ?></p>
				<?php endif; ?>
				<div class="wpss-vendor-meta">
					<?php if ( $vendor['rating'] ) : ?>
						<span class="wpss-vendor-rating"><?php echo esc_html( number_format( (float) $vendor['rating'], 1 ) ); ?> ★ (<?php echo esc_html( $vendor['review_count'] ); ?>)</span>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render request card.
	 *
	 * @param object $request Request data.
	 * @return void
	 */
	private function render_request_card( object $request ): void {
		$buyer = get_userdata( $request->author_id ?? $request->post_author ?? 0 );
		?>
		<div class="wpss-request-card">
			<div class="wpss-request-header">
				<div class="wpss-request-buyer">
					<?php echo get_avatar( $buyer->ID, 40 ); ?>
					<span><?php echo esc_html( $buyer->display_name ); ?></span>
				</div>
				<span class="wpss-request-date"><?php echo esc_html( human_time_diff( strtotime( $request->created_at ?? $request->post_date ), current_time( 'timestamp' ) ) ); ?> <?php esc_html_e( 'ago', 'wp-sell-services' ); ?></span>
			</div>
			<h3 class="wpss-request-title">
				<a href="<?php echo esc_url( get_permalink( $request->ID ?? $request->id ?? 0 ) ); ?>">
					<?php echo esc_html( $request->title ?? $request->post_title ); ?>
				</a>
			</h3>
			<p class="wpss-request-excerpt"><?php echo esc_html( wp_trim_words( $request->description ?? $request->post_content, 30 ) ); ?></p>
			<div class="wpss-request-meta">
				<span class="wpss-request-budget">
					<?php
					$min = $request->budget_min ?? 0;
					$max = $request->budget_max ?? 0;
					if ( $min && $max ) {
						echo esc_html( sprintf( '$%s - $%s', number_format( (float) $min ), number_format( (float) $max ) ) );
					} elseif ( $max ) {
						echo esc_html( sprintf( __( 'Up to $%s', 'wp-sell-services' ), number_format( (float) $max ) ) );
					} else {
						esc_html_e( 'Open budget', 'wp-sell-services' );
					}
					?>
				</span>
				<span class="wpss-request-proposals"><?php echo esc_html( $request->proposal_count ?? 0 ); ?> <?php esc_html_e( 'proposals', 'wp-sell-services' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render vendor profile fallback.
	 *
	 * @param array $profile Vendor profile data.
	 * @param int   $vendor_id Vendor ID.
	 * @return void
	 */
	private function render_vendor_profile_fallback( array $profile, int $vendor_id ): void {
		?>
		<div class="wpss-app-shell"><div class="wpss-app-shell__container">
		<div class="wpss-vendor-profile">
			<div class="wpss-vendor-header">
				<?php echo get_avatar( $vendor_id, 120 ); ?>
				<h1><?php echo esc_html( $profile['display_name'] ); ?></h1>
				<?php if ( ! empty( $profile['tagline'] ) ) : ?>
					<p class="wpss-vendor-tagline"><?php echo esc_html( $profile['tagline'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $profile['bio'] ) ) : ?>
				<div class="wpss-vendor-bio"><?php echo wp_kses_post( $profile['bio'] ); ?></div>
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

		ob_start();
		$this->render_vendor_registration_styles();

		if ( ! is_user_logged_in() ) {
			?>
			<div class="wpss-vr">
				<div class="wpss-vr__card wpss-vr__card--wide">
					<div class="wpss-vr__hero-icon">
						<i data-lucide="rocket" class="wpss-icon" aria-hidden="true"></i>
					</div>
					<h2 class="wpss-vr__title"><?php esc_html_e( 'Start selling your services', 'wp-sell-services' ); ?></h2>
					<p class="wpss-vr__desc"><?php esc_html_e( 'Create your vendor account in seconds. No credit card required.', 'wp-sell-services' ); ?></p>

					<div class="wpss-vr__features">
						<div class="wpss-vr__feature">
							<span class="wpss-vr__feature-icon">
								<i data-lucide="palette" class="wpss-icon" aria-hidden="true"></i>
							</span>
							<div>
								<strong><?php esc_html_e( 'Create Services', 'wp-sell-services' ); ?></strong>
								<span><?php esc_html_e( 'Build unlimited service listings with custom packages', 'wp-sell-services' ); ?></span>
							</div>
						</div>
						<div class="wpss-vr__feature">
							<span class="wpss-vr__feature-icon">
								<i data-lucide="wallet" class="wpss-icon" aria-hidden="true"></i>
							</span>
							<div>
								<strong><?php esc_html_e( 'Get Paid', 'wp-sell-services' ); ?></strong>
								<span><?php esc_html_e( 'Secure payments with flexible withdrawal options', 'wp-sell-services' ); ?></span>
							</div>
						</div>
						<div class="wpss-vr__feature">
							<span class="wpss-vr__feature-icon">
								<i data-lucide="trending-up" class="wpss-icon" aria-hidden="true"></i>
							</span>
							<div>
								<strong><?php esc_html_e( 'Grow Your Business', 'wp-sell-services' ); ?></strong>
								<span><?php esc_html_e( 'Analytics dashboard to track performance and revenue', 'wp-sell-services' ); ?></span>
							</div>
						</div>
					</div>

					<?php
					// B1 (baseline-2026-04-25.md): inline signup form replaces the
					// previous "Log In / Create Account" buttons that punted visitors
					// to the bare wp-login.php screen. Brand-new visitors can now
					// become vendors in one form, on one page, without leaving the
					// marketplace experience.
					( new \WPSellServices\Frontend\PublicSignup() )->render_form( 'vendor' );
					?>
				</div>
			</div>
			<?php
			return ob_get_clean();
		}

		$user_id   = get_current_user_id();
		$is_vendor = get_user_meta( $user_id, '_wpss_is_vendor', true );

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
		$vendor_settings   = get_option( 'wpss_vendor', array() );
		$registration_mode = $vendor_settings['vendor_registration'] ?? 'open';

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
		<div class="wpss-vr">
			<div class="wpss-vr__card wpss-vr__card--wide">
				<div class="wpss-vr__hero-icon">
					<i data-lucide="rocket" class="wpss-icon" aria-hidden="true"></i>
				</div>
				<h2 class="wpss-vr__title"><?php esc_html_e( 'Start selling your services', 'wp-sell-services' ); ?></h2>
				<p class="wpss-vr__desc"><?php esc_html_e( 'Join our marketplace and turn your skills into income. Create listings, set your rates, and connect with clients worldwide.', 'wp-sell-services' ); ?></p>

				<div class="wpss-vr__features">
					<div class="wpss-vr__feature">
						<span class="wpss-vr__feature-icon">
							<i data-lucide="palette" class="wpss-icon" aria-hidden="true"></i>
						</span>
						<div>
							<strong><?php esc_html_e( 'Create Services', 'wp-sell-services' ); ?></strong>
							<span><?php esc_html_e( 'Build unlimited service listings with custom packages', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-vr__feature">
						<span class="wpss-vr__feature-icon">
							<i data-lucide="wallet" class="wpss-icon" aria-hidden="true"></i>
						</span>
						<div>
							<strong><?php esc_html_e( 'Get Paid', 'wp-sell-services' ); ?></strong>
							<span><?php esc_html_e( 'Secure payments with flexible withdrawal options', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-vr__feature">
						<span class="wpss-vr__feature-icon">
							<i data-lucide="trending-up" class="wpss-icon" aria-hidden="true"></i>
						</span>
						<div>
							<strong><?php esc_html_e( 'Grow Your Business', 'wp-sell-services' ); ?></strong>
							<span><?php esc_html_e( 'Analytics dashboard to track performance and revenue', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				</div>

				<?php if ( $approval_required ) : ?>
					<p class="wpss-vr__note">
						<?php esc_html_e( 'Applications are reviewed by our team. You\'ll be notified once approved.', 'wp-sell-services' ); ?>
					</p>
				<?php endif; ?>

				<div class="wpss-vr__actions">
					<button type="button" class="wpss-vr__btn wpss-vr__btn--primary wpss-vr__btn--lg" data-action="become-vendor">
						<?php esc_html_e( 'Register as Vendor', 'wp-sell-services' ); ?>
					</button>
				</div>
			</div>
		</div>
		<script>
		(function() {
			var btn = document.querySelector('[data-action="become-vendor"]');
			if ( ! btn ) return;
			var dashboardUrl = <?php echo wp_json_encode( wpss_get_page_url( 'dashboard' ) ?: home_url() ); ?>;
			var card = btn.closest('.wpss-vr__card');

			function showMessage(msg, type) {
				var el = document.createElement('div');
				el.style.cssText = 'padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:14px;';
				el.style.background = type === 'error' ? '#fef2f2' : '#f0fdf4';
				el.style.color = type === 'error' ? '#991b1b' : '#166534';
				el.style.border = '1px solid ' + (type === 'error' ? '#fecaca' : '#bbf7d0');
				el.textContent = msg;
				card.insertBefore(el, card.firstChild);
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
			background: #fff;
			border: 1px solid #e5e7eb;
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
			background: #eef2ff;
			color: #4f46e5;
		}
		.wpss-vr__hero-icon .wpss-icon { width: 32px; height: 32px; }
		.wpss-vr__hero-icon--success { background: #dcfce7; color: #16a34a; }
		.wpss-vr__hero-icon--muted { background: #f3f4f6; color: #6b7280; }
		.wpss-vr__title {
			font-size: 24px; font-weight: 700; color: #111827;
			margin: 0 0 12px; line-height: 1.3;
		}
		.wpss-vr__desc {
			font-size: 15px; color: #6b7280; line-height: 1.6;
			margin: 0 0 32px; max-width: 440px; margin-left: auto; margin-right: auto;
		}
		.wpss-vr__features {
			display: flex; flex-direction: column; gap: 16px;
			text-align: left; margin-bottom: 32px;
			background: #f9fafb; border-radius: 12px; padding: 24px;
		}
		.wpss-vr__feature {
			display: flex; align-items: flex-start; gap: 14px;
		}
		.wpss-vr__feature-icon {
			display: inline-flex; align-items: center; justify-content: center;
			width: 32px; height: 32px;
			flex-shrink: 0; margin-top: 2px;
			border-radius: 8px;
			background: #eef2ff;
			color: #4f46e5;
		}
		.wpss-vr__feature-icon .wpss-icon { width: 18px; height: 18px; }
		.wpss-vr__feature strong {
			display: block; font-size: 14px; font-weight: 600; color: #111827; margin-bottom: 2px;
		}
		.wpss-vr__feature span { font-size: 13px; color: #6b7280; line-height: 1.4; }
		.wpss-vr__note {
			background: #fef3c7; color: #92400e; padding: 12px 16px;
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
			background: #4f46e5; color: #fff; border-color: #4f46e5;
		}
		.wpss-vr__btn--primary:hover { background: #4338ca; border-color: #4338ca; color: #fff; }
		.wpss-vr__btn--outline {
			background: transparent; color: #374151; border-color: #d1d5db;
		}
		.wpss-vr__btn--outline:hover { border-color: #9ca3af; color: #111827; }
		.wpss-vr__btn--lg { padding: 14px 36px; font-size: 16px; }

		/* Inline public signup form (B1 from baseline-2026-04-25.md). */
		.wpss-signup-form { text-align: left; max-width: 440px; margin: 0 auto; }
		.wpss-signup-form .wpss-form-group { margin-bottom: 16px; }
		.wpss-signup-form .wpss-form-label { display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px; }
		.wpss-signup-form .wpss-form-input {
			width: 100%; padding: 10px 14px; font-size: 14px;
			border: 1px solid #d1d5db; border-radius: 8px;
			background: #fff; color: #111827;
			transition: border-color 0.15s ease, box-shadow 0.15s ease;
			box-sizing: border-box;
		}
		.wpss-signup-form .wpss-form-input:focus {
			outline: none; border-color: #4f46e5;
			box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
		}
		.wpss-signup-form .wpss-form-hint { font-size: 12px; color: #6b7280; margin: 4px 0 0; }
		.wpss-signup-form .wpss-required { color: #ef4444; }
		.wpss-signup-form__submit {
			display: block; width: 100%; padding: 14px 24px;
			font-size: 15px; font-weight: 600;
			background: #4f46e5; color: #fff;
			border: 0; border-radius: 10px; cursor: pointer;
			transition: background-color 0.15s ease;
			margin-top: 8px;
		}
		.wpss-signup-form__submit:hover:not(:disabled) { background: #4338ca; }
		.wpss-signup-form__submit:disabled { opacity: 0.6; cursor: not-allowed; }
		.wpss-signup-form__signin {
			text-align: center; font-size: 13px; color: #6b7280;
			margin: 16px 0 0; padding-top: 16px; border-top: 1px solid #e5e7eb;
		}
		.wpss-signup-form__signin a { color: #4f46e5; font-weight: 600; text-decoration: none; }
		.wpss-signup-form__signin a:hover { text-decoration: underline; }

		@media (max-width: 480px) {
			.wpss-vr__card { padding: 32px 24px; }
			.wpss-vr__actions { flex-direction: column; }
			.wpss-vr__btn { width: 100%; }
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

		// If WooCommerce adapter is active, hand off to WC cart.
		$adapter = wpss_get_active_adapter();
		if ( $adapter && 'woocommerce' === $adapter->get_id() && function_exists( 'wc_get_cart_url' ) ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}

		if ( ! is_user_logged_in() ) {
			return '<p class="wpss-alert">' . esc_html__( 'Please log in to view your cart.', 'wp-sell-services' ) . '</p>';
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
	 * Checkout fallback when the active e-commerce adapter does not register wpss_checkout.
	 *
	 * When WooCommerce or another adapter handles checkout, this shortcode redirects
	 * to the adapter's checkout page instead of rendering raw shortcode text.
	 *
	 * @return string
	 */
	public function checkout_fallback(): string {
		wpss_enqueue_frontend_assets();

		$general  = get_option( 'wpss_general', array() );
		$platform = $general['ecommerce_platform'] ?? 'standalone';

		// When WooCommerce handles checkout, link to its checkout page.
		if ( function_exists( 'wc_get_checkout_url' )
			&& ( 'woocommerce' === $platform || ( 'auto' === $platform && class_exists( 'WooCommerce' ) ) )
		) {
			$checkout_url = wc_get_checkout_url();
			return '<div class="wpss-checkout-redirect"><p>'
				. sprintf(
					/* translators: %s: checkout page link */
					__( 'Checkout is handled by WooCommerce. <a href="%s">Go to checkout</a>.', 'wp-sell-services' ),
					esc_url( $checkout_url )
				)
				. '</p></div>';
		}

		// For any other adapter or misconfigured state.
		return '<div class="wpss-checkout-notice"><p>'
			. __( 'Checkout is not available. Please configure an e-commerce platform in settings.', 'wp-sell-services' )
			. '</p></div>';
	}
}
