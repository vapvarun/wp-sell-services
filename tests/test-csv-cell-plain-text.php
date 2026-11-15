<?php
/**
 * Every CSV export writes plain names (no HTML entities) and still guards spreadsheet formulas.
 *
 * Run: wp eval-file tests/test-csv-cell-plain-text.php
 *
 * The decode lives in wpss_csv_cell(), the one writer all exports share
 * (orders, payouts, vendor sales, Pro ledger and analytics).
 *
 * @package WPSellServices
 */

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$check( 'an encoded ampersand is written as &', 'Diego Smith & Sons' === wpss_csv_cell( 'Diego Smith &amp; Sons' ) );
$check( 'a double-encoded name is decoded once', 'Diego Smith &amp; Sons' === wpss_csv_cell( 'Diego Smith &amp;amp; Sons' ) );
$check( 'a plain name is untouched', "O'Brien" === wpss_csv_cell( "O'Brien" ) && 'Ana' === wpss_csv_cell( 'Ana' ) );
$check( 'an encoded formula is still neutralised', "'=cmd" === wpss_csv_cell( '&#61;cmd' ) );
$check( 'a formula and a negative number behave as before', "'=SUM(A1)" === wpss_csv_cell( '=SUM(A1)' ) && '-5' === wpss_csv_cell( '-5' ) );

$h = fopen( 'php://memory', 'w+' );
wpss_fputcsv( $h, array( 'Diego Smith &amp; Sons', 10 ) );
rewind( $h );
$check( 'the shared row writer outputs the plain name', '"Diego Smith & Sons",10' === trim( (string) stream_get_contents( $h ) ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
