<?php
/**
 * Moderation has one set of constants, one view rule, an honest filter and an atomic publish lock
 * (Basecamp 10432413144).
 *
 * Run: wp eval-file tests/test-moderation-single-source.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Admin\Pages\ServiceModerationPage;
use WPSellServices\Services\ModerationService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$page = new ReflectionClass( ServiceModerationPage::class );
$check( 'moderation statuses are defined once (on ModerationService)', array() === array_filter( array_keys( $page->getConstants() ), static fn( $name ) => str_starts_with( $name, 'STATUS_' ) ) );
$check( 'one rule decides who sees an unpublished service', function_exists( 'wpss_can_view_unpublished_service' ) && ! $page->hasMethod( 'can_view_unapproved' ) );

// A site filter on wpss_require_service_moderation must be able to switch moderation on; the old
// bridge at priority 10 re-read the setting and threw the filtered value away.
$settings = get_option( 'wpss_vendor', array() );
$force_on = static fn() => true;
try {
	update_option( 'wpss_vendor', array_merge( (array) $settings, array( 'require_service_moderation' => false ) ) );
	add_filter( 'wpss_require_service_moderation', $force_on, 5 );
	$check( 'a site filter can turn service moderation on', true === ModerationService::is_enabled() );
} finally {
	remove_filter( 'wpss_require_service_moderation', $force_on, 5 );
	update_option( 'wpss_vendor', $settings );
}

$wizard = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Frontend/ServiceWizard.php' );
$check( 'the publish lock is atomic (named MySQL lock, not a transient check-then-set)', str_contains( $wizard, "GET_LOCK( %s, 0 )" ) && ! str_contains( $wizard, 'wpss_publishing_' . "' . \$user_id . '_' . \$service_id;\n\t\tif ( get_transient" ) && ! preg_match( '/set_transient\(\s*\$lock_key/', $wizard ) );

// The lock really excludes a second connection while the first holds it.
global $wpdb;
$name   = 'wpss_publishing_test_' . wp_generate_password( 6, false );
$second = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$first  = (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', $name ) );
$again  = (string) $second->get_var( $second->prepare( 'SELECT GET_LOCK( %s, 0 )', $name ) );
$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $name ) );
$check( 'a second request cannot take the publish lock while the first holds it', '1' === $first && '0' === $again );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
