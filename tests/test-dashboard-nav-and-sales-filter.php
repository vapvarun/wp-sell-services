<?php
/**
 * Dashboard nav collapse + sales list filters contract (F27).
 *
 * At 390px every dashboard screen opened with the whole nav before the
 * content, and the seller's sales list offered only a period select while
 * the buyer's list had status chips. The shell now carries a Menu toggle
 * (aria-expanded / aria-controls) the CSS collapses under 480px, and both
 * lists render the same status + order-number filter through ONE partial,
 * backed by the same repository filters.
 *
 * Run: wp eval-file tests/test-dashboard-nav-and-sales-filter.php
 *
 * No strict_types: wp eval-file eval()s the file, and a declare must be the
 * first statement in a script.
 *
 * @package WPSellServices
 */

use WPSellServices\Database\Repositories\VendorProfileRepository;
use WPSellServices\Frontend\UnifiedDashboard;
use WPSellServices\Models\VendorProfile;

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
		'user_login' => 'wpss_f27_buyer_' . $suffix,
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$vendor_id = wp_insert_user(
	array(
		'user_login' => 'wpss_f27_vendor_' . $suffix,
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
		'display_name'      => 'F27 vendor',
		'status'            => 'active',
		'verification_tier' => VendorProfile::TIER_NEW,
	)
);

$seed      = array(
	'WPSS-F27-A-' . $suffix => 'completed',
	'WPSS-F27-B-' . $suffix => 'in_progress',
	'WPSS-F27-C-' . $suffix => 'completed',
	'WPSS-F27-D-' . $suffix => 'cancelled',
);
$order_ids = array();
foreach ( $seed as $number => $status ) {
	$wpdb->insert(
		$table,
		array(
			'order_number'   => $number,
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
 * Render the dashboard shell as a user with the given query args.
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
 * Statuses of the order rows in a rendered list, in order.
 *
 * @param string $html Rendered HTML.
 * @return string[]
 */
$row_statuses = static function ( string $html ): array {
	preg_match_all( '/class="wpss-status wpss-status--([a-z_]+)"/', $html, $m );

	return $m[1];
};

// --- 1. Shell: the Menu toggle with its ARIA wiring ------------------------
$html = $render( $vendor_id, array( 'section' => 'sales' ) );

preg_match( '/<button[^>]*wpss-dashboard__nav-toggle[^>]*>/', $html, $btn );
$btn = $btn[0] ?? '';
$check( 'shell renders the nav toggle button', '' !== $btn );
$check( 'toggle carries aria-expanded="false"', false !== strpos( $btn, 'aria-expanded="false"' ) );
$check( 'toggle carries aria-controls="wpss-dashboard-nav"', false !== strpos( $btn, 'aria-controls="wpss-dashboard-nav"' ) );
$check( 'nav carries the id the toggle controls', false !== strpos( $html, 'id="wpss-dashboard-nav"' ) );
$check( 'collapsed bar names the current section', false !== strpos( $html, 'wpss-dashboard__nav-bar-title">Sales Orders<' ) );

// --- 2. Sales list: shared filter partial + repository filters -------------
$check( 'sales list renders the status chips', false !== strpos( $html, 'class="wpss-order-filters"' ) );
$check( 'sales list renders the order-number search', false !== strpos( $html, 'name="sales_search"' ) );

$statuses = $row_statuses( $html );
sort( $statuses );
$check( 'unfiltered sales list shows all four seeded orders', array( 'cancelled', 'completed', 'completed', 'in_progress' ) === $statuses );

$html     = $render( $vendor_id, array( 'section' => 'sales', 'sales_status' => 'completed' ) );
$statuses = $row_statuses( $html );
$check( 'sales_status=completed renders only completed rows', array( 'completed', 'completed' ) === $statuses );

$html     = $render( $vendor_id, array( 'section' => 'sales', 'sales_search' => 'F27-B-' . $suffix ) );
$statuses = $row_statuses( $html );
$check( 'sales_search by order number returns the one matching row', array( 'in_progress' ) === $statuses );

$html = $render( $vendor_id, array( 'section' => 'sales', 'sales_search' => 'no-such-order-' . $suffix ) );
$check( 'a search with no match shows the filtered empty state', false !== strpos( $html, 'No sales match this filter' ) );

// --- 3. Buyer list still renders its filters through the shared partial ----
$html = $render( $buyer_id, array( 'section' => 'orders' ) );
$check( 'orders list renders the status chips', false !== strpos( $html, 'class="wpss-order-filters"' ) );
$check( 'orders list renders the order-number search', false !== strpos( $html, 'name="orders_search"' ) );

$html     = $render( $buyer_id, array( 'section' => 'orders', 'orders_search' => 'F27-D-' . $suffix ) );
$statuses = $row_statuses( $html );
$check( 'orders_search by order number returns the one matching row', array( 'cancelled' ) === $statuses );

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
