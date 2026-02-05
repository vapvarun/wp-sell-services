<?php
/**
 * Template: No Requests Found
 *
 * Displayed when no buyer requests are found in archive.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/content-no-requests.php
 *
 * Available hooks:
 * - wpss_no_requests_content - After default message
 *
 * Available filters:
 * - wpss_no_requests_message - Modify the message text
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Default message.
$default_message = esc_html__( 'There are no active buyer requests at the moment. Check back later or try adjusting your filters.', 'wp-sell-services' );

// Allow filtering the message.
$message = apply_filters( 'wpss_no_requests_message', $default_message );
?>

<div class="wpss-no-results wpss-no-requests">
	<div class="wpss-no-results-icon">
		<svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="1.5">
			<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
		</svg>
	</div>

	<h3 class="wpss-no-results-title">
		<?php esc_html_e( 'No buyer requests found', 'wp-sell-services' ); ?>
	</h3>

	<p class="wpss-no-results-message">
		<?php echo wp_kses_post( $message ); ?>
	</p>

	<?php
	/**
	 * Hook: wpss_no_requests_content
	 *
	 * Fires after the default "no requests" message.
	 * Use this to add custom content, links, or CTAs.
	 *
	 * @since 1.0.0
	 */
	do_action( 'wpss_no_requests_content' );
	?>

	<?php if ( ! empty( $_GET ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'wpss_request' ) ); ?>" class="wpss-btn wpss-btn-outline">
			<?php esc_html_e( 'Clear Filters', 'wp-sell-services' ); ?>
		</a>
	<?php endif; ?>
</div>
