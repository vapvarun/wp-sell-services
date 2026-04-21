<?php
/**
 * Template: Buyer Request Card
 *
 * Displays a buyer request card in archive/list views.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/content-request-card.php
 *
 * Available hooks:
 * - wpss_before_request_card - Before card wrapper
 * - wpss_request_card_header - After buyer info in header
 * - wpss_request_card_meta - After skills display
 * - wpss_request_card_footer - Inside footer, before actions
 * - wpss_after_request_card - After card wrapper
 *
 * Available filters:
 * - wpss_request_card_classes - Modify card CSS classes
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

// Filter card classes.
$card_classes = apply_filters( 'wpss_request_card_classes', array( 'wpss-request-card' ), $request_id );

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

<?php
/**
 * Hook: wpss_before_request_card
 *
 * Fires before the request card wrapper.
 *
 * @since 1.0.0
 *
 * @param int $request_id Request post ID.
 */
do_action( 'wpss_before_request_card', $request_id );
?>

<article <?php post_class( $card_classes ); ?>>
	<div class="wpss-request-card__header">
		<div class="wpss-request-card__buyer">
			<img src="<?php echo esc_url( get_avatar_url( $buyer_id, array( 'size' => 40 ) ) ); ?>"
				alt="<?php echo esc_attr( $buyer ? $buyer->display_name : '' ); ?>"
				class="wpss-request-card__buyer-avatar">
			<div class="wpss-request-card__buyer-info">
				<span class="wpss-request-card__buyer-name">
					<?php echo esc_html( $buyer ? $buyer->display_name : __( 'Anonymous', 'wp-sell-services' ) ); ?>
				</span>
				<span class="wpss-request-card__date">
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
			<span class="wpss-request-card__category"><?php echo esc_html( $categories[0] ); ?></span>
		<?php endif; ?>

		<?php
		/**
		 * Hook: wpss_request_card_header
		 *
		 * Fires after buyer info in the header area.
		 *
		 * @since 1.0.0
		 *
		 * @param int $request_id Request post ID.
		 */
		do_action( 'wpss_request_card_header', $request_id );
		?>
	</div>

	<div class="wpss-request-card__body">
		<h3 class="wpss-request-card__title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h3>

		<div class="wpss-request-card__excerpt">
			<?php echo wp_kses_post( wp_trim_words( get_the_content(), 40, '...' ) ); ?>
		</div>

		<?php if ( ! empty( $skills ) ) : ?>
			<div class="wpss-request-card__skills">
				<?php foreach ( array_slice( $skills, 0, 5 ) as $skill ) : ?>
					<span class="wpss-request-card__skill"><?php echo esc_html( $skill ); ?></span>
				<?php endforeach; ?>
				<?php if ( count( $skills ) > 5 ) : ?>
					<span class="wpss-request-card__skill wpss-request-card__skill--more">
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

		<?php
		/**
		 * Hook: wpss_request_card_meta
		 *
		 * Fires after skills display, useful for custom metadata.
		 *
		 * @since 1.0.0
		 *
		 * @param int $request_id Request post ID.
		 */
		do_action( 'wpss_request_card_meta', $request_id );
		?>
	</div>

	<div class="wpss-request-card__footer">
		<?php
		/**
		 * Hook: wpss_request_card_footer
		 *
		 * Fires inside footer area, before meta items and actions.
		 *
		 * @since 1.0.0
		 *
		 * @param int $request_id Request post ID.
		 */
		do_action( 'wpss_request_card_footer', $request_id );
		?>
		<div class="wpss-request-card__meta">
			<div class="wpss-request-card__meta-item">
				<span class="wpss-request-card__meta-label"><?php esc_html_e( 'Budget', 'wp-sell-services' ); ?></span>
				<span class="wpss-request-card__meta-value"><?php echo esc_html( $budget_display ); ?></span>
			</div>

			<?php if ( $delivery_days ) : ?>
				<div class="wpss-request-card__meta-item">
					<span class="wpss-request-card__meta-label"><?php esc_html_e( 'Delivery', 'wp-sell-services' ); ?></span>
					<span class="wpss-request-card__meta-value">
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

			<div class="wpss-request-card__meta-item">
				<span class="wpss-request-card__meta-label"><?php esc_html_e( 'Proposals', 'wp-sell-services' ); ?></span>
				<span class="wpss-request-card__meta-value"><?php echo esc_html( number_format_i18n( $proposal_count ) ); ?></span>
			</div>
		</div>

		<div class="wpss-request-card__actions">
			<?php if ( $time_remaining && 'Expired' !== $time_remaining ) : ?>
				<span class="wpss-request-card__expires">
					<i data-lucide="clock" class="wpss-icon wpss-icon--sm wpss-request-card__expires-icon" aria-hidden="true"></i>
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
	 * Fires after the request card wrapper closes.
	 *
	 * @since 1.0.0
	 *
	 * @param int $request_id Request post ID.
	 */
	do_action( 'wpss_after_request_card', $request_id );
	?>
</article>
