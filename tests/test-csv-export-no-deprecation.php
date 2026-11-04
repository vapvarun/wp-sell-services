<?php
/**
 * Guard: the shared CSV writer raises no PHP deprecation (8.4+ wants the escape argument passed).
 *
 * Run: wp eval-file tests/test-csv-export-no-deprecation.php
 *
 * @package WPSellServices
 */

$fails = 0;
$seen  = array();
set_error_handler( static function ( $no, $msg ) use ( &$seen ) {
	$seen[] = $msg;
	return true;
}, E_DEPRECATED | E_USER_DEPRECATED );

$h = fopen( 'php://memory', 'w+' );
wpss_fputcsv( $h, array( 'Order ID', 'say "hi"', 'back\\slash', '=SUM(A1)' ) );
rewind( $h );
$line = (string) stream_get_contents( $h );
restore_error_handler();

if ( $seen ) {
	++$fails;
	echo '  FAIL  deprecation: ' . $seen[0] . "\n";
}
// A backslash must reach the file untouched, and a formula cell must stay neutralised.
if ( false === strpos( $line, 'back\\slash' ) || false !== strpos( $line, ',=SUM' ) ) {
	++$fails;
	echo '  FAIL  row written as: ' . trim( $line ) . "\n";
}
echo $fails ? "  FAIL  $fails CSV writer check(s)\n" : "  PASS  CSV writer: no deprecation, backslash kept, formula cell neutralised\n";
