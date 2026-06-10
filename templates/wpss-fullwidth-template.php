<?php
/**
 * Template: Full-Width Plugin Page
 *
 * Sidebar-free page template used for the plugin's app-like pages
 * (dashboard, cart, checkout, become vendor). Registered via
 * TemplateLoader::template_include so the theme's blog sidebar and
 * widgets never render next to marketplace UI.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/wpss-fullwidth-template.php
 *
 * Opt out per site with:
 * add_filter( 'wpss_use_fullwidth_template', '__return_false' );
 *
 * @package WPSellServices\Templates
 * @since   1.2.0
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<?php // Container div, not <main>: most themes already open a <main> landmark in header.php. ?>
<div id="wpss-fullwidth-content" class="wpss-fullwidth-page">
	<?php
	while ( have_posts() ) {
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'wpss-fullwidth-article' ); ?>>
			<div class="wpss-fullwidth-inner">
				<?php the_content(); ?>
			</div>
		</article>
		<?php
	}
	?>
</div>

<?php
get_footer();
