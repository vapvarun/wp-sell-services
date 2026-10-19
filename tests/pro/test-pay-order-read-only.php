<?php
/**
 * Pay-order read-only contract.
 *
 * Viewing or listing orders on the WooCommerce rail must never create a
 * WooCommerce order. Until 1.7.1 the one pay-order filter both looked up AND
 * created the WC order, so GET /wpss/v1/orders minted a pending WC order for
 * every unpaid row the buyer merely listed (Basecamp 10264551109). The seam
 * is now two halves: wpss_get_pay_order_url() reads, wpss_ensure_pay_order()
 * creates on click.
 *
 * Flips the rail to WooCommerce for the run (the raw option is saved and
 * restored byte-exact; its md5 is printed before and after) and relaunches
 * itself so the Woo adapter boots. Seeds throwaway orders and removes them.
 *
 * Run: wp eval-file tests/pro/test-pay-order-read-only.php --skip-themes
 *
 * @package WPSellServices
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- throwaway rows on a test site.

if ( ! defined( 'WPSS_PRO_VERSION' ) ) {
	echo "SKIP  Pro is not active\n";
	return;
}

require_once dirname( __DIR__ ) . '/exit-on-fail.php';

$GLOBALS['wpss_pass'] = 0;
$GLOBALS['wpss_fail'] = 0;

/**
 * Assert one condition.
 *
 * @param bool   $cond Condition.
 * @param string $msg  Message.
 * @return void
 */
function wpss_t( $cond, $msg ) {
	if ( $cond ) {
		++$GLOBALS['wpss_pass'];
		echo "  PASS  {$msg}\n";
	} else {
		++$GLOBALS['wpss_fail'];
		echo "  FAIL  {$msg}\n";
	}
}

if ( ! function_exists( 'wc_create_order' ) ) {
	echo "  SKIP  WooCommerce is not active.\n";
	return;
}

global $wpdb;

$rail = (string) ( get_option( 'wpss_general', array() )['ecommerce_platform'] ?? '' );

// ---------------------------------------------------------------------------
// Parent process: flip the rail, relaunch, restore.
// ---------------------------------------------------------------------------
if ( 'woocommerce' !== $rail ) {
	$raw = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'wpss_general' ) );
	echo 'wpss_general md5 before: ' . md5( $raw ) . "\n";

	$general                       = get_option( 'wpss_general', array() );
	$general['ecommerce_platform'] = 'woocommerce';
	update_option( 'wpss_general', $general );

	try {
		WP_CLI::runcommand(
			'eval-file ' . escapeshellarg( __FILE__ ),
			array(
				'launch'     => true,
				'exit_error' => false,
			)
		);
	} finally {
		$wpdb->update( $wpdb->options, array( 'option_value' => $raw ), array( 'option_name' => 'wpss_general' ) );
		wp_cache_delete( 'wpss_general', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$after = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'wpss_general' ) );
		echo 'wpss_general md5 after:  ' . md5( $after ) . ( $after === $raw ? ' (restored byte-exact)' : ' (MISMATCH)' ) . "\n";
	}

	return;
}

// ---------------------------------------------------------------------------
// Child process: WooCommerce rail active.
// ---------------------------------------------------------------------------
echo "\nPay-order read-only contract (WooCommerce rail)\n\n";

if ( ! defined( 'SAVEQUERIES' ) ) {
	define( 'SAVEQUERIES', true );
}

$table = $wpdb->prefix . 'wpss_orders';

/**
 * Every WC order id on the site.
 *
 * @return int[]
 */
function wpss_wc_ids(): array {
	return array_map( 'intval', wc_get_orders( array( 'limit' => -1, 'return' => 'ids', 'status' => 'any' ) ) );
}

/**
 * Seed one unpaid WPSS order row.
 *
 * @param string $platform  Row platform.
 * @param int    $parent_id Parent order id for sub-orders.
 * @param int    $buyer     Customer id.
 * @param array  $meta      Meta JSON.
 * @param string $status    Row status.
 * @return int
 */
