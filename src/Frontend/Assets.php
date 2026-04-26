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

		add_filter( 'style_loader_src', array( __CLASS__, 'filter_loader_src' ), 10, 2 );
		add_filter( 'script_loader_src', array( __CLASS__, 'filter_loader_src' ), 10, 2 );
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
