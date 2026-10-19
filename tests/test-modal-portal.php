<?php
/**
 * A modal opened inside .wpss-app-shell must be lifted to <body> while open.
 *
 * The shell is `isolation:isolate`, so a modal left inside it can never paint
 * above a theme header, footer or back-to-top button (cards 10384248570, 10416190285).
 */

$js = file_get_contents( dirname( __DIR__ ) . '/assets/js/wpss-ui.js' );
$min = file_get_contents( dirname( __DIR__ ) . '/assets/js/wpss-ui.min.js' );

$fail = 0;
foreach ( array( 'source' => $js, 'minified' => $min ) as $label => $src ) {
	$ok = false !== strpos( $src, '.wpss-app-shell' ) && false !== strpos( $src, 'document.body.appendChild' );
	echo ( $ok ? 'PASS' : 'FAIL' ) . " wpssModal lifts into body ($label)\n";
	$fail += $ok ? 0 : 1;
}
exit( $fail ? 1 : 0 );
