<?php
/**
 * Dashboard list chips add up (Basecamp 10268055975; 2.0.0 batch 4).
 *
 * Every dashboard list (orders, sales, requests, proposals, services, disputes)
 * filters through one chip row and carries no stat tiles. A chip's number has to
 * be the rows its filter lists, and the chips other than All have to add up to
 * All - otherwise a member reads one number and sees another.
 *
 * The stat tiles this test once compared against are gone; it failed on a
 * vendor whose Active card counted two statuses while the Active chip selected
 * nine. Orders and sales are seeded with an order in every active status plus a
 * completed and a cancelled one, and rows are counted per chip. The other lists
 * render for whichever local member has the most rows, sum check only.
 * Every seeded row is deleted at the end.
 *
 * Run: wp eval-file tests/test-sales-active-count.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

use WPSellServices\Database\Repositories\VendorProfileRepository;
use WPSellServices\Frontend\UnifiedDashboard;
use WPSellServices\Models\VendorProfile;

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

global $wpdb;
$table  = $wpdb->prefix . 'wpss_orders';
$suffix = wp_rand( 1000, 9999 );

$buyer_id  = wp_insert_user(
	array(
		'user_login' => 'wpss_active_buyer_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$vendor_id = wp_insert_user(
	array(
		'user_login' => 'wpss_active_vendor_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'role'       => 'wpss_vendor',
	)
);

if ( is_wp_error( $buyer_id ) || is_wp_error( $vendor_id ) ) {
	echo "could not seed users; skipping\n";
	return;
}

// A vendor is an ACTIVE profile row, not a role.
( new VendorProfileRepository() )->upsert(
	$vendor_id,
	array(
		'display_name'      => 'Active-count vendor',
		'status'            => 'active',
		'verification_tier' => VendorProfile::TIER_NEW,
	)
);

$groups = wpss_get_order_status_groups();

// One order per active status - the card has to see all nine - plus a
// completed and a cancelled one so the other cards have something to be wrong
// about too.
$seed_statuses = array_merge(
	$groups['active']['statuses'],
	array( 'completed', 'cancelled' )
);

$order_ids = array();
foreach ( $seed_statuses as $index => $status ) {
	$wpdb->insert(
		$table,
		array(
			'order_number'   => sprintf( 'WPSS-AC-%s-%02d', $suffix, $index ),
			'customer_id'    => $buyer_id,
			'vendor_id'      => $vendor_id,
			'service_id'     => 0,
			'platform'       => 'standalone',
			'total'          => 50.000,
			'currency'       => 'USD',
			'status'         => $status,
			'payment_status' => 'paid',
			'created_at'     => current_time( 'mysql' ),
		)
	);
	$order_ids[] = (int) $wpdb->insert_id;
}

/**
 * Render the dashboard as a user with the given query args.
 *
 * @param int                   $user_id Viewer.
 * @param array<string, string> $get     Query args ($_GET).
 * @return string HTML.
 */
$render = static function ( int $user_id, array $get ): string {
	wp_set_current_user( $user_id );
	$_GET                   = $get;
	$_SERVER['REQUEST_URI'] = '/dashboard/?' . http_build_query( $get );

	return ( new UnifiedDashboard() )->render();
};

/**
 * Every chip in a rendered list: status key => count.
 *
 * @param string $html   Rendered HTML.
 * @param string $prefix Query-arg prefix of the list.
 * @return array<string, int>
 */
$chips = static function ( string $html, string $prefix ): array {
	preg_match_all( '/<a href="([^"]*)"\s+class="wpss-order-filter[^"]*"[^>]*>.*?<span class="wpss-order-filter__count">([\d,]+)</s', $html, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $chip ) {
		$query = array();
		wp_parse_str( (string) wp_parse_url( html_entity_decode( $chip[1] ), PHP_URL_QUERY ), $query );
		$out[ $query[ $prefix . '_status' ] ?? 'all' ] = (int) str_replace( ',', '', $chip[2] );
	}

	return $out;
};

/**
 * Number of order rows in a rendered list.
 *
 * @param string $html Rendered HTML.
 * @return int
 */
$row_count = static function ( string $html ): int {
	// One actions block per order row. Counting status pills missed late
	// orders, which show a "Late" badge in place of the pill.
	return preg_match_all( '/class="wpss-order-card__actions"/', $html );
};

$sum_check = static function ( string $label, array $found ) use ( $check ): void {
	$all = $found['all'] ?? null;
	unset( $found['all'] );
	$check( sprintf( '%s: chips (%d) add up to All (%s)', $label, array_sum( $found ), null === $all ? 'no All chip' : $all ), null !== $all && array_sum( $found ) === $all );
};

$screens = array(
	'sales'  => array(
		'user'   => $vendor_id,
		'prefix' => 'sales',
	),
	'orders' => array(
		'user'   => $buyer_id,
		'prefix' => 'orders',
	),
);

foreach ( $screens as $section => $screen ) {
	$html  = $render( $screen['user'], array( 'section' => $section ) );
	$found = $chips( $html, $screen['prefix'] );

	$check( "{$section}: no stat tiles", false === strpos( $html, 'wpss-stat-card' ) );
	$sum_check( $section, $found );

	foreach ( $found as $group_key => $count ) {
		$filtered = 'all' === $group_key
			? $html
			: $render( $screen['user'], array( 'section' => $section, $screen['prefix'] . '_status' => $group_key ) );
		$rows     = $row_count( $filtered );

		$check( sprintf( '%s: "%s" chip (%d) matches the rows it lists (%d)', $section, $group_key, $count, $rows ), $count === $rows );
	}
}

// Lists whose statuses are their own: sum check on the member with the most rows.
$busiest = static function ( string $sql ) use ( $wpdb ): int {
	return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- fixed SQL, no input.
};
$own_lists = array(
	'requests'  => $busiest( "SELECT post_author FROM {$wpdb->posts} WHERE post_type = 'wpss_request' AND post_status IN ('publish','draft','pending') GROUP BY post_author ORDER BY COUNT(*) DESC LIMIT 1" ),
	'proposals' => $busiest( "SELECT vendor_id FROM {$wpdb->prefix}wpss_proposals GROUP BY vendor_id ORDER BY COUNT(*) DESC LIMIT 1" ),
	'services'  => $busiest( "SELECT post_author FROM {$wpdb->posts} WHERE post_type = 'wpss_service' AND post_status IN ('publish','draft','pending') GROUP BY post_author ORDER BY COUNT(*) DESC LIMIT 1" ),
	'disputes'  => $busiest( "SELECT o.customer_id FROM {$wpdb->prefix}wpss_disputes d JOIN {$table} o ON o.id = d.order_id GROUP BY o.customer_id ORDER BY COUNT(*) DESC LIMIT 1" ),
);
foreach ( $own_lists as $section => $member ) {
	if ( ! $member ) {
		echo "SKIP  {$section}: no rows on this site\n";
		continue;
	}
	$html = $render( $member, array( 'section' => $section ) );
	$check( "{$section}: no stat tiles", false === strpos( $html, 'wpss-stat-card' ) );
	$sum_check( $section, $chips( $html, $section ) );
}

// --- Cleanup ---------------------------------------------------------------
wp_set_current_user( 0 );
$_GET = array();
foreach ( $order_ids as $oid ) {
	$wpdb->delete( $table, array( 'id' => $oid ) );
}
$wpdb->delete( $wpdb->prefix . 'wpss_vendor_profiles', array( 'user_id' => $vendor_id ) );
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( $buyer_id );
wp_delete_user( $vendor_id );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
