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
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

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
				<div class="wpss-requests-list">
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
