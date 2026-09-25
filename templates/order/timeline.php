<?php
/**
 * Order timeline: placed, started, delivered, completed, and the state it is in.
 *
 * One partial for the buyer's and seller's order view and the admin order
 * screen, which had no timeline at all (Basecamp 10337161480). Read-only.
 *
 * Override: {theme}/wp-sell-services/order/timeline.php
 *
 * @package WPSellServices
 * @since   1.8.0
 *
 * @var \WPSellServices\Models\ServiceOrder $wpss_order The order.
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $wpss_order ) ) {
	return;
}
?>
<section class="wpss-order-section">
	<div class="wpss-order-section__header">
		<h2 class="wpss-order-section__title">
			<i data-lucide="clock" class="wpss-icon" aria-hidden="true"></i>
			<?php esc_html_e( 'Order Timeline', 'wp-sell-services' ); ?>
		</h2>
	</div>
	<div class="wpss-order-section__body">
		<div class="wpss-timeline">
			<div class="wpss-timeline__item wpss-timeline__item--completed">
				<div class="wpss-timeline__marker"></div>
				<div class="wpss-timeline__content">
					<span class="wpss-timeline__title"><?php esc_html_e( 'Order Placed', 'wp-sell-services' ); ?></span>
					<span class="wpss-timeline__date"><?php echo esc_html( $wpss_order->created_at ? wp_date( 'M j, Y \a\t g:i A', $wpss_order->created_at->getTimestamp() ) : '' ); ?></span>
				</div>
			</div>

			<?php if ( $wpss_order->started_at ) : ?>
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title"><?php esc_html_e( 'Work Started', 'wp-sell-services' ); ?></span>
						<span class="wpss-timeline__date"><?php echo esc_html( wp_date( 'M j, Y \a\t g:i A', $wpss_order->started_at->getTimestamp() ) ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( in_array( $wpss_order->status, array( 'delivered', 'completed' ), true ) ) : ?>
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title"><?php esc_html_e( 'Delivered', 'wp-sell-services' ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( $wpss_order->completed_at ) : ?>
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
						<span class="wpss-timeline__date"><?php echo esc_html( wp_date( 'M j, Y \a\t g:i A', $wpss_order->completed_at->getTimestamp() ) ); ?></span>
					</div>
				</div>
			<?php endif; ?>

			<?php
			// Own `if`, not an `elseif` of completed_at: an order that was
			// completed and THEN refunded or cancelled keeps its Completed
			// entry and also shows what happened to it afterwards.
			?>
			<?php if ( in_array( $wpss_order->status, array( 'cancelled', 'refunded', 'partially_refunded' ), true ) ) : ?>
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker" style="background: var(--wpss-danger, #ef4444);"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title">
							<?php
							if ( 'refunded' === $wpss_order->status ) {
								esc_html_e( 'Refunded', 'wp-sell-services' );
							} elseif ( 'partially_refunded' === $wpss_order->status ) {
								esc_html_e( 'Partially Refunded', 'wp-sell-services' );
							} else {
								esc_html_e( 'Cancelled', 'wp-sell-services' );
							}
							?>
						</span>
						<span class="wpss-timeline__date"><?php echo esc_html( $wpss_order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $wpss_order->updated_at->getTimestamp() ) : '' ); ?></span>
					</div>
				</div>
			<?php elseif ( 'cancellation_requested' === $wpss_order->status ) : ?>
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker" style="background: var(--wpss-warning, #f59e0b);"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title"><?php esc_html_e( 'Cancellation Requested', 'wp-sell-services' ); ?></span>
						<span class="wpss-timeline__date"><?php echo esc_html( $wpss_order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $wpss_order->updated_at->getTimestamp() ) : '' ); ?></span>
					</div>
				</div>

			<?php elseif ( 'disputed' === $wpss_order->status ) : ?>
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker" style="background: var(--wpss-danger, #ef4444);"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title"><?php esc_html_e( 'Disputed', 'wp-sell-services' ); ?></span>
						<span class="wpss-timeline__date"><?php echo esc_html( $wpss_order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $wpss_order->updated_at->getTimestamp() ) : '' ); ?></span>
					</div>
				</div>

			<?php elseif ( 'rejected' === $wpss_order->status ) : ?>
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker" style="background: var(--wpss-danger, #ef4444);"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title"><?php esc_html_e( 'Rejected', 'wp-sell-services' ); ?></span>
						<span class="wpss-timeline__date"><?php echo esc_html( $wpss_order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $wpss_order->updated_at->getTimestamp() ) : '' ); ?></span>
					</div>
				</div>

			<?php elseif ( 'revision_requested' === $wpss_order->status ) : ?>
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker" style="background: var(--wpss-warning, #f59e0b);"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title"><?php esc_html_e( 'Revision Requested', 'wp-sell-services' ); ?></span>
						<span class="wpss-timeline__date"><?php echo esc_html( $wpss_order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $wpss_order->updated_at->getTimestamp() ) : '' ); ?></span>
						<?php $revision_reason = $wpss_order->get_revision_reason(); ?>
						<?php if ( '' !== $revision_reason ) : ?>
							<p class="wpss-timeline__note wpss-revision-reason"><?php echo esc_html( $revision_reason ); ?></p>
						<?php endif; ?>
					</div>
				</div>

			<?php elseif ( 'late' === $wpss_order->status ) : ?>
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker" style="background: var(--wpss-warning, #f59e0b);"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title"><?php esc_html_e( 'Order Late', 'wp-sell-services' ); ?></span>
						<span class="wpss-timeline__date"><?php echo esc_html( $wpss_order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $wpss_order->updated_at->getTimestamp() ) : '' ); ?></span>
					</div>
				</div>

			<?php elseif ( 'pending_approval' === $wpss_order->status ) : ?>
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker" style="background: var(--wpss-info, #3b82f6);"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title"><?php esc_html_e( 'Awaiting Approval', 'wp-sell-services' ); ?></span>
						<span class="wpss-timeline__date"><?php echo esc_html( $wpss_order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $wpss_order->updated_at->getTimestamp() ) : '' ); ?></span>
					</div>
				</div>

			<?php elseif ( 'on_hold' === $wpss_order->status ) : ?>
				<div class="wpss-timeline__item wpss-timeline__item--completed">
					<div class="wpss-timeline__marker" style="background: var(--wpss-warning, #f59e0b);"></div>
					<div class="wpss-timeline__content">
						<span class="wpss-timeline__title"><?php esc_html_e( 'On Hold', 'wp-sell-services' ); ?></span>
						<span class="wpss-timeline__date"><?php echo esc_html( $wpss_order->updated_at ? wp_date( 'M j, Y \a\t g:i A', $wpss_order->updated_at->getTimestamp() ) : '' ); ?></span>
					</div>
				</div>

			<?php else : ?>
				<!-- Pending steps -->
				<?php if ( ! $wpss_order->started_at && in_array( $wpss_order->status, array( 'pending', 'accepted', 'pending_requirements' ), true ) ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--pending">
						<div class="wpss-timeline__marker"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Work Started', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline__date"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( in_array( $wpss_order->status, array( 'pending', 'accepted', 'pending_requirements', 'in_progress' ), true ) ) : ?>
					<div class="wpss-timeline__item wpss-timeline__item--pending">
						<div class="wpss-timeline__marker"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline__date"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></span>
						</div>
					</div>
					<div class="wpss-timeline__item wpss-timeline__item--pending">
						<div class="wpss-timeline__marker"></div>
						<div class="wpss-timeline__content">
							<span class="wpss-timeline__title"><?php esc_html_e( 'Completed', 'wp-sell-services' ); ?></span>
							<span class="wpss-timeline__date"><?php esc_html_e( 'Pending', 'wp-sell-services' ); ?></span>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
</section>
