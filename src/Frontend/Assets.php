<?php
/**
 * Asset loader source filter.
 *
 * Swaps plugin-owned `*.css` / `*.js` URLs to their `*.min.{css,js}`
 * siblings at runtime when minified files exist on disk and the request
 * is not in `SCRIPT_DEBUG` mode. Lets every existing `wp_enqueue_*` call
 * stay untouched — the helper hooks the global WordPress `*_loader_src`
 * filters and rewrites only URLs that point inside this plugin's
 * `assets/` directory.
 *
 * Both source and minified files ship in the release ZIP (the source
 * remains canonical and is what Translators / Local-by-Flywheel /
 * `SCRIPT_DEBUG` users see); the swap is purely a production
 * performance optimisation.
 *
 * @package WPSellServices\Frontend
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPSellServices\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Static helper that hooks the WP loader-src filters.
 *
 * @since 1.1.0
 */
class Assets {

	/**
	 * Cached file_exists() lookups so the same `.min.{css,js}` path
	 * isn't stat'd more than once per request.
	 *
	 * @var array<string, bool>
	 */
	private static array $exists_cache = array();

	/**
	 * Plugin-relative `assets/` URL prefix. Memoised on first lookup
	 * because `WPSS_PLUGIN_URL` is constant for the request lifetime.
	 *
	 * @var string|null
	 */
	private static ?string $assets_url_prefix = null;

	/**
	 * Plugin-absolute `assets/` filesystem path. Memoised once.
	 *
	 * @var string|null
	 */
	private static ?string $assets_dir_path = null;

	/**
	 * Register the loader-src filters.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Skip the swap entirely when SCRIPT_DEBUG is on — devs and
		// translators get the readable source files. Same convention WP
		// core itself uses for its own assets.
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return;
		}

		add_filter( 'style_loader_src', array( __CLASS__, 'filter_style_src' ), 10, 2 );
		add_filter( 'script_loader_src', array( __CLASS__, 'filter_loader_src' ), 10, 2 );
	}

	/**
	 * Style variant of the rewrite, which also keeps the RTL name buildable.
	 *
	 * WP_Styles::do_item() builds the RTL URL as
	 * `str_replace( "{$suffix}.css", "-rtl{$suffix}.css", … )`, where $suffix is
	 * whatever `wp_style_add_data( $handle, 'suffix', … )` recorded. Nothing
	 * recorded one, so with $suffix empty core rewrote the ALREADY minified URL
	 * this filter returns - turning `frontend.min.css` into
	 * `frontend.min-rtl.css`, while the build writes `frontend-rtl.min.css`.
	 *
	 * Every plugin stylesheet therefore 404d on any RTL site and the plugin
	 * rendered with no CSS at all (Basecamp 10320551778). Registering the rtl
	 * flag at each enqueue was necessary but not sufficient; this is the other
	 * half, and it belongs here because this is the one place that decides a
	 * minified file is being served.
	 *
	 * Order matters and core guarantees it: do_item() resolves the LTR href
	 * (running this filter) before it reads extra['suffix'] for the RTL one.
	 * Handles ending in `-rtl` are core asking for the RTL URL itself, so they
	 * are skipped - there is no such registered style to annotate.
	 *
	 * @param string $src    The full asset URL including `?ver=…`.
	 * @param string $handle Registered style handle.
	 * @return string Possibly-rewritten URL.
	 */
	public static function filter_style_src( $src, $handle ): string {
		$rewritten = self::filter_loader_src( $src, $handle );

		// A handle ending -rtl is core asking for the RTL URL itself; there is
		// no such registered style to annotate.
		if ( '-rtl' === substr( (string) $handle, -4 ) ) {
			return $rewritten;
		}

		if ( $rewritten !== $src ) {
			wp_style_add_data( $handle, 'suffix', '.min' );
		}

		self::maybe_pair_rtl( $handle, $rewritten, $rewritten !== $src );

		return $rewritten;
	}

	/**
	 * Serve a stylesheet's RTL sibling whenever one was actually built.
	 *
	 * Pairing used to be each enqueue's job, and five of them never did it -
	 * the Vendors screen, the service-edit metabox, and Pro's currency, EDD and
	 * WooCommerce sheets all shipped an -rtl.css that WordPress was never told
	 * about. On Vendors that was worse than nothing: an LTR sheet loaded on top
	 * of an RTL one and won wherever the two overlapped (Basecamp 10321368043).
	 *
	 * Remembering at 24 call sites is the thing that keeps failing, so the
	 * pairing is derived here instead: this filter already runs for every
	 * stylesheet whose URL is inside this plugin's assets dir, and core reads
	 * extra['rtl'] after resolving the LTR href, so a flag set here is seen.
	 * A future enqueue inherits the pairing without knowing it exists.
	 *
	 * Only ever set when the sibling is on disk - flagging a sheet that has no
	 * -rtl build would turn a working LTR page into a 404.
	 *
	 * An explicit value already registered is left alone, including a handle
	 * that points 'rtl' at a URL of its own.
	 *
	 * @param string $handle   Registered style handle.
	 * @param string $src      Resolved URL, after any .min rewrite.
	 * @param bool   $minified Whether that rewrite happened.
	 * @return void
	 */
	private static function maybe_pair_rtl( string $handle, string $src, bool $minified ): void {
		$styles = wp_styles();

		if ( ! $styles instanceof \WP_Styles || ! isset( $styles->registered[ $handle ] ) ) {
			return;
		}

		if ( isset( $styles->registered[ $handle ]->extra['rtl'] ) ) {
			return;
		}

		$path = self::url_to_path( explode( '?', $src, 2 )[0] );

		if ( null === $path ) {
			return;
		}

		// The name core will build: foo.min.css -> foo-rtl.min.css, and
		// foo.css -> foo-rtl.css when nothing was minified.
		$suffix   = $minified ? '.min' : '';
		$rtl_path = preg_replace( '#' . preg_quote( $suffix, '#' ) . '\.css$#i', '-rtl' . $suffix . '.css', $path );

		if ( ! is_string( $rtl_path ) || $rtl_path === $path || ! self::min_exists( $rtl_path ) ) {
			return;
		}

		wp_style_add_data( $handle, 'rtl', 'replace' );
	}

