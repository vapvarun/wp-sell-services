<?php
/**
 * Script registration + translation pairing.
 *
 * Every plugin-owned JavaScript file needs two things to be translatable:
 * a `wp_register_script()` / `wp_enqueue_script()` call, and a matching
 * `wp_set_script_translations()` call. Historically those were written as
 * two independent statements at each call site, so the second one was
 * simply forgotten — `wpss-ui` alone is registered from four different
 * classes and picked up the translations call in none of them.
 *
 * This class makes the pair atomic: you cannot register a plugin script
 * through it without the script also being told where its JSON catalog
 * lives. `bin/i18n-verify.py` recognises these helpers, so a handle wired
 * up here satisfies the release gate without a second statement.
 *
 * Vendor bundles (Alpine, Lucide, Pusher, Shepherd, Stripe/PayPal SDKs)
 * deliberately do NOT go through here — they ship their own strings and
 * must not be handed our text domain.
 *
 * @package WPSellServices\Assets
 * @since   1.3.1
 */

declare(strict_types=1);

namespace WPSellServices\Assets;

defined( 'ABSPATH' ) || exit;

/**
 * Static registrar that keeps registration and translation together.
 *
 * @since 1.3.1
 */
class ScriptRegistry {

	/**
	 * Handle for the shared UI primitives bundle (wpssConfirm / wpssToast).
	 *
	 * Declared here because four separate surfaces need it (frontend
	 * bootstrap, the unified dashboard, every admin page, and the order
	 * metabox) and each used to carry its own copy of the registration.
	 *
	 * @var string
	 */
	public const HANDLE_UI = 'wpss-ui';

	/**
	 * Plugin-relative path to the UI primitives script.
	 *
	 * @var string
	 */
	private const SRC_UI = 'assets/js/wpss-ui.js';

	/**
	 * Register a plugin-owned script and point it at our JSON catalogs.
	 *
	 * @param string        $handle    Script handle.
	 * @param string        $rel_src   Path relative to the plugin root, e.g. `assets/js/frontend.js`.
	 * @param array<string> $deps      Dependency handles.
	 * @param bool          $in_footer Whether to print in the footer.
	 * @param string|null   $version   Version string; defaults to WPSS_VERSION.
	 * @return void
	 */
	public static function register(
		string $handle,
		string $rel_src,
		array $deps = array(),
		bool $in_footer = true,
		?string $version = null
	): void {
		wp_register_script(
			$handle,
			\WPSS_PLUGIN_URL . ltrim( $rel_src, '/' ),
			$deps,
			$version ?? \WPSS_VERSION,
			$in_footer
		);
		self::translate( $handle );
	}

	/**
	 * Register (if needed) and enqueue a plugin-owned script, with translations.
	 *
	 * @param string        $handle    Script handle.
	 * @param string        $rel_src   Path relative to the plugin root.
	 * @param array<string> $deps      Dependency handles.
	 * @param bool          $in_footer Whether to print in the footer.
	 * @param string|null   $version   Version string; defaults to WPSS_VERSION.
	 * @return void
	 */
	public static function enqueue(
		string $handle,
		string $rel_src,
		array $deps = array(),
		bool $in_footer = true,
		?string $version = null
	): void {
		self::register( $handle, $rel_src, $deps, $in_footer, $version );
		wp_enqueue_script( $handle );
	}

	/**
	 * Attach the JSON translation path to an already-registered handle.
	 *
	 * Useful for handles registered elsewhere (templates, third-party code)
	 * that we still own the strings for.
	 *
	 * @param string $handle Script handle.
	 * @return void
	 */
	public static function translate( string $handle ): void {
		wp_set_script_translations( $handle, 'wp-sell-services', \WPSS_PLUGIN_DIR . 'languages' );
	}

	/**
	 * Register the shared UI primitives bundle.
	 *
	 * The single owner of the `wpss-ui` handle. Registration is idempotent,
	 * so callers may call this unconditionally.
	 *
	 * @return void
	 */
	public static function register_ui(): void {
		self::register( self::HANDLE_UI, self::SRC_UI );
	}

	/**
	 * Register and enqueue the shared UI primitives bundle.
	 *
	 * @return void
	 */
	public static function enqueue_ui(): void {
		self::enqueue( self::HANDLE_UI, self::SRC_UI );
	}
}
