<?php
/**
 * The services grid (shortcode, block, REST grid) lists what the catalog lists.
 *
 * Run: wp eval-file tests/test-services-grid-contract.php
 *
 * wpss_render_services_grid() sorted by meta_key, so a service without that
 * meta row dropped out ("rating" listed 5 of 12, "sales" none: it read a key
 * nothing writes), and it had no vacation rule, so it listed vendors the
 * catalog and REST hide (Basecamp 10375174916, 10375176013). Makes its own
 * vendor and two services, one rated and one not, and removes them.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';
require_once __DIR__ . '/Factories/UserFactory.php';
require_once __DIR__ . '/Factories/ServiceFactory.php';

use WPSellServices\Tests\Factories\ServiceFactory;
use WPSellServices\Tests\Factories\UserFactory;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$profiles = $wpdb->prefix . 'wpss_vendor_profiles';
$suffix   = wp_generate_password( 6, false, false );
$vendor   = UserFactory::vendor( array( 'user_login' => 'wpss_grid_vendor_' . $suffix, 'user_email' => 'wpss_grid_vendor_' . $suffix . '@example.test' ) );
$ids      = array();

foreach ( array( 'rated', 'unrated' ) as $which ) {
	$made = ServiceFactory::single_plan( array( 'vendor_id' => $vendor->ID ) );
	$id   = is_object( $made ) ? (int) $made->id : 0;

	if ( $id && 'publish' !== get_post_status( $id ) ) {
		wp_set_current_user( 1 );
		( new \WPSellServices\Services\ModerationService() )->approve( $id );
		wp_set_current_user( 0 );
		clean_post_cache( $id );
	}

	delete_post_meta( $id, '_wpss_rating_average' );
	delete_post_meta( $id, '_wpss_order_count' );
	if ( 'rated' === $which ) {
		update_post_meta( $id, '_wpss_rating_average', 4.5 );
	}
	$ids[] = $id;
}

$total = static fn( array $attrs ): int => (int) wpss_render_services_grid( $attrs + array( 'postsPerPage' => 1 ), 1 )['total'];

// Whether the vendor is on vacation is one row in the profile table.
$vacation = static function ( int $on ) use ( $wpdb, $profiles, $vendor ): void {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$profiles} WHERE user_id = %d", $vendor->ID ) ) ) {
		$wpdb->insert( $profiles, array( 'user_id' => $vendor->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	}
	$wpdb->update( $profiles, array( 'vacation_mode' => $on, 'vacation_return_date' => null ), array( 'user_id' => $vendor->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	wp_cache_flush();
};

try {
	if ( 2 !== count( array_filter( $ids, static fn( $id ) => 'publish' === get_post_status( $id ) ) ) ) {
		echo "FAIL  could not create two published fixture services\n";
		exit( 1 );
	}

	$vacation( 0 );
	$by_date = $total( array( 'orderBy' => 'date' ) );
	$mine    = $total( array( 'orderBy' => 'date', 'vendor' => $vendor->ID ) );

	$check( 'the grid lists both fixture services', 2 === $mine );
	$check( 'sorted by rating it lists as many as by date', $by_date === $total( array( 'orderBy' => 'rating' ) ) );
	$check( '  and by sales', $by_date === $total( array( 'orderBy' => 'sales' ) ) );
	$check( '  and by price, either direction', $by_date === $total( array( 'orderBy' => 'price', 'order' => 'ASC' ) ) && $by_date === $total( array( 'orderBy' => 'price' ) ) );
	$check( '  the unrated service is in the rating sort', 2 === $total( array( 'orderBy' => 'rating', 'vendor' => $vendor->ID ) ) );

	$first = wpss_render_services_grid( array( 'postsPerPage' => 1, 'orderBy' => 'rating', 'vendor' => $vendor->ID ), 1 )['html'];
	$check( '  and the rated one comes first', false !== strpos( $first, get_permalink( $ids[0] ) ) );

	$vacation( 1 );
	$check( 'a vendor on vacation is not in the grid', $by_date - 2 === $total( array( 'orderBy' => 'date' ) ) );
	$check( '  under a sort either', $by_date - 2 === $total( array( 'orderBy' => 'rating' ) ) );
	$check( '  but a grid of that one vendor keeps their services', 2 === $total( array( 'orderBy' => 'date', 'vendor' => $vendor->ID ) ) );
} finally {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->delete( $profiles, array( 'user_id' => $vendor->ID ) );
	ServiceFactory::cleanup();
	UserFactory::cleanup();
	wp_cache_flush();
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
