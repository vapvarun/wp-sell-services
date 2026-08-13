<?php
/**
 * Page dropdown walker that disambiguates same-titled pages.
 *
 * @package WPSellServices\Admin
 * @since   1.5.1
 */

declare(strict_types=1);

namespace WPSellServices\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Appends each page's slug to its label in a page dropdown.
 *
 * WordPress labels every option with the page TITLE alone in wp_dropdown_pages(). That is
 * fine until a site has more than one page with the same title, at which point
 * the owner is asked to pick the right one from a list of identical entries.
 *
 * This is not hypothetical. A site in testing carried EIGHTEEN published pages
 * titled "Cart" - one of them WooCommerce's, the rest carrying [wpss_cart] -
 * and the Pages settings panel rendered eighteen indistinguishable "Cart"
 * options. There was no way to tell which one the store actually used.
 *
 * The slug is the thing that disambiguates them (cart, cart-2 ... cart-16), so
 * the label becomes "Cart (cart-16)". Only pages that share a title with
 * another page are annotated, so the common case - every page uniquely titled -
 * looks exactly as it did before.
 *
 * @since 1.5.1
 */
class PageDropdownWalker extends \Walker_PageDropdown {

	/**
	 * Titles that appear on more than one page, lowercased.
	 *
	 * @var array<string, bool>
	 */
	private array $ambiguous = array();

	/**
	 * Build the ambiguous-title index once.
	 *
	 * @param array<int, \WP_Post> $pages Pages the dropdown will render.
	 */
	public function __construct( array $pages = array() ) {
		$seen = array();

		foreach ( $pages as $page ) {
			$key = strtolower( trim( (string) $page->post_title ) );

			if ( isset( $seen[ $key ] ) ) {
				$this->ambiguous[ $key ] = true;
				continue;
			}

			$seen[ $key ] = true;
		}
	}

	/**
	 * Render one option, appending the slug when the title is ambiguous.
	 *
	 * @param string               $output            Accumulated output, passed by reference.
	 * @param \WP_Post             $data_object       Page object.
	 * @param int                  $depth             Depth of the page in the tree.
	 * @param array<string, mixed> $args      Dropdown arguments.
	 * @param int                  $current_object_id Currently selected page ID.
	 * @return void
	 */
	public function start_el( &$output, $data_object, $depth = 0, $args = array(), $current_object_id = 0 ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		$pad      = str_repeat( '&nbsp;', $depth * 3 );
		$selected = (int) $args['selected'] === (int) $data_object->ID ? ' selected="selected"' : '';
		$title    = $data_object->post_title;

		if ( '' === trim( (string) $title ) ) {
			/* translators: %d: page ID for a page with no title */
			$title = sprintf( __( '#%d (no title)', 'wp-sell-services' ), $data_object->ID );
		}

		if ( isset( $this->ambiguous[ strtolower( trim( (string) $data_object->post_title ) ) ] ) ) {
			// Slug AND id. The slug is what an owner recognises, but it is not
			// guaranteed unique either - the site that prompted this carried two
			// pairs of pages sharing a slug (cart-5 twice, cart-6 twice), which
			// would have left two entries still identical. The id always
			// disambiguates.
			$title = sprintf(
				/* translators: 1: page title, 2: page slug, 3: page ID */
				__( '%1$s (%2$s, #%3$d)', 'wp-sell-services' ),
				$title,
				$data_object->post_name ? $data_object->post_name : __( 'no slug', 'wp-sell-services' ),
				$data_object->ID
			);
		}

		$output .= "\t<option class=\"level-{$depth}\" value=\"{$data_object->ID}\"{$selected}>";
		$output .= $pad . esc_html( $title );
		$output .= "</option>\n";
	}
}
