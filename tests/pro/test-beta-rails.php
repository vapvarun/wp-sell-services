<?php
/**
 * Beta rails contract.
 *
 * The Easy Digital Downloads and FluentCart rails are opt-in until 1.8.0
 * ships them purchase-tested (Basecamp 10264297724, 10264298055). With the
 * wpss_pro_beta_rails filter off they are neither registered nor offered on
 * the platform select; with it on, both come back. A site that opts in must
 * not record zero money: the FluentCart provider reads FluentCart's own
 * unit_price / subtotal / line_total columns (minor units), and a paid
 * FluentCart order marks the tip, milestone or extension it was minted for.
 *
 * Opting in must not remove the warning. With the filter on, the platform
 * select labels both rails "Beta, not purchase-tested" rather than
 * "Available", the admin notice names what the active rail cannot do
 * (including that an EDD refund is not reflected in the marketplace and the
 * vendor stays credited), and Free refuses the add-to-cart instead of writing
 * a cart row it cannot check out.
 *
 * Runs without EDD or FluentCart active: the plugin presence checks are
 * satisfied with test-only shims, and the money mapping is fed a stub order.
 *
 * Run: wp eval-file tests/pro/test-beta-rails.php
 *
 * @package WPSellServices
 */

use WPSellServices\Admin\Settings;
use WPSellServicesPro\Integrations\FluentCart\FluentCartOrderProvider;

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

echo "\nBeta rails contract\n\n";

