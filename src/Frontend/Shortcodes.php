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

		// Account shortcodes.
		add_shortcode( 'wpss_login', array( $this, 'login_form' ) );
		add_shortcode( 'wpss_register', array( $this, 'register_form' ) );
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
								<td><a href="<?php echo esc_url( add_query_arg( 'order_id', $order->id, get_permalink( get_option( 'wpss_order_details_page' ) ) ) ); ?>" class="button button-small"><?php esc_html_e( 'View', 'wp-sell-services' ); ?></a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p class="wpss-no-results"><?php esc_html_e( 'No orders found.', 'wp-sell-services' ); ?></p>
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
					<a href="<?php echo esc_url( home_url( '/vendor/' . get_userdata( $vendor['id'] )->user_nicename ) ); ?>">
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
			<h3 class="wpss-request-title"><?php echo esc_html( $request->title ?? $request->post_title ); ?></h3>
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
}
