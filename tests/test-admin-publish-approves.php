<?php
/**
 * An administrator's Publish approves a pending service, and an untouched
 * moderation select does not undo it.
 *
 * Run: wp eval-file tests/test-admin-publish-approves.php
 *
 * The editor posts the Moderation Status select as it was rendered ("pending").
 * save_moderation_status() ran after the Publish had already approved the
 * service, saw "pending" differ from the new "approved" state and demoted the
 * service back to pending, so Publish appeared to do nothing. The select now
 * carries the state it was rendered with, and an unchanged select is not a
 * decision. Option, post status and meta are restored on any exit.
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Pages\ServiceModerationPage;

// Registered before exit-on-fail.php: its handler exits on a FAIL and would skip
// anything registered after it.
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

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;

// A complete published service: one the publish rules let go live.
$service_id = 0;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpss_service' AND post_status = 'publish' ORDER BY ID DESC LIMIT 60" ) as $candidate ) {
	if ( ! wpss_get_service_publish_errors( (int) $candidate ) ) {
		$service_id = (int) $candidate;
		break;
	}
}

if ( ! $service_id ) {
	echo "SKIP  no complete published service on this install; nothing to assert\n";
	exit( 0 );
}

$orig_option = get_option( 'wpss_vendor' );
$orig_meta   = get_post_meta( $service_id, '_wpss_moderation_status', true );
$had_meta    = metadata_exists( 'post', $service_id, '_wpss_moderation_status' );

$restore = static function () use ( $wpdb, $service_id, $orig_option, $orig_meta, $had_meta ) {
	update_option( 'wpss_vendor', $orig_option );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $service_id ) );
	if ( $had_meta ) {
		update_post_meta( $service_id, '_wpss_moderation_status', $orig_meta );
	} else {
		delete_post_meta( $service_id, '_wpss_moderation_status' );
	}
	delete_post_meta( $service_id, '_wpss_publish_blocked' );
	clean_post_cache( $service_id );
};

update_option( 'wpss_vendor', array_merge( (array) $orig_option, array( 'require_service_moderation' => true ) ) );

$page = new ServiceModerationPage();
$page->init();
$page->register_guards();

wp_set_current_user( 1 );

$to_pending = static function () use ( $wpdb, $service_id ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $service_id ) );
	update_post_meta( $service_id, '_wpss_moderation_status', 'pending' );
	clean_post_cache( $service_id );
};
$state      = static function () use ( $service_id ) {
	clean_post_cache( $service_id );
	return get_post_status( $service_id ) . '/' . get_post_meta( $service_id, '_wpss_moderation_status', true );
};

// 1. The editor: Publish with the select left at the state it rendered.
$to_pending();
$_POST = array(
	'wpss_moderation_nonce'      => wp_create_nonce( 'wpss_moderation_metabox' ),
	'wpss_moderation_status'     => 'pending',
	'wpss_moderation_status_was' => 'pending',
);
wp_update_post( array( 'ID' => $service_id, 'post_status' => 'publish' ) );
$check( 'Publish with an untouched select approves and publishes', 'publish/approved' === $state(), $state() );

// 2. The select deliberately moved: that decision is honoured.
$_POST = array(
	'wpss_moderation_nonce'      => wp_create_nonce( 'wpss_moderation_metabox' ),
	'wpss_moderation_status'     => 'pending',
	'wpss_moderation_status_was' => 'approved',
);
wp_update_post( array( 'ID' => $service_id, 'post_status' => 'publish' ) );
$check( 'choosing Pending on a live service still puts it back in the queue', 'pending/pending' === $state(), $state() );

// 3. The metabox carries the state it rendered.
$source = (string) file_get_contents( dirname( __DIR__ ) . '/src/Admin/Pages/ServiceModerationPage.php' );
$check( 'metabox renders wpss_moderation_status_was', false !== strpos( $source, 'name="wpss_moderation_status_was"' ) );

$_POST = array();
wp_set_current_user( 0 );
exit( $fails ? 1 : 0 );
