<?php
/**
 * Template Partial: Service Reviews
 *
 * Displays reviews for a service.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var WPSellServices\Models\Service $service Service object.
 */

defined( 'ABSPATH' ) || exit;

$service_id   = get_the_ID();
$rating_avg   = (float) get_post_meta( $service_id, '_wpss_rating_average', true );
$rating_count = (int) get_post_meta( $service_id, '_wpss_rating_count', true );

global $wpdb;
$reviews_table = $wpdb->prefix . 'wpss_reviews';

// Get rating breakdown.
$breakdown = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT rating, COUNT(*) as count
		FROM {$reviews_table}
		WHERE service_id = %d AND status = 'approved'
		GROUP BY rating
		ORDER BY rating DESC",
		$service_id
	),
	OBJECT_K
);

// Get reviews.
$reviews = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$reviews_table}
		WHERE service_id = %d AND status = 'approved'
		ORDER BY created_at DESC
		LIMIT 10",
		$service_id
	)
);
?>

<div class="wpss-service-reviews" id="reviews">
	<h2>
		<?php esc_html_e( 'Reviews', 'wp-sell-services' ); ?>
		<?php if ( $rating_count > 0 ) : ?>
			<span class="wpss-review-count">(<?php echo esc_html( $rating_count ); ?>)</span>
		<?php endif; ?>
	</h2>

	<?php if ( $rating_count > 0 ) : ?>
		<div class="wpss-reviews-summary">
			<div class="wpss-reviews-average">
				<span class="wpss-average-number"><?php echo esc_html( number_format( $rating_avg, 1 ) ); ?></span>
				<div class="wpss-average-stars">
					<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
						<span class="wpss-star <?php echo $i <= round( $rating_avg ) ? 'filled' : ''; ?>">★</span>
					<?php endfor; ?>
				</div>
				<span class="wpss-average-count">
					<?php
					printf(
						/* translators: %d: number of reviews */
						esc_html( _n( '%d review', '%d reviews', $rating_count, 'wp-sell-services' ) ),
						$rating_count
					);
					?>
				</span>
			</div>

			<div class="wpss-reviews-breakdown">
				<?php for ( $star = 5; $star >= 1; $star-- ) : ?>
					<?php
					$count      = isset( $breakdown[ $star ] ) ? $breakdown[ $star ]->count : 0;
					$percentage = $rating_count > 0 ? ( $count / $rating_count ) * 100 : 0;
					?>
					<div class="wpss-breakdown-row">
						<span class="wpss-breakdown-label"><?php echo esc_html( $star ); ?> <span class="wpss-star filled">★</span></span>
						<div class="wpss-breakdown-bar">
							<div class="wpss-breakdown-fill" style="width: <?php echo esc_attr( $percentage ); ?>%"></div>
						</div>
						<span class="wpss-breakdown-count">(<?php echo esc_html( $count ); ?>)</span>
					</div>
				<?php endfor; ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $reviews ) ) : ?>
		<div class="wpss-reviews-list">
			<?php foreach ( $reviews as $review ) : ?>
				<?php
				$reviewer = get_userdata( $review->customer_id );
				$service_post = get_post( $review->service_id );
				?>
				<div class="wpss-review">
					<div class="wpss-review-header">
						<img src="<?php echo esc_url( get_avatar_url( $review->customer_id, [ 'size' => 48 ] ) ); ?>"
							 alt="<?php echo esc_attr( $reviewer ? $reviewer->display_name : '' ); ?>"
							 class="wpss-review-avatar">
						<div class="wpss-review-info">
							<strong class="wpss-review-author">
								<?php echo esc_html( $reviewer ? $reviewer->display_name : __( 'Anonymous', 'wp-sell-services' ) ); ?>
							</strong>
							<div class="wpss-review-rating">
								<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
									<span class="wpss-star <?php echo $i <= $review->rating ? 'filled' : ''; ?>">★</span>
								<?php endfor; ?>
							</div>
						</div>
						<span class="wpss-review-date">
							<?php echo esc_html( wpss_time_ago( $review->created_at ) ); ?>
						</span>
					</div>

					<div class="wpss-review-content">
						<?php echo wp_kses_post( wpautop( $review->review ) ); ?>
					</div>

					<?php if ( ! empty( $review->vendor_reply ) ) : ?>
						<div class="wpss-review-reply">
							<div class="wpss-reply-header">
								<strong><?php esc_html_e( 'Seller Response:', 'wp-sell-services' ); ?></strong>
								<?php if ( ! empty( $review->vendor_reply_at ) ) : ?>
									<span class="wpss-reply-date">
										<?php echo esc_html( wpss_time_ago( $review->vendor_reply_at ) ); ?>
									</span>
								<?php endif; ?>
							</div>
							<?php echo wp_kses_post( wpautop( $review->vendor_reply ) ); ?>
						</div>
					<?php endif; ?>

					<?php if ( isset( $review->helpful_count ) && $review->helpful_count > 0 ) : ?>
						<div class="wpss-review-helpful">
							<span class="wpss-helpful-count">
								<?php
								printf(
									/* translators: %d: number of people */
									esc_html( _n( '%d person found this helpful', '%d people found this helpful', $review->helpful_count, 'wp-sell-services' ) ),
									$review->helpful_count
								);
								?>
							</span>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $rating_count > 10 ) : ?>
			<div class="wpss-reviews-pagination">
				<button type="button"
						class="wpss-btn wpss-btn-outline wpss-load-more-reviews"
						data-service="<?php echo esc_attr( $service_id ); ?>"
						data-page="2">
					<?php esc_html_e( 'Load More Reviews', 'wp-sell-services' ); ?>
				</button>
			</div>
		<?php endif; ?>

	<?php else : ?>
		<div class="wpss-no-reviews">
			<p><?php esc_html_e( 'No reviews yet. Be the first to review this service!', 'wp-sell-services' ); ?></p>
		</div>
	<?php endif; ?>
</div>
