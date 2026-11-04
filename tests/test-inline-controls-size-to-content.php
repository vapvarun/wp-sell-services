<?php
/**
 * Filter and toolbar dropdowns size to their content on every theme.
 *
 * Reign 8 gives every field a plugin leaves unstyled width: 100% (inside a
 * cascade layer). The Vendors toolbar, the earnings ledger period filter and
 * Pro's currency switcher never declared a width, so on Reign each dropdown
 * filled its own line. The plugin now declares the width once, in
 * design-system.css, for every inline control row.
 *
 * Run: wp eval-file tests/test-inline-controls-size-to-content.php
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

$free = dirname( __DIR__ );
$pro  = dirname( $free ) . '/wp-sell-services-pro';
$css  = (string) file_get_contents( $free . '/assets/css/design-system.css' );

// The one rule: an :is() list of rows whose select gets inline-size: auto.
$found = preg_match( '/:is\(([^)]*)\)\s*select\s*\{[^}]*inline-size:\s*auto/s', $css, $m );
$check( 'design-system.css sizes inline-row dropdowns to their content', 1 === $found );

$rows = $found ? array_filter( array_map( 'trim', explode( ',', $m[1] ) ) ) : array();

// The rows that stacked on Reign are covered.
foreach ( array( '.wpss-vendors-toolbar__controls', '.wpss-ledger-controls', '.wpss-currency-switcher' ) as $row ) {
	$check( "{$row} is an inline row", in_array( $row, $rows, true ) );
}

// Every listed row still exists in markup, so the list does not rot.
$markup = '';
foreach ( array( $free . '/templates', $free . '/src', $pro . '/src', $pro . '/templates' ) as $dir ) {
	if ( ! is_dir( $dir ) ) {
		continue;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( 'php' === $file->getExtension() ) {
			$markup .= file_get_contents( (string) $file );
		}
	}
}
// Pro renders the ledger and currency rows; a Free-only checkout (CI) cannot see them.
$pro_rows = is_dir( $pro ) ? array() : array( '.wpss-ledger-controls', '.wpss-currency-switcher' );
foreach ( array_diff( $rows, $pro_rows ) as $row ) {
	$check( "{$row} is still rendered somewhere", false !== strpos( $markup, substr( $row, 1 ) ) );
}

// The RTL and minified copies carry it too, or the fix is inert where they load.
foreach ( array( 'design-system-rtl.css', 'design-system.min.css', 'design-system-rtl.min.css' ) as $copy ) {
	$check( "{$copy} carries the rule", false !== strpos( (string) file_get_contents( $free . '/assets/css/' . $copy ), 'wpss-vendors-toolbar__controls' ) );
}

// Pro's switcher can render on a page with no other plugin sheet: its CSS must pull the design system in.
if ( is_dir( $pro ) ) {
	$src = (string) file_get_contents( $pro . '/src/Currency/DisplayCurrencyManager.php' );
	$check( "Pro's currency stylesheet depends on wpss-design-system", 1 === preg_match( "/'wpss-pro-currency',\s*WPSS_PRO_PLUGIN_URL[^;]*array\(\s*'wpss-design-system'\s*\)/s", $src ) );
}

echo $fails ? "\n$fails FAILED\n" : "\nALL PASS\n";
