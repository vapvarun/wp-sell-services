<?php
/**
 * Pro performance and options contract.
 *
 * Basecamp 10264300095: Pro ran seven SHOW TABLES on every licensed request,
 * recomputed every analytics collector on every call, and kept DigitalOcean
 * Spaces settings under two option keys. This script fails on that code:
 *
 * 1. ProSchemaManager::maybe_upgrade() with a matching schema version runs
 *    zero SHOW TABLES (it re-checks once a day, not once a request).
 * 2. A collector result is served from cache on the second call (zero
 *    queries) and recomputed after wpss_order_paid flushes it.
 * 3. DigitalOcean settings saved under the legacy wpss_do_settings key are
 *    readable through wpss_do_spaces_settings after the one-shot migration,
 *    and the legacy key is gone.
 * 4. dashboard.css, analytics.css and admin.css carry no hex colour outside
 *    a :root token block.
 *
 * Run: wp eval-file tests/pro/test-pro-perf-and-options.php
 *
 * @package WPSellServices
 */

use WPSellServicesPro\Analytics\AnalyticsManager;
use WPSellServicesPro\Database\ProSchemaManager;

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

/**
 * Count queries matching a pattern issued while running a callback.
 *
 * @param callable $fn      Callback.
 * @param string   $pattern Regex, or '' for every query.
 * @return int
 */
function wpss_count_queries( callable $fn, string $pattern = '' ): int {
	global $wpdb;
	$before = count( $wpdb->queries );
	$fn();
	$ran = array_slice( $wpdb->queries, $before );
	if ( '' === $pattern ) {
		return count( $ran );
	}
	return count( array_filter( $ran, static fn( $q ) => preg_match( $pattern, $q[0] ) ) );
}

echo "\nPro performance and options contract\n\n";

global $wpdb;

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}
$wpdb->queries = array();

// 1. Schema check: matching version, checked recently → no SHOW TABLES.
$saved_checked = get_option( 'wpss_pro_db_checked' );
update_option( 'wpss_pro_db_checked', time() );
$show_tables = wpss_count_queries(
	static function () {
		( new ProSchemaManager() )->maybe_upgrade();
	},
	'/^\s*SHOW TABLES/i'
);
wpss_t( 0 === $show_tables, "schema: maybe_upgrade with a matching version runs 0 SHOW TABLES (ran {$show_tables})" );
if ( false === $saved_checked ) {
	delete_option( 'wpss_pro_db_checked' );
} else {
	update_option( 'wpss_pro_db_checked', $saved_checked );
}

// 2. Analytics cache: second call is free, wpss_order_paid flushes it.
$manager = AnalyticsManager::instance();
$manager->init(); // Collectors only register on admin/AJAX requests; WP-CLI is neither.
$start   = gmdate( 'Y-m-01' );
$end     = gmdate( 'Y-m-d' );
if ( method_exists( $manager, 'flush_cache' ) ) {
	AnalyticsManager::flush_cache();
}
$first  = wpss_count_queries( static fn() => $manager->get_data( $start, $end, 'orders' ), '/wpss_orders/i' );
$second = wpss_count_queries( static fn() => $manager->get_data( $start, $end, 'orders' ), '/wpss_orders/i' );
wpss_t( $first > 0, "analytics: first collector call hits wpss_orders ({$first} queries)" );
wpss_t( 0 === $second, "analytics: second collector call is served from cache (ran {$second} queries)" );
do_action( 'wpss_order_paid', 0, 'contract' );
$third = wpss_count_queries( static fn() => $manager->get_data( $start, $end, 'orders' ), '/wpss_orders/i' );
wpss_t( $third > 0, "analytics: wpss_order_paid flushes the cache ({$third} queries after flush)" );
if ( method_exists( $manager, 'flush_cache' ) ) {
	AnalyticsManager::flush_cache();
}

// 3. DigitalOcean settings: legacy key migrates onto the driver's key.
$saved_legacy = get_option( 'wpss_do_settings' );
$saved_spaces = get_option( 'wpss_do_spaces_settings' );
delete_option( 'wpss_do_spaces_settings' );
update_option(
	'wpss_do_settings',
	array(
		'key'    => 'contract-key',
		'secret' => 'contract-secret',
		'space'  => 'contract-space',
		'region' => 'sfo3',
	)
);
if ( function_exists( 'WPSellServicesPro\\wpss_pro_migrate_do_settings' ) ) {
	\WPSellServicesPro\wpss_pro_migrate_do_settings();
}
$spaces = (array) get_option( 'wpss_do_spaces_settings', array() );
wpss_t( 'contract-key' === ( $spaces['access_key'] ?? null ) && 'contract-secret' === ( $spaces['secret_key'] ?? null ) && 'sfo3' === ( $spaces['region'] ?? null ), 'do: legacy wpss_do_settings is readable through wpss_do_spaces_settings after migration' );
wpss_t( false === get_option( 'wpss_do_settings' ), 'do: legacy wpss_do_settings is deleted after migration' );
wpss_t( false !== strpos( (string) file_get_contents( WPSS_PRO_PLUGIN_DIR . 'uninstall.php' ), "'wpss_do_spaces_settings'" ), 'do: uninstall.php removes wpss_do_spaces_settings' );
if ( false === $saved_legacy ) {
	delete_option( 'wpss_do_settings' );
} else {
	update_option( 'wpss_do_settings', $saved_legacy );
}
if ( false === $saved_spaces ) {
	delete_option( 'wpss_do_spaces_settings' );
} else {
	update_option( 'wpss_do_spaces_settings', $saved_spaces );
}

// 4. Sheets: no hex outside :root token blocks.
foreach ( array( 'dashboard', 'analytics', 'admin' ) as $sheet ) {
	$css = (string) file_get_contents( WPSS_PRO_PLUGIN_DIR . "assets/css/{$sheet}.css" );
	$css = preg_replace( '/:root[^{]*\{[^}]*\}/s', '', $css );
	$hex = preg_match_all( '/#[0-9a-fA-F]{6}\b/', (string) $css );
	wpss_t( 0 === $hex, "css: {$sheet}.css has no 6-digit hex outside :root blocks (found {$hex})" );
}

echo "\n  {$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n\n";