function wpss_seed_row( string $platform, int $parent_id, int $buyer, array $meta, string $status = 'pending_payment' ): int {
	global $wpdb;
	$wpdb->insert(
		$wpdb->prefix . 'wpss_orders',
		array(
			'order_number'      => 'P10-' . strtoupper( wp_generate_password( 8, false, false ) ),
			'customer_id'       => $buyer,
			'vendor_id'         => 1,
			'service_id'        => 0,
			'platform'          => $platform,
			'platform_order_id' => $parent_id,
			'subtotal'          => 25,
			'total'             => 25,
			'currency'          => 'USD',
			'status'            => $status,
			'payment_status'    => 'pending',
			'meta'              => wp_json_encode( $meta ),
			'created_at'        => current_time( 'mysql' ),
		)
	);
	return (int) $wpdb->insert_id;
}

$buyer = wp_insert_user(
	array(
		'user_login' => 'p10-buyer-' . wp_generate_password( 6, false, false ),
		'user_pass'  => wp_generate_password( 20 ),
		'user_email' => 'p10-' . wp_generate_password( 6, false, false ) . '@example.test',
		'role'       => 'subscriber',
	)
);
$buyer = (int) $buyer;

$order_a = wpss_seed_row( 'standalone', 0, $buyer, array( 'title' => 'P10 order A' ) );
$order_b = wpss_seed_row( 'standalone', 0, $buyer, array( 'title' => 'P10 order B' ) );
$parent  = wpss_seed_row( 'request', 0, $buyer, array( 'title' => 'P10 project' ), 'in_progress' );
$phase_1 = wpss_seed_row( 'milestone', $parent, $buyer, array( 'milestone' => true, 'parent_order_id' => $parent, 'title' => 'Phase 1', 'sort_order' => 1 ) );
$phase_2 = wpss_seed_row( 'milestone', $parent, $buyer, array( 'milestone' => true, 'parent_order_id' => $parent, 'title' => 'Phase 2', 'sort_order' => 2 ) );
$rows    = array( $order_a, $order_b, $parent, $phase_1, $phase_2 );

$wc_before = wpss_wc_ids();

