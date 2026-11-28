<?php
/**
 * One box look across wp-admin (2.0.0 desktop unification, Basecamp 10554052748).
 *
 * The owner picked the core .postbox look: 1px #c3c4c7 border, no radius, the faint core shadow.
 * Free admin.css defines it once as --wpss-admin-box-*; every admin box reads those tokens.
 *   - A rule whose subject is an admin box may not set its own border, radius or shadow
 *     (0 / none resets are fine; a line marked "box-ok:" says why it is an exception).
 *   - The admin stat tile shows state by its icon, never by a border width.
 *   - White label with no colour chosen hands every surface '' (the store colour), including a
 *     site that saved the old purple default.
 *
 * Run: wp eval-file tests/test-admin-box-contract.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ): void {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// Every sheet that loads only in wp-admin. Shared sheets (analytics.css, order-view.css) keep the frontend look;
// their admin overrides live in an admin sheet under .wp-admin.
$css   = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/assets/css/admin.css' );
$files = array();
foreach ( array( 'admin', 'admin-settings', 'admin-wizard', 'admin-manual-order', 'admin-upgrade', 'pro-teaser', 'service-edit' ) as $sheet ) {
	$files[ "{$sheet}.css" ] = (string) file_get_contents( WP_PLUGIN_DIR . "/wp-sell-services/assets/css/{$sheet}.css" );
}
$pro = WP_PLUGIN_DIR . '/wp-sell-services-pro/assets/css/admin.css';
if ( is_file( $pro ) ) {
	$files['pro admin.css'] = (string) file_get_contents( $pro );
}

// The tokens, with the owner's values.
foreach ( array(
	'--wpss-admin-box-border' => '1px solid var(--wpss-wp-admin-border, #c3c4c7)',
	'--wpss-admin-box-radius' => '0',
	'--wpss-admin-box-shadow' => '0 1px 1px rgba(0, 0, 0, 0.04)',
) as $token => $value ) {
	$check( "{$token} is the core postbox value", str_contains( $css, "{$token}: {$value};" ) );
}

// Box classes: any *-card, *-box, *-panel, *-section, *-widget, *-postbox, *-tile, plus the boxes named otherwise.
$box = '/\.wpss-(?:[a-z0-9-]*?(?:card|box|panel|section|widget|postbox|tile)|empty-state|commission-form|setup-steps|detail-header|detail-tabs|detail-tab-content|wallet-balance|subscription-plan-form|chart-container|analytics-header|(?:package|faq|requirement|addon)-item|requirement-choices)(?:--[a-z0-9-]+)*(?![\w-])/';
$own = array();
foreach ( $files as $name => $sheet ) {
	$sheet = preg_replace_callback( '#/\*(?!\s*box-ok:).*?\*/#s', static fn( $m ) => str_repeat( "\n", substr_count( $m[0], "\n" ) ), $sheet );
	preg_match_all( '/([^{}]+)\{([^{}]*)\}/', (string) $sheet, $rules, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
	foreach ( $rules as $rule ) {
		$selectors = array_map( 'trim', explode( ',', $rule[1][0] ) );
		$subjects  = array_map( static fn( $sel ) => (string) preg_replace( '/^.*[\s>+~]/', '', $sel ), $selectors );
		if ( ! preg_grep( $box, $subjects ) ) {
			continue;
		}
		$body = $rule[2][0];
		preg_match_all( '/(?<![\w-])(border(?:-(?:top|right|bottom|left|inline|block)(?:-(?:start|end))?)?(?:-width)?|border-radius|box-shadow)\s*:\s*([^;}]+)/', $body, $decls, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		foreach ( $decls as $d ) {
			$value   = trim( (string) preg_replace( '#/\*.*?\*/#s', '', $d[2][0] ) );
			$eol     = strpos( $body, "\n", $d[0][1] );
			$on_line = substr( $body, $d[0][1], false === $eol ? null : $eol - $d[0][1] );
			$uses_token = (bool) preg_match( '/^var\((--wpss-admin-box-border|--wpss-admin-box-radius|--wpss-admin-box-shadow)[,)]/', $value );
			if ( $uses_token || in_array( $value, array( '0', 'none', '0px' ), true ) || str_contains( $on_line, 'box-ok:' ) ) {
				continue;
			}
			$line  = substr_count( substr( $sheet, 0, $rule[0][1] ), "\n" ) + 1;
			$own[] = sprintf( '%s:%d %s { %s: %s }', $name, $line, trim( preg_replace( '/\s+/', ' ', $rule[1][0] ) ), $d[1][0], $value );
		}
	}
}
$check( 'no admin box sets its own border, radius or shadow' . ( $own ? ":\n  " . implode( "\n  ", array_slice( $own, 0, 15 ) ) : '' ), ! $own );

// Tile state is the icon, not a border width that shifts the content.
$check( 'an active queue tile has no accent edge', ! preg_match( '/\.wpss-stat-card--link\.is-active\s*\{[^}]*border/', $css ) );
$check( 'admin tile labels wrap between words', (bool) preg_match( '/\.wp-admin \.wpss-stat-card__label\s*\{[^}]*overflow-wrap:\s*normal/', $css ) );

// White label: no colour chosen means '' everywhere, including the legacy stored purple.
if ( class_exists( 'WPSellServicesPro\\WhiteLabel\\WhiteLabelManager' ) ) {
	$key = \WPSellServicesPro\WhiteLabel\WhiteLabelManager::OPTION_KEY;
	$was = get_option( $key, null );
	foreach ( array( '#7f54b3', '#7F54B3', '' ) as $stored ) {
		update_option( $key, array( 'primary_color' => $stored ) );
		$check( "a stored '{$stored}' reads as no colour chosen", '' === ( new \WPSellServicesPro\WhiteLabel\WhiteLabelManager() )->get_brand_color() );
	}
	update_option( $key, array( 'primary_color' => '#0e7490' ) );
	$check( 'a chosen colour is returned', '#0e7490' === ( new \WPSellServicesPro\WhiteLabel\WhiteLabelManager() )->get_brand_color() );
	null === $was ? delete_option( $key ) : update_option( $key, $was );
} else {
	echo "SKIP  Pro is not active, white-label colour not checked\n";
}

echo $fails ? "{$fails} FAILED\n" : "all passed\n";
