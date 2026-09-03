<?php
/**
 * The REST surface an app client actually needs.
 *
 * Run: wp eval-file tests/test-rest-app-surface.php
 *
 * Four things the mobile app could not do (Basecamp 10264288331): reset a
 * password by username, download a private order file with its token, be
 * protected by a per-account lockout rather than a per-IP counter, and list
 * a page of orders without one query per row.
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( '' !== $detail ? "  [{$detail}]" : '' ) . "\n";
	$fails += $ok ? 0 : 1;
};

$call = static function ( string $method, string $route, array $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
};

global $wpdb;

$order = $wpdb->get_row( "SELECT id, customer_id, vendor_id FROM {$wpdb->prefix}wpss_orders WHERE customer_id > 0 AND vendor_id > 0 ORDER BY id DESC LIMIT 1" );

if ( ! $order ) {
	echo "no usable order; skipping\n";
	return;
}

$order_id = (int) $order->id;
$buyer_id = (int) $order->customer_id;
$original = get_current_user_id();
$original_ip = $_SERVER['REMOTE_ADDR'] ?? null;

// --- 1. Forgot password accepts a username -------------------------------
$buyer = get_userdata( $buyer_id );
add_filter( 'pre_wp_mail', '__return_true' ); // Do not actually send.
wp_set_current_user( 0 );
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

$response = $call( 'POST', '/wpss/v1/auth/forgot-password', array( 'user_login' => $buyer->user_login ) );
$check( 'forgot-password with user_login answers 200', 200 === $response->get_status(), (string) $response->get_status() . ' ' . wp_json_encode( $response->get_data() ) );

$response = $call( 'POST', '/wpss/v1/auth/forgot-password', array( 'email' => $buyer->user_email ) );
$check( 'forgot-password with email still answers 200', 200 === $response->get_status() );

$response = $call( 'POST', '/wpss/v1/auth/forgot-password', array() );
$check( 'forgot-password with neither answers 400', 400 === $response->get_status() );
remove_filter( 'pre_wp_mail', '__return_true' );

// --- 2. Per-account lockout, independent of IP ---------------------------
$login = 'wpss-f14-lock-' . wp_rand( 1000, 9999 );
$lock_user = wp_create_user( $login, wp_generate_password( 20 ), $login . '@example.test' );

if ( is_wp_error( $lock_user ) ) {
	$check( 'could create a throwaway user for the lockout check', false, $lock_user->get_error_message() );
} else {
	$last = null;
	for ( $i = 1; $i <= 6; $i++ ) {
		// Three attempts from each of two addresses: the per-IP limiter (5 per
		// 300s) never trips, so only an account counter can lock this.
		$_SERVER['REMOTE_ADDR'] = $i <= 3 ? '198.51.100.1' : '198.51.100.2';
		$last                   = $call( 'POST', '/wpss/v1/auth/login', array( 'username' => $login, 'password' => 'wrong-' . $i ) );
	}
	$code = $last instanceof WP_REST_Response && $last->get_data() ? ( $last->get_data()['code'] ?? '' ) : '';
	$check( 'sixth wrong password for one account is refused as locked', 'wpss_account_locked' === $code, (string) $last->get_status() . ' ' . $code );
	$check( 'lockout answers 423', 423 === $last->get_status(), (string) $last->get_status() );

	foreach ( array( '198.51.100.1', '198.51.100.2', '203.0.113.10' ) as $ip ) {
		foreach ( array( 'login', 'forgot_password' ) as $action ) {
			delete_transient( 'wpss_rate_' . $action . '_' . md5( $ip ) );
		}
	}
	delete_transient( 'wpss_login_lock_' . md5( strtolower( $login ) ) );
	delete_transient( 'wpss_login_fails_' . md5( strtolower( $login ) ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( (int) $lock_user );
}

// --- 3. Private order file over REST -------------------------------------
$file_id = wp_generate_uuid4();
$dir     = wpss_get_order_files_dir() . $order_id . '/';
if ( ! is_dir( $dir ) ) {
	wp_mkdir_p( $dir );
}
$path = $dir . 'f14-contract.txt';
file_put_contents( $path, 'f14 bytes' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

$record = array(
	'id'          => $file_id,
	'name'        => 'f14-contract.txt',
	'type'        => 'text/plain',
	'size'        => 9,
	'path'        => 'f14-contract.txt',
	'order_id'    => $order_id,
	'kind'        => 'delivery',
	'remote_path' => null,
	'provider'    => null,
);
$wpdb->insert(
	$wpdb->prefix . 'wpss_deliveries',
	array(
		'order_id'    => $order_id,
		'vendor_id'   => (int) $order->vendor_id,
		'message'     => 'F14 contract delivery',
		'attachments' => wp_json_encode( array( $record ) ),
		'version'     => 99,
		'status'      => 'pending',
	)
);
$delivery_id = (int) $wpdb->insert_id;

wp_set_current_user( $buyer_id );
$response = $call( 'GET', "/wpss/v1/orders/{$order_id}/deliverables" );
$file_url = '';
foreach ( (array) $response->get_data() as $delivery ) {
	foreach ( (array) ( $delivery['files'] ?? array() ) as $file ) {
		if ( ( $file['id'] ?? '' ) === $file_id ) {
			$file_url = (string) ( $file['url'] ?? '' );
		}
	}
}
$expected_route = "/wpss/v1/orders/{$order_id}/files/{$file_id}";
$check( 'deliverables[].url points at the REST file route', false !== strpos( $file_url, $expected_route ), $file_url );

$response = $call( 'GET', $expected_route );
$data     = (array) $response->get_data();
$check( 'buyer GET on the file route answers 200', 200 === $response->get_status(), (string) $response->get_status() . ' ' . wp_json_encode( $data ) );
$check( 'file route names the file', ( $data['name'] ?? '' ) === 'f14-contract.txt' );

$stranger = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT u.ID FROM {$wpdb->users} u WHERE u.ID NOT IN ( %d, %d ) AND u.ID NOT IN ( SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s ) LIMIT 1",
		$buyer_id,
		(int) $order->vendor_id,
		$wpdb->prefix . 'capabilities',
		'%administrator%'
	)
);
if ( $stranger ) {
	wp_set_current_user( $stranger );
	$response = $call( 'GET', $expected_route );
	$check( 'stranger GET on the file route answers 403', 403 === $response->get_status(), (string) $response->get_status() );
}

wp_set_current_user( 0 );
$response = $call( 'GET', $expected_route );
$check( 'anonymous GET on the file route answers 401', 401 === $response->get_status(), (string) $response->get_status() );

$wpdb->delete( $wpdb->prefix . 'wpss_deliveries', array( 'id' => $delivery_id ) );
wp_delete_file( $path );

// --- 4. GET /orders is a fixed number of queries -------------------------
$busiest = $wpdb->get_row( "SELECT customer_id AS uid, COUNT(*) AS n FROM {$wpdb->prefix}wpss_orders WHERE customer_id > 0 GROUP BY customer_id ORDER BY n DESC LIMIT 1" );
wp_set_current_user( (int) $busiest->uid );
wp_cache_flush();

$before = $wpdb->num_queries;
$response = $call( 'GET', '/wpss/v1/orders', array( 'per_page' => 100 ) );
$queries = $wpdb->num_queries - $before;
$rows    = count( (array) $response->get_data() );
$check( "GET /orders per_page=100 ({$rows} rows) runs under 20 queries", $queries < 20, "{$queries} queries" );

wp_set_current_user( $original );
if ( null === $original_ip ) {
	unset( $_SERVER['REMOTE_ADDR'] );
} else {
	$_SERVER['REMOTE_ADDR'] = $original_ip;
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
