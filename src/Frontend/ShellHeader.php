<?php
/**
 * Shell Header — the one page-header component for every plugin surface.
 *
 * Implements F1 of plan/ux-uplift-findings.md: the plugin app shell owns the
 * page header on every plugin surface (archive, single, dashboard, account,
 * vendor, requests, cart/order). One `wpss-page-header` component renders the
 * title, optional subtitle, and optional actions slot. The active theme's own
 * entry-title is suppressed on plugin-shell pages through a theme-agnostic
 * compat layer — a documented filter (`wpss_suppress_theme_title`) plus a
 * body class, never a raw `display:none` hack against specific theme
 * selectors. This is the WooCommerce-grade approach: plugin surfaces feel
 * like one product regardless of theme.
 *
 * @package WPSellServices\Frontend
 * @since   1.2.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the shared page header and suppresses the theme entry-title on
 * plugin-shell surfaces.
 *
 * @since 1.2.0
 */
class ShellHeader {

	/**
	 * Whether the title-suppression filters have been registered.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * Register the theme-agnostic entry-title suppression layer.
	 *
	 * Called once from Frontend::__construct() (front-end requests only) so the
	 * filters are in place before `the_title`, `body_class`, and the
	 * `get_post_metadata` short-circuit fire during the main query render.
	 *
	 * @return void
	 */
	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		// Tag the <body> so themes / page builders that gate their title on a
		// body class can opt out, and so site owners get a stable hook for any
		// last-mile CSS. The plugin ships no selector-specific display:none.
		add_filter( 'body_class', array( __CLASS__, 'filter_body_class' ) );

		// Theme-agnostic entry-title suppression: blank the post title in the
		// main loop on plugin-shell pages. Themes read the title through
		// `the_title` (and `get_the_title`) for their <h1 class="entry-title">,
		// so returning '' there removes the duplicate heading without touching
		// any theme-specific markup. Guarded tightly to the main query title of
		// the queried object only — menus, widgets, related-card titles, and
		// admin output are never affected.
		add_filter( 'the_title', array( __CLASS__, 'maybe_suppress_theme_title' ), 10, 2 );

