<?php
/**
 * A test that prints FAIL still runs the cleanup it registered after loading
 * exit-on-fail.php, and still exits non-zero. Before, the exit ran first and
 * skipped every later shutdown function, so each red test leaked its fixture
 * users, services and option changes into the next one.
 *
 * Run: wp eval-file tests/test-failing-test-still-cleans-up.php
 *
 * Runs a throwaway child PHP script in the system temp dir; removes it.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$dir    = sys_get_temp_dir();
$marker = $dir . '/wpss-cleanup-marker-' . wp_generate_password( 8, false );
$child  = $dir . '/wpss-child-' . wp_generate_password( 8, false ) . '.php';

file_put_contents(
	$child,
	"<?php\nrequire " . var_export( __DIR__ . '/exit-on-fail.php', true ) . ";\n"
	. 'register_shutdown_function( static function () { file_put_contents( ' . var_export( $marker, true ) . ", 'ran' ); } );\n"
	. "echo \"FAIL deliberate\\n\";\n"
);

exec( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( $child ) . ' 2>&1', $out, $status );

$check( 'the failing child exits non-zero', 1 === $status, (string) $status );
$check( 'the cleanup registered after the require still ran', is_file( $marker ) );

wp_delete_file( $marker );
wp_delete_file( $child );
