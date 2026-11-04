<?php
/**
 * A seller cannot permanently delete a service that has orders (the cascade
 * would erase the buyers' order history and any dispute); trashing is fine,
 * and an admin may still delete.
 *
 * Run: wp eval-file tests/test-service-delete-guard-keeps-orders.php
 *
 * Read-only: it asks the guard and never deletes anything.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

defined( 'ABSPATH' ) || exit;

$check = static function ( string $label, bool $ok, string $detail = '' ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
};

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$with_orders = (int) $wpdb->get_var( "SELECT service_id FROM {$wpdb->prefix}wpss_orders WHERE service_id > 0 GROUP BY service_id ORDER BY COUNT(*) DESC LIMIT 1" );
$check( 'a service with orders exists', $with_orders > 0 );

$post = get_post( $with_orders );

wp_set_current_user( (int) $post->post_author );
$check( 'the owning vendor is refused a permanent delete', '' !== wpss_service_delete_refusal( $with_orders ) );
$check( 'the pre_delete_post guard stops it', false === apply_filters( 'pre_delete_post', null, $post, true ) );
$check( 'trashing is not stopped', null === apply_filters( 'pre_trash_post', null, $post ) );

wp_set_current_user( 1 );
$check( 'an admin may still delete', '' === wpss_service_delete_refusal( $with_orders ) );
