<?php
/**
 * Core utilities: platform name, plugin instance, logging, sanitizers, admin/enqueue/form helpers.
 *
 * Anything domain-specific belongs in its domain file (see docs/standards/skeleton.md).
 *
 * @package WPSellServices
 * @since   1.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the platform name.
 *
 * @since 1.1.0
 *
 * @return string Platform name or site name as fallback.
 */
function wpss_get_platform_name(): string {
	$platform_name = (string) wpss_get_option( 'general', 'platform_name' );

	// Fall back to site name if empty.
	if ( empty( $platform_name ) ) {
		$platform_name = get_bloginfo( 'name' );
	}

	/**
	 * Filter the platform name.
	 *
	 * @since 1.1.0
	 * @param string $platform_name Platform name.
	 */
	return apply_filters( 'wpss_platform_name', $platform_name );
}

/**
 * Get the plugin instance.
 *
 * @return \WPSellServices\Core\Plugin
 */
function wpss(): \WPSellServices\Core\Plugin {
	return \WPSellServices\Core\Plugin::get_instance();
}

/**
 * Sanitize HTML content.
 *
 * @param string $content HTML content.
 * @return string
 */
function wpss_sanitize_html( string $content ): string {
	return wp_kses(
		$content,
		array(
			'a'          => array(
				'href'   => array(),
				'title'  => array(),
				'target' => array(),
				'rel'    => array(),
			),
			'br'         => array(),
			'em'         => array(),
			'strong'     => array(),
			'p'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'h1'         => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'h5'         => array(),
			'h6'         => array(),
			'blockquote' => array(),
			'code'       => array(),
			'pre'        => array(),
		)
	);
}

/**
 * Log message for debugging.
 *
 * @param mixed  $message Message to log.
 * @param string $level   Log level (info, warning, error).
 * @return void
 */
function wpss_log( $message, string $level = 'info' ): void {
	$plugin_debug = (bool) wpss_get_option( 'advanced', 'enable_debug_mode' );

	if ( ! $plugin_debug && ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) ) {
		return;
	}

	if ( ! is_string( $message ) ) {
		$message = print_r( $message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	}

	$log_message = sprintf(
		'[%s UTC] [WPSS %s] %s',
		gmdate( 'Y-m-d H:i:s' ),
		strtoupper( $level ),
		$message
	);

	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log( $log_message );
}

/**
 * Sanitize a date string to a strict Y-m-d value or null.
 *
 * Accepts only a calendar-valid Y-m-d date that round-trips exactly; anything
 * else (empty string, partial, or impossible date like 2026-13-40) returns
 * null so the DATE column stores SQL NULL.
 *
 * @since 1.2.0
 *
 * @param string $value Raw date string.
 * @return string|null Valid Y-m-d date, or null.
 */
function wpss_sanitize_date( string $value ): ?string {
	$value = trim( sanitize_text_field( $value ) );

	if ( '' === $value ) {
		return null;
	}

	$parsed = \DateTimeImmutable::createFromFormat( 'Y-m-d', $value );

	if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $value ) {
		return null;
	}

	return $value;
}

/**
 * Render a submit button that works outside wp-admin.
 *
 * `submit_button()` lives in wp-admin/includes/template.php, which is only
 * loaded on admin requests. Settings sections render through the
 * `wpss_settings_sections_*` actions, and anything may fire those - so a
 * section calling submit_button() directly fatals with "undefined function"
 * the moment it is drawn anywhere else. Free's menu-visibility section and
 * Pro's display-currency section both did.
 *
 * Admin screens keep using core's function; only the fallback markup is ours.
 *
 * @since 1.6.0
 *
 * @param string $text Button label.
 * @param string $type Button type passed through to submit_button().
 * @param string $name Button name attribute.
 * @return void
 */
function wpss_submit_button( string $text, string $type = 'primary', string $name = 'submit' ): void {
	if ( function_exists( 'submit_button' ) ) {
		submit_button( $text, $type, $name, false );
		return;
	}

	printf(
		'<button type="submit" name="%1$s" id="%1$s" class="button button-%2$s">%3$s</button>',
		esc_attr( $name ),
		esc_attr( $type ),
		esc_html( $text )
	);
}

/**
 * Whether an admin hook suffix or screen id refers to one of our pages.
 *
 * WordPress derives a submenu hook suffix from the PARENT MENU TITLE, not from
 * the parent slug: sanitize_title( $menu_title ) . '_page_' . $page_slug. The
 * menu title is renameable - Pro's White Label sells exactly that - so any
 * comparison against a literal like 'sell-services_page_wpss-reports' silently
 * stops matching the moment an owner renames the menu, and whatever it guarded
 * simply never happens. That has now cost two separate defects: the vendor
 * detail screen sat on "Loading" forever (fixed in 1.7.2 by capturing the
 * suffix at registration), and the Reports queue lost the script carrying its
 * suspend/close-account confirmation (Basecamp 10320551487).
 *
 * Compare the page slug instead, which no site owner can change. Handles both
 * the 'toplevel_page_<slug>' and '<parent>_page_<slug>' forms.
 *
 * Where a class registers the page itself, capturing the return value of
 * add_submenu_page() is still the most direct answer; this helper is for the
 * places that only receive the hook.
 *
 * @since 1.7.2
 *
 * @param string $hook  Hook suffix or WP_Screen id.
 * @param string ...$slugs One or more page slugs, e.g. 'wpss-reports'.
 * @return bool True when $hook addresses any of the given slugs.
 */
function wpss_is_admin_page( string $hook, string ...$slugs ): bool {
	foreach ( $slugs as $slug ) {
		if ( '' === $slug ) {
			continue;
		}

		if ( $hook === 'toplevel_page_' . $slug || str_ends_with( $hook, '_page_' . $slug ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Enqueue one of the plugin's stylesheets, with its RTL sibling registered.
 *
 * Every sheet registered from a class remembered wp_style_add_data( …, 'rtl',
 * 'replace' ); every sheet enqueued inline from a template forgot it. So
 * orders.css, vendor-dashboard.css and messaging.css shipped an -rtl.css that
 * WordPress was never told about and therefore never served - the RTL build ran
 * on every release and its output went nowhere (Basecamp 10320551778).
 *
 * Call this instead of wp_enqueue_style() for plugin CSS so the RTL pairing is
 * not something each call site has to remember.
 *
 * @since 1.7.2
 *
 * @param string   $handle   Style handle, e.g. 'wpss-orders'.
 * @param string   $relative Path under the plugin root, e.g. 'assets/css/orders.css'.
 * @param string[] $deps     Dependencies. Defaults to the design system.
 * @return void
 */
function wpss_enqueue_style( string $handle, string $relative, array $deps = array( 'wpss-design-system' ) ): void {
	wp_enqueue_style( $handle, WPSS_PLUGIN_URL . $relative, $deps, WPSS_VERSION );
	wp_style_add_data( $handle, 'rtl', 'replace' );
}
