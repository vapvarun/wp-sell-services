<?php
/**
 * Upgrading to 1.7.1 keeps the people who were already selling.
 *
 * Run: wp eval-file tests/test-seller-migration.php
 *
 * 1.7.1 made an ACTIVE wpss_vendor_profiles row the definition of a vendor and
 * took the seller capabilities off the author role, which on a live site locks
 * out everybody who was selling without a profile row. This asserts that
 * Activator::migrate_existing_sellers() gives those people - and only those
 * people - the row back.
 *
 * Runs against code WITHOUT the migration too: it then reports the sellers as
 * locked out, which is the "before" half of the evidence.
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

use WPSellServices\Core\Activator;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$profiles_table  = $wpdb->prefix . 'wpss_vendor_profiles';
$orders_table    = $wpdb->prefix . 'wpss_orders';
$proposals_table = $wpdb->prefix . 'wpss_proposals';
$audit_table     = $wpdb->prefix . 'wpss_audit_log';

$has_migration = method_exists( Activator::class, 'migrate_existing_sellers' );
echo $has_migration ? "-- with the migration --\n" : "-- BEFORE: no migration in this build --\n";

// --- Seed ------------------------------------------------------------------
$make_user = static function ( string $slug, string $role ) {
	return wp_insert_user(
		array(
			'user_login' => 'wpss_mig_' . $slug . '_' . wp_rand( 1000, 9999 ),
			'user_pass'  => wp_generate_password(),
			'role'       => $role,
		)
	);
};

$author_service  = $make_user( 'authorsvc', 'author' );   // Author with a published service.
$author_order    = $make_user( 'authorord', 'author' );   // Author with an order, no service.
$role_proposal   = $make_user( 'roleprop', 'wpss_vendor' ); // Vendor role, no profile, a proposal.
$author_idle     = $make_user( 'authoridle', 'author' );  // Author with nothing at all.
$existing_vendor = $make_user( 'existing', 'wpss_vendor' ); // Already has an active profile.
$buyer           = $make_user( 'buyer', 'subscriber' );

foreach ( array( $author_service, $author_order, $role_proposal, $author_idle, $existing_vendor, $buyer ) as $seeded ) {
	if ( is_wp_error( $seeded ) ) {
		echo "could not seed users; skipping\n";
		return;
	}
}

// The author role lost the seller caps in 1.7.1; on a real upgrade these users
// still carried the legacy vendor meta, which is one of the signals the
// migration reads. Seed it so the fixture matches a real pre-1.7.1 site.
update_user_meta( $author_service, '_wpss_is_vendor', true );
update_user_meta( $author_order, '_wpss_is_vendor', true );

$service_id = wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'post_title'  => 'Migration fixture service',
		'post_author' => $author_service,
	)
);

$wpdb->insert(
	$orders_table,
	array(
		'order_number'   => 'WPSS-MIG-CONTRACT',
		'customer_id'    => $buyer,
		'vendor_id'      => $author_order,
		'service_id'     => 0,
		'platform'       => 'standalone',
		'total'          => 100.000,
		'currency'       => 'USD',
		'status'         => 'in_progress',
		'payment_status' => 'paid',
		'created_at'     => current_time( 'mysql' ),
	)
);
$order_id = (int) $wpdb->insert_id;

$request_id = wp_insert_post(
	array(
		'post_type'   => 'wpss_request',
		'post_status' => 'publish',
		'post_title'  => 'Migration fixture request',
		'post_author' => $buyer,
	)
);

$wpdb->insert(
	$proposals_table,
	array(
		'request_id'     => $request_id,
		'vendor_id'      => $role_proposal,
		'cover_letter'   => 'Migration fixture proposal.',
		'proposed_price' => 50.00,
		'proposed_days'  => 3,
		'status'         => 'pending',
		'created_at'     => current_time( 'mysql' ),
	)
);
$proposal_id = (int) $wpdb->insert_id;

$wpdb->insert(
	$profiles_table,
	array(
		'user_id'      => $existing_vendor,
		'display_name' => 'Already a vendor',
		'status'       => 'active',
		'created_at'   => current_time( 'mysql' ),
	)
);
$existing_profile_id = (int) $wpdb->insert_id;
\WPSellServices\Models\VendorProfile::flush_memo( $existing_vendor );

$row_count = static function ( int $user_id ) use ( $wpdb, $profiles_table ): int {
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$profiles_table} WHERE user_id = %d", $user_id ) );
};
$audit_rows = static function ( int $user_id ) use ( $wpdb, $audit_table ): int {
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$audit_table} WHERE object_type = 'vendor' AND event_type = 'vendor.migrated' AND object_id = %d",
			$user_id
		)
	);
};
$fresh = static function ( int $user_id ): bool {
	\WPSellServices\Models\VendorProfile::flush_memo( $user_id );
	return wpss_is_vendor( $user_id );
};

// --- Before ----------------------------------------------------------------
$check( 'author-role seller with a published service is locked out before the migration', false === $fresh( $author_service ) );
$check( 'author-role seller with an order is locked out before the migration', false === $fresh( $author_order ) );
$check( 'vendor-role seller with a proposal is locked out before the migration', false === $fresh( $role_proposal ) );

if ( ! $has_migration ) {
	echo "\nBEFORE run complete: the three sellers above cannot sell on this build.\n";
} else {
	$option_before = get_option( Activator::MIGRATED_SELLERS_OPTION, null );

	// The migration is site-wide, and a long-lived database has sellers of its
	// own that legitimately qualify. Snapshot what it would change so the run
	// can be undone completely, and assert on the seeded five rather than on a
	// total this script does not own.
	$vendor_meta_before = array_map( 'intval', $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = '_wpss_is_vendor'" ) );
	$vendor_role_before = array_map( 'intval', (array) get_users( array( 'role' => 'wpss_vendor', 'fields' => 'ID', 'number' => -1 ) ) );

	// --- Migrate -----------------------------------------------------------
	$migrated = Activator::migrate_existing_sellers();

	$check( 'the migration reports the sellers it moved, the seeded three included', $migrated >= 3 );

	$check( 'author-role seller with a service can sell again', true === $fresh( $author_service ) );
	$check( 'author-role seller with an order can sell again', true === $fresh( $author_order ) );
	$check( 'vendor-role seller with a proposal can sell again', true === $fresh( $role_proposal ) );

	$check( '  and each holds the vendor role', array_reduce(
		array( $author_service, $author_order, $role_proposal ),
		static fn( $ok, $id ) => $ok && in_array( 'wpss_vendor', (array) get_userdata( $id )->roles, true ),
		true
	) );
	$check( '  and each can reach the seller dashboard sections', array_reduce(
		array( $author_service, $author_order, $role_proposal ),
		static fn( $ok, $id ) => $ok && null === wpss_vendor_status_block( $id ),
		true
	) );
	$check( '  and each is marked as migrated for review', array_reduce(
		array( $author_service, $author_order, $role_proposal ),
		static fn( $ok, $id ) => $ok && (bool) get_user_meta( $id, Activator::MIGRATED_SELLER_META, true ),
		true
	) );
	$check( '  with one audit row each', 1 === $audit_rows( $author_service ) && 1 === $audit_rows( $author_order ) && 1 === $audit_rows( $role_proposal ) );

	// --- Nothing new granted -----------------------------------------------
	$check( 'the author with nothing to sell is not migrated', 0 === $row_count( $author_idle ) );
	$check( '  and is not a vendor', false === $fresh( $author_idle ) );
	$check( '  and did not gain the vendor role', ! in_array( 'wpss_vendor', (array) get_userdata( $author_idle )->roles, true ) );

	// --- The vendor who already had a profile -------------------------------
	$check( 'the existing vendor keeps exactly one profile row', 1 === $row_count( $existing_vendor ) );
	$check( '  the same row', $existing_profile_id === (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$profiles_table} WHERE user_id = %d", $existing_vendor ) ) );
	$check( '  and was not marked as migrated', '' === (string) get_user_meta( $existing_vendor, Activator::MIGRATED_SELLER_META, true ) );

	// --- The owner is told --------------------------------------------------
	$check( 'the migrated count is recorded for the notice', $migrated === (int) get_option( Activator::MIGRATED_SELLERS_OPTION, 0 ) );

	wp_set_current_user( 1 );
	require_once ABSPATH . 'wp-admin/includes/screen.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
	set_current_screen( 'toplevel_page_wpss-dashboard' );
	ob_start();
	( new \WPSellServices\Admin\Admin() )->migrated_sellers_notice();
	$notice = (string) ob_get_clean();
	$check( 'the notice names the right count', false !== strpos( $notice, number_format_i18n( $migrated ) . ' sellers kept their selling access' ) );
	$check( '  and links to the migrated vendors list', false !== strpos( $notice, 'page=wpss-vendors&#038;status=migrated' ) || false !== strpos( $notice, 'page=wpss-vendors&status=migrated' ) );
	$check( '  and can be dismissed', false !== strpos( $notice, 'data-notice="sellers"' ) );

	update_user_meta( get_current_user_id(), '_wpss_sellers_notice_dismissed', (string) $migrated );
	ob_start();
	( new \WPSellServices\Admin\Admin() )->migrated_sellers_notice();
	$check( '  and stays dismissed', '' === trim( (string) ob_get_clean() ) );
	delete_user_meta( get_current_user_id(), '_wpss_sellers_notice_dismissed' );
	wp_set_current_user( 0 );

	// --- Re-run -------------------------------------------------------------
	( new \WPSellServices\Services\VendorService() )->set_status( $author_order, 'suspended' );

	$second = Activator::migrate_existing_sellers();
	$check( 'a second run migrates nobody', 0 === $second );
	$check( '  and does not duplicate a profile row', 1 === $row_count( $author_service ) && 1 === $row_count( $author_order ) );
	$check( '  and does not add a second audit row', 1 === $audit_rows( $author_service ) );
	$check( 'a suspended migrated seller stays suspended', 'suspended' === wpss_get_vendor_status( $author_order ) );
	$check( '  and cannot sell', false === $fresh( $author_order ) );

	// The migration must not have overwritten the recorded count with 0.
	$check( 'the recorded count survives a re-run', $migrated === (int) get_option( Activator::MIGRATED_SELLERS_OPTION, 0 ) );

	// --- The owner can review them -----------------------------------------
	$listed = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT vp.user_id FROM {$profiles_table} vp
			WHERE EXISTS ( SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id = vp.user_id AND um.meta_key = %s )",
			Activator::MIGRATED_SELLER_META
		)
	);
	$listed = array_map( 'intval', (array) $listed );
	$check( 'the Vendors migrated filter lists every migrated seller', $migrated === count( $listed ) );
	$check( '  including the three seeded ones', array() === array_diff( array( $author_service, $author_order, $role_proposal ), $listed ) );
	$check( '  and nobody who was not migrated', ! in_array( $author_idle, $listed, true ) && ! in_array( $existing_vendor, $listed, true ) );

	// --- Undo the run on this database --------------------------------------
	// Everything the migration wrote, including for sellers this script did not
	// seed. Runs before the seeded users are deleted so they are covered too.
	foreach ( $listed as $touched ) {
		$wpdb->delete( $profiles_table, array( 'user_id' => $touched ) );
		$wpdb->delete( $audit_table, array( 'object_type' => 'vendor', 'object_id' => $touched, 'event_type' => 'vendor.migrated' ) );
		delete_user_meta( $touched, Activator::MIGRATED_SELLER_META );

		if ( ! in_array( $touched, $vendor_meta_before, true ) ) {
			delete_user_meta( $touched, '_wpss_is_vendor' );
		}
		if ( ! in_array( $touched, $vendor_role_before, true ) ) {
			$restore = get_userdata( $touched );
			if ( $restore instanceof WP_User ) {
				$restore->remove_role( 'wpss_vendor' );
			}
		}

		\WPSellServices\Models\VendorProfile::flush_memo( $touched );
	}

	// The migration is worthless if the upgrade path stops calling it.
	$check(
		'the 1.7.1 upgrade block runs the migration',
		false !== strpos( (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Core/Plugin.php' ), 'Activator::migrate_existing_sellers()' ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	);

	if ( null === $option_before ) {
		delete_option( Activator::MIGRATED_SELLERS_OPTION );
	} else {
		update_option( Activator::MIGRATED_SELLERS_OPTION, $option_before, false );
	}
}

// --- Cleanup ---------------------------------------------------------------
wp_set_current_user( 0 );
wp_delete_post( (int) $service_id, true );
wp_delete_post( (int) $request_id, true );
$wpdb->delete( $orders_table, array( 'id' => $order_id ) );
$wpdb->delete( $proposals_table, array( 'id' => $proposal_id ) );

require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( array( $author_service, $author_order, $role_proposal, $author_idle, $existing_vendor, $buyer ) as $seeded ) {
	$wpdb->delete( $profiles_table, array( 'user_id' => $seeded ) );
	$wpdb->delete( $audit_table, array( 'object_type' => 'vendor', 'object_id' => $seeded ) );
	wp_delete_user( $seeded );
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
