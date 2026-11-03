<?php
/**
 * The marketplace colour follows the site: theme, brand colour, or the plugin's own.
 *
 * On Reign, switching Customizer > Color Palette left every button, tab and
 * tile indigo (Basecamp 10470005743): --wpss-primary was a literal, its shades
 * were separate literals, and many rules painted white text on it or drew it as
 * text. Now one base drives every shade, fills take the theme's button pair
 * (fill + the ink chosen for it), text takes the theme's text-safe colour, a
 * brand colour (Pro White Label) wins, and an opt-out keeps the plugin colour.
 *
 * Run: wp eval-file tests/test-brand-color-follows-theme.php
 *
 * Reads files and runs filters; writes nothing.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$root    = dirname( __DIR__ );
$css_dir = $root . '/assets/css/';
$sheets  = array();
foreach ( glob( $css_dir . '*.css' ) as $file ) {
	if ( ! preg_match( '/(\.min|-rtl)\.css$/', $file ) ) {
		$sheets[ basename( $file ) ] = (string) file_get_contents( $file );
	}
}
$ds = $sheets['design-system.css'];

// Rules as [selector, body] pairs.
$rules = static function ( string $css ): array {
	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER );
	return array_map( static fn( $r ) => array( trim( $r[1] ), $r[2] ), $m );
};

// 1. One base, theme first, plugin indigo last; every shade derived.
$check( 'fills follow the theme button colour, then the plugin indigo', 1 === preg_match( '/--wpss-primary:\s*var\(--wpss-theme-fill,\s*#4f46e5\)/', $ds ) );
$check( 'the theme fill reads Reign and BuddyX button colours', false !== strpos( $ds, '--wpss-theme-fill: var(--reign-site-button-bg-color, var(--bx-color-button-bg))' ) );
$check( 'the ink on a fill comes from the same theme pair', false !== strpos( $ds, '--wpss-theme-ink: var(--reign-site-button-text-color, var(--bx-color-button-fg))' ) );
$check( 'text takes the theme text-safe colour', false !== strpos( $ds, '--wpss-theme-accent-text: var(--reign-colors-theme-text, var(--bx-color-accent))' ) );
foreach ( array( '--wpss-primary-hover', '--wpss-primary-light', '--wpss-primary-ring' ) as $token ) {
	$check( "{$token} is derived from the base, not a literal", 1 === preg_match( '/' . preg_quote( $token, '/' ) . ':\s*color-mix\(/', $ds ) );
}

// 2. Every plugin sheet reads the tokens.
$bare  = array();
$text  = array();
$ink   = array();
$tint  = array();
$stars = array();
foreach ( $sheets as $name => $css ) {
	if ( preg_match( '/rgba\(\s*79,\s*70,\s*229|rgb\(var\(--wpss-primary-rgb/', $css ) ) {
		$bare[] = $name;
	}
	foreach ( $rules( $css ) as list( $selector, $body ) ) {
		// The fill colour as text is right only on an ink ground (an inverted pill).
		if ( preg_match( '/(?<![-\w])color\s*:\s*var\(\s*--wpss-primary\s*[,)]/', $body ) && ! preg_match( '/background\s*:\s*var\(\s*--wpss-on-primary/', $body ) ) {
			$text[] = "{$name}: {$selector}";
		}
		if ( preg_match( '/background(-color)?\s*:\s*var\(\s*--wpss-primary(-hover)?\s*[,)]/', $body ) && preg_match( '/(?<![-\w])color\s*:\s*(var\(\s*--wpss-white|#fff\b|#ffffff\b)/i', $body ) ) {
			$ink[] = "{$name}: {$selector}";
		}
		if ( preg_match( '/background(-color)?\s*:\s*var\(\s*--wpss-primary-(light|50)/', $body ) && preg_match( '/(?<![-\w])color\s*:\s*var\(\s*--wpss-primary(-accent)?\s*[,)]/', $body ) ) {
			$tint[] = "{$name}: {$selector}";
		}
		if ( 'design-system.css' !== $name && preg_match( '/\.wpss-required\s*$/', $selector ) ) {
			$stars[] = "{$name}: {$selector}";
		}
	}
}
$check( 'no sheet hard-codes the indigo in a ring or tint', array() === $bare, implode( ', ', $bare ) );
$check( 'no rule draws the fill colour as text', array() === $text, implode( '; ', array_slice( $text, 0, 5 ) ) );
$check( 'no fill hard-codes white text (it uses --wpss-on-primary)', array() === $ink, implode( '; ', array_slice( $ink, 0, 5 ) ) );
$check( 'text on the brand tint uses the darker shade', array() === $tint, implode( '; ', array_slice( $tint, 0, 5 ) ) );
$check( 'the required star is declared once, in the design system', array() === $stars && 1 === preg_match( '/^\.wpss-required \{ color: var\(--wpss-danger-text/m', $ds ), implode( '; ', $stars ) );
$check( 'the shipped copies carry the theme layer', false !== strpos( (string) file_get_contents( $css_dir . 'design-system.min.css' ), '--wpss-theme-fill:var(--reign-site-button-bg-color' ) && false !== strpos( (string) file_get_contents( $css_dir . 'design-system-rtl.min.css' ), '--wpss-on-primary' ) );

// 3. PHP: a brand colour wins with readable shades; the opt-out keeps the plugin colour.
$check( 'wpss_brand_color_css() exists', function_exists( 'wpss_brand_color_css' ) );
if ( function_exists( 'wpss_brand_color_css' ) ) {
	$pro_hooked = has_filter( 'wpss_brand_color' );
	remove_all_filters( 'wpss_brand_color' );
	remove_all_filters( 'wpss_use_theme_colors' );

	$check( 'no brand colour and no opt-out adds nothing', '' === wpss_brand_color_css() );

	add_filter( 'wpss_use_theme_colors', '__return_false' );
	$css = wpss_brand_color_css();
	$check( 'the opt-out unsets every theme slot', 4 === substr_count( $css, ':initial' ) && 0 === strpos( $css, ':root{' ), $css );

	$brand = static fn() => '#c2185b';
	add_filter( 'wpss_brand_color', $brand );
	$css = wpss_brand_color_css();
	$check( 'a brand colour wins over the opt-out and the theme', false !== strpos( $css, 'html:root{--wpss-theme-fill:#c2185b;--wpss-theme-ink:#ffffff;' ), $css );
	remove_filter( 'wpss_brand_color', $brand );
	remove_all_filters( 'wpss_use_theme_colors' );

	// A light brand: dark ink on the fill, a darker shade for text on white, a lighter one on dark.
	$light = static fn() => '#ccaf0b';
	add_filter( 'wpss_brand_color', $light );
	$css = wpss_brand_color_css();
	remove_filter( 'wpss_brand_color', $light );
	preg_match( '/--wpss-theme-ink:(#[0-9a-f]{6})/', $css, $ink_m );
	preg_match( '/html:root\{[^}]*--wpss-theme-accent-text:(#[0-9a-f]{6})/', $css, $lt );
	preg_match( '/dark"\]\{--wpss-theme-accent-text:(#[0-9a-f]{6})/', $css, $dk );
	$check( 'a light brand gets dark ink that reads 4.5:1 on it', isset( $ink_m[1] ) && '#111827' === $ink_m[1] && wpss_color_contrast( $ink_m[1], '#ccaf0b' ) >= 4.5, $css );
	$check( 'brand text reads 4.5:1 on white', isset( $lt[1] ) && wpss_color_contrast( $lt[1], '#ffffff' ) >= 4.5, $lt[1] ?? 'missing' );
	$check( 'brand text reads 4.5:1 on a dark surface', isset( $dk[1] ) && wpss_color_contrast( $dk[1], '#1f2937' ) >= 4.5, $dk[1] ?? 'missing' );

	$junk = static fn() => 'red;}body{display:none';
	add_filter( 'wpss_brand_color', $junk );
	$check( 'a value that is not a hex colour is ignored', '' === wpss_brand_color_css() );
	remove_filter( 'wpss_brand_color', $junk );

	$check( 'contrast helper: black on white is 21:1', abs( wpss_color_contrast( '#000', '#fff' ) - 21 ) < 0.01 );
	unset( $pro_hooked );
}

// 4. Pro White Label names the colour through the filter and keeps no copy of the tokens.
$pro_file = WP_PLUGIN_DIR . '/wp-sell-services-pro/src/WhiteLabel/DashboardBrandingService.php';
if ( file_exists( $pro_file ) ) {
	$pro = (string) file_get_contents( $pro_file );
	$check( 'Pro hands its colour to wpss_brand_color', false !== strpos( $pro, "add_filter( 'wpss_brand_color'" ) );
	$check( 'Pro sets no --wpss-primary copy of its own', false === strpos( $pro, '--wpss-primary:' ) );
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
