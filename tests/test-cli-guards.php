<?php
/**
 * Every writing CLI command refuses on production and confirms its count, and
 * `demo delete` selects only marked demo content.
 *
 * Before this guard existed `wp wpss demo delete --yes` removed every service
 * on the site (the documented --all flag was never read), `demo marketplace`
 * rewrote the homepage setting, and `scale seed` / `test:flow` wrote users,
 * orders and ledger rows into live tables with no prompt and no environment
 * check (Basecamp 10264284996). `scale teardown` then prompted without a count
 * and never checked the environment, and `scale bench --teardown` hardcoded the
 * answer so it deleted without asking at all (Basecamp 10268057471).
 *
 * Run: wp eval-file tests/test-cli-guards.php
 *
 * Each case runs a real `wp wpss ...` subprocess with WP_ENVIRONMENT_TYPE
 * forced through --exec (it beats both the env var and wp-config), answers
 * "n" on stdin, then checks the exit code, the output, and that the row counts
 * did not move. Nothing here ever answers "y".
 *
 * The cases that pass --yes run only after the no-flag cases pass: on the old
 * code --yes skipped the only prompt and deleted every service, so a red gate
 * stops before reaching them.
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

global $wpdb;

$failures = array();

$wp_bin = $GLOBALS['argv'][0];
$wp_bin = is_executable( $wp_bin ) ? escapeshellarg( $wp_bin ) : escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $wp_bin );

$counts = static function () use ( $wpdb ): array {
	return array(
		'services' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wpss_service'" ),
		'posts'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ),
		'users'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" ),
		'vendors'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpss_vendor_profiles" ),
		// Raw reads: the subprocesses write, and this process's option cache would not notice.
		'options'  => implode( ':', $wpdb->get_col( "SELECT option_value FROM {$wpdb->options} WHERE option_name IN ( 'show_on_front', 'page_on_front' ) ORDER BY option_name" ) ),
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
		$failures[] = "{$label}: rows or settings changed (" . wp_json_encode( $before ) . ' -> ' . wp_json_encode( $after ) . ').';
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
	foreach ( (array) ( $expect['lacks'] ?? array() ) as $needle ) {
		if ( false !== stripos( $out, $needle ) ) {
			$failures[] = "{$label}: output must not contain '{$needle}'. Got: " . trim( $out );
		}
	}
	WP_CLI::log( ( count( $failures ) > $already ? '  FAIL ' : '  ok   ' ) . $label );
};

// One marked demo service, so the delete cases have something in scope and
// the positive case at the end can prove that only this row goes.
$fixture = (int) wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_title'  => 'CLI guard contract fixture',
		'post_status' => 'publish',
	)
);
if ( $fixture <= 0 ) {
	WP_CLI::error( 'Could not insert the fixture service.' );
}
update_post_meta( $fixture, '_wpss_demo_content', 1 );

// The command's own count: every status get_posts() calls "any" (no trash, no auto-draft).
$total = count(
	get_posts(
		array(
			'post_type'      => 'wpss_service',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	)
);

// Gate: safe on the old code because no case passes --yes.
$check( 'demo delete refuses on production', 'wpss demo delete', 'production', array( 'nonzero' => true, 'contains' => 'production' ) );
$check( 'demo delete scopes to demo content', 'wpss demo delete', 'local', array( 'contains' => '1 demo posts', 'lacks' => "Delete {$total} services" ) );
$check( 'demo delete --all needs --yes', 'wpss demo delete --all', 'local', array( 'nonzero' => true, 'contains' => '--yes' ) );

if ( $failures ) {
	wp_delete_post( $fixture, true );
	WP_CLI::log( '' );
	WP_CLI::log( 'Stopping before the --yes cases: on unguarded code they would delete real rows.' );
	foreach ( $failures as $failure ) {
		WP_CLI::log( '  FAIL ' . $failure );
	}
	WP_CLI::error( count( $failures ) . ' CLI guard check(s) failed.' );
}

$check( 'demo delete --all --yes still confirms the site-wide count', 'wpss demo delete --all --yes', 'local', array( 'zero' => true, 'contains' => array( (string) $total, 'every service' ) ) );
$check( 'demo create refuses on production', 'wpss demo create --count=1', 'production', array( 'nonzero' => true ) );
$check( 'demo create confirms the count', 'wpss demo create --count=1', 'local', array( 'zero' => true, 'contains' => '1 demo services' ) );
$check( 'demo marketplace refuses on production', 'wpss demo marketplace --no-images', 'production', array( 'nonzero' => true ) );
$check( 'regenerate-meta refuses on production', 'wpss service regenerate-meta', 'production', array( 'nonzero' => true ) );
$check( 'scale seed refuses on production', 'wpss scale seed --vendors=1 --orders-per-vendor=1', 'production', array( 'nonzero' => true ) );
$check( 'test:flow refuses on production', 'wpss test:flow service-purchase', 'production', array( 'nonzero' => true ) );

$check( 'scale teardown refuses on production', 'wpss scale teardown', 'production', array( 'nonzero' => true, 'contains' => 'production' ) );
$check( 'scale teardown names the row count', 'wpss scale teardown', 'local', array( 'zero' => true, 'contains' => 'scale-benchmark rows' ) );

// bench --teardown used to hardcode the answer, so it deleted without asking
// even on production. It now asks the same question a human-run teardown asks.
$check( 'scale bench --teardown asks before deleting', 'wpss scale bench --teardown', 'local', array( 'zero' => true, 'contains' => 'scale-benchmark rows' ) );

// Read-only commands must be untouched by the guard: no refusal, whatever they
// report. Exit codes are theirs to decide (preflight and rest:contract fail the
// build on a real problem), so only the refusal is asserted.
foreach ( array(
	'service list --format=count',
	'service stats',
	'validate',
	'preflight',
	'rest:contract',
	'api:shapes',
) as $read_only ) {
	$check( "{$read_only} is not gated", 'wpss ' . $read_only, 'production', array( 'lacks' => 'Refusing on a production site' ) );
}

// Every writing command documents the override, so `wp help` shows it.
foreach ( array(
	'wpss demo create',
	'wpss demo delete',
	'wpss demo marketplace',
	'wpss service regenerate-meta',
	'wpss scale seed',
	'wpss scale teardown',
	'wpss test:flow',
) as $writer ) {
	$check( "help {$writer} documents --force", 'help ' . $writer, 'local', array( 'zero' => true, 'contains' => '--force' ) );
}

// The positive case: --yes on a dev site deletes the marked fixture and nothing else.
$before             = $counts();
list( $code, $out ) = $run( 'wpss demo delete --yes', 'local' );
$after              = $counts();
$expected           = $before;
--$expected['services'];
--$expected['posts'];
$fixture_row        = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID = %d", $fixture ) );
if ( 0 !== $code || $after !== $expected || null !== $fixture_row ) {
	$failures[] = 'demo delete --yes must remove exactly the marked fixture: exit ' . $code . ', ' . wp_json_encode( $before ) . ' -> ' . wp_json_encode( $after ) . '. Got: ' . trim( $out );
	wp_delete_post( $fixture, true );
	WP_CLI::log( '  FAIL demo delete --yes removes only marked demo content' );
} else {
	WP_CLI::log( '  ok   demo delete --yes removes only marked demo content' );
}

if ( $failures ) {
	WP_CLI::log( '' );
	foreach ( $failures as $failure ) {
		WP_CLI::log( '  FAIL ' . $failure );
	}
	WP_CLI::error( count( $failures ) . ' CLI guard check(s) failed.' );
}

WP_CLI::success( 'CLI guard contract holds: 27 checks, nothing but the fixture written or deleted.' );
