<?php
/**
 * Template: Single Service
 *
 * This template displays a single service page.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/single-service.php
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$service = wpss_get_service( get_the_ID() );

if ( ! $service ) {
	wp_safe_redirect( home_url() );
	exit;
}

/**
 * Hook: wpss_before_single_service
 *
 * @param WPSellServices\Models\Service $service Service object.
 */
do_action( 'wpss_before_single_service', $service );
?>

<div class="wpss-single-service">
	<div class="wpss-container">
		<?php while ( have_posts() ) : the_post(); ?>
			<div class="wpss-service-layout">
				<div class="wpss-service-main">
					<?php
					/**
					 * Hook: wpss_single_service_header
					 *
					 * @hooked wpss_service_breadcrumb - 5
					 * @hooked wpss_service_title - 10
					 * @hooked wpss_service_meta - 15
					 */
					do_action( 'wpss_single_service_header', $service );

					/**
					 * Hook: wpss_single_service_gallery
					 *
					 * @hooked wpss_service_gallery - 10
					 */
					do_action( 'wpss_single_service_gallery', $service );

					/**
					 * Hook: wpss_single_service_content
					 *
					 * @hooked wpss_service_description - 10
					 * @hooked wpss_service_about_vendor - 20
					 */
					do_action( 'wpss_single_service_content', $service );

					/**
					 * Hook: wpss_single_service_faqs
					 *
					 * @hooked wpss_service_faqs - 10
					 */
					do_action( 'wpss_single_service_faqs', $service );

					/**
					 * Hook: wpss_single_service_reviews
					 *
					 * @hooked wpss_service_reviews - 10
					 */
					do_action( 'wpss_single_service_reviews', $service );
					?>
				</div>

				<aside class="wpss-service-sidebar">
					<?php
					/**
					 * Hook: wpss_single_service_sidebar
					 *
					 * @hooked wpss_service_packages_widget - 10
					 * @hooked wpss_service_vendor_card - 20
					 */
					do_action( 'wpss_single_service_sidebar', $service );
					?>
				</aside>
			</div>

			<?php
			/**
			 * Hook: wpss_single_service_related
			 *
			 * @hooked wpss_related_services - 10
			 */
			do_action( 'wpss_single_service_related', $service );
			?>
		<?php endwhile; ?>
	</div>
</div>

<?php
/**
 * Hook: wpss_after_single_service
 *
 * @param WPSellServices\Models\Service $service Service object.
 */
do_action( 'wpss_after_single_service', $service );

get_footer();
