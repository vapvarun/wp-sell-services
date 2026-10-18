<?php
/**
 * Text that carries meaning uses a token that clears 4.5:1 in light AND dark mode (found by the
 * 1.9.0 computed-contrast sweep on Reign: ghost Delete/Withdraw 3.0:1, analytics captions
 * 3.0:1, wallet credits 2.5:1 in light).
 *
 * Run: wp eval-file tests/test-text-token-contrast.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};
$read = static fn( string $rel ): string => (string) file_get_contents( dirname( __DIR__ ) . '/' . $rel );

$lum   = static function ( string $hex ): float {
	$hex = ltrim( $hex, '#' );
	$c   = array_map( static fn( $p ) => hexdec( $p ) / 255, str_split( $hex, 2 ) );
	$c   = array_map( static fn( $v ) => $v <= 0.03928 ? $v / 12.92 : ( ( $v + 0.055 ) / 1.055 ) ** 2.4, $c );
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
};
$ratio = static function ( string $a, string $b ) use ( $lum ): float {
	$x = $lum( $a );
	$y = $lum( $b );
	return ( max( $x, $y ) + 0.05 ) / ( min( $x, $y ) + 0.05 );
};
$token = static function ( string $css, string $name, bool $dark ): string {
	$scope = $dark ? substr( $css, (int) strpos( $css, ':root[data-theme="dark"]' ) ) : $css;
	preg_match( '/' . preg_quote( $name, '/' ) . ':\s*(#[0-9a-fA-F]{6})/', $scope, $m );
	return $m[1] ?? '#000000';
};

$ds = $read( 'assets/css/design-system.css' );
$check( 'light danger text is 4.5:1 on white', $ratio( $token( $ds, '--wpss-danger-text', false ), '#ffffff' ) >= 4.5 );
$check( 'dark danger text is 4.5:1 on the dark card', $ratio( $token( $ds, '--wpss-danger-text', true ), '#1f2937' ) >= 4.5 );
$check( 'success-dark (credit text) is 4.5:1 on white', $ratio( $token( $ds, '--wpss-success-dark', false ), '#ffffff' ) >= 4.5 );
$check( 'dark success-dark is 4.5:1 on the dark card', $ratio( $token( $ds, '--wpss-success-dark', true ), '#1f2937' ) >= 4.5 );
$check( 'muted text is 4.5:1 in both modes', $ratio( $token( $ds, '--wpss-text-muted', false ), '#ffffff' ) >= 4.5 && $ratio( $token( $ds, '--wpss-text-muted', true ), '#1f2937' ) >= 4.5 );
$check( 'the hint token is NOT 4.5:1 on the dark card (so it must stay off real text)', $ratio( $token( $ds, '--wpss-text-hint', true ), '#1f2937' ) < 4.5 );

$check( 'ghost danger buttons use the danger text token', (bool) preg_match( '/button\.wpss-btn--ghost\.wpss-btn--danger \{[^}]*--wpss-danger-text/', $ds ) );
$ud = $read( 'assets/css/unified-dashboard.css' );
$check( 'wallet credit amounts use success-dark, debits the danger text token', (bool) preg_match( '/wallet__row--credit \.wpss-wallet__amount \{\s*color: var\(--wpss-success-dark\)/', $ud ) && (bool) preg_match( '/wallet__row--debit \.wpss-wallet__amount \{\s*color: var\(--wpss-danger-text/', $ud ) );
$check( 'notification time uses muted, not hint', (bool) preg_match( '/\.wpss-notification-time \{[^}]*--wpss-text-muted/', $ud ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
