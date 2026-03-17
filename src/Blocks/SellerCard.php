<?php
/**
 * Seller Card Block
 *
 * Displays seller profile information in a card format.
 *
 * @package WPSellServices\Blocks
 * @since   1.0.0
 */

declare(strict_types=1);


namespace WPSellServices\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * SellerCard class.
 *
 * @since 1.0.0
 */
class SellerCard extends AbstractBlock {

	/**
	 * Get block name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'seller-card';
	}

	/**
	 * Get block title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Seller Card', 'wp-sell-services' );
	}

	/**
	 * Get block description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Display a seller profile card with stats.', 'wp-sell-services' );
	}

	/**
	 * Get block icon.
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'businessman';
	}

	/**
	 * Get block keywords.
	 *
	 * @return array
	 */
	public function get_keywords(): array {
		return array( 'seller', 'vendor', 'profile', 'freelancer', 'user' );
	}

	/**
	 * Get block attributes.
	 *
	 * @return array
	 */
	public function get_attributes(): array {
		return array(
			'userId'       => array(
				'type'    => 'number',
				'default' => 0,
			),
			'showBio'      => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showStats'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showRating'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showServices' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showButton'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'layout'       => array(
				'type'    => 'string',
				'default' => 'vertical',
			),
		);
	}

	/**
	 * Render the block.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string
	 */
	public function render( array $attributes, string $content = '' ): string {
		$this->start_render();

		$defaults = array(
			'userId'       => 0,
			'showBio'      => true,
			'showStats'    => true,
			'showRating'   => true,
			'showServices' => true,
			'showButton'   => true,
			'layout'       => 'vertical',
		);

		$attributes = wp_parse_args( $attributes, $defaults );

		// Get user ID - use current user if not specified.
		$user_id = $attributes['userId'] ?: get_current_user_id();

		if ( ! $user_id ) {
			return '';
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return '';
		}

		// Get seller stats.
		$stats = $this->get_seller_stats( $user_id );

		$wrapper_classes = array( 'wpss-seller-layout-' . $attributes['layout'] );
		?>
		<div <?php echo $this->get_wrapper_attributes( $attributes, $wrapper_classes ); ?>>
			<div class="wpss-seller-card-inner">
				<div class="wpss-seller-header">
					<div class="wpss-seller-avatar">
						<?php echo get_avatar( $user_id, 80 ); ?>
						<?php if ( $stats['is_online'] ) : ?>
							<span class="wpss-online-status" title="<?php esc_attr_e( 'Online', 'wp-sell-services' ); ?>"></span>
						<?php endif; ?>
					</div>

					<div class="wpss-seller-info">
						<h3 class="wpss-seller-name"><?php echo esc_html( $user->display_name ); ?></h3>

						<?php
						$title = get_user_meta( $user_id, 'wpss_seller_title', true );
						if ( $title ) :
							?>
							<p class="wpss-seller-title"><?php echo esc_html( $title ); ?></p>
						<?php endif; ?>

						<?php if ( $attributes['showRating'] && $stats['rating'] ) : ?>
							<div class="wpss-seller-rating">
								<span class="wpss-star">&#9733;</span>
								<span class="wpss-rating-value"><?php echo esc_html( number_format( $stats['rating'], 1 ) ); ?></span>
								<span class="wpss-review-count">(<?php echo esc_html( $stats['reviews'] ); ?>)</span>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( $attributes['showBio'] ) : ?>
					<?php
					$bio = get_user_meta( $user_id, 'description', true );
					if ( $bio ) :
						?>
						<div class="wpss-seller-bio">
							<?php echo wp_kses_post( wpautop( $bio ) ); ?>
						</div>
					<?php endif; ?>
				<?php endif; ?>

				<?php if ( $attributes['showStats'] ) : ?>
					<div class="wpss-seller-stats">
						<div class="wpss-seller-stat">
							<span class="stat-value"><?php echo esc_html( $stats['services'] ); ?></span>
							<span class="stat-label"><?php esc_html_e( 'Services', 'wp-sell-services' ); ?></span>
						</div>
						<div class="wpss-seller-stat">
							<span class="stat-value"><?php echo esc_html( $stats['orders_completed'] ); ?></span>
							<span class="stat-label"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
						</div>
						<div class="wpss-seller-stat">
							<span class="stat-value"><?php echo esc_html( $stats['response_time'] ); ?></span>
							<span class="stat-label"><?php esc_html_e( 'Response', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $attributes['showServices'] ) : ?>
					<?php $this->render_seller_services( $user_id ); ?>
				<?php endif; ?>

				<?php if ( $attributes['showButton'] ) : ?>
					<div class="wpss-seller-actions">
						<a href="<?php echo esc_url( get_author_posts_url( $user_id ) ); ?>" class="wpss-button wpss-button-primary">
							<?php esc_html_e( 'View Profile', 'wp-sell-services' ); ?>
						</a>
						<a href="<?php echo esc_url( add_query_arg( 'contact', $user_id, get_author_posts_url( $user_id ) ) ); ?>" class="wpss-button wpss-button-secondary">
							<?php esc_html_e( 'Contact', 'wp-sell-services' ); ?>
						</a>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php

		return $this->end_render();
	}