		/*
		 * Repair the one nav-menu label our suppression can break.
		 *
		 * wp_setup_nav_menu_item() builds a page menu item's label with
		 * get_the_title( $object_id ) - the SAME id and the SAME filter as the page
		 * being viewed - so blanking the title makes WordPress substitute its own
		 * placeholder and the "Dashboard" item in the site menu renders as
		 * "#153 (no title)" while you are on the dashboard. Measured, not theorised:
		 * it is what the first attempt at this fix actually produced.
		 *
		 * Two approaches were tried and rejected before this one. Excluding menus
		 * with a flag set around wp_nav_menu() depends on when a theme renders its
		 * menu relative to its title, and on BuddyX the flag was still set when the
		 * entry-title rendered, so the duplicate H1 came straight back. Hiding
		 * .entry-title with CSS leaves both headings in the accessibility tree,
		 * which is the actual thing being fixed.
		 *
		 * This repairs the label instead, from the RAW post_title, and only for the
		 * single item that points at the page currently being viewed. No flags, no
		 * assumptions about theme hook order.
		 */
		add_filter( 'wp_setup_nav_menu_item', array( __CLASS__, 'restore_nav_menu_item_title' ) );
	}

	/**
	 * Decide whether the current request is a plugin-shell surface whose theme
	 * entry-title should be suppressed in favour of the plugin page header.
	 *
	 * Site owners can override the decision per request:
	 *
	 *     add_filter( 'wpss_suppress_theme_title', '__return_false' ); // keep theme title
	 *
	 * @return bool True when the theme's entry-title should be suppressed.
	 */
	public static function is_shell_surface(): bool {
		/*
		 * ONLY pages that print their own <h1>.
		 *
		 * That is the precondition for suppressing the theme's title, and it is
		 * easy to miss: this list is about "who owns the heading", not "which
		 * pages belong to the plugin". Adding a page that has NO heading of its
		 * own leaves it with no <h1> at all - worse than the duplicate it was
		 * meant to fix, and invisible in a diff.
		 *
		 * Measured on BuddyX at 1.6.0 (Basecamp 10208511245):
		 *
		 *   /service-cart/      theme H1 + plugin H1   -> suppress (added here)
		 *   /dashboard/         theme H1 + plugin H1   -> suppress (already here)
		 *   /                   plugin H1 only         -> correct already
		 *   /service-checkout/  theme H1 ONLY          -> must NOT suppress
		 *   /become-a-vendor/   theme H1 ONLY          -> must NOT suppress
		 *   /vendors/           theme H1 ONLY          -> must NOT suppress
		 *
		 * The card that reported this asked for checkout, become_vendor and
		 * vendors_page to be added too. They are deliberately left out: each has
		 * a single, correct H1 today, and it is the theme's. Give one of them its
		 * own ShellHeader::render() heading first, then add it here - in that
		 * order.
		 */
		$page_keys = array( 'dashboard', 'services_page', 'cart' );

		foreach ( $page_keys as $page_key ) {
			if ( \wpss_is_page( $page_key ) ) {
				return self::filter_shell_surface( true );
			}
		}

		$is_shell = (
			\is_singular( 'wpss_service' )
			|| \is_post_type_archive( 'wpss_service' )
			|| \is_tax( 'wpss_service_category' )
			|| \is_tax( 'wpss_service_tag' )
			|| \is_singular( 'wpss_request' )
			|| \is_post_type_archive( 'wpss_request' )
			|| (bool) \get_query_var( 'wpss_vendor' )
		);

		return self::filter_shell_surface( $is_shell );
	}

	/**
	 * Apply the public override filter to a shell decision.
	 *
	 * Extracted so every return path in is_shell_surface() goes through the
	 * filter — an early return that skipped it would silently ignore a site's
	 * `wpss_suppress_theme_title` override on exactly the pages it most wants to
	 * control.
	 *
	 * @since 1.6.0
	 *
	 * @param bool $is_shell Whether this is a plugin-shell surface.
	 * @return bool
	 */
	private static function filter_shell_surface( bool $is_shell ): bool {

		/**
		 * Filter whether the active theme's entry-title is suppressed on the
		 * current plugin-shell surface.
		 *
		 * Theme-agnostic by design: instead of fighting a specific theme's
		 * `.entry-title` selector with CSS, the plugin removes the title at the
		 * source (`the_title`) so any theme that renders the post title stops
		 * showing it. Return false to keep the theme title (e.g. a theme that
		 * already hides it, or a site that wants both).
		 *
		 * @since 1.2.0
		 *
		 * @param bool $is_shell Whether this is a plugin-shell surface.
		 */
		return (bool) \apply_filters( 'wpss_suppress_theme_title', $is_shell );
	}

	/**
	 * Add a stable body class on plugin-shell surfaces.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[] Filtered body classes.
	 */
	public static function filter_body_class( array $classes ): array {
		if ( self::is_shell_surface() ) {
			$classes[] = 'wpss-plugin-shell';
		}
		return $classes;
	}

	/**
	 * Blank the queried object's title in the main loop on shell surfaces.
	 *
	 * Only the main-query title of the queried object is suppressed, so the
	 * plugin's own `wpss-page-header` is the single visible heading. Every
	 * other `the_title` call (menus, related-service cards, widgets, REST,
	 * admin) is left untouched.
	 *
	 * @param string $title The post title.
	 * @param int    $id    The post ID, when available.
	 * @return string Possibly-emptied title.
	 */
	public static function maybe_suppress_theme_title( string $title, $id = 0 ): string {
		/*
		 * `in_the_loop()` is NOT required, and requiring it was the bug.
		 *
		 * Themes do not agree on where they print the entry title. BuddyX renders
		 * it from a template part that runs OUTSIDE the loop on the dashboard: the
		 * probe showed `the_title` firing four times for the queried page, three
		 * with in_the_loop() false, and the heading that reached the screen was one
		 * of those three. So the dashboard carried the plugin-shell body class,
		 * passed every other check, and still showed two H1s (Basecamp
		 * 10208511245).
		 *
		 * What keeps this tight is the pair below, not the loop: the MAIN query,
		 * and an id that matches the queried object. A menu item, a related-service
		 * card, a widget or a breadcrumb ancestor all carry a different post id and
		 * are untouched.
		 *
		 * Checked on BuddyX before relying on it: the document <title> and the
		 * breadcrumb's current-page label both come from single_post_title(), which
		 * does not run through `the_title`, so neither goes blank. Breadcrumb
		 * ANCESTORS use get_the_title( $other_id ) and are guarded by the id match.
		 */
		if ( \is_admin() || ! \is_main_query() ) {
			return $title;
		}

		if ( ! self::is_shell_surface() ) {
			return $title;
		}

		$queried_id = \get_queried_object_id();
		if ( $queried_id && (int) $id === (int) $queried_id ) {
			return '';
		}

		return $title;
	}

	/**
	 * Give back a menu item's label when it points at the suppressed page.
	 *
	 * Only ever touches an item whose linked object IS the queried page, and only
	 * when the item carries no custom label of its own - a menu item renamed by
	 * hand keeps its name.
	 *
	 * @since 1.6.0
	 *
	 * @param object $menu_item Menu item, already set up by WordPress.
	 * @return object
	 */
	public static function restore_nav_menu_item_title( $menu_item ) {
		if ( \is_admin() || ! \is_object( $menu_item ) ) {
			return $menu_item;
		}

		$object_id = (int) ( $menu_item->object_id ?? 0 );
		$queried   = (int) \get_queried_object_id();

		if ( ! $object_id || ! $queried || $object_id !== $queried ) {
			return $menu_item;
		}

		if ( ! self::is_shell_surface() ) {
			return $menu_item;
		}

		// A hand-written label lives on the nav_menu_item post itself. Read it raw:
		// by this point core may already have replaced $menu_item->post_title with
		// its "#123 (no title)" placeholder, so the item's own record is the only
		// honest source.
		$item_post = isset( $menu_item->db_id ) ? \get_post( (int) $menu_item->db_id ) : null;

		if ( $item_post && '' !== (string) $item_post->post_title ) {
			return $menu_item;
		}

		$linked = \get_post( $object_id );

		if ( $linked && '' !== (string) $linked->post_title ) {
			$menu_item->title = $linked->post_title;
		}

		return $menu_item;
	}

	/**
	 * Render the one page-header component used on every plugin surface.
	 *
	 * @param array<string, mixed> $args {
	 *     Header arguments.
	 *
	 *     @type string $title    Required. The page heading text.
	 *     @type string $subtitle Optional. Sub-heading / tagline text.
	 *     @type string $actions  Optional. Pre-escaped HTML for the actions slot
	 *                            (e.g. a primary button). Rendered as-is, so the
	 *                            caller is responsible for escaping it.
	 *     @type bool   $echo     Optional. Whether to echo (true) or return the
	 *                            markup. Default true.
	 * }
	 * @return string The header markup. Empty string when no title is given.
	 */
	public static function render( array $args = array() ): string {
		$args = \wp_parse_args(
			$args,
			array(
				'title'    => '',
				'subtitle' => '',
				'actions'  => '',
				'echo'     => true,
			)
		);

		if ( '' === (string) $args['title'] ) {
			return '';
		}

		\ob_start();
		?>
		<header class="wpss-page-header">
			<div class="wpss-page-header__main">
				<h1 class="wpss-page-header__title"><?php echo \esc_html( (string) $args['title'] ); ?></h1>
				<?php if ( '' !== (string) $args['subtitle'] ) : ?>
					<p class="wpss-page-header__subtitle"><?php echo \esc_html( (string) $args['subtitle'] ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( '' !== (string) $args['actions'] ) : ?>
				<div class="wpss-page-header__actions">
					<?php echo $args['actions']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Caller supplies pre-escaped action markup. ?>
				</div>
			<?php endif; ?>
		</header>
		<?php
		$html = (string) \ob_get_clean();

		if ( $args['echo'] ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from esc_html() above plus caller pre-escaped actions.
		}

		return $html;
	}
}
