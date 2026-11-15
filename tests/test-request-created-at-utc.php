<?php
/**
 * A buyer request's REST created_at is the true UTC instant on a site that is not on UTC.
 *
 * Run: wp eval-file tests/test-request-created-at-utc.php
 *
 * The model already carries post_date_gmt, so converting it again shifted every
 * request by the site's offset (a UTC+5:30 site read 5.5 hours early).
 *
 * @package WPSellServices
 */

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

add_filter( 'pre_option_timezone_string', static fn() => 'Asia/Kolkata' );
add_filter( 'pre_option_gmt_offset', '__return_zero' );

$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admins[0] );

$id = wp_insert_post(
	array(
		'post_type'     => 'wpss_request',
		'post_status'   => 'publish',
		'post_title'    => 'QA created_at probe',
		'post_date'     => '2026-11-15 10:49:49',
		'post_date_gmt' => '2026-11-15 05:19:49',
		'meta_input'    => array(
			'_wpss_status'     => 'open',
			'_wpss_expires_at' => gmdate( 'Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS ),
			'_wpss_budget_min' => 50,
			'_wpss_budget_max' => 150,
		),
	)
);

foreach ( array( "/wpss/v1/buyer-requests/{$id}", '/wpss/v1/buyer-requests' ) as $route ) {
	$data = rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_data();
	$row  = isset( $data['id'] ) ? $data : null;
	foreach ( (array) $data as $item ) {
		if ( is_array( $item ) && (int) ( $item['id'] ?? 0 ) === $id ) {
			$row = $item;
		}
	}
	$check( "GET {$route}: the probe request is returned", null !== $row && (int) ( $row['id'] ?? 0 ) === $id );
	$got = (string) ( $row['created_at'] ?? '' );
	$check( "GET {$route}: created_at is the UTC instant", 0 === strpos( $got, '2026-11-15T05:19:49' ) && ( str_ends_with( $got, '+00:00' ) || str_ends_with( $got, 'Z' ) ), $got );
}

wp_delete_post( $id, true );
echo $fails ? "{$fails} FAILED\n" : "All passed\n";
