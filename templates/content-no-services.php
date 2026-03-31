<?php
/**
 * Template: No Services Found
 *
 * Displayed when no services match the query.
 *
 * Available hooks:
 * - wpss_no_services_content - After default message
 *
 * Available filters:
 * - wpss_no_services_message - Modify the message text
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Determine context-specific message.
if ( is_search() ) {
	$default_message = sprintf(
		/* translators: %s: search query */
		esc_html__( 'No services match your search for "%s". Try different keywords or browse categories.', 'wp-sell-services' ),
		esc_html( get_search_query() )
	);
} elseif ( is_tax( 'wpss_service_category' ) ) {
	$default_message = esc_html__( 'No services found in this category yet. Check back soon or browse other categories.', 'wp-sell-services' );
} else {
	$default_message = esc_html__( 'No services available at the moment. Check back soon!', 'wp-sell-services' );
}

// Allow filtering the message.
$message = apply_filters( 'wpss_no_services_message', $default_message );
?>

<div class="wpss-no-services">
	<div class="wpss-empty-state">
		<span class="wpss-empty-icon" aria-hidden="true">
			<svg viewBox="0 0 64 64" width="64" height="64">
				<circle cx="32" cy="32" r="30" fill="none" stroke="currentColor" stroke-width="2"/>
				<path d="M20 25 L44 25 M20 32 L38 32 M20 39 L30 39" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
			</svg>
		</span>

		<h2><?php esc_html_e( 'No services found', 'wp-sell-services' ); ?></h2>

		<p><?php echo wp_kses_post( $message ); ?></p>

		<?php
		/**
		 * Hook: wpss_no_services_content
		 *
		 * Fires after the default "no services" message.
		 * Use this to add custom content, links, or CTAs.
		 *
		 * @since 1.0.0
		 */
		do_action( 'wpss_no_services_content' );
		?>

		<div class="wpss-empty-actions">
			<?php if ( is_search() || is_tax() ) : ?>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'wpss_service' ) ); ?>"
					class="wpss-btn wpss-btn-primary">
					<?php esc_html_e( 'Browse All Services', 'wp-sell-services' ); ?>
				</a>
			<?php endif; ?>

			<?php if ( wpss_is_vendor() ) : ?>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpss_service' ) ); ?>"
					class="wpss-btn wpss-btn-outline">
					<?php esc_html_e( 'Create a Service', 'wp-sell-services' ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>
