<?php
/**
 * Pro's EDD rail registers its standing check with EDD. The check lived in a
 * method nothing ever hooked, so a banned buyer could add and buy a service.
 *
 * Run: wp eval-file tests/test-pro-edd-standing-is-hooked.php
 *
 * Source check, read-only. SKIPs when Pro is not installed. The live proof (real
 * EDD, banned vs active buyer) is the Docker run logged on the card.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$dir = WP_PLUGIN_DIR . '/wp-sell-services-pro/src/Integrations/EDD';
if ( ! is_dir( $dir ) ) {
	echo "SKIP  Pro is not installed\n";
	return;
}

$adapter  = (string) file_get_contents( $dir . '/EDDAdapter.php' );
$provider = (string) file_get_contents( $dir . '/EDDCheckoutProvider.php' );

$check( 'the adapter binds block_banned_buyer to edd_can_purchase_download', 1 === preg_match( "/add_filter\(\s*'edd_can_purchase_download',\s*\[\s*\\\$this->checkout_provider,\s*'block_banned_buyer'/", $adapter ) );
$check( 'the provider defines block_banned_buyer', false !== strpos( $provider, 'function block_banned_buyer(' ) );
$check( 'the provider asks wpss_account_status_block in one place', 1 === substr_count( $provider, "wpss_account_status_block'" ), (string) substr_count( $provider, "wpss_account_status_block'" ) );
