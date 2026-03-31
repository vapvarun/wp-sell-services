<?php
/**
 * Template: Single Service
 *
 * This template displays a single service page.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/single-service.php
 *
 * Available Hooks:
 *
 * - wpss_single_service_layout (filter)
 *   Allows changing the layout type. Default: 'default'.
 *
 *   @param string $layout     Layout type ('default', 'wide', 'minimal').
 *   @param int    $service_id Service post ID.
 *
 * - wpss_before_single_service (action)
 *   Fires before the single service wrapper.
 *   @param WPSellServices\Models\Service $service Service object.
 *
 * - wpss_single_service_header (action)
 *   Main header area - breadcrumb, title, meta info.
 *   @param WPSellServices\Models\Service $service Service object.
 *   @hooked wpss_service_breadcrumb - 5
 *   @hooked wpss_service_title - 10
 *   @hooked wpss_service_meta - 15
 *
 * - wpss_single_service_meta (action)
 *   Meta info area - vendor, rating, orders.
 *   @param int $service_id Service post ID.
 *
 * - wpss_single_service_gallery (action)
 *   Image gallery area.
 *   @param WPSellServices\Models\Service $service Service object.
 *   @hooked wpss_service_gallery - 10
 *
 * - wpss_single_service_content (action)
 *   Main content area - description, vendor info.
 *   @param WPSellServices\Models\Service $service Service object.
 *   @hooked wpss_service_description - 10
 *   @hooked wpss_service_about_vendor - 20
 *
 * - wpss_single_service_faqs (action)
 *   FAQs section.
 *   @param WPSellServices\Models\Service $service Service object.
 *   @hooked wpss_service_faqs - 10
 *
 * - wpss_single_service_reviews (action)
 *   Reviews section.
 *   @param WPSellServices\Models\Service $service Service object.
 *   @hooked wpss_service_reviews - 10
 *
 * - wpss_single_service_sidebar (action)
 *   Sidebar area - packages, vendor card.
 *   @param WPSellServices\Models\Service $service Service object.
 *   @hooked wpss_service_packages_widget - 10
 *   @hooked wpss_service_vendor_card - 20
 *
 * - wpss_single_service_related (action)
 *   Related services section.
 *   @param WPSellServices\Models\Service $service Service object.
 *   @hooked wpss_related_services - 10
 *
 * - wpss_after_single_service (action)
 *   Fires after the single service wrapper.
 *   @param WPSellServices\Models\Service $service Service object.
 *
 * @package WPSellServices\Templates
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

get_header();

$service    = wpss_get_service( get_the_ID() );
$service_id = get_the_ID();

if ( ! $service ) {
	wp_safe_redirect( home_url() );
	exit;
}

/**
 * Filter: wpss_single_service_layout
 *
 * Allows changing the layout type for single service page.
 *
 * @since 1.0.0
 *
 * @param string $layout     Layout type. Default 'default'. Accepts 'default', 'wide', 'minimal'.
 * @param int    $service_id Service post ID.
 */
$layout = apply_filters( 'wpss_single_service_layout', 'default', $service_id );

/**
 * Hook: wpss_before_single_service
 *
 * @param WPSellServices\Models\Service $service Service object.
 */
do_action( 'wpss_before_single_service', $service );
?>

<div class="wpss-single-service wpss-layout-<?php echo esc_attr( $layout ); ?>">
	<div class="wpss-container">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
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
					 * Hook: wpss_single_service_meta
					 *
					 * Fires in the meta info area.
					 * Use this to add custom meta information like badges, stats, etc.
					 *
					 * @since 1.0.0
					 *
					 * @param int $service_id Service post ID.
					 */
					do_action( 'wpss_single_service_meta', $service_id );

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
			 * Hook: wpss_single_service_portfolio
			 *
			 * @hooked SingleServiceView::render_portfolio - 10
			 *
			 * @param \WPSellServices\Models\Service $service Service object.
			 */
			do_action( 'wpss_single_service_portfolio', $service );

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
