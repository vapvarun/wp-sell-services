<?php
/**
 * Template: Buyer Request Archive
 *
 * This template displays the buyer requests listing page.
 * Vendors can browse and respond to buyer job postings.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/archive-request.php
 *
 * Available Hooks:
 *
 * - wpss_archive_request_columns (filter)
 *   Controls number of columns in requests grid. Default: 2.
 *
 *   @param int $columns Number of grid columns (1, 2, or 3).
 *
 * - wpss_requests_per_page (filter)
 *   Controls number of requests displayed per page. Default: 10.
 *   @param int $per_page Number of requests per page.
 *
 * - wpss_before_request_archive (action)
 *   Fires before the request archive wrapper.
 *
 * - wpss_request_archive_header (action)
 *   Archive header area - title, filters.
 *   @hooked wpss_request_archive_title - 10
 *   @hooked wpss_request_archive_filters - 20
 *
 * - wpss_before_request_loop (action)
 *   Fires before the requests loop.
 *   @hooked wpss_request_results_info - 10
 *
 * - wpss_after_request_loop (action)
 *   Fires after the requests loop.
 *   @hooked wpss_request_pagination - 10
 *
 * - wpss_request_archive_sidebar (action)
 *   Archive sidebar area - filters, categories.
 *   @hooked wpss_request_filters_sidebar - 10
 *
 * - wpss_after_request_archive (action)
 *   Fires after the request archive wrapper.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

/**
 * Filter: wpss_archive_request_columns
 *
 * Controls the number of columns in the requests grid.
 *
 * @since 1.0.0
 *
 * @param int $columns Number of columns. Default 2. Accepts 1, 2, or 3.
 */
$columns = apply_filters( 'wpss_archive_request_columns', 2 );

/**
 * Filter: wpss_requests_per_page
 *
 * Controls how many requests are displayed per page.
 *
 * @since 1.0.0
 *
 * @param int $per_page Number of requests per page. Default 10.
 */
$per_page = apply_filters( 'wpss_requests_per_page', 10 );

/**
 * Hook: wpss_before_request_archive
 */
do_action( 'wpss_before_request_archive' );
?>

<div class="wpss-requests-archive">
	<div class="wpss-container">
		<?php
		/**
		 * Hook: wpss_request_archive_header
		 *
		 * @hooked wpss_request_archive_title - 10
		 * @hooked wpss_request_archive_filters - 20
		 */
		do_action( 'wpss_request_archive_header' );
		?>

		<div class="wpss-requests-content">
			<?php
			/**
			 * Hook: wpss_before_request_loop
			 *
			 * @hooked wpss_request_results_info - 10
			 */
			do_action( 'wpss_before_request_loop' );

			if ( have_posts() ) :
				?>
				<div class="wpss-requests-list wpss-grid-columns-<?php echo esc_attr( $columns ); ?>">
					<?php
					while ( have_posts() ) :
						the_post();
						wpss_get_template_part( 'content', 'request-card' );
					endwhile;
					?>
				</div>

				<?php
				/**
				 * Hook: wpss_after_request_loop
				 *
				 * @hooked wpss_request_pagination - 10
				 */
				do_action( 'wpss_after_request_loop' );

			else :
				wpss_get_template_part( 'content', 'no-requests' );
			endif;
			?>
		</div>

		<?php
		/**
		 * Hook: wpss_request_archive_sidebar
		 *
		 * @hooked wpss_request_filters_sidebar - 10
		 */
		do_action( 'wpss_request_archive_sidebar' );
		?>
	</div>
</div>

<?php
/**
 * Hook: wpss_after_request_archive
 */
do_action( 'wpss_after_request_archive' );

get_footer();
