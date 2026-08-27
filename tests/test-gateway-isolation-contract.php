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
);

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
