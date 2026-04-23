<?php
/**
 * Icon Rendering Helper (Lucide).
 *
 * @package WPSellServices\Services
 * @since   1.1.0
 */

declare(strict_types=1);

namespace WPSellServices\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Render Lucide icon markup for use in admin + frontend templates.
 *
 * Pairs with the `lucide` vendor library enqueued by
 * {@see \WPSellServices\Frontend\Tour::register_scripts()}. The emitted
 * `<i data-lucide="...">` placeholder is resolved into an inline SVG at
 * runtime by a call to `lucide.createIcons()`.
 *
 * Intended to replace ad-hoc dashicon spans and inline SVG everywhere
 * we currently surface glyphs in admin screens or dashboard templates.
 *
 * @since 1.1.0
 */
final class Icon {

	/**
	 * Render a Lucide icon placeholder.
	 *
	 * The returned string is already escaped and safe to `echo` directly
	 * into a template. Any caller-supplied attributes are escaped with
	 * `esc_attr()` and the icon name is slug-sanitized so only
	 * `[a-z0-9-]` survives — Lucide names are kebab-case.
	 *
	 * Example:
	 *
	 *     echo \WPSellServices\Services\Icon::render(
	 *         'shopping-bag',
	 *         array(
	 *             'class' => 'wpss-nav__icon',
	 *             'width' => '20',
	 *             'data-testid' => 'nav-orders',
	 *         )
	 *     );
	 *
	 * @since 1.1.0
	 *
	 * @param string               $name  Lucide icon slug (e.g. 'shopping-bag').
	 * @param array<string,scalar> $attrs Optional HTML attributes. `class` is
	 *                                    merged with the default `wpss-icon`
	 *                                    class; `aria-hidden` defaults to true.
	 * @return string Escaped `<i>` element, or empty string when `$name` is blank.
	 */
	public static function render( string $name, array $attrs = array() ): string {
		$slug = strtolower( trim( $name ) );
		$slug = preg_replace( '/[^a-z0-9-]/', '', $slug );

		if ( null === $slug || '' === $slug ) {
			return '';
		}

		$default_class = 'wpss-icon';
		$user_class    = isset( $attrs['class'] ) ? (string) $attrs['class'] : '';
		$merged_class  = trim( $default_class . ' ' . $user_class );

		unset( $attrs['class'] );

		// `aria-hidden` is on by default — icons are decorative. Callers
		// that want a semantic icon can override by passing 'false'.
		if ( ! array_key_exists( 'aria-hidden', $attrs ) ) {
			$attrs['aria-hidden'] = 'true';
		}

		$html = sprintf(
			'<i data-lucide="%s" class="%s"',
			esc_attr( $slug ),
			esc_attr( $merged_class )
		);

		foreach ( $attrs as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$html .= sprintf( ' %s="%s"', esc_attr( (string) $key ), esc_attr( (string) $value ) );
		}

		$html .= '></i>';

		return $html;
	}
}
