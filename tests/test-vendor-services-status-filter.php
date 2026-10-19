<?php
/**
 * My Services: the status tiles are links, and each narrows the list to that status
 * (Basecamp 10403854262).
 *
 * Run: wp eval-file tests/test-vendor-services-status-filter.php
 *
 * Seeds a throwaway vendor with an active, a draft, a rejected (draft + moderation meta) and a pending service,
 * runs the list query for every filter, and renders the section to read the tile links.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$suffix = wp_generate_password( 6, false, false );
$vendor = \WPSellServices\Tests\Factories\UserFactory::vendor( array( 'user_login' => 'wpss_sf_vendor_' . $suffix, 'user_email' => 'wpss_sf_vendor_' . $suffix . '@example.test' ) );
$make   = static fn( string $status, string $title ): int => (int) wp_insert_post( array( 'post_type' => 'wpss_service', 'post_status' => $status, 'post_title' => $title . ' ' . $suffix, 'post_author' => $vendor->ID ) );

$active   = $make( 'publish', 'QA active' );
$draft    = $make( 'draft', 'QA draft' );
$rejected = $make( 'draft', 'QA rejected' );
$pending  = $make( 'pending', 'QA pending' );
update_post_meta( $rejected, '_wpss_moderation_status', 'rejected' );

$ids = static function ( string $filter ) use ( $vendor ): array {
	$q   = new WP_Query( array_merge( wpss_vendor_services_list_args( $vendor->ID, $filter ), array( 'fields' => 'ids' ) ) );
	$out = array_map( 'intval', $q->posts );
	sort( $out );

	return $out;
};
$sorted = static function ( array $a ): array {
	sort( $a );

	return $a;
};

$check( 'all lists every status', $ids( '' ) === $sorted( array( $active, $draft, $rejected, $pending ) ) );
$check( 'an unknown filter is all', $ids( 'bogus' ) === $ids( '' ) );
$check( 'publish lists only the active one', $ids( 'publish' ) === array( $active ) );
$check( 'draft leaves the rejected one out', $ids( 'draft' ) === array( $draft ) );
$check( 'pending lists only the pending one', $ids( 'pending' ) === array( $pending ) );
$check( 'rejected lists only the rejected one', $ids( 'rejected' ) === array( $rejected ) );

wp_set_current_user( $vendor->ID );
$_GET['services_status'] = 'draft';
$user_id                 = $vendor->ID;
ob_start();
include WPSS_PLUGIN_DIR . 'templates/dashboard/sections/services.php';
$html = (string) ob_get_clean();
unset( $_GET['services_status'] );

$check( 'the tiles are links', substr_count( $html, 'wpss-stat-card wpss-stat-card--link' ) >= 4 );
$check( 'each tile carries its status', false !== strpos( $html, 'services_status=publish' ) && false !== strpos( $html, 'services_status=pending' ) && false !== strpos( $html, 'services_status=rejected' ) );
$check( 'the chosen tile is marked current', 1 === substr_count( $html, 'aria-current="true"' ) );
$check( 'the filtered list shows the draft and not the active one', false !== strpos( $html, 'QA draft ' . $suffix ) && false === strpos( $html, 'QA active ' . $suffix ) );

foreach ( array( $active, $draft, $rejected, $pending ) as $id ) {
	wp_delete_post( $id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $vendor->ID );

exit( $fails ? 1 : 0 );
