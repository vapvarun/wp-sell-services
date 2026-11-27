<?php
/**
 * One store currency, used uniformly (Basecamp 10551474179, owner 2026-11-27).
 *
 * One source (wpss_get_currency), one formatter (wpss_format_price) reading the owner's position,
 * decimals and separators; anything tied to an order shows the order's currency; the currency locks
 * after the first order; a shop route (WooCommerce, EDD, FluentCart) owns code and formatting.
 *
 * Run: wp eval-file tests/test-currency-uniform.php
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};
$plain = static fn( string $html ) => html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES );

// Pin the store to USD with the owner's saved 2 decimal places, whatever this site has.
$general = (array) get_option( 'wpss_general', array() );
$store   = static fn( array $over = array() ) => array_merge( $general, array( 'currency' => 'USD', 'currency_position' => 'before', 'thousand_separator' => ',', 'decimal_separator' => '.' ), $over );
$pin     = static fn( array $over = array() ) => add_filter( 'pre_option_wpss_general', $p = static fn() => $store( $over ) ) ? $p : null;
$unpin   = static fn( $p ) => remove_filter( 'pre_option_wpss_general', $p );
$places  = static fn() => 2;
add_filter( 'pre_option_wpss_decimal_places', $places );

// A. Order amounts in the order's currency.
$p = $pin();
$order = \WPSellServices\Models\ServiceOrder::from_db(
	(object) array(
	'id'             => 0,
	'order_number'   => 'T-1',
	'total'          => 50.0,
	'subtotal'       => 50.0,
	'addons_total'   => 0.0,
	'currency'       => 'EUR',
	'status'         => 'completed',
	'addons'         => '[]',
	'service_id'     => 0,
	'customer_id'    => 0,
	'vendor_id'      => 0,
	'created_at'     => current_time( 'mysql', true ),
	'completed_at'   => current_time( 'mysql', true ),
	'vendor_earning' => 40.0,
	)
);
foreach ( array( 'new-order', 'order-completed' ) as $email ) {
	ob_start();
	try {
		include WPSS_PLUGIN_DIR . "templates/emails/{$email}.php";
	} catch ( \Throwable $e ) {
		echo 'ERR ' . esc_html( $e->getMessage() );
	}
	$out = $plain( (string) ob_get_clean() );
	$check( "{$email} email shows a EUR order in euros on a USD store", str_contains( $out, '€50.00' ) && ! str_contains( $out, '$50.00' ), substr( $out, 0, 200 ) );
}
$view = (string) file_get_contents( WPSS_PLUGIN_DIR . 'templates/order/order-view.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$check( 'order view: Pay button and tip receipt pass the row\'s currency', str_contains( $view, 'wpss_format_price( (float) $order->total, (string) $order->currency )' ) && str_contains( $view, '(string) $tip_order_row->currency' ) );

// B. Lower-case and junk codes.
$check( "'usd' reads as \$ and 'jpy' has no decimals", '$' === wpss_get_currency_symbol( 'usd' ) && '¥1,200' === $plain( wpss_format_price( 1200, 'jpy' ) ) );
$settings = new \WPSellServices\Admin\Settings();
$saved    = $settings->sanitize_general_settings( array( 'currency' => 'dollars' ) );
$check( 'Settings refuses an unsupported code and keeps the previous one', 'USD' === ( $saved['currency'] ?? '' ), (string) ( $saved['currency'] ?? '' ) );

// C. The saved decimal places apply to the store currency only.
$check( 'USD store, saved 2 places: KWD order keeps 3, JPY order shows none', 'KD1.235' === $plain( wpss_format_price( 1.2345, 'KWD' ) ) && '¥1,200' === $plain( wpss_format_price( 1200, 'JPY' ) ) );
$unpin( $p );
$p = $pin( array( 'currency' => 'JPY' ) );
$check( 'JPY store with 2 places saved still shows no .00', '¥1,200' === $plain( wpss_format_price( 1200 ) ), $plain( wpss_format_price( 1200 ) ) );
$unpin( $p );

// Separators and positions, PHP and JS alike.
$p = $pin( array( 'currency' => 'EUR', 'currency_position' => 'after_space', 'thousand_separator' => '.', 'decimal_separator' => ',' ) );
$check( 'separators 1.234,56 and "after, with a space" in PHP', '1.234,56 €' === $plain( wpss_format_price( 1234.56 ) ), $plain( wpss_format_price( 1234.56 ) ) );
$js = wpss_money_js_config();
$check( 'the browser gets the same pattern, places and separators', '%s €' === $js['pattern'] && 2 === $js['decimals'] && '.' === $js['thousand'] && ',' === $js['decimal'], wp_json_encode( $js ) );
$node = trim( (string) shell_exec( 'command -v node' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
if ( '' !== $node ) {
	$script = 'global.window={wpssMoneyConfig:' . wp_json_encode( $js ) . '};global.document={addEventListener(){},querySelectorAll(){return []},getElementById(){return null},body:{}};try{require(' . wp_json_encode( WPSS_PLUGIN_DIR . 'assets/js/wpss-ui.js' ) . ')}catch(e){}process.stdout.write(window.wpssMoney.format(1234.56)+"|"+window.wpssMoney.format(-5)+"|"+window.wpssMoney.format(1200,{pattern:"¥%s",decimals:0,thousand:",",decimal:"."}))';
	$res    = (string) shell_exec( escapeshellarg( $node ) . ' -e ' . escapeshellarg( $script ) . ' 2>&1' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec
	$check( 'wpssMoney.format matches wpss_format_price', '1.234,56 €|-5,00 €|¥1,200' === $res, $res );
} else {
	echo "SKIP  node not available for the JS formatter check\n";
}
$unpin( $p );

// Lock after the first order (owner decision 1).
$p = $pin();
add_filter( 'wpss_currency_locked', $locked = static fn() => true );
$saved = $settings->sanitize_general_settings( array( 'currency' => 'EUR' ) );
$check( 'once an order exists Settings keeps the currency', 'USD' === ( $saved['currency'] ?? '' ) );
$check( '  the wizard refuses it with the same rule', is_wp_error( wpss_validate_store_currency( 'EUR' ) ) && 'USD' === wpss_validate_store_currency( 'usd' ) );
remove_filter( 'wpss_currency_locked', $locked );
add_filter( 'wpss_currency_locked', $open = static fn() => false );
$saved = $settings->sanitize_general_settings( array( 'currency' => 'eur' ) );
$check( '  the override filter allows a deliberate change', 'EUR' === ( $saved['currency'] ?? '' ) );
remove_filter( 'wpss_currency_locked', $open );
$unpin( $p );

// E. Manual orders have no currency of their own.
$manual = (string) file_get_contents( WPSS_PLUGIN_DIR . 'src/Admin/Pages/ManualOrderPage.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
$check( 'manual order: no second currency list, no POSTed currency', ! str_contains( $manual, 'wpss_manual_order_currencies' ) && ! str_contains( $manual, "\$_POST['currency']" ) );

// D. No hard-coded USD on a money path.
$usd = array();
foreach ( array( 'src/Services/MilestoneService.php', 'src/Services/ExtensionOrderService.php', 'src/Services/TippingService.php', 'src/Integrations/Stripe/StripeGateway.php', 'src/Integrations/PayPal/PayPalGateway.php' ) as $f ) {
	if ( preg_match( "/\\?\\?\\s*'(USD|usd)'/", (string) file_get_contents( WPSS_PLUGIN_DIR . $f ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$usd[] = $f;
	}
}
$check( 'no ?? \'USD\' fallback on order and gateway money paths', array() === $usd, implode( ', ', $usd ) );

// F. A method that cannot take the store currency is not offered (ZAR store, PayPal).
$p = $pin( array( 'currency' => 'ZAR' ) );
$paypal = new \WPSellServices\Integrations\PayPal\PayPalGateway();
$check( 'PayPal cannot take ZAR, so a ZAR store never lists it', ! $paypal->supports_currency( 'ZAR' ) && ! isset( wpss_get_checkout_gateways( 'ZAR' )['paypal'] ) );
$unpin( $p );

// G. A shop route owns the currency and its formatting. Last: these filters stay for the run.
$p = $pin();
wpss_follow_shop_currency(
	'Easy Digital Downloads',
	static fn() => array(
		'currency' => 'EUR',
		'position' => 'after_space',
		'decimals' => 2,
		'thousand' => '.',
		'decimal'  => ',',
	)
);
$check( 'shop route: wpss_get_currency() is the shop\'s', 'EUR' === wpss_get_currency() && 'Easy Digital Downloads' === wpss_currency_owner() );
$check( '  and prices follow its format: 50,00 €', '50,00 €' === $plain( wpss_format_price( 50 ) ), $plain( wpss_format_price( 50 ) ) );
$check( '  an order in another currency keeps that currency\'s own places', '1.200 ¥' === $plain( wpss_format_price( 1200, 'JPY' ) ), $plain( wpss_format_price( 1200, 'JPY' ) ) );
// The locked General fields show the shop's live format, not the saved one (QA bounce 2026-11-27).
require_once ABSPATH . 'wp-admin/includes/template.php';
$settings->register_settings();
ob_start();
do_settings_fields( 'wpss_general', 'wpss_general_section' );
$form  = (string) ob_get_clean();
$field = static function ( string $id ) use ( $form ): string {
	return preg_match( '/<(?:input|select)[^>]*\bid="' . preg_quote( $id, '/' ) . '"[^>]*>/', $form, $m ) ? $m[0] : '';
};
$check(
	'  the locked fields show the shop\'s separators, not the saved ones',
	str_contains( $field( 'thousand_separator' ), 'value="."' ) && str_contains( $field( 'decimal_separator' ), 'value=","' ),
	$field( 'thousand_separator' ) . ' ' . $field( 'decimal_separator' )
);
$check( '  and the shop\'s position', 1 === preg_match( '/<option value="after_space"\s+selected/', $form ) );
$check( '  Decimal Places is locked too', str_contains( $field( 'wpss_decimal_places' ), 'disabled' ), $field( 'wpss_decimal_places' ) );
remove_filter( 'pre_option_wpss_decimal_places', $places );
$keep = get_option( 'wpss_decimal_places', null );
update_option( 'wpss_decimal_places', 3 );
$check( '  a save with the disabled field absent keeps the stored places', 3 === (int) apply_filters( 'sanitize_option_wpss_decimal_places', null, 'wpss_decimal_places', null ) );
null === $keep ? delete_option( 'wpss_decimal_places' ) : update_option( 'wpss_decimal_places', $keep );
add_filter( 'pre_option_wpss_decimal_places', $places );
$unpin( $p );
remove_filter( 'pre_option_wpss_decimal_places', $places );

echo $fails ? "\n{$fails} FAILED\n" : "\nall passed\n";
