<?php
/**
 * Uninstall WP Sell Services
 *
 * Runs when the plugin is deleted via WordPress admin.
 *
 * @package WPSellServices
 * @since   1.0.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check if we should delete all data.
$delete_data = get_option( 'wpss_delete_data_on_uninstall', false );

if ( ! $delete_data ) {
	return;
}

global $wpdb;

// Define plugin directory for autoloader.
if ( ! defined( 'WPSS_PLUGIN_DIR' ) ) {
	define( 'WPSS_PLUGIN_DIR', __DIR__ . '/' );
}

// Register PSR-4 autoloader (works without Composer vendor directory).
spl_autoload_register(
	function ( string $class_name ): void {
		$prefix   = 'WPSellServices\\';
		$base_dir = WPSS_PLUGIN_DIR . 'src/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class_name, $len );
		$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
);

// Try to load Composer autoloader safely (only if all files exist).
$autoloader     = __DIR__ . '/vendor/autoload.php';
$autoload_files = __DIR__ . '/vendor/composer/autoload_files.php';

if ( file_exists( $autoloader ) ) {
	$can_load = true;
	if ( file_exists( $autoload_files ) ) {
		$files = require $autoload_files;
		foreach ( $files as $file ) {
			if ( ! file_exists( $file ) ) {
				$can_load = false;
				break;
			}
		}
	}
	if ( $can_load ) {
		require_once $autoloader;
	}
}

use WPSellServices\Database\SchemaManager;

// Drop all tables.
$schema = new SchemaManager();
$schema->uninstall();

// Delete custom post types and their data.
$post_types = array( 'wpss_service', 'wpss_request' );

foreach ( $post_types as $post_type ) {
	$posts = get_posts(
		array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		)
	);

	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

// Delete taxonomies and terms.
$taxonomies = array( 'wpss_service_category', 'wpss_service_tag' );

foreach ( $taxonomies as $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( $term_id, $taxonomy );
		}
	}
}

// Delete all plugin options.
$options = array(
	'wpss_db_version',
	'wpss_schema_version',
	'wpss_general_settings',
	'wpss_notification_settings',
	'wpss_vendor_settings',
	'wpss_activated_at',
	'wpss_migrated_from_wss',
	'wpss_migration_date',
	'wpss_delete_data_on_uninstall',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Delete all options with wpss_ prefix.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wpss\_%'" );

// Delete user meta.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_wpss\_%'" );

// Delete post meta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_wpss\_%'" );

// Remove capabilities.
$capabilities = array(
	'wpss_manage_services',
	'wpss_manage_orders',
	'wpss_view_analytics',
	'wpss_respond_to_requests',
	'wpss_manage_settings',
	'wpss_manage_disputes',
	'wpss_manage_vendors',
);

$roles = array( 'administrator', 'shop_manager', 'author', 'editor' );

foreach ( $roles as $role_name ) {
	$role = get_role( $role_name );
	if ( $role ) {
		foreach ( $capabilities as $cap ) {
			$role->remove_cap( $cap );
		}
	}
}

// Clear any remaining transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpss\_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_wpss\_%'" );

// Clear scheduled cron events.
$cron_hooks = array(
	'wpss_auto_complete_orders',
	'wpss_cleanup_expired_requests',
	'wpss_update_vendor_stats',
);

foreach ( $cron_hooks as $hook ) {
	$timestamp = wp_next_scheduled( $hook );
	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, $hook );
	}
}

// Flush rewrite rules.
flush_rewrite_rules();
