<?php
/**
 * Developer-only CLI tools stay out of the release zip; the shipped ones stay in.
 *
 * Run: wp eval-file tests/test-dev-cli-not-shipped.php
 *
 * `wp wpss scale`, `test:flow`, `rest:contract` and `api:shapes` seed throwaway
 * data or audit the API for development. They shipped to every customer. Both
 * exclude lists (.distignore and Gruntfile copy:dist, which builds the zip) now
 * drop their files, and ServiceCommands registers each one behind class_exists(),
 * so a zip install does not offer them and does not fatal. demo, service,
 * validate, preflight, utc-migrate, refunds and repair:stripe-tax keep shipping.
 * The schema lock (audit/tables.lock) is committed so a new table needs a yes.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$root     = dirname( __DIR__ );
$ignore   = array_map( 'trim', file( $root . '/.distignore' ) );
$grunt    = (string) file_get_contents( $root . '/Gruntfile.js' );
$commands = (string) file_get_contents( $root . '/src/CLI/ServiceCommands.php' );

$dev_tools = array(
	'TestFlowCommand'     => 'wpss test:flow',
	'ScaleCommand'        => 'wpss scale',
	'RestContractCommand' => 'wpss rest:contract',
	'ApiShapeCommand'     => 'wpss api:shapes',
);
$shipped   = array( 'ServiceCommands', 'Guard', 'ValidateCommand', 'PreflightCommand', 'UtcMigrateCommand', 'RefundsCommand', 'RepairCommand' );

foreach ( $dev_tools as $class => $command ) {
	$file = "src/CLI/{$class}.php";
	$check( ".distignore drops {$file}", in_array( $file, $ignore, true ) );
	$check( "Gruntfile copy:dist drops {$file}", str_contains( $grunt, "'!{$file}'" ) );
	$check(
		"{$command} is registered only when its class loads",
		(bool) preg_match( '/if\s*\(\s*class_exists\(\s*' . $class . '::class\s*\)\s*\)\s*\{\s*WP_CLI::add_command\(\s*\'' . preg_quote( $command, '/' ) . '\'/', $commands )
	);
	$others = trim( (string) shell_exec( 'grep -rlw ' . escapeshellarg( $class ) . ' ' . escapeshellarg( $root . '/src' ) . ' ' . escapeshellarg( $root . '/wp-sell-services.php' ) ) );
	$others = array_diff( array_filter( explode( "\n", $others ) ), array( "{$root}/{$file}", "{$root}/src/CLI/ServiceCommands.php" ) );
	$check( "no other shipped file needs {$class}", array() === $others, implode( ', ', $others ) );
}

foreach ( $shipped as $class ) {
	$file = "src/CLI/{$class}.php";
	$check( "{$file} still ships", ! in_array( $file, $ignore, true ) && ! str_contains( $grunt, "'!{$file}'" ) && ! array_intersect( array( 'src', 'src/CLI', 'src/CLI/*', 'src/CLI/**' ), $ignore ) && ! str_contains( $grunt, "'!src/CLI/**'" ) );
}

$lock = is_readable( $root . '/audit/tables.lock' ) ? (string) file_get_contents( $root . '/audit/tables.lock' ) : '';
$check( 'audit/tables.lock is committed with the 20 Free tables', (bool) preg_match( '/^\{\$table\} x20$/m', $lock ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
