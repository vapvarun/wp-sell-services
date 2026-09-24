<?php
/**
 * A gateway the owner switched off cannot start new money.
 *
 * Run: wp eval-file tests/test-gateway-isolation-contract.php
 *
 * Every gateway registers its hooks unconditionally and deliberately so: an
 * order paid through Stripe last month must still be refundable after Stripe is
 * disabled, and its webhook must still land. That is correct.
 *
 * What was NOT gated was starting a payment. Each gateway already asked
 * is_enabled() before enqueuing scripts, so the visible half was guarded and
 * the reachable half was not - a logged-in caller could invoke create-payment
 * on a gateway with no credentials configured at all.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

// The runtime half is proven in a SUBPROCESS, because wp_send_json_error()
// calls wp_die() and exits the whole script - a try/catch cannot see it, and an
// in-process assertion would kill this file at the first refusal.
$run = static function ( string $php ): string {
	$cmd = 'wp eval ' . escapeshellarg( $php ) . ' 2>&1';
	return (string) shell_exec( $cmd );
};

$stub_src = '$g = new class() { public function is_enabled(): bool { return %s; } '
	. 'public function get_name(): string { return "Stub"; } }; '
	. 'wpss_gateway_require_enabled( $g ); echo "ALLOWED";';

$enabled_out  = $run( sprintf( $stub_src, 'true' ) );
$disabled_out = $run( sprintf( $stub_src, 'false' ) );

$check( 'an enabled gateway is allowed through', false !== strpos( $enabled_out, 'ALLOWED' ) );
$check( 'a disabled gateway is refused', false !== strpos( $disabled_out, 'wpss_gateway_disabled' ) );
$check( '  and never reaches the work', false === strpos( $disabled_out, 'ALLOWED' ) );

// Every buyer-facing money-starting handler must call the guard.
$expected = array(
	'src/Integrations/Stripe/StripeGateway.php'      => 2,
	'src/Integrations/PayPal/PayPalGateway.php'      => 2,
	'src/Integrations/Gateways/OfflineGateway.php'   => 1,
	'src/Integrations/Gateways/TestGateway.php'      => 1,
);

// The Test gateway checked nonce, login and WP_DEBUG but never its own
// switch, so on any WP_DEBUG site a buyer could mark a real order paid with
// the gateway turned off (Basecamp 10336397941). Prove it end to end: debug
// on, setting off, demo mode off - the handler must refuse before any work.
$test_off = 'add_filter( "pre_option_wpss_test_gateway_settings", fn() => array( "enabled" => "" ) ); '
	. 'add_filter( "pre_option_wpss_demo_payments", fn() => "no" ); '
	. '$u = get_users( array( "role" => "subscriber", "number" => 1, "fields" => "ID" ) ); wp_set_current_user( (int) ( $u[0] ?? 0 ) ); '
	. '$_POST["nonce"] = wp_create_nonce( "wpss_test_payment" ); '
	. '$g = new \WPSellServices\Integrations\Gateways\TestGateway(); '
	. 'echo $g->is_enabled() ? "ENABLED " : "DISABLED "; '
	. '$g->ajax_process_payment(); echo "REACHED";';
$test_off_out = $run( $test_off );

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
	$check( 'Test gateway reports disabled when its setting is off', false !== strpos( $test_off_out, 'DISABLED' ) );
	$check( 'Test gateway handler refuses when disabled (WP_DEBUG on)', false !== strpos( $test_off_out, 'wpss_gateway_disabled' ) );
} else {
	echo "SKIP  Test gateway runtime check - needs WP_DEBUG on to exercise the debug path\n";
}

foreach ( $expected as $rel => $count ) {
	$body = (string) file_get_contents( WPSS_PLUGIN_DIR . $rel );
	$check(
		sprintf( '%s guards %d handler(s)', basename( $rel ), $count ),
		substr_count( $body, 'wpss_gateway_require_enabled( $this )' ) === $count
	);
}

$pro = dirname( WPSS_PLUGIN_DIR ) . '/wp-sell-services-pro/src/Integrations/Razorpay/RazorpayGateway.php';

if ( file_exists( $pro ) ) {
	$check(
		'RazorpayGateway guards 2 handlers',
		substr_count( (string) file_get_contents( $pro ), 'wpss_gateway_require_enabled( $this )' ) === 2
	);
}

// Refunds and receipt review must NOT be gated - they serve historical orders.
$offline = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Integrations/Gateways/OfflineGateway.php' );
$admin_block = substr( $offline, (int) strpos( $offline, 'function ajax_admin_mark_paid' ) );
$admin_block = substr( $admin_block, 0, 2000 );
$check( 'admin mark-paid is NOT gated', false === strpos( $admin_block, 'wpss_gateway_require_enabled' ) );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
