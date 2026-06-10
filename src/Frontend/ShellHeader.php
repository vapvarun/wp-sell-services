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
		$is_shell = (
			\wpss_is_page( 'dashboard' )
			|| \wpss_is_page( 'services_page' )
			|| \is_singular( 'wpss_service' )
			|| \is_post_type_archive( 'wpss_service' )
			|| \is_tax( 'wpss_service_category' )
			|| \is_tax( 'wpss_service_tag' )
			|| \is_singular( 'wpss_request' )
			|| \is_post_type_archive( 'wpss_request' )
			|| (bool) \get_query_var( 'wpss_vendor' )
		);

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
		if ( \is_admin() || ! \is_main_query() || ! \in_the_loop() ) {
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
