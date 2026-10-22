<?php
/**
 * The dashboard has one delete door and one bulk door; the old generic
 * service/bulk AJAX actions and ServiceManager::delete (which dropped packages
 * before core could refuse the delete) have no caller and no registration.
 *
 * Run: wp eval-file tests/test-dead-service-doors-are-gone.php
 *
 * Read-only.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

$check( 'no wpss_service_action AJAX door', false === has_action( 'wp_ajax_wpss_service_action' ) );
$check( 'no wpss_bulk_action AJAX door', false === has_action( 'wp_ajax_wpss_bulk_action' ) );
$check( 'AjaxHandlers has no service_action/bulk_action', ! method_exists( \WPSellServices\Frontend\AjaxHandlers::class, 'service_action' ) && ! method_exists( \WPSellServices\Frontend\AjaxHandlers::class, 'bulk_action' ) );
$check( 'ServiceManager has no delete that strips data before core can refuse', ! method_exists( \WPSellServices\Services\ServiceManager::class, 'delete' ) );

$source = (string) file_get_contents( WP_PLUGIN_DIR . '/wp-sell-services/src/Frontend/AjaxHandlers.php' );
$body   = substr( $source, (int) strpos( $source, 'public function delete_service' ), 1200 );
$check( 'the dashboard delete reports a failed trash', false !== strpos( $body, 'if ( ! wp_trash_post(' ) );
