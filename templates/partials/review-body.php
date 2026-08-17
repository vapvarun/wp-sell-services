<?php
/**
 * Template Partial: Review body
 *
 * The author / stars / date header, the review text, and the seller's response.
 * Everything that is the review itself, and nothing that belongs to the surface
 * showing it - the public list adds its own "Helpful" control around this, the
 * order page adds its own section heading.
 *
 * Extracted because a review was rendered in two places with two different
 * ideas of the data: the public list read raw DB rows (`$review->review`,
 * `$review->vendor_reply`) while everything else uses the Review model
 * (`content`, `response`). This partial takes the MODEL, so there is one shape.
 *
 * @package WPSellServices\Templates
 * @since   1.6.0
 *
 * @var WPSellServices\Models\Review $wpss_review Review to render.
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $wpss_review ) || ! $wpss_review instanceof \WPSellServices\Models\Review ) {
	return;
}

$wpss_reviewer_name = $wpss_review->get_reviewer_name();
?>
<div class="wpss-review-header">
	<img src="<?php echo esc_url( get_avatar_url( $wpss_review->reviewer_id, array( 'size' => 48 ) ) ); ?>"
		alt="<?php echo esc_attr( $wpss_reviewer_name ); ?>"
		class="wpss-review-avatar">
	<div class="wpss-review-info">
		<strong class="wpss-review-author">
			<?php echo esc_html( $wpss_reviewer_name ); ?>
		</strong>
		<div class="wpss-review-rating"
			role="img"
			aria-label="
			<?php
			echo esc_attr(
				sprintf(
					/* translators: %d: star rating out of five */
					_n( '%d star out of 5', '%d stars out of 5', $wpss_review->rating, 'wp-sell-services' ),
					$wpss_review->rating
				)
			);
			?>
			">
			<?php for ( $wpss_star = 1; $wpss_star <= 5; $wpss_star++ ) : ?>
				<span class="wpss-star <?php echo $wpss_star <= $wpss_review->rating ? 'filled' : ''; ?>" aria-hidden="true">&#9733;</span>
			<?php endfor; ?>
		</div>
	</div>
	<?php if ( $wpss_review->created_at ) : ?>
		<span class="wpss-review-date">
			<?php echo esc_html( wpss_time_ago( $wpss_review->created_at->format( 'Y-m-d H:i:s' ) ) ); ?>
		</span>
	<?php endif; ?>
</div>

<?php if ( '' !== $wpss_review->content ) : ?>
	<div class="wpss-review-content">
		<?php echo wp_kses_post( wpautop( $wpss_review->content ) ); ?>
	</div>
<?php endif; ?>

<?php if ( '' !== $wpss_review->response ) : ?>
	<div class="wpss-review-reply">
		<div class="wpss-reply-header">
			<strong><?php esc_html_e( 'Seller Response:', 'wp-sell-services' ); ?></strong>
			<?php if ( $wpss_review->response_at ) : ?>
				<span class="wpss-reply-date">
					<?php echo esc_html( wpss_time_ago( $wpss_review->response_at->format( 'Y-m-d H:i:s' ) ) ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php echo wp_kses_post( wpautop( $wpss_review->response ) ); ?>
	</div>
<?php endif; ?>
