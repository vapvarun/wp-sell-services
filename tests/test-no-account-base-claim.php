<?php
/**
 * We never claim the site's /account/ base: Learnomy and other plugins answer /account/billing/ and the like
 * (Basecamp 10393603134).
 *
 * Run: wp eval-file tests/test-no-account-base-claim.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// The adapter only runs on the standalone rail, so register its rules directly.
$adapter = ( new ReflectionClass( \WPSellServices\Integrations\Standalone\StandaloneAdapter::class ) )->newInstanceWithoutConstructor();
global $wp_rewrite;
$wp_rewrite->extra_rules_top = array();
$adapter->register_rewrite_rules();
$claimed = array_filter( array_keys( $wp_rewrite->extra_rules_top ), static fn( $re ) => 0 === strpos( (string) $re, '^account/' ) );
$check( 'no rewrite rule of ours answers /account/*', ! $claimed );
$check( 'wpss_account_page stays a query var for the shortcode', in_array( 'wpss_account_page', $adapter->add_query_vars( array() ), true ) );

exit( $fails ? 1 : 0 );
