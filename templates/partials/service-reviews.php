<?php
/**
 * Template Partial: Service Reviews
 *
 * Displays reviews for a service.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 *
 * @var WPSellServices\Models\Service $service      Service object.
 * @var int                            $service_id   Service post ID.
 * @var array                          $reviews      Array of review objects.
 * @var float                          $rating_avg   Average rating.
 * @var int                            $rating_count Total rating count.
 */

defined( 'ABSPATH' ) || exit;

$service_id = get_the_ID();
global $wpdb;
$reviews_table = $wpdb->prefix . 'wpss_reviews';

// Get rating breakdown from actual DB data.
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

// Derive count and average from actual DB data so they stay in sync with the breakdown.
$rating_count = 0;
$rating_sum   = 0;
foreach ( $breakdown as $star => $row ) {
	$rating_count += (int) $row->count;
	$rating_sum   += (int) $star * (int) $row->count;
}
$rating_avg = $rating_count > 0 ? round( $rating_sum / $rating_count, 1 ) : 0.0;

/**
 * Filters the number of reviews to display per page.
 *
 * @since 1.0.0
 *
 * @param int $per_page  Number of reviews per page (default: 10).
 * @param int $service_id Service post ID.
 */
$reviews_per_page = apply_filters( 'wpss_reviews_per_page', 10, $service_id );

// Get reviews.
$reviews = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$reviews_table}
		WHERE service_id = %d AND status = 'approved'
		ORDER BY created_at DESC
		LIMIT %d",
		$service_id,
		$reviews_per_page
	)
);

/**
 * Fires before the service reviews section.
 *
 * @since 1.0.0
 *
 * @param int $service_id Service post ID.
 */
do_action( 'wpss_before_service_reviews', $service_id );
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
						absint( $rating_count )
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
				$reviewer     = get_userdata( $review->customer_id );
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

					<div class="wpss-review-helpful">
						<?php if ( is_user_logged_in() && (int) ( $review->reviewer_id ?? 0 ) !== get_current_user_id() ) : ?>
							<button type="button" class="wpss-review-helpful-btn" data-review="<?php echo esc_attr( $review->id ); ?>" style="background:none;border:1px solid #ddd;border-radius:4px;padding:4px 10px;cursor:pointer;font-size:13px;color:#666;display:inline-flex;align-items:center;gap:4px;">
								<span class="wpss-helpful-icon">
									<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></svg>
								</span>
								<span class="wpss-helpful-text"><?php esc_html_e( 'Helpful', 'wp-sell-services' ); ?></span>
								<?php if ( isset( $review->helpful_count ) && $review->helpful_count > 0 ) : ?>
									<span class="wpss-helpful-count">(<?php echo esc_html( $review->helpful_count ); ?>)</span>
								<?php endif; ?>
							</button>
						<?php endif; ?>
						<?php if ( ( ! is_user_logged_in() || (int) ( $review->reviewer_id ?? 0 ) === get_current_user_id() ) && isset( $review->helpful_count ) && $review->helpful_count > 0 ) : ?>
							<span style="color:#999;font-size:13px;">
								<?php
								printf(
									/* translators: %d: number of people */
									esc_html( _n( '%d person found this helpful', '%d people found this helpful', $review->helpful_count, 'wp-sell-services' ) ),
									absint( $review->helpful_count )
								);
								?>
							</span>
						<?php endif; ?>
					</div>
				</div>

				<?php
				/**
				 * Fires after a single review item.
				 *
				 * @since 1.0.0
				 *
				 * @param object $review Review object.
				 */
				do_action( 'wpss_after_single_review', $review );
				?>
			<?php endforeach; ?>
		</div>

		<?php if ( $rating_count > $reviews_per_page ) : ?>
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

<?php
/**
 * Fires after the service reviews section.
 *
 * @since 1.0.0
 *
 * @param int $service_id Service post ID.
 */
do_action( 'wpss_after_service_reviews', $service_id );
?>
