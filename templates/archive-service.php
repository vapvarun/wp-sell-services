<?php
/**
 * Template: Service Archive
 *
 * This template displays the services listing/archive page.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/archive-service.php
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

/**
 * Hook: wpss_before_service_archive
 */
do_action( 'wpss_before_service_archive' );
?>

<div class="wpss-services-archive">
	<div class="wpss-container">
		<?php
		/**
		 * Hook: wpss_service_archive_header
		 *
		 * @hooked wpss_archive_header - 10
		 * @hooked wpss_archive_filters - 20
		 */
		do_action( 'wpss_service_archive_header' );
		?>

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
				<div class="wpss-services-grid">
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
