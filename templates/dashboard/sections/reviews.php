<?php
/**
 * Dashboard Section: Reviews (vendor only)
 *
 * Reviews the vendor has received, each with the existing seller response or
 * a reply form. The order page has told vendors to "respond from your Reviews
 * section" since 1.2 while /dashboard/reviews/ redirected to the landing
 * section (Basecamp 10264294123). The reply form posts to the REST route the
 * app already uses (POST /wpss/v1/reviews/{id}/reply), so both clients share
 * one write path.
 *
 * @package WPSellServices\Templates
 * @since   1.7.1
 *
 * @var int           $user_id        Current user ID.
 * @var VendorService $vendor_service Vendor service instance.
 * @var bool          $is_vendor      Whether user is a vendor.
 */

use WPSellServices\Services\ReviewService;

defined( 'ABSPATH' ) || exit;

do_action( 'wpss_dashboard_section_before', 'reviews', $user_id );

$review_service = new ReviewService();
$per_page       = 10;
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination param.
$reviews_page = isset( $_GET['reviews_page'] ) ? max( 1, absint( $_GET['reviews_page'] ) ) : 1;
$total        = $review_service->count_vendor_reviews( $user_id );
$total_pages  = (int) ceil( $total / $per_page );
$reviews      = $review_service->get_vendor_reviews(
	$user_id,
	array(
		'limit'  => $per_page,
		'offset' => ( $reviews_page - 1 ) * $per_page,
	)
);
$profile      = wpss_get_vendor_profile_or_default( $user_id );
$avg_rating   = $profile ? (float) $profile->rating : 0.0;
?>

<div class="wpss-section wpss-section--reviews wpss-card">
	<div class="wpss-stats-grid">
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( (string) $total ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Reviews', 'wp-sell-services' ); ?></span>
		</div>
		<div class="wpss-stat-card">
			<span class="wpss-stat-card__value"><?php echo esc_html( number_format_i18n( $avg_rating, 1 ) ); ?></span>
			<span class="wpss-stat-card__label"><?php esc_html_e( 'Average Rating', 'wp-sell-services' ); ?></span>
		</div>
	</div>

	<?php if ( empty( $reviews ) ) : ?>
		<div class="wpss-empty-state">
			<div class="wpss-empty-state__icon">
				<i data-lucide="star" class="wpss-icon wpss-icon--lg" aria-hidden="true"></i>
			</div>
			<h3><?php esc_html_e( 'No reviews yet', 'wp-sell-services' ); ?></h3>
			<p><?php esc_html_e( 'Reviews buyers leave on your completed orders will appear here, and you can reply to each one.', 'wp-sell-services' ); ?></p>
		</div>
	<?php else : ?>
		<div class="wpss-reviews-list">
			<?php
			foreach ( $reviews as $wpss_review ) :
				$service_title = $wpss_review->service_id ? get_the_title( $wpss_review->service_id ) : '';
				?>
				<article class="wpss-review" id="wpss-review-<?php echo esc_attr( (string) $wpss_review->id ); ?>">
					<?php if ( '' !== $service_title ) : ?>
						<p class="wpss-review-service">
							<a href="<?php echo esc_url( wpss_get_order_url( $wpss_review->order_id, 'sales' ) ); ?>"><?php echo esc_html( $service_title ); ?></a>
						</p>
					<?php endif; ?>
					<?php include WPSS_PLUGIN_DIR . 'templates/partials/review-body.php'; ?>
					<?php if ( ! $wpss_review->has_response() ) : ?>
						<form class="wpss-review-reply-form" data-review-id="<?php echo esc_attr( (string) $wpss_review->id ); ?>">
							<label class="wpss-form-label" for="wpss-review-reply-<?php echo esc_attr( (string) $wpss_review->id ); ?>">
								<?php esc_html_e( 'Reply to this review', 'wp-sell-services' ); ?>
							</label>
							<textarea id="wpss-review-reply-<?php echo esc_attr( (string) $wpss_review->id ); ?>" name="reply" class="wpss-form-textarea" rows="3" required
								placeholder="<?php esc_attr_e( 'Thank the buyer or add context. Your reply is public.', 'wp-sell-services' ); ?>"></textarea>
							<div class="wpss-review-reply-form__row">
								<button type="submit" class="wpss-btn wpss-btn--primary wpss-btn--sm">
									<?php esc_html_e( 'Post Reply', 'wp-sell-services' ); ?>
								</button>
							</div>
						</form>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</div>

		<?php if ( $total_pages > 1 ) : ?>
			<nav class="wpss-pagination" aria-label="<?php esc_attr_e( 'Review pages', 'wp-sell-services' ); ?>">
				<?php
				$reviews_page_url = static function ( int $page ): string {
					return $page > 1 ? add_query_arg( 'reviews_page', $page ) : remove_query_arg( 'reviews_page' );
				};
	?>
				<?php if ( $reviews_page > 1 ) : ?>
					<a href="<?php echo esc_url( $reviews_page_url( $reviews_page - 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--prev">
						<i data-lucide="chevron-left" class="wpss-icon" aria-hidden="true"></i>
						<?php esc_html_e( 'Previous', 'wp-sell-services' ); ?>
					</a>
				<?php endif; ?>
				<span class="wpss-pagination__current">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Page %1$d of %2$d', 'wp-sell-services' ),
						(int) $reviews_page,
						(int) $total_pages
					);
					?>
				</span>
				<?php if ( $reviews_page < $total_pages ) : ?>
					<a href="<?php echo esc_url( $reviews_page_url( $reviews_page + 1 ) ); ?>" class="wpss-pagination__link wpss-pagination__link--next">
						<?php esc_html_e( 'Next', 'wp-sell-services' ); ?>
						<i data-lucide="chevron-right" class="wpss-icon" aria-hidden="true"></i>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php
do_action( 'wpss_dashboard_section_after', 'reviews', $user_id );
