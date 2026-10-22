<?php
/**
 * Every audit event the plugins write has a label (and the Reports filter), and
 * a reply that pushes a dispute's deadline out earns the next reminder.
 *
 * Run: wp eval-file tests/test-audit-labels-and-dispute-reminder-reset.php
 *
 * Reads source files; touches one dispute row's meta and deadline and puts it
 * back on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Services\AuditLogService;
use WPSellServices\Services\DisputeWorkflowManager;

$restore = null;
register_shutdown_function(
	static function () use ( &$restore ) {
		if ( is_callable( $restore ) ) {
			$restore();
		}
	}
);

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

// 1. Every literal event key passed to log() has a label.
$labels  = AuditLogService::get_event_labels();
$roots   = array( dirname( __DIR__ ) . '/src', dirname( __DIR__, 2 ) . '/wp-sell-services-pro/src' );
$missing = array();
foreach ( $roots as $root ) {
	if ( ! is_dir( $root ) ) {
		continue;
	}
	foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) ) as $file ) {
		if ( 'php' !== $file->getExtension() ) {
			continue;
		}
		if ( preg_match_all( "/(?:->log|AuditLogService::log)\\s*\\(\\s*'([a-z_]+\\.[a-z_]+)'/", (string) file_get_contents( $file->getPathname() ), $m ) ) {
			foreach ( $m[1] as $key ) {
				if ( ! isset( $labels[ $key ] ) ) {
					$missing[ $key ] = true;
				}
			}
		}
	}
}
$check( 'every written audit event has a label', ! $missing, implode( ', ', array_keys( $missing ) ) );

$object_labels = new ReflectionMethod( '\WPSellServices\Admin\Pages\AuditLogPage', 'object_labels' );
$check( 'report rows can be filtered by type', isset( $object_labels->invoke( null )['report'] ) );

// 2. A new deadline clears the one-per-dispute reminder marker.
global $wpdb;
$table = $wpdb->prefix . 'wpss_disputes';
$row   = $wpdb->get_row( "SELECT id, meta, response_deadline, last_response_by, updated_at FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

if ( ! $row ) {
	$check( 'a dispute exists to test against', false );
	return;
}

$restore = static function () use ( $wpdb, $table, $row ) {
	$wpdb->update( $table, array( 'meta' => $row['meta'], 'response_deadline' => $row['response_deadline'], 'last_response_by' => $row['last_response_by'], 'updated_at' => $row['updated_at'] ), array( 'id' => $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
};

$meta                  = json_decode( (string) $row['meta'], true ) ?: array();
$meta['reminder_sent'] = '2026-01-01 00:00:00';
$meta['keep_me']       = 'x';
$wpdb->update( $table, array( 'meta' => wp_json_encode( $meta ) ), array( 'id' => $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

$method = new ReflectionMethod( DisputeWorkflowManager::class, 'update_response_deadline' );
$method->invoke( new DisputeWorkflowManager(), (int) $row['id'], 1 );

$after = json_decode( (string) $wpdb->get_var( $wpdb->prepare( "SELECT meta FROM {$table} WHERE id = %d", $row['id'] ) ), true ) ?: array(); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
$check( 'the reminder marker is cleared by a new deadline', ! isset( $after['reminder_sent'] ), wp_json_encode( $after ) );
$check( 'other dispute meta is kept', 'x' === ( $after['keep_me'] ?? '' ) );
