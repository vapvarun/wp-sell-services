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
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/partials/review-body.php
 *
 * @package WPSellServices\Templates
 * @since   1.6.0
 * @version 1.9.0
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
		<?php if ( $wpss_review->is_imported() ) : ?>
			<span class="wpss-review-imported"><?php esc_html_e( 'Imported review', 'wp-sell-services' ); ?></span>
		<?php endif; ?>
		<div class="wpss-review-rating">
			<?php echo wpss_star_rating( (float) $wpss_review->rating ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the helper. ?>
		</div>
	</div>
	<?php if ( $wpss_review->created_at ) : ?>
		<span class="wpss-review-date">
			<?php echo esc_html( wpss_time_ago( $wpss_review->created_at->format( 'Y-m-d H:i:s' ) ) ); ?>
		</span>
	<?php endif; ?>
</div>

<?php
// Set by a list that mixes services (the vendor profile): a seller with several
// services is judged service by service.
$wpss_review_service = ! empty( $wpss_review_show_service ) && $wpss_review->service_id ? get_post( $wpss_review->service_id ) : null;
if ( $wpss_review_service && 'publish' === $wpss_review_service->post_status ) :
	?>
	<p class="wpss-review-service"><a href="<?php echo esc_url( get_permalink( $wpss_review_service ) ); ?>"><?php echo esc_html( $wpss_review_service->post_title ); ?></a></p>
<?php endif; ?>

<?php if ( '' !== $wpss_review->content ) : ?>
	<div class="wpss-review-content">
		<?php echo wp_kses_post( wpautop( $wpss_review->content ) ); ?>
	</div>
<?php endif; ?>

<?php if ( '' !== $wpss_review->response ) : ?>
	<div class="wpss-review-reply">
		<div class="wpss-reply-header">
			<strong><?php esc_html_e( 'Vendor Response:', 'wp-sell-services' ); ?></strong>
			<?php if ( $wpss_review->response_at ) : ?>
				<span class="wpss-reply-date">
					<?php echo esc_html( wpss_time_ago( $wpss_review->response_at->format( 'Y-m-d H:i:s' ) ) ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php echo wp_kses_post( wpautop( $wpss_review->response ) ); ?>
	</div>
<?php endif; ?>