	/**
	 * Get seller statistics.
	 *
	 * @param int $user_id User ID.
	 * @return array
	 */
	private function get_seller_stats( int $user_id ): array {
		global $wpdb;

		// Count services.
		$services = count_user_posts( $user_id, 'wpss_service', true );

		// Count completed orders.
		$orders_completed = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_orders
				WHERE vendor_id = %d AND status = 'completed'",
				$user_id
			)
		);

		// Calculate average rating.
		$rating = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(rating) FROM {$wpdb->prefix}wpss_reviews
				WHERE vendor_id = %d",
				$user_id
			)
		);

		$reviews = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}wpss_reviews
				WHERE vendor_id = %d",
				$user_id
			)
		);

		// Get response time.
		$response_time = get_user_meta( $user_id, 'wpss_avg_response_time', true );
		$response_time = $response_time ? $this->format_response_time( $response_time ) : __( 'N/A', 'wp-sell-services' );

		// Check if online (active in last 15 minutes).
		$last_active = get_user_meta( $user_id, 'wpss_last_active', true );
		$is_online   = $last_active && ( time() - strtotime( $last_active ) ) < 900;

		return array(
			'services'         => $services,
			'orders_completed' => $orders_completed,
			'rating'           => $rating,
			'reviews'          => $reviews,
			'response_time'    => $response_time,
			'is_online'        => $is_online,
		);
	}

	/**
	 * Format response time.
	 *
	 * @param int $seconds Response time in seconds.
	 * @return string
	 */
	private function format_response_time( int $seconds ): string {
		if ( $seconds < 3600 ) {
			$minutes = round( $seconds / 60 );
			/* translators: %d: number of minutes */
			return sprintf( _n( '%d min', '%d mins', $minutes, 'wp-sell-services' ), $minutes );
		}

		$hours = round( $seconds / 3600 );
		/* translators: %d: number of hours */
		return sprintf( _n( '%d hour', '%d hours', $hours, 'wp-sell-services' ), $hours );
	}

	/**
	 * Render seller's services.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function render_seller_services( int $user_id ): void {
		$services = get_posts(
			array(
				'post_type'      => 'wpss_service',
				'post_status'    => 'publish',
				'author'         => $user_id,
				'posts_per_page' => 3,
				'orderby'        => 'meta_value_num',
				'meta_key'       => '_wpss_rating_average',
				'order'          => 'DESC',
			)
		);

		if ( empty( $services ) ) {
			return;
		}
		?>
		<div class="wpss-seller-services">
			<h4 class="wpss-seller-services-title"><?php esc_html_e( 'Popular Services', 'wp-sell-services' ); ?></h4>
			<ul class="wpss-seller-services-list">
				<?php foreach ( $services as $service ) : ?>
					<li>
						<a href="<?php echo esc_url( get_permalink( $service ) ); ?>">
							<?php if ( has_post_thumbnail( $service ) ) : ?>
								<?php echo get_the_post_thumbnail( $service, 'thumbnail' ); ?>
							<?php endif; ?>
							<span><?php echo esc_html( get_the_title( $service ) ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
