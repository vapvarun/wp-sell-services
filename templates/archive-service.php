<?php
/**
 * Template: Service Archive
 *
 * This template displays the services listing/archive page.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/archive-service.php
 *
 * Available Hooks:
 *
 * - wpss_archive_service_columns (filter)
 *   Controls number of columns in services grid. Default: 3.
 *
 *   @param int $columns Number of grid columns (2, 3, or 4).
 *
 * - wpss_services_per_page (filter)
 *   Controls number of services displayed per page. Default: 12.
 *   @param int $per_page Number of services per page.
 *
 * - wpss_before_service_archive (action)
 *   Fires before the service archive wrapper.
 *
 * - wpss_service_archive_header (action)
 *   Archive header area - title, filters.
 *   @hooked wpss_archive_header - 10
 *   @hooked wpss_archive_filters - 20
 *
 * - wpss_before_service_loop (action)
 *   Fires before the services loop.
 *   @hooked wpss_archive_sidebar_toggle - 10
 *
 * - wpss_after_service_loop (action)
 *   Fires after the services loop.
 *   @hooked wpss_archive_pagination - 10
 *
 * - wpss_service_archive_sidebar (action)
 *   Archive sidebar area - filters, categories.
 *   @hooked wpss_archive_sidebar - 10
 *
 * - wpss_after_service_archive (action)
 *   Fires after the service archive wrapper.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

/**
 * Filter: wpss_archive_service_columns
 *
 * Controls the number of columns in the services grid.
 *
 * @since 1.0.0
 *
 * @param int $columns Number of columns. Default 3. Accepts 2, 3, or 4.
 */
$columns = apply_filters( 'wpss_archive_service_columns', 3 );

/**
 * Filter: wpss_services_per_page
 *
 * Controls how many services are displayed per page.
 *
 * @since 1.0.0
 *
 * @param int $per_page Number of services per page. Default 12.
 */
$per_page = apply_filters( 'wpss_services_per_page', 12 );

/**
 * Hook: wpss_before_service_archive
 */
do_action( 'wpss_before_service_archive' );
?>

<div class="wpss-services-archive">
	<div class="wpss-container">
		<div class="wpss-archive-header-wrap">
			<?php
			/**
			 * Hook: wpss_service_archive_header
			 *
			 * @hooked wpss_archive_header - 10
			 * @hooked wpss_archive_filters - 20
			 */
			do_action( 'wpss_service_archive_header' );
			?>
		</div>

		<div class="wpss-services-content">
			<?php
			/**
			 * Hook: wpss_before_service_loop
			 *
			 * @hooked wpss_archive_sidebar_toggle - 10
			 */
			do_action( 'wpss_before_service_loop' );

			if ( have_posts() ) :
				?>
				<div class="wpss-services-grid wpss-grid-columns-<?php echo esc_attr( $columns ); ?>">
					<?php
					while ( have_posts() ) :
						the_post();
						wpss_get_template_part( 'content', 'service-card' );
					endwhile;
					?>
				</div>

				<?php
				/**
				 * Hook: wpss_after_service_loop
				 *
				 * @hooked wpss_archive_pagination - 10
				 */
				do_action( 'wpss_after_service_loop' );

			else :
				wpss_get_template_part( 'content', 'no-services' );
			endif;
			?>
		</div>

		<?php
		/**
		 * Hook: wpss_service_archive_sidebar
		 *
		 * @hooked wpss_archive_sidebar - 10
		 */
		do_action( 'wpss_service_archive_sidebar' );
		?>
	</div>
</div>

<?php
/**
 * Hook: wpss_after_service_archive
 */
do_action( 'wpss_after_service_archive' );

get_footer();
