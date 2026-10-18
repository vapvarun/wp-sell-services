<?php
/**
 * Both Pro CLI commands refuse on production and confirm their count.
 *
 * Pro registers two writing commands of its own - `wpsspro test:flow` (users,
 * orders, commission rules, subscription plans) and `wpss-pro demo` (seven Pro
 * tables). Neither had any gate: a production shell one arrow-up from a staging
 * command seeded or wiped live rows (Basecamp 10268057471). Both now route
 * through the free plugin's \WPSellServices\CLI\Guard, the same seam Free's own
 * writers use, so --force and --yes mean the same thing on both sides.
 *
 * Run: wp eval-file tests/pro/test-cli-guards.php
 *
 * Every case answers "n" on stdin and asserts the row counts did not move.
 * Nothing here ever answers "y", so the file is safe on a seeded sandbox.
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

global $wpdb;

$failures = array();

$wp_bin = $GLOBALS['argv'][0];
$wp_bin = is_executable( $wp_bin ) ? escapeshellarg( $wp_bin ) : escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $wp_bin );

$counts = static function () use ( $wpdb ): array {
	return array(
		'users'             => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ),
		'orders'            => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_orders" ),
		'commission_rules'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_pro_commission_rules" ),
		'plans'             => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_pro_subscription_plans" ),
		'connect_accounts'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_pro_connect_accounts" ),
		'payout_batches'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_pro_paypal_payout_batches" ),
	);
};

/**
 * Run one wp command with "n" on stdin and a forced environment type.
 *
 * @return array{0:int, 1:string} Exit code and combined stdout + stderr.
 */
$run = static function ( string $args, string $env_type ) use ( $wp_bin ): array {
	$cmd   = $wp_bin . ' ' . $args
		. ' --path=' . escapeshellarg( ABSPATH )
		// WP_PLUGIN_DIR is passed through so the subprocess loads the same
		// plugin code this process did, which is what lets the contract be run
		// against a worktree. On a normal run it is the value WordPress would
		// have picked anyway.
		. ' --exec=' . escapeshellarg( "define( 'WP_ENVIRONMENT_TYPE', '{$env_type}' ); define( 'WP_PLUGIN_DIR', '" . WP_PLUGIN_DIR . "' );" )
		. ' --no-color';
	$spec  = array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	);
	$pipes = array();
	$proc  = proc_open( $cmd, $spec, $pipes, ABSPATH );
	if ( ! is_resource( $proc ) ) {
		return array( -1, 'proc_open failed' );
	}
	fwrite( $pipes[0], "n\n" );
	fclose( $pipes[0] );
	$out = stream_get_contents( $pipes[1] ) . stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );

	return array( proc_close( $proc ), (string) $out );
};

$check = static function ( string $label, string $args, string $env_type, array $expect ) use ( &$failures, $run, $counts ): void {
	$before             = $counts();
	$already            = count( $failures );
	list( $code, $out ) = $run( $args, $env_type );
	$after              = $counts();

	if ( $before !== $after ) {
		$failures[] = "{$label}: rows changed (" . wp_json_encode( $before ) . ' -> ' . wp_json_encode( $after ) . ').';
	}
	if ( ! empty( $expect['nonzero'] ) && 0 === $code ) {
		$failures[] = "{$label}: exited 0, expected a refusal.";
	}
	if ( ! empty( $expect['zero'] ) && 0 !== $code ) {
		$failures[] = "{$label}: exited {$code}, expected 0.";
	}
	foreach ( (array) ( $expect['contains'] ?? array() ) as $needle ) {
		if ( false === stripos( $out, $needle ) ) {
			$failures[] = "{$label}: output lacks '{$needle}'. Got: " . trim( $out );
		}
	}
	WP_CLI::log( ( count( $failures ) > $already ? '  FAIL ' : '  ok   ' ) . $label );
};

$check( 'test:flow refuses on production', 'wpsspro test:flow all', 'production', array( 'nonzero' => true, 'contains' => 'production' ) );
$check( 'test:flow confirms the flow count', 'wpsspro test:flow all', 'local', array( 'zero' => true, 'contains' => 'test flows' ) );
$check( 'demo refuses on production', 'wpss-pro demo', 'production', array( 'nonzero' => true, 'contains' => 'production' ) );
$check( 'demo --fresh refuses on production', 'wpss-pro demo --fresh', 'production', array( 'nonzero' => true, 'contains' => 'production' ) );
$check( 'demo --clear refuses on production', 'wpss-pro demo --clear', 'production', array( 'nonzero' => true, 'contains' => 'production' ) );
$check( 'demo confirms what it writes', 'wpss-pro demo', 'local', array( 'zero' => true, 'contains' => 'Pro demo tables' ) );
$check( 'demo --clear names the row count', 'wpss-pro demo --clear', 'local', array( 'zero' => true, 'contains' => 'seeded Pro demo rows' ) );

foreach ( array( 'wpsspro test:flow', 'wpss-pro demo' ) as $writer ) {
	$check( "help {$writer} documents --force", 'help ' . $writer, 'local', array( 'zero' => true, 'contains' => '--force' ) );
}

if ( $failures ) {
	WP_CLI::log( '' );
	foreach ( $failures as $failure ) {
		WP_CLI::log( '  FAIL ' . $failure );
	}
	WP_CLI::error( count( $failures ) . ' Pro CLI guard check(s) failed.' );
}

WP_CLI::success( 'Pro CLI guard contract holds: 9 checks, nothing written or deleted.' );
