<?php
/**
 * One definition of who is a vendor: an ACTIVE wpss_vendor_profiles row.
 *
 * The stats cron selected vendors with a bare `_wpss_is_vendor` meta query, so
 * every seller created by the wizard, the seeder or a role grant kept stale or
 * zero stats forever; the public search matched role OR that meta, so it
 * published suspended vendors and role-holders with no profile row
 * (Basecamp 10268057607).
 *
 * Run: wp eval-file tests/test-vendor-identity-queries.php
 *
 * @package WPSellServices
 */

use WPSellServices\Database\Repositories\VendorProfileRepository;
use WPSellServices\Services\OrderWorkflowManager;
use WPSellServices\Services\VendorService;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;

$token    = 'wpssvendorid' . wp_rand( 1000, 9999 );
$repo     = new VendorProfileRepository();
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';

// Sentinel the sweep overwrites: update_stats() recomputes total_orders from
// the orders table, and these throwaway users have no orders.
$sentinel = 4242;

/**
 * Create one throwaway user.
 *
 * @param string $slug   Suffix for the login.
 * @param string $token  Shared search token.
 * @param bool   $role   Whether to grant the vendor role.
 * @param bool   $meta   Whether to write the legacy `_wpss_is_vendor` meta.
 * @return int User ID.
 */
$make_user = static function ( string $slug, string $token, bool $role, bool $meta ): int {
	$user_id = wp_insert_user(
		array(
			'user_login'   => $token . '_' . $slug,
			'user_pass'    => wp_generate_password(),
			'user_email'   => $token . '_' . $slug . '@example.invalid',
			'display_name' => $token . ' ' . $slug,
		)
	);

	if ( $role ) {
		( new WP_User( $user_id ) )->add_role( VendorService::ROLE );
	}
	if ( $meta ) {
		update_user_meta( $user_id, '_wpss_is_vendor', true );
	}

	return (int) $user_id;
};

// Active vendor that also carries the legacy meta (the only kind the old
// queries found).
$legacy = $make_user( 'legacy', $token, true, true );
// Active vendor without it - what the wizard, admin screen and seeder create.
$modern = $make_user( 'modern', $token, true, false );
// Suspended vendor, meta and role intact: must never be swept or published.
$suspended = $make_user( 'suspended', $token, true, true );
// Role holder with no profile row at all.
$roleonly = $make_user( 'roleonly', $token, true, false );

foreach ( array( $legacy => 'active', $modern => 'active', $suspended => 'suspended' ) as $user_id => $status ) {
	$repo->upsert(
		$user_id,
		array(
			'display_name' => $token,
			'status'       => $status,
			'total_orders' => $sentinel,
		)
	);
}

$total_orders = static function ( int $user_id ) use ( $wpdb, $profiles ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$value = $wpdb->get_var( $wpdb->prepare( "SELECT total_orders FROM {$profiles} WHERE user_id = %d", $user_id ) );

	return null === $value ? null : (int) $value;
};

// --- the helper answers with the same set as wpss_is_vendor() -------------
// function_exists so this file also runs against a build without the fix and
// reports failures instead of fatalling.
$has_helper = function_exists( 'wpss_get_active_vendor_ids' );
$active_ids = $has_helper ? wpss_get_active_vendor_ids() : array();

$check( 'helper wpss_get_active_vendor_ids() exists', $has_helper );
$check( 'helper lists the active vendor that has the legacy meta', in_array( $legacy, $active_ids, true ) );
$check( 'helper lists the active vendor that has no legacy meta', in_array( $modern, $active_ids, true ) );
$check( 'helper omits the suspended vendor', $has_helper && ! in_array( $suspended, $active_ids, true ) );
$check( 'helper omits the role holder with no profile row', $has_helper && ! in_array( $roleonly, $active_ids, true ) );
$check(
	'helper agrees with wpss_is_vendor() on all four',
	wpss_is_vendor( $legacy ) && wpss_is_vendor( $modern ) && ! wpss_is_vendor( $suspended ) && ! wpss_is_vendor( $roleonly )
);
$check( 'helper pages (limit/offset)', $has_helper && count( wpss_get_active_vendor_ids( 1 ) ) === 1 );

