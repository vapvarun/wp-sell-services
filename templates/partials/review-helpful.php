<?php
/**
 * Review "Helpful" control.
 *
 * A toggle for a signed-in reader who did not write the review; a plain count
 * for everyone else. Shared by the service page and the "load more" reviews
 * so the two cannot drift.
 *
 * This template can be overridden in your theme:
 * yourtheme/wp-sell-services/partials/review-helpful.php
 *
 * @package WPSellServices\Templates
 * @version 1.9.0
 *
 * @var array{review_id:int, helpful_count:int, reviewer_id:int, voted:bool} $wpss_helpful Review and the viewer's vote.
 */

defined( 'ABSPATH' ) || exit;

$wpss_helpful_count = (int) $wpss_helpful['helpful_count'];
?>
<?php if ( is_user_logged_in() && (int) $wpss_helpful['reviewer_id'] !== get_current_user_id() ) : ?>
	<button type="button" class="wpss-review-helpful-btn<?php echo $wpss_helpful['voted'] ? ' marked' : ''; ?>" data-review="<?php echo esc_attr( (string) $wpss_helpful['review_id'] ); ?>" aria-pressed="<?php echo $wpss_helpful['voted'] ? 'true' : 'false'; ?>">
		<span class="wpss-helpful-icon">
			<i data-lucide="thumbs-up" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
		</span>
		<span class="wpss-helpful-text"><?php esc_html_e( 'Helpful', 'wp-sell-services' ); ?></span>
		<?php if ( $wpss_helpful_count > 0 ) : ?>
			<span class="wpss-helpful-count">(<?php echo esc_html( (string) $wpss_helpful_count ); ?>)</span>
		<?php endif; ?>
	</button>
<?php elseif ( $wpss_helpful_count > 0 ) : ?>
	<span class="wpss-text-muted wpss-helpful-note">
		<?php
		printf(
			/* translators: %d: number of people */
			esc_html( _n( '%d person found this helpful', '%d people found this helpful', $wpss_helpful_count, 'wp-sell-services' ) ),
			absint( $wpss_helpful_count )
		);
		?>
	</span>
<?php endif; ?>
