<?php
/**
 * A setting means the same thing on the Settings screen and at runtime.
 *
 * Run: wp eval-file tests/test-settings-defaults.php
 *
 * Guards Basecamp 10264286815: the screen showed "Auto-start on timeout"
 * ticked while the cron read a missing key as false and cancelled the order;
 * decimals / currency position were saved and never read; min / max order
 * amount were saved and never enforced; review_window_days was read and had
 * no field. Every option touched here is snapshotted and restored.
 *
 * @package WPSellServices
 */

use WPSellServices\Checkout\CheckoutIntentService;
use WPSellServices\Services\OrderWorkflowManager;
use WPSellServices\Services\ReviewService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$orders_table = $wpdb->prefix . 'wpss_orders';

$snapshot = array();
foreach ( array( 'wpss_orders', 'wpss_advanced', 'wpss_decimal_places', 'wpss_max_order_amount', 'wpss_min_order_amount' ) as $name ) {
	$snapshot[ $name ] = get_option( $name, null );
}
$restore = static function () use ( $snapshot ) {
	foreach ( $snapshot as $name => $value ) {
		if ( null === $value ) {
			delete_option( $name );
		} else {
			update_option( $name, $value );
		}
	}
};

// 1. Missing key resolves to the shared default, and the cron acts on it.
$orders = is_array( $snapshot['wpss_orders'] ) ? $snapshot['wpss_orders'] : array();
unset( $orders['auto_start_on_timeout'] );
// 365 so no real order on the site is old enough to be touched by the sweep.
$orders['requirements_timeout_days'] = 365;
update_option( 'wpss_orders', $orders );
wp_cache_delete( 'alloptions', 'options' );

$check( 'auto_start_on_timeout defaults to true when the key is absent', true === wpss_get_option( 'orders', 'auto_start_on_timeout' ) );

$wpdb->insert(
	$orders_table,
	array(
		'order_number'   => 'WPSS-SETTINGS-DEFAULTS-CONTRACT',
		'customer_id'    => 1,
		'vendor_id'      => 1,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'total'          => 10.000,
		'currency'       => 'USD',
		'status'         => 'pending_requirements',
		'payment_status' => 'pending',
		'created_at'     => gmdate( 'Y-m-d H:i:s', time() - 400 * DAY_IN_SECONDS ),
	)
);
$order_id = (int) $wpdb->insert_id;

if ( $order_id ) {
	( new OrderWorkflowManager() )->check_requirements_timeout();
	$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$orders_table} WHERE id = %d", $order_id ) );
	$check( 'timed-out order without requirements is started, not cancelled', 'in_progress' === $status );

	$wpdb->delete( $orders_table, array( 'id' => $order_id ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpss_notifications WHERE type = 'requirements_timeout' AND data LIKE %s", '%"order_id":' . $order_id . '%' ) );
} else {
	$check( 'could seed a throwaway order', false );
}

// 2. Decimals and position are display settings, so they must change the display.
update_option( 'wpss_decimal_places', 0 );
$advanced                      = is_array( $snapshot['wpss_advanced'] ) ? $snapshot['wpss_advanced'] : array();
$advanced['currency_position'] = 'after';
update_option( 'wpss_advanced', $advanced );
$symbol = wpss_get_currency_symbol( wpss_get_currency() );
$check( 'wpss_format_price honours decimals=0 and position=after', 99 . $symbol === wpss_format_price( 99 ) );

// 3. A total above the maximum is refused before any gateway sees it.
$service_id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'wpss_service' AND post_status = 'publish' ORDER BY ID ASC LIMIT 1" );
$packages   = $service_id ? get_post_meta( $service_id, '_wpss_packages', true ) : array();
$author_id  = $service_id ? (int) get_post_field( 'post_author', $service_id ) : 0;
$buyer_id   = 1 === $author_id ? (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->users} WHERE ID <> 1 ORDER BY ID ASC LIMIT 1" ) : 1;

if ( $service_id && is_array( $packages ) && $packages && $buyer_id ) {
	update_option( 'wpss_max_order_amount', 0.01 );
	update_option( 'wpss_min_order_amount', 0 );
	$intent = ( new CheckoutIntentService() )->resolve(
		array(
			'service_id' => $service_id,
			'package_id' => array_key_first( $packages ),
		),
		$buyer_id
	);
	$check( 'single intent above the maximum returns WP_Error', is_wp_error( $intent ) && 'wpss_above_maximum' === $intent->get_error_code() );
} else {
	$check( 'a published service with a package exists to price', false );
}

// 4. The review window is whatever the owner saved.
$orders['review_window_days'] = 45;
update_option( 'wpss_orders', $orders );
$check( 'review window read equals the setting', 45 === ( new ReviewService() )->get_review_window_days() );

$restore();

if ( $fails ) {
	echo "\nFAIL ({$fails})\n";
	exit( 1 );
}

echo "PASS - settings render, seed and read from one defaults source.\n";
