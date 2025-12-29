<?php
/**
 * Template: Buyer Request Card
 *
 * Displays a buyer request card in archive/list views.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/content-request-card.php
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

$request_id         = get_the_ID();
$buyer_id           = (int) get_post_field( 'post_author', $request_id );
$buyer              = get_userdata( $buyer_id );
$request_status_raw = get_post_meta( $request_id, '_wpss_status', true );
$request_status     = $request_status_raw ? $request_status_raw : 'open';
$budget_type_raw    = get_post_meta( $request_id, '_wpss_budget_type', true );
$budget_type        = $budget_type_raw ? $budget_type_raw : 'fixed';
$budget_min         = (float) get_post_meta( $request_id, '_wpss_budget_min', true );
$budget_max         = (float) get_post_meta( $request_id, '_wpss_budget_max', true );
$delivery_days      = (int) get_post_meta( $request_id, '_wpss_delivery_days', true );
$expires_at         = get_post_meta( $request_id, '_wpss_expires_at', true );
$proposal_count     = (int) get_post_meta( $request_id, '_wpss_proposal_count', true );
$skills_raw         = get_post_meta( $request_id, '_wpss_skills_required', true );
$skills             = $skills_raw ? $skills_raw : array();
$categories         = wp_get_post_terms( $request_id, 'wpss_service_category', array( 'fields' => 'names' ) );

// Format budget display.
if ( 'range' === $budget_type && $budget_min && $budget_max ) {
	$budget_display = wpss_format_price( $budget_min ) . ' - ' . wpss_format_price( $budget_max );
} elseif ( $budget_min ) {
	$budget_display = wpss_format_price( $budget_min );
} else {
	$budget_display = __( 'Negotiable', 'wp-sell-services' );
}

// Calculate time remaining.
$time_remaining = '';
if ( $expires_at ) {
	$expires_timestamp = strtotime( $expires_at );
	$now               = time();
	$diff              = $expires_timestamp - $now;

	if ( $diff > 0 ) {
		$days  = floor( $diff / DAY_IN_SECONDS );
		$hours = floor( ( $diff % DAY_IN_SECONDS ) / HOUR_IN_SECONDS );

		if ( $days > 0 ) {
			/* translators: %d: number of days */
			$time_remaining = sprintf( _n( '%d day left', '%d days left', $days, 'wp-sell-services' ), $days );
		} else {
			/* translators: %d: number of hours */
			$time_remaining = sprintf( _n( '%d hour left', '%d hours left', $hours, 'wp-sell-services' ), $hours );
		}
	} else {
		$time_remaining = __( 'Expired', 'wp-sell-services' );
	}
}
?>

<article <?php post_class( 'wpss-request-card' ); ?>>
	<div class="wpss-request-card-header">
		<div class="wpss-request-buyer">
			<img src="<?php echo esc_url( get_avatar_url( $buyer_id, array( 'size' => 40 ) ) ); ?>"
				alt="<?php echo esc_attr( $buyer ? $buyer->display_name : '' ); ?>"
				class="wpss-buyer-avatar">
			<div class="wpss-buyer-info">
				<span class="wpss-buyer-name">
					<?php echo esc_html( $buyer ? $buyer->display_name : __( 'Anonymous', 'wp-sell-services' ) ); ?>
				</span>
				<span class="wpss-request-date">
					<?php
					printf(
						/* translators: %s: human readable time difference */
						esc_html__( 'Posted %s ago', 'wp-sell-services' ),
						esc_html( human_time_diff( get_the_time( 'U' ), time() ) )
					);
					?>
				</span>
			</div>
		</div>

		<?php if ( ! empty( $categories ) ) : ?>
			<span class="wpss-request-category"><?php echo esc_html( $categories[0] ); ?></span>
		<?php endif; ?>
	</div>

	<div class="wpss-request-card-body">
		<h3 class="wpss-request-title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h3>

		<div class="wpss-request-excerpt">
			<?php echo wp_kses_post( wp_trim_words( get_the_content(), 40, '...' ) ); ?>
		</div>

		<?php if ( ! empty( $skills ) ) : ?>
			<div class="wpss-request-skills">
				<?php foreach ( array_slice( $skills, 0, 5 ) as $skill ) : ?>
					<span class="wpss-skill-tag"><?php echo esc_html( $skill ); ?></span>
				<?php endforeach; ?>
				<?php if ( count( $skills ) > 5 ) : ?>
					<span class="wpss-skill-more">
						<?php
						printf(
							/* translators: %d: number of additional skills */
							esc_html__( '+%d more', 'wp-sell-services' ),
							count( $skills ) - 5
						);
						?>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="wpss-request-card-footer">
		<div class="wpss-request-meta">
			<div class="wpss-request-meta-item wpss-request-budget">
				<span class="wpss-meta-label"><?php esc_html_e( 'Budget', 'wp-sell-services' ); ?></span>
				<span class="wpss-meta-value"><?php echo esc_html( $budget_display ); ?></span>
			</div>

			<?php if ( $delivery_days ) : ?>
				<div class="wpss-request-meta-item wpss-request-delivery">
					<span class="wpss-meta-label"><?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?></span>
					<span class="wpss-meta-value">
						<?php
						printf(
							/* translators: %d: number of days */
							esc_html( _n( '%d day', '%d days', $delivery_days, 'wp-sell-services' ) ),
							esc_html( $delivery_days )
						);
						?>
					</span>
				</div>
			<?php endif; ?>

			<div class="wpss-request-meta-item wpss-request-proposals">
				<span class="wpss-meta-label"><?php esc_html_e( 'Proposals', 'wp-sell-services' ); ?></span>
				<span class="wpss-meta-value"><?php echo esc_html( number_format_i18n( $proposal_count ) ); ?></span>
			</div>
		</div>

		<div class="wpss-request-actions">
			<?php if ( $time_remaining && 'Expired' !== $time_remaining ) : ?>
				<span class="wpss-request-expires">
					<span class="wpss-icon-clock"></span>
					<?php echo esc_html( $time_remaining ); ?>
				</span>
			<?php endif; ?>

			<a href="<?php the_permalink(); ?>" class="wpss-btn wpss-btn-primary wpss-btn-sm">
				<?php esc_html_e( 'Send Proposal', 'wp-sell-services' ); ?>
			</a>
		</div>
	</div>

	<?php
	/**
	 * Hook: wpss_after_request_card
	 *
	 * @param int $request_id Request post ID.
	 */
	do_action( 'wpss_after_request_card', $request_id );
	?>
</article>
