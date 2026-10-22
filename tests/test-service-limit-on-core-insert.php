<?php
/**
 * The service limit holds on core's own insert path (wp-admin Add New, Quick
 * Edit, /wp/v2/wpss-services), not only in the wizard and the plugin's REST:
 * a member at the limit who publishes is saved as a draft; an admin, and a
 * service that already holds a slot, are untouched.
 *
 * Run: wp eval-file tests/test-service-limit-on-core-insert.php
 *
 * Sets the per-vendor maximum to 1 for the run and restores it; deletes the
 * QA services it inserts, on any exit.
 *
 * @package WPSellServices
 */

$created = array();
$option  = get_option( 'wpss_vendor', array() );
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

$restore = static function () use ( $option, &$created ) {
	update_option( 'wpss_vendor', $option );
	wp_set_current_user( 1 );
	foreach ( $created as $id ) {
		wp_delete_post( $id, true );
	}
};

$vendor = 7831;
$count  = (int) wpss_count_vendor_services( $vendor, array( 'publish', 'pending' ) );
$check( 'the QA vendor already holds services', $count >= 1, (string) $count );

update_option( 'wpss_vendor', array_merge( (array) $option, array( 'max_services_per_vendor' => 1 ) ) );

$insert = static function ( string $status ) use ( $vendor, &$created ): int {
	$id = (int) wp_insert_post(
		array(
			'post_type'   => 'wpss_service',
			'post_status' => $status,
			'post_title'  => 'QA-LIMIT core insert',
			'post_author' => $vendor,
		)
	);
	$created[] = $id;

	return $id;
};

// Pending, not publish: the existing publish rules also demote an incomplete
// service to a draft, which would hide what this limit does.
wp_set_current_user( $vendor );
$over = $insert( 'pending' );
$check( 'a member at the limit who submits is saved as a draft', 'draft' === get_post_status( $over ), (string) get_post_status( $over ) );

$draft = $insert( 'draft' );
$check( 'a draft is always allowed', 'draft' === get_post_status( $draft ) );

wp_set_current_user( 1 );
$by_admin = $insert( 'pending' );
$check( 'an admin is not limited', 'pending' === get_post_status( $by_admin ), (string) get_post_status( $by_admin ) );

// A service that already holds a slot keeps it on every later save.
wp_set_current_user( $vendor );
wp_update_post(
	array(
		'ID'         => $by_admin,
		'post_title' => 'QA-LIMIT core insert (edited)',
	)
);
$check( 'a service that holds a slot keeps it when edited', 'pending' === get_post_status( $by_admin ), (string) get_post_status( $by_admin ) );