// Presence shims so the registration branches run on a sandbox where neither
// plugin is active. Nothing below touches EDD or FluentCart code paths.
if ( ! class_exists( 'Easy_Digital_Downloads' ) ) {
	class Easy_Digital_Downloads {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound, Generic.Classes.DuplicateClassName.Found
}
if ( ! defined( 'FLUENTCART_VERSION' ) ) {
	define( 'FLUENTCART_VERSION', 'test' );
}

$settings = new Settings();
$field    = array(
	'option_name' => 'wpss_general',
	'field'       => 'ecommerce_platform',
);

/**
 * Render the platform select and return its HTML.
 *
 * @param Settings $settings Free settings screen.
 * @param array    $field    Field args.
 * @return string
 */
function wpss_render_platform_select( Settings $settings, array $field ): string {
	ob_start();
	$settings->render_ecommerce_platform_field( $field );
	return (string) ob_get_clean();
}

/**
 * Render the beta rail admin notice and return its HTML.
 *
 * @return string
 */
function wpss_render_beta_notice(): string {
	ob_start();
	\WPSellServicesPro\wpss_pro_beta_rail_notice();
	return (string) ob_get_clean();
}

/**
 * Save the configured store platform and re-detect the active adapter.
 *
 * @param string $platform Platform id.
 * @return void
 */
function wpss_set_platform( string $platform ): void {
	$general                       = (array) get_option( 'wpss_general', array() );
	$general['ecommerce_platform'] = $platform;
	update_option( 'wpss_general', $general );

	$manager = wpss()->get_integration_manager();
	if ( $manager ) {
		$manager->init();
	}
}

// The owner sees the notice, so the assertions have to run as one.
$wpss_original_general = get_option( 'wpss_general', array() );
$wpss_original_user    = get_current_user_id();
wp_set_current_user( 1 );

// 1. Flag off (default): no beta adapters, no beta options, a note saying why.
wpss_t( function_exists( 'WPSellServicesPro\\wpss_pro_beta_rails_enabled' ) && ! \WPSellServicesPro\wpss_pro_beta_rails_enabled(), 'beta rails are off by default' );

$adapters = apply_filters( 'wpss_ecommerce_adapters', array() );
wpss_t( ! isset( $adapters['edd'] ) && ! isset( $adapters['fluentcart'] ), 'flag off: wpss_ecommerce_adapters has no edd or fluentcart adapter' );

$html = wpss_render_platform_select( $settings, $field );
wpss_t( false === strpos( $html, 'value="edd"' ) && false === strpos( $html, 'value="fluentcart"' ), 'flag off: platform select offers neither edd nor fluentcart' );
// The note says why the two rails are missing, in the owner's words. It
// stopped naming the developer filter on purpose (Basecamp 10337154229); the
// old assertion still asked for the filter name.
wpss_t( false !== strpos( $html, 'EDD and FluentCart are not offered yet' ) && false === strpos( $html, 'wpss_pro_beta_rails' ), 'flag off: platform select says why the rails are missing, without naming a filter' );

wpss_set_platform( 'fluentcart' );
$notice = wpss_render_beta_notice();
wpss_t( false !== strpos( $notice, 'is in beta and is not loaded' ), 'flag off: the notice still says the configured beta rail is not loaded' );

// 2. Flag on: both adapters register, both options are offered, and every
// piece of the warning survives opting in.
add_filter( 'wpss_pro_beta_rails', '__return_true' );

$adapters = apply_filters( 'wpss_ecommerce_adapters', array() );
wpss_t( isset( $adapters['edd'] ) && isset( $adapters['fluentcart'] ), 'flag on: wpss_ecommerce_adapters has edd and fluentcart' );

$manager = wpss()->get_integration_manager();
if ( $manager && isset( $adapters['edd'], $adapters['fluentcart'] ) ) {
	$manager->register_adapter( 'edd', $adapters['edd'] );
	$manager->register_adapter( 'fluentcart', $adapters['fluentcart'] );
	$html = wpss_render_platform_select( $settings, $field );
	wpss_t( false !== strpos( $html, 'value="edd"' ) && false !== strpos( $html, 'value="fluentcart"' ), 'flag on: platform select offers edd and fluentcart' );
	wpss_t(
		false !== strpos( $html, 'Easy Digital Downloads (Beta, not purchase-tested)' )
		&& false !== strpos( $html, 'Fluent Cart (Beta, not purchase-tested)' ),
		'flag on: the select labels both beta rails Beta, not purchase-tested'
	);
	wpss_t(
		false === strpos( $html, 'Easy Digital Downloads (Available)' )
		&& false === strpos( $html, 'Fluent Cart (Available)' ),
		'flag on: neither beta rail is labelled Available'
	);
	wpss_t( false !== strpos( $html, 'neither can take a catalog checkout yet' ), 'flag on: the platform description still warns' );
} else {
	wpss_t( false, 'flag on: platform select offers edd and fluentcart' );
	wpss_t( false, 'flag on: the select labels both beta rails Beta, not purchase-tested' );
	wpss_t( false, 'flag on: neither beta rail is labelled Available' );
	wpss_t( false, 'flag on: the platform description still warns' );
}

// 2b. With a beta rail actually taking checkout, the notice names the limits.
$expected_limits = array(
	'fluentcart' => array(
		'Buyers cannot purchase a catalog service on this rail',
		'the Checkout button dead-ends',
		'Tips, milestone phases and paid extensions do work',
		'planned for 1.8.0',
	),
	'edd'        => array(
		'Services are not created as Easy Digital Downloads downloads',
		'A refund issued in Easy Digital Downloads is not reflected in the marketplace',
		'the vendor stays credited',
		'Pay links for milestone phases, tips and paid extensions are unavailable',
		'planned for 1.8.0',
	),
);

foreach ( $expected_limits as $rail => $phrases ) {
	wpss_set_platform( $rail );
	$active = wpss_get_active_adapter();
	wpss_t( $active && $rail === $active->get_id(), "flag on: {$rail} is the active rail" );

	$notice = wpss_render_beta_notice();
	foreach ( $phrases as $phrase ) {
		wpss_t( false !== strpos( $notice, $phrase ), "flag on: {$rail} notice says \"{$phrase}\"" );
	}
	wpss_t( false === strpos( $notice, 'is-dismissible' ), "flag on: {$rail} notice is not dismissible" );
}

remove_filter( 'wpss_pro_beta_rails', '__return_true' );

// 3. FluentCart line money: unit_price / subtotal / line_total, minor units.
$product_id = wp_insert_post(
	array(
		'post_type'   => 'post',
		'post_status' => 'draft',
		'post_title'  => 'Beta rails contract carrier',
	)
);
update_post_meta( $product_id, '_fc_product_type', 'service' );
update_post_meta( $product_id, '_wpss_service_id', 1 );
update_post_meta( $product_id, '_wpss_vendor_id', 1 );

$fc_order = (object) array(
	'currency' => 'USD',
	'items'    => array(
		(object) array(
			'id'         => 1,
			'product_id' => $product_id,
			'quantity'   => 1,
			'unit_price' => 12500,
			'subtotal'   => 12500,
			'line_total' => 11000,
			'meta'       => array(),
		),
	),
);

$items = array();
try {
	$items = ( new FluentCartOrderProvider() )->extract_service_items( $fc_order );
} catch ( \Throwable $e ) {
	echo '  note  ' . $e->getMessage() . "\n";
}

wpss_t( 1 === count( $items ), 'FluentCart: one service item is mapped from the stub order' );
wpss_t( isset( $items[0]['price'] ) && 125.0 === (float) $items[0]['price'], 'FluentCart: price reads unit_price as major units (125.00)' );
wpss_t( isset( $items[0]['subtotal'] ) && 125.0 === (float) $items[0]['subtotal'], 'FluentCart: subtotal reads subtotal as major units (125.00)' );
wpss_t( isset( $items[0]['total'] ) && 110.0 === (float) $items[0]['total'], 'FluentCart: total reads line_total as major units (110.00)' );

wp_delete_post( $product_id, true );

// 4. A FluentCart order minted for a sub-order is found through the meta key.
global $wpdb;
$table   = $wpdb->prefix . 'wpss_orders';
$fc_id   = 987654321;
$row_ids = array();

foreach ( array( $fc_id, (int) ( $fc_id . '0' ) ) as $meta_id ) {
	$wpdb->insert(
		$table,
		array(
			'order_number'      => 'BETA-RAILS-' . $meta_id,
			'customer_id'       => 1,
			'vendor_id'         => 1,
			'service_id'        => 1,
			'subtotal'          => 10,
			'total'             => 10,
			'status'            => 'pending_payment',
			'platform'          => 'tip',
			'platform_order_id' => 0,
			'meta'              => wp_json_encode( array( 'fc_pay_order_id' => $meta_id ) ),
		)
	);
	$row_ids[] = (int) $wpdb->insert_id;
}

$found = array();
try {
	$found = ( new FluentCartOrderProvider() )->find_orders_for_fc_order( $fc_id );
} catch ( \Throwable $e ) {
	echo '  note  ' . $e->getMessage() . "\n";
}

wpss_t( 1 === count( $found ) && (int) $found[0]->id === $row_ids[0], 'FluentCart paid: a tip minted for this FluentCart order is matched through fc_pay_order_id' );
wpss_t( ! in_array( $row_ids[1], array_map( static fn( $r ) => (int) $r->id, $found ), true ), 'FluentCart paid: a longer id sharing the prefix is not matched' );

foreach ( $row_ids as $row_id ) {
	$wpdb->delete( $table, array( 'id' => $row_id ) );
}

// 5. Free refuses honestly when the active rail cannot build a checkout URL.
// FluentCart is the rail with no catalog checkout, so it is the one that
// proves the seam: the control must not report success, and must not leave a
// cart row behind that the buyer can never check out.
add_filter( 'wpss_pro_beta_rails', '__return_true' );
$adapters = apply_filters( 'wpss_ecommerce_adapters', array() );
foreach ( array( 'edd', 'fluentcart' ) as $beta_id ) {
	if ( $manager && isset( $adapters[ $beta_id ] ) ) {
		$manager->register_adapter( $beta_id, $adapters[ $beta_id ] );
	}
}
wpss_set_platform( 'fluentcart' );

$vendor_id  = wp_insert_user(
	array(
		'user_login' => 'wpss_beta_rails_vendor',
		'user_pass'  => wp_generate_password(),
		'user_email' => 'wpss-beta-rails-vendor@example.test',
		'role'       => 'subscriber',
	)
);
$vendor_id  = is_wp_error( $vendor_id ) ? (int) get_user_by( 'login', 'wpss_beta_rails_vendor' )->ID : (int) $vendor_id;
$service_id = wp_insert_post(
	array(
		'post_type'   => 'wpss_service',
		'post_status' => 'publish',
		'post_title'  => 'Beta rails refusal carrier',
		'post_author' => $vendor_id,
	)
);
update_post_meta( $service_id, '_wpss_packages', array( array( 'name' => 'Standard', 'price' => 50, 'delivery_time' => 3 ) ) );

// The carrier must be live: a service that is not is refused as unavailable
// before the rail is asked. Site rules (moderation, a plan limit) can hold a
// new service back as a draft, so the status is written directly.
global $wpdb;
$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => (int) $service_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
clean_post_cache( (int) $service_id );

wpss_t( '' === wpss_get_service_checkout_url( $service_id ), 'Fluent Cart builds no checkout URL for a catalog service' );

wp_set_current_user( 1 );
delete_user_meta( 1, '_wpss_cart' );

// Only ever one adapter inits per request in production. This process booted
// on WooCommerce and then switched rails, so WooCommerce's cart handler is
// still attached and would answer for FluentCart. Drop it, or the assertion
// below would pass on WooCommerce's cart rather than on the refusal.
remove_all_filters( 'wpss_add_service_to_cart' );

if ( ! defined( 'DOING_AJAX' ) ) {
	define( 'DOING_AJAX', true );
}
add_filter(
	'wp_die_ajax_handler',
	static function () {
		return static function () {
			throw new \RuntimeException( 'wpss_json_sent' );
		};
	}
);

$_POST    = array(
	'service_id'    => $service_id,
	'package_index' => '0',
	'quantity'      => 1,
	'nonce'         => wp_create_nonce( 'wpss_service_nonce' ),
);
$_REQUEST = $_POST;

ob_start();
try {
	( new \WPSellServices\Frontend\AjaxHandlers() )->add_service_to_cart();
} catch ( \Throwable $e ) {
	// wp_send_json_* always exits; the shim above turns that into a throw.
	unset( $e );
}
$payload = json_decode( (string) ob_get_clean(), true );

wpss_t( is_array( $payload ) && false === ( $payload['success'] ?? null ), 'add to cart refuses on a rail that cannot check out' );
wpss_t(
	is_array( $payload ) && false !== strpos( (string) ( $payload['data']['message'] ?? '' ), 'nothing was added to your cart' ),
	'the refusal says nothing was added to the cart'
);
wpss_t( empty( get_user_meta( 1, '_wpss_cart', true ) ), 'no cart row is written when the rail cannot check out' );

$_POST    = array();
$_REQUEST = array();
wp_delete_post( $service_id, true );
wp_delete_user( $vendor_id );
delete_user_meta( 1, '_wpss_cart' );
remove_filter( 'wpss_pro_beta_rails', '__return_true' );

// Leave the site exactly as it was found: the gate off, the saved platform
// restored, and the adapter re-detected from it.
update_option( 'wpss_general', $wpss_original_general );
if ( $manager ) {
	$manager->init();
}
wp_set_current_user( $wpss_original_user );

echo "\n  {$GLOBALS['wpss_pass']} passed, {$GLOBALS['wpss_fail']} failed\n\n";
