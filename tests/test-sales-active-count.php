<?php
/**
 * Stat card / filter chip agreement contract (Basecamp 10268055975).
 *
 * The vendor Sales screen and the buyer Orders screen each pair a row of stat
 * cards with a row of filter chips. A card and the chip beside it carry the
 * same label, so they have to be the same number - and clicking the chip has
 * to list exactly that many rows.
 *
 * The seller's Active card counted two statuses (in_progress,
 * pending_approval) while the Active chip selected nine, so a vendor with a
 * delivered order read "1 Active" beside an Active chip showing 2.
 *
 * Seeds one throwaway vendor + buyer with an order in every one of the nine
 * active statuses plus a completed and a cancelled one, renders both screens,
 * and asserts card == chip == rendered row count for every card that has a
 * chip. Every seeded row is deleted at the end.
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
 * Value of the stat card carrying the given label.
 *
 * @param string $html  Rendered HTML.
 * @param string $label Card label, e.g. "Active".
 * @return int|null Card value, null when the card is not rendered.
 */
$card_value = static function ( string $html, string $label ): ?int {
	$pattern = '/wpss-stat-card__value">([\d,]+)<\/span>\s*<span class="wpss-stat-card__label">' . preg_quote( $label, '/' ) . '</';

	return preg_match( $pattern, $html, $m ) ? (int) str_replace( ',', '', $m[1] ) : null;
};

/**
 * Count shown on the filter chip carrying the given label.
 *
 * @param string $html  Rendered HTML.
 * @param string $label Chip label, e.g. "Active".
 * @return int|null Chip count, null when the chip is not rendered.
 */
$chip_count = static function ( string $html, string $label ): ?int {
	$pattern = '/wpss-order-filter[^"]*"[^>]*>\s*' . preg_quote( $label, '/' ) . '\s*<span class="wpss-order-filter__count">([\d,]+)</';

	return preg_match( $pattern, $html, $m ) ? (int) str_replace( ',', '', $m[1] ) : null;
};

/**
 * Number of order rows in a rendered list.
 *
 * @param string $html Rendered HTML.
 * @return int
 */
$row_count = static function ( string $html ): int {
	return preg_match_all( '/class="wpss-status wpss-status--([a-z_]+)"/', $html );
};

// Card label => chip label, for every card that has a filter beside it.
// Revenue is money, not a row count, so it has no chip and no assertion.
$pairs = array(
	'Total Orders'     => array( 'All', 'all' ),
	'Active'           => array( 'Active', 'active' ),
	'Completed'        => array( 'Completed', 'completed' ),
	'Awaiting Payment' => array( 'Awaiting payment', 'awaiting' ),
	'Disputed'         => array( 'Needs attention', 'disputed' ),
);

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
	$html = $render( $screen['user'], array( 'section' => $section ) );

	foreach ( $pairs as $card_label => $chip ) {
		list( $chip_label, $group_key ) = $chip;

		$card = $card_value( $html, $card_label );

		if ( null === $card ) {
			// The buyer's Awaiting Payment / Disputed cards only render when
			// they are non-zero, so an absent card is not a failure.
			continue;
		}

		$chip_n = $chip_count( $html, $chip_label );

		$check(
			sprintf( '%s: "%s" card (%d) matches the "%s" chip (%s)', $section, $card_label, $card, $chip_label, null === $chip_n ? 'not rendered' : $chip_n ),
			$card === $chip_n
		);

		$filtered = 'all' === $group_key
			? $html
			: $render( $screen['user'], array( 'section' => $section, $screen['prefix'] . '_status' => $group_key ) );
		$rows     = $row_count( $filtered );

		$check(
			sprintf( '%s: "%s" card (%d) matches the rows the "%s" filter renders (%d)', $section, $card_label, $card, $chip_label, $rows ),
			$card === $rows
		);
	}
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