	/**
	 * Filter callback for both `style_loader_src` and `script_loader_src`.
	 *
	 * Returns the original `$src` unchanged when:
	 *   - The URL doesn't point inside this plugin's `assets/` tree.
	 *   - The URL is already `.min.css` / `.min.js`.
	 *   - The corresponding `.min` file doesn't exist on disk.
	 *
	 * Otherwise rewrites `foo.css` → `foo.min.css` (preserving the query
	 * string, including the cache-busting `?ver=` param appended by WP).
	 *
	 * @param string $src    The full asset URL including `?ver=…`.
	 * @param string $handle The handle that wp_enqueue_*() registered with
	 *                       (unused here — the filter is purely URL-based).
	 * @return string Possibly-rewritten URL.
	 */
	public static function filter_loader_src( $src, $handle ): string {
		$src = (string) $src;

		if ( '' === $src ) {
			return $src;
		}

		$prefix = self::get_assets_url_prefix();
		if ( '' === $prefix || strpos( $src, $prefix ) === false ) {
			return $src;
		}

		// Split off the query string so we don't rewrite inside it.
		$parts = explode( '?', $src, 2 );
		$path  = $parts[0];
		$query = isset( $parts[1] ) ? '?' . $parts[1] : '';

		// Skip already-minified URLs and skip the `vendor/` subtree
		// (third-party libs ship their own .min builds — we don't make
		// our own .min.min siblings).
		if ( preg_match( '#\.min\.(css|js)$#i', $path ) ) {
			return $src;
		}
		if ( strpos( $path, $prefix . 'js/vendor/' ) !== false || strpos( $path, $prefix . 'vendor/' ) !== false ) {
			return $src;
		}

		if ( ! preg_match( '#\.(css|js)$#i', $path, $match ) ) {
			return $src;
		}

		$ext       = $match[1];
		$min_path  = preg_replace( '#\.' . $ext . '$#i', '.min.' . $ext, $path );
		$file_path = self::url_to_path( $min_path );

		if ( null === $file_path ) {
			return $src;
		}

		if ( ! self::min_exists( $file_path ) ) {
			return $src;
		}

		return $min_path . $query;
	}

	/**
	 * Translate a plugin-asset URL back to its absolute filesystem path
	 * so we can `file_exists()`-check the minified sibling.
	 *
	 * Returns null when the URL doesn't resolve under our `assets/` dir
	 * (defensive — shouldn't happen given the prefix check above).
	 *
	 * @param string $url Asset URL (no query string).
	 * @return string|null Absolute path on disk, or null if outside the
	 *                     plugin assets dir.
	 */
	private static function url_to_path( string $url ): ?string {
		$prefix = self::get_assets_url_prefix();
		$dir    = self::get_assets_dir_path();
		if ( '' === $prefix || '' === $dir ) {
			return null;
		}
		$pos = strpos( $url, $prefix );
		if ( false === $pos ) {
			return null;
		}
		$rel = substr( $url, $pos + strlen( $prefix ) );
		return $dir . $rel;
	}

	/**
	 * Memoised `file_exists()` so the same path isn't hit twice.
	 *
	 * @param string $path Absolute filesystem path.
	 * @return bool True when the file exists.
	 */
	private static function min_exists( string $path ): bool {
		if ( ! isset( self::$exists_cache[ $path ] ) ) {
			self::$exists_cache[ $path ] = file_exists( $path );
		}
		return self::$exists_cache[ $path ];
	}

	/**
	 * Build (and memoise) the `assets/` URL prefix.
	 *
	 * @return string `https://example.com/wp-content/plugins/wp-sell-services/assets/` style URL.
	 */
	private static function get_assets_url_prefix(): string {
		if ( null === self::$assets_url_prefix ) {
			self::$assets_url_prefix = defined( 'WPSS_PLUGIN_URL' ) ? \WPSS_PLUGIN_URL . 'assets/' : '';
		}
		return self::$assets_url_prefix;
	}

	/**
	 * Build (and memoise) the `assets/` filesystem path.
	 *
	 * @return string Absolute path with trailing slash, or empty string
	 *                if the WPSS_PLUGIN_DIR constant is missing.
	 */
	private static function get_assets_dir_path(): string {
		if ( null === self::$assets_dir_path ) {
			self::$assets_dir_path = defined( 'WPSS_PLUGIN_DIR' ) ? \WPSS_PLUGIN_DIR . 'assets/' : '';
		}
		return self::$assets_dir_path;
	}
}
