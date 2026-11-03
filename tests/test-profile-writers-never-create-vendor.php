<?php
/**
 * Counter and flag writers never turn a member into a vendor.
 *
 * A vendor profile row defaults to status 'active', and an active row is what
 * wpss_is_vendor() answers from. update_stats() runs for whoever an order or a
 * review names, and it created the row when none existed, so a member named on
 * an order (an administrator selling their own service, a buyer-turned-member
 * whose profile was removed) became an active vendor in the directory. Found on
 * a fresh CI database, where test-order-action-gates.php saw a role-only vendor
 * answer true.
 *
 * Run: wp eval-file tests/test-profile-writers-never-create-vendor.php
 *
 * Creates one member and a profile row for a second, removes both.
 *
 * @package WPSellServices
 */

use WPSellServices\Database\Repositories\VendorProfileRepository;
use WPSellServices\Models\VendorProfile;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table  = $wpdb->prefix . 'wpss_vendor_profiles';
$repo   = new VendorProfileRepository();
$member = (int) wp_insert_user(
	array(
		'user_login' => 'qa_profile_writer_' . wp_rand( 10000, 99999 ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$vendor = (int) wp_insert_user(
	array(
		'user_login' => 'qa_profile_vendor_' . wp_rand( 10000, 99999 ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$rows   = static function ( int $id ) use ( $wpdb, $table ): int {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
};

try {
	$writers = array(
		'update_stats'             => static fn() => $repo->update_stats( $member ),
		'set_vacation_mode'        => static fn() => $repo->set_vacation_mode( $member, true, 'away' ),
		'set_availability'         => static fn() => $repo->set_availability( $member, true ),
		'update_verification_tier' => static fn() => $repo->update_verification_tier( $member, 'pro' ),
	);
	foreach ( $writers as $name => $write ) {
		$write();
		VendorProfile::flush_memo( $member );
		$check( "{$name}() on a member with no profile creates no row", 0 === $rows( $member ) );
		$check( "  and the member is still not a vendor after {$name}()", false === wpss_is_vendor( $member ) );
	}

	// The real door: an order naming the member as its vendor.
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prefix . 'wpss_orders',
		array(
			'order_number' => 'QA-WRITER-' . wp_generate_password( 5, false ),
			'customer_id'  => $vendor,
			'vendor_id'    => $member,
			'status'       => 'pending_payment',
			'total'        => 10,
			'currency'     => wpss_get_currency(),
			'created_at'   => current_time( 'mysql' ),
		)
	);
	$order_id = (int) $wpdb->insert_id;
	do_action( 'wpss_order_created', $order_id, array( 'vendor_id' => $member ) );
	VendorProfile::flush_memo( $member );
	$check( 'an order naming the member as vendor leaves them a non-vendor', false === wpss_is_vendor( $member ) && 0 === $rows( $member ) );

	// A real vendor still gets counters and flags.
	$wpdb->insert( $table, array( 'user_id' => $vendor, 'display_name' => 'QA profile writer', 'status' => 'active' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$check( 'an existing vendor profile still takes its stats', true === $repo->update_stats( $vendor ) );
	$check( 'an existing vendor profile still takes vacation mode', true === $repo->set_vacation_mode( $vendor, true, 'away' ) && 1 === (int) $wpdb->get_var( $wpdb->prepare( "SELECT vacation_mode FROM {$table} WHERE user_id = %d", $vendor ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$check( 'a suspended vendor stays suspended after a stats refresh', ( static function () use ( $wpdb, $table, $repo, $vendor ): bool {
		$wpdb->update( $table, array( 'status' => 'suspended' ), array( 'user_id' => $vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$repo->update_stats( $vendor );
		VendorProfile::flush_memo( $vendor );
		return 'suspended' === wpss_get_vendor_status( $vendor );
	} )() );
} finally {
	if ( ! empty( $order_id ) ) {
		$wpdb->delete( $wpdb->prefix . 'wpss_orders', array( 'id' => $order_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	$wpdb->delete( $table, array( 'user_id' => $member ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->delete( $table, array( 'user_id' => $vendor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $member );
	wp_delete_user( $vendor );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nALL PASS\n";