try {
	wpss_t( false === has_filter( 'wpss_pay_order_url' ), 'no rail hooks the retired wpss_pay_order_url filter' );
	wpss_t( false !== has_filter( 'wpss_ensure_pay_order' ), 'the WooCommerce rail hooks wpss_ensure_pay_order' );
	wpss_t( false !== has_filter( 'wpss_pay_order_url_lookup' ), 'the WooCommerce rail hooks wpss_pay_order_url_lookup' );
	wpss_t( function_exists( 'wpss_can_pay_single_order' ) && wpss_can_pay_single_order(), 'wpss_can_pay_single_order() is true on the WooCommerce rail' );

	// 1. Listing orders over REST as the buyer is a pure read.
	wp_set_current_user( $buyer );
	$wpdb->queries = array();

	$request = new WP_REST_Request( 'GET', '/wpss/v1/orders' );
	$request->set_param( 'per_page', 100 );
	$response = rest_do_request( $request );
	$body     = wp_json_encode( $response->get_data() );

	$writes = array();
	foreach ( $wpdb->queries as $q ) {
		if ( preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE)\b/i', (string) $q[0] ) ) {
			$writes[] = $q[0];
		}
	}

	wpss_t( 200 === $response->get_status(), 'GET /wpss/v1/orders answers 200 for the buyer' );
	wpss_t( false !== strpos( $body, '"id":' . $order_a ) && false !== strpos( $body, '"id":' . $order_b ), 'the listing contains both seeded unpaid orders' );
	wpss_t( count( $wpdb->queries ) > 0, 'SAVEQUERIES captured the request (' . count( $wpdb->queries ) . ' queries)' );
	wpss_t( array() === $writes, 'listing orders ran zero write queries' );
	foreach ( $writes as $w ) {
		echo '        write: ' . substr( preg_replace( '/\s+/', ' ', $w ), 0, 160 ) . "\n";
	}
	wpss_t( wpss_wc_ids() === $wc_before, 'listing orders created no WooCommerce order (' . count( $wc_before ) . ' before and after)' );

	// 2. Clicking Pay creates exactly one WC order, and the read path then knows it.
	wpss_t( function_exists( 'wpss_ensure_pay_order' ), 'wpss_ensure_pay_order() exists' );
	$pay_url = function_exists( 'wpss_ensure_pay_order' ) ? wpss_ensure_pay_order( $order_a ) : '';
	$wc_now  = wpss_wc_ids();
	wpss_t( count( $wc_now ) === count( $wc_before ) + 1, 'wpss_ensure_pay_order() created one WooCommerce order' );
	wpss_t( false !== strpos( $pay_url, 'order-pay' ) && false !== strpos( $pay_url, 'key=wc_order_' ), 'ensure returns the WC order-pay URL with its key' );

	$lookup = wpss_get_pay_order_url( $order_a );
	wpss_t( $lookup === $pay_url, 'wpss_get_pay_order_url() now returns that same order-pay URL from row meta' );
	wpss_t( wpss_wc_ids() === $wc_now, 'the lookup created nothing' );

	if ( function_exists( 'wpss_ensure_pay_order' ) ) {
		wpss_ensure_pay_order( $order_a );
	}
	wpss_t( wpss_wc_ids() === $wc_now, 'a second ensure reuses the WC order' );

	$untouched = wpss_get_pay_order_url( $order_b );
	wpss_t( false !== strpos( $untouched, 'pay_order=' . $order_b ), 'an order never paid for still gets the Free landing URL (' . $untouched . ')' );

	// 3. Rendering the order page with a locked phase creates nothing.
	$wc_before_render = wpss_wc_ids();
	$html             = '';
	$render_error     = '';
	try {
		ob_start();
		$order_id = $parent; // The template reads $order_id.
		include WPSS_PLUGIN_DIR . 'templates/order/order-view.php';
		$html = (string) ob_get_clean();
	} catch ( \Throwable $e ) {
		ob_end_clean();
		$render_error = $e->getMessage();
	}
	wpss_t( '' === $render_error, 'order-view.php rendered for the buyer' . ( $render_error ? ' (' . $render_error . ')' : '' ) );
	wpss_t( false !== strpos( $html, 'wpss-ms-state--locked' ), 'phase 2 renders locked behind phase 1' );
	wpss_t( false !== strpos( $html, 'pay_order=' . $phase_1 ), 'phase 1 renders a Pay link to the Free landing URL' );
	wpss_t( wpss_wc_ids() === $wc_before_render, 'rendering the milestone section created no WooCommerce order' );

	// 4. The click: landing on the Free URL creates the WC order and forwards.
	$checkout_id = (int) wpss_get_page_id( 'checkout' );
	$wc_before_click = wpss_wc_ids();
	$landed          = '';
	if ( $checkout_id > 0 ) {
		$GLOBALS['wp_the_query']->query( array( 'page_id' => $checkout_id ) );
		$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
		$_GET['pay_order']   = (string) $order_b;
		$catch               = static function ( $location ) {
			throw new \RuntimeException( (string) $location );
		};
		add_filter( 'wp_redirect', $catch, 0 ); // Before WP-CLI's own handler, which only warns.
		try {
			wpss()->redirect_dormant_store_pages();
		} catch ( \RuntimeException $e ) {
			$landed = $e->getMessage();
		} finally {
			remove_filter( 'wp_redirect', $catch, 0 );
			unset( $_GET['pay_order'] );
		}
	}
	wpss_t( $checkout_id > 0, 'a WPSS checkout page is mapped for the landing' );
	wpss_t( false !== strpos( $landed, 'order-pay' ) && false !== strpos( $landed, 'key=wc_order_' ), 'landing on ?pay_order=N forwards to the WC order-pay URL (' . $landed . ')' );
	wpss_t( count( wpss_wc_ids() ) === count( $wc_before_click ) + 1, 'the click created exactly one WooCommerce order' );
	wpss_t( wpss_get_pay_order_url( $order_b ) === $landed, 'after the click the read path returns that order-pay URL' );
} finally {
	// Cleanup: every WC order minted during the run, the seeded rows, the buyer.
	foreach ( array_diff( wpss_wc_ids(), $wc_before ) as $wc_id ) {
		$wc_order = wc_get_order( $wc_id );
		if ( $wc_order ) {
			$wc_order->delete( true );
		}
	}
	foreach ( $rows as $row_id ) {
		$wpdb->delete( $table, array( 'id' => $row_id ), array( '%d' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $buyer );
	echo "\n  cleanup: removed " . count( $rows ) . " WPSS rows, " . count( array_diff( wpss_wc_ids(), $wc_before ) ) . " WC orders left behind, buyer {$buyer} deleted\n";
}

echo "\n{$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n";

if ( $GLOBALS['wpss_fail'] > 0 ) {
	WP_CLI::halt( 1 );
}
