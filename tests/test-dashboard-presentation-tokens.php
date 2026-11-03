<?php
/**
 * Dashboard presentation: one frame on a phone, readable status colours in
 * both modes, and one owner per design token.
 *
 * Guards the 1.9.2 presentation sweep. A section drawn as a second card inside
 * the dashboard frame on a phone; a section title said twice under 1024px;
 * status pills, danger tints and sub-order badges that kept their light pastels
 * in dark mode; success green used as text at 2.5:1 on white; and frontend.css
 * re-declaring 23 design-system tokens, so a stale copy there silently undid
 * every token fix made in the design system.
 *
 * Run: wp eval-file tests/test-dashboard-presentation-tokens.php
 *
 * Reads files only; writes nothing.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$css_dir = dirname( __DIR__ ) . '/assets/css/';
$read    = static fn( string $file ): string => (string) file_get_contents( $css_dir . $file );

// Custom properties declared in the first block opened by $opener.
$tokens = static function ( string $css, string $opener ): array {
	$start = strpos( $css, $opener );
	if ( false === $start ) {
		return array();
	}
	$body = substr( $css, $start, strpos( $css, "\n}", $start ) - $start );
	preg_match_all( '/(--wpss-[\w-]+)\s*:\s*([^;]+);/', $body, $m );
	return array_combine( $m[1], array_map( 'trim', $m[2] ) );
};

$luminance = static function ( string $hex ): float {
	$hex = ltrim( $hex, '#' );
	$ch  = array_map(
		static function ( $v ) {
			$v = hexdec( $v ) / 255;
			return $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4;
		},
		str_split( $hex, 2 )
	);
	return 0.2126 * $ch[0] + 0.7152 * $ch[1] + 0.0722 * $ch[2];
};

$ds    = $read( 'design-system.css' );
$light = $tokens( $ds, ':root {' );
$dark  = $tokens( $ds, ":root[data-theme=\"dark\"],\n:root[data-bx-mode=\"dark\"]," );

// 1. Success is used as text: it must read on white.
$success = $light['--wpss-success'] ?? '';
$ratio   = preg_match( '/^#[0-9a-f]{6}$/i', $success ) ? 1.05 / ( $luminance( $success ) + 0.05 ) : 0;
$check( 'light --wpss-success reads 4.5:1 on white', $ratio >= 4.5, sprintf( '%s is %.2f:1', $success, $ratio ) );

// 2. Every tint with a light pastel has a dark value.
foreach ( array( '--wpss-danger-light', '--wpss-danger-dark', '--wpss-wp-admin-bg', '--wpss-status-in-progress-bg', '--wpss-status-pending-bg', '--wpss-status-rejected-bg', '--wpss-badge-tip-bg', '--wpss-badge-milestone-icon-bg', '--wpss-badge-milestone-icon-fg' ) as $token ) {
	$check( "{$token} has a dark-mode value", isset( $dark[ $token ] ) && $dark[ $token ] !== ( $light[ $token ] ?? null ) );
}

// 3. design-system.css owns shared tokens: frontend.css loads after it and must not re-declare them.
$redeclared = array_intersect_key( $tokens( $read( 'frontend.css' ), ':root {' ), $light );
$check( 'frontend.css re-declares no design-system token', array() === $redeclared, implode( ', ', array_keys( $redeclared ) ) );

// 4. Phone: the dashboard frame is the only card, and content keeps the header's inset.
$ud = $read( 'unified-dashboard.css' );
$check( 'a dashboard section drops its card frame on a phone', 1 === preg_match( '/@media \(max-width: 640px\)\s*\{[^@]*\.wpss-dashboard__body > \.wpss-section\.wpss-card\s*\{[^}]*border:\s*0/s', $ud ) );
$check( 'the dashboard body keeps its inset on a phone', 0 === preg_match( '/\.wpss-dashboard__body\s*\{\s*padding-inline:\s*0;/', $ud ) );
$check( 'the header does not repeat the nav bar title under 1024px', 1 === preg_match( '/@media \(max-width: 1024px\)\s*\{.*?\.wpss-dashboard__header \.wpss-dashboard__title\s*\{[^}]*clip:/s', $ud ) );

// 5. Text drawn in a fill colour.
$check( 'conversation order line uses the muted ink, not gray-400', 1 === preg_match( '/\.wpss-conversation-card__order\s*\{[^}]*--wpss-text-muted/', $ud ) );
$check( 'the dashboard pending pill reads the canonical status tokens', 1 === preg_match( '/\.wpss-status-pending\s*\{\s*background:\s*var\(--wpss-status-pending-bg/', $ud ) );

// 6. The copies that actually load (min, rtl) carry it.
foreach ( array( 'design-system.min.css', 'design-system-rtl.min.css' ) as $copy ) {
	$check( "{$copy} carries the dark danger tint", false !== strpos( $read( $copy ), '--wpss-danger-light:rgba(239,68,68,.2)' ) );
}
foreach ( array( 'unified-dashboard.min.css', 'unified-dashboard-rtl.min.css' ) as $copy ) {
	$check( "{$copy} carries the flat phone section", false !== strpos( $read( $copy ), '.wpss-dashboard__body>.wpss-section.wpss-card' ) );
}

// 7. Vendors grid tile: the name leads, figures are body size, facts are their own group.
$fe = $read( 'frontend.css' );
$check( 'vendor tile name has its own size and weight', 1 === preg_match( '/\.wpss-vendors-grid \.wpss-vendor-name\s*\{[^}]*font-size:\s*1rem[^}]*font-weight:\s*600/s', $fe ) );
$check( 'vendor tile figures do not inherit the 24px sidebar headline', 1 === preg_match( '/\.wpss-vendors-grid \.wpss-vendor-stats \.wpss-stat-value\s*\{[^}]*font-size:\s*0\.9375rem/s', $fe ) );
$check( 'vendor tile facts sit in their own divided group', 1 === preg_match( '/\.wpss-vendors-grid \.wpss-vendor-details\s*\{[^}]*border-block-start:/s', $fe ) );

// 8. Buttons: one theme-proof button for every public page, and an outline edge that reads.
$on = static fn( string $fg, string $bg ): float => ( max( $luminance( $fg ), $luminance( $bg ) ) + 0.05 ) / ( min( $luminance( $fg ), $luminance( $bg ) ) + 0.05 );
$edge_light = $light['--wpss-border-control'] ?? '';
$edge_dark  = $dark['--wpss-border-control'] ?? '';
$check( 'outline edge reads 3:1 on the light surfaces', '' !== $edge_light && $on( $edge_light, '#ffffff' ) >= 3 && $on( $edge_light, '#f9fafb' ) >= 3, $edge_light );
$check( 'outline edge reads 3:1 on the dark surfaces', '' !== $edge_dark && $on( $edge_dark, '#0b1220' ) >= 3 && $on( $edge_dark, '#1f2937' ) >= 3, $edge_dark );
$check( 'the outline button draws its edge with the control token', 1 === preg_match( '/\.wpss-btn\.wpss-btn--outline\s*\{[^}]*border:\s*2px solid var\(--wpss-border-control\)/', $ds ) );
$check( 'the design system holds the one public-page button reset', false !== strpos( $ds, ':is(.wpss-services-archive, .wpss-single-service, .wpss-requests-archive, .wpss-single-request) .wpss-btn {' ) );
foreach ( array( 'archive-service.css', 'single-service.css', 'buyer-request.css' ) as $sheet ) {
	$page_css = $read( $sheet );
	$check( "{$sheet} carries no button reset of its own", 0 === preg_match( '/^\.wpss-[a-z-]+ \.wpss-btn,\s*$/m', $page_css ) );
	$check( "{$sheet} outline edge uses the control token", false !== strpos( $page_css, '--wpss-border-control' ) );
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
