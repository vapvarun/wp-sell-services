<?php
/**
 * Service rejection notice: what the reviewer asked for.
 *
 * Shown on the vendor's service card and at the top of the wizard when they
 * open the service to fix it, so the feedback is in front of them where they
 * edit (Basecamp 10340661636). Renders nothing unless the service is rejected.
 *
 * This template can be overridden by copying it to:
 * yourtheme/wp-sell-services/partials/service-rejection-notice.php
 *
 * @package WPSellServices\Templates
 * @since   1.8.0
 *
 * @var int    $service_id   Service ID.
 * @var string $resubmit_url Link to fix and resubmit; omitted inside the wizard.
 */

defined( 'ABSPATH' ) || exit;

if ( 'rejected' !== wpss_get_service_moderation_state( (int) $service_id ) ) {
	return;
}

$wpss_reason = (string) get_post_meta( (int) $service_id, '_wpss_rejection_reason', true );
if ( '' === $wpss_reason ) {
	$wpss_reason = (string) get_post_meta( (int) $service_id, '_wpss_moderation_notes', true );
}
?>
<div class="wpss-service-card__rejection wpss-notice wpss-notice--error" role="status">
	<p class="wpss-service-card__rejection-title">
		<i data-lucide="alert-triangle" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
		<?php esc_html_e( 'This service was not approved.', 'wp-sell-services' ); ?>
	</p>
	<?php if ( '' !== $wpss_reason ) : ?>
		<p class="wpss-service-card__rejection-reason">
			<strong><?php esc_html_e( 'Reviewer feedback:', 'wp-sell-services' ); ?></strong>
			<?php echo esc_html( $wpss_reason ); ?>
		</p>
	<?php endif; ?>
	<?php if ( ! empty( $resubmit_url ) ) : ?>
		<p class="wpss-service-card__rejection-help">
			<?php esc_html_e( 'Edit your service to address the feedback, then resubmit it for review. A reviewer will check it again before it goes live.', 'wp-sell-services' ); ?>
		</p>
		<a href="<?php echo esc_url( $resubmit_url ); ?>" class="wpss-btn wpss-btn--primary wpss-btn--sm wpss-service-card__resubmit">
			<i data-lucide="refresh-cw" class="wpss-icon wpss-icon--sm" aria-hidden="true"></i>
			<?php esc_html_e( 'Resubmit for review', 'wp-sell-services' ); ?>
		</a>
	<?php else : ?>
		<p class="wpss-service-card__rejection-help">
			<?php esc_html_e( 'Address the feedback, then publish again to send it back for review.', 'wp-sell-services' ); ?>
		</p>
	<?php endif; ?>
</div>
