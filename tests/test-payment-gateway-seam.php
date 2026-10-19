<?php
/**
 * Payment gateway seam: core drives a gateway through its interface, never by name.
 * A stub gateway that only extends AbstractPaymentGateway gets safe REST defaults, the legacy
 * wpss_rest_* filters still work for it, and the settings card has a default shape.
 *
 * Run: wp eval-file tests/test-payment-gateway-seam.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

use WPSellServices\Checkout\CheckoutIntent;
use WPSellServices\Integrations\Gateways\AbstractPaymentGateway;

$fails = 0;
$check = static function ( string $label, bool $ok ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . "\n";
	$fails += $ok ? 0 : 1;
};

$stub = new class() extends AbstractPaymentGateway {
	public function get_id(): string {
		return 'acme_pay';
	}
	public function get_name(): string {
		return 'Acme Pay';
	}
	public function get_description(): string {
		return 'Pay with Acme.';
	}
	public function is_enabled(): bool {
		return true;
	}
	public function supports_currency( string $currency ): bool {
		return true;
	}
	public function init(): void {}
	public function create_payment( float $amount, string $currency, array $metadata = array() ): array {
		return array();
	}
	public function process_payment( string $payment_id ): array {
		return array();
	}
	public function process_refund( string $transaction_id, ?float $amount = null, string $reason = '' ): array {
		return array();
	}
	public function handle_webhook( array $payload ): array {
		return array();
	}
	public function get_settings_fields(): array {
		return array();
	}
	public function render_payment_form( float $amount, string $currency, int $order_id ): string {
		return '';
	}
};

$intent = CheckoutIntent::single( 1, 0, array(), 0.0, 10.0, 'USD', 1, array() );

$res = $stub->rest_create_intent( $intent, array() );
$check( 'with no handler a stub gateway answers REST create with unsupported_gateway', is_wp_error( $res ) && 'unsupported_gateway' === $res->get_error_code() );
$res = $stub->rest_confirm_payment( array( 'payment_id' => 'x' ) );
$check( 'with no handler a stub gateway answers REST confirm with unsupported_gateway', is_wp_error( $res ) && 'unsupported_gateway' === $res->get_error_code() );

add_filter( 'wpss_rest_create_payment_intent', static fn( $r, $g ) => $g === $GLOBALS['wpss_seam_stub'] ? new WP_REST_Response( array( 'ok' => 1 ), 201 ) : $r, 10, 2 );
add_filter( 'wpss_rest_confirm_payment', static fn( $r, $g ) => $g === $GLOBALS['wpss_seam_stub'] ? new WP_REST_Response( array( 'ok' => 2 ), 200 ) : $r, 10, 2 );
$GLOBALS['wpss_seam_stub'] = $stub;
$check( 'the legacy wpss_rest_create_payment_intent filter still drives it', 201 === $stub->rest_create_intent( $intent, array() )->get_status() );
$check( 'the legacy wpss_rest_confirm_payment filter still drives it', 200 === $stub->rest_confirm_payment( array() )->get_status() );

$card = $stub->get_settings_card();
$check( 'default settings card uses the gateway name, description and wpss_{id}_settings', is_array( $card ) && 'Acme Pay' === $card['title'] && 'wpss_acme_pay_settings' === $card['option_group'] );

// Every shipped gateway is built on the base, so none can miss a method of the contract.
$all = wpss()->get_payment_gateways();
$ok  = true;
foreach ( $all as $gw ) {
	$ok = $ok && $gw instanceof AbstractPaymentGateway;
}
$check( 'every registered gateway extends AbstractPaymentGateway', $ok && count( $all ) >= 3 );

// Settings screen: one card per registered gateway, in card order, with no core edit for a new one.
$plugin = wpss();
$prop   = new ReflectionProperty( $plugin, 'payment_gateways' );
$prop->setAccessible( true );
$saved = $prop->getValue( $plugin );
$prop->setValue( $plugin, $saved + array( 'acme_pay' => $stub ) );

$im      = $plugin->get_integration_manager();
$adapter = new ReflectionProperty( $im, 'active_adapter' );
$adapter->setAccessible( true );
$was = $adapter->getValue( $im );
$adapter->setValue( $im, $im->get_adapter( 'standalone' ) );

wp_set_current_user( 1 );
$settings = new WPSellServices\Admin\Settings();
$render   = new ReflectionMethod( $settings, 'render_gateway_cards' );
$render->setAccessible( true );
ob_start();
$render->invoke( $settings );
$html = ob_get_clean();
preg_match_all( '/data-gateway="([a-z_]+)"/', $html, $found );

$prop->setValue( $plugin, $saved );
$adapter->setValue( $im, $was );

$check( 'settings screen renders a registered stub gateway with no core edit', in_array( 'acme_pay', $found[1], true ) );
$check( 'stripe card prints before offline', array_search( 'stripe', $found[1], true ) < array_search( 'offline', $found[1], true ) );

echo $fails ? "{$fails} FAILED\n" : "All passed\n";
