<?php
/**
 * PHPStan bootstrap file — defines constants and stubs.
 *
 * @package WPSellServices
 */

// Plugin constants.
define( 'WPSS_VERSION', '1.0.0' );
define( 'WPSS_PLUGIN_FILE', __DIR__ . '/wp-sell-services.php' );
define( 'WPSS_PLUGIN_DIR', __DIR__ . '/' );
define( 'WPSS_PLUGIN_URL', 'https://example.com/wp-content/plugins/wp-sell-services/' );
define( 'WPSS_PLUGIN_BASENAME', 'wp-sell-services/wp-sell-services.php' );

// WordPress config constant (set in wp-config.php at runtime; declared here so
// static analysis knows it exists).
define( 'DB_NAME', 'wordpress' );

// WP-CLI stubs for static analysis.
require_once __DIR__ . '/src/CLI/wp-cli-stubs.php';
require_once __DIR__ . '/src/CLI/wp-cli-utils-stubs.php';
