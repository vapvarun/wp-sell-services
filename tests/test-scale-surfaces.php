<?php
/**
 * Every member list is bounded, admin counts are cached, and the indexes exist.
 *
 * F16 (Basecamp 10264289070): the dashboard, favourites and vendor directory
 * loaded every row; pending-service and dashboard counts loaded ids to count
 * them; review moderation and the vendor directory filtered on unindexed
 * columns. This seeds a throwaway vendor with 60 services and a buyer with 25
 * favourites, then checks each surface returns one page, the counts are one
 * query, and SHOW INDEX lists the four 1.7.1 keys.
 *
 * Run: wp eval-file tests/test-scale-surfaces.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

require_once __DIR__ . '/Factories/UserFactory.php';
require_once __DIR__ . '/Factories/ServiceFactory.php';

use WPSellServices\Tests\Factories\ServiceFactory;
use WPSellServices\Tests\Factories\UserFactory;

global $wpdb;

$failures = array();
$cleanup  = array(
	'users' => array(),
	'posts' => array(),
);

$_SERVER['REQUEST_URI'] = '/dashboard/?section=services';

// The throwaway vendor needs more than the default 20-service cap.
$wpss_unlimited = static fn ( $options ) => array_merge( (array) $options, array( 'max_services_per_vendor' => 0 ) );
add_filter( 'option_wpss_vendor', $wpss_unlimited );

$suffix = wp_rand( 1000, 9999 );
$vendor = UserFactory::vendor(
	array(
		'user_login' => "wpss_scale_vendor_{$suffix}",
		'user_email' => "wpss_scale_vendor_{$suffix}@example.test",
	)
);
$buyer  = UserFactory::customer(
	array(
		'user_login' => "wpss_scale_buyer_{$suffix}",
		'user_email' => "wpss_scale_buyer_{$suffix}@example.test",
	)
);
$cleanup['users'][] = $vendor->ID;
$cleanup['users'][] = $buyer->ID;

for ( $i = 0; $i < 60; $i++ ) {
	$service = ServiceFactory::simple( array( 'vendor_id' => $vendor->ID ) );
	if ( is_object( $service ) && ! empty( $service->id ) ) {
		$cleanup['posts'][] = (int) $service->id;
	}
}

if ( 60 !== count( $cleanup['posts'] ) ) {
	$failures[] = 'Seeded ' . count( $cleanup['posts'] ) . ' services - expected 60.';
}

// 1. Dashboard services section: one page of 20 plus a paginator.
wp_set_current_user( $vendor->ID );
$user_id        = $vendor->ID;
$vendor_service = new \WPSellServices\Services\VendorService();
$is_vendor      = true;

ob_start();
include WPSS_PLUGIN_DIR . 'templates/dashboard/sections/services.php';
$html = (string) ob_get_clean();

$cards = substr_count( $html, 'class="wpss-service-card wpss-service-card--dashboard' );
if ( 20 !== $cards ) {
	$failures[] = "Services section rendered {$cards} cards - expected 20 (one page).";
}
if ( false === strpos( $html, 'class="wpss-pagination"' ) ) {
	$failures[] = 'Services section has no pagination element.';
}
if ( false === strpos( $html, '<span class="wpss-stat-card__value">60</span>' ) ) {
	$failures[] = 'Services section Active stat is not 60.';
}

// 2. Favourites REST: X-WP-Total describes the set, the body is one page.
$favourite_ids = array_slice( $cleanup['posts'], 0, 25 );
update_user_meta( $buyer->ID, \WPSellServices\Services\FavoritesService::META_KEY, $favourite_ids );
wp_set_current_user( $buyer->ID );

$request = new WP_REST_Request( 'GET', '/wpss/v1/favorites' );
$request->set_param( 'per_page', 20 );
$response = rest_get_server()->dispatch( $request );
$headers  = $response->get_headers();
$body     = (array) $response->get_data();

if ( 25 !== (int) ( $headers['X-WP-Total'] ?? 0 ) ) {
	$failures[] = 'Favorites REST X-WP-Total is ' . ( $headers['X-WP-Total'] ?? 'missing' ) . ' - expected 25.';
}
if ( 20 !== count( $body ) ) {
	$failures[] = 'Favorites REST body has ' . count( $body ) . ' items - expected 20 (per_page).';
}

// Favourites dashboard section renders one page too.
$user_id = $buyer->ID;
ob_start();
include WPSS_PLUGIN_DIR . 'templates/dashboard/sections/favorites.php';
$html = (string) ob_get_clean();
if ( 20 !== substr_count( $html, 'class="wpss-favorites__card"' ) || false === strpos( $html, 'class="wpss-pagination"' ) ) {
	$failures[] = 'Favorites section did not render 20 cards with a paginator.';
}

// 3. Vendor directory shortcode: page 2 is a different page.
wp_set_current_user( 0 );
$directory_total = ( new \WPSellServices\Database\Repositories\VendorProfileRepository() )->count( array( 'status' => 'active' ) );
for ( $i = $directory_total; $i < 13; $i++ ) {
	$extra_vendor       = UserFactory::vendor(
		array(
			'user_login' => "wpss_scale_vendor_{$suffix}_{$i}",
			'user_email' => "wpss_scale_vendor_{$suffix}_{$i}@example.test",
		)
	);
	$cleanup['users'][] = $extra_vendor->ID;
}

$page_one = do_shortcode( '[wpss_vendors per_page="12" paged="1"]' );
$page_two = do_shortcode( '[wpss_vendors per_page="12" paged="2"]' );

if ( $page_one === $page_two ) {
	$failures[] = 'Vendor directory page 2 is identical to page 1.';
}
if ( 12 !== substr_count( $page_one, 'class="wpss-vendor-card' ) ) {
	$failures[] = 'Vendor directory page 1 rendered ' . substr_count( $page_one, 'class="wpss-vendor-card' ) . ' cards - expected 12.';
}
if ( false === strpos( $page_one, 'class="wpss-pagination"' ) ) {
	$failures[] = 'Vendor directory has no pagination element.';
}
if ( false === strpos( $page_one, 'wpss-url-select' ) ) {
	$failures[] = 'Vendor directory has no sort/filter selects.';
}

// 4. The four 1.7.1 keys exist after the migration.
( new \WPSellServices\Database\SchemaManager() )->sync();
$expected_keys = array(
	array( 'wpss_reviews', 'status_created' ),
	array( 'wpss_vendor_profiles', 'availability' ),
	array( 'wpss_vendor_profiles', 'country' ),
	array( 'wpss_orders', 'customer_status_created' ),
);
foreach ( $expected_keys as list( $table, $key ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$found = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$wpdb->prefix}{$table} WHERE Key_name = %s", $key ) );
	if ( null === $found ) {
		$failures[] = "{$table} has no KEY {$key}.";
	}
}

// 5. Pending count is a COUNT and drops its cache on a moderation event.
if ( ! function_exists( 'wpss_count_pending_services' ) ) {
	$failures[] = 'wpss_count_pending_services() does not exist.';
} else {
	$before  = wpss_count_pending_services();
	$pending = ServiceFactory::pending( array( 'vendor_id' => $vendor->ID ) );
	if ( is_object( $pending ) && ! empty( $pending->id ) ) {
		$cleanup['posts'][] = (int) $pending->id;
		update_post_meta( (int) $pending->id, '_wpss_moderation_status', 'pending' );
		do_action( 'wpss_service_pending_moderation', (int) $pending->id );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$direct = (int) $wpdb->get_var(
		"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
		INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_wpss_moderation_status'
		WHERE p.post_type = 'wpss_service' AND p.post_status IN ('pending', 'publish') AND pm.meta_value = 'pending'"
	);
	$after  = wpss_count_pending_services();

	if ( $after !== $direct ) {
		$failures[] = "wpss_count_pending_services() is {$after} - direct COUNT is {$direct}.";
	}
	if ( $after !== $before + 1 ) {
		$failures[] = "Pending count did not move from {$before} to " . ( $before + 1 ) . " after a moderation event (got {$after}).";
	}
	if ( ( new \WPSellServices\Services\ModerationService() )->get_pending_count() !== $after ) {
		$failures[] = 'ModerationService::get_pending_count() disagrees with wpss_count_pending_services().';
	}
}

// 6. Admin dashboard aggregates: one query, then none.
if ( ! function_exists( 'wpss_get_order_aggregates' ) ) {
	$failures[] = 'wpss_get_order_aggregates() does not exist.';
} else {
	$orders_queries = static function ( int $from ) use ( $wpdb ): int {
		$hits = 0;
		foreach ( array_slice( (array) $wpdb->queries, $from ) as $entry ) {
			if ( false !== stripos( (string) $entry[0], $wpdb->prefix . 'wpss_orders' ) ) {
				++$hits;
			}
		}
		return $hits;
	};

	wpss_flush_order_aggregates();
	$mark  = count( (array) $wpdb->queries );
	$first = wpss_get_order_aggregates();
	$hits1 = $orders_queries( $mark );

	$mark   = count( (array) $wpdb->queries );
	$second = wpss_get_order_aggregates();
	$total2 = count( (array) $wpdb->queries ) - $mark;

	if ( 1 !== $hits1 ) {
		$failures[] = "First aggregates call hit wpss_orders {$hits1} times - expected 1.";
	}
	if ( 0 !== $total2 ) {
		$failures[] = "Second aggregates call ran {$total2} queries - expected 0 (cached).";
	}
	if ( $first != $second ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- object value comparison.
		$failures[] = 'Cached aggregates differ from the fresh ones.';
	}
	// A paid order must drop the cache.
	do_action( 'wpss_order_paid', 0, '' );
	if ( false !== get_transient( 'wpss_order_aggregates' ) ) {
		$failures[] = 'wpss_order_paid did not flush the aggregates cache.';
	}
}

// Cleanup.
remove_filter( 'option_wpss_vendor', $wpss_unlimited );
wp_set_current_user( 0 );
foreach ( $cleanup['posts'] as $post_id ) {
	wp_delete_post( $post_id, true );
}
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $cleanup['users'] as $uid ) {
	wp_delete_user( $uid );
}
delete_transient( 'wpss_pending_services_count' );
delete_transient( 'wpss_order_aggregates' );

if ( $failures ) {
	echo "FAIL\n";
	foreach ( $failures as $failure ) {
		echo " - {$failure}\n";
	}
	exit( 1 );
}

echo "PASS: scale surfaces are bounded, counts are cached and the 1.7.1 indexes exist.\n";
