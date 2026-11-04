<?php
/**
 * The order page always adds up: a gap between the lines and the Total gets its own line.
 *
 * Run: wp eval-file tests/test-line-items-explain-the-difference.php
 *
 * A buyer saw "Premium $75.00" above "Total $72.50" with nothing between them: a coupon (stored by Pro as
 * meta.discount) or a custom price changed what was charged and the page said nothing. The shared line-items partial
 * now shows the difference as "Discount" (below the lines) or "Price adjustment" (above), and nothing when it adds up.
 *
 * @package WPSellServices
 */

require_once __DIR__ . '/exit-on-fail.php';

global $wpdb;
$fails = 0;
$check = static function ( string $label, bool $ok, string $detail = '' ) use ( &$fails ) {
	echo ( $ok ? 'PASS  ' : 'FAIL  ' ) . $label . ( $ok || '' === $detail ? '' : ' - ' . $detail ) . "\n";
	$fails += $ok ? 0 : 1;
};

$orders = $wpdb->prefix . 'wpss_orders';
$ids    = array();
register_shutdown_function(
	static function () use ( $wpdb, $orders, &$ids ) {
		foreach ( $ids as $id ) {
			$wpdb->delete( $orders, array( 'id' => $id ) );
		}
	}
);
$make = static function ( float $subtotal, float $total, array $meta = array() ) use ( $wpdb, $orders, &$ids ): int {
	$wpdb->insert( $orders, array( 'order_number' => 'QA-LI-' . wp_generate_password( 6, false ), 'customer_id' => 1, 'vendor_id' => 1, 'service_id' => 0, 'platform' => 'standalone', 'status' => 'in_progress', 'payment_status' => 'paid', 'subtotal' => $subtotal, 'total' => $total, 'currency' => wpss_get_currency(), 'meta' => wp_json_encode( $meta ), 'created_at' => current_time( 'mysql', true ) ) );
	$ids[] = (int) $wpdb->insert_id;
	return (int) $wpdb->insert_id;
};
$render = static function ( int $id ): string {
	ob_start();
	wpss_get_template_part( 'order/line-items', '', array( 'wpss_order' => wpss_get_order( $id ) ) );
	return (string) ob_get_clean();
};
$money = static fn( float $v ) => esc_html( wpss_format_price( $v, wpss_get_currency() ) );

$exact = $render( $make( 100.0, 100.0 ) );
$check( 'lines that add up to the Total show no adjustment row', ! str_contains( $exact, 'wpss-line-items__adjust' ) );

$taxed = $render( $make( 100.0, 120.0, array( 'tax_amount' => 20.0, 'tax_included' => false ) ) );
$check( 'tax added on top is not mistaken for an adjustment', ! str_contains( $taxed, 'wpss-line-items__adjust' ) );

$incl = $render( $make( 100.0, 100.0, array( 'tax_amount' => 20.0, 'tax_included' => true ) ) );
$check( 'tax already inside the price is not mistaken for an adjustment', ! str_contains( $incl, 'wpss-line-items__adjust' ) );

$disc = $render( $make( 75.0, 72.5, array( 'discount' => 2.5 ) ) );
$check( 'a lower Total shows a Discount line with the difference', str_contains( $disc, 'wpss-line-items__adjust' ) && str_contains( $disc, 'Discount' ) && str_contains( $disc, $money( 2.5 ) ), $disc );
$check( 'the Discount sits in the body, above the Total row', strpos( $disc, 'wpss-line-items__adjust' ) < strpos( $disc, 'wpss-line-items__total' ) );

$custom = $render( $make( 799.0, 2172.24 ) );
$check( 'a higher Total shows a Price adjustment with the difference', str_contains( $custom, 'Price adjustment' ) && str_contains( $custom, $money( 1373.24 ) ), $custom );

$contract = $render( $make( 0.0, 0.0 ) );
$check( 'a zero-total contract parent shows no adjustment', ! str_contains( $contract, 'wpss-line-items__adjust' ) );

echo $fails ? "FAILED: {$fails}\n" : "OK\n";
