<?php
/**
 * Template: No Services Found
 *
 * Displayed when no services match the query.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;
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

		<?php if ( is_search() ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: search query */
					esc_html__( 'No services match your search for "%s". Try different keywords or browse categories.', 'wp-sell-services' ),
					esc_html( get_search_query() )
				);
				?>
			</p>
		<?php elseif ( is_tax( 'wpss_service_category' ) ) : ?>
			<p><?php esc_html_e( 'No services found in this category yet. Check back soon or browse other categories.', 'wp-sell-services' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'No services available at the moment. Check back soon!', 'wp-sell-services' ); ?></p>
		<?php endif; ?>

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
