<?php
/**
 * The buyer never names the price of an add-on.
 *
 * Run: wp eval-file tests/test-addon-price-trust-contract.php
 *
 * wpss_resolve_checkout_addons() read a JSON `addons_data` field from $_POST and
 * charged each add-on at whatever `price` the request carried. The checkout form
 * filled it in, so it looked like server data, but every rail that resolved
 * without explicit ids (Offline, Test, Stripe AJAX, PayPal, Pro Razorpay) took
 * the browser's number. Posting [{"price":-70}] against an $80 package created a
 * real order for $11.80 (1.8.0 triage, Basecamp 10336645932).
 *
 * Add-ons are priced from `_wpss_addons` post meta by id, nowhere else.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// 1. The normaliser never stores a negative add-on price.
$norm = wpss_normalize_service_addons( array( array( 'title' => 'Refund me', 'price' => -70 ) ) );
$check( 'a negative add-on price is clamped to 0 on normalise', isset( $norm[0] ) && 0.0 === (float) $norm[0]['price'] );

// 2. Nothing in the plugin sends or reads addons_data any more.
$hits = array();
foreach ( array( 'src', 'assets/js', 'templates' ) as $dir ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPSS_PLUGIN_DIR . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( ! preg_match( '/\.(php|js)$/', $file->getFilename() ) ) {
			continue;
		}
		if ( false !== strpos( (string) file_get_contents( $file->getPathname() ), 'addons_data' ) ) {
			$hits[] = str_replace( WPSS_PLUGIN_DIR, '', $file->getPathname() );
		}
	}
}
$pro_js = dirname( WPSS_PLUGIN_DIR ) . '/wp-sell-services-pro/assets/js/razorpay.js';
if ( file_exists( $pro_js ) && false !== strpos( (string) file_get_contents( $pro_js ), 'addons_data' ) ) {
	$hits[] = 'pro:assets/js/razorpay.js';
}
$check( 'no file sends or reads addons_data' . ( $hits ? ' (' . implode( ', ', $hits ) . ')' : '' ), empty( $hits ) );

// 3. Runtime: a forged addons_data price does not reach the charge.
$service_id = 0;
$package_id = 0;
foreach ( get_posts( array( 'post_type' => 'wpss_service', 'post_status' => 'publish', 'posts_per_page' => 50, 'fields' => 'ids' ) ) as $id ) {
	$packages = get_post_meta( $id, '_wpss_packages', true );
	if ( ! is_array( $packages ) ) {
		continue;
	}
	foreach ( $packages as $index => $package ) {
		if ( (float) ( $package['price'] ?? 0 ) > 0 ) {
			$service_id = (int) $id;
			$package_id = (int) $index;
			break 2;
		}
	}
}

$buyer = 0;
if ( $service_id ) {
	$author = (int) get_post_field( 'post_author', $service_id );
	foreach ( get_users( array( 'fields' => 'ID', 'number' => 5, 'exclude' => array( $author ) ) ) as $uid ) {
		$buyer = (int) $uid;
		break;
	}
}

if ( ! $service_id || ! $buyer ) {
	echo "SKIP  runtime checks - no published service with a priced package and a second user\n";
} else {
	$svc     = new \WPSellServices\Checkout\CheckoutIntentService();
	$request = array( 'service_id' => $service_id, 'package_id' => $package_id );

	unset( $_POST['addons_data'], $_POST['addon_ids'] );
	$honest = $svc->resolve( $request, $buyer );

	$_POST['addons_data'] = wp_json_encode( array( array( 'id' => 0, 'name' => 'x', 'price' => -70 ) ) );
	$forged               = $svc->resolve( $request, $buyer );
	unset( $_POST['addons_data'] );

	$honest_amount = is_wp_error( $honest ) ? null : (float) $honest->amount;
	$forged_amount = is_wp_error( $forged ) ? null : (float) $forged->amount;

	$check(
		sprintf( 'a forged -70 add-on does not change the charge (honest %s, forged %s)', var_export( $honest_amount, true ), var_export( $forged_amount, true ) ),
		null !== $honest_amount && $honest_amount === $forged_amount
	);
}

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
