<?php
/**
 * Legacy order-file migration contract.
 *
 * Files uploaded before 1.7.0 sit in wp-content/uploads with no check in front
 * of them - the URL is unlisted, not secret, and anyone holding it can fetch
 * the file forever. Order requirements are where buyers hand over briefs,
 * contracts and sometimes identity documents (Basecamp 10239807824).
 *
 * The decision was to migrate ON ACCESS: a bulk move breaks every link already
 * sitting in an inbox, and doing nothing leaves history public. Moving the file
 * when someone with permission opens it keeps the link working and closes the
 * hole as people touch their own files.
 *
 * Run: wp eval-file tests/test-legacy-file-migration-contract.php
 *
 * @package WPSellServices
 */

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

echo "\nLegacy order-file migration contract\n\n";

global $wpdb;

$order = $wpdb->get_row( "SELECT id, customer_id, vendor_id FROM {$wpdb->prefix}wpss_orders WHERE customer_id > 0 AND vendor_id > 0 LIMIT 1" );

if ( ! $order ) {
	echo "  SKIP  no usable order on this install\n";
	return;
}

$uploads = wp_get_upload_dir();
$name    = 'wpss-migration-probe-' . wp_generate_password( 6, false ) . '.txt';
$public  = trailingslashit( $uploads['basedir'] ) . $name;
$url     = trailingslashit( $uploads['baseurl'] ) . $name;

file_put_contents( $public, 'PROBE CONTENTS' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

$file_id = 'migration-probe-' . wp_generate_password( 6, false );

$wpdb->insert(
	$wpdb->prefix . 'wpss_deliveries',
	array(
		'order_id'    => (int) $order->id,
		'vendor_id'   => (int) $order->vendor_id,
		'message'     => 'migration probe',
		'attachments' => wp_json_encode( array( array( 'id' => $file_id, 'name' => $name, 'url' => $url, 'size' => 14, 'type' => 'text/plain' ) ) ),
		'status'      => 'delivered',
		'created_at'  => current_time( 'mysql' ),
	)
);
$delivery_id = (int) $wpdb->insert_id;

$cleanup = static function () use ( $wpdb, $delivery_id, $public, $order, $name ) {
	$wpdb->delete( $wpdb->prefix . 'wpss_deliveries', array( 'id' => $delivery_id ) );
	foreach ( array( $public, wpss_get_order_files_dir() . (int) $order->id . '/' . $name ) as $p ) {
		if ( file_exists( $p ) ) {
			wp_delete_file( $p );
		}
	}
};

// 1. A stranger is not handed the public URL just because the record is old.
wp_set_current_user( 0 );
$record = array( 'id' => $file_id, 'order_id' => (int) $order->id, 'url' => $url );
wpss_t( '' === wpss_get_order_file_url( $record ), 'a stranger is given no URL for a legacy file' );

// 2. The buyer is routed through the checked endpoint, not the raw upload.
wp_set_current_user( (int) $order->customer_id );
$buyer_url = wpss_get_order_file_url( $record );
wpss_t( false !== strpos( $buyer_url, 'action=wpss_order_file' ), 'the buyer is routed through the permission-checked endpoint' );
wpss_t( false === strpos( $buyer_url, '/uploads/' ), 'the buyer is not handed the raw uploads path' );

// 3. Migration moves the bytes, rewrites the record, and removes the public copy.
$found    = wpss_find_order_file( (int) $order->id, $file_id );
$migrated = $found ? wpss_migrate_legacy_order_file( $found, (int) $order->id ) : null;

wpss_t( is_array( $migrated ), 'the file migrates' );
wpss_t( is_array( $migrated ) && ! empty( $migrated['path'] ), 'the migrated record carries a private path' );
wpss_t( is_array( $migrated ) && ! isset( $migrated['url'] ), 'the public url is dropped, so the file has ONE address' );
wpss_t( ! file_exists( $public ), 'the public copy is removed - this is the whole point' );
wpss_t( file_exists( wpss_get_order_files_dir() . (int) $order->id . '/' . $name ), 'the bytes are in the private store' );

// 4. The rewrite reached the database, not just the returned array.
$reread = wpss_find_order_file( (int) $order->id, $file_id );
wpss_t( is_array( $reread ) && ! empty( $reread['path'] ), 'the rewritten record is what the database now holds' );

// 5. Migrating again is a no-op rather than a second copy.
wpss_t( null === wpss_migrate_legacy_order_file( $reread, (int) $order->id ), 'an already-private file is not migrated twice' );

// 6. Path traversal is refused - the resolver must not reach outside uploads.
wpss_t( null === wpss_resolve_local_path_from_url( trailingslashit( $uploads['baseurl'] ) . '../../../wp-config.php' ), 'a traversal URL resolves to nothing' );
wpss_t( null === wpss_resolve_local_path_from_url( 'https://example.com/evil.txt' ), 'an offsite URL resolves to nothing' );

$cleanup();
wp_set_current_user( 0 );

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";
