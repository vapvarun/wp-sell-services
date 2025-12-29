<?php
/**
 * Template: No Requests Found
 *
 * Displayed when no buyer requests are found in archive.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/content-no-requests.php
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
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
		<?php esc_html_e( 'There are no active buyer requests at the moment. Check back later or try adjusting your filters.', 'wp-sell-services' ); ?>
	</p>

	<?php if ( ! empty( $_GET ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<a href="<?php echo esc_url( get_post_type_archive_link( 'wpss_request' ) ); ?>" class="wpss-btn wpss-btn-outline">
			<?php esc_html_e( 'Clear Filters', 'wp-sell-services' ); ?>
		</a>
	<?php endif; ?>
</div>