// --- the stats cron sweeps exactly the active vendors ----------------------
( new OrderWorkflowManager() )->update_vendor_stats();

$check( 'cron refreshes the vendor with the legacy meta', $sentinel !== $total_orders( $legacy ) );
$check( 'cron refreshes the vendor without the legacy meta', $sentinel !== $total_orders( $modern ) );
$check( 'cron skips the suspended vendor', $sentinel === $total_orders( $suspended ) );
$check( 'cron creates no profile for the role holder', null === $total_orders( $roleonly ) );

// --- the public search returns active vendors only ------------------------
wp_set_current_user( 0 );

$request = new WP_REST_Request( 'GET', '/wpss/v1/search' );
$request->set_param( 'q', $token );
$request->set_param( 'type', 'vendors' );

$body       = rest_do_request( $request )->get_data();
$found      = wp_list_pluck( $body['vendors'] ?? array(), 'id' );
$found      = array_map( 'intval', $found );
$found_sort = $found;
sort( $found_sort );
$expected = array( $legacy, $modern );
sort( $expected );

$check( 'public search returns exactly the two active vendors', $found_sort === $expected );
$check( 'public search never returns the suspended vendor', ! in_array( $suspended, $found, true ) );
$check( 'public search never returns the role holder with no profile row', ! in_array( $roleonly, $found, true ) );
$check( 'public search total matches the rows returned', (int) ( $body['vendors_total'] ?? -1 ) === count( $expected ) );

// --- the public vendor list uses the same definition ----------------------
// Searched by the shared token so the page holds these four users and nothing
// else - the default ordering is by rating, which would bury them.
$list_request = new WP_REST_Request( 'GET', '/wpss/v1/vendors' );
$list_request->set_param( 'search', $token );

$list = rest_do_request( $list_request )->get_data();
$ids  = array_map( 'intval', wp_list_pluck( is_array( $list ) ? $list : array(), 'id' ) );
sort( $ids );

$check( 'public /vendors returns exactly the two active vendors', $ids === $expected );
$check( 'public /vendors omits the suspended vendor', ! in_array( $suspended, $ids, true ) );
$check( 'public /vendors omits the role holder with no profile row', ! in_array( $roleonly, $ids, true ) );

// --- nothing READS the legacy meta to decide who is a vendor ---------------
$readers = array();

// The one legitimate read: the 1.7.1 upgrade migration asks what access a user
// held BEFORE the upgrade, which is history, not a live vendor query.
$historic = 'src/Core/Activator.php';

foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPSS_PLUGIN_DIR . 'src' ) ) as $file ) {
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}

	foreach ( file( $file->getPathname() ) as $number => $line ) {
		$trimmed = ltrim( $line );

		if ( false === strpos( $line, '_wpss_is_vendor' ) ) {
			continue;
		}
		// Comments are documentation, and update_/delete_user_meta are writes -
		// the meta stays written for back-compat, it just decides nothing.
		if ( '' === $trimmed || '*' === $trimmed[0] || 0 === strpos( $trimmed, '//' ) || 0 === strpos( $trimmed, '#' ) ) {
			continue;
		}
		if ( preg_match( '/(update|delete)_user_meta/', $line ) ) {
			continue;
		}

		$path = str_replace( WPSS_PLUGIN_DIR, '', $file->getPathname() );

		if ( $historic === $path ) {
			continue;
		}

		$readers[] = $path . ':' . ( $number + 1 );
	}
}

$check( 'no code path reads the legacy meta to decide vendor status: ' . ( $readers ? implode( ', ', $readers ) : 'none' ), empty( $readers ) );

// --- cleanup ---------------------------------------------------------------
require_once ABSPATH . 'wp-admin/includes/user.php';

foreach ( array( $legacy, $modern, $suspended, $roleonly ) as $user_id ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete( $profiles, array( 'user_id' => $user_id ), array( '%d' ) );
	wp_delete_user( $user_id );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$profiles} WHERE display_name = %s", $token ) );
$check( 'throwaway rows removed', 0 === $left && ! get_user_by( 'login', $token . '_legacy' ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nALL PASS\n";
exit( $fails ? 1 : 0 );
