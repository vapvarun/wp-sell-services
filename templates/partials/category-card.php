<?php
/**
 * Template: Category Card
 *
 * ONE category card, shared by [wpss_service_categories] and the
 * wpss/service-categories block.
 *
 * Override this template by copying to:
 * yourtheme/wp-sell-services/partials/category-card.php
 *
 * Before this partial existed the two surfaces rendered different cards: the
 * shortcode emitted <h3> with a bare <span class="{icon}"> and no fallback when
 * a category had neither icon nor image, while the block emitted <h4>, a
 * `dashicons`-prefixed span, a Lucide folder fallback and a lazy-loaded image.
 * A theme could style one and silently miss the other.
 *
 * Link target: the taxonomy archive via get_term_link(). Both this and the
 * `?category=N` form the block used were verified to filter correctly against
 * live data (Business -> 2 of 52 services; Digital Marketing -> 3). The term
 * link wins because it is a real, readable, indexable URL rather than an opaque
 * query parameter on the generic archive.
 *
 * Expected in scope:
 *
 * @var WP_Term $category   The category term.
 * @var bool    $show_count Whether to print the service count.
 * @var bool    $show_icon  Whether to print the icon when there is no image.
 * @var bool    $show_image Whether to print the term image when one is set.
 *
 * Available hooks:
 * - wpss_before_category_card - Before the card anchor
 * - wpss_after_category_card  - After the card anchor
 *
 * Available filters:
 * - wpss_category_card_classes - Modify card CSS classes
 * - wpss_category_card_link    - Modify the card's link target
 *
 * @package WPSellServices\Templates
 * @since   1.5.1
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $category ) || ! $category instanceof WP_Term ) {
	return;
}

$show_count = isset( $show_count ) ? (bool) $show_count : true;
$show_icon  = isset( $show_icon ) ? (bool) $show_icon : true;
$show_image = isset( $show_image ) ? (bool) $show_image : false;

$term_link = get_term_link( $category );
$card_link = is_wp_error( $term_link ) ? '' : $term_link;

/**
 * Filters the category card's link target.
 *
 * @since 1.5.1
 *
 * @param string  $card_link Link URL.
 * @param WP_Term $category  The category term.
 */
$card_link = (string) apply_filters( 'wpss_category_card_link', $card_link, $category );

$icon     = get_term_meta( $category->term_id, '_wpss_icon', true );
$image_id = get_term_meta( $category->term_id, '_wpss_image', true );
$image    = $image_id ? wp_get_attachment_image_url( (int) $image_id, 'medium' ) : '';

$card_classes = array( 'wpss-category-card' );

/**
 * Filters the category card CSS classes.
 *
 * @since 1.5.1
 *
 * @param string[] $card_classes Card classes.
 * @param WP_Term  $category     The category term.
 */
$card_classes = (array) apply_filters( 'wpss_category_card_classes', $card_classes, $category );

/**
 * Fires before the category card.
 *
 * @since 1.5.1
 *
 * @param WP_Term $category The category term.
 */
do_action( 'wpss_before_category_card', $category );
?>
<a href="<?php echo esc_url( $card_link ); ?>" class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $card_classes ) ) ); ?>">
	<?php if ( $show_image && $image ) : ?>
		<div class="wpss-category-image">
			<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $category->name ); ?>" loading="lazy">
		</div>
	<?php elseif ( $show_icon ) : ?>
		<div class="wpss-category-icon">
			<?php if ( $icon ) : ?>
				<span class="dashicons <?php echo esc_attr( $icon ); ?>"></span>
			<?php else : ?>
				<i data-lucide="folder" class="wpss-icon" aria-hidden="true"></i>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="wpss-category-content">
		<h3 class="wpss-category-name"><?php echo esc_html( $category->name ); ?></h3>

		<?php if ( $show_count ) : ?>
			<span class="wpss-category-count">
				<?php
				printf(
					/* translators: %d: number of services */
					esc_html( _n( '%d service', '%d services', (int) $category->count, 'wp-sell-services' ) ),
					absint( $category->count )
				);
				?>
			</span>
		<?php endif; ?>
	</div>
</a>
<?php
/**
 * Fires after the category card.
 *
 * @since 1.5.1
 *
 * @param WP_Term $category The category term.
 */
do_action( 'wpss_after_category_card', $category );
